<?php

/**
 * AJAX License Validation Handler
 * Verarbeitet Lizenz-Aktivierung vom Modal
 */

session_start();
header('Content-Type: application/json');

require_once '../includes/auth.php';
require_once '../config/database.php';

// Prüfe ob User pending ist
$pending_user_id = $_SESSION['pending_user_id'] ?? null;
$is_admin = $_SESSION['is_admin'] ?? false;

if (!$pending_user_id) {
    echo json_encode(['success' => false, 'error' => 'Keine aktive Session']);
    exit;
}

if (!$is_admin) {
    echo json_encode(['success' => false, 'error' => 'Nur Administratoren können Lizenzen aktivieren']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['license_key'])) {
    $licenseKey = trim($_POST['license_key']);

    if (empty($licenseKey)) {
        echo json_encode(['success' => false, 'error' => 'Bitte Lizenzschlüssel eingeben']);
        exit;
    }

    // Validiere Lizenz
    $validation = $auth->validateLicenseKey($licenseKey, $pending_user_id);

    if ($validation['valid']) {
        // Lizenz ist gültig! Logge User ein
        $db = new Database();
        $pdo = $db->getConnection();

        // Hole User-Daten
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$pending_user_id]);
        $user = $stmt->fetch();

        if ($user) {
            // Setze Session-Variablen
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';
            $_SESSION['last_activity'] = time();
            $_SESSION['just_logged_in'] = true;
            $_SESSION['global_license_key'] = $licenseKey;
            $_SESSION['last_license_check'] = time();

            // Speichere Lizenz in Session
            if ($auth->getLicenseHelper()) {
                $auth->getLicenseHelper()->storeLicenseInSession($licenseKey, $validation);
            }

            // Entferne pending flags
            unset($_SESSION['pending_user_id']);
            unset($_SESSION['pending_username']);
            unset($_SESSION['is_admin']);
            unset($_SESSION['license_error']);

            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = datetime('now') WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Session tracking
            $sessionId = session_id();
            $stmt = $pdo->prepare("
                INSERT INTO sessions (session_id, user_id, ip_address, user_agent) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $sessionId,
                $user['id'],
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            echo json_encode(['success' => true, 'message' => 'Lizenz aktiviert']);
            exit;
        }
    }

    echo json_encode([
        'success' => false,
        'error' => $validation['error'] ?? 'Lizenzschlüssel ungültig'
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
