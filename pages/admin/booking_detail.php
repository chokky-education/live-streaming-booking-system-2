<?php
/**
 * Admin Booking Detail
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_layout.php';
require_once '../../models/Booking.php';
require_once '../../models/Payment.php';

require_admin();

$error_message = '';
$success_message = '';
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($booking_id <= 0) {
    redirect('bookings.php');
}

try {
    $db = get_db_connection();

    $booking = new Booking($db);
    $payment = new Payment($db);

    $booking_data = $booking->getById($booking_id);
    if (!$booking_data) {
        $error_message = 'ไม่พบข้อมูลการจอง';
    } else {
        $payment_data = $payment->getByBookingId($booking_id);
    }

    if ($booking_data && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $error_message = 'Invalid CSRF token';
        } else {
            $new_status = $_POST['status'] ?? '';
            $booking->id = $booking_id;
            if ($booking->updateStatus($new_status)) {
                $success_message = 'อัปเดตสถานะการจองสำเร็จ';
                log_event("Admin updated booking {$booking_id} status to {$new_status}", 'INFO');
                $booking_data = $booking->getById($booking_id);
            } else {
                $error_message = 'เกิดข้อผิดพลาดในการอัปเดตสถานะ';
            }
        }
    }
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event('Admin booking detail error: ' . $e->getMessage(), 'ERROR');
}

function booking_status_badge(string $status): string
{
    $map = [
        'pending' => ['label' => 'รอดำเนินการ', 'class' => 'badge badge--pending'],
        'confirmed' => ['label' => 'ยืนยันแล้ว', 'class' => 'badge badge--confirmed'],
        'cancelled' => ['label' => 'ยกเลิก', 'class' => 'badge badge--cancelled'],
        'completed' => ['label' => 'เสร็จสิ้น', 'class' => 'badge badge--completed'],
    ];

    $config = $map[$status] ?? ['label' => 'ไม่ทราบสถานะ', 'class' => 'badge badge--pending'];
    return '<span class="' . $config['class'] . '">' . $config['label'] . '</span>';
}

function payment_status_badge(?string $status): string
{
    if ($status === null) {
        return '<span class="badge badge--pending">ไม่มีข้อมูล</span>';
    }
    $map = [
        'pending' => ['label' => 'รอตรวจสอบ', 'class' => 'badge badge--pending'],
        'verified' => ['label' => 'ยืนยันแล้ว', 'class' => 'badge badge--confirmed'],
        'rejected' => ['label' => 'ปฏิเสธ', 'class' => 'badge badge--cancelled'],
    ];
    $config = $map[$status] ?? ['label' => 'ไม่ทราบสถานะ', 'class' => 'badge badge--pending'];
    return '<span class="' . $config['class'] . '">' . $config['label'] . '</span>';
}

render_admin_page_start('รายละเอียดการจอง - ' . SITE_NAME, [
    'active' => 'bookings',
]);
?>

<section class="admin-toolbar">
    <div>
        <h1 style="margin:0;">รายละเอียดการจอง</h1>
        <?php if ($booking_data) : ?>
            <p style="margin:6px 0 0; color:var(--brand-muted);">รหัสการจอง: <?= htmlspecialchars($booking_data['booking_code'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>
    <a class="btn btn-ghost" href="/pages/admin/bookings.php"><i class="fa-solid fa-arrow-left"></i>&nbsp;กลับรายการ</a>
</section>

<?php if ($error_message !== '') : ?>
    <div class="alert alert-danger" role="alert"><?= $error_message; ?></div>
<?php endif; ?>

<?php if ($success_message !== '') : ?>
    <div class="alert alert-success" role="alert"><?= $success_message; ?></div>
<?php endif; ?>

<?php if ($booking_data) : ?>
    <div class="dashboard__grid">
        <div class="booking-panel">
            <h3 style="margin-top:0;">ข้อมูลการจอง</h3>
            <div class="stat-stack" style="margin-top:16px;">
                <div class="info-card" style="margin-top:0;">
                    <strong>สถานะปัจจุบัน</strong>
                    <span><?= booking_status_badge($booking_data['status']); ?></span>
                    <form method="POST" class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); margin-top:12px;">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                        <input type="hidden" name="update_status" value="1">
                        <div>
                            <label for="status">อัปเดตสถานะ</label>
                            <select class="input" id="status" name="status" required>
                                <?php
                                $statuses = [
                                    'pending' => 'รอดำเนินการ',
                                    'confirmed' => 'ยืนยันแล้ว',
                                    'cancelled' => 'ยกเลิก',
                                    'completed' => 'เสร็จสิ้น',
                                ];
                                foreach ($statuses as $value => $label) :
                                    $selected = $booking_data['status'] === $value ? 'selected' : '';
                                ?>
                                    <option value="<?= $value; ?>" <?= $selected; ?>><?= $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="align-self:end;">
                            <button type="submit" class="btn btn-primary" style="width:100%;">บันทึกสถานะใหม่</button>
                        </div>
                    </form>
                </div>

                <div class="info-card" style="margin-top:0;">
                    <strong>รายละเอียด</strong>
                    <span>แพ็คเกจ: <?= htmlspecialchars($booking_data['package_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span>ช่วงวันที่: <?= format_thai_date($booking_data['pickup_date']); ?> - <?= format_thai_date($booking_data['return_date']); ?> (<?= (int)$booking_data['rental_days']; ?> วัน)</span>
                    <span>เวลารับ/คืน: <?= htmlspecialchars(substr($booking_data['pickup_time'] ?? BOOKING_DEFAULT_PICKUP_TIME, 0, 5), ENT_QUOTES, 'UTF-8'); ?> - <?= htmlspecialchars(substr($booking_data['return_time'] ?? BOOKING_DEFAULT_RETURN_TIME, 0, 5), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span>สถานที่ใช้งาน: <?= htmlspecialchars($booking_data['location'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if (!empty($booking_data['notes'])) : ?>
                        <span>หมายเหตุ: <?= htmlspecialchars($booking_data['notes'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="booking-panel">
            <h3 style="margin-top:0;">ข้อมูลลูกค้า</h3>
            <div class="info-card" style="margin-top:16px;">
                <strong><?= htmlspecialchars(($booking_data['first_name'] ?? '') . ' ' . ($booking_data['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                <span>Email: <?= htmlspecialchars($booking_data['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                <span>โทร: <?= htmlspecialchars($booking_data['phone'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                <span>บัญชีผู้ใช้: <?= htmlspecialchars($booking_data['username'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <h3>การชำระเงิน</h3>
            <div class="info-card" style="margin-top:16px;">
                <?php if ($payment_data) : ?>
                    <strong>สถานะการชำระ</strong>
                    <span><?= payment_status_badge($payment_data['status']); ?></span>
                    <span>ยอดมัดจำ: <?= format_currency($payment_data['amount']); ?></span>
                    <span>อัปโหลดเมื่อ: <?= format_thai_date($payment_data['paid_at']); ?></span>
                    <?php if (!empty($payment_data['transaction_ref'])) : ?>
                        <span>อ้างอิง: <?= htmlspecialchars($payment_data['transaction_ref'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <div style="display:flex; gap:12px; margin-top:12px;">
                        <a class="btn btn-ghost" href="/pages/admin/payment_detail.php?id=<?= urlencode($payment_data['id']); ?>">ดูรายละเอียดการชำระ</a>
                        <?php if (!empty($payment_data['slip_image_url'])) : ?>
                            <a class="btn btn-primary" href="/<?= ltrim($payment_data['slip_image_url'], '/'); ?>" target="_blank" rel="noopener">
                                <i class="fa-solid fa-image"></i>&nbsp;เปิดสลิป
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <strong>ยังไม่มีการชำระเงิน</strong>
                    <span style="color:var(--brand-muted);">ลูกค้ายังไม่อัปโหลดหลักฐานการโอน</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
render_admin_page_end();
