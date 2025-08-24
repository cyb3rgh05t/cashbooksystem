<?php

/**
 * Dashboard - Cashbook System
 * Updated mit Auth-Klasse
 */

require_once 'includes/auth.php';
require_once 'config/database.php';
require_once 'includes/timezone.php';
require_once 'includes/init_logger.php';
require_once 'includes/role_check.php';

// Require login
$auth->requireLogin();

// Initialisiere Zeitzone (prüft automatisch DB-Einstellungen)
TimezoneHelper::initializeTimezone();

// Get current user
$currentUser = $auth->getCurrentUser();
$user_id = $currentUser['id'];
$username = $currentUser['username'];

// Prüfe ob User gerade eingeloggt hat
$justLoggedIn = false;
if (isset($_SESSION['just_logged_in'])) {
    $justLoggedIn = true;
    unset($_SESSION['just_logged_in']); // Nur einmal verwenden
}

// Database connection
$db = new Database();
$pdo = $db->getConnection();

// Get user balance and statistics
$stmt = $pdo->prepare("
    SELECT 
        u.starting_balance,
        COALESCE(SUM(CASE WHEN c.type = 'income' THEN t.amount ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN c.type = 'expense' THEN t.amount ELSE 0 END), 0) as total_expense,
        COALESCE(SUM(CASE WHEN c.type = 'debt_in' THEN t.amount ELSE 0 END), 0) as total_debt_in,
        COALESCE(SUM(CASE WHEN c.type = 'debt_out' THEN t.amount ELSE 0 END), 0) as total_debt_out
    FROM users u
    LEFT JOIN transactions t ON u.id = t.user_id
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

// Calculate current balance
$current_balance = $stats['starting_balance'] + $stats['total_income'] - $stats['total_expense'];

// Get recent transactions
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name, c.icon, c.color, c.type
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ?
    ORDER BY t.date DESC, t.created_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_transactions = $stmt->fetchAll();

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

// Null-safe number formatting helper function
function formatNumber($number, $decimals = 2, $default = '0.00')
{
    return $number !== null ? number_format($number, $decimals, ',', '.') : $default;
}


// Automatisch fällige wiederkehrende Transaktionen verarbeiten beim Login
$processed_count = $db->processDueRecurringTransactions($user_id);

if ($processed_count > 0) {
    $_SESSION['success'] = "$processed_count wiederkehrende Transaktion(en) automatisch erstellt!";
}

// Dashboard-Statistiken laden
$current_month = date('Y-m');

// UPDATED: Verwende die neue getTotalWealth() Methode für alle Berechnungen
$wealth_data = $db->getTotalWealth($user_id);

// Monatliche Statistiken (aktueller Monat)
$total_income = $db->getTotalIncome($current_month);
$total_expenses = $db->getTotalExpenses($current_month);
$total_debt_in_month = $db->getTotalDebtIncoming($current_month);
$total_debt_out_month = $db->getTotalDebtOutgoing($current_month);

// Saldo berechnen (nur aktueller Monat)
$balance = $total_income - $total_expenses;
$debt_balance_month = $total_debt_in_month - $total_debt_out_month;

// UPDATED: Verwende die berechneten Werte aus getTotalWealth()
$starting_balance = $wealth_data['starting_balance'];
$total_income_all_time = $wealth_data['total_income'];
$total_expenses_all_time = $wealth_data['total_expenses'];
$total_debt_in = $wealth_data['total_debt_in'];
$total_debt_out = $wealth_data['total_debt_out'];
$net_debt_position = $wealth_data['net_debt_position'];
$total_investment_value = $wealth_data['total_investments'];
$total_wealth_with_investments = $wealth_data['total_wealth'];

// Lade Top Investments für die Anzeige
$all_investments = $db->getInvestmentsWithCurrentValue($user_id);
$top_investments = array_slice($all_investments, 0, 5); // Top 3 nehmen

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

// Recurring Transaction Statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_recurring,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_recurring,
        COUNT(CASE WHEN is_active = 1 AND next_due_date <= ? THEN 1 END) as due_soon,
        COUNT(CASE WHEN is_active = 1 AND next_due_date < ? THEN 1 END) as overdue
    FROM recurring_transactions
");

$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime('+7 days'));

$stmt->execute([$soon, $today]);
$recurring_stats = $stmt->fetch() ?: [
    'total_recurring' => 0,
    'active_recurring' => 0,
    'due_soon' => 0,
    'overdue' => 0
];

// FIXED: Letzte Transaktionen laden (gemeinsam für alle User)
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name, c.icon as category_icon, c.color as category_color, c.type as transaction_type
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    ORDER BY t.date DESC, t.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_transactions = $stmt->fetchAll();

// Fällige wiederkehrende Transaktionen für Info-Box
$due_recurring = $db->getDueRecurringTransactions($user_id, 3);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Meine Firma Finance</title>
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-..."
        crossorigin="anonymous"
        referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
</head>

<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-logo">
                    <img src="assets/images/logo.png" alt="Meine Firma Finance Logo" class="sidebar-logo-image">
                </a>
                <p class="sidebar-welcome">Willkommen, <?= htmlspecialchars($currentUser['full_name'] ?? $currentUser['username']) ?></p>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="dashboard.php" class="active"><i class="fa-solid fa-house"></i>&nbsp;&nbsp;Dashboard</a></li>

                    <?php if (canAccessModules($currentUser)): ?>
                        <li><a href="modules/expenses/index.php"><i class="fa-solid fa-money-bill-wave"></i>&nbsp;&nbsp;Ausgaben</a></li>
                        <li><a href="modules/income/index.php"><i class="fa-solid fa-sack-dollar"></i>&nbsp;&nbsp;Einnahmen</a></li>
                        <li><a href="modules/debts/index.php"><i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden</a></li>
                        <li><a href="modules/recurring/index.php"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend</a></li>
                        <li><a href="modules/investments/index.php"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto</a></li>
                        <li><a href="modules/categories/index.php"><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorien</a></li>
                    <?php endif; ?>
                    <li>
                        <a style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;" href="settings.php">
                            <i class="fa-solid fa-gear"></i>&nbsp;&nbsp;Einstellungen
                        </a>
                    </li>
                    <li><a href="modules/settings/license.php"><i class="fas fa-key"></i>&nbsp;&nbsp;Lizenz</a></li>
                    <li>
                        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i>&nbsp;&nbsp;Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="dashboard-header">
                <div class="welcome-text">
                    <h1><i class="fa-solid fa-house"></i>&nbsp;&nbsp;Dashboard</h1>
                    <p>Gemeinsamer Überblick über die Finanzen - <?= date('F Y') ?></p>
                </div>
                <?php if (canEditEntries($currentUser)): ?>
                    <div class="quick-actions">
                        <a href="modules/income/add.php" class="btn btn-primary">+ Einnahme</a>
                        <a href="modules/expenses/add.php" class="btn btn-secondary">+ Ausgabe</a>
                        <a href="modules/debts/add.php?type=debt_in" class="btn" style="background: #22c55e; color: white;">+ Geld erhalten / leihen</a>
                        <a href="modules/debts/add.php?type=debt_out" class="btn" style="background: #f97316; color: white;">+ Geld verleihen</a>
                        <a href="modules/investments/add.php" class="btn" style="background: #f59e0b; color: white;">+ Investment</a>
                    </div>
                <?php endif; ?>
            </div>

            <?= $message ?>

            <!-- Gesamtvermögen (Hauptkarte) -->
            <div class="wealth-card-container">
                <div class="wealth-card">
                    <div class="wealth-card-header">
                        <h2><i class="fa-solid fa-globe"></i> Gesamtvermögen</h2>
                        <div style="color: var(--clr-surface-a50); font-size: 14px;">
                            Stand: <?= TimezoneHelper::getCurrentUserTime('d.m.Y H:i') ?>
                        </div>
                    </div>

                    <div class="wealth-value">
                        €<?= formatNumber($total_wealth_with_investments) ?>
                    </div>

                    <div class="wealth-breakdown">
                        <div class="breakdown-item">
                            <div class="breakdown-value">€<?= formatNumber($starting_balance) ?></div>
                            <div class="breakdown-label">Startkapital</div>
                        </div>
                        <div class="breakdown-item">
                            <div class="breakdown-value positive">+€<?= formatNumber($total_income_all_time) ?></div>
                            <div class="breakdown-label">Gesamt Einnahmen</div>
                        </div>
                        <div class="breakdown-item">
                            <div class="breakdown-value negative">-€<?= formatNumber($total_expenses_all_time) ?></div>
                            <div class="breakdown-label">Gesamt Ausgaben</div>
                        </div>

                        <!-- NEUE Schulden-Anzeige -->
                        <?php if ($total_debt_in > 0 || $total_debt_out > 0): ?>
                            <div class="breakdown-item">
                                <div class="breakdown-value debt-positive">+€<?= formatNumber($total_debt_in) ?></div>
                                <div class="breakdown-label">Erhaltenes Geld</div>
                            </div>
                            <div class="breakdown-item">
                                <div class="breakdown-value debt-negative">-€<?= formatNumber($total_debt_out) ?></div>
                                <div class="breakdown-label">Verliehenes Geld</div>
                            </div>
                        <?php endif; ?>

                        <?php if ($total_investment_value > 0): ?>
                            <div class="breakdown-item">
                                <div class="breakdown-value positive">+€<?= formatNumber($total_investment_value) ?></div>
                                <div class="breakdown-label">Investments</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Monatsstatistiken -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                        <div class="stat-title">Einnahmen diesen Monat</div>
                    </div>
                    <div class="stat-value income">+€<?= formatNumber($total_income) ?></div>
                    <div class="stat-subtitle">Einnahmen</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
                        <div class="stat-title">Ausgaben diesen Monat</div>
                    </div>
                    <div class="stat-value expense">-€<?= formatNumber($total_expenses) ?></div>
                    <div class="stat-subtitle">Ausgaben</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon"><i class="fa-solid fa-money-check-dollar"></i></div>
                        <div class="stat-title">Monatssaldo</div>
                    </div>
                    <div class="stat-value <?= $balance >= 0 ? 'positive' : 'negative' ?>">
                        <?= $balance >= 0 ? '+' : '' ?>€<?= formatNumber($balance) ?>
                    </div>
                    <div class="stat-subtitle">Einnahmen - Ausgaben</div>
                </div>

                <!-- NEUE Schulden-Statistik für diesen Monat -->
                <?php if ($total_debt_in_month > 0 || $total_debt_out_month > 0): ?>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fa-solid fa-handshake"></i></div>
                            <div class="stat-title">Schulden-Saldo</div>
                        </div>
                        <div class="stat-value <?= $debt_balance_month >= 0 ? 'debt-positive' : 'debt-negative' ?>">
                            <?= $debt_balance_month >= 0 ? '+' : '' ?>€<?= formatNumber($debt_balance_month) ?>
                        </div>
                        <div class="stat-subtitle">Dieser Monat</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Content Grid -->
            <div class="dashboard-grid">
                <!-- Letzte Transaktionen -->
                <div class="card">
                    <div class="card-header">
                        <h3 style="color: var(--clr-primary-a20);"><i class="fa-solid fa-clock-rotate-left"></i> Letzte Transaktionen</h3>
                        <a href="modules/expenses/index.php" style="color: var(--clr-primary-a20); text-decoration: none; font-size: 14px;">
                            Alle anzeigen →
                        </a>
                    </div>

                    <?php if (empty($recent_transactions)): ?>
                        <div class="empty-state">
                            <h3>Keine Transaktionen</h3>
                            <p>Erstelle deine erste Transaktion!</p>
                            <a href="modules/expenses/add.php" class="btn btn-small">Transaktion hinzufügen</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_transactions as $transaction): ?>
                            <div class="transaction-item">
                                <div class="transaction-info">
                                    <div class="transaction-title">
                                        <?= htmlspecialchars($transaction['note'] ?: $transaction['category_name']) ?>
                                    </div>
                                    <div class="transaction-meta">
                                        <?= htmlspecialchars($transaction['category_name']) ?> •
                                        <?= TimezoneHelper::convertToUserTimezone($transaction['date'], 'd.m.Y') ?>
                                    </div>
                                </div>
                                <div class="transaction-amount <?= $transaction['transaction_type'] ?>">
                                    <?php
                                    $prefix = '';
                                    switch ($transaction['transaction_type']) {
                                        case 'income':
                                        case 'debt_in':
                                            $prefix = '+';
                                            break;
                                        case 'expense':
                                        case 'debt_out':
                                            $prefix = '-';
                                            break;
                                    }
                                    ?>
                                    <?= $prefix ?>€<?= number_format($transaction['amount'], 2, ',', '.') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Top Investments -->
                <?php if (!empty($top_investments)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 style="color: var(--clr-primary-a20);"><i class="fa-brands fa-btc"></i> Top Investments</h3>
                            <a href="modules/investments/index.php" style="color: var(--clr-primary-a20); text-decoration: none; font-size: 14px;">
                                Alle anzeigen →
                            </a>
                        </div>

                        <?php foreach ($top_investments as $investment): ?>
                            <div class="investment-item">
                                <div class="investment-info">
                                    <div class="investment-symbol"><?= htmlspecialchars($investment['symbol']) ?></div>
                                    <div class="investment-name"><?= htmlspecialchars($investment['name']) ?></div>
                                </div>
                                <div class="investment-value">
                                    <div class="investment-current">
                                        <?php if (($investment['current_value'] ?? null) !== null): ?>
                                            €<?= formatNumber($investment['current_value']) ?>
                                        <?php else: ?>
                                            <span class="price-unavailable">Nicht verfügbar</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="investment-change <?= (($investment['profit_loss_percent'] ?? 0) >= 0) ? 'positive' : 'negative' ?>">
                                        <?php if (($investment['profit_loss_percent'] ?? null) !== null): ?>
                                            <?= ($investment['profit_loss_percent'] ?? 0) >= 0 ? '+' : '' ?><?= formatNumber($investment['profit_loss_percent']) ?>%
                                        <?php else: ?>
                                            <span class="price-unavailable">-</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Info-Box wenn keine Investments -->
                    <div class="card">
                        <div class="card-header">
                            <h3 style="color: var(--clr-primary-a20);"><i class="fa-brands fa-btc"></i> Investments</h3>
                        </div>
                        <div class="empty-state">
                            <h3>Keine Investments</h3>
                            <p>Erstelle dein erstes Crypto-Investment!</p>
                            <a href="modules/investments/add.php" class="btn btn-small">Investment hinzufügen</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Verbesserte Schulden-Übersicht (nur anzeigen wenn Schulden vorhanden) -->
            <?php if ($total_debt_in > 0 || $total_debt_out > 0): ?>
                <div class="debt-overview-card">
                    <div class="card-header-modern">
                        <div class="header-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <h3 class="header-title">Schulden-Übersicht</h3>
                    </div>

                    <div class="debt-stats-grid">
                        <div class="debt-stat-card positive">
                            <div class="stat-icon-modern">
                                <i class="fas fa-arrow-down"></i>
                            </div>
                            <div class="stat-value-modern positive">+€<?= formatNumber($total_debt_in) ?></div>
                            <div class="stat-label-modern">Erhaltenes Geld</div>
                        </div>

                        <div class="debt-stat-card negative">
                            <div class="stat-icon-modern">
                                <i class="fas fa-arrow-up"></i>
                            </div>
                            <div class="stat-value-modern negative">-€<?= formatNumber($total_debt_out) ?></div>
                            <div class="stat-label-modern">Verliehenes Geld</div>
                        </div>

                        <div class="debt-stat-card <?= $net_debt_position >= 0 ? 'neutral' : 'negative' ?>">
                            <div class="stat-icon-modern">
                                <i class="fas fa-balance-scale"></i>
                            </div>
                            <div class="stat-value-modern <?= $net_debt_position >= 0 ? 'neutral' : 'negative' ?>">
                                <?= $net_debt_position >= 0 ? '+' : '' ?>€<?= formatNumber($net_debt_position) ?>
                            </div>
                            <div class="stat-label-modern">Netto-Position</div>
                        </div>
                    </div>

                    <div class="info-box-modern">
                        <div class="info-content">
                            <div class="info-icon-large">
                                <i class="fas fa-lightbulb"></i>
                            </div>
                            <div class="info-text-content">
                                <div class="info-title-modern">
                                    <?php if ($net_debt_position > 0): ?>
                                        💡 Du hast mehr Geld erhalten als verliehen
                                    <?php elseif ($net_debt_position < 0): ?>
                                        💡 Du hast mehr Geld verliehen als erhalten
                                    <?php else: ?>
                                        💡 Deine Schulden-Position ist ausgeglichen
                                    <?php endif; ?>
                                </div>
                                <div class="info-description">
                                    <?php if ($net_debt_position > 0): ?>
                                        Das verbessert dein Gesamtvermögen! Du hast eine positive Schulden-Bilanz.
                                    <?php elseif ($net_debt_position < 0): ?>
                                        Das reduziert dein verfügbares Vermögen. Verwalte deine Schulden, um den Überblick zu behalten.
                                    <?php else: ?>
                                        Deine Einnahmen und Ausgaben durch Schulden gleichen sich aus.
                                    <?php endif; ?>
                                </div>
                                <a href="modules/debts/index.php" class="info-link">
                                    <span>Alle Schulden verwalten</span>
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Verbesserte Wiederkehrende Transaktionen (nur anzeigen wenn vorhanden) -->
            <?php if ($recurring_stats['total_recurring'] > 0): ?>
                <div class="wealth-calculation-card">
                    <div class="wealth-header">
                        <div class="header-icon">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <h3 class="header-title">Wiederkehrende Transaktionen</h3>
                    </div>

                    <!-- Statistik-Karten für wiederkehrende Transaktionen -->
                    <div class="debt-stats-grid">
                        <div class="debt-stat-card neutral">
                            <div class="stat-icon-modern">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-value-modern neutral"><?= $recurring_stats['active_recurring'] ?></div>
                            <div class="stat-label-modern">Aktive</div>
                        </div>

                        <div class="debt-stat-card <?= $recurring_stats['due_soon'] > 0 ? 'positive' : 'neutral' ?>">
                            <div class="stat-icon-modern">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-value-modern <?= $recurring_stats['due_soon'] > 0 ? 'positive' : 'neutral' ?>"><?= $recurring_stats['due_soon'] ?></div>
                            <div class="stat-label-modern">Fällig (7 Tage)</div>
                        </div>

                        <div class="debt-stat-card <?= $recurring_stats['overdue'] > 0 ? 'negative' : 'neutral' ?>">
                            <div class="stat-icon-modern">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-value-modern <?= $recurring_stats['overdue'] > 0 ? 'negative' : 'neutral' ?>"><?= $recurring_stats['overdue'] ?></div>
                            <div class="stat-label-modern">Überfällig</div>
                        </div>
                    </div>

                    <?php if (!empty($due_recurring)): ?>
                        <div class="info-box-modern">
                            <div class="info-content">
                                <div class="info-icon-large">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <div class="info-text-content">
                                    <div class="info-title-modern">
                                        <?php if ($recurring_stats['overdue'] > 0): ?>
                                            ⚠️ Du hast überfällige wiederkehrende Transaktionen
                                        <?php elseif ($recurring_stats['due_soon'] > 0): ?>
                                            🔔 Wiederkehrende Transaktionen sind bald fällig
                                        <?php else: ?>
                                            ✅ Alle wiederkehrenden Transaktionen sind aktuell
                                        <?php endif; ?>
                                    </div>
                                    <div class="info-description">
                                        <?php
                                        $displayed = 0;
                                        foreach ($due_recurring as $due):
                                            if ($displayed >= 3) break;
                                            $displayed++;
                                        ?>
                                            • <?= htmlspecialchars($due['category_name']) ?>: €<?= number_format($due['amount'], 2, ',', '.') ?>
                                            (<?= date('d.m.Y', strtotime($due['next_due_date'])) ?>)<br>
                                        <?php endforeach; ?>
                                        <?php if (count($due_recurring) > 3): ?>
                                            <em>... und <?= count($due_recurring) - 3 ?> weitere</em><br>
                                        <?php endif; ?>
                                    </div>
                                    <a href="modules/recurring/index.php" class="info-link">
                                        <span>Alle wiederkehrenden Transaktionen verwalten</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="info-box-modern">
                            <div class="info-content">
                                <div class="info-icon-large">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="info-text-content">
                                    <div class="info-title-modern">
                                        ✅ Keine fälligen Transaktionen
                                    </div>
                                    <div class="info-description">
                                        Du hast <?= $recurring_stats['active_recurring'] ?> aktive wiederkehrende Transaktion<?= $recurring_stats['active_recurring'] != 1 ? 'en' : '' ?>,
                                        aber momentan ist nichts fällig. Alles im grünen Bereich!
                                    </div>
                                    <a href="modules/recurring/index.php" class="info-link">
                                        <span>Wiederkehrende Transaktionen verwalten</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

    </div>
    </div>
    </main>
    </div>
    <?php if ($justLoggedIn): ?>
        <!-- NUR nach dem Login: Sende Browser-Zeitzone -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Erkenne Browser-Zeitzone
                const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                console.log('Login erkannt - prüfe Zeitzone:', browserTimezone);

                // Sende an Server (wird nur übernommen wenn nicht manuell gesetzt)
                fetch('ajax/set_timezone.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            timezone: browserTimezone,
                            force: false // Respektiere manuelle Einstellungen
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('✅ Browser-Zeitzone übernommen:', data.timezone);
                            // Optional: Seite neu laden für korrekte Zeitanzeige
                            if (data.timezone !== '<?= TimezoneHelper::getCurrentTimezone() ?>') {
                                location.reload();
                            }
                        } else if (data.manually_set) {
                            console.log('ℹ️ Behalte manuelle Einstellung:', data.current_timezone);
                        }
                    })
                    .catch(error => {
                        console.error('Fehler:', error);
                    });
            });
        </script>
    <?php endif; ?>
    <?php include 'includes/debug_widget.php'; ?>
</body>

</html>