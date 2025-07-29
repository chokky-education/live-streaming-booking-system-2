<?php
/**
 * หน้าชำระเงิน
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../models/Booking.php';
require_once '../models/Payment.php';

// ตรวจสอบการเข้าสู่ระบบ
require_login();

$error_message = '';
$success_message = '';
$booking_code = isset($_GET['booking']) ? sanitize_input($_GET['booking']) : '';

if (empty($booking_code)) {
    redirect('profile.php');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $booking = new Booking($db);
    $payment = new Payment($db);
    
    // ดึงข้อมูลการจอง
    $booking_data = $booking->getByBookingCode($booking_code);
    
    if (!$booking_data || $booking_data['user_id'] != $_SESSION['user_id']) {
        $error_message = 'ไม่พบข้อมูลการจองหรือคุณไม่มีสิทธิ์เข้าถึง';
    } else {
        // ตรวจสอบว่ามีการชำระเงินแล้วหรือไม่
        $existing_payment = $payment->getByBookingId($booking_data['id']);
    }
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event("Payment page error: " . $e->getMessage(), 'ERROR');
}

// ประมวลผลการอัปโหลดสลิป
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_slip'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid CSRF token';
    } else {
        try {
            // ตรวจสอบไฟล์
            if (!isset($_FILES['slip_image']) || $_FILES['slip_image']['error'] === UPLOAD_ERR_NO_FILE) {
                $error_message = 'กรุณาเลือกไฟล์สลิปการโอนเงิน';
            } else {
                // คำนวณจำนวนเงินมัดจำ (50%)
                $deposit_amount = $booking_data['total_price'] * 0.5;
                
                // สร้างการชำระเงินใหม่หรืออัปเดต
                if ($existing_payment) {
                    $payment->id = $existing_payment['id'];
                } else {
                    $payment->booking_id = $booking_data['id'];
                    $payment->amount = $deposit_amount;
                    $payment->payment_method = 'bank_transfer';
                    $payment->status = 'pending';
                    $payment->paid_at = date('Y-m-d H:i:s');
                    $payment->transaction_ref = sanitize_input($_POST['transaction_ref']);
                    $payment->notes = sanitize_input($_POST['notes']);
                }
                
                // อัปโหลดสลิป
                $upload_result = $payment->uploadSlip($_FILES['slip_image']);
                
                if ($upload_result['success']) {
                    if ($existing_payment) {
                        // อัปเดตข้อมูลการชำระเงิน
                        $query = "UPDATE payments 
                                  SET slip_image_url = :slip_image_url, 
                                      transaction_ref = :transaction_ref,
                                      notes = :notes,
                                      status = 'pending',
                                      paid_at = :paid_at
                                  WHERE id = :id";
                        
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(":slip_image_url", $payment->slip_image_url);
                        $stmt->bindParam(":transaction_ref", $_POST['transaction_ref']);
                        $stmt->bindParam(":notes", $_POST['notes']);
                        $stmt->bindParam(":paid_at", date('Y-m-d H:i:s'));
                        $stmt->bindParam(":id", $payment->id);
                        $stmt->execute();
                    } else {
                        // สร้างการชำระเงินใหม่
                        $payment->create();
                    }
                    
                    $success_message = 'อัปโหลดสลิปสำเร็จ! รอการตรวจสอบจากเจ้าหน้าที่';
                    log_event("Payment slip uploaded for booking {$booking_code}", 'INFO');
                    
                    // รีเฟรชข้อมูล
                    $existing_payment = $payment->getByBookingId($booking_data['id']);
                } else {
                    $error_message = $upload_result['message'];
                }
            }
        } catch (Exception $e) {
            $error_message = 'เกิดข้อผิดพลาดในการอัปโหลดสลิป';
            log_event("Payment upload error: " . $e->getMessage(), 'ERROR');
        }
    }
}

// คำนวณจำนวนเงิน
$deposit_amount = 0;
$remaining_amount = 0;
if ($booking_data) {
    $deposit_amount = $booking_data['total_price'] * 0.5;
    $remaining_amount = $booking_data['total_price'] - $deposit_amount;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ชำระเงิน - ระบบจองอุปกรณ์ Live Streaming</title>
    
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
        
        .payment-header {
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
            background: #28a745;
            color: white;
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
        
        .payment-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .bank-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            border-left: 5px solid #667eea;
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
        
        .btn-payment {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-payment:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-verified {
            background: #d1edff;
            color: #0c5460;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .upload-area:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .upload-area.dragover {
            border-color: #667eea;
            background: #f8f9ff;
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
    <div class="payment-header">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h1><i class="fas fa-credit-card me-2"></i>ชำระเงิน</h1>
                    <p class="mb-0">อัปโหลดสลิปการโอนเงินเพื่อยืนยันการจอง</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Step Indicator -->
    <div class="container">
        <div class="step-indicator">
            <div class="step completed">
                <div class="step-number"><i class="fas fa-check"></i></div>
                <span>เลือกแพ็คเกจ</span>
            </div>
            <div class="step completed">
                <div class="step-number"><i class="fas fa-check"></i></div>
                <span>กรอกข้อมูล</span>
            </div>
            <div class="step active">
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

        <?php if ($booking_data): ?>
        <div class="row">
            <!-- Payment Form -->
            <div class="col-lg-8">
                <div class="payment-card">
                    <h3 class="mb-4"><i class="fas fa-receipt me-2"></i>รายละเอียดการชำระเงิน</h3>
                    
                    <!-- Order Summary -->
                    <div class="order-summary mb-4">
                        <h5 class="mb-3">สรุปการจอง</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>รหัสการจอง:</strong> <?php echo htmlspecialchars($booking_data['booking_code']); ?></p>
                                <p><strong>แพ็คเกจ:</strong> <?php echo htmlspecialchars($booking_data['package_name']); ?></p>
                                <p><strong>วันที่ใช้งาน:</strong> <?php echo format_thai_date($booking_data['booking_date']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>ราคารวม:</strong> <?php echo format_currency($booking_data['total_price']); ?></p>
                                <p><strong>มัดจำ (50%):</strong> <span class="text-primary fw-bold"><?php echo format_currency($deposit_amount); ?></span></p>
                                <p><strong>ชำระเมื่อรับอุปกรณ์:</strong> <?php echo format_currency($remaining_amount); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Information -->
                    <div class="bank-info mb-4">
                        <h5 class="mb-3"><i class="fas fa-university me-2"></i>ข้อมูลการโอนเงิน</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-2"><strong>ธนาคาร:</strong> กสิกรไทย</p>
                                <p class="mb-2"><strong>เลขที่บัญชี:</strong> 123-4-56789-0</p>
                                <p class="mb-2"><strong>ชื่อบัญชี:</strong> บริษัท Live Streaming Pro จำกัด</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-2"><strong>ประเภทบัญชี:</strong> ออมทรัพย์</p>
                                <p class="mb-2"><strong>จำนวนเงินที่ต้องโอน:</strong></p>
                                <h4 class="text-primary"><?php echo format_currency($deposit_amount); ?></h4>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Status -->
                    <?php if ($existing_payment): ?>
                        <div class="mb-4">
                            <h5>สถานะการชำระเงิน</h5>
                            <div class="d-flex align-items-center">
                                <?php
                                $status_class = '';
                                $status_text = '';
                                switch ($existing_payment['status']) {
                                    case 'pending':
                                        $status_class = 'status-pending';
                                        $status_text = 'รอการตรวจสอบ';
                                        break;
                                    case 'verified':
                                        $status_class = 'status-verified';
                                        $status_text = 'ยืนยันแล้ว';
                                        break;
                                    case 'rejected':
                                        $status_class = 'status-rejected';
                                        $status_text = 'ถูกปฏิเสธ';
                                        break;
                                }
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                
                                <?php if ($existing_payment['status'] === 'verified'): ?>
                                    <span class="ms-3 text-success">
                                        <i class="fas fa-check-circle me-1"></i>
                                        การจองได้รับการยืนยันแล้ว
                                    </span>
                                <?php elseif ($existing_payment['status'] === 'rejected'): ?>
                                    <span class="ms-3 text-danger">
                                        <i class="fas fa-times-circle me-1"></i>
                                        กรุณาอัปโหลดสลิปใหม่
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($existing_payment['slip_image_url']): ?>
                                <div class="mt-3">
                                    <p class="mb-2"><strong>สลิปที่อัปโหลด:</strong></p>
                                    <img src="../<?php echo htmlspecialchars($existing_payment['slip_image_url']); ?>" 
                                         alt="Payment Slip" class="img-thumbnail" style="max-width: 300px;">
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($existing_payment['notes']): ?>
                                <div class="mt-3">
                                    <p class="mb-1"><strong>หมายเหตุ:</strong></p>
                                    <p class="text-muted"><?php echo htmlspecialchars($existing_payment['notes']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Upload Form -->
                    <?php if (!$existing_payment || $existing_payment['status'] === 'rejected'): ?>
                        <form method="POST" action="" enctype="multipart/form-data" id="paymentForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <h5 class="mb-3"><i class="fas fa-upload me-2"></i>อัปโหลดสลิปการโอนเงิน</h5>
                            
                            <div class="mb-3">
                                <label for="slip_image" class="form-label">ไฟล์สลิป <span class="text-danger">*</span></label>
                                <div class="upload-area" id="uploadArea">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                    <p class="mb-2">คลิกเพื่อเลือกไฟล์หรือลากไฟล์มาวางที่นี่</p>
                                    <p class="text-muted small">รองรับไฟล์ JPG, PNG, PDF (สูงสุด 5MB)</p>
                                    <input type="file" class="form-control" id="slip_image" name="slip_image" 
                                           accept=".jpg,.jpeg,.png,.pdf" required style="display: none;">
                                </div>
                                <div id="filePreview" class="mt-3" style="display: none;"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="transaction_ref" class="form-label">หมายเลขอ้างอิง (ถ้ามี)</label>
                                <input type="text" class="form-control" id="transaction_ref" name="transaction_ref" 
                                       placeholder="หมายเลขอ้างอิงจากการโอนเงิน">
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">หมายเหตุเพิ่มเติม</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                          placeholder="หมายเหตุหรือข้อมูลเพิ่มเติม (ไม่บังคับ)"></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="upload_slip" class="btn btn-primary btn-payment">
                                    <i class="fas fa-upload me-2"></i>
                                    อัปโหลดสลิป
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="payment-card">
                    <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>ข้อมูลสำคัญ</h5>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>ขั้นตอนการชำระเงิน</h6>
                        <ol class="mb-0 ps-3">
                            <li>โอนเงินมัดจำ 50% ตามจำนวนที่ระบุ</li>
                            <li>อัปโหลดสลิปการโอนเงิน</li>
                            <li>รอการตรวจสอบจากเจ้าหน้าที่</li>
                            <li>รับการยืนยันการจอง</li>
                        </ol>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-clock me-2"></i>เวลาในการตรวจสอบ</h6>
                        <p class="mb-0">เจ้าหน้าที่จะตรวจสอบการชำระเงินภายใน 2-4 ชั่วโมง ในเวลาทำการ</p>
                    </div>
                    
                    <div class="alert alert-success">
                        <h6><i class="fas fa-shield-alt me-2"></i>การรับประกัน</h6>
                        <p class="mb-0">หากมีปัญหาเกี่ยวกับการชำระเงิน สามารถติดต่อเจ้าหน้าที่ได้ตลอด 24 ชั่วโมง</p>
                    </div>
                </div>
                
                <div class="payment-card">
                    <h5 class="mb-3"><i class="fas fa-phone me-2"></i>ติดต่อเจ้าหน้าที่</h5>
                    <p class="mb-2"><i class="fas fa-phone me-2"></i> 02-123-4567</p>
                    <p class="mb-2"><i class="fab fa-line me-2"></i> @livestreamingpro</p>
                    <p class="mb-0"><i class="fas fa-envelope me-2"></i> support@livestreaming.com</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Navigation -->
    <div class="container mb-4">
        <div class="d-flex justify-content-between">
            <a href="profile.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>กลับโปรไฟล์
            </a>
            <a href="booking.php" class="btn btn-outline-primary">
                <i class="fas fa-plus me-2"></i>จองใหม่
            </a>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // File upload handling
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('slip_image');
        const filePreview = document.getElementById('filePreview');
        
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });
        
        function handleFileSelect(file) {
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
            if (!allowedTypes.includes(file.type)) {
                alert('ประเภทไฟล์ไม่ถูกต้อง กรุณาเลือกไฟล์ JPG, PNG หรือ PDF');
                return;
            }
            
            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('ไฟล์มีขนาดใหญ่เกินไป กรุณาเลือกไฟล์ที่มีขนาดไม่เกิน 5MB');
                return;
            }
            
            // Show file preview
            filePreview.innerHTML = `
                <div class="d-flex align-items-center p-3 bg-light rounded">
                    <i class="fas fa-file-${file.type.includes('pdf') ? 'pdf' : 'image'} fa-2x me-3 text-primary"></i>
                    <div>
                        <h6 class="mb-1">${file.name}</h6>
                        <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="clearFile()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            filePreview.style.display = 'block';
        }
        
        function clearFile() {
            fileInput.value = '';
            filePreview.style.display = 'none';
        }
        
        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('slip_image');
            
            if (!fileInput.files.length) {
                e.preventDefault();
                alert('กรุณาเลือกไฟล์สลิปการโอนเงิน');
                return;
            }
        });
    </script>
</body>
</html>