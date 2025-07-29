<?php
/**
 * Admin Reports
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../models/Booking.php';
require_once '../../models/Payment.php';
require_once '../../models/Package.php';
require_once '../../models/User.php';

// ตรวจสอบสิทธิ์ admin
require_admin();

$error_message = '';
$success_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $booking = new Booking($db);
    $payment = new Payment($db);
    $package = new Package($db);
    $user = new User($db);
    
    // ดึงสถิติต่างๆ
    $booking_stats = $booking->getBookingStatistics();
    $payment_stats = $payment->getPaymentStatistics();
    $package_stats = $package->getBookingStats();
    $total_customers = count($user->getAllCustomers());
    
    // สถิติรายเดือน (6 เดือนล่าสุด)
    $monthly_data = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $start_date = $month . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));
        
        $monthly_revenue = $payment->getTotalRevenue($start_date, $end_date);
        $monthly_bookings = $booking->getBookingsByDateRange($start_date, $end_date);
        
        $monthly_data[] = [
            'month' => $month,
            'month_name' => date('M Y', strtotime($start_date)),
            'thai_month' => format_thai_date($start_date),
            'revenue' => $monthly_revenue['total_revenue'] ?? 0,
            'bookings' => count($monthly_bookings),
            'confirmed_bookings' => count(array_filter($monthly_bookings, function($b) {
                return $b['status'] === 'confirmed';
            }))
        ];
    }
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event("Admin reports error: " . $e->getMessage(), 'ERROR');
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงาน - Admin Dashboard</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background: #f8f9fa;
        }
        
        .sidebar {
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin: 0.2rem 0;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.2);
        }
        
        .main-content {
            padding: 2rem;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            height: 100%;
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .chart-container {
            position: relative;
            height: 400px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .chart-container {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="p-3">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-video me-2"></i>
                        Admin Panel
                    </h4>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a>
                        <a class="nav-link" href="bookings.php">
                            <i class="fas fa-calendar-alt me-2"></i>
                            จัดการการจอง
                        </a>
                        <a class="nav-link" href="payments.php">
                            <i class="fas fa-credit-card me-2"></i>
                            จัดการการชำระเงิน
                        </a>
                        <a class="nav-link" href="packages.php">
                            <i class="fas fa-box me-2"></i>
                            จัดการแพ็คเกจ
                        </a>
                        <a class="nav-link" href="customers.php">
                            <i class="fas fa-users me-2"></i>
                            จัดการลูกค้า
                        </a>
                        <a class="nav-link active" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>
                            รายงาน
                        </a>
                        <hr class="my-3">
                        <a class="nav-link" href="../profile.php">
                            <i class="fas fa-user me-2"></i>
                            โปรไฟล์
                        </a>
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            ออกจากระบบ
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>รายงานและสถิติ</h2>
                        <p class="text-muted mb-0">ภาพรวมและการวิเคราะห์ข้อมูลระบบ</p>
                    </div>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Summary Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="stats-number text-primary">
                                <?php echo number_format($booking_stats['total_bookings'] ?? 0); ?>
                            </div>
                            <div class="stats-label">การจองทั้งหมด</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="stats-number text-success">
                                <?php echo format_currency($booking_stats['total_revenue'] ?? 0); ?>
                            </div>
                            <div class="stats-label">รายได้รวม</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="stats-number text-info">
                                <?php echo number_format($total_customers); ?>
                            </div>
                            <div class="stats-label">ลูกค้าทั้งหมด</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="stats-number text-warning">
                                <?php echo number_format($payment_stats['pending_payments'] ?? 0); ?>
                            </div>
                            <div class="stats-label">รอตรวจสอบ</div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Monthly Revenue Chart -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-line me-2"></i>
                                    รายได้รายเดือน (6 เดือนล่าสุด)
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="revenueChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Package Performance -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>
                                    ประสิทธิภาพแพ็คเกจ
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($package_stats)): ?>
                                    <?php foreach ($package_stats as $pkg): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($pkg['name']); ?></h6>
                                                <small class="text-muted"><?php echo $pkg['total_bookings']; ?> การจอง</small>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-success">
                                                    <?php echo format_currency($pkg['total_revenue']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center">ยังไม่มีข้อมูล</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Data Table -->
                <div class="row g-4 mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-table me-2"></i>
                                    สรุปข้อมูลรายเดือน
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>เดือน</th>
                                                <th>การจองทั้งหมด</th>
                                                <th>การจองที่ยืนยัน</th>
                                                <th>รายได้</th>
                                                <th>อัตราความสำเร็จ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($monthly_data as $month): ?>
                                                <tr>
                                                    <td><?php echo $month['month_name']; ?></td>
                                                    <td><?php echo number_format($month['bookings']); ?></td>
                                                    <td><?php echo number_format($month['confirmed_bookings']); ?></td>
                                                    <td><?php echo format_currency($month['revenue']); ?></td>
                                                    <td>
                                                        <?php 
                                                        $success_rate = $month['bookings'] > 0 ? ($month['confirmed_bookings'] / $month['bookings']) * 100 : 0;
                                                        ?>
                                                        <span class="badge bg-<?php echo $success_rate >= 80 ? 'success' : ($success_rate >= 60 ? 'warning' : 'danger'); ?>">
                                                            <?php echo number_format($success_rate, 1); ?>%
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_data); ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => item.month_name),
                datasets: [{
                    label: 'รายได้ (บาท)',
                    data: monthlyData.map(item => item.revenue),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'การจองที่ยืนยัน',
                    data: monthlyData.map(item => item.confirmed_bookings),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'รายได้ (บาท)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'จำนวนการจอง'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
        
        // Auto refresh every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>