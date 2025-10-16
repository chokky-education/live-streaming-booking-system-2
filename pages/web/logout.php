<?php
/**
 * หน้าออกจากระบบ
 * ระบบจองอุปกรณ์ Live Streaming
 */

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/includes/config.php';
require_once $rootPath . '/includes/functions.php';

// Log การออกจากระบบ
if (is_logged_in()) {
    log_event("User {$_SESSION['username']} logged out", 'INFO');
}

// ทำลาย session
session_destroy();

// Redirect ไปหน้าแรก
redirect('/index.php');
?>
