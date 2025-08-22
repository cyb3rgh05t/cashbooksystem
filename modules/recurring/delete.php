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

$recurring_id = $_GET['id'] ?? '';

// Validierung
if (empty($recurring_id)) {
    $_SESSION['error'] = 'Keine wiederkehrende Transaktion angegeben.';
    header('Location: index.php');
    exit;
}

try {
    // Prüfe ob wiederkehrende Transaktion existiert und dem Benutzer gehört
    $stmt = $pdo->prepare("
    SELECT rt.*, c.name as category_name
    FROM recurring_transactions rt
    JOIN categories c ON rt.category_id = c.id
    WHERE rt.id = ?
");
    $stmt->execute([$recurring_id]);
    $recurring = $stmt->fetch();

    if (!$recurring) {
        $_SESSION['error'] = 'Wiederkehrende Transaktion nicht gefunden oder keine Berechtigung.';
        header('Location: index.php');
        exit;
    }

    $pdo->beginTransaction();
    try {
        // Entferne Verknüpfung zu bestehenden Transaktionen (setze recurring_transaction_id auf NULL)
        $stmt = $pdo->prepare("
    UPDATE transactions 
    SET recurring_transaction_id = NULL 
    WHERE recurring_transaction_id = ?
");
        $stmt->execute([$recurring_id]);

        // Lösche wiederkehrende Transaktion
        $stmt = $pdo->prepare("DELETE FROM recurring_transactions WHERE id = ?");
        $stmt->execute([$recurring_id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = sprintf(
                'Wiederkehrende Transaktion "%s" erfolgreich gelöscht.',
                htmlspecialchars($recurring['note'])
            );
        } else {
            $_SESSION['error'] = 'Wiederkehrende Transaktion konnte nicht gelöscht werden.';
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (PDOException $e) {
    error_log("Delete Recurring Error: " . $e->getMessage());
    $_SESSION['error'] = 'Ein Fehler ist beim Löschen aufgetreten.';
}

// Weiterleitung zur Übersicht
header('Location: index.php');
exit;
