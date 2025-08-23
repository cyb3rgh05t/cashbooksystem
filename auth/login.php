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
        // Login erfolgreich
        $_SESSION['success'] = 'Erfolgreich angemeldet!';
        header('Location: ../dashboard.php');
        exit;
    } else {
        // Login fehlgeschlagen
        $_SESSION['error'] = $result['message'];

        // WICHTIG: Bei Lizenzfehler mit Parameter weiterleiten
        if (isset($result['require_license']) && $result['require_license']) {
            header('Location: ../login.php?require_license=1');  // <-- Mit Parameter
        } else {
            header('Location: ../login.php');  // <-- Ohne Parameter
        }
        exit;
    }
}
