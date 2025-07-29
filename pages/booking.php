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
                'equipment_list' => $package->equipment_list
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
            'package_id' => (int)$_POST['package_id'],
            'booking_date' => $_POST['booking_date'],
            'start_time' => $_POST['start_time'],
            'end_time' => $_POST['end_time'],
            'location' => sanitize_input($_POST['location']),
            'notes' => sanitize_input($_POST['notes'])
        ];
        
        try {
            // ตรวจสอบความถูกต้องของข้อมูล
            $validation_errors = $booking->validate($booking_data);
            
            if (!empty($validation_errors)) {
                $error_message = implode('<br>', $validation_errors);
            } else {
                // ตรวจสอบความพร้อมของแพ็คเกจ
                if (!$booking->checkPackageAvailability($booking_data['package_id'], $booking_data['booking_date'])) {
                    $error_message = 'แพ็คเกจนี้ไม่ว่างในวันที่เลือก กรุณาเลือกวันอื่น';
                } else {
                    // คำนวณราคา
                    $package->getById($booking_data['package_id']);
                    $price_calculation = $package->calculateTotalPrice(1);
                    
                    // สร้างการจอง
                    $booking->user_id = $_SESSION['user_id'];
                    $booking->package_id = $booking_data['package_id'];
                    $booking->booking_date = $booking_data['booking_date'];
                    $booking->start_time = $booking_data['start_time'];
                    $booking->end_time = $booking_data['end_time'];
                    $booking->location = $booking_data['location'];
                    $booking->notes = $booking_data['notes'];
                    $booking->total_price = $price_calculation['total_price'];
                    $booking->status = 'pending';
                    
                    if ($booking->create()) {
                        $success_message = 'จองสำเร็จ! รหัสการจอง: ' . $booking->booking_code;
                        log_event("New booking created: {$booking->booking_code} by user {$_SESSION['username']}", 'INFO');
                        
                        // Redirect ไปหน้าชำระเงิน
                        header("refresh:2;url=payment.php?booking=" . $booking->booking_code);
                    } else {
                        $error_message = 'เกิดข้อผิดพลาดในการจอง กรุณาลองใหม่อีกครั้ง';
                    }
                }
            }
        } catch (Exception $e) {
            $error_message = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
            log_event("Booking creation error: " . $e->getMessage(), 'ERROR');
        }
    }
}
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
                                     data-package-price="<?php echo $pkg['price']; ?>">
                                    <div class="package-header">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($pkg['name']); ?></h5>
                                        <div class="package-price"><?php echo format_currency($pkg['price']); ?></div>
                                        <small>ต่อวัน</small>
                                    </div>
                                    <div class="p-3">
                                        <p class="text-muted mb-3"><?php echo htmlspecialchars($pkg['description']); ?></p>
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
                                <label for="booking_date" class="form-label">วันที่ต้องการใช้งาน <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="booking_date" name="booking_date" 
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="start_time" class="form-label">เวลาเริ่ม</label>
                                <input type="time" class="form-control" id="start_time" name="start_time">
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="end_time" class="form-label">เวลาสิ้นสุด</label>
                                <input type="time" class="form-control" id="end_time" name="end_time">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="location" class="form-label">สถานที่ใช้งาน <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="location" name="location" rows="3" 
                                      placeholder="กรุณาระบุสถานที่ที่จะใช้งานอุปกรณ์" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">หมายเหตุเพิ่มเติม</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="ข้อมูลเพิ่มเติมหรือความต้องการพิเศษ (ไม่บังคับ)"></textarea>
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
                        </div>
                        
                        <div class="price-summary">
                            <div class="d-flex justify-content-between mb-2">
                                <span>ราคาแพ็คเกจ:</span>
                                <span id="packagePrice"><?php echo $selected_package ? format_currency($selected_package['price']) : ''; ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>จำนวนวัน:</span>
                                <span>1 วัน</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>VAT (7%):</span>
                                <span id="vatAmount"><?php echo $selected_package ? format_currency($selected_package['price'] * 0.07) : ''; ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong>ราคารวม:</strong>
                                <strong id="totalPrice"><?php echo $selected_package ? format_currency($selected_package['price'] * 1.07) : ''; ?></strong>
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
        // Package selection
        document.querySelectorAll('.package-card').forEach(card => {
            card.addEventListener('click', function() {
                // Remove selected class from all cards
                document.querySelectorAll('.package-card').forEach(c => c.classList.remove('selected'));
                
                // Add selected class to clicked card
                this.classList.add('selected');
                
                // Update form and summary
                const packageId = this.dataset.packageId;
                const packageName = this.dataset.packageName;
                const packagePrice = parseFloat(this.dataset.packagePrice);
                
                document.getElementById('selectedPackageId').value = packageId;
                document.getElementById('selectedPackageName').textContent = packageName;
                document.getElementById('packagePrice').textContent = formatCurrency(packagePrice);
                
                const vatAmount = packagePrice * 0.07;
                const totalPrice = packagePrice + vatAmount;
                
                document.getElementById('vatAmount').textContent = formatCurrency(vatAmount);
                document.getElementById('totalPrice').textContent = formatCurrency(totalPrice);
                
                // Show form and summary
                document.getElementById('bookingForm').style.display = 'block';
                document.getElementById('bookingSummary').style.display = 'block';
                document.getElementById('noPackageSelected').style.display = 'none';
            });
        });
        
        // Format currency function
        function formatCurrency(amount) {
            return new Intl.NumberFormat('th-TH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount) + ' บาท';
        }
        
        // Date validation
        document.getElementById('booking_date').addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate <= today) {
                alert('กรุณาเลือกวันที่ในอนาคต');
                this.value = '';
            }
        });
        
        // Time validation
        document.getElementById('end_time').addEventListener('change', function() {
            const startTime = document.getElementById('start_time').value;
            const endTime = this.value;
            
            if (startTime && endTime && startTime >= endTime) {
                alert('เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม');
                this.value = '';
            }
        });
        
        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const packageId = document.getElementById('selectedPackageId').value;
            const bookingDate = document.getElementById('booking_date').value;
            const location = document.getElementById('location').value.trim();
            
            if (!packageId) {
                e.preventDefault();
                alert('กรุณาเลือกแพ็คเกจ');
                return;
            }
            
            if (!bookingDate) {
                e.preventDefault();
                alert('กรุณาเลือกวันที่จอง');
                return;
            }
            
            if (!location) {
                e.preventDefault();
                alert('กรุณาระบุสถานที่ใช้งาน');
                return;
            }
        });
    </script>
</body>
</html>