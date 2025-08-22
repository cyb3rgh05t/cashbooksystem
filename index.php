<?php
require_once 'includes/auth.php';
require_once 'includes/init_logger.php';

$auth = new Auth();

// Prüfe ob User eingeloggt ist
if ($auth->isLoggedIn()) {
    // Weiterleitung zum Dashboard
    header('Location: dashboard.php');
} else {
    // Weiterleitung zum Login
    header('Location: login.php');
}
exit;
