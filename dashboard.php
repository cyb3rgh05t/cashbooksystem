<?php

/**
 * Dashboard - Cashbook System
 * Updated mit Auth-Klasse
 */

require_once 'includes/auth.php';
require_once 'config/database.php';

// Require login mit Auth-Klasse
$auth->requireLogin();

// Get current user
$currentUser = $auth->getCurrentUser();
$user_id = $currentUser['id'];
$username = $currentUser['username'];

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
    <style>
        .debt-overview-card {
            background: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .debt-overview-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .card-header-modern {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--clr-surface-a20);
        }

        .header-icon {
            width: 40px;
            height: 40px;
            background: var(--clr-primary-a0);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--clr-dark-a0);
            font-size: 18px;
        }

        .header-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--clr-primary-a20);
            margin: 0;
        }

        /* Verbesserte Statistik-Karten */
        .debt-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .debt-stat-card {
            background: var(--clr-surface-a20);
            border: 1px solid var(--clr-surface-a30);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .debt-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
            background: var(--clr-surface-a30);
        }

        .debt-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .debt-stat-card.positive::before {
            background: #22c55e;
        }

        .debt-stat-card.negative::before {
            background: #f97316;
        }

        .debt-stat-card.neutral::before {
            background: var(--clr-primary-a0);
        }

        .stat-icon-modern {
            width: 36px;
            height: 36px;
            margin: 0 auto 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .positive .stat-icon-modern {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
        }

        .negative .stat-icon-modern {
            background: rgba(249, 115, 22, 0.15);
            color: #f97316;
        }

        .neutral .stat-icon-modern {
            background: rgba(230, 163, 9, 0.15);
            color: var(--clr-primary-a0);
        }

        .stat-value-modern {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 6px;
            line-height: 1;
        }

        .stat-value-modern.positive {
            color: #22c55e;
        }

        .stat-value-modern.negative {
            color: #f97316;
        }

        .stat-value-modern.neutral {
            color: var(--clr-primary-a20);
        }

        .stat-label-modern {
            color: var(--clr-surface-a50);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Verbesserte Info-Box */
        .info-box-modern {
            background: var(--clr-surface-tonal-a10);
            border: 1px solid var(--clr-surface-tonal-a20);
            border-radius: 8px;
            padding: 16px;
        }

        .info-content {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .info-icon-large {
            width: 36px;
            height: 36px;
            background: var(--clr-primary-a0);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--clr-dark-a0);
            font-size: 16px;
            flex-shrink: 0;
        }

        .info-text-content {
            flex: 1;
        }

        .info-title-modern {
            font-size: 1rem;
            font-weight: 600;
            color: var(--clr-primary-a20);
            margin-bottom: 6px;
        }

        .info-description {
            color: var(--clr-surface-a50);
            line-height: 1.5;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }

        .info-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--clr-primary-a20);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .info-link:hover {
            color: var(--clr-primary-a0);
            gap: 8px;
        }

        /* Vermögensberechnung */
        .wealth-calculation-card {
            background: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .wealth-calculation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .wealth-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--clr-surface-a20);
        }

        .wealth-header .header-icon {
            background: var(--clr-primary-a0);
        }

        .formula-container {
            background: var(--clr-surface-a20);
            border: 1px solid var(--clr-surface-a30);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .formula-title {
            font-weight: 600;
            color: var(--clr-primary-a20);
            margin-bottom: 12px;
            font-size: 1rem;
        }

        .formula-content {
            font-family: 'Courier New', monospace;
            background: var(--clr-surface-a30);
            padding: 16px;
            border-radius: 6px;
            border-left: 4px solid var(--clr-primary-a0);
            line-height: 1.6;
            color: var(--clr-light-a0);
            font-size: 0.9rem;
        }

        .formula-result {
            background: linear-gradient(135deg, var(--clr-primary-a0) 0%, var(--clr-primary-a10) 100%);
            color: var(--clr-dark-a0);
            padding: 12px 20px;
            border-radius: 6px;
            text-align: center;
            font-weight: 700;
            font-size: 1.1rem;
            margin-top: 12px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .debt-stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .info-content {
                flex-direction: column;
                text-align: left;
            }

            .card-header-modern,
            .wealth-header {
                justify-content: flex-start;
            }

            .debt-overview-card,
            .wealth-calculation-card {
                padding: 16px;
            }
        }
    </style>
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
                    <li><a href="modules/expenses/index.php"><i class="fa-solid fa-money-bill-wave"></i>&nbsp;&nbsp;Ausgaben</a></li>
                    <li><a href="modules/income/index.php"><i class="fa-solid fa-sack-dollar"></i>&nbsp;&nbsp;Einnahmen</a></li>
                    <li><a href="modules/debts/index.php"><i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden</a></li>
                    <li><a href="modules/recurring/index.php"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend</a></li>
                    <li><a href="modules/investments/index.php"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto</a></li>
                    <li><a href="modules/categories/index.php"><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorien</a></li>
                    <li>
                        <a style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;" href="settings.php">
                            <i class="fa-solid fa-gear"></i>&nbsp;&nbsp;Einstellungen
                        </a>
                    </li>
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
                <div class="quick-actions">
                    <a href="modules/income/add.php" class="btn btn-primary">+ Einnahme</a>
                    <a href="modules/expenses/add.php" class="btn btn-secondary">+ Ausgabe</a>
                    <a href="modules/debts/add.php?type=debt_in" class="btn" style="background: #22c55e; color: white;">+ Geld erhalten / leihen</a>
                    <a href="modules/debts/add.php?type=debt_out" class="btn" style="background: #f97316; color: white;">+ Geld verleihen</a>
                    <a href="modules/investments/add.php" class="btn" style="background: #f59e0b; color: white;">+ Investment</a>
                </div>
            </div>

            <?= $message ?>

            <!-- Gesamtvermögen (Hauptkarte) -->
            <div class="wealth-card-container">
                <div class="wealth-card">
                    <div class="wealth-card-header">
                        <h2><i class="fa-solid fa-globe"></i> Gesamtvermögen</h2>
                        <div style="color: var(--clr-surface-a50); font-size: 14px;">
                            Stand: <?= date('d.m.Y H:i') ?>
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
                                        <?= date('d.m.Y', strtotime($transaction['date'])) ?>
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

            <?php
            // Ersetze den bestehenden Schulden-Übersicht und Vermögensberechnung Code in dashboard.php mit diesem:
            ?>

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

            <!-- Verbesserte Vermögensberechnung -->
            <div class="wealth-calculation-card">
                <div class="wealth-header">
                    <div class="header-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <h3 class="header-title">Wiederkehrende Transaktionen</h3>
                </div>

                <?php if (!empty($due_recurring)): ?>
                    <div class="info-box-modern">
                        <div class="info-content">

                        </div>
                        <div class="info-text-content">
                            <div class="info-title-modern">🔔 Fällige wiederkehrende Transaktionen</div>
                            <div class="info-description">
                                <?php foreach ($due_recurring as $due): ?>
                                    • <?= htmlspecialchars($due['category_name']) ?>: €<?= number_format($due['amount'], 2, ',', '.') ?> (<?= date('d.m.Y', strtotime($due['next_due_date'])) ?>)<br>
                                <?php endforeach; ?>
                                Vergiss nicht, deine wiederkehrenden Transaktionen zu überprüfen.
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
    </div>
    </main>
    </div>
</body>

</html>