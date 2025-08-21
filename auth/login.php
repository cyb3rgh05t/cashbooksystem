<?php
session_start();
require_once '../config/database.php';

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

try {
    $db = new Database();

    // Authenticate user with the new method
    $user = $db->authenticateUser($username, $password);

    if (!$user) {
        $_SESSION['error'] = 'Benutzername oder Passwort ungültig.';
        header('Location: ../index.php');
        exit;
    }

    // Login erfolgreich - Session setzen
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];

    // Weiterleitung zum Dashboard
    header('Location: ../dashboard.php');
    exit;
} catch (Exception $e) {
    error_log("Login Error: " . $e->getMessage());
    $_SESSION['error'] = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
    header('Location: ../index.php');
    exit;
}
