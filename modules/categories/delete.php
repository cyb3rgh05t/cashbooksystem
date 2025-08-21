<?php
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

$category_id = $_GET['id'] ?? '';

// Validierung
if (empty($category_id)) {
    $_SESSION['error'] = 'Keine Kategorie angegeben.';
    header('Location: index.php');
    exit;
}

try {
    // Prüfe ob Kategorie existiert und dem Benutzer gehört
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();

    if (!$category) {
        $_SESSION['error'] = 'Kategorie nicht gefunden oder keine Berechtigung.';
        header('Location: index.php');
        exit;
    }

    // Prüfe ob Kategorie in Transaktionen verwendet wird
    $stmt = $pdo->prepare("SELECT COUNT(*) as transaction_count FROM transactions WHERE category_id = ?");
    $stmt->execute([$category_id]);
    $transaction_count = $stmt->fetchColumn();

    if ($transaction_count > 0) {
        $_SESSION['error'] = sprintf(
            'Kategorie "%s" kann nicht gelöscht werden, da sie in %d Transaktionen verwendet wird. Lösche zuerst alle zugehörigen Transaktionen.',
            htmlspecialchars($category['name']),
            $transaction_count
        );
        header('Location: index.php');
        exit;
    }

    // Kategorie löschen
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = sprintf(
            'Kategorie "%s" erfolgreich gelöscht.',
            htmlspecialchars($category['name'])
        );
    } else {
        $_SESSION['error'] = 'Kategorie konnte nicht gelöscht werden.';
    }
} catch (PDOException $e) {
    error_log("Delete Category Error: " . $e->getMessage());
    $_SESSION['error'] = 'Ein Fehler ist beim Löschen aufgetreten.';
}

// Weiterleitung zur Übersicht
header('Location: index.php');
exit;
