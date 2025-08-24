<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../config/crypto_api.php';
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

// Get investment ID
$investment_id = $_GET['id'] ?? null;

if (!$investment_id || !is_numeric($investment_id)) {
    $_SESSION['error'] = 'Ungültige Investment-ID.';
    header('Location: index.php');
    exit;
}

// Load investment
$investment = $db->getInvestmentById((int)$investment_id);

if (!$investment) {
    $_SESSION['error'] = 'Investment nicht gefunden.';
    header('Location: index.php');
    exit;
}

// Check if user owns this investment (security check)
if (!$db->isInvestmentOwner((int)$investment_id, $user_id)) {
    $_SESSION['error'] = 'Du kannst nur deine eigenen Investments löschen.';
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_delete'])) {
        try {
            $success = $db->deleteInvestment((int)$investment_id);

            if ($success) {
                $_SESSION['success'] = 'Investment "' . htmlspecialchars($investment['symbol']) . '" wurde erfolgreich gelöscht.';
            } else {
                $_SESSION['error'] = 'Fehler beim Löschen des Investments.';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Fehler beim Löschen: ' . $e->getMessage();
        }
    }

    header('Location: index.php');
    exit;
}

// Calculate current value for display
require_once '../../config/crypto_api.php';
$crypto_api = new CryptoAPI();
$current_price_data = $crypto_api->getCurrentPrice($crypto_api->convertSymbolToId($investment['symbol']));
$current_price = $current_price_data ? $current_price_data : $investment['purchase_price'];

$purchase_value = $investment['amount'] * $investment['purchase_price'];
$current_value = $investment['amount'] * $current_price;
$profit_loss = $current_value - $purchase_value;
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investment löschen - Meine Firma Finance</title>
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-..."
        crossorigin="anonymous"
        referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/investments.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
</head>

<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a class="sidebar-logo">
                    <img src="../../assets/images/logo.png" alt="Meine Firma Finance Logo" class="sidebar-logo-image">
                </a>
                <p class="sidebar-welcome">Willkommen, <?= htmlspecialchars($currentUser['full_name'] ?? $currentUser['username']) ?></p>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="../../dashboard.php"><i class="fa-solid fa-house"></i>&nbsp;&nbsp;Dashboard</a></li>
                    <li><a href="../expenses/index.php"><i class="fa-solid fa-money-bill-wave"></i>&nbsp;&nbsp;Ausgaben</a></li>
                    <li><a href="../income/index.php"><i class="fa-solid fa-sack-dollar"></i>&nbsp;&nbsp;Einnahmen</a></li>
                    <li><a href="../debts/index.php" class="<?= strpos($_SERVER['PHP_SELF'], 'debts') ? 'active' : '' ?>">
                            <i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden
                        </a></li>
                    <li><a href="../recurring/index.php"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend</a></li>
                    <li><a href="index.php" class="active"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto</a></li>
                    <li><a href="../categories/index.php"><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorien</a></li>
                    <li>
                        <a style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;" href="../../settings.php">
                            <i class="fa-solid fa-gear"></i>&nbsp;&nbsp;Einstellungen
                        </a>
                    </li>
                    <li><a href="modules/settings/license.php"><i class="fas fa-key"></i>&nbsp;&nbsp;Lizenz</a></li>
                    <li>
                        <a href="../../logout.php"><i class="fa-solid fa-right-from-bracket"></i>&nbsp;&nbsp;Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 style="color: #f87171; margin-bottom: 5px;"><i class="fa-solid fa-trash-can"></i> Investment löschen</h1>
                    <p style="color: var(--clr-surface-a50);">Bestätige das Löschen deines Investments</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
            </div>

            <div class="delete-container">
                <div class="delete-card">
                    <div class="delete-header">
                        <div class="delete-icon">⚠️</div>
                        <h2>Investment löschen bestätigen</h2>
                        <p>Diese Aktion kann nicht rückgängig gemacht werden!</p>
                    </div>

                    <div class="investment-preview">
                        <div class="preview-header">
                            <div class="crypto-info">
                                <div class="crypto-symbol"><?= htmlspecialchars($investment['symbol']) ?></div>
                                <div class="crypto-name"><?= htmlspecialchars($investment['name']) ?></div>
                            </div>
                        </div>

                        <div class="preview-details">
                            <div class="detail-row">
                                <span class="label">Anzahl:</span>
                                <span class="value"><?= number_format($investment['amount'], 4, ',', '.') ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Einkaufspreis:</span>
                                <span class="value">€<?= number_format($investment['purchase_price'], 2, ',', '.') ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Kaufdatum:</span>
                                <span class="value"><?= date('d.m.Y', strtotime($investment['purchase_date'])) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Investierter Betrag:</span>
                                <span class="value invested">€<?= number_format($purchase_value, 2, ',', '.') ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Aktueller Wert:</span>
                                <span class="value current">€<?= number_format($current_value, 2, ',', '.') ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Gewinn/Verlust:</span>
                                <span class="value <?= $profit_loss >= 0 ? 'profit' : 'loss' ?>">
                                    <?= $profit_loss >= 0 ? '+' : '' ?>€<?= number_format($profit_loss, 2, ',', '.') ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="delete-warning">
                        <div class="warning-icon">🚨</div>
                        <div class="warning-text">
                            <strong>Achtung:</strong> Durch das Löschen dieses Investments gehen alle gespeicherten
                            Daten dauerhaft verloren. Diese Aktion kann nicht rückgängig gemacht werden.
                        </div>
                    </div>

                    <form method="POST" class="delete-form">
                        <div class="form-actions">
                            <a href="index.php" class="btn btn-secondary">Abbrechen</a>
                            <button type="submit" name="confirm_delete" class="btn btn-danger">
                                <i class="fa-solid fa-trash-can"></i> Endgültig löschen
                            </button>
                        </div>
                    </form>

                    <div class="alternative-actions">
                        <p><strong>Alternativen:</strong></p>
                        <p>
                            <a href="edit.php?id=<?= $investment['id'] ?>" class="btn btn-small"><i class="fa-solid fa-pen-to-square"></i> Investment bearbeiten</a>
                            statt löschen - falls du nur die Daten korrigieren möchtest.
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        .delete-container {
            max-width: 600px;
            margin: 0 auto;
        }

        .delete-card {
            background: var(--clr-surface-a10);
            border: 1px solid #f87171;
            border-radius: 12px;
            padding: 24px;
        }

        .delete-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .delete-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .delete-header h2 {
            color: #f87171;
            margin-bottom: 8px;
        }

        .delete-header p {
            color: var(--clr-surface-a50);
        }

        .investment-preview {
            background: var(--clr-surface-a05);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .preview-header {
            margin-bottom: 16px;
        }

        .crypto-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .crypto-symbol {
            font-size: 18px;
            font-weight: bold;
            color: var(--clr-primary-a20);
        }

        .crypto-name {
            color: var(--clr-surface-a50);
        }

        .preview-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .detail-row .label {
            color: var(--clr-surface-a40);
        }

        .detail-row .value {
            font-weight: 600;
        }

        .detail-row .value.invested {
            color: #fbbf24;
        }

        .detail-row .value.current {
            color: var(--clr-primary-a20);
        }

        .detail-row .value.profit {
            color: #4ade80;
        }

        .detail-row .value.loss {
            color: #f87171;
        }

        .delete-warning {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid #f87171;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .warning-icon {
            font-size: 24px;
            flex-shrink: 0;
        }

        .warning-text {
            color: var(--clr-surface-a50);
        }

        .warning-text strong {
            color: #f87171;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn-danger {
            background: #f87171;
            color: white;
        }

        .btn-danger:hover {
            background: #ef4444;
        }

        .alternative-actions {
            margin-top: 20px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid var(--clr-surface-a20);
        }

        .alternative-actions p {
            color: var(--clr-surface-a50);
            margin-bottom: 8px;
        }
    </style>
</body>

</html>