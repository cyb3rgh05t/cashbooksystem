<?php
require_once 'auth.php';
require_once '../config/database.php';
require_once 'init_logger.php';

// Require login
$auth->requireLogin();

// Get current user
$currentUser = $auth->getCurrentUser();

// Nur Admins dürfen diese Seite aufrufen
if ($currentUser['role'] !== 'admin') {
    $_SESSION['error'] = 'Keine Berechtigung für diese Aktion.';
    header('Location: ../settings.php');
    exit;
}

// Database connection
$db = new Database();

// User ID aus GET-Parameter
$user_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id_to_delete <= 0) {
    $_SESSION['error'] = 'Ungültige Benutzer-ID.';
    header('Location: ../settings.php');
    exit;
}

// Löschung durchführen
$result = $db->deleteUser($user_id_to_delete, $currentUser['id']);

if ($result['success']) {
    $_SESSION['success'] = $result['message'];

    // Logge die erfolgreiche Löschung
    if (isset($logger)) {
        $logger->success(
            "User deleted: ID {$user_id_to_delete}",
            $currentUser['id'],
            'USER_MANAGEMENT'
        );
    }
} else {
    $_SESSION['error'] = $result['message'];

    // Logge den fehlgeschlagenen Versuch
    if (isset($logger)) {
        $logger->warning(
            "Failed to delete user: ID {$user_id_to_delete} - " . $result['message'],
            $currentUser['id'],
            'USER_MANAGEMENT'
        );
    }
}

// Zurück zu den Settings
header('Location: ../settings.php');
exit;
