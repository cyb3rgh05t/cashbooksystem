<?php

/**
 * Login Handler mit neuer Auth-Klasse
 * Updated für Cashbook System
 */

require_once '../includes/auth.php';

// Prüfe ob das Formular abgesendet wurde
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validierung
if (empty($username) || empty($password)) {
    $_SESSION['error'] = 'Bitte alle Felder ausfüllen.';
    header('Location: ../index.php');
    exit;
}

// Login mit Auth-Klasse
$result = $auth->login($username, $password);

if ($result['success']) {
    // Check for redirect URL
    $redirect = $_SESSION['redirect_after_login'] ?? '../dashboard.php';
    unset($_SESSION['redirect_after_login']);

    header('Location: ' . $redirect);
    exit;
} else {
    $_SESSION['error'] = $result['message'];
    header('Location: ../index.php');
    exit;
}
