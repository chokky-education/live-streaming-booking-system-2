<?php
/**
 * Admin Packages Management
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../models/Package.php';

// ตรวจสอบสิทธิ์ admin
require_admin();

$error_message = '';
$success_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $package = new Package($db);
    
    // ประมวลผลการเพิ่ม/แก้ไขแพ็คเกจ
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_package'])) {
        if (!verify_csrf_token($_POST['csrf_token'])) {
            $error_message = 'Invalid CSRF token';
        } else {
            $package_data = [
                'name' => sanitize_input($_POST['name']),
                'description' => sanitize_input($_POST['description']),
                'price' => (float)$_POST['price'],
                'equipment_list' => array_filter(array_map('trim', explode("\n", $_POST['equipment_list']))),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            // ตรวจสอบความถูกต้องของข้อมูล
            $validation_errors = $package->validate($package_data);
            
            if (!empty($validation_errors)) {
                $error_message = implode('<br>', $validation_errors);
            } else {
                $package->name = $package_data['name'];
                $package->description = $package_data['description'];
                $package->price = $package_data['price'];
                $package->equipment_list = $package_data['equipment_list'];
                $package->is_active = $package_data['is_active'];
                
                if (isset($_POST['package_id']) && $_POST['package_id']) {
                    // แก้ไขแพ็คเกจ
                    $package->id = (int)$_POST['package_id'];
                    if ($package->update()) {
                        $success_message = 'อัปเดตแพ็คเกจสำเร็จ';
                        log_event("Admin updated package {$package->id}", 'INFO');
                    } else {
                        $error_message = 'เกิดข้อผิดพลาดในการอัปเดตแพ็คเกจ';
                    }
                } else {
                    // เพิ่มแพ็คเกจใหม่
                    if ($package->create()) {
                        $success_message = 'เพิ่มแพ็คเกจใหม่สำเร็จ';
                        log_event("Admin created new package {$package->id}", 'INFO');
                    } else {
                        $error_message = 'เกิดข้อผิดพลาดในการเพิ่มแพ็คเกจ';
                    }
                }
            }
        }
    }
    
    // ประมวลผลการลบแพ็คเกจ
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_package'])) {
        if (!verify_csrf_token($_POST['csrf_token'])) {
            $error_message = 'Invalid CSRF token';
        } else {
            $package_id = (int)$_POST['package_id'];
            $package->id = $package_id;
            
            if ($package->delete()) {
                $success_message = 'ปิดใช้งานแพ็คเกจสำเร็จ';
                log_event("Admin deactivated package {$package_id}", 'INFO');
            } else {
                $error_message = 'เกิดข้อผิดพลาดในการปิดใช้งานแพ็คเกจ';
            }
        }
    }
    
    // ดึงข้อมูลแพ็คเกจทั้งหมด
    $packages = $package->getAllPackages();
    
    // ดึงสถิติแพ็คเกจ
    $package_stats = $package->getBookingStats();
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event("Admin packages error: " . $e->getMessage(), 'ERROR');
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการแพ็คเกจ - Admin Dashboard</title>
    
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
        
        .package-card {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .package-card.active {
            border-color: #28a745;
            background: #f8fff9;
        }
        
        .package-card.inactive {
            border-color: #dc3545;
            background: #fff8f8;
            opacity: 0.7;
        }
        
        .package-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 1.5rem;
            border-radius: 13px 13px 0 0;
            text-align: center;
        }
        
        .package-price {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .equipment-list {
            list-style: none;
            padding: 0;
        }
        
        .equipment-list li {
            padding: 0.3rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .equipment-list li:last-child {
            border-bottom: none;
        }
        
        .equipment-list i {
            color: #28a745;
            margin-right: 8px;
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
                        <a class="nav-link active" href="packages.php">
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
                        <h2>จัดการแพ็คเกจ</h2>
                        <p class="text-muted mb-0">จัดการแพ็คเกจอุปกรณ์ Live Streaming</p>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#packageModal">
                        <i class="fas fa-plus me-2"></i>เพิ่มแพ็คเกจใหม่
                    </button>
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

                <!-- Package Statistics -->
                <div class="row g-4 mb-4">
                    <?php if (!empty($package_stats)): ?>
                        <?php foreach ($package_stats as $stat): ?>
                            <div class="col-lg-3 col-md-6">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h5><?php echo htmlspecialchars($stat['name']); ?></h5>
                                        <div class="h4 text-primary"><?php echo $stat['total_bookings'] ?? 0; ?> การจอง</div>
                                        <div class="text-success"><?php echo format_currency($stat['total_revenue'] ?? 0); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center text-muted">
                                    <i class="fas fa-chart-bar fa-3x mb-3"></i>
                                    <h5>ยังไม่มีข้อมูลสถิติ</h5>
                                    <p>สถิติจะแสดงเมื่อมีการจองแพ็คเกจ</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Packages Grid -->
                <div class="row g-4">
                    <?php foreach ($packages as $pkg): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="package-card <?php echo $pkg['is_active'] ? 'active' : 'inactive'; ?>">
                                <div class="package-header">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($pkg['name']); ?></h5>
                                    <div class="package-price"><?php echo format_currency($pkg['price']); ?></div>
                                    <small>ต่อวัน</small>
                                </div>
                                <div class="p-3">
                                    <p class="text-muted mb-3"><?php echo htmlspecialchars($pkg['description']); ?></p>
                                    
                                    <h6>รายการอุปกรณ์:</h6>
                                    <ul class="equipment-list">
                                        <?php foreach ($pkg['equipment_list'] as $equipment): ?>
                                            <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($equipment); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    
                                    <div class="mt-3">
                                        <span class="badge <?php echo $pkg['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $pkg['is_active'] ? 'เปิดใช้งาน' : 'ปิดใช้งาน'; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mt-3 d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary flex-fill" 
                                                onclick="editPackage(<?php echo htmlspecialchars(json_encode($pkg)); ?>)">
                                            <i class="fas fa-edit me-1"></i>แก้ไข
                                        </button>
                                        
                                        <?php if ($pkg['is_active']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="deactivatePackage(<?php echo $pkg['id']; ?>)">
                                                <i class="fas fa-times me-1"></i>ปิดใช้งาน
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Package Modal -->
    <div class="modal fade" id="packageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="packageModalTitle">เพิ่มแพ็คเกจใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="packageForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="package_id" id="packageId">
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="packageName" class="form-label">ชื่อแพ็คเกจ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="packageName" name="name" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="packagePrice" class="form-label">ราคา (บาท) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="packagePrice" name="price" min="0" step="0.01" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="packageDescription" class="form-label">คำอธิบาย</label>
                            <textarea class="form-control" id="packageDescription" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="equipmentList" class="form-label">รายการอุปกรณ์ <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="equipmentList" name="equipment_list" rows="5" 
                                      placeholder="กรอกรายการอุปกรณ์ (แต่ละรายการขึ้นบรรทัดใหม่)" required></textarea>
                            <small class="text-muted">แต่ละรายการขึ้นบรรทัดใหม่</small>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="isActive" name="is_active" checked>
                            <label class="form-check-label" for="isActive">
                                เปิดใช้งานแพ็คเกจ
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="save_package" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>บันทึก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="package_id" id="deletePackageId">
        <input type="hidden" name="delete_package" value="1">
    </form>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function editPackage(packageData) {
            document.getElementById('packageModalTitle').textContent = 'แก้ไขแพ็คเกจ';
            document.getElementById('packageId').value = packageData.id;
            document.getElementById('packageName').value = packageData.name;
            document.getElementById('packagePrice').value = packageData.price;
            document.getElementById('packageDescription').value = packageData.description || '';
            document.getElementById('equipmentList').value = packageData.equipment_list.join('\n');
            document.getElementById('isActive').checked = packageData.is_active == 1;
            
            new bootstrap.Modal(document.getElementById('packageModal')).show();
        }
        
        function deactivatePackage(packageId) {
            if (confirm('คุณต้องการปิดใช้งานแพ็คเกจนี้หรือไม่?')) {
                document.getElementById('deletePackageId').value = packageId;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Reset form when modal is closed
        document.getElementById('packageModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('packageModalTitle').textContent = 'เพิ่มแพ็คเกจใหม่';
            document.getElementById('packageForm').reset();
            document.getElementById('packageId').value = '';
        });
        
        // Form validation
        document.getElementById('packageForm').addEventListener('submit', function(e) {
            const name = document.getElementById('packageName').value.trim();
            const price = document.getElementById('packagePrice').value;
            const equipmentList = document.getElementById('equipmentList').value.trim();
            
            if (!name || !price || !equipmentList) {
                e.preventDefault();
                alert('กรุณากรอกข้อมูลให้ครบถ้วน');
            }
        });
    </script>
</body>
</html>