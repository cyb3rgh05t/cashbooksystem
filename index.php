<?php
require_once 'includes/auth.php';
require_once 'includes/init_logger.php';
require_once 'includes/timezone.php'; // NEU: Timezone Helper einbinden

$auth = new Auth();

// Prüfe ob User eingeloggt ist
if ($auth->isLoggedIn()) {
    // NEU: Initialisiere Zeitzone für eingeloggten User
    TimezoneHelper::initializeTimezone();

    // Weiterleitung zum Dashboard
    header('Location: dashboard.php');
} else {
    // Weiterleitung zum Login
    header('Location: login.php');
}
exit;
