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

// Automatisch fällige wiederkehrende Transaktionen verarbeiten
$processed_count = $db->processDueRecurringTransactions($user_id);

if ($processed_count > 0) {
    $_SESSION['success'] = "$processed_count wiederkehrende Transaktion(en) automatisch erstellt!";
}

// Filter-Parameter
$selected_type = $_GET['type'] ?? '';
$selected_frequency = $_GET['frequency'] ?? '';
$selected_status = $_GET['status'] ?? '';

// Wiederkehrende Transaktionen laden mit Filtern
$sql = "
    SELECT rt.*, c.name as category_name, c.icon as category_icon, c.color as category_color, c.type as transaction_type
    FROM recurring_transactions rt
    JOIN categories c ON rt.category_id = c.id
    WHERE 1=1
";

$params = [];

if ($selected_type) {
    $sql .= " AND c.type = ?";
    $params[] = $selected_type;
}

if ($selected_frequency) {
    $sql .= " AND rt.frequency = ?";
    $params[] = $selected_frequency;
}

if ($selected_status !== '') {
    $sql .= " AND rt.is_active = ?";
    $params[] = $selected_status;
}

$sql .= " ORDER BY rt.next_due_date ASC, rt.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recurring_transactions = $stmt->fetchAll();

// Statistiken
$active_count = count(array_filter($recurring_transactions, fn($r) => $r['is_active']));
$income_count = count(array_filter($recurring_transactions, fn($r) => $r['transaction_type'] === 'income'));
$expense_count = count(array_filter($recurring_transactions, fn($r) => $r['transaction_type'] === 'expense'));

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

// Häufigkeits-Labels
$frequency_labels = [
    'daily' => 'Täglich',
    'weekly' => 'Wöchentlich',
    'monthly' => 'Monatlich',
    'yearly' => 'Jährlich'
];
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wiederkehrende Transaktionen - Meine Firma Finance</title>
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-..."
        crossorigin="anonymous"
        referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/recurring.css">
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
                    <li><a href="index.php" class="active"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend</a></li>
                    <li><a href="../investments/index.php"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto</a></li>
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
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend Transaktionen</h1>
                    <p style="color: var(--clr-surface-a50);">Automatisiere deine regelmäßigen Einnahmen und Ausgaben</p>
                </div>
                <a href="add.php" class="btn">+ Neue wiederkehrende Transaktion</a>
            </div>

            <?= $message ?>

            <!-- Quick Stats -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-value total"><?= count($recurring_transactions) ?></div>
                    <div class="stat-label">Gesamt</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value active"><?= $active_count ?></div>
                    <div class="stat-label">Aktiv</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value income"><?= $income_count ?></div>
                    <div class="stat-label">Einnahmen</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value expense"><?= $expense_count ?></div>
                    <div class="stat-label">Ausgaben</div>
                </div>
            </div>

            <!-- Filter Section -->
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label class="filter-label">Typ</label>
                    <select name="type" class="form-select">
                        <option value="">Alle Typen</option>
                        <option value="income" <?= $selected_type === 'income' ? 'selected' : '' ?>>Einnahmen</option>
                        <option value="expense" <?= $selected_type === 'expense' ? 'selected' : '' ?>>Ausgaben</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Häufigkeit</label>
                    <select name="frequency" class="form-select">
                        <option value="">Alle Häufigkeiten</option>
                        <option value="daily" <?= $selected_frequency === 'daily' ? 'selected' : '' ?>>Täglich</option>
                        <option value="weekly" <?= $selected_frequency === 'weekly' ? 'selected' : '' ?>>Wöchentlich</option>
                        <option value="monthly" <?= $selected_frequency === 'monthly' ? 'selected' : '' ?>>Monatlich</option>
                        <option value="yearly" <?= $selected_frequency === 'yearly' ? 'selected' : '' ?>>Jährlich</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Alle Status</option>
                        <option value="1" <?= $selected_status === '1' ? 'selected' : '' ?>>Aktiv</option>
                        <option value="0" <?= $selected_status === '0' ? 'selected' : '' ?>>Inaktiv</option>
                    </select>
                </div>

                <div class="filter-group">
                    <button type="submit" class="btn">🔍 Filtern</button>
                </div>
            </form>

            <!-- Recurring Transactions Table -->
            <div class="recurring-table">
                <?php if (empty($recurring_transactions)): ?>
                    <div class="empty-state">
                        <h3>🔄 Noch keine wiederkehrenden Transaktionen</h3>
                        <p>Automatisiere deine regelmäßigen Einnahmen und Ausgaben.</p>
                        <div style="margin-top: 20px;">
                            <a href="add.php" class="btn">Erste wiederkehrende Transaktion erstellen</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-header">
                        <div>Icon</div>
                        <div>Beschreibung</div>
                        <div>Betrag</div>
                        <div>Häufigkeit</div>
                        <div>Nächste Fälligkeit</div>
                        <div>Status</div>
                        <div>Erstellt</div>
                        <div>Aktionen</div>
                    </div>

                    <?php foreach ($recurring_transactions as $recurring): ?>
                        <?php
                        $today = date('Y-m-d');
                        $next_due = $recurring['next_due_date'];
                        $is_overdue = $next_due < $today;
                        $is_due_soon = $next_due <= date('Y-m-d', strtotime('+3 days'));
                        ?>
                        <div class="recurring-row <?= !$recurring['is_active'] ? 'inactive' : '' ?>">
                            <div class="category-icon" style="background-color: <?= htmlspecialchars($recurring['category_color']) ?>;">
                                <?= $recurring['category_icon'] ?>
                            </div>


                            <div>
                                <div style="font-weight: 500; color: var(--clr-light-a0); margin-bottom: 4px;">
                                    <?= htmlspecialchars($recurring['note']) ?: 'Keine Beschreibung' ?>
                                </div>
                                <span class="category-badge" style="background-color: <?= htmlspecialchars($recurring['category_color']) ?>20; color: <?= htmlspecialchars($recurring['category_color']) ?>;">
                                    <?= $recurring['category_icon'] ?> <?= htmlspecialchars($recurring['category_name']) ?>
                                </span>
                            </div>

                            <div class="amount <?= $recurring['transaction_type'] ?>">
                                <?= $recurring['transaction_type'] === 'income' ? '+' : '-' ?>€<?= number_format($recurring['amount'], 2, ',', '.') ?>
                            </div>

                            <div>
                                <span class="frequency-badge">
                                    <?= $frequency_labels[$recurring['frequency']] ?>
                                </span>
                            </div>

                            <div class="next-due <?= $is_overdue ? 'overdue' : ($is_due_soon ? 'due-soon' : '') ?>">
                                <?= date('d.m.Y', strtotime($next_due)) ?>
                                <?php if ($is_overdue): ?>
                                    <br><small style="color: #f87171;">Überfällig</small>
                                <?php elseif ($is_due_soon): ?>
                                    <br><small style="color: #fbbf24;">Bald fällig</small>
                                <?php endif; ?>
                            </div>

                            <div>
                                <span class="status-badge <?= $recurring['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                    <?= $recurring['is_active'] ? '✅ Aktiv' : '⏸️ Pausiert' ?>
                                </span>
                            </div>

                            <div style="font-size: 14px; color: var(--clr-surface-a50);">
                                <?= date('d.m.Y', strtotime($recurring['created_at'])) ?>
                            </div>

                            <div class="actions">
                                <a href="edit.php?id=<?= $recurring['id'] ?>" class="btn btn-icon btn-edit" title="Bearbeiten"><i class="fa-solid fa-pen-to-square"></i></a>
                                <a href="toggle.php?id=<?= $recurring['id'] ?>" class="btn btn-icon btn-toggle"
                                    title="<?= $recurring['is_active'] ? 'Pausieren' : 'Aktivieren' ?>">
                                    <?= $recurring['is_active'] ? '<i class="fa-solid fa-pause"></i>' : '<i class="fa-solid fa-play"></i>' ?>
                                </a>
                                <a href="delete.php?id=<?= $recurring['id'] ?>" class="btn btn-icon btn-delete"> <i class="fa-solid fa-trash-can"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>