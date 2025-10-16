<?php
/**
 * Admin Payment Detail
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_layout.php';
require_once '../../models/Payment.php';
require_once '../../models/Booking.php';

require_admin();

$error_message = '';
$success_message = '';
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($payment_id <= 0) {
    redirect('bookings.php');
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $payment = new Payment($db);
    $booking = new Booking($db);

    $payment_data = $payment->getById($payment_id);
    if (!$payment_data) {
        $error_message = 'ไม่พบข้อมูลการชำระเงิน';
    }

    if ($payment_data && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $error_message = 'Invalid CSRF token';
        } else {
            $action = $_POST['action'] ?? '';
            $notes = sanitize_input($_POST['notes'] ?? '');

            $payment->id = $payment_id;
            if ($action === 'verify') {
                if ($payment->verify($_SESSION['user_id'], $notes)) {
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

            $payment_data = $payment->getById($payment_id);
        }
    }
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event('Admin payment detail error: ' . $e->getMessage(), 'ERROR');
}

function payment_status_badge(string $status): string
{
    $map = [
        'pending' => ['label' => 'รอตรวจสอบ', 'class' => 'badge badge--pending'],
        'verified' => ['label' => 'ยืนยันแล้ว', 'class' => 'badge badge--confirmed'],
        'rejected' => ['label' => 'ปฏิเสธ', 'class' => 'badge badge--cancelled'],
    ];
    $config = $map[$status] ?? ['label' => 'ไม่ทราบสถานะ', 'class' => 'badge badge--pending'];
    return '<span class="' . $config['class'] . '">' . $config['label'] . '</span>';
}

render_admin_page_start('รายละเอียดการชำระเงิน - ' . SITE_NAME, [
    'active' => 'payments',
]);
?>

<section class="admin-toolbar">
    <div>
        <h1 style="margin:0;">รายละเอียดการชำระเงิน</h1>
        <?php if ($payment_data) : ?>
            <p style="margin:6px 0 0; color:var(--brand-muted);">รหัสการจอง: <?= htmlspecialchars($payment_data['booking_code'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>
    <a class="btn btn-ghost" href="/pages/admin/bookings.php"><i class="fa-solid fa-arrow-left"></i>&nbsp;กลับการจอง</a>
</section>

<?php if ($error_message !== '') : ?>
    <div class="alert alert-danger" role="alert"><?= $error_message; ?></div>
<?php endif; ?>

<?php if ($success_message !== '') : ?>
    <div class="alert alert-success" role="alert"><?= $success_message; ?></div>
<?php endif; ?>

<?php if ($payment_data) : ?>
    <div class="dashboard__grid">
        <div class="booking-panel">
            <h3 style="margin-top:0;">ข้อมูลการชำระ</h3>
            <div class="info-card" style="margin-top:16px;">
                <strong>สถานะปัจจุบัน</strong>
                <span><?= payment_status_badge($payment_data['status']); ?></span>
                <span>จำนวนเงิน: <?= format_currency($payment_data['amount']); ?></span>
                <span>ดำเนินการเมื่อ: <?= format_thai_date($payment_data['paid_at']); ?></span>
                <?php if (!empty($payment_data['transaction_ref'])) : ?>
                    <span>หมายเลขอ้างอิง: <?= htmlspecialchars($payment_data['transaction_ref'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
                <?php if (!empty($payment_data['notes'])) : ?>
                    <span>หมายเหตุเดิม: <?= htmlspecialchars($payment_data['notes'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>

            <h3>การดำเนินการ</h3>
            <form method="POST" class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-top:16px;">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                <input type="hidden" name="verify_payment" value="1">

                <div>
                    <label for="notes">หมายเหตุถึงลูกค้า/ทีม</label>
                    <textarea class="input" id="notes" name="notes" rows="3" placeholder="ข้อความถึงผู้จองหรือทีมบัญชี"></textarea>
                </div>

                <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                    <button type="submit" name="action" value="verify" class="btn btn-primary">
                        <i class="fa-solid fa-circle-check"></i>&nbsp;ยืนยันการชำระเงิน
                    </button>
                    <button type="submit" name="action" value="reject" class="btn btn-ghost" style="color:#b61c2c; border-color:rgba(220,53,69,0.4);">
                        <i class="fa-solid fa-circle-xmark"></i>&nbsp;ปฏิเสธสลิป
                    </button>
                </div>
            </form>
        </div>

        <div class="booking-panel">
            <h3 style="margin-top:0;">ข้อมูลลูกค้า</h3>
            <div class="info-card" style="margin-top:16px;">
                <strong><?= htmlspecialchars(($payment_data['first_name'] ?? '') . ' ' . ($payment_data['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                <span>Email: <?= htmlspecialchars($payment_data['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                <span>โทร: <?= htmlspecialchars($payment_data['phone'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                <div style="margin-top:12px;">
                    <a class="btn btn-ghost" href="/pages/admin/booking_detail.php?id=<?= urlencode($payment_data['booking_id']); ?>">
                        <i class="fa-solid fa-calendar-days"></i>&nbsp;ดูรายละเอียดการจอง
                    </a>
                </div>
            </div>

            <h3>สลิปการโอน</h3>
            <div class="info-card" style="margin-top:16px;">
                <?php if (!empty($payment_data['slip_image_url'])) : ?>
                    <img src="/<?= ltrim($payment_data['slip_image_url'], '/'); ?>" alt="Payment Slip" style="border-radius: var(--brand-radius-md); box-shadow: var(--brand-shadow-sm); max-width:100%;">
                    <a class="btn btn-primary" style="margin-top:12px;" href="/<?= ltrim($payment_data['slip_image_url'], '/'); ?>" target="_blank" rel="noopener">
                        เปิดไฟล์ต้นฉบับ
                    </a>
                <?php else : ?>
                    <span style="color:var(--brand-muted);">ผู้ใช้ยังไม่ได้อัปโหลดสลิป</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
render_admin_page_end();
