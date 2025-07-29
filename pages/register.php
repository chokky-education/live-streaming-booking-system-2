<?php
/**
 * หน้าสมัครสมาชิก
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../models/User.php';

$error_message = '';
$success_message = '';

// ตรวจสอบว่าเข้าสู่ระบบแล้วหรือไม่
if (is_logged_in()) {
    redirect('profile.php');
}

// ประมวลผลการสมัครสมาชิก
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid CSRF token';
    } else {
        $data = [
            'username' => sanitize_input($_POST['username']),
            'email' => sanitize_input($_POST['email']),
            'password' => $_POST['password'],
            'confirm_password' => $_POST['confirm_password'],
            'first_name' => sanitize_input($_POST['first_name']),
            'last_name' => sanitize_input($_POST['last_name']),
            'phone' => sanitize_input($_POST['phone'])
        ];
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            $user = new User($db);
            
            // ตรวจสอบความถูกต้องของข้อมูล
            $validation_errors = $user->validate($data);
            
            // ตรวจสอบรหัสผ่านยืนยัน
            if ($data['password'] !== $data['confirm_password']) {
                $validation_errors[] = 'รหัสผ่านและรหัสผ่านยืนยันไม่ตรงกัน';
            }
            
            // ตรวจสอบว่า username หรือ email ซ้ำหรือไม่
            if ($user->exists($data['username'], $data['email'])) {
                $validation_errors[] = 'Username หรือ Email นี้มีผู้ใช้งานแล้ว';
            }
            
            if (!empty($validation_errors)) {
                $error_message = implode('<br>', $validation_errors);
            } else {
                // สร้างผู้ใช้ใหม่
                $user->username = $data['username'];
                $user->email = $data['email'];
                $user->password = $data['password'];
                $user->first_name = $data['first_name'];
                $user->last_name = $data['last_name'];
                $user->phone = $data['phone'];
                $user->role = 'customer';
                
                if ($user->create()) {
                    $success_message = 'สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ';
                    log_event("New user registered: {$user->username}", 'INFO');
                    
                    // Redirect หลังจาก 2 วินาที
                    header("refresh:2;url=login.php");
                } else {
                    $error_message = 'เกิดข้อผิดพลาดในการสมัครสมาชิก กรุณาลองใหม่อีกครั้ง';
                }
            }
        } catch (Exception $e) {
            $error_message = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
            log_event("Registration error: " . $e->getMessage(), 'ERROR');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - ระบบจองอุปกรณ์ Live Streaming</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
        }
        
        .register-left {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }
        
        .register-right {
            padding: 3rem;
        }
        
        .register-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .register-subtitle {
            opacity: 0.9;
            margin-bottom: 2rem;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-register {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
        }
        
        .input-group .form-control {
            border-left: none;
        }
        
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #dc3545; }
        .strength-medium { background: #ffc107; }
        .strength-strong { background: #28a745; }
        
        @media (max-width: 768px) {
            .register-left {
                padding: 2rem;
            }
            
            .register-right {
                padding: 2rem;
            }
            
            .register-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-11">
                <div class="register-container row g-0">
                    <!-- Left Side -->
                    <div class="col-lg-4 register-left">
                        <div>
                            <i class="fas fa-user-plus fa-4x mb-4"></i>
                            <h2 class="register-title">เริ่มต้นใช้งาน</h2>
                            <p class="register-subtitle">สมัครสมาชิกเพื่อจองอุปกรณ์ Live Streaming</p>
                            <div class="mt-4">
                                <div class="mb-3">
                                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                                    <p class="mb-0">จองอุปกรณ์ได้ทันที</p>
                                </div>
                                <div class="mb-3">
                                    <i class="fas fa-shield-alt fa-2x mb-2"></i>
                                    <p class="mb-0">ข้อมูลปลอดภัย</p>
                                </div>
                                <div class="mb-3">
                                    <i class="fas fa-headset fa-2x mb-2"></i>
                                    <p class="mb-0">ซัพพอร์ตตลอด 24 ชั่วโมง</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Side -->
                    <div class="col-lg-8 register-right">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold">สมัครสมาชิก</h3>
                            <p class="text-muted">กรอกข้อมูลเพื่อสร้างบัญชีใหม่</p>
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
                        
                        <form method="POST" action="" id="registerForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">ชื่อ <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               placeholder="กรอกชื่อ" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               placeholder="กรอกนามสกุล" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-at"></i>
                                        </span>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               placeholder="กรอก Username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                                    </div>
                                    <small class="text-muted">ต้องมีอย่างน้อย 3 ตัวอักษร</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               placeholder="กรอก Email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-phone"></i>
                                    </span>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           placeholder="กรอกเบอร์โทรศัพท์ (ไม่บังคับ)" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                </div>
                                <small class="text-muted">รูปแบบ: 08xxxxxxxx</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               placeholder="กรอกรหัสผ่าน" required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength" id="passwordStrength"></div>
                                    <small class="text-muted">ต้องมีอย่างน้อย 6 ตัวอักษร</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">ยืนยันรหัสผ่าน <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               placeholder="ยืนยันรหัสผ่าน" required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small id="passwordMatch" class="text-muted"></small>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    ฉันยอมรับ <a href="#" class="text-decoration-none">เงื่อนไขการใช้งาน</a> และ 
                                    <a href="#" class="text-decoration-none">นโยบายความเป็นส่วนตัว</a>
                                </label>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="register" class="btn btn-primary btn-register">
                                    <i class="fas fa-user-plus me-2"></i>
                                    สมัครสมาชิก
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="mb-0">มีบัญชีแล้ว? 
                                <a href="login.php" class="text-decoration-none fw-bold">เข้าสู่ระบบ</a>
                            </p>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="../index.html" class="text-muted text-decoration-none">
                                <i class="fas fa-arrow-left me-2"></i>
                                กลับหน้าแรก
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const password = document.getElementById('confirm_password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength';
            if (strength < 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength < 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        });
        
        // Password match checker
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirmPassword === '') {
                matchText.textContent = '';
                matchText.className = 'text-muted';
            } else if (password === confirmPassword) {
                matchText.textContent = 'รหัสผ่านตรงกัน';
                matchText.className = 'text-success';
            } else {
                matchText.textContent = 'รหัสผ่านไม่ตรงกัน';
                matchText.className = 'text-danger';
            }
        }
        
        document.getElementById('password').addEventListener('input', checkPasswordMatch);
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms').checked;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('รหัสผ่านและรหัสผ่านยืนยันไม่ตรงกัน');
                return;
            }
            
            if (!terms) {
                e.preventDefault();
                alert('กรุณายอมรับเงื่อนไขการใช้งาน');
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