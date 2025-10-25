<?php
require_once '../includes/auth.php';
require_once '../includes/two_factor.class.php';
require_once '../config/database.php';

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Initialize 2FA helper
$db = new Database();
$pdo = $db->getConnection();
$twoFactor = new TwoFactorAuth($pdo, $auth->getLogger());

// Handle form submissions
$message = '';
$messageType = '';
$qrCodeUrl = '';
$secret = '';
$backupCodes = [];
$showSetup = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        // Generate new secret and show QR code
        $secret = $twoFactor->generateSecret();
        $qrCodeUrl = $twoFactor->getQRCodeUrl($username, $secret);
        $showSetup = true;
        $_SESSION['temp_2fa_secret'] = $secret;
    } elseif ($action === 'verify') {
        // Verify code and enable 2FA
        $code = $_POST['code'] ?? '';
        $secret = $_SESSION['temp_2fa_secret'] ?? '';

        if (!$secret) {
            $message = 'Sitzung abgelaufen. Bitte erneut versuchen.';
            $messageType = 'error';
        } elseif (empty($code)) {
            $message = 'Bitte Code eingeben.';
            $messageType = 'error';
            $qrCodeUrl = $twoFactor->getQRCodeUrl($username, $secret);
            $showSetup = true;
        } elseif ($twoFactor->verifyCode($secret, $code)) {
            // Code is valid, enable 2FA
            $result = $twoFactor->enable($userId, $secret);

            if ($result['success']) {
                $backupCodes = $result['backup_codes'];
                $message = '2FA erfolgreich aktiviert! Bitte speichern Sie Ihre Backup-Codes sicher.';
                $messageType = 'success';
                unset($_SESSION['temp_2fa_secret']);
            } else {
                $message = $result['message'] ?? 'Fehler beim Aktivieren von 2FA.';
                $messageType = 'error';
            }
        } else {
            $message = 'Ungültiger Code. Bitte erneut versuchen.';
            $messageType = 'error';
            $qrCodeUrl = $twoFactor->getQRCodeUrl($username, $secret);
            $showSetup = true;
        }
    } elseif ($action === 'disable') {
        // Disable 2FA
        $code = $_POST['code'] ?? '';
        $userSecret = $twoFactor->getSecret($userId);

        if (!$userSecret) {
            $message = '2FA ist nicht aktiviert.';
            $messageType = 'error';
        } elseif (empty($code)) {
            $message = 'Bitte Code eingeben zur Bestätigung.';
            $messageType = 'error';
        } elseif ($twoFactor->verifyCode($userSecret, $code) || $twoFactor->verifyBackupCode($userId, $code)) {
            if ($twoFactor->disable($userId)) {
                $message = '2FA wurde deaktiviert.';
                $messageType = 'success';
            } else {
                $message = 'Fehler beim Deaktivieren von 2FA.';
                $messageType = 'error';
            }
        } else {
            $message = 'Ungültiger Code.';
            $messageType = 'error';
        }
    }
}

$is2FAEnabled = $twoFactor->isEnabled($userId);

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zwei-Faktor-Authentifizierung - Cashbook System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/settings.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        .two-factor-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            margin-left: 10px;
        }

        .status-badge.enabled {
            background: var(--clr-accent-green-a10);
            color: var(--clr-accent-green);
        }

        .status-badge.disabled {
            background: var(--clr-surface-a10);
            color: var(--clr-on-surface-variant);
        }

        .qr-code-container {
            text-align: center;
            padding: 30px;
            background: var(--clr-surface-a5);
            border-radius: 12px;
            margin: 20px 0;
        }

        .qr-code-container img {
            border: 4px solid white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .secret-key {
            background: var(--clr-surface-a10);
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 1.1em;
            letter-spacing: 2px;
            text-align: center;
            margin: 20px 0;
            word-break: break-all;
        }

        .backup-codes {
            background: var(--clr-surface-a5);
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .backup-codes-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 15px;
        }

        .backup-code {
            background: white;
            padding: 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 1.1em;
            text-align: center;
            letter-spacing: 1px;
            border: 2px solid var(--clr-surface-a20);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .alert.success {
            background: var(--clr-accent-green-a10);
            color: var(--clr-accent-green);
            border-left: 4px solid var(--clr-accent-green);
        }

        .alert.error {
            background: var(--clr-accent-red-a10);
            color: var(--clr-accent-red);
            border-left: 4px solid var(--clr-accent-red);
        }

        .alert.warning {
            background: var(--clr-accent-orange-a10);
            color: var(--clr-accent-orange);
            border-left: 4px solid var(--clr-accent-orange);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: var(--clr-primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--clr-primary-dark);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--clr-accent-red);
            color: white;
        }

        .btn-danger:hover {
            background: #c53030;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--clr-surface-a10);
            color: var(--clr-on-surface);
        }

        .btn-secondary:hover {
            background: var(--clr-surface-a20);
        }

        .code-input {
            font-size: 1.5em;
            text-align: center;
            letter-spacing: 8px;
            padding: 15px;
            border: 2px solid var(--clr-surface-a20);
            border-radius: 8px;
            width: 100%;
            max-width: 300px;
            margin: 20px auto;
            display: block;
            font-family: 'Courier New', monospace;
        }

        .form-section {
            background: var(--clr-surface);
            padding: 30px;
            border-radius: 12px;
            margin: 20px 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .instructions {
            background: var(--clr-surface-a5);
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid var(--clr-primary);
        }

        .instructions ol {
            margin: 10px 0;
            padding-left: 20px;
        }

        .instructions li {
            margin: 8px 0;
        }
    </style>
</head>

<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a class="sidebar-logo">
                    <img src="../../assets/images/logo.png" alt="Cashbook Logo" class="sidebar-logo-image">
                </a>
                <p class="sidebar-welcome">Willkommen, <?= htmlspecialchars($username) ?></p>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="../../dashboard.php"><i class="fa-solid fa-house"></i>&nbsp;&nbsp;Dashboard</a></li>
                    <li><a href="../expenses/index.php"><i class="fa-solid fa-money-bill-wave"></i>&nbsp;&nbsp;Ausgaben</a></li>
                    <li><a href="../income/index.php"><i class="fa-solid fa-sack-dollar"></i>&nbsp;&nbsp;Einnahmen</a></li>
                    <li><a href="../debts/index.php"><i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden</a></li>
                    <li><a href="../recurring/index.php"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend</a></li>
                    <li><a href="../investments/index.php"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto</a></li>
                    <li><a href="../categories/index.php"><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorien</a></li>
                    <li>
                        <a style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;" href="../../settings.php">
                            <i class="fa-solid fa-gear"></i>&nbsp;&nbsp;Einstellungen
                        </a>
                    </li>
                    <li><a href="#" class="active"><i class="fa-solid fa-shield-alt"></i>&nbsp;&nbsp;Zwei-Faktor-Auth</a></li>
                    <li>
                        <a href="../../logout.php"><i class="fa-solid fa-right-from-bracket"></i>&nbsp;&nbsp;Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>
                        Zwei-Faktor-Authentifizierung
                        <span class="status-badge <?php echo $is2FAEnabled ? 'enabled' : 'disabled'; ?>">
                            <?php echo $is2FAEnabled ? 'Aktiviert' : 'Deaktiviert'; ?>
                        </span>
                    </h1>
                    <p style="color: var(--clr-on-surface-variant); margin-top: 8px;">Erhöhe die Sicherheit deines Kontos</p>
                </div>
            </div>

            <div class="two-factor-container">

                <?php if ($message): ?>
                    <div class="alert <?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($backupCodes)): ?>
                    <div class="backup-codes">
                        <h3><i class="fas fa-key"></i> Ihre Backup-Codes</h3>
                        <div class="alert warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Bewahren Sie diese Codes sicher auf! Sie können jeden Code nur einmal verwenden.</span>
                        </div>
                        <div class="backup-codes-grid">
                            <?php foreach ($backupCodes as $code): ?>
                                <div class="backup-code"><?php echo htmlspecialchars($code); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 20px; text-align: center;">
                            <button class="btn btn-secondary" onclick="window.print();">
                                <i class="fas fa-print"></i> Codes drucken
                            </button>
                            <a href="../settings.php" class="btn btn-primary">
                                <i class="fas fa-check"></i> Fertig
                            </a>
                        </div>
                    </div>
                <?php elseif ($showSetup): ?>
                    <div class="form-section">
                        <h2><i class="fas fa-qrcode"></i> QR-Code scannen</h2>

                        <div class="instructions">
                            <h4>Anleitung:</h4>
                            <ol>
                                <li>Installieren Sie eine Authenticator-App (z.B. Google Authenticator, Microsoft Authenticator, Authy)</li>
                                <li>Scannen Sie den QR-Code mit Ihrer App</li>
                                <li>Geben Sie den 6-stelligen Code ein, der in Ihrer App angezeigt wird</li>
                            </ol>
                        </div>

                        <div class="qr-code-container">
                            <img src="<?php echo htmlspecialchars($qrCodeUrl); ?>" alt="QR Code">
                        </div>

                        <div>
                            <strong>Oder geben Sie diesen Code manuell ein:</strong>
                            <div class="secret-key"><?php echo htmlspecialchars($secret); ?></div>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="action" value="verify">
                            <div>
                                <label for="code"><strong>Geben Sie den 6-stelligen Code ein:</strong></label>
                                <input
                                    type="text"
                                    id="code"
                                    name="code"
                                    class="code-input"
                                    maxlength="6"
                                    pattern="[0-9]{6}"
                                    placeholder="000000"
                                    required
                                    autofocus>
                            </div>
                            <div style="text-align: center; margin-top: 20px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Bestätigen und aktivieren
                                </button>
                                <a href="two_factor.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Abbrechen
                                </a>
                            </div>
                        </form>
                    </div>
                <?php elseif (!$is2FAEnabled): ?>
                    <div class="form-section">
                        <h2><i class="fas fa-shield-alt"></i> 2FA aktivieren</h2>
                        <p>
                            Erhöhen Sie die Sicherheit Ihres Kontos durch Zwei-Faktor-Authentifizierung.
                            Sie benötigen zusätzlich zu Ihrem Passwort einen zeitbasierten Code von Ihrer
                            Authenticator-App, um sich anzumelden.
                        </p>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="generate">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> 2FA einrichten
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="form-section">
                        <h2><i class="fas fa-shield-alt"></i> 2FA deaktivieren</h2>
                        <div class="alert warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Das Deaktivieren von 2FA macht Ihr Konto weniger sicher.</span>
                        </div>
                        <p>
                            Um 2FA zu deaktivieren, geben Sie bitte einen aktuellen Code aus Ihrer
                            Authenticator-App oder einen Backup-Code ein.
                        </p>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="disable">
                            <div>
                                <label for="code"><strong>Code zur Bestätigung:</strong></label>
                                <input
                                    type="text"
                                    id="code"
                                    name="code"
                                    class="code-input"
                                    maxlength="8"
                                    placeholder="000000"
                                    required>
                            </div>
                            <div style="text-align: center; margin-top: 20px;">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-times"></i> 2FA deaktivieren
                                </button>
                                <a href="../settings.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Zurück
                                </a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>