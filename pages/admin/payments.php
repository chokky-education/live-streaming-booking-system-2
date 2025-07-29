<?php
/**
 * Admin Payments Management
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../models/Payment.php';

// ตรวจสอบสิทธิ์ admin
require_admin();

$error_message = '';
$success_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $payment = new Payment($db);
    
    // ดึงข้อมูลการชำระเงิน
    $status_filter = isset($_GET['status']) ? $_GET['status'] : null;
    $payments = $payment->getAllPayments($status_filter, 50);
    
    // ดึงสถิติการชำระเงิน
    $payment_stats = $payment->getPaymentStatistics();
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event("Admin payments error: " . $e->getMessage(), 'ERROR');
}

// ฟังก์ชันแสดงสถานะ
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
    <title>จัดการการชำระเงิน - Admin Dashboard</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
        
        .filter-tabs .nav-link {
            border-radius: 20px;
            margin-right: 0.5rem;
            border: 2px solid transparent;
        }
        
        .filter-tabs .nav-link.active {
            background: #667eea;
            border-color: #667eea;
            color: white;
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
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
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
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a>
                        <a class="nav-link" href="bookings.php">
                            <i class="fas fa-calendar-alt me-2"></i>
                            จัดการการจอง
                        </a>
                        <a class="nav-link active" href="payments.php">
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
                        <h2>จัดการการชำระเงิน</h2>
                        <p class="text-muted mb-0">ตรวจสอบและจัดการการชำระเงินทั้งหมด</p>
                    </div>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="stats-number text-primary">
                                <?php echo number_format($payment_stats['total_payments'] ?? 0); ?>
                            </div>
                            <div class="stats-label">การชำระเงินทั้งหมด</div>
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
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="stats-number text-success">
                                <?php echo number_format($payment_stats['verified_payments'] ?? 0); ?>
                            </div>
                            <div class="stats-label">ยืนยันแล้ว</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="stats-number text-info">
                                <?php echo format_currency($payment_stats['total_verified_amount'] ?? 0); ?>
                            </div>
                            <div class="stats-label">ยอดรวมที่ยืนยัน</div>
                        </div>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <div class="card mb-4">
                    <div class="card-body">
                        <ul class="nav nav-pills filter-tabs">
                            <li class="nav-item">
                                <a class="nav-link <?php echo !$status_filter ? 'active' : ''; ?>" 
                                   href="payments.php">
                                    ทั้งหมด
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" 
                                   href="payments.php?status=pending">
                                    รอตรวจสอบ
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $status_filter === 'verified' ? 'active' : ''; ?>" 
                                   href="payments.php?status=verified">
                                    ยืนยันแล้ว
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" 
                                   href="payments.php?status=rejected">
                                    ปฏิเสธ
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Payments Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            รายการการชำระเงิน
                            <?php if ($status_filter): ?>
                                - <?php 
                                    $status_names = [
                                        'pending' => 'รอตรวจสอบ',
                                        'verified' => 'ยืนยันแล้ว',
                                        'rejected' => 'ปฏิเสธ'
                                    ];
                                    echo $status_names[$status_filter];
                                ?>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>รหัสการจอง</th>
                                        <th>ลูกค้า</th>
                                        <th>แพ็คเกจ</th>
                                        <th>จำนวนเงิน</th>
                                        <th>วันที่ชำระ</th>
                                        <th>สถานะ</th>
                                        <th>สลิป</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($payments)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">
                                                ไม่มีข้อมูลการชำระเงิน
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($payments as $payment_item): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($payment_item['booking_code']); ?></strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <?php echo htmlspecialchars($payment_item['first_name'] . ' ' . $payment_item['last_name']); ?>
                                                    </div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($payment_item['email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($payment_item['package_name']); ?></td>
                                                <td>
                                                    <strong class="text-primary"><?php echo format_currency($payment_item['amount']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($payment_item['paid_at']): ?>
                                                        <small><?php echo format_thai_date($payment_item['paid_at']); ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">ไม่ระบุ</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo getPaymentStatusBadge($payment_item['status']); ?></td>
                                                <td>
                                                    <?php if ($payment_item['slip_image_url']): ?>
                                                        <a href="../../<?php echo htmlspecialchars($payment_item['slip_image_url']); ?>" 
                                                           target="_blank" class="btn btn-sm btn-outline-info btn-action">
                                                            <i class="fas fa-image"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="payment_detail.php?id=<?php echo $payment_item['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary btn-action">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        
                                                        <a href="booking_detail.php?id=<?php echo $payment_item['booking_id']; ?>" 
                                                           class="btn btn-sm btn-outline-secondary btn-action">
                                                            <i class="fas fa-calendar-alt"></i>
                                                        </a>
                                                    </div>
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
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto refresh every 2 minutes
        setTimeout(function() {
            location.reload();
        }, 120000);
        
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stats numbers
            const statsNumbers = document.querySelectorAll('.stats-number');
            statsNumbers.forEach(stat => {
                const text = stat.textContent;
                const finalValue = parseInt(text.replace(/[^0-9]/g, ''));
                
                if (!isNaN(finalValue) && finalValue > 0) {
                    let currentValue = 0;
                    const increment = finalValue / 30;
                    const timer = setInterval(() => {
                        currentValue += increment;
                        if (currentValue >= finalValue) {
                            stat.textContent = text;
                            clearInterval(timer);
                        } else {
                            const newText = text.replace(/[0-9,]+/, Math.floor(currentValue).toLocaleString());
                            stat.textContent = newText;
                        }
                    }, 50);
                }
            });
        });
    </script>
</body>
</html>