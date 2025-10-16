<?php
/**
 * หน้าจองอุปกรณ์
 * ระบบจองอุปกรณ์ Live Streaming
 */

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/includes/config.php';
require_once $rootPath . '/includes/functions.php';
require_once $rootPath . '/models/Package.php';
require_once $rootPath . '/models/Booking.php';

require_login();

$error_message = '';
$success_message = '';
$selected_package_id = isset($_GET['package']) ? (int)$_GET['package'] : 0;

try {
    $database = new Database();
    $db = $database->getConnection();
    $package = new Package($db);
    $booking = new Booking($db);

    $packages = $package->getActivePackages();
    $selected_package = null;

    if ($selected_package_id > 0 && $package->getById($selected_package_id)) {
        $selected_package = [
            'id' => $package->id,
            'name' => $package->name,
            'description' => $package->description,
            'price' => $package->price,
            'equipment_list' => $package->equipment_list,
            'max_concurrent_reservations' => $package->max_concurrent_reservations,
        ];
    }
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    log_event('Booking page error: ' . $e->getMessage(), 'ERROR');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_booking'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid CSRF token';
    } else {
        $booking_data = [
            'package_id' => (int)($_POST['package_id'] ?? 0),
            'pickup_date' => $_POST['pickup_date'] ?? null,
            'return_date' => $_POST['return_date'] ?? null,
            'pickup_time' => $_POST['pickup_time'] ?? BOOKING_DEFAULT_PICKUP_TIME,
            'return_time' => $_POST['return_time'] ?? BOOKING_DEFAULT_RETURN_TIME,
            'location' => sanitize_input($_POST['location'] ?? ''),
            'notes' => sanitize_input($_POST['notes'] ?? ''),
        ];

        try {
            $validation_errors = $booking->validate($booking_data);

            if (!empty($validation_errors)) {
                $error_message = implode('<br>', $validation_errors);
            } else {
                $is_available = $booking->checkPackageAvailability(
                    $booking_data['package_id'],
                    $booking_data['pickup_date'],
                    $booking_data['return_date']
                );

                if (!$is_available) {
                    $booking->logCapacityWarning(
                        $booking_data['package_id'],
                        $booking_data['pickup_date'],
                        $booking_data['return_date'],
                        $_SESSION['user_id'] ?? null
                    );
                    $error_message = 'แพ็คเกจนี้ไม่ว่างในช่วงวันที่เลือก กรุณาเลือกช่วงอื่น';
                } else {
                    $package->getById($booking_data['package_id']);
                    $pricing_breakdown = $booking->calculatePricingBreakdown(
                        $package->price,
                        $booking_data['pickup_date'],
                        $booking_data['return_date']
                    );
                    $subtotal = $pricing_breakdown['subtotal'];
                    $vat_amount = $subtotal * VAT_RATE;
                    $total_price = $subtotal + $vat_amount;

                    $booking->user_id = $_SESSION['user_id'];
                    $booking->package_id = $booking_data['package_id'];
                    $booking->pickup_date = $booking_data['pickup_date'];
                    $booking->return_date = $booking_data['return_date'];
                    $booking->pickup_time = $booking_data['pickup_time'];
                    $booking->return_time = $booking_data['return_time'];
                    $booking->location = $booking_data['location'];
                    $booking->notes = $booking_data['notes'];
                    $booking->total_price = $total_price;
                    $booking->status = 'pending';

                    if ($booking->create()) {
                        $success_message = 'จองสำเร็จ! รหัสการจอง: ' . $booking->booking_code;
                        log_event("New booking created: {$booking->booking_code} by user {$_SESSION['username']}", 'INFO');
                        header('refresh:2;url=payment.php?booking=' . $booking->booking_code);
                    } else {
                        if ($booking->error_code === 'capacity_conflict') {
                            $error_message = 'แพ็คเกจนี้ไม่ว่างในช่วงวันที่เลือก กรุณาเลือกช่วงอื่น';
                        } else {
                            $error_message = 'เกิดข้อผิดพลาดในการจอง กรุณาลองใหม่อีกครั้ง';
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $error_message = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
            log_event('Booking creation error: ' . $e->getMessage(), 'ERROR');
        }
    }
}

$recent_request = $_POST ?? [];
$quote_preview = $pricing_breakdown ?? null;

require_once $rootPath . '/includes/layout.php';

$map_extra_css = [];
if (GOOGLE_MAPS_API_KEY === '') {
    $map_extra_css[] = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
}

render_page_start('จองอุปกรณ์ - ' . SITE_NAME, [
    'active' => 'packages',
    'extra_css' => $map_extra_css,
]);
?>

<section class="section">
    <div class="page-container">
        <div class="section-header">
            <div class="hero__eyebrow">Booking Hub</div>
            <h2>จองอุปกรณ์ Live Streaming</h2>
            <p>เลือกแพ็คเกจที่เหมาะกับงานของคุณ กำหนดช่วงเวลารับ-คืน และยืนยันการจองได้ในหน้าจอเดียว</p>
        </div>

        <div class="stepper">
            <div class="stepper__item stepper__item--active"><span class="stepper__number">1</span> เลือกแพ็คเกจ</div>
            <div class="stepper__item"><span class="stepper__number">2</span> กรอกรายละเอียด</div>
            <div class="stepper__item"><span class="stepper__number">3</span> ชำระเงิน</div>
        </div>

        <?php if ($error_message !== '') : ?>
            <div class="alert alert-danger" role="alert"><?= $error_message; ?></div>
        <?php endif; ?>

        <?php if ($success_message !== '') : ?>
            <div class="alert alert-success" role="alert"><?= $success_message; ?></div>
        <?php endif; ?>

        <div class="booking-shell">
            <div class="booking-panel">
                <div class="booking-panel__header">
                    <div>
                        <h3 style="margin:0;">เลือกแพ็คเกจ</h3>
                        <p style="margin:6px 0 0; color:var(--brand-muted);">แตะที่แพ็คเกจเพื่อดูรายละเอียดและดำเนินการจอง</p>
                    </div>
                    <span class="badge">ขั้นตอน 1 / 3</span>
                </div>

                <div class="package-grid" id="packageSelection">
                    <?php foreach ($packages as $pkg) :
                        $equipment = json_decode($pkg['equipment_list'], true) ?? [];
                    ?>
                        <div class="package-card <?= $selected_package_id === (int)$pkg['id'] ? 'selected' : ''; ?>"
                             data-package-id="<?= (int)$pkg['id']; ?>"
                             data-package-name="<?= htmlspecialchars($pkg['name'], ENT_QUOTES, 'UTF-8'); ?>"
                             data-package-price="<?= (float)$pkg['price']; ?>"
                             data-package-capacity="<?= (int)($pkg['max_concurrent_reservations'] ?? 1); ?>">
                            <div>
                                <h4 class="package-card__title"><?= htmlspecialchars($pkg['name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                <div class="package-card__price"><?= format_currency($pkg['price']); ?></div>
                                <div class="package-card__meta">ต่อวัน • รองรับสูงสุด <?= (int)($pkg['max_concurrent_reservations'] ?? 1); ?> คิว</div>
                            </div>
                            <p class="package-card__meta"><?= htmlspecialchars($pkg['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php if (!empty($equipment)) : ?>
                                <ul class="package-card__list">
                                    <?php foreach ($equipment as $item) : ?>
                                        <li><i class="fa-solid fa-check" style="color:var(--brand-primary);"></i><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8'); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="POST" action="" id="bookingForm"
                      class="form-grid"
                      style="margin-top:32px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); display: <?= $selected_package ? 'grid' : 'none'; ?>;">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                    <input type="hidden" name="package_id" id="selectedPackageId" value="<?= (int)$selected_package_id; ?>">

                    <div>
                        <label for="pickup_date">วันที่รับอุปกรณ์ <span style="color:#dc3545;">*</span></label>
                        <input type="date" class="input" id="pickup_date" name="pickup_date"
                               value="<?= htmlspecialchars($recent_request['pickup_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               min="<?= date('Y-m-d'); ?>" required>
                    </div>

                    <div>
                        <label for="return_date">วันที่คืนอุปกรณ์ <span style="color:#dc3545;">*</span></label>
                        <input type="date" class="input" id="return_date" name="return_date"
                               value="<?= htmlspecialchars($recent_request['return_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               min="<?= htmlspecialchars($recent_request['pickup_date'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div>
                        <label for="pickup_time">เวลารับ</label>
                        <input type="time" class="input" id="pickup_time" name="pickup_time"
                               value="<?= htmlspecialchars($recent_request['pickup_time'] ?? BOOKING_DEFAULT_PICKUP_TIME, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div>
                        <label for="return_time">เวลาคืน</label>
                        <input type="time" class="input" id="return_time" name="return_time"
                               value="<?= htmlspecialchars($recent_request['return_time'] ?? BOOKING_DEFAULT_RETURN_TIME, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label for="location">สถานที่ใช้งาน <span style="color:#dc3545;">*</span></label>
                        <?php $use_google_maps = GOOGLE_MAPS_API_KEY !== ''; ?>
                        <p style="margin:8px 0; font-size:0.85rem; color:var(--brand-muted);">
                            <?= $use_google_maps
                                ? 'ปักหมุดหรือค้นหาสถานที่ ระบบจะบันทึกค่าที่อยู่ให้อัตโนมัติ'
                                : 'ปักหมุดหรือค้นหาสถานที่บนแผนที่ (OpenStreetMap) ระบบจะบันทึกค่าที่อยู่ให้อัตโนมัติ'; ?>
                        </p>
                        <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:12px;">
                            <input type="text" class="input" id="locationSearch" name="location_search"
                                   style="flex:1 1 240px;"
                                   placeholder="ค้นหาชื่อสถานที่หรือที่อยู่" autocomplete="off"
                                   value="<?= isset($recent_request['location_search']) ? htmlspecialchars($recent_request['location_search'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            <?php if (!$use_google_maps) : ?>
                                <button type="button" id="locationSearchButton" class="btn btn-ghost" style="padding:10px 16px;">
                                    ค้นหา
                                </button>
                            <?php endif; ?>
                        </div>
                        <div id="locationMap" style="width:100%; height:280px; margin-bottom:12px; border-radius:12px; border:1px solid #e0e0e0; background:#f8f9fa;"></div>
                        <input type="hidden" name="location_lat" id="locationLat" value="<?= isset($recent_request['location_lat']) ? htmlspecialchars((string)$recent_request['location_lat'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        <input type="hidden" name="location_lng" id="locationLng" value="<?= isset($recent_request['location_lng']) ? htmlspecialchars((string)$recent_request['location_lng'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        <input type="hidden" name="location_place_id" id="locationPlaceId" value="<?= isset($recent_request['location_place_id']) ? htmlspecialchars((string)$recent_request['location_place_id'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        <textarea class="input" id="location" name="location" rows="3" placeholder="กรุณาระบุสถานที่ที่จะใช้งานอุปกรณ์" required><?= isset($recent_request['location']) ? htmlspecialchars($recent_request['location'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                        <small style="display:block; margin-top:6px; font-size:0.8rem; color:var(--brand-muted);">
                            สามารถแก้ไขข้อความที่อยู่ก่อนยืนยันการจองได้ทุกขั้นตอน
                        </small>
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label for="notes">หมายเหตุเพิ่มเติม</label>
                        <textarea class="input" id="notes" name="notes" rows="3" placeholder="ข้อมูลเพิ่มเติมหรือความต้องการพิเศษ (ไม่บังคับ)"><?= isset($recent_request['notes']) ? htmlspecialchars($recent_request['notes'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <div id="availabilityNotice" class="alert alert-info" role="alert" style="display:none;"></div>
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <button type="submit" name="create_booking" class="btn btn-primary" style="width:100%;">
                            ยืนยันการจอง
                        </button>
                    </div>
                </form>
            </div>

            <aside class="booking-summary">
                <h3 style="margin-top:0;">สรุปการจอง</h3>
                <div id="bookingSummary" style="display: <?= $selected_package ? 'block' : 'none'; ?>;">
                    <div class="mb-3">
                        <strong>แพ็คเกจที่เลือก</strong>
                        <div id="selectedPackageName"><?= $selected_package ? htmlspecialchars($selected_package['name'], ENT_QUOTES, 'UTF-8') : ''; ?></div>
                        <div class="package-card__meta" id="selectedPackageCapacity">
                            <?php if ($selected_package) : ?>
                                รองรับการจองพร้อมกันสูงสุด <?= (int)($selected_package['max_concurrent_reservations'] ?? 1); ?> คิวต่อวัน
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="price-summary">
                        <div class="price-summary__row"><span>ราคาแพ็คเกจ (วันแรก)</span><span id="summaryBaseDay">-</span></div>
                        <div class="price-summary__row"><span>จำนวนวันเช่า</span><span id="summaryRentalDays">-</span></div>
                        <div style="display:grid; gap:6px; font-size:0.85rem; color:var(--brand-muted);">
                            <div class="price-summary__row"><span>วันที 2 (+40%)</span><span id="summaryDay2">-</span></div>
                            <div class="price-summary__row"><span>วันที 3-6 (+20%/วัน)</span><span id="summaryDay3to6">-</span></div>
                            <div class="price-summary__row"><span>วันที 7+ (+10%/วัน)</span><span id="summaryDay7plus">-</span></div>
                            <div class="price-summary__row"><span>เสาร์-อาทิตย์/วันหยุด (+10%/วัน)</span><span id="summaryWeekend">-</span></div>
                        </div>
                        <div class="price-summary__row"><span>ราคารวมก่อน VAT</span><span id="summarySubtotal">-</span></div>
                        <div class="price-summary__row"><span>VAT (<?= number_format(VAT_RATE * 100, 0); ?>%)</span><span id="summaryVat">-</span></div>
                        <div class="price-summary__row price-summary__total"><span>ราคารวมสุทธิ</span><span id="summaryTotal">-</span></div>
                    </div>

                    <p style="margin-top:16px; font-size:0.85rem; color:var(--brand-muted);">
                        <i class="fa-solid fa-circle-info"></i>
                        ต้องชำระมัดจำ 50% ของราคารวมเมื่อยืนยันการจอง
                    </p>
                </div>

                <div id="noPackageSelected" class="empty-state" style="display: <?= $selected_package ? 'none' : 'block'; ?>;">
                    <i class="fa-solid fa-arrow-pointer"></i>
                    <p style="margin-top:12px;">กรุณาเลือกแพ็คเกจเพื่อดูรายละเอียดการชำระเงิน</p>
                </div>

                <div class="info-card">
                    <strong>ข้อมูลผู้จอง</strong>
                    <span>ชื่อ: <?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span>Username: <?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </aside>
        </div>

        <div style="margin-top:32px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
            <a class="btn btn-ghost" href="/index.php"><i class="fa-solid fa-arrow-left"></i>&nbsp;กลับหน้าแรก</a>
            <a class="btn btn-ghost" href="/pages/web/profile.php"><i class="fa-solid fa-user"></i>&nbsp;โปรไฟล์</a>
        </div>
    </div>
</section>

<?php if (GOOGLE_MAPS_API_KEY === '') : ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>

<script>
    const HOLIDAYS = new Set(<?= json_encode(get_configured_holidays()); ?>);
    const VAT_RATE = <?= (float)VAT_RATE; ?>;
    const PRICING_RULES = {
        day2: <?= (float)BOOKING_SURCHARGE_DAY2; ?>,
        day3to6: <?= (float)BOOKING_SURCHARGE_DAY3_TO6; ?>,
        day7plus: <?= (float)BOOKING_SURCHARGE_DAY7_PLUS; ?>,
        weekend: <?= (float)BOOKING_WEEKEND_HOLIDAY_SURCHARGE; ?>
    };

    const DEFAULT_PICKUP_TIME = '<?= BOOKING_DEFAULT_PICKUP_TIME; ?>';
    const DEFAULT_RETURN_TIME = '<?= BOOKING_DEFAULT_RETURN_TIME; ?>';

    const packageSelection = document.getElementById('packageSelection');
    const bookingForm = document.getElementById('bookingForm');
    const bookingSummary = document.getElementById('bookingSummary');
    const noPackageSelected = document.getElementById('noPackageSelected');
    const capacityField = document.getElementById('selectedPackageCapacity');
    const availabilityNotice = document.getElementById('availabilityNotice');

    const pickupDateInput = document.getElementById('pickup_date');
    const returnDateInput = document.getElementById('return_date');
    const pickupTimeInput = document.getElementById('pickup_time');
    const returnTimeInput = document.getElementById('return_time');
    const locationField = document.getElementById('location');
    const locationSearchInput = document.getElementById('locationSearch');
    const locationLatInput = document.getElementById('locationLat');
    const locationLngInput = document.getElementById('locationLng');
    const locationPlaceIdInput = document.getElementById('locationPlaceId');

    const summaryBaseDay = document.getElementById('summaryBaseDay');
    const summaryRentalDays = document.getElementById('summaryRentalDays');
    const summaryDay2 = document.getElementById('summaryDay2');
    const summaryDay3to6 = document.getElementById('summaryDay3to6');
    const summaryDay7plus = document.getElementById('summaryDay7plus');
    const summaryWeekend = document.getElementById('summaryWeekend');
    const summarySubtotal = document.getElementById('summarySubtotal');
    const summaryVat = document.getElementById('summaryVat');
    const summaryTotal = document.getElementById('summaryTotal');
    const selectedPackageIdInput = document.getElementById('selectedPackageId');
    const selectedPackageName = document.getElementById('selectedPackageName');

    const selectedPackage = {
        id: <?= $selected_package ? (int)$selected_package['id'] : 'null'; ?>,
        name: <?= $selected_package ? json_encode($selected_package['name']) : 'null'; ?>,
        price: <?= $selected_package ? (float)$selected_package['price'] : 'null'; ?>,
        capacity: <?= $selected_package ? (int)($selected_package['max_concurrent_reservations'] ?? 1) : 'null'; ?>
    };

    const MAP_PROVIDER = <?= GOOGLE_MAPS_API_KEY !== '' ? json_encode('google') : json_encode('leaflet'); ?>;
    const locationSearchButton = document.getElementById('locationSearchButton');
    const NOMINATIM_ENDPOINT = 'https://nominatim.openstreetmap.org';
    const NOMINATIM_EMAIL = 'support@example.com';

    let locationMapInstance = null;
    let locationMarker = null;
    let locationGeocoder = null;
    let locationAutocomplete = null;

    function setLocationFormValues(options = {}) {
        const {
            formattedAddress,
            searchText,
            lat,
            lng,
            placeId
        } = options;

        if (typeof formattedAddress === 'string' && locationField) {
            locationField.value = formattedAddress;
        }

        if (typeof searchText === 'string' && locationSearchInput) {
            locationSearchInput.value = searchText;
        }

        if (typeof lat !== 'undefined' && locationLatInput) {
            locationLatInput.value = lat === null || lat === '' ? '' : lat.toString();
        }

        if (typeof lng !== 'undefined' && locationLngInput) {
            locationLngInput.value = lng === null || lng === '' ? '' : lng.toString();
        }

        if (typeof placeId !== 'undefined' && locationPlaceIdInput) {
            locationPlaceIdInput.value = placeId || '';
        }
    }

    function updateLocationFromLatLng(latLng, meta = {}) {
        if (!latLng) return;

        const lat = typeof latLng.lat === 'function' ? latLng.lat() : latLng.lat;
        const lng = typeof latLng.lng === 'function' ? latLng.lng() : latLng.lng;

        setLocationFormValues({ lat, lng, placeId: meta.placeId || '' });

        if (meta.formattedAddress) {
            setLocationFormValues({
                formattedAddress: meta.formattedAddress,
                searchText: meta.formattedAddress
            });
            return;
        }

        if (MAP_PROVIDER === 'google') {
            if (!locationGeocoder) {
                return;
            }

            locationGeocoder.geocode({ location: latLng }, (results, status) => {
                if (status === 'OK' && Array.isArray(results) && results[0]) {
                    const result = results[0];
                    setLocationFormValues({
                        formattedAddress: result.formatted_address,
                        searchText: result.formatted_address,
                        placeId: meta.placeId || result.place_id || ''
                    });
                }
            });
        } else if (MAP_PROVIDER === 'leaflet') {
            reverseGeocodeWithNominatim(lat, lng, meta.placeId || '');
        }
    }

    function reverseGeocodeWithNominatim(lat, lng, fallbackPlaceId = '') {
        if (MAP_PROVIDER !== 'leaflet') {
            return;
        }

        const params = new URLSearchParams({
            format: 'jsonv2',
            lat: String(lat),
            lon: String(lng),
            zoom: '16',
            addressdetails: '1',
            email: NOMINATIM_EMAIL
        });

        fetch(`${NOMINATIM_ENDPOINT}/reverse?${params.toString()}`, {
            headers: { 'Accept': 'application/json' }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Reverse geocode failed: ${response.status}`);
                }
                return response.json();
            })
            .then(payload => {
                if (payload && payload.display_name) {
                    setLocationFormValues({
                        formattedAddress: payload.display_name,
                        searchText: payload.display_name,
                        placeId: payload.place_id ? String(payload.place_id) : fallbackPlaceId
                    });
                }
            })
            .catch(error => {
                console.error('Reverse geocode error', error);
            });
    }

    function initGoogleLocationPicker() {
        if (typeof google === 'undefined' || !google.maps) {
            console.error('Google Maps SDK is not available');
            return;
        }

        const mapNode = document.getElementById('locationMap');
        if (!mapNode || !locationField) {
            return;
        }

        const parsedLat = locationLatInput ? parseFloat(locationLatInput.value) : NaN;
        const parsedLng = locationLngInput ? parseFloat(locationLngInput.value) : NaN;
        const hasSavedCoords = !Number.isNaN(parsedLat) && !Number.isNaN(parsedLng);
        const fallbackCenter = { lat: 13.7563, lng: 100.5018 }; // Bangkok default
        const initialCenter = hasSavedCoords ? { lat: parsedLat, lng: parsedLng } : fallbackCenter;

        locationGeocoder = new google.maps.Geocoder();
        locationMapInstance = new google.maps.Map(mapNode, {
            center: initialCenter,
            zoom: hasSavedCoords ? 15 : 12,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false
        });

        locationMarker = new google.maps.Marker({
            map: locationMapInstance,
            draggable: true,
            position: initialCenter,
            visible: hasSavedCoords
        });

        locationMapInstance.addListener('click', event => {
            if (!event.latLng) return;
            locationMarker.setVisible(true);
            locationMarker.setPosition(event.latLng);
            updateLocationFromLatLng(event.latLng);
        });

        locationMarker.addListener('dragend', event => {
            if (!event.latLng) return;
            updateLocationFromLatLng(event.latLng);
        });

        if (locationSearchInput) {
            locationAutocomplete = new google.maps.places.Autocomplete(locationSearchInput, {
                fields: ['formatted_address', 'geometry', 'name', 'place_id'],
                componentRestrictions: { country: 'th' }
            });

            locationAutocomplete.addListener('place_changed', () => {
                const place = locationAutocomplete.getPlace();
                if (!place || !place.geometry || !place.geometry.location) {
                    return;
                }

                const latLng = place.geometry.location;
                locationMapInstance.panTo(latLng);
                locationMapInstance.setZoom(15);
                locationMarker.setVisible(true);
                locationMarker.setPosition(latLng);
                updateLocationFromLatLng(latLng, {
                    formattedAddress: place.formatted_address || place.name || '',
                    placeId: place.place_id || ''
                });
            });
        }

        const existingAddress = locationField.value.trim();
        if (existingAddress && !hasSavedCoords && locationGeocoder) {
            locationGeocoder.geocode({ address: existingAddress }, (results, status) => {
                if (status === 'OK' && Array.isArray(results) && results[0]) {
                    const result = results[0];
                    locationMarker.setVisible(true);
                    locationMarker.setPosition(result.geometry.location);
                    locationMapInstance.panTo(result.geometry.location);
                    locationMapInstance.setZoom(15);
                    updateLocationFromLatLng(result.geometry.location, {
                        formattedAddress: result.formatted_address,
                        placeId: result.place_id || ''
                    });
                }
            });
        }
    }

    function initLeafletLocationPicker() {
        if (typeof L === 'undefined') {
            console.error('Leaflet library is not available');
            return;
        }

        const mapNode = document.getElementById('locationMap');
        if (!mapNode || !locationField) {
            return;
        }

        const parsedLat = locationLatInput ? parseFloat(locationLatInput.value) : NaN;
        const parsedLng = locationLngInput ? parseFloat(locationLngInput.value) : NaN;
        const hasSavedCoords = !Number.isNaN(parsedLat) && !Number.isNaN(parsedLng);
        const fallbackCenter = { lat: 13.7563, lng: 100.5018 };
        const initialCenter = hasSavedCoords ? { lat: parsedLat, lng: parsedLng } : fallbackCenter;

        locationMapInstance = L.map(mapNode).setView([initialCenter.lat, initialCenter.lng], hasSavedCoords ? 15 : 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors'
        }).addTo(locationMapInstance);

        locationMarker = L.marker([initialCenter.lat, initialCenter.lng], {
            draggable: true,
            autoPan: true
        });

        if (hasSavedCoords) {
            locationMarker.addTo(locationMapInstance);
        }

        locationMarker.on('dragend', event => {
            updateLocationFromLatLng(event.target.getLatLng());
        });

        locationMapInstance.on('click', event => {
            if (!event.latlng) {
                return;
            }

            if (!locationMapInstance.hasLayer(locationMarker)) {
                locationMarker.addTo(locationMapInstance);
            }

            locationMarker.setLatLng(event.latlng);
            updateLocationFromLatLng(event.latlng);
        });

        if (locationSearchInput) {
            locationSearchInput.addEventListener('keydown', event => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    performLeafletSearch(locationSearchInput.value.trim());
                }
            });
        }

        if (locationSearchButton) {
            if (!locationSearchButton.dataset.defaultLabel) {
                locationSearchButton.dataset.defaultLabel = locationSearchButton.textContent.trim() || 'ค้นหา';
            }
            locationSearchButton.addEventListener('click', () => {
                performLeafletSearch(locationSearchInput ? locationSearchInput.value.trim() : '');
            });
        }

        const existingAddress = locationField.value.trim();
        if (existingAddress && !hasSavedCoords) {
            performLeafletSearch(existingAddress);
        }
    }

    function performLeafletSearch(query) {
        if (MAP_PROVIDER !== 'leaflet' || !query) {
            return;
        }

        if (!locationMapInstance || typeof L === 'undefined') {
            console.error('Leaflet map is not initialised');
            return;
        }

        if (locationSearchButton) {
            locationSearchButton.disabled = true;
            locationSearchButton.textContent = 'กำลังค้นหา...';
        }

        const params = new URLSearchParams({
            format: 'json',
            q: query,
            countrycodes: 'th',
            limit: '1',
            addressdetails: '1',
            email: NOMINATIM_EMAIL
        });

        fetch(`${NOMINATIM_ENDPOINT}/search?${params.toString()}`, {
            headers: { 'Accept': 'application/json' }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Search failed with status ${response.status}`);
                }
                return response.json();
            })
            .then(results => {
                if (!Array.isArray(results) || results.length === 0) {
                    alert('ไม่พบสถานที่ กรุณาลองค้นหาใหม่');
                    return;
                }

                const result = results[0];
                const lat = parseFloat(result.lat);
                const lng = parseFloat(result.lon);
                if (Number.isNaN(lat) || Number.isNaN(lng)) {
                    alert('ข้อมูลพิกัดไม่ถูกต้องจากผลลัพธ์การค้นหา');
                    return;
                }

                if (!locationMarker) {
                    locationMarker = L.marker([lat, lng], {
                        draggable: true,
                        autoPan: true
                    });
                    locationMarker.addTo(locationMapInstance);
                    locationMarker.on('dragend', event => {
                        updateLocationFromLatLng(event.target.getLatLng());
                    });
                } else {
                    if (!locationMapInstance.hasLayer(locationMarker)) {
                        locationMarker.addTo(locationMapInstance);
                    }
                    locationMarker.setLatLng([lat, lng]);
                }

                locationMapInstance.setView([lat, lng], 15);
                updateLocationFromLatLng({ lat, lng }, {
                    formattedAddress: result.display_name || query,
                    placeId: result.place_id ? String(result.place_id) : ''
                });
            })
            .catch(error => {
                console.error('ค้นหาสถานที่ล้มเหลว', error);
                alert('ไม่สามารถค้นหาสถานที่ได้ กรุณาลองใหม่');
            })
            .finally(() => {
                if (locationSearchButton) {
                    locationSearchButton.disabled = false;
                    const defaultLabel = locationSearchButton.dataset.defaultLabel || 'ค้นหา';
                    locationSearchButton.textContent = defaultLabel;
                }
            });
    }

    window.initLocationPicker = function initLocationPicker() {
        if (MAP_PROVIDER === 'google') {
            initGoogleLocationPicker();
        } else {
            initLeafletLocationPicker();
        }
    };

    if (MAP_PROVIDER === 'leaflet') {
        document.addEventListener('DOMContentLoaded', () => {
            initLocationPicker();
        });
    }

    locationField?.addEventListener('input', () => {
        if (locationLatInput) locationLatInput.value = '';
        if (locationLngInput) locationLngInput.value = '';
        if (locationPlaceIdInput) locationPlaceIdInput.value = '';
        if (locationSearchInput && locationField.value.trim() === '') {
            locationSearchInput.value = '';
        }
    });

    packageSelection?.addEventListener('click', event => {
        const card = event.target.closest('.package-card');
        if (!card) return;

        document.querySelectorAll('#packageSelection .package-card').forEach(node => node.classList.remove('selected'));
        card.classList.add('selected');

        selectedPackage.id = parseInt(card.dataset.packageId, 10);
        selectedPackage.name = card.dataset.packageName;
        selectedPackage.price = parseFloat(card.dataset.packagePrice);
        selectedPackage.capacity = parseInt(card.dataset.packageCapacity, 10) || 1;

        selectedPackageIdInput.value = selectedPackage.id;
        selectedPackageName.textContent = selectedPackage.name;
        if (capacityField) {
            capacityField.textContent = `รองรับการจองพร้อมกันสูงสุด ${selectedPackage.capacity} คิวต่อวัน`;
        }

        bookingForm.style.display = 'grid';
        bookingSummary.style.display = 'block';
        noPackageSelected.style.display = 'none';

        updateSummary();
        fetchAvailability();
    });

    function updateSummary() {
        if (!selectedPackage.id) return;

        const pickupDate = pickupDateInput.value;
        const returnDate = returnDateInput.value || pickupDate;
        const rentalDays = calculateRentalDays(pickupDate, returnDate);
        const dayBreakdown = calculateDayBreakdown(pickupDate, returnDate, rentalDays);

        const basePrice = selectedPackage.price || 0;
        const baseDayCost = rentalDays > 0 ? basePrice : 0;
        const day2Cost = dayBreakdown.day2 * basePrice * PRICING_RULES.day2;
        const day3to6Cost = dayBreakdown.day3to6 * basePrice * PRICING_RULES.day3to6;
        const day7plusCost = dayBreakdown.day7plus * basePrice * PRICING_RULES.day7plus;
        const weekendCost = dayBreakdown.weekend * basePrice * PRICING_RULES.weekend;

        const subtotal = baseDayCost + day2Cost + day3to6Cost + day7plusCost + weekendCost;
        const vat = subtotal * VAT_RATE;
        const total = subtotal + vat;

        summaryBaseDay.textContent = formatCurrency(baseDayCost);
        summaryRentalDays.textContent = rentalDays;
        summaryDay2.textContent = formatCurrency(day2Cost);
        summaryDay3to6.textContent = formatCurrency(day3to6Cost);
        summaryDay7plus.textContent = formatCurrency(day7plusCost);
        summaryWeekend.textContent = formatCurrency(weekendCost);
        summarySubtotal.textContent = formatCurrency(subtotal);
        summaryVat.textContent = formatCurrency(vat);
        summaryTotal.textContent = formatCurrency(total);
    }

    function calculateRentalDays(start, end) {
        if (!start || !end) return 0;
        const startDate = new Date(start);
        const endDate = new Date(end);
        const diffTime = endDate.getTime() - startDate.getTime();
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
        return diffDays + 1;
    }

    function calculateDayBreakdown(start, end, days) {
        if (!start || !end || days <= 0) {
            return { day2: 0, day3to6: 0, day7plus: 0, weekend: 0 };
        }
        let day2 = 0;
        let day3to6 = 0;
        let day7plus = 0;
        let weekend = 0;

        const startDate = new Date(start);
        for (let i = 0; i < days; i++) {
            const current = new Date(startDate);
            current.setDate(startDate.getDate() + i);
            const dayOfWeek = current.getDay();
            const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
            const dateISO = current.toISOString().split('T')[0];
            const isHoliday = HOLIDAYS.has(dateISO);

            if (i === 0) {
                continue; // base day already counted
            } else if (i === 1) {
                day2 += 1;
            } else if (i >= 2 && i <= 5) {
                day3to6 += 1;
            } else {
                day7plus += 1;
            }

            if (isWeekend || isHoliday) {
                weekend += 1;
            }
        }

        return { day2, day3to6, day7plus, weekend };
    }

    function formatCurrency(amount) {
        return new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(amount || 0);
    }

    function fetchAvailability() {
        if (!selectedPackage.id) return;

        const packageId = selectedPackage.id;
        const start = pickupDateInput.value;
        const end = returnDateInput.value;

        if (!start) {
            availabilityNotice.style.display = 'none';
            return;
        }

        availabilityNotice.style.display = 'block';
        availabilityNotice.textContent = 'ตรวจสอบความพร้อมของแพ็คเกจ...';
        availabilityNotice.classList.remove('alert-danger');
        availabilityNotice.classList.add('alert-info');

        const params = new URLSearchParams({ package_id: packageId });
        if (start) params.append('start', start);
        if (end) params.append('end', end);

        fetch(`api/availability.php?${params.toString()}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(payload => {
                if (!payload.success) {
                    throw new Error(payload.error?.message || 'Failed to load availability');
                }

                const capacity = payload.data.capacity ?? 1;
                const usage = payload.data.usage ?? {};
                const usageEntries = Object.entries(usage).sort(([a], [b]) => a.localeCompare(b));
                const fullyBooked = usageEntries.filter(([, count]) => count >= capacity).map(([date]) => date);
                const partiallyBooked = usageEntries.filter(([, count]) => count < capacity).map(([date, count]) => `${date} (จองแล้ว ${count}/${capacity})`);

                if (fullyBooked.length > 0) {
                    availabilityNotice.textContent = `วันดังกล่าวเต็มแล้ว: ${fullyBooked.join(', ')}`;
                    availabilityNotice.classList.remove('alert-info');
                    availabilityNotice.classList.add('alert-danger');
                } else if (partiallyBooked.length > 0) {
                    availabilityNotice.textContent = `ยังเหลือคิวว่าง — วันที่จองแล้ว: ${partiallyBooked.join(', ')}`;
                    availabilityNotice.classList.remove('alert-danger');
                    availabilityNotice.classList.add('alert-info');
                } else {
                    availabilityNotice.textContent = `ช่วงวันที่เลือกพร้อมให้บริการ (รองรับ ${capacity} คิวต่อวัน)`;
                    availabilityNotice.classList.remove('alert-danger');
                    availabilityNotice.classList.add('alert-info');
                }
            })
            .catch(error => {
                console.error(error);
                availabilityNotice.textContent = 'ไม่สามารถตรวจสอบความพร้อมได้ กรุณาลองใหม่';
                availabilityNotice.classList.remove('alert-info');
                availabilityNotice.classList.add('alert-danger');
            });
    }

    pickupDateInput?.addEventListener('change', () => {
        returnDateInput.min = pickupDateInput.value;
        if (!returnDateInput.value || returnDateInput.value < pickupDateInput.value) {
            returnDateInput.value = pickupDateInput.value;
        }
        updateSummary();
        fetchAvailability();
    });

    returnDateInput?.addEventListener('change', () => {
        updateSummary();
        fetchAvailability();
    });

    [pickupTimeInput, returnTimeInput].forEach(input => {
        if (input) {
            input.addEventListener('change', updateSummary);
        }
    });

    bookingForm?.addEventListener('submit', event => {
        const packageId = selectedPackageIdInput.value;
        const pickupDate = pickupDateInput.value;
        const returnDate = returnDateInput.value;
        const pickupTime = pickupTimeInput.value || DEFAULT_PICKUP_TIME;
        const returnTime = returnTimeInput.value || DEFAULT_RETURN_TIME;
        const location = locationField ? locationField.value.trim() : '';

        if (!packageId) {
            event.preventDefault();
            alert('กรุณาเลือกแพ็คเกจ');
            return;
        }

        if (!pickupDate || !returnDate) {
            event.preventDefault();
            alert('กรุณาเลือกช่วงวันที่รับและคืนอุปกรณ์');
            return;
        }

        if (returnDate < pickupDate) {
            event.preventDefault();
            alert('วันที่คืนต้องไม่ก่อนวันที่รับ');
            return;
        }

        if (!location) {
            event.preventDefault();
            alert('กรุณาระบุสถานที่ใช้งาน');
            return;
        }

        if (pickupDate === returnDate && pickupTime && returnTime && pickupTime >= returnTime) {
            event.preventDefault();
            alert('เวลาคืนต้องมากกว่าเวลารับในวันเดียวกัน');
        }
    });

    if (selectedPackage.id) {
        const card = document.querySelector(`.package-card[data-package-id="${selectedPackage.id}"]`);
        if (card) {
            selectedPackage.price = parseFloat(card.dataset.packagePrice);
            selectedPackage.capacity = parseInt(card.dataset.packageCapacity, 10) || 1;
            card.classList.add('selected');
            bookingForm.style.display = 'grid';
            bookingSummary.style.display = 'block';
            noPackageSelected.style.display = 'none';
            if (capacityField) {
                capacityField.textContent = `รองรับการจองพร้อมกันสูงสุด ${selectedPackage.capacity} คิวต่อวัน`;
            }
            updateSummary();
        }
    }

    if (!pickupTimeInput.value) pickupTimeInput.value = DEFAULT_PICKUP_TIME;
    if (!returnTimeInput.value) returnTimeInput.value = DEFAULT_RETURN_TIME;
    if (pickupDateInput.value) {
        returnDateInput.min = pickupDateInput.value;
    }
</script>

<?php if (GOOGLE_MAPS_API_KEY !== '') : ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= urlencode(GOOGLE_MAPS_API_KEY); ?>&libraries=places&callback=initLocationPicker" async defer></script>
<?php endif; ?>

<?php
render_page_end();
