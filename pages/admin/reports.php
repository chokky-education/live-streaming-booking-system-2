<?php
/**
 * Admin Reports
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_layout.php';
require_once '../../models/Booking.php';
require_once '../../models/Payment.php';
require_once '../../models/Package.php';
require_once '../../models/User.php';

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
    $package_stats = $package->getBookingStats();
    $total_customers = count($user->getAllCustomers());

    $monthly_data = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $start_date = $month . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));

        $monthly_revenue = $payment->getTotalRevenue($start_date, $end_date);
        $monthly_bookings = $booking->getBookingsByDateRange($start_date, $end_date);

        $monthly_data[] = [
            'month_key' => date('M Y', strtotime($start_date)),
            'revenue' => $monthly_revenue['total_revenue'] ?? 0,
            'bookings' => count($monthly_bookings),
            'confirmed' => count(array_filter($monthly_bookings, static function ($row) {
                return in_array($row['status'], ['confirmed', 'completed'], true);
            })),
        ];
    }
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event('Admin reports error: ' . $e->getMessage(), 'ERROR');
}

render_admin_page_start('รายงานและสถิติ - ' . SITE_NAME, [
    'active' => 'reports',
]);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<section class="admin-toolbar">
    <div>
        <h1 style="margin:0;">รายงานและสถิติ</h1>
        <p style="margin:6px 0 0; color:var(--brand-muted);">สรุปผลการดำเนินงานย้อนหลัง 6 เดือนล่าสุด</p>
    </div>
    <a class="btn btn-primary" href="/pages/admin/reports.php?download=1"><i class="fa-solid fa-file-arrow-down"></i>&nbsp;ดาวน์โหลดรายงาน</a>
</section>

<?php if ($error_message !== '') : ?>
    <div class="alert alert-danger" role="alert"><?= $error_message; ?></div>
<?php endif; ?>

<div class="dashboard__grid">
    <div class="metric-card">
        <h3>การจองทั้งหมด</h3>
        <strong><?= number_format($booking_stats['total_bookings'] ?? 0); ?></strong>
        <p style="margin:8px 0 0; color:var(--brand-muted);">ตั้งแต่เปิดระบบ</p>
    </div>
    <div class="metric-card">
        <h3>รายได้รวม</h3>
        <strong><?= format_currency($booking_stats['total_revenue'] ?? 0); ?></strong>
        <p style="margin:8px 0 0; color:var(--brand-muted);">รวมภาษีมูลค่าเพิ่ม</p>
    </div>
    <div class="metric-card">
        <h3>ลูกค้าทั้งหมด</h3>
        <strong><?= number_format($total_customers); ?></strong>
        <p style="margin:8px 0 0; color:var(--brand-muted);">บัญชีที่มีการจอง</p>
    </div>
    <div class="metric-card">
        <h3>สลิปรอตรวจสอบ</h3>
        <strong><?= number_format($payment_stats['pending_payments'] ?? 0); ?></strong>
        <p style="margin:8px 0 0; color:var(--brand-muted);">ควรตรวจสอบภายใน 12 ชั่วโมง</p>
    </div>
</div>

<div class="dashboard__grid">
    <div class="booking-panel">
        <h3 style="margin-top:0;">รายได้และการจองรายเดือน</h3>
        <div class="chart-container" style="height:360px; margin-top:20px;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
    <div class="booking-panel">
        <h3 style="margin-top:0;">ประสิทธิภาพแพ็คเกจ</h3>
        <div class="stat-stack" style="margin-top:20px;">
            <?php if (empty($package_stats)) : ?>
                <div class="empty-state">
                    <i class="fa-solid fa-box"></i>
                    <p style="margin-top:12px;">ยังไม่มีข้อมูลแพ็คเกจ</p>
                </div>
            <?php else : ?>
                <?php foreach ($package_stats as $pkg) :
                    $bookings_count = $pkg['total_bookings'] ?? 0;
                    $revenue = $pkg['total_revenue'] ?? 0;
                ?>
                    <div class="info-card" style="margin-top:0;">
                        <strong><?= htmlspecialchars($pkg['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>การจอง: <?= number_format($bookings_count); ?> ครั้ง</span>
                        <span>รายได้รวม: <?= format_currency($revenue); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="table-shell">
    <h3 style="margin-top:0;">ตารางสรุปรายเดือน</h3>
    <?php if (empty($monthly_data)) : ?>
        <div class="empty-state">
            <i class="fa-solid fa-calendar"></i>
            <p style="margin-top:12px;">ไม่มีข้อมูลรายเดือน</p>
        </div>
    <?php else : ?>
        <table>
            <thead>
                <tr>
                    <th>เดือน</th>
                    <th>การจองทั้งหมด</th>
                    <th>การจองที่ยืนยัน</th>
                    <th>รายได้</th>
                    <th>อัตราความสำเร็จ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthly_data as $row) :
                    $success_rate = $row['bookings'] > 0 ? ($row['confirmed'] / $row['bookings']) * 100 : 0;
                    $rate_class = $success_rate >= 80 ? 'badge--confirmed' : ($success_rate >= 60 ? 'badge--pending' : 'badge--cancelled');
                ?>
                    <tr>
                        <td><?= $row['month_key']; ?></td>
                        <td><?= number_format($row['bookings']); ?></td>
                        <td><?= number_format($row['confirmed']); ?></td>
                        <td><?= format_currency($row['revenue']); ?></td>
                        <td><span class="badge <?= $rate_class; ?>"><?= number_format($success_rate, 1); ?>%</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
    const monthlyData = <?= json_encode($monthly_data); ?>;
    if (monthlyData.length > 0) {
        const ctx = document.getElementById('revenueChart');
        const bookingsDataset = monthlyData.map(item => item.bookings);
        const confirmedDataset = monthlyData.map(item => item.confirmed);
        const revenueDataset = monthlyData.map(item => item.revenue);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => item.month_key),
                datasets: [
                    {
                        label: 'รายได้ (บาท)',
                        data: revenueDataset,
                        borderColor: '#0f9cb9',
                        backgroundColor: 'rgba(15, 156, 185, 0.12)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.35,
                        yAxisID: 'y',
                    },
                    {
                        label: 'การจองที่ยืนยัน',
                        data: confirmedDataset,
                        borderColor: '#1dd1a1',
                        backgroundColor: 'rgba(29, 209, 161, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.35,
                        yAxisID: 'y1',
                    },
                    {
                        label: 'การจองทั้งหมด',
                        data: bookingsDataset,
                        borderColor: '#0c1336',
                        backgroundColor: 'rgba(12, 19, 54, 0.08)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.35,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        grid: { color: 'rgba(12, 19, 54, 0.08)' },
                        ticks: {
                            callback: value => new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(value)
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    }
</script>

<?php
render_admin_page_end();
