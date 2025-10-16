<?php
/**
 * หน้าสมัครสมาชิก
 * ระบบจองอุปกรณ์ Live Streaming
 */

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/includes/config.php';
require_once $rootPath . '/includes/functions.php';
require_once $rootPath . '/models/User.php';

$error_message = '';
$success_message = '';
$form_data = [
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'phone' => '',
];

if (is_logged_in()) {
    redirect('/pages/web/profile.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid CSRF token';
    } else {
        $form_data['username'] = sanitize_input($_POST['username'] ?? '');
        $form_data['email'] = sanitize_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $form_data['first_name'] = sanitize_input($_POST['first_name'] ?? '');
        $form_data['last_name'] = sanitize_input($_POST['last_name'] ?? '');
        $form_data['phone'] = sanitize_input($_POST['phone'] ?? '');

        try {
            $db = get_db_connection();
            $user = new User($db);

            $data = $form_data;
            $data['password'] = $password;
            $data['confirm_password'] = $confirm_password;

            $validation_errors = $user->validate($data);

            if ($password !== $confirm_password) {
                $validation_errors[] = 'รหัสผ่านและรหัสผ่านยืนยันไม่ตรงกัน';
            }

            if ($user->exists($data['username'], $data['email'])) {
                $validation_errors[] = 'Username หรือ Email นี้มีผู้ใช้งานแล้ว';
            }

            if (!empty($validation_errors)) {
                $error_message = implode('<br>', $validation_errors);
            } else {
                $user->username = $data['username'];
                $user->email = $data['email'];
                $user->password = $password;
                $user->first_name = $data['first_name'];
                $user->last_name = $data['last_name'];
                $user->phone = $data['phone'];
                $user->role = 'customer';

                if ($user->create()) {
                    $success_message = 'สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ';
                    log_event("New user registered: {$user->username}", 'INFO');
                    header('refresh:2;url=login.php');
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

require_once $rootPath . '/includes/layout.php';

render_page_start('สมัครสมาชิก - ' . SITE_NAME, [
    'active' => 'profile',
]);
?>

<section class="form-shell">
    <div class="page-container">
        <div class="form-card">
            <div style="display:flex; gap:16px; align-items:center; margin-bottom:24px;">
                <span class="badge" style="background:rgba(29,209,161,0.14); color:#0b7a5c;"><i class="fa-solid fa-user-plus"></i></span>
                <div>
                    <h1 style="margin:0;">สร้างบัญชีใหม่</h1>
                    <p style="margin:4px 0 0; color:var(--brand-muted);">เริ่มจัดการการจองและการชำระเงินในไม่กี่นาที</p>
                </div>
            </div>

            <?php if ($error_message !== '') : ?>
                <div class="alert alert-error" role="alert"><?= $error_message; ?></div>
            <?php endif; ?>

            <?php if ($success_message !== '') : ?>
                <div class="alert alert-success" role="alert"><?= $success_message; ?></div>
            <?php endif; ?>

            <form method="POST" class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">

                <div>
                    <label for="first_name">ชื่อจริง</label>
                    <input type="text" class="input" id="first_name" name="first_name" value="<?= htmlspecialchars($form_data['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div>
                    <label for="last_name">นามสกุล</label>
                    <input type="text" class="input" id="last_name" name="last_name" value="<?= htmlspecialchars($form_data['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div>
                    <label for="username">Username</label>
                    <input type="text" class="input" id="username" name="username" value="<?= htmlspecialchars($form_data['username'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div>
                    <label for="email">อีเมล</label>
                    <input type="email" class="input" id="email" name="email" value="<?= htmlspecialchars($form_data['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div>
                    <label for="phone">เบอร์ติดต่อ</label>
                    <input type="tel" class="input" id="phone" name="phone" value="<?= htmlspecialchars($form_data['phone'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div>
                    <label for="password">รหัสผ่าน</label>
                    <input type="password" class="input" id="password" name="password" required>
                </div>

                <div>
                    <label for="confirm_password">ยืนยันรหัสผ่าน</label>
                    <input type="password" class="input" id="confirm_password" name="confirm_password" required>
                </div>

                <div style="grid-column: 1 / -1; display:flex; flex-direction:column; gap:18px;">
                    <button type="submit" name="register" class="btn btn-primary" style="width:100%;">สมัครใช้งาน</button>
                    <p style="text-align:center; margin:0; color:var(--brand-muted);">มีบัญชีแล้ว? <a href="/pages/web/login.php">เข้าสู่ระบบ</a></p>
                </div>
            </form>
        </div>
    </div>
</section>

<?php
render_page_end(['show_footer' => false]);
