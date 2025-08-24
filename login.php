<?php
require_once 'includes/auth.php';
require_once 'includes/init_logger.php';
require_once 'config/database.php';

// Wenn bereits eingeloggt, weiterleiten zum Dashboard
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Funktion um globale Lizenz direkt aus DB zu prüfen
function checkGlobalLicenseValid()
{
    global $auth;

    try {
        // WICHTIG: Nutze die existierende Auth-Verbindung, keine neue erstellen!
        if (!$auth || !$auth->getLicenseHelper()) {
            return false;
        }

        // Hole globalen Lizenzschlüssel über Auth-Methode
        $globalKey = null;

        // Prüfe zuerst Session
        if (isset($_SESSION['global_license_key'])) {
            $globalKey = $_SESSION['global_license_key'];
        } else {
            // Wenn nicht in Session, hole direkt über Auth
            // Nutze Reflection um auf private Methode zuzugreifen
            $reflection = new ReflectionClass($auth);
            $method = $reflection->getMethod('getGlobalLicenseKey');
            $method->setAccessible(true);
            $globalKey = $method->invoke($auth);
        }

        if ($globalKey) {
            // Prüfe ob die Lizenz gültig ist - OHNE neue DB-Verbindung
            // Nutze Cache um DB-Lock zu vermeiden
            $validation = $auth->getLicenseHelper()->validateLicense($globalKey, false);

            // Speichere in Session für späteren Gebrauch
            if ($validation['valid']) {
                $_SESSION['temp_global_license_key'] = $globalKey;
                unset($_SESSION['license_error']);
                return true;
            }
        }
    } catch (Exception $e) {
        // Bei Fehler versuche alternative Methode
        error_log("License check error: " . $e->getMessage());
    }

    return false;
}

// Prüfe Lizenz-Status bei require_license Parameter
$licenseIsValid = false;
if (isset($_GET['require_license']) && $_GET['require_license'] == '1') {
    $licenseIsValid = checkGlobalLicenseValid();
}

$error_message = '';
if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

$success_message = '';
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Check for logout message
if (isset($_SESSION['logout_message'])) {
    $success_message = $_SESSION['logout_message'];
    unset($_SESSION['logout_message']);
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Meine Firma Finance</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--clr-surface-a0) 0%, var(--clr-surface-tonal-a0) 100%);
        }

        .login-card {
            background-color: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-title {
            color: var(--clr-primary-a20);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-subtitle {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background-color: rgba(248, 113, 113, 0.1);
            border: 1px solid #f87171;
            color: #fca5a5;
        }

        .alert-success {
            background-color: rgba(74, 222, 128, 0.1);
            border: 1px solid #4ade80;
            color: #86efac;
        }

        .btn-full {
            width: 100%;
            justify-content: center;
            margin-top: 10px;
        }

        .form-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--clr-surface-a20);
        }

        .form-footer p {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        /* Demo Info Box */
        .demo-info {
            background: var(--clr-surface-tonal-a10);
            border: 1px solid var(--clr-primary-a20);
            border-radius: 6px;
            padding: 12px;
            margin-top: 20px;
            font-size: 13px;
        }

        .demo-info-title {
            color: var(--clr-primary-a20);
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center !important;
            justify-content: center;
            gap: 6px;
        }

        .demo-credentials {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .demo-credential {
            text-align: center;
        }

        .demo-credential strong {
            color: var(--clr-primary-a20);
            display: block;
            margin-bottom: 2px;
        }

        .demo-credential span {
            color: var(--clr-surface-a50);
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <img src="assets/images/logo.png" alt="Meine Firma Finance Logo" class="login-logo-image">
                    <h1 class="login-title">Meine Firma Finance</h1>
                </div>
                <p class="login-subtitle">Dein persönlicher Finanztracker</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="auth/login.php" method="POST">
                <div class="form-group">
                    <label class="form-label" for="username">Benutzername</label>
                    <input type="text" id="username" name="username" class="form-input" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Passwort</label>
                    <input type="password" id="password" name="password" class="form-input" required>
                </div>

                <button type="submit" class="btn btn-full">Anmelden</button>
            </form>

            <!-- Demo Credentials -->
            <div class="demo-info">
                <div class="demo-info-title">
                    Demo-Zugangsdaten
                </div>
                <div class="demo-credentials">
                    <div class="demo-credential">
                        <strong>Admin</strong>
                        <span>admin / admin123</span>
                    </div>
                    <div class="demo-credential">
                        <strong>Demo User</strong>
                        <span>demo / demo123</span>
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <?php
                $start_year = 2024;
                $current_year = date('Y');
                ?>
                <p>© <?= $start_year == $current_year ? $current_year : $start_year . ' - ' . $current_year ?> · Flammang Yves</p>
            </div>
        </div>
    </div>

    <!-- License Modal einbinden -->
    <?php include 'includes/license_modal.php'; ?>

    <script>
        // Automatisch Modal öffnen wenn require_license Parameter vorhanden ist
        document.addEventListener('DOMContentLoaded', function() {
            // URL-Parameter prüfen
            const urlParams = new URLSearchParams(window.location.search);

            // NUR wenn require_license=1 vorhanden ist, führe Lizenz-Check aus
            if (urlParams.get('require_license') === '1') {
                console.log('License required parameter detected');

                // PHP-Variablen in JavaScript verfügbar machen
                const isAdmin = <?php echo isset($_SESSION['is_admin']) && $_SESSION['is_admin'] ? 'true' : 'false'; ?>;

                // Verwende die PHP-Variable die wir oben gesetzt haben
                const licenseActive = <?php echo $licenseIsValid ? 'true' : 'false'; ?>;

                console.log('Admin status:', isAdmin);
                console.log('License active:', licenseActive);
                console.log('License check performed directly from database');

                // Modal öffnen mit beiden Parametern
                if (typeof openLicenseModal === 'function') {
                    // Kurze Verzögerung für bessere UX
                    setTimeout(function() {
                        // Wenn Lizenz bereits aktiv ist, öffne Modal mit Success-Nachricht
                        if (licenseActive) {
                            console.log('Valid license found - showing success in modal');

                            // Öffne Modal um Success zu zeigen
                            openLicenseModal(isAdmin, true);

                            // Zeige Success-Nachricht im Modal
                            setTimeout(function() {
                                const successDiv = document.getElementById('license-success');
                                const errorDiv = document.getElementById('license-error');
                                const warningDiv = document.getElementById('license-warning');

                                // Verstecke alle anderen Nachrichten
                                if (errorDiv) errorDiv.style.display = 'none';
                                if (warningDiv) warningDiv.style.display = 'none';

                                // Zeige Success
                                if (successDiv) {
                                    successDiv.innerHTML = '<i class="fas fa-check-circle"></i> Systemlizenz ist bereits aktiviert! Weiterleitung zum Dashboard...';
                                    successDiv.style.display = 'block';
                                }

                                // Automatische Weiterleitung nach 2 Sekunden
                                setTimeout(function() {
                                    window.location.href = 'dashboard.php';
                                }, 2000);
                            }, 100);

                        } else {
                            // Nur wenn KEINE gültige Lizenz gefunden wurde, öffne das Modal mit Warnung
                            openLicenseModal(isAdmin, false);

                            // Zeige Warnung nach kurzer Verzögerung
                            setTimeout(function() {
                                const errorDiv = document.getElementById('license-error');
                                const successDiv = document.getElementById('license-success');

                                // Verstecke Success falls vorhanden
                                if (successDiv) successDiv.style.display = 'none';

                                <?php if (isset($_SESSION['license_error']) && $_SESSION['license_error']): ?>
                                    if (errorDiv) {
                                        errorDiv.textContent = 'Keine gültige Systemlizenz gefunden. Bitte aktivieren Sie eine Lizenz.';
                                        errorDiv.style.display = 'block';
                                    }
                                <?php else: ?>
                                    // Auch ohne expliziten license_error zeige Nachricht wenn keine Lizenz vorhanden
                                    if (errorDiv && !isAdmin) {
                                        errorDiv.textContent = 'Nur Administratoren können Systemlizenzen aktivieren.';
                                        errorDiv.style.display = 'block';
                                    } else if (errorDiv) {
                                        errorDiv.textContent = 'Bitte aktivieren Sie eine Systemlizenz.';
                                        errorDiv.style.display = 'block';
                                    }
                                <?php endif; ?>
                            }, 100);
                        }
                    }, 500);
                } else {
                    console.error('openLicenseModal function not found');
                }
            }
            // KEIN else - wenn kein require_license Parameter, dann NICHTS tun!
        });
    </script>

</body>

</html>