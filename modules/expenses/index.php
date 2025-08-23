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
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Kategorien für Filter laden (nur Ausgaben-Kategorien) - FIXED: Keine user_id Filter
$stmt = $pdo->prepare("SELECT * FROM categories WHERE type = 'expense' ORDER BY name");
$stmt->execute(); // FIXED: Keine Parameter
$categories = $stmt->fetchAll();

// Ausgaben laden mit Filtern - FIXED: user_id Filter entfernt für gemeinsame Nutzung
$sql = "
    SELECT t.*, c.name as category_name, c.icon as category_icon, c.color as category_color
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE c.type = 'expense'
";

$params = [];

if ($selected_month) {
    $sql .= " AND strftime('%Y-%m', t.date) = ?";
    $params[] = $selected_month;
}

if ($selected_category) {
    $sql .= " AND t.category_id = ?";
    $params[] = $selected_category;
}

if ($search) {
    $sql .= " AND (t.note LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY t.date DESC, t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Summe der gefilterten Ausgaben
$total_filtered = array_sum(array_column($expenses, 'amount'));

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
    <title>Ausgaben - Meine Firma Finance</title>
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-..."
        crossorigin="anonymous"
        referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/expenses.css">
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
                    <li><a href="../expenses/index.php" class="active"><i class="fa-solid fa-money-bill-wave"></i>&nbsp;&nbsp;Ausgaben</a></li>
                    <li><a href="../income/index.php"><i class="fa-solid fa-sack-dollar"></i>&nbsp;&nbsp;Einnahmen</a></li>
                    <li><a href="../debts/index.php" class="<?= strpos($_SERVER['PHP_SELF'], 'debts') ? 'active' : '' ?>">
                            <i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden
                        </a></li>
                    <li><a href="../recurring/index.php"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend</a></li>
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
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;"><i class="fa-solid fa-money-bill-wave"></i>&nbsp;&nbsp;Ausgaben</h1>
                    <p style="color: var(--clr-surface-a50);">Verwalte deine Ausgaben und verfolge deine Kosten</p>
                </div>
                <a href="add.php" class="btn">+ Neue Ausgabe</a>
            </div>

            <?= $message ?>

            <!-- Quick Stats -->
            <?php if (!empty($expenses)): ?>
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-value">€<?= number_format($total_filtered, 2, ',', '.') ?></div>
                        <div class="stat-label">Gefilterte Summe</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= count($expenses) ?></div>
                        <div class="stat-label">Ausgaben gefunden</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">€<?= count($expenses) > 0 ? number_format($total_filtered / count($expenses), 2, ',', '.') : '0,00' ?></div>
                        <div class="stat-label">Durchschnitt</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= date('F Y', strtotime($selected_month . '-01')) ?></div>
                        <div class="stat-label">Zeitraum</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filter Section -->
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label class="filter-label">Monat</label>
                    <input type="month" name="month" value="<?= htmlspecialchars($selected_month) ?>" class="form-input">
                </div>

                <div class="filter-group">
                    <label class="filter-label">Kategorie</label>
                    <select name="category" class="form-select">
                        <option value="">Alle Kategorien</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= $selected_category == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Suche</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                        placeholder="Beschreibung oder Kategorie..." class="form-input">
                </div>

                <div class="filter-group">
                    <button type="submit" class="btn">🔍 Filtern</button>
                </div>
            </form>

            <!-- Expenses Table -->
            <div class="expenses-table">
                <?php if (empty($expenses)): ?>
                    <div class="empty-state">
                        <h3><i class="fa-solid fa-money-bill-wave"></i> Noch keine Ausgaben</h3>
                        <p>Füge deine erste Ausgabe hinzu, um hier eine Übersicht zu sehen.</p>
                        <div style="margin-top: 20px;">
                            <a href="add.php" class="btn">Erste Ausgabe hinzufügen</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-header">
                        <div>Icon</div>
                        <div>Beschreibung</div>
                        <div>Kategorie</div>
                        <div>Betrag</div>
                        <div>Datum</div>
                        <div>Aktionen</div>
                    </div>

                    <?php foreach ($expenses as $expense): ?>
                        <div class="expense-row">
                            <div class="category-icon" style="background-color: <?= htmlspecialchars($expense['category_color']) ?>;">
                                <?= $expense['category_icon'] ?>
                            </div>

                            <div class="expense-description">
                                <?= htmlspecialchars($expense['note']) ?: 'Keine Beschreibung' ?>
                            </div>

                            <div>
                                <span class="category-badge" style="background-color: <?= htmlspecialchars($expense['category_color']) ?>20; color: <?= htmlspecialchars($expense['category_color']) ?>;">
                                    <?= $expense['category_icon'] ?> <?= htmlspecialchars($expense['category_name']) ?>
                                </span>
                            </div>

                            <div class="expense-amount">
                                - €<?= number_format($expense['amount'], 2, ',', '.') ?>
                            </div>

                            <div class="expense-date">
                                <?= date('d.m.Y', strtotime($expense['date'])) ?>
                            </div>

                            <div class="actions">
                                <a href="edit.php?id=<?= $expense['id'] ?>" class="btn btn-icon btn-edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                <a href="delete.php?id=<?= $expense['id'] ?>" class="btn btn-icon btn-delete"> <i class="fa-solid fa-trash-can"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>