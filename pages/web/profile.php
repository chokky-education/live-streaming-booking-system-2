<?php
/**
 * หน้าโปรไฟล์ลูกค้า
 * ระบบจองอุปกรณ์ Live Streaming
 */

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/includes/config.php';
require_once $rootPath . '/includes/functions.php';
require_once $rootPath . '/models/User.php';
require_once $rootPath . '/models/Booking.php';

require_login();

$error_message = '';
$success_message = '';
$page_csrf_token = generate_csrf_token();
$user_bookings = [];
$modifiable_bookings = [];

try {
    $db = get_db_connection();
    $user = new User($db);
    $booking = new Booking($db);

    $user->getById($_SESSION['user_id']);
    $user_bookings = $booking->getUserBookings($_SESSION['user_id'], 10);

    $total_bookings = count($user_bookings);
    $pending_bookings = 0;
    $confirmed_bookings = 0;
    $next_booking = null;

    foreach ($user_bookings as $entry) {
        if ($entry['status'] === 'pending') {
            $pending_bookings++;
        }
        if ($entry['status'] === 'confirmed') {
            $confirmed_bookings++;
        }
        if ($next_booking === null) {
            $next_booking = $entry;
        }

        $modifiable_bookings[$entry['id']] = $booking->customerCanModify((int)$entry['id'], (int)$_SESSION['user_id']);
    }
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event('Profile page error: ' . $e->getMessage(), 'ERROR');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid CSRF token';
    } else {
        $user->email = sanitize_input($_POST['email'] ?? '');
        $user->phone = sanitize_input($_POST['phone'] ?? '');
        $user->first_name = sanitize_input($_POST['first_name'] ?? '');
        $user->last_name = sanitize_input($_POST['last_name'] ?? '');

        try {
            if ($user->update()) {
                $_SESSION['first_name'] = $user->first_name;
                $_SESSION['last_name'] = $user->last_name;
                $success_message = 'อัปเดตข้อมูลสำเร็จ';
                log_event("User {$user->username} updated profile", 'INFO');
            } else {
                $error_message = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล';
            }
        } catch (Exception $e) {
            $error_message = 'เกิดข้อผิดพลาดในระบบ';
            log_event('Profile update error: ' . $e->getMessage(), 'ERROR');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid CSRF token';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($new_password !== $confirm_password) {
            $error_message = 'รหัสผ่านใหม่และรหัสผ่านยืนยันไม่ตรงกัน';
        } elseif (strlen($new_password) < 6) {
            $error_message = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
        } else {
            try {
                if ($user->login($user->username, $current_password)) {
                    if ($user->changePassword($new_password)) {
                        $success_message = 'เปลี่ยนรหัสผ่านสำเร็จ';
                        log_event("User {$user->username} changed password", 'INFO');
                    } else {
                        $error_message = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน';
                    }
                } else {
                    $error_message = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
                }
            } catch (Exception $e) {
                $error_message = 'เกิดข้อผิดพลาดในระบบ';
                log_event('Password change error: ' . $e->getMessage(), 'ERROR');
            }
        }
    }
}

function getStatusBadge($status) {
    $map = [
        'pending' => ['label' => 'รอดำเนินการ', 'class' => 'badge badge--pending'],
        'confirmed' => ['label' => 'ยืนยันแล้ว', 'class' => 'badge badge--confirmed'],
        'cancelled' => ['label' => 'ยกเลิก', 'class' => 'badge badge--cancelled'],
        'completed' => ['label' => 'เสร็จสิ้น', 'class' => 'badge badge--completed'],
    ];

    $config = $map[$status] ?? ['label' => 'ไม่ทราบสถานะ', 'class' => 'badge badge--pending'];
    return '<span class="' . $config['class'] . '">' . $config['label'] . '</span>';
}

require_once $rootPath . '/includes/layout.php';

render_page_start('โปรไฟล์ - ' . SITE_NAME, [
    'active' => 'profile',
]);
?>

<section class="section">
    <div class="page-container">
        <div class="section-header">
            <div class="hero__eyebrow">Account Center</div>
            <h2>สรุปบัญชีและการจองของคุณ</h2>
            <p>จัดการข้อมูลส่วนตัว ตรวจสอบสถานะการจอง และติดตามความคืบหน้าทั้งหมดได้จากหน้านี้</p>
        </div>

        <?php if ($error_message !== '') : ?>
            <div class="alert alert-danger" role="alert"><?= $error_message; ?></div>
        <?php endif; ?>

        <?php if ($success_message !== '') : ?>
            <div class="alert alert-success" role="alert"><?= $success_message; ?></div>
        <?php endif; ?>

        <div id="bookingActionsData" data-csrf-token="<?= $page_csrf_token; ?>" hidden></div>

        <div class="dashboard__grid" style="margin-bottom:32px;">
            <div class="metric-card">
                <h3>การจองทั้งหมด</h3>
                <strong><?= $total_bookings; ?></strong>
                <p style="margin:8px 0 0; color:var(--brand-muted);">ย้อนหลัง 10 รายการล่าสุด</p>
            </div>
            <div class="metric-card">
                <h3>รอตรวจสอบ</h3>
                <strong><?= $pending_bookings; ?></strong>
                <p style="margin:8px 0 0; color:var(--brand-muted);">การจองที่ต้องชำระหรือยืนยัน</p>
            </div>
            <div class="metric-card">
                <h3>ยืนยันแล้ว</h3>
                <strong><?= $confirmed_bookings; ?></strong>
                <p style="margin:8px 0 0; color:var(--brand-muted);">พร้อมดำเนินการตามกำหนดการ</p>
            </div>
            <div class="metric-card">
                <h3>อีเวนท์ถัดไป</h3>
                <?php if ($next_booking) : ?>
                    <strong><?= format_thai_date($next_booking['pickup_date']); ?></strong>
                    <p style="margin:8px 0 0; color:var(--brand-muted);"><?= htmlspecialchars($next_booking['package_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php else : ?>
                    <strong>-</strong>
                    <p style="margin:8px 0 0; color:var(--brand-muted);">ยังไม่มีการจอง</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));">
            <div class="booking-panel">
                <h3 style="margin-top:0;">ข้อมูลส่วนตัว</h3>
                <p style="margin:6px 0 24px; color:var(--brand-muted);">อัปเดตข้อมูลการติดต่อเพื่อให้ทีมงานสามารถเชื่อมต่อได้อย่างรวดเร็ว</p>
                <form method="POST" class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $page_csrf_token; ?>">
                    <input type="hidden" name="update_profile" value="1">

                    <div>
                        <label for="first_name">ชื่อ</label>
                        <input type="text" class="input" id="first_name" name="first_name" value="<?= htmlspecialchars($user->first_name ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div>
                        <label for="last_name">นามสกุล</label>
                        <input type="text" class="input" id="last_name" name="last_name" value="<?= htmlspecialchars($user->last_name ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div>
                        <label for="email">Email</label>
                        <input type="email" class="input" id="email" name="email" value="<?= htmlspecialchars($user->email ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div>
                        <label for="phone">เบอร์ติดต่อ</label>
                        <input type="tel" class="input" id="phone" name="phone" value="<?= htmlspecialchars($user->phone ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div style="grid-column: 1 / -1; margin-top:12px;">
                        <button type="submit" class="btn btn-primary" style="width:100%;">บันทึกการเปลี่ยนแปลง</button>
                    </div>
                </form>
            </div>

            <div class="booking-panel">
                <h3 style="margin-top:0;">เปลี่ยนรหัสผ่าน</h3>
                <p style="margin:6px 0 24px; color:var(--brand-muted);">ตั้งรหัสผ่านใหม่เพื่อเพิ่มความปลอดภัยให้บัญชีของคุณ</p>
                <form method="POST" class="form-grid" style="grid-template-columns: 1fr;" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $page_csrf_token; ?>">
                    <input type="hidden" name="change_password" value="1">

                    <div>
                        <label for="current_password">รหัสผ่านปัจจุบัน</label>
                        <input type="password" class="input" id="current_password" name="current_password" required>
                    </div>

                    <div>
                        <label for="new_password">รหัสผ่านใหม่</label>
                        <input type="password" class="input" id="new_password" name="new_password" required>
                    </div>

                    <div>
                        <label for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" class="input" id="confirm_password" name="confirm_password" required>
                    </div>

                    <div style="margin-top:12px;">
                        <button type="submit" class="btn btn-primary" style="width:100%;">อัปเดตรหัสผ่าน</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="booking-history" class="table-shell" style="margin-top:48px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                <div>
                    <h3 style="margin:0;">ประวัติการจองล่าสุด</h3>
                    <p style="margin:4px 0 0; color:var(--brand-muted);">อัปเดตล่าสุด 10 รายการ</p>
                </div>
                <a class="btn btn-ghost" href="/pages/web/booking.php"><i class="fa-solid fa-plus"></i>&nbsp;จองอีกครั้ง</a>
            </div>

            <?php if (empty($user_bookings)) : ?>
                <div class="empty-state">
                    <i class="fa-solid fa-calendar-xmark" style="font-size:2rem;"></i>
                    <p style="margin-top:12px;">ยังไม่มีการจอง เริ่มต้นจองอุปกรณ์ของคุณเลย</p>
                    <a class="btn btn-primary" href="/pages/web/booking.php">จองตอนนี้</a>
                </div>
            <?php else : ?>
                <table>
                    <thead>
                        <tr>
                            <th>รหัสการจอง</th>
                            <th>แพ็คเกจ</th>
                            <th>ช่วงวันที่</th>
                            <th>สถานะ</th>
                            <th>ยอดรวม</th>
                            <th>การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_bookings as $item) :
                            $canModify = $modifiable_bookings[$item['id']] ?? ['allowed' => false, 'reason' => ''];
                            $pickupTime = substr($item['pickup_time'] ?? BOOKING_DEFAULT_PICKUP_TIME, 0, 5);
                            $returnTime = substr($item['return_time'] ?? BOOKING_DEFAULT_RETURN_TIME, 0, 5);
                        ?>
                            <tr
                                data-booking-id="<?= (int)$item['id']; ?>"
                                data-booking-code="<?= htmlspecialchars($item['booking_code'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-package-name="<?= htmlspecialchars($item['package_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-pickup-date="<?= htmlspecialchars($item['pickup_date'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-return-date="<?= htmlspecialchars($item['return_date'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-pickup-time="<?= htmlspecialchars($pickupTime, ENT_QUOTES, 'UTF-8'); ?>"
                                data-return-time="<?= htmlspecialchars($returnTime, ENT_QUOTES, 'UTF-8'); ?>"
                                data-location="<?= htmlspecialchars($item['location'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-notes="<?= htmlspecialchars($item['notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-total-price="<?= htmlspecialchars((string)$item['total_price'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-can-modify="<?= $canModify['allowed'] ? '1' : '0'; ?>"
                                data-deny-reason="<?= htmlspecialchars($canModify['reason'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <td><?= htmlspecialchars($item['booking_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars($item['package_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= format_thai_date($item['pickup_date']); ?> - <?= format_thai_date($item['return_date']); ?></td>
                                <td><?= getStatusBadge($item['status']); ?></td>
                                <td><?= format_currency($item['total_price']); ?></td>
                                <td>
                                    <div class="booking-actions__stack">
                                        <?php if ($item['status'] === 'pending') : ?>
                                            <a class="btn btn-primary" title="ชำระเงิน" href="/pages/web/payment.php?booking=<?= urlencode($item['booking_code']); ?>">
                                                <i class="fa-solid fa-credit-card"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($canModify['allowed'])) : ?>
                                            <button type="button" class="btn btn-ghost js-edit-booking" title="แก้ไขการจอง">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <button type="button" class="btn btn-ghost js-cancel-booking" title="ยกเลิกการจอง">
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                        <?php elseif (!empty($canModify['reason'])) : ?>
                                            <span class="badge badge--muted" title="<?= htmlspecialchars($canModify['reason'], ENT_QUOTES, 'UTF-8'); ?>">จำกัด</span>
                                        <?php endif; ?>

                                        <a class="btn btn-ghost" title="ดูทั้งหมด" href="#booking-history">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top:16px; text-align:center;">
                    <a class="btn btn-ghost" href="#booking-history"><i class="fa-solid fa-history"></i>&nbsp;ดูประวัติทั้งหมด</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<div id="bookingModalBackdrop" class="booking-modal__backdrop" hidden></div>

<div id="editBookingModal" class="booking-modal" hidden>
    <form id="editBookingForm" class="booking-modal__content" novalidate>
        <header class="booking-modal__header">
            <div>
                <div class="booking-modal__eyebrow">ปรับปรุงการจอง</div>
                <h3 id="editBookingTitle" class="booking-modal__title">แก้ไขการจอง</h3>
                <p id="editBookingSubtitle" class="booking-modal__subtitle"></p>
            </div>
            <button type="button" class="booking-modal__close" data-modal-close aria-label="ปิดหน้าต่าง">&times;</button>
        </header>

        <div class="booking-modal__body">
            <input type="hidden" name="booking_id" id="editBookingId">

            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="form-group">
                    <label for="editPickupDate">วันรับอุปกรณ์</label>
                    <input type="date" class="input" id="editPickupDate" name="pickup_date" required>
                </div>
                <div class="form-group">
                    <label for="editPickupTime">เวลารับ</label>
                    <input type="time" class="input" id="editPickupTime" name="pickup_time" required>
                </div>
                <div class="form-group">
                    <label for="editReturnDate">วันคืนอุปกรณ์</label>
                    <input type="date" class="input" id="editReturnDate" name="return_date" required>
                </div>
                <div class="form-group">
                    <label for="editReturnTime">เวลาคืน</label>
                    <input type="time" class="input" id="editReturnTime" name="return_time" required>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="editLocation">สถานที่ใช้งาน</label>
                    <input type="text" class="input" id="editLocation" name="location" required>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="editNotes">โน้ตเพิ่มเติม</label>
                    <textarea class="input" id="editNotes" name="notes" rows="3" placeholder="(ไม่บังคับ)"></textarea>
                </div>
            </div>

            <div id="editBookingFeedback" class="booking-modal__feedback" role="alert"></div>
            <p class="booking-modal__hint">ระบบจะตรวจสอบความพร้อมของแพ็คเกจอีกครั้งเพื่อป้องกันการจองทับซ้อน</p>
        </div>

        <footer class="booking-modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>ปิด</button>
            <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
        </footer>
    </form>
</div>

<div id="cancelBookingModal" class="booking-modal" hidden>
    <form id="cancelBookingForm" class="booking-modal__content" novalidate>
        <header class="booking-modal__header">
            <div>
                <div class="booking-modal__eyebrow">ยกเลิกการจอง</div>
                <h3 class="booking-modal__title">ยืนยันการยกเลิก</h3>
                <p id="cancelBookingSummary" class="booking-modal__subtitle"></p>
            </div>
            <button type="button" class="booking-modal__close" data-modal-close aria-label="ปิดหน้าต่าง">&times;</button>
        </header>

        <div class="booking-modal__body">
            <input type="hidden" name="booking_id" id="cancelBookingId">

            <div class="alert alert-warning" role="alert">
                <strong>โปรดทราบ:</strong> การยกเลิกที่ใกล้วันใช้งานอาจมีค่าปรับตามนโยบายที่กำหนด
            </div>

            <label for="cancelReason" class="booking-modal__label">เหตุผลในการยกเลิก (ไม่บังคับ)</label>
            <textarea class="input" id="cancelReason" name="reason" rows="3" placeholder="แจ้งเหตุผล (ถ้ามี)"></textarea>

            <div class="booking-modal__policy">
                <h4>นโยบายการยกเลิก</h4>
                <ul>
                    <li>ล่วงหน้ามากกว่า 7 วัน: ไม่มีค่าปรับ</li>
                    <li>ล่วงหน้า 3-7 วัน: ค่าปรับ 15% จากยอดรวม</li>
                    <li>ล่วงหน้า 1-3 วัน: ค่าปรับ 30%</li>
                    <li>ล่วงหน้าน้อยกว่า 24 ชั่วโมง: ค่าปรับ 50%</li>
                </ul>
            </div>

            <div id="cancelBookingFeedback" class="booking-modal__feedback" role="alert"></div>
        </div>

        <footer class="booking-modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>กลับ</button>
            <button type="submit" class="btn btn-danger">ยืนยันการยกเลิก</button>
        </footer>
    </form>
</div>

<script>
(function () {
    const dataEl = document.getElementById('bookingActionsData');
    if (!dataEl) {
        return;
    }

    const csrfToken = dataEl.dataset.csrfToken;
    if (!csrfToken) {
        return;
    }

    const backdrop = document.getElementById('bookingModalBackdrop');
    const editModal = document.getElementById('editBookingModal');
    const cancelModal = document.getElementById('cancelBookingModal');
    const editForm = document.getElementById('editBookingForm');
    const cancelForm = document.getElementById('cancelBookingForm');
    const editFeedback = document.getElementById('editBookingFeedback');
    const cancelFeedback = document.getElementById('cancelBookingFeedback');
    const cancelSummary = document.getElementById('cancelBookingSummary');
    const editTitle = document.getElementById('editBookingTitle');
    const editSubtitle = document.getElementById('editBookingSubtitle');
    const editSubmitBtn = editForm?.querySelector('button[type="submit"]');
    const cancelSubmitBtn = cancelForm?.querySelector('button[type="submit"]');
    let activeModal = null;

    const resetEditState = () => {
        if (!editForm) {
            return;
        }
        delete editForm.dataset.bookingId;
        editForm.reset();
        if (editFeedback) {
            editFeedback.textContent = '';
        }
        if (editSubmitBtn) {
            editSubmitBtn.disabled = true;
        }
    };

    const resetCancelState = () => {
        if (!cancelForm) {
            return;
        }
        delete cancelForm.dataset.bookingId;
        cancelForm.reset();
        if (cancelFeedback) {
            cancelFeedback.textContent = '';
        }
        if (cancelSummary) {
            cancelSummary.textContent = '';
        }
        if (cancelSubmitBtn) {
            cancelSubmitBtn.disabled = true;
        }
    };

    const closeModal = () => {
        if (activeModal) {
            activeModal.setAttribute('hidden', '');
            activeModal = null;
        }
        if (backdrop) {
            backdrop.setAttribute('hidden', '');
        }
        document.body.classList.remove('modal-open');
        resetEditState();
        resetCancelState();
    };

    const openModal = (modal) => {
        if (!modal || !backdrop) {
            return;
        }
        modal.removeAttribute('hidden');
        backdrop.removeAttribute('hidden');
        document.body.classList.add('modal-open');
        activeModal = modal;
    };

    if (editSubmitBtn) {
        editSubmitBtn.disabled = true;
    }
    if (cancelSubmitBtn) {
        cancelSubmitBtn.disabled = true;
    }

    backdrop?.addEventListener('click', closeModal);
    document.querySelectorAll('[data-modal-close]').forEach((btn) => {
        btn.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    const fillEditForm = (row) => {
        resetEditState();

        const bookingId = row.dataset.bookingId || '';
        const bookingCode = row.dataset.bookingCode || '';
        const packageName = row.dataset.packageName || '';

        editForm.querySelector('#editBookingId').value = bookingId;
        editForm.dataset.bookingId = bookingId;
        editForm.querySelector('#editPickupDate').value = row.dataset.pickupDate || '';
        editForm.querySelector('#editReturnDate').value = row.dataset.returnDate || '';
        editForm.querySelector('#editPickupTime').value = row.dataset.pickupTime || '';
        editForm.querySelector('#editReturnTime').value = row.dataset.returnTime || '';
        editForm.querySelector('#editLocation').value = row.dataset.location || '';
        editForm.querySelector('#editNotes').value = row.dataset.notes || '';

        editForm.dataset.originalPickupDate = row.dataset.pickupDate || '';
        editForm.dataset.originalReturnDate = row.dataset.returnDate || '';
        editForm.dataset.originalPickupTime = row.dataset.pickupTime || '';
        editForm.dataset.originalReturnTime = row.dataset.returnTime || '';
        editForm.dataset.originalLocation = row.dataset.location || '';
        editForm.dataset.originalNotes = row.dataset.notes || '';

        editTitle.textContent = `แก้ไขการจอง ${bookingCode}`;
        editSubtitle.textContent = packageName ? `แพ็คเกจ: ${packageName}` : '';

        if (editSubmitBtn) {
            editSubmitBtn.disabled = !bookingId;
        }
    };

    const fillCancelForm = (row) => {
        resetCancelState();

        const bookingId = row.dataset.bookingId || '';
        const bookingCode = row.dataset.bookingCode || '';
        const packageName = row.dataset.packageName || '';
        const pickupDate = row.dataset.pickupDate || '';

        if (!bookingId) {
            if (cancelFeedback) {
                cancelFeedback.textContent = 'ไม่พบรหัสการจองที่เลือก';
            }
            return false;
        }

        cancelForm.querySelector('#cancelBookingId').value = bookingId;
        cancelForm.dataset.bookingId = bookingId;
        cancelSummary.textContent = `รหัส ${bookingCode} • ${packageName} • เริ่ม ${pickupDate}`;

        if (cancelSubmitBtn) {
            cancelSubmitBtn.disabled = false;
        }

        return true;
    };

    document.querySelectorAll('.js-edit-booking').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            if (!row || row.dataset.canModify !== '1') {
                const reason = row?.dataset.denyReason;
                if (reason) {
                    alert(reason);
                }
                return;
            }
            fillEditForm(row);
            openModal(editModal);
        });
    });

    document.querySelectorAll('.js-cancel-booking').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            if (!row || row.dataset.canModify !== '1') {
                const reason = row?.dataset.denyReason;
                if (reason) {
                    alert(reason);
                }
                return;
            }
            const ok = fillCancelForm(row);
            if (ok !== false) {
                openModal(cancelModal);
            }
        });
    });

    const toggleSubmitting = (form, isSubmitting) => {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = isSubmitting;
        }
    };

    editForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        editFeedback.textContent = '';

        const bookingIdInput = editForm.querySelector('#editBookingId');
        const bookingIdValue = bookingIdInput?.value || editForm.dataset.bookingId || '';
        if (!bookingIdValue) {
            editFeedback.textContent = 'ไม่พบรหัสการจอง';
            return;
        }

        const payload = {
            booking_id: Number.parseInt(bookingIdValue, 10),
            csrf_token: csrfToken,
        };

        const fields = [
            ['pickup_date', 'originalPickupDate'],
            ['pickup_time', 'originalPickupTime'],
            ['return_date', 'originalReturnDate'],
            ['return_time', 'originalReturnTime'],
            ['location', 'originalLocation'],
            ['notes', 'originalNotes'],
        ];

        fields.forEach(([fieldName, originalKey]) => {
            const input = editForm.elements[fieldName];
            if (!input) {
                return;
            }
            const value = input.value.trim();
            const original = editForm.dataset[originalKey] ? editForm.dataset[originalKey].trim() : '';
            if (value !== original) {
                payload[fieldName] = value;
            }
        });

        if (Object.keys(payload).length <= 2) {
            editFeedback.textContent = 'ไม่มีข้อมูลที่เปลี่ยนแปลง';
            return;
        }

        toggleSubmitting(editForm, true);

        try {
            const response = await fetch('/pages/api/bookings_update.php', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                editFeedback.textContent = (data && data.error) ? data.error : 'ไม่สามารถบันทึกการแก้ไขได้';
                toggleSubmitting(editForm, false);
                return;
            }

            window.location.reload();
        } catch (error) {
            editFeedback.textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่';
            toggleSubmitting(editForm, false);
        }
    });

    cancelForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        cancelFeedback.textContent = '';

        const bookingIdInput = cancelForm.querySelector('#cancelBookingId');
        const bookingIdValue = bookingIdInput?.value || cancelForm.dataset.bookingId || '';
        if (!bookingIdValue) {
            cancelFeedback.textContent = 'ไม่พบรหัสการจอง';
            return;
        }

        const payload = {
            booking_id: Number.parseInt(bookingIdValue, 10),
            csrf_token: csrfToken,
        };

        const reason = cancelForm.querySelector('#cancelReason').value.trim();
        if (reason) {
            payload.reason = reason;
        }

        toggleSubmitting(cancelForm, true);

        try {
            const response = await fetch('/pages/api/bookings_update.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                cancelFeedback.textContent = (data && data.error) ? data.error : 'ไม่สามารถยกเลิกการจองได้';
                toggleSubmitting(cancelForm, false);
                return;
            }

            window.location.reload();
        } catch (error) {
            cancelFeedback.textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่';
            toggleSubmitting(cancelForm, false);
        }
    });
})();
</script>

<style>
.booking-actions__stack {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.booking-actions__stack .btn {
    padding: 0.45rem 1rem;
}

.booking-modal__backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.35);
    backdrop-filter: blur(2px);
    z-index: 1040;
}

.booking-modal {
    position: fixed;
    inset: 0;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 48px 16px;
    z-index: 1050;
    overflow-y: auto;
}

.booking-modal__content {
    background: #ffffff;
    border-radius: var(--brand-radius-lg, 16px);
    box-shadow: var(--brand-shadow-lg, 0 24px 48px rgba(15, 23, 42, 0.18));
    width: min(540px, 100%);
    display: flex;
    flex-direction: column;
    max-height: 100%;
}

.booking-modal__header,
.booking-modal__footer {
    padding: 20px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.booking-modal__body {
    padding: 0 24px 24px;
    overflow-y: auto;
}

.booking-modal__eyebrow {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--brand-muted, #6b7280);
}

.booking-modal__title {
    margin: 4px 0;
    font-size: 1.35rem;
}

.booking-modal__subtitle {
    margin: 0;
    color: var(--brand-muted, #6b7280);
    font-size: 0.95rem;
}

.booking-modal__close {
    border: none;
    background: transparent;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--brand-muted, #6b7280);
}

.booking-modal__feedback {
    margin-top: 16px;
    color: #dc2626;
    font-size: 0.9rem;
    min-height: 20px;
}

.booking-modal__hint {
    margin: 12px 0 0;
    font-size: 0.85rem;
    color: var(--brand-muted, #6b7280);
}

.booking-modal__policy {
    background: #f9fafb;
    border-radius: var(--brand-radius-md, 12px);
    padding: 16px;
    margin: 16px 0 0;
}

.booking-modal__policy h4 {
    margin: 0 0 8px;
    font-size: 1rem;
}

.booking-modal__policy ul {
    margin: 0;
    padding-left: 20px;
    color: var(--brand-muted, #6b7280);
}

.booking-modal__footer {
    border-top: 1px solid rgba(15, 23, 42, 0.08);
}

.booking-modal__body .form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.booking-modal__body label {
    font-weight: 600;
}

.booking-modal__body .input,
.booking-modal__body textarea {
    width: 100%;
}

.booking-modal__label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}

body.modal-open {
    overflow: hidden;
}

.booking-modal__subtitle:empty,
.booking-modal__feedback:empty {
    display: none;
}
</style>

<?php
render_page_end();
