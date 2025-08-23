<?php
require_once 'includes/auth.php';
require_once 'includes/init_logger.php';
require_once 'includes/timezone.php'; // NEU: Timezone Helper einbinden

$auth = new Auth();

if (isset($_GET['require_license']) && $_GET['require_license'] == '1') {
    // Session-Variablen sollten bereits gesetzt sein (pending_user_id, is_admin)
    header('Location: login.php?require_license=1');
    exit;
}


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
