<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
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

// Filter-Parameter
$filter_month = $_GET['month'] ?? date('Y-m');

// Schulden-Kategorien laden (debt_in und debt_out)
$stmt = $pdo->prepare("SELECT * FROM categories WHERE type IN ('debt_in', 'debt_out') ORDER BY name");
$stmt->execute();
$debt_categories = $stmt->fetchAll();

// Outgoing Debts (Firma leiht Geld an andere)
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name, c.icon as category_icon, c.color as category_color
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE c.type = 'debt_out' AND strftime('%Y-%m', t.date) = ?
    ORDER BY t.date DESC, t.created_at DESC
");
$stmt->execute([$filter_month]);
$outgoing_debts = $stmt->fetchAll();

// Incoming Debts (Firma bekommt Geld von anderen)
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name, c.icon as category_icon, c.color as category_color
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE c.type = 'debt_in' AND strftime('%Y-%m', t.date) = ?
    ORDER BY t.date DESC, t.created_at DESC
");
$stmt->execute([$filter_month]);
$incoming_debts = $stmt->fetchAll();

// Statistiken
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(t.amount), 0) as total 
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE c.type = 'debt_out' AND strftime('%Y-%m', t.date) = ?
");
$stmt->execute([$filter_month]);
$total_outgoing = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(t.amount), 0) as total 
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE c.type = 'debt_in' AND strftime('%Y-%m', t.date) = ?
");
$stmt->execute([$filter_month]);
$total_incoming = $stmt->fetchColumn();

$net_position = $total_incoming - $total_outgoing;

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
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schulden & Darlehen - Meine Firma Finance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/debt.css">
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
                    <li><a href="index.php" class="active"><i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden</a></li>
                    <li><a href="../recurring/index.php"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend</a></li>
                    <li><a href="../investments/index.php"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto</a></li>
                    <li><a href="../categories/index.php"><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorien</a></li>
                    <li>
                        <a style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;" href="../../settings.php">
                            <i class="fa-solid fa-gear"></i>&nbsp;&nbsp;Einstellungen
                        </a>
                    </li>
                    <li><a href="modules/settings/license.php"><i class="fas fa-key"></i>&nbsp;&nbsp;Lizenz</a></li>
                    </li>
                    <li>
                        <a href="../../logout.php"><i class="fa-solid fa-right-from-bracket"></i>&nbsp;&nbsp;Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;">
                        <i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden & Darlehen
                    </h1>
                    <p style="color: var(--clr-surface-a50);">Verwalte Geldleihen zwischen Firma und Privatpersonen</p>
                </div>
                <div style="display: flex; gap: 12px;">
                    <a href="add.php?type=debt_out" class="btn btn-secondary">+ Geld verleihen</a>
                    <a href="add.php?type=debt_in" class="btn">+ Geld erhalten / leihen</a>
                </div>
            </div>

            <?= $message ?>

            <!-- Stats Cards -->
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 30px;">
                <div class="stat-card">

                    <div class="stat-value expense">€<?= number_format($total_outgoing, 2, ',', '.') ?></div>
                    <div class="stat-label">Verliehenes Geld</div>
                </div>

                <div class="stat-card">

                    <div class="stat-value income">€<?= number_format($total_incoming, 2, ',', '.') ?></div>
                    <div class="stat-label">Erhaltenes Geld</div>
                </div>

                <div class="stat-card">

                    <div class="stat-value <?= $net_position >= 0 ? 'income' : 'expense' ?>">
                        €<?= number_format($net_position, 2, ',', '.') ?>
                    </div>
                    <div class="stat-label">Netto-Position</div>
                </div>
            </div>

            <!-- Filter -->
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="form-group-inline">
                        <label for="month">Monat:</label>
                        <input type="month" id="month" name="month" value="<?= htmlspecialchars($filter_month) ?>" class="form-input">
                        <button type="submit" class="btn btn-small">Filtern</button>
                    </div>
                </form>
            </div>

            <!-- Transactions Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <!-- Outgoing Debts -->
                <div class="transaction-section">
                    <h2 style="color: #f97316; margin-bottom: 20px;">
                        <i class="fa-solid fa-arrow-right"></i> Verliehenes Geld
                    </h2>

                    <?php if (empty($outgoing_debts)): ?>
                        <div class="empty-state">
                            <h3>Keine Darlehen vergeben</h3>
                            <p>Du hast in diesem Monat kein Geld verliehen.</p>
                            <a href="add.php?type=debt_out" class="btn btn-small">+ Geld verleihen</a>
                        </div>
                    <?php else: ?>
                        <div class="transaction-list">
                            <?php foreach ($outgoing_debts as $debt): ?>
                                <div class="transaction-item">
                                    <div class="transaction-info">
                                        <div class="transaction-category">
                                            <span class="category-icon" style="background-color: <?= htmlspecialchars($debt['category_color']) ?>;">
                                                <?= $debt['category_icon'] ?>
                                            </span>
                                            <?= htmlspecialchars($debt['category_name']) ?>
                                        </div>
                                        <div class="transaction-note">
                                            <?= htmlspecialchars($debt['note'] ?: 'Keine Notiz') ?>
                                        </div>
                                        <div class="transaction-date">
                                            <?= date('d.m.Y', strtotime($debt['date'])) ?>
                                        </div>
                                    </div>
                                    <div class="transaction-amount expense">
                                        -€<?= number_format($debt['amount'], 2, ',', '.') ?>
                                    </div>
                                    <div class="transaction-actions">
                                        <a href="edit.php?id=<?= $debt['id'] ?>" class="btn btn-icon btn-edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <a href="delete.php?id=<?= $debt['id'] ?>"
                                            ('Sicher löschen?')"
                                            class="btn btn-icon btn-delete"><i class="fa-solid fa-trash-can"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Incoming Debts -->
                <div class="transaction-section">
                    <h2 style="color: #22c55e; margin-bottom: 20px;">
                        <i class="fa-solid fa-arrow-left"></i> Erhaltenes Geld
                    </h2>

                    <?php if (empty($incoming_debts)): ?>
                        <div class="empty-state">
                            <h3>Kein Geld erhalten</h3>
                            <p>Du hast in diesem Monat kein Geld geliehen bekommen.</p>
                            <a href="add.php?type=debt_in" class="btn btn-small">+ Geld erhalten / leihen</a>
                        </div>
                    <?php else: ?>
                        <div class="transaction-list">
                            <?php foreach ($incoming_debts as $debt): ?>
                                <div class="transaction-item">
                                    <div class="transaction-info">
                                        <div class="transaction-category">
                                            <span class="category-icon" style="background-color: <?= htmlspecialchars($debt['category_color']) ?>;">
                                                <?= $debt['category_icon'] ?>
                                            </span>
                                            <?= htmlspecialchars($debt['category_name']) ?>
                                        </div>
                                        <div class="transaction-note">
                                            <?= htmlspecialchars($debt['note'] ?: 'Keine Notiz') ?>
                                        </div>
                                        <div class="transaction-date">
                                            <?= date('d.m.Y', strtotime($debt['date'])) ?>
                                        </div>
                                    </div>
                                    <div class="transaction-amount income">
                                        +€<?= number_format($debt['amount'], 2, ',', '.') ?>
                                    </div>
                                    <div class="transaction-actions">
                                        <a href="edit.php?id=<?= $debt['id'] ?>" class="btn btn-icon btn-edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <a href="delete.php?id=<?= $debt['id'] ?>"

                                            class="btn btn-icon btn-delete"> <i class="fa-solid fa-trash-can"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>

</html>