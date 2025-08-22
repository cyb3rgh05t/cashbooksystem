<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Require login mit Auth-Klasse
$auth->requireLogin();

// Get current user
$currentUser = $auth->getCurrentUser();
$user_id = $currentUser['id'];

// Database connection
$db = new Database();
$pdo = $db->getConnection();

$expense_id = $_GET['id'] ?? '';

// Validierung
if (empty($expense_id)) {
    $_SESSION['error'] = 'Keine Ausgabe angegeben.';
    header('Location: index.php');
    exit;
}

try {
    // FIXED: Prüfe ob Ausgabe existiert (ohne user_id Filter da gemeinsame Nutzung)
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE t.id = ? AND c.type = 'expense'
    ");
    $stmt->execute([$expense_id]);
    $expense = $stmt->fetch();

    if (!$expense) {
        $_SESSION['error'] = 'Ausgabe nicht gefunden.';
        header('Location: index.php');
        exit;
    }

    // FIXED: Ausgabe löschen (ohne user_id Filter)
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
    $stmt->execute([$expense_id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = sprintf(
            'Ausgabe "%s" (€%s) erfolgreich gelöscht.',
            htmlspecialchars($expense['note'] ?: $expense['category_name']),
            number_format($expense['amount'], 2, ',', '.')
        );
    } else {
        $_SESSION['error'] = 'Ausgabe konnte nicht gelöscht werden.';
    }
} catch (PDOException $e) {
    error_log("Delete Expense Error: " . $e->getMessage());
    $_SESSION['error'] = 'Ein Fehler ist beim Löschen aufgetreten.';
}

// Weiterleitung zur Übersicht
header('Location: index.php');
exit;
