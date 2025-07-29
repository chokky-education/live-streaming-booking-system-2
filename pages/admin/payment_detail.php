<?php
/**
 * Admin Payment Detail
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../models/Payment.php';
require_once '../../models/Booking.php';

// ตรวจสอบสิทธิ์ admin
require_admin();

$error_message = '';
$success_message = '';
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$payment_id) {
    redirect('bookings.php');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $payment = new Payment($db);
    $booking = new Booking($db);
    
    // ดึงข้อมูลการชำระเงิน
    $payment_data = $payment->getById($payment_id);
    
    if (!$payment_data) {
        $error_message = 'ไม่พบข้อมูลการชำระเงิน';
    }
    
    // ประมวลผลการตรวจสอบการชำระเงิน
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_payment'])) {
        if (!verify_csrf_token($_POST['csrf_token'])) {
            $error_message = 'Invalid CSRF token';
        } else {
            $action = $_POST['action'];
            $notes = sanitize_input($_POST['notes']);
            
            $payment->id = $payment_id;
            
            if ($action === 'verify') {
                if ($payment->verify($_SESSION['user_id'], $notes)) {
                    // อัปเดตสถานะการจองเป็น confirmed
                    $booking->id = $payment_data['booking_id'];
                    $booking->updateStatus('confirmed');
                    
                    $success_message = 'ยืนยันการชำระเงินสำเร็จ';
                    log_event("Admin verified payment {$payment_id}", 'INFO');
                } else {
                    $error_message = 'เกิดข้อผิดพลาดในการยืนยันการชำระเงิน';
                }
            } elseif ($action === 'reject') {
                if ($payment->reject($_SESSION['user_id'], $notes)) {
                    $success_message = 'ปฏิเสธการชำระเงินสำเร็จ';
                    log_event("Admin rejected payment {$payment_id}", 'INFO');
                } else {
                    $error_message = 'เกิดข้อผิดพลาดในการปฏิเสธการชำระเงิน';
                }
            }
            
            // รีเฟรชข้อมูล
            $payment_data = $payment->getById($payment_id);
        }
    }
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event("Admin payment detail error: " . $e->getMessage(), 'ERROR');
}

// ฟังก์ชันแสดงสถานะ
function getPaymentStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning">รอตรวจสอบ</span>';
        case 'verified':
            return '<span class="badge bg-success">ยืนยันแล้ว</span>';
        case 'rejected':
            return '<span class="badge bg-danger">ปฏิเสธ</span>';
        default:
            return '<span class="badge bg-secondary">ไม่ทราบสถานะ</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดการชำระเงิน - Admin Dashboard</title>
    
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
        
        .slip-image {
            max-width: 100%;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>รายละเอียดการชำระเงิน</h2>
                        <p class="text-muted mb-0">
                            <?php echo $payment_data ? "รหัสการจอง: " . htmlspecialchars($payment_data['booking_code']) : 'ไม่พบข้อมูล'; ?>
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

                <?php if ($payment_data): ?>
                <div class="row">
                    <!-- Payment Information -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-credit-card me-2"></i>
                                    ข้อมูลการชำระเงิน
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-row">
                                            <div class="info-label">รหัสการจอง</div>
                                            <div>
                                                <a href="booking_detail.php?id=<?php echo $payment_data['booking_id']; ?>">
                                                    <?php echo htmlspecialchars($payment_data['booking_code']); ?>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="info-row">
                                            <div class="info-label">แพ็คเกจ</div>
                                            <div><?php echo htmlspecialchars($payment_data['package_name']); ?></div>
                                        </div>
                                        <div class="info-row">
                                            <div class="info-label">จำนวนเงิน</div>
                                            <div class="text-primary fw-bold"><?php echo format_currency($payment_data['amount']); ?></div>
                                        </div>
                                        <div class="info-row">
                                            <div class="info-label">วิธีการชำระเงิน</div>
                                            <div>โอนเงินผ่านธนาคาร</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-row">
                                            <div class="info-label">สถานะ</div>
                                            <div><?php echo getPaymentStatusBadge($payment_data['status']); ?></div>
                                        </div>
                                        <div class="info-row">
                                            <div class="info-label">วันที่ชำระเงิน</div>
                                            <div><?php echo $payment_data['paid_at'] ? format_thai_date($payment_data['paid_at']) : 'ไม่ระบุ'; ?></div>
                                        </div>
                                        <?php if ($payment_data['verified_at']): ?>
                                        <div class="info-row">
                                            <div class="info-label">วันที่ตรวจสอบ</div>
                                            <div><?php echo format_thai_date($payment_data['verified_at']); ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($payment_data['transaction_ref']): ?>
                                        <div class="info-row">
                                            <div class="info-label">หมายเลขอ้างอิง</div>
                                            <div><?php echo htmlspecialchars($payment_data['transaction_ref']); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($payment_data['notes']): ?>
                                <div class="info-row">
                                    <div class="info-label">หมายเหตุ</div>
                                    <div><?php echo htmlspecialchars($payment_data['notes']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Customer Information -->
                        <div class="card mb-4">
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
                                            <div><?php echo htmlspecialchars($payment_data['first_name'] . ' ' . $payment_data['last_name']); ?></div>
                                        </div>
                                        <div class="info-row">
                                            <div class="info-label">อีเมล</div>
                                            <div><?php echo htmlspecialchars($payment_data['email']); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Slip -->
                        <?php if ($payment_data['slip_image_url']): ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-image me-2"></i>
                                    สลิปการโอนเงิน
                                </h5>
                            </div>
                            <div class="card-body text-center">
                                <img src="../../<?php echo htmlspecialchars($payment_data['slip_image_url']); ?>" 
                                     alt="Payment Slip" class="slip-image">
                                <div class="mt-3">
                                    <a href="../../<?php echo htmlspecialchars($payment_data['slip_image_url']); ?>" 
                                       target="_blank" class="btn btn-outline-primary">
                                        <i class="fas fa-external-link-alt me-2"></i>เปิดในหน้าต่างใหม่
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div class="col-lg-4">
                        <?php if ($payment_data['status'] === 'pending'): ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-cogs me-2"></i>
                                    ตรวจสอบการชำระเงิน
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="verify_payment" value="1">
                                    
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">หมายเหตุ</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                                  placeholder="หมายเหตุเพิ่มเติม (ไม่บังคับ)"></textarea>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="action" value="verify" 
                                                class="btn btn-success" onclick="return confirm('คุณต้องการยืนยันการชำระเงินนี้หรือไม่?')">
                                            <i class="fas fa-check me-2"></i>ยืนยันการชำระเงิน
                                        </button>
                                        <button type="submit" name="action" value="reject" 
                                                class="btn btn-danger" onclick="return confirm('คุณต้องการปฏิเสธการชำระเงินนี้หรือไม่?')">
                                            <i class="fas fa-times me-2"></i>ปฏิเสธการชำระเงิน
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    สถานะการชำระเงิน
                                </h5>
                            </div>
                            <div class="card-body text-center">
                                <?php echo getPaymentStatusBadge($payment_data['status']); ?>
                                <p class="mt-3 text-muted">
                                    <?php if ($payment_data['status'] === 'verified'): ?>
                                        การชำระเงินได้รับการยืนยันแล้ว
                                    <?php elseif ($payment_data['status'] === 'rejected'): ?>
                                        การชำระเงินถูกปฏิเสธ
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Quick Actions -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-link me-2"></i>
                                    ลิงก์ที่เกี่ยวข้อง
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="booking_detail.php?id=<?php echo $payment_data['booking_id']; ?>" 
                                       class="btn btn-outline-primary">
                                        <i class="fas fa-calendar-alt me-2"></i>ดูรายละเอียดการจอง
                                    </a>
                                    <a href="bookings.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-list me-2"></i>รายการการจองทั้งหมด
                                    </a>
                                </div>
                            </div>
                        </div>
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