#!/usr/bin/env php
<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Package.php';
require_once __DIR__ . '/../../models/PackageItem.php';

function assert_true(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database = new Database();
$db = $database->getConnection();
$db->beginTransaction();

$createdPackageId = null;
$createdItemId1 = null;
$createdItemId2 = null;

try {
    // สร้างแพ็คเกจชั่วคราว
    $package = new Package($db);
    $package->name = 'QA Smoke Package';
    $package->description = 'Temporary package for smoke test';
    $package->price = 999.99;
    $package->equipment_list = ['cam', 'mic'];
    $package->image_url = null;
    $package->is_active = true;
    assert_true($package->create(), 'สร้าง package ใหม่ไม่สำเร็จ');
    $createdPackageId = $package->id;

    $itemModel = new PackageItem($db);

    // เพิ่มรายการอุปกรณ์ 1
    $createdItemId1 = $itemModel->create([
        'package_id' => $createdPackageId,
        'name' => 'Camera Body',
        'quantity' => '1 ชุด',
        'specs' => '4K recording',
        'notes' => 'With lens',
        'image_path' => null,
        'image_alt' => 'Camera Body',
    ]);
    assert_true($createdItemId1 !== false, 'เพิ่ม item ตัวแรกไม่สำเร็จ');

    // เพิ่มรายการอุปกรณ์ 2
    $createdItemId2 = $itemModel->create([
        'package_id' => $createdPackageId,
        'name' => 'Microphone',
        'quantity' => '2 ตัว',
        'specs' => 'Wireless',
        'notes' => null,
        'image_path' => null,
        'image_alt' => 'Microphone',
    ]);
    assert_true($createdItemId2 !== false, 'เพิ่ม item ตัวที่สองไม่สำเร็จ');

    // อัปเดตรายการ 2
    assert_true($itemModel->update($createdItemId2, [
        'name' => 'Wireless Microphone',
        'quantity' => '2 ตัว',
        'specs' => 'UHF Wireless',
        'notes' => 'Includes clips',
        'image_path' => null,
        'image_alt' => 'Wireless Microphone',
    ]), 'อัปเดต item ตัวที่สองไม่สำเร็จ');

    // ตรวจสอบข้อมูลด้วย Package model
    assert_true($package->getById($createdPackageId), 'ไม่พบแพ็คเกจที่สร้างไว้');
    $items = $package->getItemsForPackage($createdPackageId);
    assert_true(count($items) === 2, 'จำนวน item ในแพ็คเกจไม่ตรงตามที่คาดไว้');

    // ลองลบ item
    assert_true($itemModel->delete($createdItemId1), 'ลบ item ตัวแรกไม่สำเร็จ');
    $itemsAfterDelete = $package->getItemsForPackage($createdPackageId);
    assert_true(count($itemsAfterDelete) === 1, 'จำนวน item หลังลบไม่ถูกต้อง');

    $db->rollBack();

    echo "[PASS] admin_package_smoke\n";
    exit(0);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
