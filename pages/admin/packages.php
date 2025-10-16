<?php
/**
 * Admin Packages Management
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_layout.php';
require_once '../../models/Package.php';
require_once '../../models/PackageItem.php';

require_admin();

$error_message = '';
$success_message = '';

if (!function_exists('remove_package_item_image')) {
    function remove_package_item_image($relativePath): void
    {
        if (empty($relativePath)) {
            return;
        }
        $projectRoot = dirname(__DIR__, 2);
        $uploadsRoot = realpath($projectRoot . '/uploads/package-items');
        $target = realpath($projectRoot . '/' . ltrim($relativePath, '/'));
        if ($uploadsRoot && $target && str_starts_with($target, $uploadsRoot) && file_exists($target)) {
            @unlink($target);
        }
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $package = new Package($db);
    $packageItem = new PackageItem($db);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_package'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $error_message = 'Invalid CSRF token';
        } else {
            $equipment_lines = array_filter(array_map('trim', explode("\n", $_POST['equipment_list'] ?? '')));
            $package_data = [
                'name' => sanitize_input($_POST['name'] ?? ''),
                'description' => sanitize_input($_POST['description'] ?? ''),
                'price' => (float)($_POST['price'] ?? 0),
                'equipment_list' => $equipment_lines,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];

            $validation_errors = $package->validate($package_data);
            if (!empty($validation_errors)) {
                $error_message = implode('<br>', $validation_errors);
            } else {
                $package->name = $package_data['name'];
                $package->description = $package_data['description'];
                $package->price = $package_data['price'];
                $package->equipment_list = $package_data['equipment_list'];
                $package->is_active = $package_data['is_active'];

                $package_id = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
                if ($package_id > 0) {
                    $package->id = $package_id;
                    if ($package->update()) {
                        $success_message = 'อัปเดตแพ็คเกจสำเร็จ';
                        log_event("Admin updated package {$package_id}", 'INFO');
                    } else {
                        $error_message = 'เกิดข้อผิดพลาดในการอัปเดตแพ็คเกจ';
                    }
                } else {
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_package'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $error_message = 'Invalid CSRF token';
        } else {
            $package_id = (int)($_POST['package_id'] ?? 0);
            $package->id = $package_id;
            if ($package->delete()) {
                $success_message = 'ปิดใช้งานแพ็คเกจสำเร็จ';
                log_event("Admin deactivated package {$package_id}", 'INFO');
            } else {
                $error_message = 'เกิดข้อผิดพลาดในการปิดใช้งานแพ็คเกจ';
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $error_message = 'Invalid CSRF token';
        } else {
            $package_id = (int)($_POST['package_id'] ?? 0);
            $item_data = [
                'package_id' => $package_id,
                'name' => sanitize_input($_POST['item_name'] ?? ''),
                'quantity' => sanitize_input($_POST['item_quantity'] ?? ''),
                'specs' => sanitize_input($_POST['item_specs'] ?? ''),
                'notes' => sanitize_input($_POST['item_notes'] ?? ''),
                'image_path' => null,
                'image_alt' => sanitize_input($_POST['item_image_alt'] ?? ''),
            ];

            $validation = $packageItem->validate(['package_id' => $package_id, 'name' => $item_data['name']]);
            if (!empty($validation)) {
                $error_message = implode('<br>', $validation);
            } else {
                $uploadResult = $packageItem->handleImageUpload($_FILES['item_image'] ?? null);
                if (!$uploadResult['success']) {
                    $error_message = $uploadResult['message'];
                } else {
                    if (!empty($uploadResult['path'])) {
                        $item_data['image_path'] = $uploadResult['path'];
                    }
                    $newId = $packageItem->create($item_data);
                    if ($newId) {
                        $success_message = 'เพิ่มอุปกรณ์ในแพ็คเกจสำเร็จ';
                        log_event("Admin added package item {$newId} to package {$package_id}", 'INFO');
                    } else {
                        $error_message = 'ไม่สามารถเพิ่มอุปกรณ์ได้';
                    }
                }
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $error_message = 'Invalid CSRF token';
        } else {
            $item_id = (int)($_POST['item_id'] ?? 0);
            $package_id = (int)($_POST['package_id'] ?? 0);
            $existing = $packageItem->getById($item_id);

            if (!$existing || (int)$existing['package_id'] !== $package_id) {
                $error_message = 'ไม่พบรายการอุปกรณ์ที่ต้องการแก้ไข';
            } else {
                $item_data = [
                    'name' => sanitize_input($_POST['item_name'] ?? ''),
                    'quantity' => sanitize_input($_POST['item_quantity'] ?? ''),
                    'specs' => sanitize_input($_POST['item_specs'] ?? ''),
                    'notes' => sanitize_input($_POST['item_notes'] ?? ''),
                    'image_path' => $existing['image_path'],
                    'image_alt' => sanitize_input($_POST['item_image_alt'] ?? ''),
                ];

                $validation = $packageItem->validate(['package_id' => $package_id, 'name' => $item_data['name']]);
                if (!empty($validation)) {
                    $error_message = implode('<br>', $validation);
                } else {
                    $uploadResult = $packageItem->handleImageUpload($_FILES['item_image'] ?? null);
                    if (!$uploadResult['success']) {
                        $error_message = $uploadResult['message'];
                    } else {
                        if (!empty($uploadResult['path'])) {
                            remove_package_item_image($existing['image_path']);
                            $item_data['image_path'] = $uploadResult['path'];
                        }

                        if ($packageItem->update($item_id, $item_data)) {
                            $success_message = 'อัปเดตรายการอุปกรณ์สำเร็จ';
                            log_event("Admin updated package item {$item_id}", 'INFO');
                        } else {
                            $error_message = 'ไม่สามารถอัปเดตรายการอุปกรณ์ได้';
                        }
                    }
                }
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $error_message = 'Invalid CSRF token';
        } else {
            $item_id = (int)($_POST['item_id'] ?? 0);
            $existing = $packageItem->getById($item_id);
            if (!$existing) {
                $error_message = 'ไม่พบรายการอุปกรณ์';
            } else {
                if ($packageItem->delete($item_id)) {
                    remove_package_item_image($existing['image_path']);
                    $success_message = 'ลบอุปกรณ์ออกจากแพ็คเกจสำเร็จ';
                    log_event("Admin deleted package item {$item_id}", 'INFO');
                } else {
                    $error_message = 'ไม่สามารถลบอุปกรณ์ได้';
                }
            }
        }
    }

    $packages = $package->getAllPackages();
    $package_stats = $package->getBookingStats();

    $editing_package = null;
    if (isset($_GET['edit'])) {
        $edit_id = (int)$_GET['edit'];
        if ($edit_id > 0 && $package->getById($edit_id)) {
            $editing_package = [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'price' => $package->price,
                'equipment_list' => implode("\n", $package->equipment_list ?? []),
                'is_active' => (int)$package->is_active,
            ];
        }
    }
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event('Admin packages error: ' . $e->getMessage(), 'ERROR');
}

render_admin_page_start('จัดการแพ็คเกจ - ' . SITE_NAME, [
    'active' => 'packages',
]);
?>

<section class="admin-toolbar">
    <div>
        <h1 style="margin:0;">จัดการแพ็คเกจ</h1>
        <p style="margin:6px 0 0; color:var(--brand-muted);">เพิ่ม ปรับปรุง และจัดการรายการอุปกรณ์สำหรับงาน Live Streaming</p>
    </div>
    <?php if ($editing_package) : ?>
        <a class="btn btn-ghost" href="/pages/admin/packages.php">ยกเลิกการแก้ไข</a>
    <?php endif; ?>
</section>

<?php if ($error_message !== '') : ?>
    <div class="alert alert-danger" role="alert"><?= $error_message; ?></div>
<?php endif; ?>
<?php if ($success_message !== '') : ?>
    <div class="alert alert-success" role="alert"><?= $success_message; ?></div>
<?php endif; ?>

<div class="booking-panel">
    <h3 style="margin-top:0;"><?= $editing_package ? 'แก้ไขแพ็คเกจ' : 'เพิ่มแพ็คเกจใหม่'; ?></h3>
    <form method="POST" class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));" novalidate>
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
        <input type="hidden" name="save_package" value="1">
        <?php if ($editing_package) : ?>
            <input type="hidden" name="package_id" value="<?= (int)$editing_package['id']; ?>">
        <?php endif; ?>

        <div>
            <label for="package_name">ชื่อแพ็คเกจ *</label>
            <input type="text" class="input" id="package_name" name="name" value="<?= htmlspecialchars($editing_package['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        <div>
            <label for="package_price">ราคา (บาท) *</label>
            <input type="number" class="input" id="package_price" name="price" min="0" step="0.01" value="<?= htmlspecialchars((string)($editing_package['price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        <div style="grid-column:1 / -1;">
            <label for="package_description">คำอธิบาย</label>
            <textarea class="input" id="package_description" name="description" rows="3" placeholder="รายละเอียดแพ็คเกจ"><?= htmlspecialchars($editing_package['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div style="grid-column:1 / -1;">
            <label for="equipment_list">รายการอุปกรณ์ (ขึ้นบรรทัดใหม่ทุกรายการ) *</label>
            <textarea class="input" id="equipment_list" name="equipment_list" rows="4" required><?= htmlspecialchars($editing_package['equipment_list'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div style="align-self:center;">
            <label style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="is_active" <?= !isset($editing_package) || ($editing_package['is_active'] ?? 0) ? 'checked' : ''; ?>>
                เปิดใช้งานแพ็คเกจทันที
            </label>
        </div>
        <div style="grid-column:1 / -1;">
            <button type="submit" class="btn btn-primary" style="width:100%;">
                <?= $editing_package ? 'บันทึกการแก้ไข' : 'เพิ่มแพ็คเกจ'; ?>
            </button>
        </div>
    </form>
</div>

<?php if (!empty($package_stats)) : ?>
    <div class="dashboard__grid" style="margin-top:24px;">
        <?php foreach ($package_stats as $stat) : ?>
            <div class="metric-card">
                <h3><?= htmlspecialchars($stat['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <strong><?= number_format($stat['total_bookings'] ?? 0); ?> การจอง</strong>
                <p style="margin:4px 0 0; color:var(--brand-muted);">รายได้รวม <?= format_currency($stat['total_revenue'] ?? 0); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="dashboard" style="margin-top:32px;">
    <?php foreach ($packages as $pkg) : ?>
        <div class="booking-panel">
            <div class="admin-toolbar">
                <div>
                    <h3 style="margin:0;"><?= htmlspecialchars($pkg['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p style="margin:4px 0 0; color:var(--brand-muted);">ราคา <?= format_currency($pkg['price']); ?> ต่อวัน</p>
                </div>
                <div style="display:flex; gap:12px;">
                    <a class="btn btn-ghost" href="/pages/admin/packages.php?edit=<?= (int)$pkg['id']; ?>"><i class="fa-solid fa-pen"></i>&nbsp;แก้ไข</a>
                    <?php if ((int)$pkg['is_active'] === 1) : ?>
                        <form method="POST" onsubmit="return confirm('ยืนยันการปิดใช้งานแพ็คเกจนี้หรือไม่?');">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                            <input type="hidden" name="package_id" value="<?= (int)$pkg['id']; ?>">
                            <input type="hidden" name="delete_package" value="1">
                            <button type="submit" class="btn btn-ghost" style="color:#b61c2c; border-color:rgba(220,53,69,0.4);">ปิดใช้งาน</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-card" style="margin-top:16px;">
                <strong>รายละเอียด</strong>
                <span><?= htmlspecialchars($pkg['description'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                <span>สถานะ: <?= (int)$pkg['is_active'] === 1 ? '<span class="badge badge--confirmed">เปิดใช้งาน</span>' : '<span class="badge badge--cancelled">ปิดใช้งาน</span>'; ?></span>
                <?php if (!empty($pkg['equipment_list'])) : ?>
                    <span style="margin-top:8px;">รายการอุปกรณ์พื้นฐาน:</span>
                    <ul style="margin:0; padding-left:18px; color:var(--brand-muted);">
                        <?php foreach ($pkg['equipment_list'] as $item) : ?>
                            <li><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <h4 style="margin-top:24px;">รายการอุปกรณ์เสริม</h4>
            <?php if (empty($pkg['items'])) : ?>
                <div class="empty-state">
                    <i class="fa-solid fa-cube"></i>
                    <p style="margin-top:12px;">ยังไม่มีอุปกรณ์เสริม</p>
                </div>
            <?php else : ?>
                <div class="stat-stack" style="margin-top:16px;">
                    <?php foreach ($pkg['items'] as $item) : ?>
                        <details class="info-card" style="margin-top:0;">
                            <summary style="font-weight:600; cursor:pointer; display:flex; justify-content:space-between; align-items:center; gap:12px;">
                                <span><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?><?= $item['quantity'] ? ' • ' . htmlspecialchars($item['quantity'], ENT_QUOTES, 'UTF-8') : ''; ?></span>
                                <span style="color:var(--brand-muted); font-size:0.85rem;">คลิกเพื่อแก้ไข</span>
                            </summary>
                            <div style="margin-top:16px;">
                                <?php if (!empty($item['image_path'])) : ?>
                                    <img src="/<?= ltrim($item['image_path'], '/'); ?>" alt="<?= htmlspecialchars($item['image_alt'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" style="max-width:100%; border-radius: var(--brand-radius-sm); box-shadow: var(--brand-shadow-sm); margin-bottom:12px;">
                                <?php endif; ?>
                                <form method="POST" enctype="multipart/form-data" class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                                    <input type="hidden" name="update_item" value="1">
                                    <input type="hidden" name="item_id" value="<?= (int)$item['id']; ?>">
                                    <input type="hidden" name="package_id" value="<?= (int)$pkg['id']; ?>">

                                    <div>
                                        <label>ชื่ออุปกรณ์ *</label>
                                        <input type="text" class="input" name="item_name" value="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                    <div>
                                        <label>จำนวน</label>
                                        <input type="text" class="input" name="item_quantity" value="<?= htmlspecialchars($item['quantity'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div>
                                        <label>Alt Text รูป</label>
                                        <input type="text" class="input" name="item_image_alt" value="<?= htmlspecialchars($item['image_alt'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div>
                                        <label>สเปก</label>
                                        <input type="text" class="input" name="item_specs" value="<?= htmlspecialchars($item['specs'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div>
                                        <label>หมายเหตุ</label>
                                        <input type="text" class="input" name="item_notes" value="<?= htmlspecialchars($item['notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div style="grid-column:1 / -1;">
                                        <label>อัปโหลดรูปใหม่ (ถ้ามี)</label>
                                        <input type="file" class="input" name="item_image" accept="image/jpeg,image/png">
                                    </div>
                                    <div style="grid-column:1 / -1;">
                                        <button type="submit" class="btn btn-primary">บันทึก</button>
                                    </div>
                                </form>
                                <form method="POST" style="margin-top:12px;" onsubmit="return confirm('ยืนยันการลบอุปกรณ์นี้หรือไม่?');">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                                    <input type="hidden" name="delete_item" value="1">
                                    <input type="hidden" name="item_id" value="<?= (int)$item['id']; ?>">
                                    <button type="submit" class="btn btn-ghost" style="color:#b61c2c; border-color:rgba(220,53,69,0.4);">ลบอุปกรณ์นี้</button>
                                </form>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <details class="info-card" style="margin-top:24px;">
                <summary style="font-weight:600; cursor:pointer;">เพิ่มอุปกรณ์ใหม่ในแพ็คเกจนี้</summary>
                <form method="POST" enctype="multipart/form-data" class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-top:16px;">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                    <input type="hidden" name="add_item" value="1">
                    <input type="hidden" name="package_id" value="<?= (int)$pkg['id']; ?>">

                    <div>
                        <label>ชื่ออุปกรณ์ *</label>
                        <input type="text" class="input" name="item_name" required>
                    </div>
                    <div>
                        <label>จำนวน</label>
                        <input type="text" class="input" name="item_quantity">
                    </div>
                    <div>
                        <label>Alt Text รูป</label>
                        <input type="text" class="input" name="item_image_alt">
                    </div>
                    <div>
                        <label>สเปก</label>
                        <input type="text" class="input" name="item_specs">
                    </div>
                    <div>
                        <label>หมายเหตุ</label>
                        <input type="text" class="input" name="item_notes">
                    </div>
                    <div style="grid-column:1 / -1;">
                        <label>อัปโหลดรูป (JPG/PNG สูงสุด 5MB)</label>
                        <input type="file" class="input" name="item_image" accept="image/jpeg,image/png">
                    </div>
                    <div style="grid-column:1 / -1;">
                        <button type="submit" class="btn btn-primary" style="width:100%;">เพิ่มอุปกรณ์</button>
                    </div>
                </form>
            </details>
        </div>
    <?php endforeach; ?>
</div>

<?php
render_admin_page_end();
