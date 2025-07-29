<?php
/**
 * Admin Dashboard
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../models/User.php';
require_once '../../models/Booking.php';
require_once '../../models/Payment.php';
require_once '../../models/Package.php';

// ตรวจสอบสิทธิ์ admin
require_admin();

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
    $recent_bookings = $booking->getAllBookings(null, 10);
    $pending_payments = $payment->getPendingPayments(5);
    $popular_packages = $package->getPopularPackages(3);
    $total_customers = count($user->getAllCustomers());
    
    // สถิติรายเดือน
    $current_month = date('Y-m');
    $monthly_revenue = $payment->getTotalRevenue($current_month . '-01', $current_month . '-31');
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event("Admin dashboard error: " . $e->getMessage(), 'ERROR');
}

// ฟังก์ชันแสดงสถานะ
function getStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning">รอดำเนินการ</span>';
        case 'confirmed':
            return '<span class="badge bg-success">ยืนยันแล้ว</span>';
        case 'cancelled':
            return '<span class="badge bg-danger">ยกเลิก</span>';
        case 'completed':
            return '<span class="badge bg-info">เสร็จสิ้น</span>';
        default:
            return '<span class="badge bg-secondary">ไม่ทราบสถานะ</span>';
    }
}

function getPaymentStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning">รอตรวจสอบ</span>';
        case 'verified':
            return '<span class="badge bg-success">ยืนยันแล้ว</span>';
        case 'rejected':
            return '<span class="badge bg-danger">ปฏิเสธ</span>';
        default:
            return '<span class="badge bg-secondary">ไม่ทราบสถานะ</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ระบบจองอุปกรณ์ Live Streaming</title>
    
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
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
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
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }
        
        .btn-action {
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            border-radius: 20px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
            }
            
            .main-content {
                padding: 1rem;
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
                        <a class="nav-link active" href="dashboard.php">
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
                        <a class="nav-link" href="reports.php">
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
                        <h2>Dashboard</h2>
                        <p class="text-muted mb-0">ภาพรวมระบบจองอุปกรณ์ Live Streaming</p>
                    </div>
                    <div>
                        <span class="text-muted">ยินดีต้อนรับ, </span>
                        <strong><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></strong>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card text-center">
                            <div class="stats-number text-primary">
                                <?php echo number_format($booking_stats['total_bookings'] ?? 0); ?>
                            </div>
                            <div class="stats-label">การจองทั้งหมด</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card text-center">
                            <div class="stats-number text-success">
                                <?php echo number_format($booking_stats['confirmed_bookings'] ?? 0); ?>
                            </div>
                            <div class="stats-label">การจองที่ยืนยัน</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card text-center">
                            <div class="stats-number text-warning">
                                <?php echo number_format($booking_stats['pending_bookings'] ?? 0); ?>
                            </div>
                            <div class="stats-label">รอดำเนินการ</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card text-center">
                            <div class="stats-number text-info">
                                <?php echo number_format($total_customers); ?>
                            </div>
                            <div class="stats-label">ลูกค้าทั้งหมด</div>
                        </div>
                    </div>
                </div>

                <!-- Revenue Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="stats-card">
                            <h5 class="mb-3"><i class="fas fa-money-bill-wave me-2 text-success"></i>รายได้</h5>
                            <div class="row">
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="stats-number text-success">
                                            <?php echo format_currency($booking_stats['total_revenue'] ?? 0); ?>
                                        </div>
                                        <div class="stats-label">รายได้รวม</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="stats-number text-info">
                                            <?php echo format_currency($monthly_revenue['total_revenue'] ?? 0); ?>
                                        </div>
                                        <div class="stats-label">รายได้เดือนนี้</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="stats-card">
                            <h5 class="mb-3"><i class="fas fa-chart-pie me-2 text-primary"></i>สถิติการชำระเงิน</h5>
                            <div class="row">
                                <div class="col-4 text-center">
                                    <div class="stats-number text-warning" style="font-size: 1.5rem;">
                                        <?php echo $payment_stats['pending_payments'] ?? 0; ?>
                                    </div>
                                    <div class="stats-label">รอตรวจสอบ</div>
                                </div>
                                <div class="col-4 text-center">
                                    <div class="stats-number text-success" style="font-size: 1.5rem;">
                                        <?php echo $payment_stats['verified_payments'] ?? 0; ?>
                                    </div>
                                    <div class="stats-label">ยืนยันแล้ว</div>
                                </div>
                                <div class="col-4 text-center">
                                    <div class="stats-number text-danger" style="font-size: 1.5rem;">
                                        <?php echo $payment_stats['rejected_payments'] ?? 0; ?>
                                    </div>
                                    <div class="stats-label">ปฏิเสธ</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Recent Bookings -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    การจองล่าสุด
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>รหัสจอง</th>
                                                <th>ลูกค้า</th>
                                                <th>แพ็คเกจ</th>
                                                <th>วันที่ใช้งาน</th>
                                                <th>ราคา</th>
                                                <th>สถานะ</th>
                                                <th>จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recent_bookings)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center py-4 text-muted">
                                                        ไม่มีข้อมูลการจอง
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_bookings as $booking): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($booking['package_name']); ?></td>
                                                        <td><?php echo format_thai_date($booking['booking_date']); ?></td>
                                                        <td><?php echo format_currency($booking['total_price']); ?></td>
                                                        <td><?php echo getStatusBadge($booking['status']); ?></td>
                                                        <td>
                                                            <a href="booking_detail.php?id=<?php echo $booking['id']; ?>" 
                                                               class="btn btn-sm btn-outline-primary btn-action">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Payments -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-clock me-2"></i>
                                    การชำระเงินรอตรวจสอบ
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pending_payments)): ?>
                                    <p class="text-muted text-center">ไม่มีการชำระเงินรอตรวจสอบ</p>
                                <?php else: ?>
                                    <?php foreach ($pending_payments as $payment): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                                            <div>
                                                <strong><?php echo htmlspecialchars($payment['booking_code']); ?></strong><br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-primary">
                                                    <?php echo format_currency($payment['amount']); ?>
                                                </div>
                                                <a href="payment_detail.php?id=<?php echo $payment['id']; ?>" 
                                                   class="btn btn-sm btn-warning btn-action">
                                                    ตรวจสอบ
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="text-center">
                                        <a href="payments.php" class="btn btn-sm btn-outline-primary">
                                            ดูทั้งหมด
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Popular Packages -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-star me-2"></i>
                                    แพ็คเกจยอดนิยม
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($popular_packages as $pkg): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <strong><?php echo htmlspecialchars($pkg['name']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo $pkg['booking_count']; ?> การจอง
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold text-success">
                                                <?php echo format_currency($pkg['price']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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
        // Auto refresh every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
        
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stats numbers
            const statsNumbers = document.querySelectorAll('.stats-number');
            statsNumbers.forEach(stat => {
                const finalValue = parseInt(stat.textContent.replace(/,/g, ''));
                if (!isNaN(finalValue)) {
                    let currentValue = 0;
                    const increment = finalValue / 50;
                    const timer = setInterval(() => {
                        currentValue += increment;
                        if (currentValue >= finalValue) {
                            stat.textContent = finalValue.toLocaleString();
                            clearInterval(timer);
                        } else {
                            stat.textContent = Math.floor(currentValue).toLocaleString();
                        }
                    }, 20);
                }
            });
        });
    </script>
</body>
</html>