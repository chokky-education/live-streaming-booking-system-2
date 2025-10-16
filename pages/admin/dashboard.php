<?php
/**
 * Admin Dashboard
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_layout.php';
require_once '../../models/User.php';
require_once '../../models/Booking.php';
require_once '../../models/Payment.php';
require_once '../../models/Package.php';

require_admin();

$error_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();

    $booking = new Booking($db);
    $payment = new Payment($db);
    $package = new Package($db);
    $user = new User($db);

    $booking_stats = $booking->getBookingStatistics();
    $payment_stats = $payment->getPaymentStatistics();
    $recent_bookings = $booking->getAllBookings(null, 10);
    $pending_payments = $payment->getPendingPayments(5);
    $popular_packages = $package->getPopularPackages(3);
    $total_customers = count($user->getAllCustomers());

    $current_month = date('Y-m');
    $monthly_revenue = $payment->getTotalRevenue($current_month . '-01', $current_month . '-31');
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event('Admin dashboard error: ' . $e->getMessage(), 'ERROR');
}

function render_status_badge(string $status): string
{
    $map = [
        'pending' => ['text' => 'รอดำเนินการ', 'class' => 'badge badge--pending'],
        'confirmed' => ['text' => 'ยืนยันแล้ว', 'class' => 'badge badge--confirmed'],
        'cancelled' => ['text' => 'ยกเลิก', 'class' => 'badge badge--cancelled'],
        'completed' => ['text' => 'เสร็จสิ้น', 'class' => 'badge badge--completed'],
    ];

    $config = $map[$status] ?? ['text' => 'ไม่ทราบสถานะ', 'class' => 'badge badge--pending'];
    return '<span class="' . $config['class'] . '">' . $config['text'] . '</span>';
}

function render_payment_badge(string $status): string
{
    $map = [
        'pending' => ['text' => 'รอตรวจสอบ', 'class' => 'badge badge--pending'],
        'verified' => ['text' => 'ยืนยันแล้ว', 'class' => 'badge badge--confirmed'],
        'rejected' => ['text' => 'ปฏิเสธ', 'class' => 'badge badge--cancelled'],
    ];

    $config = $map[$status] ?? ['text' => 'ไม่ทราบสถานะ', 'class' => 'badge badge--pending'];
    return '<span class="' . $config['class'] . '">' . $config['text'] . '</span>';
}

render_admin_page_start('แดชบอร์ดผู้ดูแล - ' . SITE_NAME, [
    'active' => 'dashboard',
]);
?>

<section class="admin-toolbar">
    <div>
        <h1 style="margin:0;">แดชบอร์ด</h1>
        <p style="margin:6px 0 0; color:var(--brand-muted);">ภาพรวมระบบจองอุปกรณ์และสถานะการเงินแบบเรียลไทม์</p>
    </div>
    <div class="stat-stack">
        <span style="font-size:0.85rem; color:var(--brand-muted);">อัปเดตล่าสุด: <?= date('d/m/Y H:i'); ?> น.</span>
        <a class="btn btn-primary" href="/pages/admin/reports.php"><i class="fa-solid fa-arrow-trend-up"></i>&nbsp;ดูรายงาน</a>
    </div>
</section>

<?php if ($error_message !== '') : ?>
    <div class="alert alert-danger" role="alert"><?= $error_message; ?></div>
<?php endif; ?>

<div class="dashboard__grid">
    <div class="metric-card">
        <h3>การจองทั้งหมด</h3>
        <strong><?= number_format($booking_stats['total_bookings'] ?? 0); ?></strong>
        <p style="margin:8px 0 0; color:var(--brand-muted);">นับตั้งแต่เปิดระบบ</p>
    </div>
    <div class="metric-card">
        <h3>การจองที่ยืนยัน</h3>
        <strong><?= number_format($booking_stats['confirmed_bookings'] ?? 0); ?></strong>
        <p style="margin:8px 0 0; color:var(--brand-muted);">พร้อมดำเนินการ</p>
    </div>
    <div class="metric-card">
        <h3>รอตรวจสอบ</h3>
        <strong><?= number_format($booking_stats['pending_bookings'] ?? 0); ?></strong>
        <p style="margin:8px 0 0; color:var(--brand-muted);">ต้องติดตามการชำระเงิน</p>
    </div>
    <div class="metric-card">
        <h3>ลูกค้าทั้งหมด</h3>
        <strong><?= number_format($total_customers); ?></strong>
        <p style="margin:8px 0 0; color:var(--brand-muted);">บัญชีล่าสุด</p>
    </div>
</div>

<div class="dashboard__grid">
    <div class="booking-panel">
        <h3 style="margin-top:0;">ภาพรวมรายได้</h3>
        <div class="dashboard__grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <div class="info-card" style="margin-top:0;">
                <strong>รายได้รวม</strong>
                <span style="font-size:1.4rem; font-weight:700;"><?= format_currency($booking_stats['total_revenue'] ?? 0); ?></span>
                <span style="color:var(--brand-muted);">รวมการจองทุกสถานะ</span>
            </div>
            <div class="info-card" style="margin-top:0;">
                <strong>รายได้เดือนนี้</strong>
                <span style="font-size:1.4rem; font-weight:700;"><?= format_currency($monthly_revenue['total_revenue'] ?? 0); ?></span>
                <span style="color:var(--brand-muted);"><?= date('F Y'); ?></span>
            </div>
            <div class="info-card" style="margin-top:0;">
                <strong>ชำระแล้ว</strong>
                <span style="font-size:1.2rem; font-weight:700;"><?= $payment_stats['verified_payments'] ?? 0; ?> รายการ</span>
                <span style="color:var(--brand-muted);">รวมสลิปที่ยืนยันแล้ว</span>
            </div>
        </div>
    </div>

    <div class="booking-panel">
        <h3 style="margin-top:0;">สถิติการชำระเงิน</h3>
        <div class="dashboard__grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
            <div class="info-card" style="margin-top:0;">
                <strong>รอตรวจสอบ</strong>
                <span style="font-size:1.2rem; font-weight:700;"><?= $payment_stats['pending_payments'] ?? 0; ?></span>
                <span style="color:var(--brand-muted);">ต้องตรวจสอบสลิป</span>
            </div>
            <div class="info-card" style="margin-top:0;">
                <strong>ยืนยันแล้ว</strong>
                <span style="font-size:1.2rem; font-weight:700;"><?= $payment_stats['verified_payments'] ?? 0; ?></span>
                <span style="color:var(--brand-muted);">เตรียมจัดส่งอุปกรณ์</span>
            </div>
            <div class="info-card" style="margin-top:0;">
                <strong>ถูกปฏิเสธ</strong>
                <span style="font-size:1.2rem; font-weight:700;"><?= $payment_stats['rejected_payments'] ?? 0; ?></span>
                <span style="color:var(--brand-muted);">ต้องติดต่อผู้จอง</span>
            </div>
        </div>
    </div>
</div>

<div class="booking-panel">
    <div class="admin-toolbar">
        <div>
            <h3 style="margin:0;">การจองล่าสุด</h3>
            <p style="margin:6px 0 0; color:var(--brand-muted);">10 รายการล่าสุดที่เพิ่งเข้าระบบ</p>
        </div>
        <a class="btn btn-ghost" href="/pages/admin/bookings.php">จัดการการจองทั้งหมด</a>
    </div>

    <div class="table-shell" style="margin-top:20px;">
        <?php if (empty($recent_bookings)) : ?>
            <div class="empty-state">
                <i class="fa-solid fa-calendar-xmark" style="font-size:2rem;"></i>
                <p style="margin-top:12px;">ยังไม่มีการจองล่าสุด</p>
            </div>
        <?php else : ?>
            <table>
                <thead>
                    <tr>
                        <th>รหัสจอง</th>
                        <th>ลูกค้า</th>
                        <th>แพ็คเกจ</th>
                        <th>ช่วงวันที่</th>
                        <th>สถานะ</th>
                        <th>ยอดรวม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_bookings as $booking_item) : ?>
                        <tr>
                            <td><?= htmlspecialchars($booking_item['booking_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars(($booking_item['first_name'] ?? '') . ' ' . ($booking_item['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($booking_item['package_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= format_thai_date($booking_item['pickup_date']); ?> - <?= format_thai_date($booking_item['return_date']); ?></td>
                            <td><?= render_status_badge($booking_item['status']); ?></td>
                            <td><?= format_currency($booking_item['total_price']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="dashboard__grid">
    <div class="booking-panel">
        <div class="admin-toolbar">
            <div>
                <h3 style="margin:0;">สลิปที่รอตรวจสอบ</h3>
                <p style="margin:6px 0 0; color:var(--brand-muted);">ตรวจสอบการชำระเงินเพื่อยืนยันการจอง</p>
            </div>
            <a class="btn btn-ghost" href="/pages/admin/payments.php">ดูทั้งหมด</a>
        </div>

        <div class="table-shell" style="margin-top:20px;">
            <?php if (empty($pending_payments)) : ?>
                <div class="empty-state">
                    <i class="fa-solid fa-circle-check" style="font-size:2rem;"></i>
                    <p style="margin-top:12px;">ไม่มีสลิปค้างตรวจสอบในขณะนี้</p>
                </div>
            <?php else : ?>
                <table>
                    <thead>
                        <tr>
                            <th>รหัสจอง</th>
                            <th>ลูกค้า</th>
                            <th>ยอดชำระ</th>
                            <th>สถานะ</th>
                            <th>ดูรายละเอียด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_payments as $payment_item) : ?>
                            <tr>
                                <td><?= htmlspecialchars($payment_item['booking_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars(($payment_item['first_name'] ?? '') . ' ' . ($payment_item['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= format_currency($payment_item['amount']); ?></td>
                                <td><?= render_payment_badge($payment_item['status']); ?></td>
                                <td>
                                    <a class="btn btn-primary" style="padding:0.45rem 1rem;" href="/pages/admin/payment_detail.php?id=<?= urlencode($payment_item['id']); ?>">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="booking-panel">
        <h3 style="margin-top:0;">แพ็คเกจยอดนิยม</h3>
        <div class="stat-stack" style="margin-top:20px;">
            <?php if (empty($popular_packages)) : ?>
                <div class="empty-state">
                    <i class="fa-solid fa-box" style="font-size:2rem;"></i>
                    <p style="margin-top:12px;">ยังไม่มีข้อมูลความนิยมแพ็คเกจ</p>
                </div>
            <?php else : ?>
                <?php foreach ($popular_packages as $package_row) : ?>
                    <div class="info-card" style="margin-top:0;">
                        <strong><?= htmlspecialchars($package_row['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>จองแล้ว <?= number_format($package_row['bookings_count']); ?> ครั้ง</span>
                        <span>รายได้รวม <?= format_currency($package_row['total_revenue'] ?? 0); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
render_admin_page_end();
