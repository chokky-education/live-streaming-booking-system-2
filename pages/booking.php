<?php
/**
 * หน้าจองอุปกรณ์
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../models/Package.php';
require_once '../models/Booking.php';

// ตรวจสอบการเข้าสู่ระบบ
require_login();

$error_message = '';
$success_message = '';
$selected_package_id = isset($_GET['package']) ? (int)$_GET['package'] : 0;

try {
    $database = new Database();
    $db = $database->getConnection();
    $package = new Package($db);
    $booking = new Booking($db);
    
    // ดึงข้อมูลแพ็คเกจ
    $packages = $package->getActivePackages();
    $selected_package = null;
    
    if ($selected_package_id > 0) {
        if ($package->getById($selected_package_id)) {
            $selected_package = [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'price' => $package->price,
                'equipment_list' => $package->equipment_list,
                'max_concurrent_reservations' => $package->max_concurrent_reservations
            ];
        }
    }
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event("Booking page error: " . $e->getMessage(), 'ERROR');
}

// ประมวลผลการจอง
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_booking'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid CSRF token';
    } else {
        $booking_data = [
            'package_id'   => (int)$_POST['package_id'],
            'pickup_date'  => $_POST['pickup_date'] ?? null,
            'return_date'  => $_POST['return_date'] ?? null,
            'pickup_time'  => $_POST['pickup_time'] ?? BOOKING_DEFAULT_PICKUP_TIME,
            'return_time'  => $_POST['return_time'] ?? BOOKING_DEFAULT_RETURN_TIME,
            'location'     => sanitize_input($_POST['location']),
            'notes'        => sanitize_input($_POST['notes'])
        ];
        
        try {
            // ตรวจสอบความถูกต้องของข้อมูล
            $validation_errors = $booking->validate($booking_data);
            
            if (!empty($validation_errors)) {
                $error_message = implode('<br>', $validation_errors);
            } else {
                // ตรวจสอบความพร้อมของแพ็คเกจ
                $is_available = $booking->checkPackageAvailability(
                    $booking_data['package_id'],
                    $booking_data['pickup_date'],
                    $booking_data['return_date']
                );

                if (!$is_available) {
                    $booking->logCapacityWarning(
                        $booking_data['package_id'],
                        $booking_data['pickup_date'],
                        $booking_data['return_date'],
                        $_SESSION['user_id'] ?? null
                    );
                    $error_message = 'แพ็คเกจนี้ไม่ว่างในช่วงวันที่เลือก กรุณาเลือกช่วงอื่น';
                } else {
                    // คำนวณราคา
                    $package->getById($booking_data['package_id']);
                    $pricing_breakdown = $booking->calculatePricingBreakdown(
                        $package->price,
                        $booking_data['pickup_date'],
                        $booking_data['return_date']
                    );
                    $subtotal = $pricing_breakdown['subtotal'];
                    $vat_amount = $subtotal * VAT_RATE;
                    $total_price = $subtotal + $vat_amount;
                    
                    // สร้างการจอง
                    $booking->user_id = $_SESSION['user_id'];
                    $booking->package_id = $booking_data['package_id'];
                    $booking->pickup_date = $booking_data['pickup_date'];
                    $booking->return_date = $booking_data['return_date'];
                    $booking->pickup_time = $booking_data['pickup_time'];
                    $booking->return_time = $booking_data['return_time'];
                    $booking->location = $booking_data['location'];
                    $booking->notes = $booking_data['notes'];
                    $booking->total_price = $total_price;
                    $booking->status = 'pending';
                    
                    if ($booking->create()) {
                        $success_message = 'จองสำเร็จ! รหัสการจอง: ' . $booking->booking_code;
                        log_event("New booking created: {$booking->booking_code} by user {$_SESSION['username']}", 'INFO');
                        
                        // Redirect ไปหน้าชำระเงิน
                        header("refresh:2;url=payment.php?booking=" . $booking->booking_code);
                    } else {
                        if ($booking->error_code === 'capacity_conflict') {
                            $error_message = 'แพ็คเกจนี้ไม่ว่างในช่วงวันที่เลือก กรุณาเลือกช่วงอื่น';
                        } else {
                            $error_message = 'เกิดข้อผิดพลาดในการจอง กรุณาลองใหม่อีกครั้ง';
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $error_message = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
            log_event("Booking creation error: " . $e->getMessage(), 'ERROR');
        }
    }
}

$recent_request = $_POST ?? [];
$quote_preview = $pricing_breakdown ?? null;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จองอุปกรณ์ - ระบบจองอุปกรณ์ Live Streaming</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background: #f8f9fa;
        }
        
        .booking-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 2rem 0;
        }
        
        .step {
            display: flex;
            align-items: center;
            margin: 0 1rem;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 0.5rem;
        }
        
        .step.active .step-number {
            background: #667eea;
            color: white;
        }
        
        .step.completed .step-number {
            background: #28a745;
            color: white;
        }
        
        .package-card {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .package-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
        }
        
        .package-card.selected {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .package-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 1.5rem;
            border-radius: 13px 13px 0 0;
            text-align: center;
        }
        
        .package-price {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .equipment-list {
            list-style: none;
            padding: 0;
        }
        
        .equipment-list li {
            padding: 0.3rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .equipment-list li:last-child {
            border-bottom: none;
        }
        
        .equipment-list i {
            color: #28a745;
            margin-right: 8px;
        }
        
        .booking-form {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-booking {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-booking:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .price-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        @media (max-width: 768px) {
            .step-indicator {
                flex-direction: column;
                align-items: center;
            }
            
            .step {
                margin: 0.5rem 0;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="booking-header">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h1><i class="fas fa-calendar-check me-2"></i>จองอุปกรณ์ Live Streaming</h1>
                    <p class="mb-0">เลือกแพ็คเกจและกำหนดรายละเอียดการจอง</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Step Indicator -->
    <div class="container">
        <div class="step-indicator">
            <div class="step active">
                <div class="step-number">1</div>
                <span>เลือกแพ็คเกจ</span>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <span>กรอกข้อมูล</span>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <span>ชำระเงิน</span>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Package Selection -->
            <div class="col-lg-8">
                <div class="booking-form">
                    <h3 class="mb-4"><i class="fas fa-box me-2"></i>เลือกแพ็คเกจ</h3>
                    
                    <div class="row" id="packageSelection">
                        <?php foreach ($packages as $pkg): ?>
                            <div class="col-md-4 mb-3">
                                <div class="package-card <?php echo ($selected_package_id == $pkg['id']) ? 'selected' : ''; ?>" 
                                     data-package-id="<?php echo $pkg['id']; ?>"
                                     data-package-name="<?php echo htmlspecialchars($pkg['name']); ?>"
                                     data-package-price="<?php echo $pkg['price']; ?>"
                                     data-package-capacity="<?php echo (int)($pkg['max_concurrent_reservations'] ?? 1); ?>">
                                    <div class="package-header">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($pkg['name']); ?></h5>
                                        <div class="package-price"><?php echo format_currency($pkg['price']); ?></div>
                                        <small>ต่อวัน</small>
                                    </div>
                                    <div class="p-3">
                                        <p class="text-muted mb-3"><?php echo htmlspecialchars($pkg['description']); ?></p>
                                        <p class="small text-secondary mb-3">รองรับการจองพร้อมกันสูงสุด <?php echo (int)($pkg['max_concurrent_reservations'] ?? 1); ?> คิว/วัน</p>
                                        <ul class="equipment-list">
                                            <?php 
                                            $equipment = json_decode($pkg['equipment_list'], true);
                                            foreach ($equipment as $item): 
                                            ?>
                                                <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($item); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Booking Form -->
                    <form method="POST" action="" id="bookingForm" style="display: <?php echo $selected_package ? 'block' : 'none'; ?>;">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="package_id" id="selectedPackageId" value="<?php echo $selected_package_id; ?>">
                        
                        <hr class="my-4">
                        <h4 class="mb-3"><i class="fas fa-edit me-2"></i>รายละเอียดการจอง</h4>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="pickup_date" class="form-label">วันที่รับอุปกรณ์ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="pickup_date" name="pickup_date"
                                       value="<?php echo htmlspecialchars($recent_request['pickup_date'] ?? ''); ?>"
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="return_date" class="form-label">วันที่คืนอุปกรณ์ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="return_date" name="return_date"
                                       value="<?php echo htmlspecialchars($recent_request['return_date'] ?? ''); ?>"
                                       min="<?php echo htmlspecialchars($recent_request['pickup_date'] ?? date('Y-m-d')); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="pickup_time" class="form-label">เวลารับ</label>
                                <input type="time" class="form-control" id="pickup_time" name="pickup_time"
                                       value="<?php echo htmlspecialchars($recent_request['pickup_time'] ?? BOOKING_DEFAULT_PICKUP_TIME); ?>">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="return_time" class="form-label">เวลาคืน</label>
                                <input type="time" class="form-control" id="return_time" name="return_time"
                                       value="<?php echo htmlspecialchars($recent_request['return_time'] ?? BOOKING_DEFAULT_RETURN_TIME); ?>">
                            </div>

                            <div class="col-md-6 mb-3 d-flex align-items-end">
                                <div class="w-100">
                                    <label class="form-label">สถานะความพร้อม</label>
                                    <div id="availabilityNotice" class="alert alert-info mb-0" role="alert" style="display: none;"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="location" class="form-label">สถานที่ใช้งาน <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="location" name="location" rows="3" 
                                      placeholder="กรุณาระบุสถานที่ที่จะใช้งานอุปกรณ์" required><?php echo isset($recent_request['location']) ? htmlspecialchars($recent_request['location']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">หมายเหตุเพิ่มเติม</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="ข้อมูลเพิ่มเติมหรือความต้องการพิเศษ (ไม่บังคับ)"><?php echo isset($recent_request['notes']) ? htmlspecialchars($recent_request['notes']) : ''; ?></textarea>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="create_booking" class="btn btn-primary btn-booking">
                                <i class="fas fa-calendar-check me-2"></i>
                                ยืนยันการจอง
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Booking Summary -->
            <div class="col-lg-4">
                <div class="booking-form">
                    <h4 class="mb-3"><i class="fas fa-receipt me-2"></i>สรุปการจอง</h4>
                    
                    <div id="bookingSummary" style="display: <?php echo $selected_package ? 'block' : 'none'; ?>;">
                        <div class="mb-3">
                            <strong>แพ็คเกจที่เลือก:</strong>
                            <div id="selectedPackageName"><?php echo $selected_package ? htmlspecialchars($selected_package['name']) : ''; ?></div>
                            <div class="small text-secondary" id="selectedPackageCapacity">
                                <?php if ($selected_package) : ?>
                                    รองรับการจองพร้อมกันสูงสุด <?php echo (int)($selected_package['max_concurrent_reservations'] ?? 1); ?> คิวต่อวัน
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="price-summary">
                            <div class="d-flex justify-content-between mb-2">
                                <span>ราคาแพ็คเกจ (วันแรก):</span>
                                <span id="summaryBaseDay">-</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>จำนวนวันเช่า:</span>
                                <span id="summaryRentalDays">-</span>
                            </div>
                            <div class="mb-2 small text-muted">
                                <div class="d-flex justify-content-between"><span>บวกวันที 2 (+40%)</span><span id="summaryDay2">-</span></div>
                                <div class="d-flex justify-content-between"><span>วันที 3-6 (+20%/วัน)</span><span id="summaryDay3to6">-</span></div>
                                <div class="d-flex justify-content-between"><span>วันที 7+ (+10%/วัน)</span><span id="summaryDay7plus">-</span></div>
                                <div class="d-flex justify-content-between"><span>เสาร์-อาทิตย์/วันหยุด (+10%/วัน)</span><span id="summaryWeekend">-</span></div>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>ราคารวมก่อน VAT:</span>
                                <span id="summarySubtotal">-</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>VAT (<?php echo number_format(VAT_RATE * 100, 0); ?>%):</span>
                                <span id="summaryVat">-</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong>ราคารวมสุทธิ:</strong>
                                <strong id="summaryTotal">-</strong>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                ต้องชำระมัดจำ 50% ของราคารวม
                            </small>
                        </div>
                    </div>
                    
                    <div id="noPackageSelected" style="display: <?php echo $selected_package ? 'none' : 'block'; ?>;">
                        <p class="text-muted text-center">
                            <i class="fas fa-arrow-left me-2"></i>
                            กรุณาเลือกแพ็คเกจ
                        </p>
                    </div>
                </div>
                
                <!-- User Info -->
                <div class="booking-form mt-3">
                    <h5 class="mb-3"><i class="fas fa-user me-2"></i>ข้อมูลผู้จอง</h5>
                    <p class="mb-1"><strong>ชื่อ:</strong> <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
                    <p class="mb-0"><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="container mb-4">
        <div class="d-flex justify-content-between">
            <a href="../index.html" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>กลับหน้าแรก
            </a>
            <a href="profile.php" class="btn btn-outline-primary">
                <i class="fas fa-user me-2"></i>โปรไฟล์
            </a>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const HOLIDAYS = new Set(<?php echo json_encode(get_configured_holidays()); ?>);
        const VAT_RATE = <?php echo (float)VAT_RATE; ?>;
        const PRICING_RULES = {
            day2: <?php echo (float)BOOKING_SURCHARGE_DAY2; ?>,
            day3to6: <?php echo (float)BOOKING_SURCHARGE_DAY3_TO6; ?>,
            day7plus: <?php echo (float)BOOKING_SURCHARGE_DAY7_PLUS; ?>,
            weekend: <?php echo (float)BOOKING_WEEKEND_HOLIDAY_SURCHARGE; ?>
        };

        const DEFAULT_PICKUP_TIME = '<?php echo BOOKING_DEFAULT_PICKUP_TIME; ?>';
        const DEFAULT_RETURN_TIME = '<?php echo BOOKING_DEFAULT_RETURN_TIME; ?>';

        const packageCards = document.querySelectorAll('.package-card');
        const bookingForm = document.getElementById('bookingForm');
        const bookingSummary = document.getElementById('bookingSummary');
        const noPackageSelected = document.getElementById('noPackageSelected');
        const availabilityNotice = document.getElementById('availabilityNotice');
        const capacityField = document.getElementById('selectedPackageCapacity');

        const pickupDateInput = document.getElementById('pickup_date');
        const returnDateInput = document.getElementById('return_date');
        const pickupTimeInput = document.getElementById('pickup_time');
        const returnTimeInput = document.getElementById('return_time');

        const summaryFields = {
            baseDay: document.getElementById('summaryBaseDay'),
            rentalDays: document.getElementById('summaryRentalDays'),
            day2: document.getElementById('summaryDay2'),
            day3to6: document.getElementById('summaryDay3to6'),
            day7plus: document.getElementById('summaryDay7plus'),
            weekend: document.getElementById('summaryWeekend'),
            subtotal: document.getElementById('summarySubtotal'),
            vat: document.getElementById('summaryVat'),
            total: document.getElementById('summaryTotal'),
        };

        let selectedPackage = {
            id: document.getElementById('selectedPackageId').value || null,
            name: document.getElementById('selectedPackageName').textContent,
            price: null,
            capacity: null
        };

        packageCards.forEach(card => {
            card.addEventListener('click', () => {
                packageCards.forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');

                selectedPackage = {
                    id: card.dataset.packageId,
                    name: card.dataset.packageName,
                    price: parseFloat(card.dataset.packagePrice),
                    capacity: parseInt(card.dataset.packageCapacity, 10) || 1
                };

                document.getElementById('selectedPackageId').value = selectedPackage.id;
                document.getElementById('selectedPackageName').textContent = selectedPackage.name;
                if (capacityField) {
                    capacityField.textContent = `รองรับการจองพร้อมกันสูงสุด ${selectedPackage.capacity} คิวต่อวัน`;
                }

                bookingForm.style.display = 'block';
                bookingSummary.style.display = 'block';
                noPackageSelected.style.display = 'none';

                updateSummary();
            });
        });

        [pickupDateInput, returnDateInput, pickupTimeInput, returnTimeInput].forEach(input => {
            if (!input) return;
            input.addEventListener('change', () => {
                if (input === pickupDateInput && returnDateInput.value) {
                    if (returnDateInput.value < pickupDateInput.value) {
                        returnDateInput.value = pickupDateInput.value;
                    }
                    returnDateInput.min = pickupDateInput.value || '<?php echo date('Y-m-d'); ?>';
                }
                updateSummary();
            });
        });

        function formatCurrency(amount) {
            if (amount === null || isNaN(amount)) {
                return '-';
            }
            return new Intl.NumberFormat('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount) + ' บาท';
        }

        function calculatePricing(basePrice, pickupDate, returnDate) {
            if (!basePrice || !pickupDate || !returnDate) {
                return null;
            }
            const start = new Date(pickupDate);
            const end = new Date(returnDate);
            if (isNaN(start.getTime()) || isNaN(end.getTime()) || end < start) {
                return null;
            }

            const msPerDay = 86400000;
            const rentalDays = Math.floor((end - start) / msPerDay) + 1;
            const baseDay = basePrice;
            const day2 = rentalDays >= 2 ? basePrice * PRICING_RULES.day2 : 0;
            const day3to6Count = Math.max(0, Math.min(rentalDays, 6) - 2);
            const day3to6 = day3to6Count * basePrice * PRICING_RULES.day3to6;
            const day7plusCount = Math.max(0, rentalDays - 6);
            const day7plus = day7plusCount * basePrice * PRICING_RULES.day7plus;

            let weekend = 0;
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                const iso = d.toISOString().slice(0, 10);
                const day = d.getDay();
                if (day === 0 || day === 6 || HOLIDAYS.has(iso)) {
                    weekend += basePrice * PRICING_RULES.weekend;
                }
            }

            const subtotal = baseDay + day2 + day3to6 + day7plus + weekend;
            const vat = subtotal * VAT_RATE;
            const total = subtotal + vat;

            return { rentalDays, baseDay, day2, day3to6, day7plus, weekend, subtotal, vat, total };
        }

        let availabilityTimeout = null;

        function updateSummary() {
            const pickupDate = pickupDateInput.value;
            const returnDate = returnDateInput.value;
            const basePrice = selectedPackage.price || (selectedPackage.id ? parseFloat(getPackagePrice(selectedPackage.id)) : null);

            const pricing = calculatePricing(basePrice, pickupDate, returnDate);

            if (!pricing) {
                setSummaryPlaceholders();
            } else {
                summaryFields.baseDay.textContent = formatCurrency(pricing.baseDay);
                summaryFields.rentalDays.textContent = `${pricing.rentalDays} วัน`;
                summaryFields.day2.textContent = formatCurrency(pricing.day2);
                summaryFields.day3to6.textContent = formatCurrency(pricing.day3to6);
                summaryFields.day7plus.textContent = formatCurrency(pricing.day7plus);
                summaryFields.weekend.textContent = formatCurrency(pricing.weekend);
                summaryFields.subtotal.textContent = formatCurrency(pricing.subtotal);
                summaryFields.vat.textContent = formatCurrency(pricing.vat);
                summaryFields.total.textContent = formatCurrency(pricing.total);
            }

            if (selectedPackage.id) {
                if (availabilityTimeout) {
                    clearTimeout(availabilityTimeout);
                }
                availabilityTimeout = setTimeout(() => {
                    fetchAvailability(selectedPackage.id, pickupDate || null, returnDate || null);
                }, 250);
            } else {
                availabilityNotice.style.display = 'none';
            }
        }

        function setSummaryPlaceholders() {
            Object.values(summaryFields).forEach(field => {
                field.textContent = '-';
            });
        }

        function getPackagePrice(packageId) {
            const card = document.querySelector(`.package-card[data-package-id="${packageId}"]`);
            return card ? card.dataset.packagePrice : null;
        }

        async function fetchAvailability(packageId, start, end) {
            if (!availabilityNotice) {
                return;
            }

            availabilityNotice.style.display = 'block';
            availabilityNotice.classList.remove('alert-danger', 'alert-info');
            availabilityNotice.classList.add('alert-info');
            availabilityNotice.textContent = 'กำลังตรวจสอบสถานะความพร้อม...';

            try {
                const params = new URLSearchParams({ package_id: packageId });
                const hasRange = Boolean(start) && Boolean(end);
                if (start) params.append('start', start);
                if (end) params.append('end', end);

                const response = await fetch(`api/availability.php?${params.toString()}`);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const payload = await response.json();
                if (!payload.success) {
                    throw new Error(payload.error?.message || 'Failed to load availability');
                }

                const windowStart = payload.data.window?.start;
                const windowEnd = payload.data.window?.end;
                const capacity = payload.data.capacity ?? 1;
                const usage = payload.data.usage ?? {};
                const usageEntries = Object.entries(usage).sort(([a], [b]) => a.localeCompare(b));
                const fullyBookedDates = usageEntries
                    .filter(([, count]) => count >= capacity)
                    .map(([date]) => date);
                const partiallyBookedDates = usageEntries
                    .filter(([, count]) => count < capacity)
                    .map(([date, count]) => `${date} (จองแล้ว ${count}/${capacity})`);

                if (fullyBookedDates.length > 0) {
                    const details = fullyBookedDates
                        .map(date => `${date} (เต็ม ${capacity}/${capacity})`)
                        .join(', ');
                    availabilityNotice.textContent = hasRange
                        ? `วันดังกล่าวเต็มแล้ว: ${details}`
                        : `ช่วง ${windowStart} ถึง ${windowEnd} มีวันที่เต็มแล้ว: ${details}`;
                    availabilityNotice.classList.remove('alert-info');
                    availabilityNotice.classList.add('alert-danger');
                } else if (partiallyBookedDates.length > 0) {
                    const details = partiallyBookedDates.join(', ');
                    availabilityNotice.textContent = hasRange
                        ? `ยังเหลือคิวว่าง (รองรับสูงสุด ${capacity} ต่อวัน) — วันที่จองแล้ว: ${details}`
                        : `ในช่วง ${windowStart} ถึง ${windowEnd} มีการจองแล้วบางส่วน: ${details}`;
                    availabilityNotice.classList.remove('alert-danger');
                    availabilityNotice.classList.add('alert-info');
                } else {
                    availabilityNotice.textContent = hasRange
                        ? `ช่วงวันที่ที่เลือกพร้อมให้บริการ (รองรับสูงสุด ${capacity} คิวต่อวัน)`
                        : `ยังไม่มีการจองในช่วง ${windowStart} ถึง ${windowEnd} (รองรับ ${capacity} คิวต่อวัน)`;
                    availabilityNotice.classList.remove('alert-danger');
                    availabilityNotice.classList.add('alert-info');
                }
            } catch (error) {
                logConsoleError(error);
                availabilityNotice.textContent = 'ไม่สามารถตรวจสอบความพร้อมได้ กรุณาลองใหม่';
                availabilityNotice.classList.remove('alert-info');
                availabilityNotice.classList.add('alert-danger');
            }
        }

        function logConsoleError(error) {
            if (window.console && console.error) {
                console.error(error);
            }
        }

        bookingForm.addEventListener('submit', event => {
            const packageId = document.getElementById('selectedPackageId').value;
            const pickupDate = pickupDateInput.value;
            const returnDate = returnDateInput.value;
            const pickupTime = pickupTimeInput.value || DEFAULT_PICKUP_TIME;
            const returnTime = returnTimeInput.value || DEFAULT_RETURN_TIME;
            const location = document.getElementById('location').value.trim();

            if (!packageId) {
                event.preventDefault();
                alert('กรุณาเลือกแพ็คเกจ');
                return;
            }

            if (!pickupDate || !returnDate) {
                event.preventDefault();
                alert('กรุณาเลือกช่วงวันที่รับและคืนอุปกรณ์');
                return;
            }

            if (returnDate < pickupDate) {
                event.preventDefault();
                alert('วันที่คืนต้องไม่ก่อนวันที่รับ');
                return;
            }

            if (!location) {
                event.preventDefault();
                alert('กรุณาระบุสถานที่ใช้งาน');
                return;
            }

            if (pickupDate === returnDate && pickupTime && returnTime && pickupTime >= returnTime) {
                event.preventDefault();
                alert('เวลาคืนต้องมากกว่าเวลารับในวันเดียวกัน');
                return;
            }
        });

        // Initialise summary if page loaded with existing selection
        if (selectedPackage.id) {
            const card = document.querySelector(`.package-card[data-package-id="${selectedPackage.id}"]`);
            if (card) {
                selectedPackage.price = parseFloat(card.dataset.packagePrice);
                selectedPackage.capacity = parseInt(card.dataset.packageCapacity, 10) || 1;
                card.classList.add('selected');
                bookingForm.style.display = 'block';
                bookingSummary.style.display = 'block';
                noPackageSelected.style.display = 'none';
                if (capacityField) {
                    capacityField.textContent = `รองรับการจองพร้อมกันสูงสุด ${selectedPackage.capacity} คิวต่อวัน`;
                }
                updateSummary();
            }
        }

        // Default times if empty
        if (!pickupTimeInput.value) pickupTimeInput.value = DEFAULT_PICKUP_TIME;
        if (!returnTimeInput.value) returnTimeInput.value = DEFAULT_RETURN_TIME;
        if (pickupDateInput.value) {
            returnDateInput.min = pickupDateInput.value;
        }
    </script>
</body>
</html>
