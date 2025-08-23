<?php

/**
 * Lizenz-Aktivierungsseite
 * Wird angezeigt wenn User keine gültige Lizenz hat
 */

require_once 'includes/auth.php';
require_once 'config/database.php';

// Prüfe ob User von Login kommt
$pending_user_id = $_SESSION['pending_user_id'] ?? null;
$pending_username = $_SESSION['pending_username'] ?? null;
$is_admin = $_SESSION['is_admin'] ?? false;
$error_message = $_SESSION['license_error'] ?? '';
$success_message = '';

// Wenn kein pending user, redirect zum Login
if (!$pending_user_id) {
    header('Location: index.php');
    exit;
}

// NUR ADMINS dürfen neue Lizenzen aktivieren bei globaler Lizenz!
if (!$is_admin) {
    $error_message = 'Nur Administratoren können die Systemlizenz aktivieren. Bitte kontaktieren Sie Ihren Administrator.';
}

// Handle Lizenz-Aktivierung (NUR für Admins!)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['license_key']) && $is_admin) {
    $licenseKey = trim($_POST['license_key']);

    if (!empty($licenseKey)) {
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

                // Speichere Lizenz in Session
                $auth->getLicenseHelper()->storeLicenseInSession($licenseKey, $validation);

                // Entferne pending flags
                unset($_SESSION['pending_user_id']);
                unset($_SESSION['pending_username']);
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

                $_SESSION['success'] = 'Lizenz erfolgreich aktiviert! Willkommen im System.';

                // Redirect zum Dashboard
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $error_message = 'Lizenzschlüssel ungültig: ' . ($validation['error'] ?? 'Unbekannter Fehler');
        }
    } else {
        $error_message = 'Bitte geben Sie einen Lizenzschlüssel ein.';
    }
}

// Generiere Hardware-ID für Anzeige
$hardwareId = $auth->getLicenseHelper() ? $auth->getLicenseHelper()->generateHardwareId() : 'N/A';
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lizenz aktivieren - Cashbook</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --error: #ef4444;
            --success: #22c55e;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            width: 100%;
            max-width: 480px;
            padding: 40px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo i {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        h1 {
            color: var(--gray-900);
            font-size: 28px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 8px;
        }

        .subtitle {
            color: var(--gray-500);
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .user-info {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .user-info-label {
            color: var(--gray-500);
            font-size: 12px;
            margin-bottom: 4px;
        }

        .user-info-value {
            color: var(--gray-900);
            font-weight: 600;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--gray-700);
            font-weight: 500;
            font-size: 14px;
        }

        input[type="text"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.2s;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        input[type="text"]::placeholder {
            color: var(--gray-400);
        }

        .hardware-id {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 24px;
        }

        .hardware-id-label {
            color: var(--gray-500);
            font-size: 12px;
            margin-bottom: 6px;
        }

        .hardware-id-value {
            font-family: 'Courier New', monospace;
            color: var(--gray-700);
            font-size: 14px;
            word-break: break-all;
            background: white;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid var(--gray-200);
        }

        .btn {
            width: 100%;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
            margin-top: 12px;
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #fef2f2;
            color: var(--error);
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: var(--success);
            border: 1px solid #bbf7d0;
        }

        .alert i {
            font-size: 18px;
        }

        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 16px;
            margin-top: 24px;
        }

        .info-box h3 {
            color: #1e40af;
            font-size: 14px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-box ul {
            margin: 0;
            padding-left: 20px;
            color: #3730a3;
            font-size: 13px;
            line-height: 1.6;
        }

        .divider {
            border-top: 1px solid var(--gray-200);
            margin: 24px 0;
        }

        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo">
            <i class="fas fa-key"></i>
        </div>

        <h1>Lizenz aktivieren</h1>
        <p class="subtitle">Bitte geben Sie Ihren Lizenzschlüssel ein, um fortzufahren</p>

        <?php if ($pending_username): ?>
            <div class="user-info">
                <div class="user-info-label">Angemeldet als:</div>
                <div class="user-info-value">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($pending_username) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($success_message) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$is_admin): ?>
            <div class="alert alert-error">
                <i class="fas fa-lock"></i>
                <span>Nur Administratoren können die Systemlizenz verwalten. Bitte kontaktieren Sie Ihren Administrator.</span>
            </div>

            <div class="info-box" style="background: #fef2f2; border-color: #fecaca;">
                <h3 style="color: #dc2626;"><i class="fas fa-exclamation-triangle"></i> Administrator kontaktieren</h3>
                <p style="color: #7f1d1d;">
                    Das System benötigt eine gültige Lizenz. Bitte bitten Sie einen Administrator,
                    sich einzuloggen und die Systemlizenz zu aktivieren.
                </p>
            </div>
        <?php else: ?>

            <form method="POST">
                <div class="form-group">
                    <label for="license_key">
                        <i class="fas fa-certificate"></i> Lizenzschlüssel
                    </label>
                    <input type="text"
                        id="license_key"
                        name="license_key"
                        placeholder="KFZ####-####-####-####"
                        style="text-transform: uppercase;"
                        required
                        autofocus>
                </div>

                <div class="hardware-id">
                    <div class="hardware-id-label">
                        <i class="fas fa-fingerprint"></i> Ihre Hardware-ID:
                    </div>
                    <div class="hardware-id-value"><?= htmlspecialchars($hardwareId) ?></div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Lizenz aktivieren
                </button>
            </form>

        <?php endif; ?>

        <form method="GET" action="index.php">
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Zurück zum Login
            </button>
        </form>

        <?php if ($is_admin): ?>
            <div class="info-box">
                <h3><i class="fas fa-info-circle"></i> Wichtige Informationen:</h3>
                <ul>
                    <li><strong>Sie aktivieren die SYSTEM-LIZENZ</strong></li>
                    <li>Diese Lizenz gilt für ALLE Benutzer der Installation</li>
                    <li>Die Lizenz wird an diese Installation gebunden</li>
                    <li>Nur Administratoren können die Lizenz ändern</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>