#!/usr/bin/env php
<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Payment.php';

function assert_true(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cleanup_uploaded_slip(?string $relativePath): void {
    if (!$relativePath) {
        return;
    }
    $fullPath = dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

$database = new Database();
$db = $database->getConnection();
$db->beginTransaction();

$createdUserId = null;
$generatedSlipPath = null;

try {
    // --- Login smoke test -------------------------------------------------
    $testUsername = 'qa_login_smoke_' . bin2hex(random_bytes(4));
    $testPassword = 'P@ssw0rd!';

    $user = new User($db);
    $user->username = $testUsername;
    $user->password = $testPassword;
    $user->email = $testUsername . '@example.com';
    $user->phone = '0123456789';
    $user->first_name = 'QA';
    $user->last_name = 'Smoke';
    $user->role = 'customer';

    assert_true($user->create(), 'ไม่สามารถสร้างผู้ใช้สำหรับการทดสอบได้');
    $createdUserId = $user->id;

    $loginUser = new User($db);
    assert_true($loginUser->login($testUsername, $testPassword), 'ฟังก์ชัน login ล้มเหลวสำหรับ credentials ที่ถูกต้อง');
    assert_true($loginUser->role === 'customer', 'บทบาทของผู้ใช้หลัง login ไม่ถูกต้อง');

    $failedLoginUser = new User($db);
    assert_true(!$failedLoginUser->login($testUsername, 'wrong-password'), 'login ควรล้มเหลวเมื่อรหัสผ่านไม่ถูกต้อง');

    // --- Payment upload validation smoke test ----------------------------
    $payment = new Payment($db);

    $tmpPdf = tempnam(sys_get_temp_dir(), 'slip_pdf');
    file_put_contents($tmpPdf, "%PDF-1.4\n%");
    $uploadResult = $payment->uploadSlip([
        'name' => 'test.pdf',
        'tmp_name' => $tmpPdf,
        'size' => filesize($tmpPdf),
        'error' => UPLOAD_ERR_OK,
    ]);
    assert_true($uploadResult['success'] ?? false, 'การอัปโหลดไฟล์ PDF ควรสำเร็จ');
    $generatedSlipPath = $payment->slip_image_url ?? null;
    assert_true($generatedSlipPath !== null, 'คาดหวังให้มีเส้นทางไฟล์ slip หลังอัปโหลด');
    $fullSlipPath = dirname(__DIR__, 2) . '/' . $generatedSlipPath;
    assert_true(is_file($fullSlipPath), 'ไฟล์ slip ที่อัปโหลดไม่พบในระบบไฟล์');

    $tmpTxt = tempnam(sys_get_temp_dir(), 'slip_txt');
    file_put_contents($tmpTxt, 'not allowed');
    $invalidUpload = $payment->uploadSlip([
        'name' => 'bad.txt',
        'tmp_name' => $tmpTxt,
        'size' => filesize($tmpTxt),
        'error' => UPLOAD_ERR_OK,
    ]);
    assert_true(!($invalidUpload['success'] ?? false), 'การอัปโหลดไฟล์นามสกุล .txt ควรล้มเหลว');

    // --- สรุปผล -----------------------------------------------------------
    $db->rollBack();
    cleanup_uploaded_slip($generatedSlipPath);

    echo "[PASS] login_payment_smoke\n";
    exit(0);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    cleanup_uploaded_slip($generatedSlipPath);
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
