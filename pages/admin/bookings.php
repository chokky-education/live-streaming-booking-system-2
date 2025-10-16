<?php
/**
 * Admin Bookings Management
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

try {
    $db = get_db_connection();

    $booking = new Booking($db);
    $payment = new Payment($db);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $error_message = 'Invalid CSRF token';
        } else {
            $booking_id = (int)($_POST['booking_id'] ?? 0);
            $new_status = $_POST['status'] ?? '';

            if ($booking_id > 0 && $booking->getById($booking_id)) {
                $booking->id = $booking_id;
                if ($booking->updateStatus($new_status)) {
                    $success_message = 'อัปเดตสถานะการจองสำเร็จ';
                    log_event("Admin updated booking {$booking_id} status to {$new_status}", 'INFO');
                } else {
                    $error_message = 'เกิดข้อผิดพลาดในการอัปเดตสถานะ';
                }
            } else {
                $error_message = 'ไม่พบข้อมูลการจอง';
            }
        }
    }

    $status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : null;
    $bookings = $booking->getAllBookings($status_filter, 50);
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event('Admin bookings error: ' . $e->getMessage(), 'ERROR');
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

render_admin_page_start('จัดการการจอง - ' . SITE_NAME, [
    'active' => 'bookings',
]);
?>

<section class="admin-toolbar">
    <div>
        <h1 style="margin:0;">จัดการการจอง</h1>
        <p style="margin:6px 0 0; color:var(--brand-muted);">ติดตาม สรุป และอัปเดตสถานะการจองทั้งหมดในระบบ</p>
    </div>
    <a class="btn btn-primary" href="/pages/admin/reports.php"><i class="fa-solid fa-chart-line"></i>&nbsp;สรุปรายงาน</a>
</section>

<?php if ($error_message !== '') : ?>
    <div class="alert alert-danger" role="alert"><?= $error_message; ?></div>
<?php endif; ?>

<?php if ($success_message !== '') : ?>
    <div class="alert alert-success" role="alert"><?= $success_message; ?></div>
<?php endif; ?>

<div class="info-card" style="margin-top:0;">
    <strong>กรองตามสถานะ</strong>
    <div class="filter-tabs">
        <?php
        $filters = [
            null => 'ทั้งหมด',
            'pending' => 'รอดำเนินการ',
            'confirmed' => 'ยืนยันแล้ว',
            'cancelled' => 'ยกเลิก',
            'completed' => 'เสร็จสิ้น',
        ];
        foreach ($filters as $key => $label) :
            $is_active = ($key === null && $status_filter === null) || ($key !== null && $status_filter === $key);
            $href = $key ? '/pages/admin/bookings.php?status=' . urlencode($key) : '/pages/admin/bookings.php';
        ?>
            <a class="filter-chip <?= $is_active ? 'active' : ''; ?>" href="<?= $href; ?>"><?= $label; ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="table-shell">
    <div class="admin-toolbar" style="margin-bottom:16px;">
        <div>
            <h3 style="margin:0;">รายการการจอง</h3>
            <?php if ($status_filter) : ?>
                <?php
                $labels = [
                    'pending' => 'รอดำเนินการ',
                    'confirmed' => 'ยืนยันแล้ว',
                    'cancelled' => 'ยกเลิก',
                    'completed' => 'เสร็จสิ้น',
                ];
                ?>
                <p style="margin:4px 0 0; color:var(--brand-muted);">แสดงเฉพาะสถานะ: <?= $labels[$status_filter] ?? $status_filter; ?></p>
            <?php else : ?>
                <p style="margin:4px 0 0; color:var(--brand-muted);">รายการล่าสุดสูงสุด 50 รายการ</p>
            <?php endif; ?>
        </div>
        <a class="btn btn-ghost" href="/pages/admin/bookings.php">รีเซ็ตตัวกรอง</a>
    </div>

    <?php if (empty($bookings)) : ?>
        <div class="empty-state">
            <i class="fa-solid fa-calendar-xmark" style="font-size:2rem;"></i>
            <p style="margin-top:12px;">ไม่พบการจองในช่วงตัวกรองนี้</p>
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
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $item) : ?>
                    <tr>
                        <td><?= htmlspecialchars($item['booking_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($item['package_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= format_thai_date($item['pickup_date']); ?> - <?= format_thai_date($item['return_date']); ?></td>
                        <td><?= booking_status_badge($item['status']); ?></td>
                        <td><?= format_currency($item['total_price']); ?></td>
                        <td>
                            <div class="table-actions">
                                <a class="btn btn-ghost" href="/pages/admin/booking_detail.php?id=<?= urlencode($item['id']); ?>">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                </a>
                                <?php if ($item['status'] === 'pending') : ?>
                                    <button type="button" class="btn btn-primary" onclick="updateStatus(<?= (int)$item['id']; ?>, 'confirmed')">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                    <button type="button" class="btn btn-ghost" onclick="updateStatus(<?= (int)$item['id']; ?>, 'cancelled')">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                <?php elseif ($item['status'] === 'confirmed') : ?>
                                    <button type="button" class="btn btn-primary" onclick="updateStatus(<?= (int)$item['id']; ?>, 'completed')">
                                        <i class="fa-solid fa-flag-checkered"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<form id="statusUpdateForm" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
    <input type="hidden" name="booking_id" id="statusBookingId">
    <input type="hidden" name="status" id="statusValue">
    <input type="hidden" name="update_status" value="1">
</form>

<script>
    const STATUS_LABEL = {
        confirmed: 'ยืนยัน',
        cancelled: 'ยกเลิก',
        completed: 'เสร็จสิ้น'
    };

    function updateStatus(bookingId, status) {
        if (!STATUS_LABEL[status]) return;
        const confirmed = window.confirm(`คุณต้องการ${STATUS_LABEL[status]}การจองนี้หรือไม่?`);
        if (!confirmed) {
            return;
        }
        document.getElementById('statusBookingId').value = bookingId;
        document.getElementById('statusValue').value = status;
        document.getElementById('statusUpdateForm').submit();
    }

    setTimeout(() => window.location.reload(), 120000);
</script>

<?php
render_admin_page_end();
