<?php

/**
 * Registration Handler mit Auth-Klasse
 * Für Cashbook System
 */

require_once '../includes/auth.php';

// Prüfe ob das Formular abgesendet wurde
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$starting_balance = floatval($_POST['starting_balance'] ?? 0);

// Validierung
if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
    $_SESSION['error'] = 'Bitte alle Felder ausfüllen.';
    header('Location: ../index.php');
    exit;
}

if ($password !== $password_confirm) {
    $_SESSION['error'] = 'Passwörter stimmen nicht überein.';
    header('Location: ../index.php');
    exit;
}

if (strlen($password) < 6) {
    $_SESSION['error'] = 'Passwort muss mindestens 6 Zeichen lang sein.';
    header('Location: ../index.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
    header('Location: ../index.php');
    exit;
}

// Registrierung mit Auth-Klasse
$result = $auth->register([
    'username' => $username,
    'email' => $email,
    'password' => $password,
    'starting_balance' => $starting_balance
]);

if ($result['success']) {
    $_SESSION['success'] = $result['message'] . ' Sie können sich jetzt anmelden.';
    header('Location: ../index.php');
    exit;
} else {
    $_SESSION['error'] = $result['message'];
    header('Location: ../index.php');
    exit;
}
