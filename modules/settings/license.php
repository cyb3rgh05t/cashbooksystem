<?php

/**
 * Lizenz-Verwaltung für Cashbook System  
 * Im einheitlichen Design
 */

require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/init_logger.php';

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
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
</head>

<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Cashbook</h2>
                <p>Hallo, <?= htmlspecialchars($currentUser['username']) ?></p>
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
                    <div class="card" style="grid-column: span 2;">
                        <div class="card-header">
                            <h3><i class="fas fa-certificate"></i> Systemlizenz-Status</h3>
                            <span class="badge <?= $licenseInfo['valid'] ? 'badge-success' : 'badge-error' ?>">
                                <?= $licenseInfo['valid'] ? 'Aktiv' : 'Ungültig' ?>
                            </span>
                        </div>

                        <div class="card-content">
                            <div class="stats-grid" style="margin-bottom: 20px;">
                                <div class="stat-item">
                                    <div class="stat-label">Lizenzschlüssel</div>
                                    <div class="stat-value" style="font-family: monospace; font-size: 14px;">
                                        <?= htmlspecialchars(substr($globalLicenseKey, 0, 7)) ?>-****-****
                                    </div>
                                </div>

                                <?php if (isset($licenseInfo['data']['user_info'])): ?>
                                    <div class="stat-item">
                                        <div class="stat-label">Lizenztyp</div>
                                        <div class="stat-value">
                                            <?= htmlspecialchars($licenseInfo['data']['user_info']['license_type'] ?? 'Standard') ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($licenseInfo['expires_at']): ?>
                                    <div class="stat-item">
                                        <div class="stat-label">Gültig bis</div>
                                        <div class="stat-value">
                                            <?= date('d.m.Y', $licenseInfo['expires_at'] / 1000) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="stat-item">
                                    <div class="stat-label">Hardware-ID</div>
                                    <div class="stat-value" style="font-family: monospace; font-size: 11px; word-break: break-all;">
                                        <?= htmlspecialchars($licenseInfo['hardware_id'] ?? $licenseHelper->generateHardwareId()) ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($licenseInfo['features'])): ?>
                                <div style="margin-bottom: 20px;">
                                    <div class="stat-label" style="margin-bottom: 10px;">Verfügbare Features</div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                        <?php foreach ($licenseInfo['features'] as $feature): ?>
                                            <span class="badge badge-primary"><?= htmlspecialchars($feature) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($isAdmin): ?>
                                <div class="button-group">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="validate_license">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-sync"></i> Lizenz prüfen
                                        </button>
                                    </form>

                                    <form method="POST" style="display: inline;" onsubmit="return confirm('ACHTUNG: Ohne Lizenz kann sich niemand mehr einloggen! Fortfahren?');">
                                        <input type="hidden" name="action" value="remove_license">
                                        <input type="hidden" name="confirm" value="yes">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> Lizenz entfernen
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Keine Lizenz -->
                    <div class="card" style="grid-column: span 2;">
                        <div class="card-header">
                            <h3><i class="fas fa-exclamation-triangle"></i> Keine Systemlizenz</h3>
                            <span class="badge badge-error">Inaktiv</span>
                        </div>

                        <div class="card-content">
                            <?php if ($isAdmin): ?>
                                <p style="margin-bottom: 20px;">
                                    Das System hat keine aktive Lizenz. Bitte aktivieren Sie eine Lizenz,
                                    damit sich Benutzer einloggen können.
                                </p>

                                <form method="POST">
                                    <input type="hidden" name="action" value="update_license">

                                    <div class="form-group">
                                        <label for="license_key">Lizenzschlüssel:</label>
                                        <input type="text"
                                            id="license_key"
                                            name="license_key"
                                            placeholder="KFZ####-####-####-####"
                                            style="text-transform: uppercase;"
                                            required>
                                    </div>

                                    <div class="info-box" style="margin-bottom: 20px;">
                                        <p><strong>Hardware-ID:</strong></p>
                                        <code style="display: block; padding: 8px; background: var(--clr-surface-a10); border-radius: 4px; margin-top: 8px;">
                                            <?= htmlspecialchars($licenseHelper->generateHardwareId()) ?>
                                        </code>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-check"></i> Lizenz aktivieren
                                    </button>
                                </form>
                            <?php else: ?>
                                <p>
                                    Das System benötigt eine gültige Lizenz.
                                    Bitte kontaktieren Sie einen Administrator.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Info Card -->
                <div class="card" style="grid-column: span 2;">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> Informationen</h3>
                    </div>
                    <div class="card-content">
                        <ul style="line-height: 1.8; color: var(--clr-surface-a70);">
                            <li><strong>Systemlizenz:</strong> Eine Lizenz für die gesamte Installation</li>
                            <li><strong>Hardware-Bindung:</strong> Die Lizenz wird an diese Installation gebunden</li>
                            <li><strong>Validierung:</strong> Automatische Prüfung alle 60 Sekunden</li>
                            <li><strong>Verwaltung:</strong> Nur Administratoren können die Lizenz ändern</li>
                            <?php if ($isAdmin): ?>
                                <li><strong>Wichtig:</strong> Ohne gültige Lizenz kann sich niemand einloggen!</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>