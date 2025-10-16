<?php
/**
 * Booking Model
 * รองรับการจองแบบหลายวันพร้อมระบบ ledger ความพร้อมใช้งาน
 */

require_once __DIR__ . '/BaseModel.php';

class CapacityConflictException extends Exception {}

class Booking extends BaseModel {
    private $packageCapacityCache = [];
    protected $table_name = "bookings";

    public $id;
    public $booking_code;
    public $user_id;
    public $package_id;
    public $pickup_date;
    public $return_date;
    public $pickup_time;
    public $return_time;
    public $rental_days;
    public $location;
    public $notes;
    public $total_price;
    public $status;
    public $created_at;
    public $error_code;

    // Constructor uses parent
    public function __construct($db) {
        parent::__construct($db);
    }

    /**
     * สร้างการจองใหม่ (พร้อมบันทึก ledger)
     */
    public function create() {
        $this->error_code = null;
        try {
            $this->conn->beginTransaction();

            // Lock package row to prevent concurrent overbooking
            $lockStmt = $this->conn->prepare('SELECT id FROM packages WHERE id = :package_id FOR UPDATE');
            $lockStmt->bindParam(':package_id', $this->package_id, PDO::PARAM_INT);
            $lockStmt->execute();
            if ($lockStmt->rowCount() === 0) {
                throw new Exception('Package not found while locking');
            }

            if (!$this->checkPackageAvailability(
                $this->package_id,
                $this->pickup_date,
                $this->return_date,
                null,
                true
            )) {
                $this->error_code = 'capacity_conflict';
                $usage = $this->getReservationUsageMap(
                    $this->package_id,
                    $this->pickup_date,
                    $this->return_date,
                    null,
                    true
                );
                $capacity = $this->getPackageCapacity($this->package_id);
                $this->logCapacityWarning(
                    $this->package_id,
                    $this->pickup_date,
                    $this->return_date,
                    $this->user_id,
                    $usage,
                    $capacity
                );
                throw new CapacityConflictException('Capacity exceeded during booking create');
            }

            $query = "INSERT INTO " . $this->table_name . " 
                      SET booking_code=:booking_code, user_id=:user_id, package_id=:package_id,
                          pickup_date=:pickup_date, return_date=:return_date,
                          pickup_time=:pickup_time, return_time=:return_time,
                          location=:location, notes=:notes, total_price=:total_price, status=:status";

            $stmt = $this->conn->prepare($query);

            $this->booking_code = $this->generateBookingCode();
            $this->pickup_time = $this->pickup_time ?: BOOKING_DEFAULT_PICKUP_TIME;
            $this->return_time = $this->return_time ?: BOOKING_DEFAULT_RETURN_TIME;
            $this->status = $this->status ?: 'pending';

            $stmt->bindParam(":booking_code", $this->booking_code);
            $stmt->bindParam(":user_id", $this->user_id, PDO::PARAM_INT);
            $stmt->bindParam(":package_id", $this->package_id, PDO::PARAM_INT);
            $stmt->bindParam(":pickup_date", $this->pickup_date);
            $stmt->bindParam(":return_date", $this->return_date);
            $stmt->bindParam(":pickup_time", $this->pickup_time);
            $stmt->bindParam(":return_time", $this->return_time);
            $stmt->bindParam(":location", $this->location);
            $stmt->bindParam(":notes", $this->notes);
            $stmt->bindParam(":total_price", $this->total_price);
            $stmt->bindParam(":status", $this->status);

            if (!$stmt->execute()) {
                throw new Exception('Failed to insert booking');
            }

            $this->id = (int)$this->conn->lastInsertId();

            $this->seedAvailabilityLedger();

            availability_cache_invalidate($this->package_id);

            $this->conn->commit();
            return true;
        } catch (CapacityConflictException $conflict) {
            $this->conn->rollBack();
            return false;
        } catch (Throwable $e) {
            $this->conn->rollBack();
            log_event('Booking create failed: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * ดึงการจองตาม ID
     */
    public function getById($id) {
        $query = "SELECT b.*, p.name as package_name, p.price as package_price,
                         u.first_name, u.last_name, u.email, u.phone, u.username
                  FROM " . $this->table_name . " b
                  LEFT JOIN packages p ON b.package_id = p.id
                  LEFT JOIN users u ON b.user_id = u.id
                  WHERE b.id = :id 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->hydrateFromRow($row);
            return $row;
        }

        return false;
    }

    /**
     * ดึงการจองตาม Booking Code
     */
    public function getByBookingCode($booking_code) {
        $query = "SELECT b.*, p.name as package_name, p.price as package_price,
                         u.first_name, u.last_name, u.email, u.phone, u.username
                  FROM " . $this->table_name . " b
                  LEFT JOIN packages p ON b.package_id = p.id
                  LEFT JOIN users u ON b.user_id = u.id
                  WHERE b.booking_code = :booking_code 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":booking_code", $booking_code);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->hydrateFromRow($row);
            return $row;
        }

        return false;
    }

    /**
     * ดึงประวัติการจองของผู้ใช้
     */
    public function getUserBookings($user_id, $limit = 10, $offset = 0) {
        $query = "SELECT b.*, p.name as package_name, p.price as package_price
                  FROM " . $this->table_name . " b
                  LEFT JOIN packages p ON b.package_id = p.id
                  WHERE b.user_id = :user_id
                  ORDER BY b.created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ดึงการจองทั้งหมด (สำหรับ admin)
     */
    public function getAllBookings($status = null, $limit = 50, $offset = 0) {
        $where_clause = "";
        if ($status) {
            $where_clause = "WHERE b.status = :status";
        }

        $query = "SELECT b.*, p.name as package_name, p.price as package_price,
                         u.first_name, u.last_name, u.email, u.phone, u.username
                  FROM " . $this->table_name . " b
                  LEFT JOIN packages p ON b.package_id = p.id
                  LEFT JOIN users u ON b.user_id = u.id
                  $where_clause
                  ORDER BY b.created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);

        if ($status) {
            $stmt->bindParam(":status", $status);
        }

        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * อัปเดตสถานะการจอง พร้อมจัดการ ledger
     */
    public function updateStatus($status) {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $this->status = $status;
            if (in_array($status, ['cancelled', 'completed'], true)) {
                $this->deleteAvailabilityLedger();
            }
            availability_cache_invalidate($this->package_id);
            return true;
        }

        return false;
    }

    public function cancel() {
        return $this->updateStatus('cancelled');
    }

    public function confirm() {
        return $this->updateStatus('confirmed');
    }

    /**
     * ตรวจสอบว่าผู้ใช้สามารถแก้ไขหรือยกเลิกการจองได้หรือไม่
     */
    public function customerCanModify(int $booking_id, int $user_id): array {
        $booking = $this->fetchBookingForCustomer($booking_id, $user_id, false);

        if (!$booking) {
            return [
                'allowed' => false,
                'reason' => 'ไม่พบการจองนี้หรือคุณไม่มีสิทธิ์เข้าถึง',
            ];
        }

        if ($booking['status'] !== 'pending') {
            return [
                'allowed' => false,
                'reason' => 'ไม่สามารถแก้ไขการจองสถานะ ' . $booking['status'],
            ];
        }

        if ($this->hasVerifiedPayment($booking['id'])) {
            return [
                'allowed' => false,
                'reason' => 'ไม่สามารถแก้ไขเมื่อมีการยืนยันการชำระเงินแล้ว',
            ];
        }

        return [
            'allowed' => true,
            'reason' => 'สามารถดำเนินการได้',
        ];
    }

    /**
     * อัปเดตรายละเอียดการจองโดยผู้ใช้ปลายทาง (รองรับแก้ไขวันที่ เวลา สถานที่ และโน้ต)
     */
    public function updateByCustomer(int $booking_id, int $user_id, array $payload): array {
        try {
            $this->conn->beginTransaction();

            $booking = $this->fetchBookingForCustomer($booking_id, $user_id, true);
            if (!$booking) {
                throw new Exception('ไม่พบการจองนี้หรือคุณไม่มีสิทธิ์แก้ไข');
            }

            if ($booking['status'] !== 'pending') {
                throw new Exception('ไม่สามารถแก้ไขการจองสถานะ ' . $booking['status']);
            }

            if ($this->hasVerifiedPayment($booking_id)) {
                throw new Exception('ไม่สามารถแก้ไขเมื่อมีการยืนยันการชำระเงินแล้ว');
            }

            $new_pickup_date = $payload['pickup_date'] ?? $booking['pickup_date'];
            $new_return_date = $payload['return_date'] ?? $booking['return_date'];
            $new_pickup_time = $payload['pickup_time'] ?? $booking['pickup_time'] ?? BOOKING_DEFAULT_PICKUP_TIME;
            $new_return_time = $payload['return_time'] ?? $booking['return_time'] ?? BOOKING_DEFAULT_RETURN_TIME;
            $new_location = isset($payload['location']) ? $this->sanitizeValue($payload['location']) : $booking['location'];
            $new_notes = isset($payload['notes']) ? $this->sanitizeValue($payload['notes']) : $booking['notes'];

            $validationData = [
                'package_id' => (int)$booking['package_id'],
                'pickup_date' => $new_pickup_date,
                'return_date' => $new_return_date,
                'pickup_time' => $new_pickup_time,
                'return_time' => $new_return_time,
                'location' => $new_location,
                'notes' => $new_notes,
            ];

            $errors = $this->validate($validationData);
            if (!empty($errors)) {
                throw new Exception(implode('\n', $errors));
            }

            $isAvailable = $this->checkPackageAvailability(
                (int)$booking['package_id'],
                $new_pickup_date,
                $new_return_date,
                $booking_id,
                true
            );

            if (!$isAvailable) {
                throw new Exception('แพ็คเกจไม่ว่างในช่วงวันที่ที่เลือก');
            }

            $pricing = $this->calculatePricingBreakdown((float)$booking['package_price'], $new_pickup_date, $new_return_date);
            $subtotal = $pricing['subtotal'];
            $vatAmount = $subtotal * VAT_RATE;
            $totalPrice = $subtotal + $vatAmount;

            $update = $this->conn->prepare(
                'UPDATE ' . $this->table_name . ' SET
                    pickup_date = :pickup_date,
                    return_date = :return_date,
                    pickup_time = :pickup_time,
                    return_time = :return_time,
                    location = :location,
                    notes = :notes,
                    total_price = :total_price,
                    updated_at = NOW()
                 WHERE id = :id'
            );

            $update->bindValue(':pickup_date', $new_pickup_date);
            $update->bindValue(':return_date', $new_return_date);
            $update->bindValue(':pickup_time', $new_pickup_time);
            $update->bindValue(':return_time', $new_return_time);
            $update->bindValue(':location', $new_location);
            $update->bindValue(':notes', $new_notes);
            $update->bindValue(':total_price', $totalPrice);
            $update->bindValue(':id', $booking_id, PDO::PARAM_INT);

            if (!$update->execute()) {
                throw new Exception('ไม่สามารถบันทึกข้อมูลการจองได้');
            }

            $this->hydrateFromRow(array_merge($booking, [
                'pickup_date' => $new_pickup_date,
                'return_date' => $new_return_date,
                'pickup_time' => $new_pickup_time,
                'return_time' => $new_return_time,
                'location' => $new_location,
                'notes' => $new_notes,
                'total_price' => $totalPrice,
            ]));

            $this->deleteAvailabilityLedger();
            $this->seedAvailabilityLedger();
            availability_cache_invalidate((int)$booking['package_id']);

            $this->conn->commit();

            log_event(sprintf('Customer updated booking %s (id=%d)', $this->booking_code, $booking_id), 'INFO');

            return [
                'success' => true,
                'booking' => $this->getById($booking_id),
            ];
        } catch (Throwable $e) {
            $this->conn->rollBack();
            log_event('Customer update booking error: ' . $e->getMessage(), 'ERROR');

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * ยกเลิกการจองโดยลูกค้า (รองรับเฉพาะสถานะ pending และยังไม่ยืนยันการชำระ)
     */
    public function cancelByCustomer(int $booking_id, int $user_id, ?string $reason = null): array {
        try {
            $this->conn->beginTransaction();

            $booking = $this->fetchBookingForCustomer($booking_id, $user_id, true);
            if (!$booking) {
                throw new Exception('ไม่พบการจองนี้หรือคุณไม่มีสิทธิ์ยกเลิก');
            }

            if ($booking['status'] !== 'pending') {
                throw new Exception('ไม่สามารถยกเลิกการจองสถานะ ' . $booking['status']);
            }

            if ($this->hasVerifiedPayment($booking_id)) {
                throw new Exception('ไม่สามารถยกเลิกเมื่อมีการยืนยันการชำระเงินแล้ว');
            }

            $this->hydrateFromRow($booking);

            if (!$this->updateStatus('cancelled')) {
                throw new Exception('ไม่สามารถเปลี่ยนสถานะการจองได้');
            }

            $this->conn->commit();

            $message = sprintf('Customer cancelled booking %s (id=%d)', $this->booking_code, $booking_id);
            if ($reason) {
                $message .= ' Reason: ' . $this->sanitizeValue($reason);
            }
            log_event($message, 'INFO');

            return [
                'success' => true,
                'message' => 'ยกเลิกการจองสำเร็จ',
            ];
        } catch (Throwable $e) {
            $this->conn->rollBack();
            log_event('Customer cancel booking error: ' . $e->getMessage(), 'ERROR');

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * สร้างรหัสการจองที่ไม่ซ้ำ
     */
    private function generateBookingCode() {
        do {
            $code = 'BK' . date('Ymd') . strtoupper(substr(uniqid('', true), -6));

            $query = "SELECT id FROM " . $this->table_name . " WHERE booking_code = :code LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":code", $code);
            $stmt->execute();

        } while ($stmt->rowCount() > 0);

        return $code;
    }

    /**
     * ตรวจสอบความถูกต้องของข้อมูลการจอง
     */
    public function validate($data) {
        $errors = [];

        $package_id = $data['package_id'] ?? null;
        $pickup_date = $data['pickup_date'] ?? null;
        $return_date = $data['return_date'] ?? null;
        $pickup_time = $data['pickup_time'] ?? BOOKING_DEFAULT_PICKUP_TIME;
        $return_time = $data['return_time'] ?? BOOKING_DEFAULT_RETURN_TIME;
        $location = $data['location'] ?? null;

        if (empty($package_id)) {
            $errors[] = "กรุณาเลือกแพ็คเกจ";
        }

        if (empty($pickup_date)) {
            $errors[] = "กรุณาเลือกวันรับอุปกรณ์";
        }

        if (empty($return_date)) {
            $errors[] = "กรุณาเลือกวันคืนอุปกรณ์";
        }

        if ($pickup_date && $return_date) {
            $pickup = DateTime::createFromFormat('Y-m-d', $pickup_date);
            $return = DateTime::createFromFormat('Y-m-d', $return_date);
            if (!$pickup || !$return) {
                $errors[] = "รูปแบบวันที่ไม่ถูกต้อง";
            } else {
                $today = new DateTime('today');
                if ($pickup < $today) {
                    $errors[] = "ไม่สามารถจองย้อนหลังได้";
                }
                if ($return < $pickup) {
                    $errors[] = "วันคืนต้องไม่น้อยกว่าวันรับ";
                }
            }
        }

        if ($pickup_time && !preg_match('/^\d{2}:\d{2}$/', $pickup_time)) {
            $errors[] = "รูปแบบเวลาเวลารับไม่ถูกต้อง";
        }

        if ($return_time && !preg_match('/^\d{2}:\d{2}$/', $return_time)) {
            $errors[] = "รูปแบบเวลากลับไม่ถูกต้อง";
        }

        if ($pickup_date === $return_date && $pickup_time && $return_time) {
            if (strtotime($pickup_time) >= strtotime($return_time)) {
                $errors[] = "เวลาคืนต้องมากกว่าเวลารับในวันเดียวกัน";
            }
        }

        if (empty($location)) {
            $errors[] = "กรุณาระบุสถานที่ใช้งาน";
        }

        return $errors;
    }

    /**
     * คำนวณราคาเช่าตามช่วงเวลาและกติกา
     */
    public function calculatePricingBreakdown($package_price, $pickup_date, $return_date) {
        $pickup = new DateTime($pickup_date);
        $return = new DateTime($return_date);
        $days = (int)$pickup->diff($return)->format('%a') + 1;

        $base = $package_price; // day 1
        $day2 = $days >= 2 ? $package_price * BOOKING_SURCHARGE_DAY2 : 0;

        $day3to6_count = max(0, min($days, 6) - 2);
        $day3to6 = $day3to6_count * $package_price * BOOKING_SURCHARGE_DAY3_TO6;

        $day7plus_count = max(0, $days - 6);
        $day7plus = $day7plus_count * $package_price * BOOKING_SURCHARGE_DAY7_PLUS;

        $weekend_surcharge = 0;
        $holidays = array_flip(get_configured_holidays());
        foreach ($this->generateDateSequence($pickup_date, $return_date) as $day) {
            $dateObj = new DateTime($day);
            $isWeekend = in_array((int)$dateObj->format('w'), [0, 6], true);
            $isHoliday = isset($holidays[$day]);
            if ($isWeekend || $isHoliday) {
                $weekend_surcharge += $package_price * BOOKING_WEEKEND_HOLIDAY_SURCHARGE;
            }
        }

        $subtotal = $base + $day2 + $day3to6 + $day7plus + $weekend_surcharge;

        return [
            'rental_days' => $days,
            'base_day' => $base,
            'day2_surcharge' => $day2,
            'day3_6_surcharge' => $day3to6,
            'day7_plus_surcharge' => $day7plus,
            'weekend_holiday_surcharge' => $weekend_surcharge,
            'subtotal' => $subtotal,
        ];
    }

    /**
     * ดึงการจองตามช่วงวันที่ (ซ้อนทับ)
     */
    public function getBookingsByDateRange($start_date, $end_date) {
        $query = "SELECT b.*, p.name as package_name, u.first_name, u.last_name
                  FROM " . $this->table_name . " b
                  LEFT JOIN packages p ON b.package_id = p.id
                  LEFT JOIN users u ON b.user_id = u.id
                  WHERE NOT (b.return_date < :start_date OR b.pickup_date > :end_date)
                  ORDER BY b.pickup_date ASC, b.created_at ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":start_date", $start_date);
        $stmt->bindParam(":end_date", $end_date);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBookingStatistics() {
        $query = "SELECT 
                        COUNT(*) AS total_bookings,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_bookings,
                        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) AS confirmed_bookings,
                        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) AS cancelled_bookings,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_bookings,
                        COALESCE(SUM(CASE WHEN status IN ('confirmed','completed') THEN total_price ELSE 0 END),0) AS total_revenue,
                        COALESCE(SUM(rental_days),0) AS total_rental_days
                  FROM " . $this->table_name;

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ตรวจสอบความพร้อมของแพ็คเกจ
     */
    public function checkPackageAvailability($package_id, $pickup_date, $return_date, $ignore_booking_id = null, $lockRows = false) {
        $capacity = $this->getPackageCapacity($package_id);
        $usageMap = $this->getReservationUsageMap(
            $package_id,
            $pickup_date,
            $return_date,
            $ignore_booking_id,
            $lockRows
        );

        foreach ($usageMap as $count) {
            if ($count >= $capacity) {
                return false;
            }
        }

        return true;
    }

    /**
     * ดึงข้อมูล availability ledger สำหรับ API
     */
    public function getAvailabilityWindow($package_id, $start_date, $end_date) {
        try {
            $query = "SELECT ea.date, ea.status, ea.booking_id, b.booking_code
                      FROM equipment_availability ea
                      LEFT JOIN bookings b ON ea.booking_id = b.id
                      WHERE ea.package_id = :package_id
                        AND ea.date BETWEEN :start AND :end
                      ORDER BY ea.date ASC";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":package_id", $package_id, PDO::PARAM_INT);
            $stmt->bindParam(":start", $start_date);
            $stmt->bindParam(":end", $end_date);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if (!$this->isMissingTableError($e)) {
                throw $e;
            }

            log_event('equipment_availability missing, deriving availability from bookings', 'WARNING');
            return $this->deriveAvailabilityFromBookings($package_id, $start_date, $end_date);
        }
    }

    private function hydrateFromRow(array $row) {
        $this->id = $row['id'];
        $this->booking_code = $row['booking_code'];
        $this->user_id = $row['user_id'];
        $this->package_id = $row['package_id'];
        $this->pickup_date = $row['pickup_date'];
        $this->return_date = $row['return_date'];
        $this->pickup_time = $row['pickup_time'];
        $this->return_time = $row['return_time'];
        $this->rental_days = $row['rental_days'];
        $this->location = $row['location'];
        $this->notes = $row['notes'];
        $this->total_price = $row['total_price'];
        $this->status = $row['status'];
        $this->created_at = $row['created_at'];
    }

    private function seedAvailabilityLedger() {
        $insert = "INSERT INTO equipment_availability (package_id, booking_id, date, status)
                   VALUES (:package_id, :booking_id, :date, 'reserved')";
        $stmt = $this->conn->prepare($insert);
        $stmt->bindParam(":package_id", $this->package_id, PDO::PARAM_INT);
        $stmt->bindParam(":booking_id", $this->id, PDO::PARAM_INT);

        foreach ($this->generateDateSequence($this->pickup_date, $this->return_date) as $date) {
            $stmt->bindValue(":date", $date);
            if (!$stmt->execute()) {
                throw new Exception('Failed to write availability ledger');
            }
        }
    }

    private function deleteAvailabilityLedger() {
        $stmt = $this->conn->prepare("DELETE FROM equipment_availability WHERE booking_id = :booking_id");
        $stmt->bindParam(":booking_id", $this->id, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function getPackageCapacity($package_id) {
        if (!isset($this->packageCapacityCache[$package_id])) {
            $query = "SELECT COALESCE(max_concurrent_reservations, 1) AS capacity
                      FROM packages
                      WHERE id = :package_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":package_id", $package_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->packageCapacityCache[$package_id] = (int)($result['capacity'] ?? 1);
        }

        return max(1, (int)$this->packageCapacityCache[$package_id]);
    }

    private function generateDateSequence($start, $end) {
        $dates = [];
        $current = new DateTime($start);
        $limit = new DateTime($end);

        while ($current <= $limit) {
            $dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        return $dates;
    }

    private function isMissingTableError(PDOException $e) {
        if ($e->getCode() === '42S02') {
            return true;
        }
        $message = $e->getMessage();
        return strpos($message, '1146') !== false;
    }

	private function deriveAvailabilityFromBookings($package_id, $start_date, $end_date) {
		$query = "SELECT id, booking_code, pickup_date, return_date, status
		          FROM " . $this->table_name . "
		          WHERE package_id = :package_id
		            AND NOT (return_date < :start OR pickup_date > :end)";

		try {
			$stmt = $this->conn->prepare($query);
			$stmt->bindParam(":package_id", $package_id, PDO::PARAM_INT);
			$stmt->bindParam(":start", $start_date);
			$stmt->bindParam(":end", $end_date);
			$stmt->execute();

			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			if (!$this->isMissingColumnError($e)) {
				throw $e;
			}

			$legacyQuery = "SELECT id, booking_code, booking_date, status
						 FROM " . $this->table_name . "
						 WHERE package_id = :package_id
						   AND booking_date BETWEEN :start AND :end";

			$stmt = $this->conn->prepare($legacyQuery);
			$stmt->bindParam(":package_id", $package_id, PDO::PARAM_INT);
			$stmt->bindParam(":start", $start_date);
			$stmt->bindParam(":end", $end_date);
			$stmt->execute();

			$rows = array_map(function ($row) {
				$row['pickup_date'] = $row['booking_date'];
				$row['return_date'] = $row['booking_date'];
				unset($row['booking_date']);
				return $row;
			}, $stmt->fetchAll(PDO::FETCH_ASSOC));
		}

		$results = [];

		foreach ($rows as $row) {
			$status = $this->mapBookingStatusToAvailability($row['status']);
			if ($status === null) {
				continue;
			}

			$pickup = $row['pickup_date'];
			$return = $row['return_date'];
			if (!$pickup || !$return) {
				continue;
			}

			foreach ($this->generateDateSequence(
				max($pickup, $start_date),
				min($return, $end_date)
			) as $date) {
				$results[] = [
					'date' => $date,
					'status' => $status,
					'booking_id' => (int)$row['id'],
					'booking_code' => $row['booking_code'],
				];
			}
		}

		usort($results, function ($a, $b) {
			return strcmp($a['date'], $b['date']);
		});

		return $results;
	}

	private function mapBookingStatusToAvailability($status) {
		switch ($status) {
			case 'pending':
			case 'confirmed':
				return 'reserved';
			case 'completed':
				return 'returned';
			case 'cancelled':
			default:
				return null;
		}
	}

    public function logCapacityWarning($package_id, $pickup_date, $return_date, $user_id = null, ?array $usageMap = null, ?int $capacity = null) {
        if ($usageMap === null) {
            $usageMap = $this->getReservationUsageMap($package_id, $pickup_date, $return_date);
        }
        if ($capacity === null) {
            $capacity = $this->getPackageCapacity($package_id);
        }

        $message = sprintf(
            'Booking capacity exceeded: package_id=%d, range=%s..%s, usage=%s, capacity=%d%s',
            $package_id,
            $pickup_date,
            $return_date,
            json_encode($usageMap, JSON_UNESCAPED_SLASHES),
            $capacity,
            $user_id ? ', user_id=' . $user_id : ''
        );

        log_event($message, 'WARNING');
    }

    private function fetchBookingForCustomer(int $booking_id, int $user_id, bool $forUpdate = false): ?array {
        $query = "SELECT b.*, p.price AS package_price
                  FROM " . $this->table_name . " b
                  INNER JOIN packages p ON b.package_id = p.id
                  WHERE b.id = :booking_id AND b.user_id = :user_id
                  LIMIT 1";

        if ($forUpdate) {
            $query .= ' FOR UPDATE';
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':booking_id', $booking_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function hasVerifiedPayment(int $booking_id): bool {
        $stmt = $this->conn->prepare('SELECT 1 FROM payments WHERE booking_id = :booking_id AND status = "verified" LIMIT 1');
        $stmt->bindValue(':booking_id', $booking_id, PDO::PARAM_INT);
        $stmt->execute();

        return (bool)$stmt->fetchColumn();
    }

    private function sanitizeValue(string $value): string {
        if (function_exists('sanitize_input')) {
            return sanitize_input($value);
        }

        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }

    private function getReservationUsageMap($package_id, $start_date, $end_date, $ignore_booking_id = null, $lockRows = false) {
        $usage = [];
        foreach ($this->generateDateSequence($start_date, $end_date) as $date) {
            $usage[$date] = 0;
        }

        try {
            $query = "SELECT date, COUNT(*) as reserved_count
                      FROM equipment_availability
                      WHERE package_id = :package_id
                        AND date BETWEEN :start AND :end
                        AND status IN ('reserved', 'picked_up', 'maintenance')";

            if ($ignore_booking_id) {
                $query .= " AND (booking_id IS NULL OR booking_id <> :ignore_booking_id)";
            }

            $query .= " GROUP BY date";
            if ($lockRows) {
                $query .= " FOR UPDATE";
            }

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":package_id", $package_id, PDO::PARAM_INT);
            $stmt->bindParam(":start", $start_date);
            $stmt->bindParam(":end", $end_date);
            if ($ignore_booking_id) {
                $stmt->bindParam(":ignore_booking_id", $ignore_booking_id, PDO::PARAM_INT);
            }
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $usage[$row['date']] = (int)$row['reserved_count'];
            }

            return $usage;
        } catch (PDOException $e) {
            if (!$this->isMissingTableError($e)) {
                throw $e;
            }
            log_event('equipment_availability missing, deriving usage from bookings', 'WARNING');
            // fall back to derived data
        }

        $derived = $this->deriveAvailabilityFromBookings($package_id, $start_date, $end_date);
        foreach ($derived as $row) {
            if ($ignore_booking_id && $row['booking_id'] === (int)$ignore_booking_id) {
                continue;
            }
            if (!in_array($row['status'], ['reserved', 'picked_up', 'maintenance'], true)) {
                continue;
            }
            if (!isset($usage[$row['date']])) {
                $usage[$row['date']] = 0;
            }
            $usage[$row['date']]++;
        }

        return $usage;
    }

	private function isMissingColumnError(PDOException $e) {
		if ($e->getCode() === '42S22') {
			return true;
		}
		return strpos($e->getMessage(), '1054') !== false;
	}
}

?>
