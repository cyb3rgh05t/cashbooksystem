<?php

session_start();
require_once '../includes/timezone.php';

// Nur JSON-Requests akzeptieren
header('Content-Type: application/json');

// Prüfe ob User eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Hole die Zeitzone aus dem Request
$input = json_decode(file_get_contents('php://input'), true);
$timezone = $input['timezone'] ?? null;
$forceUpdate = $input['force'] ?? false; // Erlaubt manuelles Überschreiben

if (!$timezone) {
    echo json_encode(['success' => false, 'error' => 'No timezone provided']);
    exit;
}

// Wenn force=true, dann ist es eine manuelle Einstellung aus den Settings
if ($forceUpdate) {
    // Manuelle Einstellung - wird IMMER gespeichert
    if (TimezoneHelper::setTimezone($timezone, true)) {
        echo json_encode([
            'success' => true,
            'timezone' => $timezone,
            'current_time' => TimezoneHelper::getCurrentUserTime('d.m.Y H:i:s'),
            'manually_set' => true
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid timezone']);
    }
} else {
    // Automatische Erkennung - nur wenn noch nicht manuell gesetzt
    if (TimezoneHelper::setTimezoneFromBrowser($timezone)) {
        echo json_encode([
            'success' => true,
            'timezone' => $timezone,
            'current_time' => TimezoneHelper::getCurrentUserTime('d.m.Y H:i:s'),
            'manually_set' => false
        ]);
    } else {
        // Wurde nicht gesetzt (wahrscheinlich weil manuell eingestellt)
        echo json_encode([
            'success' => false,
            'error' => 'Timezone not updated - manual setting exists',
            'current_timezone' => TimezoneHelper::getCurrentTimezone(),
            'manually_set' => true
        ]);
    }
}
