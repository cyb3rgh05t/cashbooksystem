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
$pdo = $db->getConnection();

// User ID aus GET-Parameter
$user_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id_to_delete <= 0) {
    $_SESSION['error'] = 'Ungültige Benutzer-ID.';
    header('Location: ../settings.php');
    exit;
}

try {
    // Hole User-Daten
    $stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmt->execute([$user_id_to_delete]);
    $userToDelete = $stmt->fetch();

    if (!$userToDelete) {
        $_SESSION['error'] = 'Benutzer nicht gefunden.';
        header('Location: ../settings.php');
        exit;
    }

    // Prüfe ob es der letzte Admin ist
    if ($userToDelete['role'] === 'admin') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin' AND is_active = 1");
        $stmt->execute();
        $adminCount = $stmt->fetch()['admin_count'];

        if ($adminCount <= 1) {
            $_SESSION['error'] = 'Der letzte Administrator kann nicht gelöscht werden.';
            header('Location: ../settings.php');
            exit;
        }
    }

    // Starte Transaktion für sicheres Löschen
    $pdo->beginTransaction();

    // WICHTIG: Zuerst system_logs Einträge löschen (da kein CASCADE)
    $stmt = $pdo->prepare("DELETE FROM system_logs WHERE user_id = ?");
    $stmt->execute([$user_id_to_delete]);

    // Lösche Sessions des Users
    $stmt = $pdo->prepare("DELETE FROM sessions WHERE user_id = ?");
    $stmt->execute([$user_id_to_delete]);

    // Lösche den User (CASCADE löscht automatisch: transactions, categories, investments, recurring_transactions)
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id_to_delete]);

    $pdo->commit();

    $_SESSION['success'] = "Benutzer '{$userToDelete['username']}' wurde erfolgreich gelöscht.";

    // Logge die erfolgreiche Löschung (nur wenn Logger existiert)
    if (isset($logger)) {
        // Nutze den aktuellen Admin User für den Log, nicht den gelöschten User
        $logger->success(
            "User deleted: {$userToDelete['username']} (ID: {$user_id_to_delete})",
            $currentUser['id'],
            'USER_MANAGEMENT'
        );
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error'] = 'Fehler beim Löschen des Users: ' . $e->getMessage();
    error_log("Delete User Error: " . $e->getMessage());

    // Logge den fehlgeschlagenen Versuch
    if (isset($logger)) {
        $logger->warning(
            "Failed to delete user: ID {$user_id_to_delete} - " . $e->getMessage(),
            $currentUser['id'],
            'USER_MANAGEMENT'
        );
    }
}

// Zurück zu den Settings
header('Location: ../settings.php');
exit;
