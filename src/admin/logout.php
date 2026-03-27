<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();
logout();
header('Location: ' . APP_URL . '/admin/login.php');
exit;
