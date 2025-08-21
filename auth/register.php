<?php
session_start();
require_once '../config/database.php';

// Prüfe ob das Formular abgesendet wurde
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// Validierung
$errors = [];

if (empty($username)) {
    $errors[] = 'Benutzername ist erforderlich.';
} elseif (strlen($username) < 3) {
    $errors[] = 'Benutzername muss mindestens 3 Zeichen lang sein.';
} elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
    $errors[] = 'Benutzername darf nur Buchstaben, Zahlen, _ und - enthalten.';
}

if (empty($email)) {
    $errors[] = 'E-Mail ist erforderlich.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Ungültige E-Mail-Adresse.';
}

if (empty($password)) {
    $errors[] = 'Passwort ist erforderlich.';
} elseif (strlen($password) < 6) {
    $errors[] = 'Passwort muss mindestens 6 Zeichen lang sein.';
}

if ($password !== $password_confirm) {
    $errors[] = 'Passwörter stimmen nicht überein.';
}

if (!empty($errors)) {
    $_SESSION['error'] = implode(' ', $errors);
    header('Location: ../index.php');
    exit;
}

try {
    $db = new Database();

    // Create user with the new method (includes default categories)
    $user_id = $db->createUser($username, $email, $password);

    // Success message und redirect
    $_SESSION['success'] = 'Registrierung erfolgreich! Sie können sich jetzt anmelden.';
    header('Location: ../index.php');
    exit;
} catch (RuntimeException $e) {
    $_SESSION['error'] = 'Benutzername oder E-Mail bereits vergeben.';
    header('Location: ../index.php');
    exit;
} catch (Exception $e) {
    error_log("Registration Error: " . $e->getMessage());
    $_SESSION['error'] = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
    header('Location: ../index.php');
    exit;
}
