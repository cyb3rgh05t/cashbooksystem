<?php

/**
 * Lizenz-Verwaltung für Cashbook System  
 * Im einheitlichen Design
 */

require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/init_logger.php';
require_once '../../includes/role_check.php';

// Require login
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();
$licenseHelper = $auth->getLicenseHelper();
$isAdmin = $auth->isAdmin();

// Hole globale Lizenz
$globalLicenseKey = $_SESSION['global_license_key'] ?? null;

// Database connection
$db = new Database();
$pdo = $db->getConnection();

$message = '';
$messageType = '';

// Handle form submission (nur für Admins)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_license':
                $newLicenseKey = trim($_POST['license_key'] ?? '');

                if (empty($newLicenseKey)) {
                    $message = 'Bitte geben Sie einen gültigen Lizenzschlüssel ein.';
                    $messageType = 'error';
                } else {
                    // Validiere neue Lizenz
                    $validation = $licenseHelper->validateLicense($newLicenseKey, true);

                    if ($validation['valid']) {
                        // Speichere in Datenbank
                        $stmt = $pdo->prepare("UPDATE users SET license_key = ? WHERE id = ?");
                        $stmt->execute([$newLicenseKey, $currentUser['id']]);

                        // Update Session
                        $licenseHelper->storeLicenseInSession($newLicenseKey, $validation);
                        $_SESSION['global_license_key'] = $newLicenseKey;
                        $_SESSION['last_license_check'] = time();

                        $message = 'Systemlizenz erfolgreich aktiviert!';
                        $messageType = 'success';

                        // Reload
                        $globalLicenseKey = $newLicenseKey;
                    } else {
                        $message = 'Lizenzschlüssel ungültig: ' . ($validation['error'] ?? 'Unbekannter Fehler');
                        $messageType = 'error';
                    }
                }
                break;

            case 'validate_license':
                if ($globalLicenseKey) {
                    // Force online validation
                    $validation = $licenseHelper->validateLicense($globalLicenseKey, true);

                    if ($validation['valid']) {
                        $licenseHelper->storeLicenseInSession($globalLicenseKey, $validation);
                        $_SESSION['last_license_check'] = time();
                        $message = 'Systemlizenz erfolgreich validiert!';
                        $messageType = 'success';
                    } else {
                        $message = 'Lizenzvalidierung fehlgeschlagen: ' . ($validation['error'] ?? 'Unbekannter Fehler');
                        $messageType = 'error';
                    }
                }
                break;

            case 'remove_license':
                if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes' && $globalLicenseKey) {
                    // Deaktiviere Lizenz auf Server
                    $licenseHelper->deactivateLicense($globalLicenseKey);

                    // Entferne aus Datenbank
                    $stmt = $pdo->prepare("UPDATE users SET license_key = NULL WHERE license_key = ?");
                    $stmt->execute([$globalLicenseKey]);

                    // Clear session
                    unset($_SESSION['license']);
                    unset($_SESSION['global_license_key']);

                    $message = 'Systemlizenz wurde entfernt.';
                    $messageType = 'info';

                    $globalLicenseKey = null;
                }
                break;
        }
    }
}

// Get current license info
$licenseInfo = $licenseHelper->getLicenseFromSession();
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lizenz-Verwaltung - Cashbook</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/settings.css">
    <link rel="stylesheet" href="../../assets/css/license.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
</head>

<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a class="sidebar-logo">
                    <img src="/assets/images/logo.png" alt="Meine Firma Finance Logo" class="sidebar-logo-image">
                </a>
                <p class="sidebar-welcome">Willkommen, <?= htmlspecialchars($currentUser['full_name'] ?? $currentUser['username']) ?></p>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="../../dashboard.php"><i class="fa-solid fa-house"></i>&nbsp;&nbsp;Dashboard</a></li>
                    <?php if (canAccessModules($currentUser)): ?>
                        <li><a href="../expenses/index.php"><i class="fa-solid fa-money-bill-wave"></i>&nbsp;&nbsp;Ausgaben</a></li>
                        <li><a href="../income/index.php"><i class="fa-solid fa-sack-dollar"></i>&nbsp;&nbsp;Einnahmen</a></li>
                        <li><a href="../debts/index.php"><i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden</a></li>
                        <li><a href="../recurring/index.php"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend</a></li>
                        <li><a href="../investments/index.php"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto</a></li>
                        <li><a href="../categories/index.php"><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorien</a></li>
                    <?php endif; ?>
                    <li>
                        <a style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;" href="../../settings.php">
                            <i class="fa-solid fa-gear"></i>&nbsp;&nbsp;Einstellungen
                        </a>
                    </li>
                    <li><a href="#" class="active"><i class="fa-solid fa-key"></i>&nbsp;&nbsp;Lizenz</a></li>
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
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;">
                        <i class="fa-solid fa-key"></i>&nbsp;&nbsp;Systemlizenz-Verwaltung
                    </h1>
                    <p style="color: var(--clr-surface-a50);">
                        <?= $isAdmin ? 'Verwalte die Systemlizenz für alle Benutzer' : 'Lizenzstatus einsehen' ?>
                    </p>
                </div>
                <a href="../../dashboard.php" class="btn btn-secondary">← Zurück zum Dashboard</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!$isAdmin): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-lock"></i> Nur Administratoren können die Systemlizenz verwalten.
                </div>
            <?php endif; ?>

            <div class="dashboard-cards">
                <?php if ($globalLicenseKey && $licenseInfo): ?>
                    <!-- Aktive Lizenz -->
                    <div class="settings-card" style="grid-column: span 2;">
                        <div class="settings-header card-header">
                            <h2><i class="fas fa-certificate"></i> Systemlizenz-Status</h2>
                            <span class="status-badge-large <?= $licenseInfo['valid'] ? 'active' : 'inactive' ?>">
                                <i class="fas fa-<?= $licenseInfo['valid'] ? 'check-circle' : 'times-circle' ?>"></i>
                                <?= $licenseInfo['valid'] ? 'AKTIV' : 'UNGÜLTIG' ?>
                            </span>
                        </div>

                        <div class="card-content">
                            <!-- Lizenzschlüssel Anzeige -->
                            <div class="license-key-display">
                                <i class="fas fa-key"></i>&nbsp;
                                <?= htmlspecialchars(substr($globalLicenseKey, 0, 7)) ?>-****-****-****
                            </div>

                            <!-- Lizenz-Informationen Grid -->
                            <div class="license-info-grid">
                                <?php if (isset($licenseInfo['data']['user_info'])): ?>
                                    <div class="license-info-item">
                                        <div class="license-info-label">
                                            <i class="fas fa-crown"></i> Lizenztyp
                                        </div>
                                        <div class="license-info-value">
                                            <?= htmlspecialchars($licenseInfo['data']['user_info']['license_type'] ?? 'Standard') ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($licenseInfo['expires_at']): ?>
                                    <div class="license-info-item">
                                        <div class="license-info-label">
                                            <i class="fas fa-calendar-check"></i> Gültig bis
                                        </div>
                                        <div class="license-info-value">
                                            <?= date('d.m.Y', $licenseInfo['expires_at'] / 1000) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="license-info-item">
                                    <div class="license-info-label">
                                        <i class="fas fa-check-circle"></i> Letzte Prüfung
                                    </div>
                                    <div class="license-info-value">
                                        <?= date('H:i:s', $_SESSION['last_license_check'] ?? time()) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Hardware-ID -->
                            <div style="margin: 20px 0;">
                                <div class="license-info-label">
                                    <i class="fas fa-microchip"></i> Hardware-ID
                                </div>
                                <div class="hardware-id-display">
                                    <?= htmlspecialchars($licenseInfo['hardware_id'] ?? $licenseHelper->generateHardwareId()) ?>
                                </div>
                            </div>

                            <!-- Features -->
                            <?php if (!empty($licenseInfo['features'])): ?>
                                <div style="margin: 20px 0;">
                                    <div class="license-info-label" style="margin-bottom: 12px;">
                                        <i class="fas fa-star"></i> Verfügbare Features
                                    </div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                        <?php foreach ($licenseInfo['features'] as $feature): ?>
                                            <span class="feature-badge">
                                                <i class="fas fa-check"></i> <?= htmlspecialchars($feature) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Admin Actions -->
                            <?php if ($isAdmin): ?>
                                <div class="license-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="validate_license">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-sync"></i>&nbsp;&nbsp;Lizenz prüfen
                                        </button>
                                    </form>

                                    <form method="POST" style="display: inline;" onsubmit="return confirm('ACHTUNG: Ohne Lizenz kann sich niemand mehr einloggen!\n\nDiese Aktion kann nicht rückgängig gemacht werden.\n\nWirklich fortfahren?');">
                                        <input type="hidden" name="action" value="remove_license">
                                        <input type="hidden" name="confirm" value="yes">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-trash"></i>&nbsp;&nbsp;Lizenz entfernen
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Keine Lizenz -->
                    <div class="card no-license-card" style="grid-column: span 2;">
                        <div class="card-header">
                            <h3><i class="fas fa-exclamation-triangle"></i> Keine Systemlizenz</h3>
                            <span class="status-badge-large inactive">
                                <i class="fas fa-times-circle"></i> INAKTIV
                            </span>
                        </div>

                        <div class="card-content">
                            <?php if ($isAdmin): ?>
                                <div class="alert alert-warning" style="margin-bottom: 20px;">
                                    <i class="fas fa-info-circle"></i>
                                    Das System hat keine aktive Lizenz. Ohne gültige Lizenz können sich Benutzer nicht einloggen.
                                </div>

                                <form method="POST">
                                    <input type="hidden" name="action" value="update_license">

                                    <div class="form-group">
                                        <label for="license_key" class="form-label">
                                            <i class="fas fa-key"></i> Lizenzschlüssel eingeben:
                                        </label>
                                        <input type="text"
                                            id="license_key"
                                            name="license_key"
                                            class="form-input"
                                            placeholder="KFZ####-####-####-####"
                                            style="text-transform: uppercase; font-family: 'Courier New', monospace; letter-spacing: 1px;"
                                            required>
                                    </div>

                                    <div style="margin: 20px 0;">
                                        <div class="license-info-label">
                                            <i class="fas fa-microchip"></i> Ihre Hardware-ID:
                                        </div>
                                        <div class="hardware-id-display" style="margin-top: 8px;">
                                            <?= htmlspecialchars($licenseHelper->generateHardwareId()) ?>
                                        </div>
                                        <small style="color: var(--clr-surface-a50); display: block; margin-top: 8px;">
                                            Diese ID wird für die Lizenzaktivierung benötigt.
                                        </small>
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-full">
                                        <i class="fas fa-check"></i> Lizenz aktivieren
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-error">
                                    <i class="fas fa-lock"></i>
                                    Das System benötigt eine gültige Lizenz.
                                    Bitte kontaktieren Sie einen Administrator.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Info Card -->
                <div class="card info-card" style="grid-column: span 2;">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> System-Informationen</h3>
                    </div>
                    <div class="card-content">
                        <ul class="info-list">
                            <li>
                                <i class="fas fa-server"></i>
                                <div>
                                    <strong>Systemlizenz:</strong><br>
                                    <span style="color: var(--clr-surface-a60);">Eine Lizenz gilt für die gesamte Installation und alle Benutzer</span>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-link"></i>
                                <div>
                                    <strong>Hardware-Bindung:</strong><br>
                                    <span style="color: var(--clr-surface-a60);">Die Lizenz wird an diese spezifische Installation gebunden</span>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-shield-alt"></i>
                                <div>
                                    <strong>Validierung:</strong><br>
                                    <span style="color: var(--clr-surface-a60);">Automatische Überprüfung alle 60 Sekunden während der Nutzung</span>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-user-shield"></i>
                                <div>
                                    <strong>Verwaltung:</strong><br>
                                    <span style="color: var(--clr-surface-a60);">Nur Administratoren können die Systemlizenz ändern oder entfernen</span>
                                </div>
                            </li>
                            <?php if ($isAdmin): ?>
                                <li>
                                    <i class="fas fa-exclamation-triangle" style="color: #f87171;"></i>
                                    <div>
                                        <strong style="color: #f87171;">Wichtiger Hinweis:</strong><br>
                                        <span style="color: var(--clr-surface-a60);">Ohne gültige Lizenz ist kein Login möglich! Entfernen Sie die Lizenz nur, wenn Sie eine neue haben.</span>
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>