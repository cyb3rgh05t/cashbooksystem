<?php

/**
 * Login-Verarbeitung
 * Speichere als: auth/login.php
 */

session_start();
require_once '../includes/auth.php';
require_once '../includes/timezone.php'; // NEU: Timezone Helper

// Prüfe ob bereits eingeloggt
if ($auth->isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit;
}

// Verarbeite Login-Formular
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Versuche Login
    $result = $auth->login($username, $password);

    if ($result['success']) {
        // Login erfolgreich!

        // NEU: Initialisiere Zeitzone nach erfolgreichem Login
        // Dies lädt die gespeicherte Zeitzone aus der Datenbank
        TimezoneHelper::initializeTimezone();

        // NEU: Setze Flag für Dashboard, dass User gerade eingeloggt hat
        // Damit kann das Dashboard einmalig die Browser-Zeitzone prüfen
        $_SESSION['just_logged_in'] = true;

        // Erfolgs-Nachricht
        $_SESSION['success'] = 'Erfolgreich angemeldet!';

        // Weiterleitung zum Dashboard
        header('Location: ../dashboard.php');
        exit;
    } else {
        // Login fehlgeschlagen
        $_SESSION['error'] = $result['message'];
        header('Location: ../login.php');
        exit;
    }
} else {
    // Keine POST-Daten, zurück zum Login
    header('Location: ../login.php');
    exit;
}
