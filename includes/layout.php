<?php
/**
 * Layout helper utilities for consistent Finpay-inspired UI
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

/**
 * Render the initial page chrome (doctype, head, nav, opening wrappers)
 */
function render_page_start(string $title, array $options = []): void
{
    $lang = $options['lang'] ?? 'th';
    $body_class = trim('page-shell ' . ($options['body_class'] ?? ''));
    $show_nav = $options['show_nav'] ?? true;

    echo "<!DOCTYPE html>\n";
    echo "<html lang=\"{$lang}\">\n";
    echo "<head>\n";
    echo "    <meta charset=\"UTF-8\">\n";
    echo "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
    echo "    <title>{$title}</title>\n";
    echo "    <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css\" integrity=\"sha512-yH7QQ+uJgG1CEV4VpWw2X2gYdEx+kt1lEuzMdGII4XESyqCCpt5TR1+t0NenE2no0RvrRZtGJPD7WuaN9Y0jQw==\" crossorigin=\"anonymous\" referrerpolicy=\"no-referrer\" />\n";
    echo "    <link rel=\"stylesheet\" href=\"/assets/css/theme.css\">\n";
    if (!empty($options['extra_css']) && is_array($options['extra_css'])) {
        foreach ($options['extra_css'] as $href) {
            $escaped = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
            echo "    <link rel=\"stylesheet\" href=\"{$escaped}\">\n";
        }
    }
    echo "</head>\n";
    echo "<body class=\"{$body_class}\">\n";

    if ($show_nav) {
        render_primary_nav($options);
    }

    echo "<div class=\"page-content\">\n";
}

/**
 * Render the top navigation bar
 */
function render_primary_nav(array $options = []): void
{
    $active = $options['active'] ?? null;
    $is_admin_area = $options['admin'] ?? false;

    if ($is_admin_area) {
        $links = [
            ['key' => 'dashboard', 'label' => 'แดชบอร์ด', 'href' => '/pages/admin/dashboard.php'],
            ['key' => 'bookings', 'label' => 'การจอง', 'href' => '/pages/admin/bookings.php'],
            ['key' => 'packages', 'label' => 'แพ็คเกจ', 'href' => '/pages/admin/packages.php'],
            ['key' => 'payments', 'label' => 'การชำระเงิน', 'href' => '/pages/admin/payments.php'],
            ['key' => 'customers', 'label' => 'ลูกค้า', 'href' => '/pages/admin/customers.php'],
        ];
    } else {
        $links = [
            ['key' => 'home', 'label' => 'หน้าแรก', 'href' => '/index.php'],
            ['key' => 'packages', 'label' => 'แพ็คเกจ', 'href' => '/pages/booking.php'],
        ];

        if (is_logged_in()) {
            $links[] = ['key' => 'profile', 'label' => 'บัญชีของฉัน', 'href' => '/pages/profile.php'];
        } else {
            $links[] = ['key' => 'learn', 'label' => 'วิธีใช้งาน', 'href' => '/index.php#how-it-works'];
        }

        $links[] = ['key' => 'support', 'label' => 'ฝ่ายบริการ', 'href' => '/index.php#support'];
    }

    echo '<header class="primary-nav">';
    echo '<div class="page-container primary-nav__inner">';
    echo '<a class="primary-nav__brand" href="' . ($is_admin_area ? '/pages/admin/dashboard.php' : '/index.php') . '">';
    echo '<span class="brand-mark"><i class="fa-solid fa-wave-square"></i></span>';
    echo '<span>' . htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '</a>';

    echo '<nav class="primary-nav__links">';
    foreach ($links as $item) {
        $classes = [];
        if ($active === $item['key']) {
            $classes[] = 'active';
        }
        $class_attr = $classes ? ' class="' . implode(' ', $classes) . '"' : '';
        $href = htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
        echo "<a{$class_attr} href=\"{$href}\">{$label}</a>";
    }
    echo '</nav>';

    echo '<div class="primary-nav__actions">';
    if (is_logged_in()) {
        $username = htmlspecialchars($_SESSION['username'] ?? 'บัญชี', ENT_QUOTES, 'UTF-8');
        echo '<span class="nav-username">' . $username . '</span>';
        echo '<a class="btn btn-ghost" href="' . ($is_admin_area ? '/pages/logout.php' : '/pages/logout.php') . '">ออกจากระบบ</a>';
    } else {
        echo '<a class="btn btn-ghost" href="/pages/login.php">เข้าสู่ระบบ</a>';
        echo '<a class="btn btn-primary" href="/pages/register.php">เริ่มต้นใช้งาน</a>';
    }
    echo '</div>';

    echo '</div>';
    echo '</header>';
}

/**
 * Render the marketing footer
 */
function render_footer(array $options = []): void
{
    if (!($options['show_footer'] ?? true)) {
        return;
    }

    echo '<footer class="footer" id="support">';
    echo '<div class="page-container">';
    echo '<div class="footer__grid">';
    echo '<div>';
    echo '<div class="footer__brand">' . htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') . '</div>';
    echo '<p>โซลูชันจัดการการจองและการชำระเงินสำหรับธุรกิจไลฟ์สตรีมมิ่งของคุณ</p>';
    echo '</div>';

    echo '<div>';
    echo '<h4>โซลูชัน</h4>';
    echo '<div class="footer__links">';
    echo '<a href="/pages/booking.php">ระบบจอง</a>';
    echo '<a href="/pages/payment.php">ระบบชำระเงิน</a>';
    echo '<a href="/pages/profile.php">แดชบอร์ดลูกค้า</a>';
    echo '</div>';
    echo '</div>';

    echo '<div>';
    echo '<h4>บริษัท</h4>';
    echo '<div class="footer__links">';
    echo '<a href="/index.php#how-it-works">วิธีใช้งาน</a>';
    echo '<a href="mailto:support@example.com">ติดต่อเรา</a>';
    echo '<a href="/docs/prd.md">เอกสาร</a>';
    echo '</div>';
    echo '</div>';

    echo '<div>';
    echo '<h4>ติดตาม</h4>';
    echo '<div class="footer__links">';
    echo '<a href="#">Facebook</a>';
    echo '<a href="#">YouTube</a>';
    echo '<a href="#">Line Official</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="footer__bottom">© ' . date('Y') . ' ' . htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') . '. สงวนลิขสิทธิ์.</div>';
    echo '</div>';
    echo '</footer>';
}

/**
 * Close the page wrapper and body
 */
function render_page_end(array $options = []): void
{
    echo '</div>'; // page-content

    render_footer($options);

    echo '</body>\n</html>';
}
