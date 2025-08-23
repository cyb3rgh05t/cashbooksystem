<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/init_logger.php';
require_once '../../includes/role_check.php';

// Require login mit Auth-Klasse
$auth->requireLogin();

// Get current user
$currentUser = $auth->getCurrentUser();
restrictViewerAccess($currentUser);
$user_id = $currentUser['id'];

// Database connection
$db = new Database();
$pdo = $db->getConnection();

$transaction_id = $_GET['id'] ?? '';

// Validierung
if (empty($transaction_id)) {
    $_SESSION['error'] = 'Keine Transaktion angegeben.';
    header('Location: index.php');
    exit;
}

try {
    // Prüfe ob Transaktion existiert und vom Typ debt_in oder debt_out ist
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name, c.type as category_type
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE t.id = ? AND c.type IN ('debt_in', 'debt_out')
    ");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch();

    if (!$transaction) {
        $_SESSION['error'] = 'Transaktion nicht gefunden oder keine Berechtigung.';
        header('Location: index.php');
        exit;
    }

    // Transaktion löschen
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
    $stmt->execute([$transaction_id]);

    if ($stmt->rowCount() > 0) {
        // Success message abhängig vom Typ
        $transaction_type_text = $transaction['category_type'] === 'debt_out' ? 'Verliehenes Geld' : 'Erhaltenes Geld';

        $_SESSION['success'] = sprintf(
            '%s "%s" (€%s) erfolgreich gelöscht.',
            $transaction_type_text,
            htmlspecialchars($transaction['note']),
            number_format($transaction['amount'], 2, ',', '.')
        );
    } else {
        $_SESSION['error'] = 'Transaktion konnte nicht gelöscht werden.';
    }
} catch (PDOException $e) {
    error_log("Delete debt transaction error: " . $e->getMessage());
    $_SESSION['error'] = 'Ein Fehler ist beim Löschen aufgetreten.';
}

// Weiterleitung zur Übersicht
header('Location: index.php');
exit;
