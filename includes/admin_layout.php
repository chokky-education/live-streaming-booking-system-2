<?php
/**
 * Admin layout helpers that reuse the global Finpay theme.
 */

require_once __DIR__ . '/layout.php';

function render_admin_page_start(string $title, array $options = []): void
{
    $active = $options['active'] ?? 'dashboard';

    render_page_start($title, [
        'admin' => true,
        'active' => $active,
        'show_nav' => $options['show_nav'] ?? true,
    ]);

    echo '<section class="section">';
    echo '<div class="page-container admin-layout">';
    render_admin_sidebar($active);
    echo '<main class="admin-main">';
}

function render_admin_sidebar(string $active): void
{
    $links = [
        'dashboard' => ['label' => 'แดชบอร์ด', 'icon' => 'fa-gauge-high', 'href' => '/pages/admin/dashboard.php'],
        'bookings' => ['label' => 'การจอง', 'icon' => 'fa-calendar-days', 'href' => '/pages/admin/bookings.php'],
        'payments' => ['label' => 'การชำระเงิน', 'icon' => 'fa-credit-card', 'href' => '/pages/admin/payments.php'],
        'packages' => ['label' => 'แพ็คเกจ', 'icon' => 'fa-box', 'href' => '/pages/admin/packages.php'],
        'customers' => ['label' => 'ลูกค้า', 'icon' => 'fa-users', 'href' => '/pages/admin/customers.php'],
        'reports' => ['label' => 'รายงาน', 'icon' => 'fa-chart-line', 'href' => '/pages/admin/reports.php'],
    ];

    echo '<aside class="admin-sidebar">';
    echo '<div class="admin-sidebar__brand"><i class="fa-solid fa-wave-square"></i> Admin Control</div>';
    echo '<nav class="admin-nav">';
    foreach ($links as $key => $item) {
        $classes = $key === $active ? 'active' : '';
        $href = htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
        $icon = htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8');
        echo "<a class=\"{$classes}\" href=\"{$href}\"><i class=\"fa-solid {$icon}\"></i><span>{$label}</span></a>";
    }
    echo '</nav>';
    echo '<div class="info-card" style="margin-top:0;">';
    echo '<strong>บัญชีผู้ดูแล</strong>';
    if (isset($_SESSION['username'])) {
        $name = htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $username = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
        echo '<span>' . $name . '</span>';
        echo '<span>@' . $username . '</span>';
    } else {
        echo '<span>ไม่ได้เข้าสู่ระบบ</span>';
    }
    echo '<a class="btn btn-ghost" style="justify-content:center;" href="/pages/logout.php">ออกจากระบบ</a>';
    echo '</div>';
    echo '</aside>';
}

function render_admin_page_end(): void
{
    echo '</main>';
    echo '</div>';
    echo '</section>';
    render_page_end(['show_footer' => false]);
}
