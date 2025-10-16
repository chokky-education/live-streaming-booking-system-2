<?php
/**
 * Admin Payments Management
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_layout.php';
require_once '../../models/Payment.php';

require_admin();

$error_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();

    $payment = new Payment($db);
    $status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : null;
    $payments = $payment->getAllPayments($status_filter, 50);
    $payment_stats = $payment->getPaymentStatistics();
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event('Admin payments error: ' . $e->getMessage(), 'ERROR');
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

render_admin_page_start('จัดการการชำระเงิน - ' . SITE_NAME, [
    'active' => 'payments',
]);
?>

<section class="admin-toolbar">
    <div>
        <h1 style="margin:0;">จัดการการชำระเงิน</h1>
        <p style="margin:6px 0 0; color:var(--brand-muted);">ตรวจสอบสถานะการโอนเงินและยืนยันสลิปจากลูกค้า</p>
    </div>
    <a class="btn btn-primary" href="/pages/admin/reports.php"><i class="fa-solid fa-receipt"></i>&nbsp;สรุปรายงานการเงิน</a>
</section>

<?php if ($error_message !== '') : ?>
    <div class="alert alert-danger" role="alert"><?= $error_message; ?></div>
<?php endif; ?>

<div class="dashboard__grid">
    <div class="metric-card">
        <h3>การชำระเงินทั้งหมด</h3>
        <strong><?= number_format($payment_stats['total_payments'] ?? 0); ?></strong>
        <p style="margin:8px 0 0; color:var(--brand-muted);">ในระบบทั้งหมด</p>
    </div>
    <div class="metric-card">
        <h3>รอตรวจสอบ</h3>
        <strong><?= number_format($payment_stats['pending_payments'] ?? 0); ?></strong>
        <p style="margin:8px 0 0; color:var(--brand-muted);">ต้องตรวจสอบสลิป</p>
    </div>
    <div class="metric-card">
        <h3>ยืนยันแล้ว</h3>
        <strong><?= number_format($payment_stats['verified_payments'] ?? 0); ?></strong>
        <p style="margin:8px 0 0; color:var(--brand-muted);">พร้อมดำเนินการส่งมอบ</p>
    </div>
    <div class="metric-card">
        <h3>ยอดยืนยันสะสม</h3>
        <strong><?= format_currency($payment_stats['total_verified_amount'] ?? 0); ?></strong>
        <p style="margin:8px 0 0; color:var(--brand-muted);">รวมยอดที่อนุมัติทั้งหมด</p>
    </div>
</div>

<div class="info-card" style="margin-top:0;">
    <strong>กรองตามสถานะ</strong>
    <div class="filter-tabs">
        <?php
        $filters = [
            null => 'ทั้งหมด',
            'pending' => 'รอตรวจสอบ',
            'verified' => 'ยืนยันแล้ว',
            'rejected' => 'ปฏิเสธ',
        ];
        foreach ($filters as $key => $label) :
            $is_active = ($key === null && $status_filter === null) || ($key !== null && $status_filter === $key);
            $href = $key ? '/pages/admin/payments.php?status=' . urlencode($key) : '/pages/admin/payments.php';
        ?>
            <a class="filter-chip <?= $is_active ? 'active' : ''; ?>" href="<?= $href; ?>"><?= $label; ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="table-shell">
    <div class="admin-toolbar" style="margin-bottom:16px;">
        <div>
            <h3 style="margin:0;">รายการชำระเงินล่าสุด</h3>
            <p style="margin:4px 0 0; color:var(--brand-muted);">สูงสุด 50 รายการเรียงตามเวลาที่ชำระ</p>
        </div>
        <a class="btn btn-ghost" href="/pages/admin/payments.php">รีเซ็ตตัวกรอง</a>
    </div>

    <?php if (empty($payments)) : ?>
        <div class="empty-state">
            <i class="fa-solid fa-credit-card" style="font-size:2rem;"></i>
            <p style="margin-top:12px;">ไม่มีข้อมูลการชำระเงิน</p>
        </div>
    <?php else : ?>
        <table>
            <thead>
                <tr>
                    <th>รหัสจอง</th>
                    <th>ลูกค้า</th>
                    <th>แพ็คเกจ</th>
                    <th>จำนวนเงิน</th>
                    <th>วันที่ชำระ</th>
                    <th>สถานะ</th>
                    <th>สลิป</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $item) : ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['booking_code'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                        <td>
                            <div><?= htmlspecialchars(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <small style="color:var(--brand-muted);">
                                <?= htmlspecialchars($item['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                            </small>
                        </td>
                        <td><?= htmlspecialchars($item['package_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><strong><?= format_currency($item['amount']); ?></strong></td>
                        <td>
                            <?php if (!empty($item['paid_at'])) : ?>
                                <?= format_thai_date($item['paid_at']); ?>
                            <?php else : ?>
                                <span style="color:var(--brand-muted);">ไม่ระบุ</span>
                            <?php endif; ?>
                        </td>
                        <td><?= payment_status_badge($item['status']); ?></td>
                        <td>
                            <?php if (!empty($item['slip_image_url'])) : ?>
                                <a class="btn btn-ghost" href="/<?= ltrim($item['slip_image_url'], '/'); ?>" target="_blank" rel="noopener">
                                    <i class="fa-solid fa-image"></i>
                                </a>
                            <?php else : ?>
                                <span style="color:var(--brand-muted);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a class="btn btn-primary" href="/pages/admin/payment_detail.php?id=<?= urlencode($item['id']); ?>">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <a class="btn btn-ghost" href="/pages/admin/booking_detail.php?id=<?= urlencode($item['booking_id']); ?>">
                                    <i class="fa-solid fa-calendar-days"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
render_admin_page_end();
