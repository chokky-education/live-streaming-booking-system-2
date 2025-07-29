<?php
/**
 * Admin Customers Management
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../models/User.php';
require_once '../../models/Booking.php';

// ตรวจสอบสิทธิ์ admin
require_admin();

$error_message = '';
$success_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $user = new User($db);
    $booking = new Booking($db);
    
    // ดึงข้อมูลลูกค้าทั้งหมด
    $customers = $user->getAllCustomers();
    
    // ดึงสถิติลูกค้า
    $total_customers = count($customers);
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event("Admin customers error: " . $e->getMessage(), 'ERROR');
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการลูกค้า - Admin Dashboard</title>
    
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
        
        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
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
                        <a class="nav-link" href="payments.php">
                            <i class="fas fa-credit-card me-2"></i>
                            จัดการการชำระเงิน
                        </a>
                        <a class="nav-link" href="packages.php">
                            <i class="fas fa-box me-2"></i>
                            จัดการแพ็คเกจ
                        </a>
                        <a class="nav-link active" href="customers.php">
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
                        <h2>จัดการลูกค้า</h2>
                        <p class="text-muted mb-0">ดูข้อมูลและจัดการลูกค้าทั้งหมด</p>
                    </div>
                    <div class="text-end">
                        <div class="h4 text-primary mb-0"><?php echo number_format($total_customers); ?></div>
                        <small class="text-muted">ลูกค้าทั้งหมด</small>
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

                <!-- Customers Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>
                            รายชื่อลูกค้า
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ลูกค้า</th>
                                        <th>Username</th>
                                        <th>อีเมล</th>
                                        <th>เบอร์โทร</th>
                                        <th>วันที่สมัคร</th>
                                        <th>การจอง</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($customers)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                ไม่มีข้อมูลลูกค้า
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($customers as $customer): ?>
                                            <?php
                                            // ดึงจำนวนการจองของลูกค้า
                                            $customer_bookings = $booking->getUserBookings($customer['id'], 1000);
                                            $total_bookings = count($customer_bookings);
                                            $confirmed_bookings = count(array_filter($customer_bookings, function($b) {
                                                return $b['status'] === 'confirmed';
                                            }));
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="customer-avatar me-3">
                                                            <?php echo strtoupper(substr($customer['first_name'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold">
                                                                <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code><?php echo htmlspecialchars($customer['username']); ?></code>
                                                </td>
                                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                                <td><?php echo htmlspecialchars($customer['phone'] ?: 'ไม่ระบุ'); ?></td>
                                                <td>
                                                    <small><?php echo format_thai_date($customer['created_at']); ?></small>
                                                </td>
                                                <td>
                                                    <div>
                                                        <span class="badge bg-primary"><?php echo $total_bookings; ?> ครั้ง</span>
                                                        <?php if ($confirmed_bookings > 0): ?>
                                                            <span class="badge bg-success"><?php echo $confirmed_bookings; ?> สำเร็จ</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#customerModal<?php echo $customer['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <a href="bookings.php?customer=<?php echo $customer['id']; ?>" 
                                                           class="btn btn-sm btn-outline-secondary">
                                                            <i class="fas fa-calendar-alt"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Customer Detail Modal -->
                                            <div class="modal fade" id="customerModal<?php echo $customer['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">
                                                                ข้อมูลลูกค้า - <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <h6>ข้อมูลส่วนตัว</h6>
                                                                    <p><strong>ชื่อ-นามสกุล:</strong> <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></p>
                                                                    <p><strong>Username:</strong> <?php echo htmlspecialchars($customer['username']); ?></p>
                                                                    <p><strong>อีเมล:</strong> <?php echo htmlspecialchars($customer['email']); ?></p>
                                                                    <p><strong>เบอร์โทร:</strong> <?php echo htmlspecialchars($customer['phone'] ?: 'ไม่ระบุ'); ?></p>
                                                                    <p><strong>วันที่สมัคร:</strong> <?php echo format_thai_date($customer['created_at']); ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <h6>สถิติการใช้งาน</h6>
                                                                    <p><strong>การจองทั้งหมด:</strong> <?php echo $total_bookings; ?> ครั้ง</p>
                                                                    <p><strong>การจองที่สำเร็จ:</strong> <?php echo $confirmed_bookings; ?> ครั้ง</p>
                                                                    
                                                                    <?php if (!empty($customer_bookings)): ?>
                                                                        <h6 class="mt-3">การจองล่าสุด</h6>
                                                                        <?php foreach (array_slice($customer_bookings, 0, 3) as $recent_booking): ?>
                                                                            <div class="border-bottom pb-2 mb-2">
                                                                                <small>
                                                                                    <strong><?php echo htmlspecialchars($recent_booking['booking_code']); ?></strong><br>
                                                                                    <?php echo htmlspecialchars($recent_booking['package_name']); ?><br>
                                                                                    <?php echo format_thai_date($recent_booking['booking_date']); ?>
                                                                                    <span class="badge bg-<?php echo $recent_booking['status'] === 'confirmed' ? 'success' : ($recent_booking['status'] === 'pending' ? 'warning' : 'secondary'); ?>">
                                                                                        <?php echo $recent_booking['status']; ?>
                                                                                    </span>
                                                                                </small>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                                                            <a href="bookings.php?customer=<?php echo $customer['id']; ?>" 
                                                               class="btn btn-primary">ดูการจองทั้งหมด</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
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
</body>
</html>