<?php
/**
 * หน้าโปรไฟล์ลูกค้า
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../models/User.php';
require_once '../models/Booking.php';

// ตรวจสอบการเข้าสู่ระบบ
require_login();

$error_message = '';
$success_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);
    $booking = new Booking($db);
    
    // ดึงข้อมูลผู้ใช้
    $user->getById($_SESSION['user_id']);
    
    // ดึงประวัติการจอง
    $user_bookings = $booking->getUserBookings($_SESSION['user_id'], 10);
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event("Profile page error: " . $e->getMessage(), 'ERROR');
}

// ประมวลผลการอัปเดตข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid CSRF token';
    } else {
        $user->email = sanitize_input($_POST['email']);
        $user->phone = sanitize_input($_POST['phone']);
        $user->first_name = sanitize_input($_POST['first_name']);
        $user->last_name = sanitize_input($_POST['last_name']);
        
        try {
            if ($user->update()) {
                $_SESSION['first_name'] = $user->first_name;
                $_SESSION['last_name'] = $user->last_name;
                $success_message = 'อัปเดตข้อมูลสำเร็จ';
                log_event("User {$user->username} updated profile", 'INFO');
            } else {
                $error_message = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล';
            }
        } catch (Exception $e) {
            $error_message = 'เกิดข้อผิดพลาดในระบบ';
            log_event("Profile update error: " . $e->getMessage(), 'ERROR');
        }
    }
}

// ประมวลผลการเปลี่ยนรหัสผ่าน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid CSRF token';
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error_message = 'รหัสผ่านใหม่และรหัสผ่านยืนยันไม่ตรงกัน';
        } elseif (strlen($new_password) < 6) {
            $error_message = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
        } else {
            try {
                // ตรวจสอบรหัสผ่านปัจจุบัน
                if ($user->login($user->username, $current_password)) {
                    if ($user->changePassword($new_password)) {
                        $success_message = 'เปลี่ยนรหัสผ่านสำเร็จ';
                        log_event("User {$user->username} changed password", 'INFO');
                    } else {
                        $error_message = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน';
                    }
                } else {
                    $error_message = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
                }
            } catch (Exception $e) {
                $error_message = 'เกิดข้อผิดพลาดในระบบ';
                log_event("Password change error: " . $e->getMessage(), 'ERROR');
            }
        }
    }
}

// ฟังก์ชันแสดงสถานะการจอง
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
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ - ระบบจองอุปกรณ์ Live Streaming</title>
    
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
        
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .nav-tabs .nav-link {
            border-radius: 10px 10px 0 0;
            border: none;
            color: #6c757d;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
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
        
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .booking-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .booking-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                padding: 1rem 0;
            }
            
            .profile-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-user me-2"></i>โปรไฟล์ของฉัน</h1>
                    <p class="mb-0">จัดการข้อมูลส่วนตัวและดูประวัติการจอง</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="booking.php" class="btn btn-light">
                        <i class="fas fa-plus me-2"></i>จองใหม่
                    </a>
                </div>
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
            <!-- Profile Info -->
            <div class="col-lg-4">
                <div class="profile-card text-center">
                    <div class="avatar mx-auto mb-3">
                        <?php echo strtoupper(substr($user->first_name, 0, 1)); ?>
                    </div>
                    <h4><?php echo htmlspecialchars($user->first_name . ' ' . $user->last_name); ?></h4>
                    <p class="text-muted mb-3">@<?php echo htmlspecialchars($user->username); ?></p>
                    <p class="mb-1"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($user->email); ?></p>
                    <?php if ($user->phone): ?>
                        <p class="mb-3"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($user->phone); ?></p>
                    <?php endif; ?>
                    <small class="text-muted">สมาชิกตั้งแต่ <?php echo format_thai_date($user->created_at); ?></small>
                </div>

                <!-- Quick Stats -->
                <div class="row g-3">
                    <div class="col-6">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo count($user_bookings); ?></div>
                            <div class="text-muted">การจองทั้งหมด</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stats-card">
                            <div class="stats-number">
                                <?php 
                                $confirmed_bookings = array_filter($user_bookings, function($booking) {
                                    return $booking['status'] === 'confirmed';
                                });
                                echo count($confirmed_bookings);
                                ?>
                            </div>
                            <div class="text-muted">ยืนยันแล้ว</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-8">
                <div class="profile-card">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="bookings-tab" data-bs-toggle="tab" data-bs-target="#bookings" type="button" role="tab">
                                <i class="fas fa-calendar-alt me-2"></i>ประวัติการจอง
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                                <i class="fas fa-user-edit me-2"></i>แก้ไขข้อมูล
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">
                                <i class="fas fa-key me-2"></i>เปลี่ยนรหัสผ่าน
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content mt-4" id="profileTabsContent">
                        <!-- Bookings Tab -->
                        <div class="tab-pane fade show active" id="bookings" role="tabpanel">
                            <h5 class="mb-3">ประวัติการจองของฉัน</h5>
                            
                            <?php if (empty($user_bookings)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">ยังไม่มีการจอง</h5>
                                    <p class="text-muted">เริ่มจองอุปกรณ์เพื่อใช้งาน Live Streaming</p>
                                    <a href="booking.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>จองเลย
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($user_bookings as $booking): ?>
                                    <div class="booking-card">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <h6 class="mb-1">
                                                    <?php echo htmlspecialchars($booking['package_name']); ?>
                                                    <?php echo getStatusBadge($booking['status']); ?>
                                                </h6>
                                                <p class="text-muted mb-1">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo format_thai_date($booking['booking_date']); ?>
                                                    <?php if ($booking['start_time']): ?>
                                                        เวลา <?php echo date('H:i', strtotime($booking['start_time'])); ?>
                                                        <?php if ($booking['end_time']): ?>
                                                            - <?php echo date('H:i', strtotime($booking['end_time'])); ?>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </p>
                                                <p class="text-muted mb-1">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo htmlspecialchars($booking['location']); ?>
                                                </p>
                                                <small class="text-muted">
                                                    รหัสการจอง: <?php echo htmlspecialchars($booking['booking_code']); ?>
                                                </small>
                                            </div>
                                            <div class="col-md-4 text-md-end">
                                                <h5 class="text-primary mb-2">
                                                    <?php echo format_currency($booking['total_price']); ?>
                                                </h5>
                                                <?php if ($booking['status'] === 'pending'): ?>
                                                    <a href="payment.php?booking=<?php echo $booking['booking_code']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="fas fa-credit-card me-1"></i>ชำระเงิน
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="text-center mt-3">
                                    <a href="booking_history.php" class="btn btn-outline-primary">
                                        <i class="fas fa-history me-2"></i>ดูประวัติทั้งหมด
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Profile Edit Tab -->
                        <div class="tab-pane fade" id="profile" role="tabpanel">
                            <h5 class="mb-3">แก้ไขข้อมูลส่วนตัว</h5>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label">ชื่อ</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($user->first_name); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label">นามสกุล</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($user->last_name); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user->email); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user->phone); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user->username); ?>" disabled>
                                    <small class="text-muted">Username ไม่สามารถเปลี่ยนแปลงได้</small>
                                </div>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>บันทึกการเปลี่ยนแปลง
                                </button>
                            </form>
                        </div>

                        <!-- Password Change Tab -->
                        <div class="tab-pane fade" id="password" role="tabpanel">
                            <h5 class="mb-3">เปลี่ยนรหัสผ่าน</h5>
                            
                            <form method="POST" action="" id="passwordForm">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">รหัสผ่านปัจจุบัน</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">รหัสผ่านใหม่</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <small class="text-muted">ต้องมีอย่างน้อย 6 ตัวอักษร</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">ยืนยันรหัสผ่านใหม่</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <small id="passwordMatch" class="text-muted"></small>
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="fas fa-key me-2"></i>เปลี่ยนรหัสผ่าน
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="container mb-4">
        <div class="d-flex justify-content-between">
            <a href="../index.html" class="btn btn-outline-secondary">
                <i class="fas fa-home me-2"></i>หน้าแรก
            </a>
            <a href="logout.php" class="btn btn-outline-danger">
                <i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ
            </a>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password match checker
        function checkPasswordMatch() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirmPassword === '') {
                matchText.textContent = '';
                matchText.className = 'text-muted';
            } else if (newPassword === confirmPassword) {
                matchText.textContent = 'รหัสผ่านตรงกัน';
                matchText.className = 'text-success';
            } else {
                matchText.textContent = 'รหัสผ่านไม่ตรงกัน';
                matchText.className = 'text-danger';
            }
        }
        
        document.getElementById('new_password').addEventListener('input', checkPasswordMatch);
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        
        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('รหัสผ่านใหม่และรหัสผ่านยืนยันไม่ตรงกัน');
                return;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร');
                return;
            }
        });
        
        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            this.value = value;
        });
    </script>
</body>
</html>