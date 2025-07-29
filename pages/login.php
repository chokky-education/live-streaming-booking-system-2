<?php
/**
 * หน้าเข้าสู่ระบบ
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../models/User.php';

$error_message = '';
$success_message = '';

// ตรวจสอบว่าเข้าสู่ระบบแล้วหรือไม่
if (is_logged_in()) {
    if (is_admin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('profile.php');
    }
}

// ประมวลผลการเข้าสู่ระบบ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid CSRF token';
    } else {
        $username = sanitize_input($_POST['username']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($password)) {
            $error_message = 'กรุณากรอก Username และรหัสผ่าน';
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();
                $user = new User($db);
                
                if ($user->login($username, $password)) {
                    $_SESSION['user_id'] = $user->id;
                    $_SESSION['username'] = $user->username;
                    $_SESSION['first_name'] = $user->first_name;
                    $_SESSION['last_name'] = $user->last_name;
                    $_SESSION['role'] = $user->role;
                    
                    // Log การเข้าสู่ระบบ
                    log_event("User {$user->username} logged in", 'INFO');
                    
                    if ($user->role === 'admin') {
                        redirect('admin/dashboard.php');
                    } else {
                        redirect('profile.php');
                    }
                } else {
                    $error_message = 'Username หรือรหัสผ่านไม่ถูกต้อง';
                }
            } catch (Exception $e) {
                $error_message = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
                log_event("Login error: " . $e->getMessage(), 'ERROR');
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบจองอุปกรณ์ Live Streaming</title>
    
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
            display: flex;
            align-items: center;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }
        
        .login-left {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }
        
        .login-right {
            padding: 3rem;
        }
        
        .login-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
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
        
        .btn-login {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
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
        
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #dee2e6;
        }
        
        .divider span {
            background: white;
            padding: 0 1rem;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .login-left {
                padding: 2rem;
            }
            
            .login-right {
                padding: 2rem;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="login-container row g-0">
                    <!-- Left Side -->
                    <div class="col-lg-5 login-left">
                        <div>
                            <i class="fas fa-video fa-4x mb-4"></i>
                            <h2 class="login-title">ยินดีต้อนรับ</h2>
                            <p class="login-subtitle">เข้าสู่ระบบจองอุปกรณ์ Live Streaming</p>
                            <div class="mt-4">
                                <p class="mb-2"><i class="fas fa-check me-2"></i> อุปกรณ์คุณภาพสูง</p>
                                <p class="mb-2"><i class="fas fa-check me-2"></i> บริการรวดเร็ว</p>
                                <p class="mb-2"><i class="fas fa-check me-2"></i> ราคาเป็นมิตร</p>
                                <p class="mb-0"><i class="fas fa-check me-2"></i> ซัพพอร์ต 24/7</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Side -->
                    <div class="col-lg-7 login-right">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold">เข้าสู่ระบบ</h3>
                            <p class="text-muted">กรอกข้อมูลเพื่อเข้าสู่ระบบ</p>
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
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username หรือ Email</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           placeholder="กรอก Username หรือ Email" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">รหัสผ่าน</label>
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
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">
                                    จดจำการเข้าสู่ระบบ
                                </label>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="login" class="btn btn-primary btn-login">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    เข้าสู่ระบบ
                                </button>
                            </div>
                        </form>
                        
                        <div class="divider">
                            <span>หรือ</span>
                        </div>
                        
                        <div class="text-center">
                            <p class="mb-0">ยังไม่มีบัญชี? 
                                <a href="register.php" class="text-decoration-none fw-bold">สมัครสมาชิก</a>
                            </p>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="../index.html" class="text-muted text-decoration-none">
                                <i class="fas fa-arrow-left me-2"></i>
                                กลับหน้าแรก
                            </a>
                        </div>
                        
                        <!-- Demo Accounts -->
                        <div class="mt-4 p-3 bg-light rounded">
                            <h6 class="fw-bold mb-2">บัญชีทดสอบ:</h6>
                            <small class="text-muted">
                                <strong>Admin:</strong> admin / password<br>
                                <strong>Customer:</strong> customer / password
                            </small>
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
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('กรุณากรอกข้อมูลให้ครบถ้วน');
            }
        });
    </script>
</body>
</html>