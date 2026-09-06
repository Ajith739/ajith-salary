<?php
/**
 * Entry point — redirect to dashboard or login
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();

if (isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;