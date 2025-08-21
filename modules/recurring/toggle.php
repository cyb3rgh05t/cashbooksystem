<?php
// modules/recurring/toggle.php
session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once '../../config/database.php';

$db = new Database();
$pdo = $db->getConnection();
$user_id = $_SESSION['user_id'];

$recurring_id = $_GET['id'] ?? '';

// Validierung
if (empty($recurring_id)) {
    $_SESSION['error'] = 'Keine wiederkehrende Transaktion angegeben.';
    header('Location: index.php');
    exit;
}

try {
    // Prüfe ob wiederkehrende Transaktion existiert und dem Benutzer gehört
    $stmt = $pdo->prepare("SELECT * FROM recurring_transactions WHERE id = ?");
    $stmt->execute([$recurring_id]);
    $recurring = $stmt->fetch();

    if (!$recurring) {
        $_SESSION['error'] = 'Wiederkehrende Transaktion nicht gefunden oder keine Berechtigung.';
        header('Location: index.php');
        exit;
    }

    // Status umschalten
    $new_status = $recurring['is_active'] ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE recurring_transactions SET is_active = ? WHERE id = ?");
    $stmt->execute([$new_status, $recurring_id]);

    if ($stmt->rowCount() > 0) {
        $status_text = $new_status ? 'aktiviert' : 'pausiert';
        $_SESSION['success'] = sprintf(
            'Wiederkehrende Transaktion "%s" erfolgreich %s.',
            htmlspecialchars($recurring['note']),
            $status_text
        );
    } else {
        $_SESSION['error'] = 'Status konnte nicht geändert werden.';
    }
} catch (PDOException $e) {
    error_log("Toggle Recurring Error: " . $e->getMessage());
    $_SESSION['error'] = 'Ein Fehler ist beim Statuswechsel aufgetreten.';
}

// Weiterleitung zur Übersicht
header('Location: index.php');
exit;
