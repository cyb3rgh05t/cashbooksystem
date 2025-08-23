<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../config/crypto_api.php';
require_once '../../includes/init_logger.php';
require_once '../../includes/timezone.php';
require_once '../../includes/role_check.php';

// Require login mit Auth-Klasse
$auth->requireLogin();

// Get current user
$currentUser = $auth->getCurrentUser();
restrictViewerAccess($currentUser);
$user_id = $currentUser['id'];

TimezoneHelper::initializeTimezone();

// Database connection
$db = new Database();
$pdo = $db->getConnection();

// Get all investments with current values
$investments = $db->getInvestmentsWithCurrentValue($user_id);
$total_stats = $db->getTotalInvestmentValue($user_id);

// Success/Error Messages
$message = '';
if (isset($_SESSION['success'])) {
    $message = '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $message = '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}

// Check for API status
$api_status_message = '';
if (!empty($total_stats['api_error'])) {
    if ($total_stats['data_status'] === 'api_unavailable') {
        $api_status_message = '<div class="alert alert-warning">
            <strong>⚠️ Crypto-Preise nicht verfügbar:</strong><br>
            ' . htmlspecialchars($total_stats['api_error']) . '<br>
            <small>Nur Einkaufspreise werden angezeigt. Gewinn/Verlust kann nicht berechnet werden.</small>
        </div>';
    } elseif ($total_stats['data_status'] === 'partial_data') {
        $api_status_message = '<div class="alert alert-warning">
            <strong>⚠️ Teilweise Daten verfügbar:</strong><br>
            ' . $total_stats['error_count'] . ' von ' . $total_stats['investment_count'] . ' Investments haben veraltete Preise.<br>
            <small>Gesamtberechnung möglicherweise unvollständig.</small>
        </div>';
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto - Meine Firma Finance</title>
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-..."
        crossorigin="anonymous"
        referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/investments.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">

    <style>
        .alert-warning {
            background-color: rgba(251, 191, 36, 0.1);
            border: 1px solid #fbbf24;
            color: #fcd34d;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .investment-row.api-error {
            background-color: rgba(248, 113, 113, 0.05);
            border-left: 4px solid #f87171;
        }

        .price-unavailable {
            color: #94a3b8;
            font-style: italic;
        }

        .data-status {
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-left: 8px;
        }

        .data-status.current {
            background-color: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .data-status.unavailable {
            background-color: rgba(248, 113, 113, 0.2);
            color: #f87171;
        }

        .api-error-details {
            grid-column: 1 / -1;
            padding: 8px 12px;
            background-color: rgba(248, 113, 113, 0.1);
            border-radius: 4px;
            margin-top: 8px;
        }

        .system-status {
            background-color: rgba(59, 130, 246, 0.1);
            border: 1px solid #3b82f6;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .system-status .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .system-status .status-item:last-child {
            margin-bottom: 0;
        }

        .status-indicator {
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .status-indicator.online {
            background-color: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .status-indicator.offline {
            background-color: rgba(248, 113, 113, 0.2);
            color: #f87171;
        }
    </style>
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
                    <li>
                        <a href="../../logout.php"><i class="fa-solid fa-right-from-bracket"></i>&nbsp;&nbsp;Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto - Investment</h1>
                    <p style="color: var(--clr-surface-a50);">Verwalte deine Kryptowährung-Investments - Live-Preise von CoinGecko</p>
                </div>
                <a href="add.php" class="btn">+ Neues Investment</a>
            </div>

            <?= $message ?>



            <!-- Investment Stats -->
            <?php if (!empty($investments)): ?>
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-value portfolio">
                            <?php if ($total_stats['total_current_value'] !== null): ?>
                                €<?= number_format($total_stats['total_current_value'], 2, ',', '.') ?>
                                <span class="data-status current">Live</span>
                            <?php else: ?>
                                <span class="price-unavailable">Nicht verfügbar</span>
                                <span class="data-status unavailable">API Fehler</span>
                            <?php endif; ?>
                        </div>
                        <div class="stat-label">Portfolio-Wert</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-value invested">€<?= number_format($total_stats['total_purchase_value'], 2, ',', '.') ?></div>
                        <div class="stat-label">Investiert</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-value <?= ($total_stats['total_profit_loss'] ?? 0) >= 0 ? 'profit' : 'loss' ?>">
                            <?php if ($total_stats['total_profit_loss'] !== null): ?>
                                <?= $total_stats['total_profit_loss'] >= 0 ? '+' : '' ?>€<?= number_format($total_stats['total_profit_loss'], 2, ',', '.') ?>
                            <?php else: ?>
                                <span class="price-unavailable">Nicht verfügbar</span>
                            <?php endif; ?>
                        </div>
                        <div class="stat-label">Gewinn/Verlust</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-value <?= ($total_stats['total_profit_loss_percent'] ?? 0) >= 0 ? 'profit' : 'loss' ?>">
                            <?php if ($total_stats['total_profit_loss_percent'] !== null): ?>
                                <?= $total_stats['total_profit_loss_percent'] >= 0 ? '+' : '' ?><?= number_format($total_stats['total_profit_loss_percent'], 2, ',', '.') ?>%
                            <?php else: ?>
                                <span class="price-unavailable">-</span>
                            <?php endif; ?>
                        </div>
                        <div class="stat-label">Performance</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Investments Table -->
            <div class="investments-table">
                <?php if (empty($investments)): ?>
                    <div class="empty-state">
                        <h3>📈 Noch keine Investments</h3>
                        <p>Füge dein erstes Krypto-Investment hinzu, um hier eine Übersicht zu sehen.</p>
                        <div style="margin-top: 20px;">
                            <a href="add.php" class="btn">Erstes Investment hinzufügen</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-header">
                        <div>Kryptowährung</div>
                        <div>Menge</div>
                        <div>Einkaufspreis</div>
                        <div>Aktueller Preis</div>
                        <div>Investiert</div>
                        <div>Aktueller Wert</div>
                        <div>Gewinn/Verlust</div>
                        <div>24h Änderung</div>
                        <div>Aktionen</div>
                    </div>

                    <?php foreach ($investments as $investment): ?>
                        <div class="investment-row <?= $investment['data_status'] !== 'current' ? 'api-error' : '' ?>">
                            <div class="crypto-info">
                                <div class="crypto-symbol"><?= htmlspecialchars(strtoupper($investment['symbol'])) ?></div>
                                <div class="crypto-name">
                                    <?= htmlspecialchars($investment['name']) ?>
                                    <?php if ($investment['data_status'] === 'current'): ?>
                                        <span class="data-status current">Live</span>
                                    <?php else: ?>
                                        <span class="data-status unavailable">Fehler</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="investment-amount">
                                <?= number_format($investment['amount'], 6, ',', '.') ?>
                            </div>

                            <div class="price">
                                €<?= number_format($investment['purchase_price'], 2, ',', '.') ?>
                            </div>

                            <div class="price">
                                <?php if ($investment['current_price'] !== null): ?>
                                    €<?= number_format($investment['current_price'], 2, ',', '.') ?>
                                <?php else: ?>
                                    <span class="price-unavailable">Nicht verfügbar</span>
                                <?php endif; ?>
                            </div>

                            <div class="value invested">
                                €<?= number_format($investment['purchase_value'], 2, ',', '.') ?>
                            </div>

                            <div class="value current">
                                <?php if ($investment['current_value'] !== null): ?>
                                    €<?= number_format($investment['current_value'], 2, ',', '.') ?>
                                <?php else: ?>
                                    <span class="price-unavailable">Nicht verfügbar</span>
                                <?php endif; ?>
                            </div>

                            <div class="profit-loss <?= ($investment['profit_loss'] ?? 0) >= 0 ? 'profit' : 'loss' ?>">
                                <?php if ($investment['profit_loss'] !== null): ?>
                                    <div class="amount">
                                        <?= $investment['profit_loss'] >= 0 ? '+' : '' ?>€<?= number_format($investment['profit_loss'], 2, ',', '.') ?>
                                    </div>
                                    <div class="percentage">
                                        <?= $investment['profit_loss_percent'] >= 0 ? '+' : '' ?><?= number_format($investment['profit_loss_percent'], 2, ',', '.') ?>%
                                    </div>
                                <?php else: ?>
                                    <span class="price-unavailable">Nicht verfügbar</span>
                                <?php endif; ?>
                            </div>

                            <div class="price-change <?= ($investment['price_change_24h'] ?? 0) >= 0 ? 'positive' : 'negative' ?>">
                                <?php if ($investment['price_change_24h'] !== null): ?>
                                    <?= $investment['price_change_24h'] >= 0 ? '+' : '' ?><?= number_format($investment['price_change_24h'], 2, ',', '.') ?>%
                                <?php else: ?>
                                    <span class="price-unavailable">-</span>
                                <?php endif; ?>
                            </div>

                            <div class="actions">
                                <a href="edit.php?id=<?= $investment['id'] ?>" class="btn btn-icon btn-edit" title="Bearbeiten"><i class="fa-solid fa-pen-to-square"></i></a>
                                <a href="delete.php?id=<?= $investment['id'] ?>" class="btn btn-icon btn-delete"> <i class="fa-solid fa-trash-can"></i></a>
                            </div>

                            <!-- API Error Details -->
                            <?php if ($investment['api_error']): ?>
                                <div class="api-error-details">
                                    <small style="color: #f87171;">
                                        ⚠️ <?= htmlspecialchars($investment['api_error']) ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- API Status Message -->
            <?= $api_status_message ?>

            <!-- System Status -->
            <div style="margin-top: 20px;" class="system-status">
                <div class="status-item">
                    <span><strong>🔌 API-Status:</strong></span>
                    <span>
                        <?php
                        require_once '../../config/crypto_api.php';
                        $crypto_api = new CryptoAPI();
                        if ($crypto_api->isApiAvailable()): ?>
                            <span style="border-radius: 20px;" class="status-indicator online">✅ CoinGecko erreichbar</span>
                        <?php else: ?>
                            <span style="border-radius: 20px;" class="status-indicator offline">❌ CoinGecko nicht erreichbar</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span><strong>💹 Preisdaten:</strong></span>
                    <span>
                        <?php if ($total_stats['data_status'] === 'current'): ?>
                            <span style="border-radius: 20px;" class="status-indicator online">Live-Preise aktiv</span>
                        <?php elseif ($total_stats['data_status'] === 'partial_data'): ?>
                            <span style="border-radius: 20px;" class="status-indicator offline">Teilweise verfügbar</span>
                        <?php else: ?>
                            <span style="border-radius: 20px;" class="status-indicator offline">Nicht verfügbar</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- Info Box -->
            <div class="info-box">
                <div class="info-title">💡 Investment-Tracking</div>
                <div class="info-text">
                    <?php if ($total_stats['data_status'] === 'current'): ?>
                        ✅ <strong>Live-Preise von CoinGecko</strong> - alle <?= $total_stats['investment_count'] ?> Investments aktuell.
                        <br>Die Preise werden alle 5 Minuten automatisch aktualisiert.
                    <?php elseif ($total_stats['data_status'] === 'partial_data'): ?>
                        ⚠️ <strong>Live-Preise teilweise verfügbar</strong> - <?= $total_stats['working_count'] ?> von <?= $total_stats['investment_count'] ?> Investments aktuell.
                        <br>Einige Symbole konnten nicht gefunden werden oder die API ist teilweise nicht erreichbar.
                    <?php else: ?>
                        ❌ <strong>Live-Preise nicht verfügbar</strong> - nur Einkaufspreise werden angezeigt.
                        <br>Die CoinGecko API ist momentan nicht erreichbar. Gewinn/Verlust kann nicht berechnet werden.
                    <?php endif; ?>
                    <br><br>
                    <strong>System-Details:</strong><br>
                    • Alle Investments sind für alle User sichtbar und gemeinsam verwaltbar<br>
                    • Der Gesamtwert wird automatisch in dein Vermögen im Dashboard eingerechnet<br>
                    • Cache-Zeit: 5 Minuten für optimale Performance
                    <?php if ($total_stats['data_status'] !== 'current'): ?>
                        <br><br>
                        <strong>Fehlerbehebung:</strong><br>
                        • Prüfe deine Internetverbindung<br>
                        • CoinGecko API könnte temporär überlastet sein<br>
                        • Versuche die Seite in ein paar Minuten zu aktualisieren
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>

</html