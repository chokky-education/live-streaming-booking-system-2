<?php
/**
 * Admin Index - Redirect to Dashboard
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once '../../includes/config.php';

// ตรวจสอบสิทธิ์ admin
require_admin();

// Redirect ไปหน้า dashboard
redirect('dashboard.php');
?>