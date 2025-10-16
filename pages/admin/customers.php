<?php
/**
 * Admin Customers Management
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_layout.php';
require_once '../../models/User.php';
require_once '../../models/Booking.php';

require_admin();

$error_message = '';
$success_message = '';
$customers = [];
$total_customers = 0;

try {
    $database = new Database();
    $db = $database->getConnection();

    $user = new User($db);
    $booking = new Booking($db);

    $customers = $user->getAllCustomers();
    $total_customers = count($customers);
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event('Admin customers error: ' . $e->getMessage(), 'ERROR');
}

render_admin_page_start('จัดการลูกค้า - ' . SITE_NAME, [
    'active' => 'customers',
]);
?>

<section class="admin-toolbar">
    <div>
        <h1 style="margin:0;">จัดการลูกค้า</h1>
        <p style="margin:6px 0 0; color:var(--brand-muted);">ดูข้อมูลผู้ใช้งานและประวัติการจองสูงสุด 10 รายการล่าสุด</p>
    </div>
    <div class="metric-card" style="padding:16px 24px; box-shadow:none;">
        <h3 style="margin:0;">ลูกค้าทั้งหมด</h3>
        <strong><?= number_format($total_customers); ?></strong>
    </div>
</section>

<?php if ($error_message !== '') : ?>
    <div class="alert alert-danger" role="alert"><?= $error_message; ?></div>
<?php endif; ?>

<?php if ($success_message !== '') : ?>
    <div class="alert alert-success" role="alert"><?= $success_message; ?></div>
<?php endif; ?>

<div class="table-shell">
    <div class="admin-toolbar" style="margin-bottom:16px;">
        <div>
            <h3 style="margin:0;">รายการลูกค้า</h3>
            <p style="margin:4px 0 0; color:var(--brand-muted);">รายชื่อผู้ใช้ทั้งหมดพร้อมช่องทางติดต่อ</p>
        </div>
        <a class="btn btn-primary" href="/pages/admin/reports.php"><i class="fa-solid fa-user-chart"></i>&nbsp;ส่งออกข้อมูล</a>
    </div>

    <?php if (empty($customers)) : ?>
        <div class="empty-state">
            <i class="fa-solid fa-users" style="font-size:2rem;"></i>
            <p style="margin-top:12px;">ยังไม่มีข้อมูลลูกค้า</p>
        </div>
    <?php else : ?>
        <table>
            <thead>
                <tr>
                    <th>ชื่อ-นามสกุล</th>
                    <th>อีเมล</th>
                    <th>เบอร์ติดต่อ</th>
                    <th>วันที่สมัคร</th>
                    <th>จำนวนการจอง</th>
                    <th>ยอดรวม</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer) : ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <div style="color:var(--brand-muted); font-size:0.85rem;">@<?= htmlspecialchars($customer['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                        </td>
                        <td><?= htmlspecialchars($customer['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($customer['phone'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= format_thai_date($customer['created_at'] ?? date('Y-m-d')); ?></td>
                        <td><?= number_format($customer['total_bookings'] ?? 0); ?></td>
                        <td><?= format_currency($customer['total_spent'] ?? 0); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
render_admin_page_end();
