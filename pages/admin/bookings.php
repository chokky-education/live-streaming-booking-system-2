<?php
/**
 * Admin Bookings Management
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../models/Booking.php';
require_once '../../models/Payment.php';

// ตรวจสอบสิทธิ์ admin
require_admin();

$error_message = '';
$success_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $booking = new Booking($db);
    $payment = new Payment($db);
    
    // ประมวลผลการอัปเดตสถานะ
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
        if (!verify_csrf_token($_POST['csrf_token'])) {
            $error_message = 'Invalid CSRF token';
        } else {
            $booking_id = (int)$_POST['booking_id'];
            $new_status = $_POST['status'];
            
            if ($booking->getById($booking_id)) {
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
    
    // ดึงข้อมูลการจอง
    $status_filter = isset($_GET['status']) ? $_GET['status'] : null;
    $bookings = $booking->getAllBookings($status_filter, 50);
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event("Admin bookings error: " . $e->getMessage(), 'ERROR');
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
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการจอง - Admin Dashboard</title>
    
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
                        <a class="nav-link active" href="bookings.php">
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
                        <h2>จัดการการจอง</h2>
                        <p class="text-muted mb-0">จัดการและติดตามสถานะการจองทั้งหมด</p>
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

                <!-- Filter Tabs -->
                <div class="card mb-4">
                    <div class="card-body">
                        <ul class="nav nav-pills filter-tabs">
                            <li class="nav-item">
                                <a class="nav-link <?php echo !$status_filter ? 'active' : ''; ?>" 
                                   href="bookings.php">
                                    ทั้งหมด
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" 
                                   href="bookings.php?status=pending">
                                    รอดำเนินการ
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $status_filter === 'confirmed' ? 'active' : ''; ?>" 
                                   href="bookings.php?status=confirmed">
                                    ยืนยันแล้ว
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>" 
                                   href="bookings.php?status=cancelled">
                                    ยกเลิก
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $status_filter === 'completed' ? 'active' : ''; ?>" 
                                   href="bookings.php?status=completed">
                                    เสร็จสิ้น
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Bookings Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            รายการการจอง
                            <?php if ($status_filter): ?>
                                - <?php 
                                    $status_names = [
                                        'pending' => 'รอดำเนินการ',
                                        'confirmed' => 'ยืนยันแล้ว',
                                        'cancelled' => 'ยกเลิก',
                                        'completed' => 'เสร็จสิ้น'
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
                                        <th>รหัสจอง</th>
                                        <th>ลูกค้า</th>
                                        <th>แพ็คเกจ</th>
                                        <th>วันที่ใช้งาน</th>
                                        <th>สถานที่</th>
                                        <th>ราคา</th>
                                        <th>สถานะ</th>
                                        <th>วันที่จอง</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($bookings)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4 text-muted">
                                                ไม่มีข้อมูลการจอง
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($bookings as $booking_item): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($booking_item['booking_code']); ?></strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <?php echo htmlspecialchars($booking_item['first_name'] . ' ' . $booking_item['last_name']); ?>
                                                    </div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($booking_item['email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($booking_item['package_name']); ?></td>
                                                <td>
                                                    <?php echo format_thai_date($booking_item['booking_date']); ?>
                                                    <?php if ($booking_item['start_time']): ?>
                                                        <br><small class="text-muted">
                                                            <?php echo date('H:i', strtotime($booking_item['start_time'])); ?>
                                                            <?php if ($booking_item['end_time']): ?>
                                                                - <?php echo date('H:i', strtotime($booking_item['end_time'])); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars(substr($booking_item['location'], 0, 50)); ?>
                                                    <?php echo strlen($booking_item['location']) > 50 ? '...' : ''; ?></small>
                                                </td>
                                                <td><?php echo format_currency($booking_item['total_price']); ?></td>
                                                <td><?php echo getStatusBadge($booking_item['status']); ?></td>
                                                <td>
                                                    <small><?php echo format_thai_date($booking_item['created_at']); ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary btn-action" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#bookingModal<?php echo $booking_item['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <?php if ($booking_item['status'] === 'pending'): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-success btn-action"
                                                                    onclick="updateStatus(<?php echo $booking_item['id']; ?>, 'confirmed')">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger btn-action"
                                                                    onclick="updateStatus(<?php echo $booking_item['id']; ?>, 'cancelled')">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php elseif ($booking_item['status'] === 'confirmed'): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-info btn-action"
                                                                    onclick="updateStatus(<?php echo $booking_item['id']; ?>, 'completed')">
                                                                <i class="fas fa-flag-checkered"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Booking Detail Modal -->
                                            <div class="modal fade" id="bookingModal<?php echo $booking_item['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">
                                                                รายละเอียดการจอง - <?php echo htmlspecialchars($booking_item['booking_code']); ?>
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <h6>ข้อมูลลูกค้า</h6>
                                                                    <p><strong>ชื่อ:</strong> <?php echo htmlspecialchars($booking_item['first_name'] . ' ' . $booking_item['last_name']); ?></p>
                                                                    <p><strong>อีเมล:</strong> <?php echo htmlspecialchars($booking_item['email']); ?></p>
                                                                    <p><strong>เบอร์โทร:</strong> <?php echo htmlspecialchars($booking_item['phone'] ?: 'ไม่ระบุ'); ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <h6>ข้อมูลการจอง</h6>
                                                                    <p><strong>แพ็คเกจ:</strong> <?php echo htmlspecialchars($booking_item['package_name']); ?></p>
                                                                    <p><strong>วันที่ใช้งาน:</strong> <?php echo format_thai_date($booking_item['booking_date']); ?></p>
                                                                    <p><strong>เวลา:</strong> 
                                                                        <?php if ($booking_item['start_time']): ?>
                                                                            <?php echo date('H:i', strtotime($booking_item['start_time'])); ?>
                                                                            <?php if ($booking_item['end_time']): ?>
                                                                                - <?php echo date('H:i', strtotime($booking_item['end_time'])); ?>
                                                                            <?php endif; ?>
                                                                        <?php else: ?>
                                                                            ไม่ระบุ
                                                                        <?php endif; ?>
                                                                    </p>
                                                                    <p><strong>ราคารวม:</strong> <?php echo format_currency($booking_item['total_price']); ?></p>
                                                                    <p><strong>สถานะ:</strong> <?php echo getStatusBadge($booking_item['status']); ?></p>
                                                                </div>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <h6>สถานที่ใช้งาน</h6>
                                                                    <p><?php echo htmlspecialchars($booking_item['location']); ?></p>
                                                                    
                                                                    <?php if ($booking_item['notes']): ?>
                                                                        <h6>หมายเหตุ</h6>
                                                                        <p><?php echo htmlspecialchars($booking_item['notes']); ?></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
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

    <!-- Status Update Form -->
    <form id="statusUpdateForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="booking_id" id="statusBookingId">
        <input type="hidden" name="status" id="statusValue">
        <input type="hidden" name="update_status" value="1">
    </form>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function updateStatus(bookingId, status) {
            const statusNames = {
                'confirmed': 'ยืนยัน',
                'cancelled': 'ยกเลิก',
                'completed': 'เสร็จสิ้น'
            };
            
            if (confirm(`คุณต้องการ${statusNames[status]}การจองนี้หรือไม่?`)) {
                document.getElementById('statusBookingId').value = bookingId;
                document.getElementById('statusValue').value = status;
                document.getElementById('statusUpdateForm').submit();
            }
        }
        
        // Auto refresh every 2 minutes
        setTimeout(function() {
            location.reload();
        }, 120000);
    </script>
</body>
</html>