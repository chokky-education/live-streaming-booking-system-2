<?php
/**
 * Admin Booking Detail
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../models/Booking.php';
require_once '../../models/Payment.php';

// ตรวจสอบสิทธิ์ admin
require_admin();

$error_message = '';
$success_message = '';
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$booking_id) {
    redirect('bookings.php');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $booking = new Booking($db);
    $payment = new Payment($db);
    
    // ดึงข้อมูลการจอง
    $booking_data = $booking->getById($booking_id);
    
    if (!$booking_data) {
        $error_message = 'ไม่พบข้อมูลการจอง';
    } else {
        // ดึงข้อมูลการชำระเงิน
        $payment_data = $payment->getByBookingId($booking_id);
    }
    
    // ประมวลผลการอัปเดตสถานะ
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
        if (!verify_csrf_token($_POST['csrf_token'])) {
            $error_message = 'Invalid CSRF token';
        } else {
            $new_status = $_POST['status'];
            $booking->id = $booking_id;
            
            if ($booking->updateStatus($new_status)) {
                $success_message = 'อัปเดตสถานะการจองสำเร็จ';
                log_event("Admin updated booking {$booking_id} status to {$new_status}", 'INFO');
                
                // รีเฟรชข้อมูล
                $booking_data = $booking->getById($booking_id);
            } else {
                $error_message = 'เกิดข้อผิดพลาดในการอัปเดตสถานะ';
            }
        }
    }
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event("Admin booking detail error: " . $e->getMessage(), 'ERROR');
}

// ฟังก์ชันแสดงสถานะ
function getStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning">รอดำเนินการ</span>';
        case 'confirmed':
            return '<span class="badge bg-success">ยืนยันแล้ว</span>';
        case 'cancelled':
            return '<span class="badge bg-danger">ยกเลิก</span>';
        case 'completed':
            return '<span class="badge bg-info">เสร็จสิ้น</span>';
        default:
            return '<span class="badge bg-secondary">ไม่ทราบสถานะ</span>';
    }
}

function getPaymentStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning">รอตรวจสอบ</span>';
        case 'verified':
            return '<span class="badge bg-success">ยืนยันแล้ว</span>';
        case 'rejected':
            return '<span class="badge bg-danger">ปฏิเสธ</span>';
        default:
            return '<span class="badge bg-secondary">ไม่มีข้อมูล</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดการจอง - Admin Dashboard</title>
    
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
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }
        
        .info-row {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        
        .btn-status {
            margin: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>รายละเอียดการจอง</h2>
                        <p class="text-muted mb-0">
                            <?php echo $booking_data ? "รหัสการจอง: " . htmlspecialchars($booking_data['booking_code']) : 'ไม่พบข้อมูล'; ?>
                        </p>
                    </div>
                    <a href="bookings.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>กลับ
                    </a>
                </div>

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

                <?php if ($booking_data): ?>
                <div class="row">
                    <!-- Booking Information -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    ข้อมูลการจอง
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-row">
                                            <div class="info-label">รหัสการจอง</div>
                                            <div><?php echo htmlspecialchars($booking_data['booking_code']); ?></div>
                                        </div>
                                        <div class="info-row">
                                            <div class="info-label">แพ็คเกจ</div>
                                            <div><?php echo htmlspecialchars($booking_data['package_name']); ?></div>
                                        </div>
                                        <div class="info-row">
                                            <div class="info-label">วันที่ใช้งาน</div>
                                            <div><?php echo format_thai_date($booking_data['booking_date']); ?></div>
                                        </div>
                                        <div class="info-row">
                                            <div class="info-label">เวลา</div>
                                            <div>
                                                <?php if ($booking_data['start_time']): ?>
                                                    <?php echo date('H:i', strtotime($booking_data['start_time'])); ?>
                                                    <?php if ($booking_data['end_time']): ?>
                                                        - <?php echo date('H:i', strtotime($booking_data['end_time'])); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    ไม่ระบุ
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-row">
                                            <div class="info-label">ราคารวม</div>
                                            <div class="text-primary fw-bold"><?php echo format_currency($booking_data['total_price']); ?></div>
                                        </div>
                                        <div class="info-row">
                                            <div class="info-label">สถานะ</div>
                                            <div><?php echo getStatusBadge($booking_data['status']); ?></div>
                                        </div>
                                        <div class="info-row">
                                            <div class="info-label">วันที่จอง</div>
                                            <div><?php echo format_thai_date($booking_data['created_at']); ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">สถานที่ใช้งาน</div>
                                    <div><?php echo htmlspecialchars($booking_data['location']); ?></div>
                                </div>
                                
                                <?php if ($booking_data['notes']): ?>
                                <div class="info-row">
                                    <div class="info-label">หมายเหตุ</div>
                                    <div><?php echo htmlspecialchars($booking_data['notes']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Customer Information -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-user me-2"></i>
                                    ข้อมูลลูกค้า
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-row">
                                            <div class="info-label">ชื่อ-นามสกุล</div>
                                            <div><?php echo htmlspecialchars($booking_data['first_name'] . ' ' . $booking_data['last_name']); ?></div>
                                        </div>
                                        <div class="info-row">
                                            <div class="info-label">อีเมล</div>
                                            <div><?php echo htmlspecialchars($booking_data['email']); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-row">
                                            <div class="info-label">เบอร์โทร</div>
                                            <div><?php echo htmlspecialchars($booking_data['phone'] ?: 'ไม่ระบุ'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-cogs me-2"></i>
                                    จัดการสถานะ
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">สถานะปัจจุบัน: <?php echo getStatusBadge($booking_data['status']); ?></p>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    
                                    <?php if ($booking_data['status'] === 'pending'): ?>
                                        <button type="submit" name="status" value="confirmed" class="btn btn-success btn-status w-100 mb-2">
                                            <i class="fas fa-check me-2"></i>ยืนยันการจอง
                                        </button>
                                        <button type="submit" name="status" value="cancelled" class="btn btn-danger btn-status w-100">
                                            <i class="fas fa-times me-2"></i>ยกเลิกการจอง
                                        </button>
                                    <?php elseif ($booking_data['status'] === 'confirmed'): ?>
                                        <button type="submit" name="status" value="completed" class="btn btn-info btn-status w-100 mb-2">
                                            <i class="fas fa-flag-checkered me-2"></i>เสร็จสิ้น
                                        </button>
                                        <button type="submit" name="status" value="cancelled" class="btn btn-danger btn-status w-100">
                                            <i class="fas fa-times me-2"></i>ยกเลิกการจอง
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>

                        <!-- Payment Information -->
                        <?php if ($payment_data): ?>
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-credit-card me-2"></i>
                                    ข้อมูลการชำระเงิน
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="info-row">
                                    <div class="info-label">จำนวนเงิน</div>
                                    <div><?php echo format_currency($payment_data['amount']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">สถานะ</div>
                                    <div><?php echo getPaymentStatusBadge($payment_data['status']); ?></div>
                                </div>
                                <?php if ($payment_data['slip_image_url']): ?>
                                <div class="info-row">
                                    <div class="info-label">สลิปการโอนเงิน</div>
                                    <div>
                                        <a href="../../<?php echo htmlspecialchars($payment_data['slip_image_url']); ?>" 
                                           target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>ดูสลิป
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="text-center mt-3">
                                    <a href="payment_detail.php?id=<?php echo $payment_data['id']; ?>" 
                                       class="btn btn-primary">
                                        <i class="fas fa-credit-card me-2"></i>จัดการการชำระเงิน
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>