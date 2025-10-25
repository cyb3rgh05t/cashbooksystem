<?php
session_start();
require_once 'includes/two_factor.class.php';
require_once 'config/database.php';

// Check if user is in 2FA verification state
if (!isset($_SESSION['pending_2fa_user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['pending_2fa_user_id'];
$username = $_SESSION['pending_2fa_username'] ?? 'User';
$error = '';
$success = '';

// Initialize 2FA helper
$db = new Database();
$pdo = $db->getConnection();
require_once 'includes/logger.class.php';
$logger = new Logger($pdo);
$twoFactor = new TwoFactorAuth($pdo, $logger);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $useBackup = isset($_POST['use_backup']);

    if (empty($code)) {
        $error = 'Bitte geben Sie einen Code ein.';
    } else {
        $secret = $twoFactor->getSecret($userId);
        $isValid = false;

        if ($useBackup) {
            // Verify backup code
            $isValid = $twoFactor->verifyBackupCode($userId, strtoupper($code));
            if ($isValid) {
                $logger->info("2FA login with backup code", $userId, 'TWO_FACTOR');
            }
        } else {
            // Verify TOTP code
            $isValid = $twoFactor->verifyCode($secret, $code);
            if ($isValid) {
                $logger->info("2FA login successful", $userId, 'TWO_FACTOR');
            }
        }

        if ($isValid) {
            // 2FA verification successful, complete login
            require_once 'includes/auth.php';

            // Set session variables
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $_SESSION['pending_2fa_username'];
            $_SESSION['user_role'] = $_SESSION['pending_2fa_role'] ?? 'user';
            $_SESSION['last_activity'] = time();
            $_SESSION['just_logged_in'] = true;

            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = datetime('now') WHERE id = ?");
            $stmt->execute([$userId]);

            // Clear pending 2FA session data
            unset($_SESSION['pending_2fa_user_id']);
            unset($_SESSION['pending_2fa_username']);
            unset($_SESSION['pending_2fa_role']);

            // Redirect to dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            $error = $useBackup ? 'Ungültiger Backup-Code.' : 'Ungültiger Authentifizierungscode.';
            $logger->warning("Failed 2FA verification", $userId, 'TWO_FACTOR', ['method' => $useBackup ? 'backup' : 'totp']);
        }
    }
}

// Handle session timeout (10 minutes)
if (isset($_SESSION['2fa_timestamp']) && (time() - $_SESSION['2fa_timestamp']) > 600) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

$_SESSION['2fa_timestamp'] = time();
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zwei-Faktor-Authentifizierung - Cashbook System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .verify-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--clr-surface-a0) 0%, var(--clr-surface-tonal-a0) 100%);
            padding: 20px;
        }

        .verify-card {
            background: var(--clr-surface);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }

        .verify-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .verify-header .icon {
            width: 80px;
            height: 80px;
            background: var(--clr-primary-a10);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .verify-header .icon i {
            font-size: 40px;
            color: var(--clr-primary);
        }

        .verify-header h1 {
            font-size: 1.8em;
            margin-bottom: 10px;
            color: var(--clr-on-surface);
        }

        .verify-header p {
            color: var(--clr-on-surface-variant);
            margin: 0;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--clr-on-surface);
        }

        .code-input {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--clr-surface-a20);
            border-radius: 8px;
            font-size: 1.8em;
            text-align: center;
            letter-spacing: 12px;
            font-family: 'Courier New', monospace;
            transition: border-color 0.3s;
        }

        .code-input:focus {
            outline: none;
            border-color: var(--clr-primary);
        }

        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 10px;
        }

        .btn-primary {
            background: var(--clr-primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--clr-primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary {
            background: var(--clr-surface-a10);
            color: var(--clr-on-surface);
        }

        .btn-secondary:hover {
            background: var(--clr-surface-a20);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert.error {
            background: var(--clr-accent-red-a10);
            color: var(--clr-accent-red);
            border-left: 4px solid var(--clr-accent-red);
        }

        .alert.success {
            background: var(--clr-accent-green-a10);
            color: var(--clr-accent-green);
            border-left: 4px solid var(--clr-accent-green);
        }

        .divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: var(--clr-surface-a20);
        }

        .divider span {
            background: var(--clr-surface);
            padding: 0 15px;
            position: relative;
            color: var(--clr-on-surface-variant);
            font-size: 0.9em;
        }

        .backup-toggle {
            text-align: center;
            margin-top: 20px;
        }

        .backup-toggle a {
            color: var(--clr-primary);
            text-decoration: none;
            font-size: 0.95em;
        }

        .backup-toggle a:hover {
            text-decoration: underline;
        }

        .info-box {
            background: var(--clr-surface-a5);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid var(--clr-primary);
        }

        .info-box p {
            margin: 0;
            font-size: 0.95em;
            color: var(--clr-on-surface-variant);
        }

        .cancel-link {
            text-align: center;
            margin-top: 20px;
        }

        .cancel-link a {
            color: var(--clr-on-surface-variant);
            text-decoration: none;
            font-size: 0.95em;
        }

        .cancel-link a:hover {
            color: var(--clr-primary);
        }
    </style>
</head>

<body>
    <div class="verify-container">
        <div class="verify-card">
            <div class="verify-header">
                <div class="icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1>Zwei-Faktor-Authentifizierung</h1>
                <p>Hallo, <strong><?php echo htmlspecialchars($username); ?></strong></p>
            </div>

            <?php if ($error): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <p>
                    <i class="fas fa-info-circle"></i>
                    Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein.
                </p>
            </div>

            <form method="POST" action="" id="verifyForm">
                <div class="form-group">
                    <label for="code">Authentifizierungscode</label>
                    <input
                        type="text"
                        id="code"
                        name="code"
                        class="code-input"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        placeholder="000000"
                        required
                        autofocus
                        autocomplete="off">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Bestätigen
                </button>
            </form>

            <div class="divider">
                <span>oder</span>
            </div>

            <form method="POST" action="" id="backupForm">
                <input type="hidden" name="use_backup" value="1">
                <div class="form-group">
                    <label for="backup_code">Backup-Code</label>
                    <input
                        type="text"
                        id="backup_code"
                        name="code"
                        class="code-input"
                        maxlength="8"
                        placeholder="XXXXXXXX"
                        style="letter-spacing: 4px;">
                </div>

                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-key"></i> Mit Backup-Code anmelden
                </button>
            </form>

            <div class="cancel-link">
                <a href="logout.php">
                    <i class="fas fa-arrow-left"></i> Abbrechen und zurück zum Login
                </a>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus on code input
        document.getElementById('code').focus();

        // Auto-submit when 6 digits are entered
        document.getElementById('code').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 6) {
                document.getElementById('verifyForm').submit();
            }
        });

        // Format backup code input
        document.getElementById('backup_code').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9A-Z]/g, '').toUpperCase();
        });
    </script>
</body>

</html>