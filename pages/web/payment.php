<?php
/**
 * หน้าชำระเงิน
 * ระบบจองอุปกรณ์ Live Streaming
 */

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/includes/config.php';
require_once $rootPath . '/includes/functions.php';
require_once $rootPath . '/models/Booking.php';
require_once $rootPath . '/models/Payment.php';

require_login();

$error_message = '';
$success_message = '';
$booking_code = isset($_GET['booking']) ? sanitize_input($_GET['booking']) : '';

if ($booking_code === '') {
    redirect('/pages/web/profile.php');
}

try {
    $db = get_db_connection();
    $booking = new Booking($db);
    $payment = new Payment($db);

    $booking_data = $booking->getByBookingCode($booking_code);

    if (!$booking_data || (int)$booking_data['user_id'] !== (int)($_SESSION['user_id'] ?? 0)) {
        $error_message = 'ไม่พบข้อมูลการจองหรือคุณไม่มีสิทธิ์เข้าถึง';
        $booking_data = null;
    } else {
        $existing_payment = $payment->getByBookingId($booking_data['id']);
    }
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event('Payment page error: ' . $e->getMessage(), 'ERROR');
}

if ($booking_data && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_slip'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid CSRF token';
    } else {
        try {
            if (!isset($_FILES['slip_image']) || $_FILES['slip_image']['error'] === UPLOAD_ERR_NO_FILE) {
                $error_message = 'กรุณาเลือกไฟล์สลิปการโอนเงิน';
            } else {
                $deposit_amount = $booking_data['total_price'] * 0.5;

                if ($existing_payment) {
                    $payment->id = $existing_payment['id'];
                } else {
                    $payment->booking_id = $booking_data['id'];
                    $payment->amount = $deposit_amount;
                    $payment->payment_method = 'bank_transfer';
                    $payment->status = 'pending';
                    $payment->paid_at = date('Y-m-d H:i:s');
                    $payment->transaction_ref = sanitize_input($_POST['transaction_ref'] ?? '');
                    $payment->notes = sanitize_input($_POST['notes'] ?? '');
                }

                $upload_result = $payment->uploadSlip(
                    $_FILES['slip_image'],
                    $existing_payment['slip_image_url'] ?? null
                );

                if ($upload_result['success']) {
                    if ($existing_payment) {
                        $query = 'UPDATE payments SET slip_image_url = :slip_image_url, transaction_ref = :transaction_ref, notes = :notes, status = "pending", paid_at = :paid_at WHERE id = :id';
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':slip_image_url', $payment->slip_image_url);
                        $sanitized_ref = sanitize_input($_POST['transaction_ref'] ?? '');
                        $sanitized_notes = sanitize_input($_POST['notes'] ?? '');
                        $stmt->bindParam(':transaction_ref', $sanitized_ref);
                        $stmt->bindParam(':notes', $sanitized_notes);
                        $stmt->bindParam(':paid_at', date('Y-m-d H:i:s'));
                        $stmt->bindParam(':id', $payment->id, PDO::PARAM_INT);
                        $stmt->execute();
                    } else {
                        $payment->create();
                    }

                    $success_message = 'อัปโหลดสลิปสำเร็จ! รอการตรวจสอบจากเจ้าหน้าที่';
                    log_event("Payment slip uploaded for booking {$booking_code}", 'INFO');
                    $existing_payment = $payment->getByBookingId($booking_data['id']);
                } else {
                    $error_message = $upload_result['message'];
                }
            }
        } catch (Exception $e) {
            $error_message = 'เกิดข้อผิดพลาดในการอัปโหลดสลิป';
            log_event('Payment upload error: ' . $e->getMessage(), 'ERROR');
        }
    }
}

$deposit_amount = 0;
$remaining_amount = 0;
if ($booking_data) {
    $deposit_amount = $booking_data['total_price'] * 0.5;
    $remaining_amount = $booking_data['total_price'] - $deposit_amount;
}

require_once $rootPath . '/includes/layout.php';

render_page_start('ชำระเงิน - ' . SITE_NAME, [
    'active' => 'profile',
]);
?>

<section class="section">
    <div class="page-container">
        <div class="section-header">
            <div class="hero__eyebrow">Secure Payment</div>
            <h2>ยืนยันการชำระเงินมัดจำ</h2>
            <p>อัปโหลดสลิปการโอนเงินเพื่อให้ทีมงานตรวจสอบและยืนยันการจองของคุณ</p>
        </div>

        <div class="stepper">
            <div class="stepper__item stepper__item--done"><span class="stepper__number"><i class="fa-solid fa-check"></i></span> เลือกแพ็คเกจ</div>
            <div class="stepper__item stepper__item--done"><span class="stepper__number"><i class="fa-solid fa-check"></i></span> กรอกข้อมูล</div>
            <div class="stepper__item stepper__item--active"><span class="stepper__number">3</span> ชำระเงิน</div>
        </div>

        <?php if ($error_message !== '') : ?>
            <div class="alert alert-danger" role="alert"><?= $error_message; ?></div>
        <?php endif; ?>

        <?php if ($success_message !== '') : ?>
            <div class="alert alert-success" role="alert"><?= $success_message; ?></div>
        <?php endif; ?>

        <?php if ($booking_data) : ?>
            <div class="booking-shell">
                <div class="booking-panel">
                    <div class="booking-panel__header">
                        <div>
                            <h3 style="margin:0;">รายละเอียดการชำระเงิน</h3>
                            <p style="margin:6px 0 0; color:var(--brand-muted);">ตรวจสอบข้อมูลและอัปโหลดหลักฐานการโอน</p>
                        </div>
                        <span class="badge">ขั้นตอน 3 / 3</span>
                    </div>

                    <div class="info-card" style="margin-top:0;">
                        <strong>สรุปการจอง</strong>
                        <span>รหัสการจอง: <?= htmlspecialchars($booking_data['booking_code'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span>แพ็คเกจ: <?= htmlspecialchars($booking_data['package_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span>ระยะเวลา: <?= format_thai_date($booking_data['pickup_date']); ?> - <?= format_thai_date($booking_data['return_date']); ?> (<?= (int)$booking_data['rental_days']; ?> วัน)</span>
                        <span>เวลารับ/คืน: <?= htmlspecialchars(substr($booking_data['pickup_time'] ?? BOOKING_DEFAULT_PICKUP_TIME, 0, 5), ENT_QUOTES, 'UTF-8'); ?> - <?= htmlspecialchars(substr($booking_data['return_time'] ?? BOOKING_DEFAULT_RETURN_TIME, 0, 5), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span>ราคารวม: <?= format_currency($booking_data['total_price']); ?></span>
                        <span>มัดจำ (50%): <strong><?= format_currency($deposit_amount); ?></strong></span>
                        <span>ชำระเมื่อรับอุปกรณ์: <?= format_currency($remaining_amount); ?></span>
                    </div>

                    <div class="info-card">
                        <strong>ข้อมูลบัญชีสำหรับโอนเงิน</strong>
                        <span>ธนาคาร: กสิกรไทย</span>
                        <span>เลขที่บัญชี: 123-4-56789-0</span>
                        <span>ชื่อบัญชี: บริษัท Live Streaming Pro จำกัด</span>
                        <span>ประเภทบัญชี: ออมทรัพย์</span>
                        <span>จำนวนเงินที่ต้องโอน: <strong><?= format_currency($deposit_amount); ?></strong></span>
                    </div>

                    <?php if (!empty($existing_payment)) : ?>
                        <?php
                        $status_class = 'status-pill--pending';
                        $status_text = 'รอการตรวจสอบ';
                        if ($existing_payment['status'] === 'verified') {
                            $status_class = 'status-pill--verified';
                            $status_text = 'ยืนยันแล้ว';
                        } elseif ($existing_payment['status'] === 'rejected') {
                            $status_class = 'status-pill--rejected';
                            $status_text = 'ถูกปฏิเสธ';
                        }
                        ?>
                        <div class="info-card">
                            <strong>สถานะการชำระเงิน</strong>
                            <span class="status-pill <?= $status_class; ?>"><?= $status_text; ?></span>
                            <?php if ($existing_payment['status'] === 'verified') : ?>
                                <span><i class="fa-solid fa-circle-check" style="color:#16a34a;"></i> การจองได้รับการยืนยันเรียบร้อย</span>
                            <?php elseif ($existing_payment['status'] === 'rejected') : ?>
                                <span><i class="fa-solid fa-circle-xmark" style="color:#dc2626;"></i> กรุณาอัปโหลดสลิปใหม่</span>
                            <?php endif; ?>

                            <?php if (!empty($existing_payment['slip_image_url'])) : ?>
                                <div style="margin-top:16px;">
                                    <strong>สลิปที่อัปโหลด</strong>
                                    <img src="../<?= htmlspecialchars($existing_payment['slip_image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Payment Slip" style="margin-top:8px; max-width:100%; border-radius: var(--brand-radius-sm); box-shadow: var(--brand-shadow-sm);">
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($existing_payment['notes'])) : ?>
                                <span>หมายเหตุเจ้าหน้าที่: <?= htmlspecialchars($existing_payment['notes'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$existing_payment || $existing_payment['status'] === 'rejected') : ?>
                        <form method="POST" action="" enctype="multipart/form-data" id="paymentForm" class="form-grid" style="margin-top:24px;">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">

                            <label style="font-weight:600;">อัปโหลดสลิปการโอนเงิน</label>
                            <div id="uploadArea" class="upload-dropzone">
                                <i class="fa-solid fa-cloud-arrow-up" style="font-size:2rem;"></i>
                                <div>
                                    <strong>ลากไฟล์มาวาง หรือคลิกเพื่อเลือก</strong>
                                    <p style="margin:4px 0 0; color:var(--brand-muted); font-size:0.9rem;">รองรับ JPG, PNG หรือ PDF ขนาดไม่เกิน 5MB</p>
                                </div>
                            </div>
                            <input type="file" id="slip_image" name="slip_image" accept="image/*,.pdf" style="display:none;">

                            <div class="file-preview" id="filePreview"></div>

                            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-top:24px; gap:18px;">
                                <div>
                                    <label for="transaction_ref">หมายเลขอ้างอิงธุรกรรม</label>
                                    <input type="text" class="input" id="transaction_ref" name="transaction_ref" placeholder="เช่น เลขอ้างอิงจากธนาคาร" value="<?= isset($_POST['transaction_ref']) ? htmlspecialchars($_POST['transaction_ref'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                </div>
                                <div>
                                    <label for="notes">หมายเหตุถึงเจ้าหน้าที่</label>
                                    <textarea class="input" id="notes" name="notes" rows="3" placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)"><?= isset($_POST['notes']) ? htmlspecialchars($_POST['notes'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                                </div>
                            </div>

                            <button type="submit" name="upload_slip" class="btn btn-primary" style="margin-top:24px; width:100%;">ส่งหลักฐานการชำระเงิน</button>
                        </form>
                    <?php endif; ?>
                </div>

                <aside class="booking-summary" style="position:static;">
                    <h3 style="margin-top:0;">คู่มือและการสนับสนุน</h3>
                    <div class="info-card" style="margin-top:16px;">
                        <strong>ขั้นตอนหลังจากนี้</strong>
                        <span>1. เจ้าหน้าที่ตรวจสอบสลิปภายใน 12 ชั่วโมง</span>
                        <span>2. ระบบจะส่งอีเมลแจ้งผลทันทีเมื่อยืนยัน</span>
                        <span>3. ชำระยอดที่เหลือในวันรับอุปกรณ์</span>
                    </div>

                    <div class="info-card">
                        <strong>เคล็ดลับการโอน</strong>
                        <span><i class="fa-solid fa-clock" style="color:var(--brand-primary);"></i> โอนภายใน 24 ชั่วโมงหลังจองเพื่อรักษาคิว</span>
                        <span><i class="fa-solid fa-file-circle-check" style="color:var(--brand-primary);"></i> ตรวจสอบให้เห็นเลขบัญชีและจำนวนเงินในสลิปชัดเจน</span>
                        <span><i class="fa-solid fa-phone" style="color:var(--brand-primary);"></i> ติดต่อเจ้าหน้าที่ทันทีหากพบปัญหาการโอน</span>
                    </div>

                    <div class="support-card">
                        <strong>ติดต่อทีมซัพพอร์ต</strong>
                        <span><i class="fa-solid fa-phone"></i> 02-123-4567</span>
                        <span><i class="fa-brands fa-line"></i> @livestreamingpro</span>
                        <span><i class="fa-solid fa-envelope"></i> support@livestreaming.com</span>
                    </div>
                </aside>
            </div>

            <div style="margin-top:32px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <a class="btn btn-ghost" href="/pages/web/profile.php"><i class="fa-solid fa-arrow-left"></i>&nbsp;กลับโปรไฟล์</a>
                <a class="btn btn-ghost" href="/pages/web/booking.php"><i class="fa-solid fa-plus"></i>&nbsp;จองใหม่</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('slip_image');
    const filePreview = document.getElementById('filePreview');
    const paymentForm = document.getElementById('paymentForm');

    if (uploadArea && fileInput && filePreview && paymentForm) {
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        uploadArea.addEventListener('dragover', event => {
            event.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', event => {
            event.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = event.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });

        fileInput.addEventListener('change', event => {
            if (event.target.files.length > 0) {
                handleFileSelect(event.target.files[0]);
            }
        });

        paymentForm.addEventListener('submit', event => {
            if (!fileInput.files.length) {
                event.preventDefault();
                alert('กรุณาเลือกไฟล์สลิปการโอนเงิน');
            }
        });
    }

    function handleFileSelect(file) {
        const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!allowedTypes.includes(file.type)) {
            alert('ประเภทไฟล์ไม่ถูกต้อง กรุณาเลือกไฟล์ JPG, PNG หรือ PDF');
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert('ไฟล์มีขนาดใหญ่เกินไป กรุณาเลือกไฟล์ที่มีขนาดไม่เกิน 5MB');
            return;
        }

        const icon = file.type.includes('pdf') ? 'fa-file-pdf' : 'fa-image';
        filePreview.innerHTML = `
            <div class="uploaded-file">
                <i class="fa-solid ${icon} uploaded-file__icon"></i>
                <div>
                    <div style="font-weight:600;">${file.name}</div>
                    <div style="font-size:0.85rem; color:var(--brand-muted);">${(file.size / 1024 / 1024).toFixed(2)} MB</div>
                </div>
                <button type="button" class="btn btn-ghost" style="margin-left:auto; padding:0.35rem 0.75rem;" onclick="clearSelectedFile()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        `;
        filePreview.style.display = 'block';
    }

    function clearSelectedFile() {
        const fileInput = document.getElementById('slip_image');
        const filePreview = document.getElementById('filePreview');
        if (fileInput) {
            fileInput.value = '';
        }
        if (filePreview) {
            filePreview.style.display = 'none';
            filePreview.innerHTML = '';
        }
    }
</script>

<?php
render_page_end();
