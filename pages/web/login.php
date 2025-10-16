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
$recent_username = '';

if (is_logged_in()) {
    if (is_admin()) {
        redirect('admin/dashboard.php');
    }
    redirect('profile.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid CSRF token';
    } else {
        $recent_username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($recent_username === '' || $password === '') {
            $error_message = 'กรุณากรอก Username และรหัสผ่าน';
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();
                $user = new User($db);

                if ($user->login($recent_username, $password)) {
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_regenerate_id(true);
                    }

                    $_SESSION['user_id'] = $user->id;
                    $_SESSION['username'] = $user->username;
                    $_SESSION['first_name'] = $user->first_name;
                    $_SESSION['last_name'] = $user->last_name;
                    $_SESSION['role'] = $user->role;

                    log_event("User {$user->username} logged in", 'INFO');

                    if ($user->role === 'admin') {
                        redirect('admin/dashboard.php');
                    }
                    redirect('profile.php');
                }

                $error_message = 'Username หรือรหัสผ่านไม่ถูกต้อง';
            } catch (Exception $e) {
                $error_message = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
                log_event("Login error: " . $e->getMessage(), 'ERROR');
            }
        }
    }
}

require_once '../includes/layout.php';

render_page_start('เข้าสู่ระบบ - ' . SITE_NAME, [
    'active' => 'profile',
]);
?>

<section class="form-shell">
    <div class="page-container">
        <div class="form-card">
            <div style="display:flex; gap:16px; align-items:center; margin-bottom:24px;">
                <span class="badge" style="background:rgba(15,156,185,0.12); color:var(--brand-primary);"><i class="fa-solid fa-right-to-bracket"></i></span>
                <div>
                    <h1 style="margin:0;">ยินดีต้อนรับกลับ</h1>
                    <p style="margin:4px 0 0; color:var(--brand-muted);">เข้าถึงแดชบอร์ดการจองและการชำระเงินของคุณ</p>
                </div>
            </div>

            <?php if ($error_message !== '') : ?>
                <div class="alert alert-error" role="alert"><?= $error_message; ?></div>
            <?php endif; ?>

            <?php if ($success_message !== '') : ?>
                <div class="alert alert-success" role="alert"><?= $success_message; ?></div>
            <?php endif; ?>

            <form method="POST" class="form-grid" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">

                <div>
                    <label for="username">Username</label>
                    <input type="text" class="input" id="username" name="username" placeholder="กรอก Username" value="<?= htmlspecialchars($recent_username, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div>
                    <label for="password">รหัสผ่าน</label>
                    <input type="password" class="input" id="password" name="password" placeholder="กรอกรหัสผ่าน" required>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.9rem; color:var(--brand-muted); flex-wrap:wrap; gap:12px;">
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" style="width:16px; height:16px;" name="remember">
                        จดจำฉันไว้ในระบบ
                    </label>
                    <a href="#">ลืมรหัสผ่าน?</a>
                </div>

                <button type="submit" name="login" class="btn btn-primary" style="width:100%;">เข้าสู่ระบบ</button>
            </form>

            <div style="text-align:center; margin-top:24px;">
                <p style="margin-bottom:12px; color:var(--brand-muted);">ยังไม่มีบัญชี?</p>
                <a class="btn btn-ghost" style="width:100%; justify-content:center;" href="register.php">สมัครใช้งาน</a>
            </div>
        </div>
    </div>
</section>

<?php
render_page_end(['show_footer' => false]);
