<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/init_logger.php';
require_once '../../includes/timezone.php';
TimezoneHelper::initializeTimezone();

// Require login mit Auth-Klasse
$auth->requireLogin();

// Get current user
$currentUser = $auth->getCurrentUser();
$user_id = $currentUser['id'];

// Database connection
$db = new Database();
$pdo = $db->getConnection();

// FIXED: Kategorien laden (ohne user_id Filter für gemeinsame Nutzung)
$stmt = $pdo->prepare("SELECT * FROM categories ORDER BY type, name");
$stmt->execute();
$all_categories = $stmt->fetchAll();

// Nach Typen trennen
$income_categories = [];
$expense_categories = [];
// NEUE ARRAYS FÜR SCHULDEN
$debt_in_categories = [];
$debt_out_categories = [];

// FIXED: Statistiken für jede Kategorie laden (ohne user_id Filter)
foreach ($all_categories as $category) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as total_amount,
            MAX(date) as last_used
        FROM transactions 
        WHERE category_id = ?
    ");
    $stmt->execute([$category['id']]);
    $stats = $stmt->fetch();

    // Sicherstellen dass alle Werte gesetzt sind
    $category['stats'] = [
        'transaction_count' => isset($stats['transaction_count']) ? (int)$stats['transaction_count'] : 0,
        'total_amount' => isset($stats['total_amount']) ? (float)$stats['total_amount'] : 0.00,
        'last_used' => isset($stats['last_used']) ? $stats['last_used'] : null
    ];

    // UPDATED: Nach Typ trennen mit switch statement
    switch ($category['type']) {
        case 'income':
            $income_categories[] = $category;
            break;
        case 'expense':
            $expense_categories[] = $category;
            break;
        case 'debt_in':
            $debt_in_categories[] = $category;
            break;
        case 'debt_out':
            $debt_out_categories[] = $category;
            break;
    }
}

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
    <title>Kategorien - Meine Firma Finance</title>
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-..."
        crossorigin="anonymous"
        referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/categories.css">
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
                    <li><a href="../debts/index.php"><i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden</a></li>
                    <li><a href="../recurring/index.php"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend</a></li>
                    <li><a href="../investments/index.php"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto</a></li>
                    <li><a href="index.php" class="active"><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorien</a></li>
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
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;"><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorien</h1>
                    <p style="color: var(--clr-surface-a50);">Verwalte die gemeinsamen Einnahmen- und Ausgaben-Kategorien
                    </p>
                </div>
                <a href="add.php" class="btn">+ Neue Kategorie</a>
            </div>

            <?= $message ?>



            <div class="categories-grid">
                <!-- Einnahmen-Kategorien -->
                <div class="category-section">
                    <div class="section-header">
                        <h2 class="section-title income"><i class="fa-solid fa-sack-dollar"></i>&nbsp;&nbsp;Einnahmen</h2>
                        <div class="section-stats">
                            <?= count($income_categories) ?> Kategorien
                        </div>
                    </div>

                    <?php if (empty($income_categories)): ?>
                        <div class="empty-section">
                            <h4>Noch keine Einnahmen-Kategorien</h4>
                            <p>Erstelle die erste gemeinsame Kategorie für Einnahmen.</p>
                            <div style="margin-top: 15px;">
                                <a href="add.php?type=income" class="btn btn-small">+ Einnahmen-Kategorie</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="category-list">
                            <?php foreach ($income_categories as $category): ?>
                                <div class="category-card">
                                    <div class="category-icon" style="background-color: <?= htmlspecialchars($category['color']) ?>;">
                                        <?= $category['icon'] ?>
                                    </div>

                                    <div class="category-info">
                                        <div class="category-name"><?= htmlspecialchars($category['name']) ?></div>
                                        <div class="category-usage">
                                            <?php if ($category['stats']['transaction_count'] > 0): ?>
                                                <?= $category['stats']['transaction_count'] ?> Transaktionen (alle User)
                                                • Zuletzt: <?= date('d.m.Y', strtotime($category['stats']['last_used'])) ?>
                                            <?php else: ?>
                                                Noch nicht verwendet
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="category-stats">
                                        <div class="stat-amount income">
                                            +€<?= number_format($category['stats']['total_amount'], 2, ',', '.') ?>
                                        </div>
                                        <div class="stat-count">
                                            <?= $category['stats']['transaction_count'] ?> Einträge
                                        </div>
                                    </div>

                                    <div class="category-actions">
                                        <a href="edit.php?id=<?= $category['id'] ?>" class="btn btn-icon btn-edit" title="Bearbeiten"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <?php if ($category['stats']['transaction_count'] == 0): ?>
                                            <a href="delete.php?id=<?= $category['id'] ?>" class="btn btn-icon btn-delete"> <i class="fa-solid fa-trash-can"></i></a>
                                        <?php else: ?>
                                            <button class="btn btn-icon btn-delete" disabled title="Kategorie wird verwendet und kann nicht gelöscht werden"> <i class="fa-solid fa-lock"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Ausgaben-Kategorien -->
                <div class="category-section">
                    <div class="section-header">
                        <h2 class="section-title expense"><i class="fa-solid fa-money-bill-wave"></i>&nbsp;&nbsp;Ausgaben</h2>
                        <div class="section-stats">
                            <?= count($expense_categories) ?> Kategorien
                        </div>
                    </div>

                    <?php if (empty($expense_categories)): ?>
                        <div class="empty-section">
                            <h4>Noch keine Ausgaben-Kategorien</h4>
                            <p>Erstelle die erste gemeinsame Kategorie für Ausgaben.</p>
                            <div style="margin-top: 15px;">
                                <a href="add.php?type=expense" class="btn btn-small">+ Ausgaben-Kategorie</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="category-list">
                            <?php foreach ($expense_categories as $category): ?>
                                <div class="category-card">
                                    <div class="category-icon" style="background-color: <?= htmlspecialchars($category['color']) ?>;">
                                        <?= $category['icon'] ?>
                                    </div>

                                    <div class="category-info">
                                        <div class="category-name"><?= htmlspecialchars($category['name']) ?></div>
                                        <div class="category-usage">
                                            <?php if ($category['stats']['transaction_count'] > 0): ?>
                                                <?= $category['stats']['transaction_count'] ?> Transaktionen (alle User)
                                                • Zuletzt: <?= date('d.m.Y', strtotime($category['stats']['last_used'])) ?>
                                            <?php else: ?>
                                                Noch nicht verwendet
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="category-stats">
                                        <div class="stat-amount expense">
                                            -€<?= number_format($category['stats']['total_amount'], 2, ',', '.') ?>
                                        </div>
                                        <div class="stat-count">
                                            <?= $category['stats']['transaction_count'] ?> Einträge
                                        </div>
                                    </div>

                                    <div class="category-actions">
                                        <a href="edit.php?id=<?= $category['id'] ?>" class="btn btn-icon btn-edit" title="Bearbeiten"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <?php if ($category['stats']['transaction_count'] == 0): ?>
                                            <a href="delete.php?id=<?= $category['id'] ?>" class="btn btn-icon btn-delete"
                                                ('Kategorie wirklich löschen? Dies betrifft alle User!')" title="Löschen"><i class="fa-solid fa-trash-can"></i></a>
                                        <?php else: ?>
                                            <button class="btn btn-icon btn-delete" disabled title="Kategorie wird verwendet und kann nicht gelöscht werden"> <i class="fa-solid fa-lock"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- NEUE SCHULDEN EINGANG SEKTION -->
                <div class="category-section">
                    <div class="section-header">
                        <h2 class="section-title income"><i class="fa-solid fa-arrow-left"></i>&nbsp;&nbsp;Schulden Eingang</h2>
                        <div class="section-stats">
                            <?= count($debt_in_categories) ?> Kategorien
                        </div>
                    </div>

                    <?php if (empty($debt_in_categories)): ?>
                        <div class="empty-section">
                            <h4>Noch keine Schulden-Eingang-Kategorien</h4>
                            <p>Erstelle Kategorien für erhaltenes Geld.</p>
                            <div style="margin-top: 15px;">
                                <a href="add.php?type=debt_in" class="btn btn-small">+ Eingangs-Kategorie</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="category-list">
                            <?php foreach ($debt_in_categories as $category): ?>
                                <div class="category-card">
                                    <div class="category-icon" style="background-color: <?= htmlspecialchars($category['color']) ?>;">
                                        <?= $category['icon'] ?>
                                    </div>

                                    <div class="category-info">
                                        <div class="category-name"><?= htmlspecialchars($category['name']) ?></div>
                                        <div class="category-usage">
                                            <?php if ($category['stats']['transaction_count'] > 0): ?>
                                                <?= $category['stats']['transaction_count'] ?> Transaktionen (alle User)
                                                • Zuletzt: <?= date('d.m.Y', strtotime($category['stats']['last_used'])) ?>
                                            <?php else: ?>
                                                Noch nicht verwendet
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="category-stats">
                                        <div class="stat-amount income">
                                            +€<?= number_format($category['stats']['total_amount'], 2, ',', '.') ?>
                                        </div>
                                        <div class="stat-count">
                                            <?= $category['stats']['transaction_count'] ?> Einträge
                                        </div>
                                    </div>

                                    <div class="category-actions">
                                        <a href="edit.php?id=<?= $category['id'] ?>" class="btn btn-icon btn-edit" title="Bearbeiten"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <?php if ($category['stats']['transaction_count'] == 0): ?>
                                            <a href="delete.php?id=<?= $category['id'] ?>" class="btn btn-icon btn-delete"
                                                ('Kategorie wirklich löschen? Dies betrifft alle User!')" title="Löschen"><i class="fa-solid fa-trash-can"></i></a>
                                        <?php else: ?>
                                            <button class="btn btn-icon btn-delete" disabled title="Kategorie wird verwendet und kann nicht gelöscht werden"> <i class="fa-solid fa-lock"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- NEUE SCHULDEN AUSGANG SEKTION -->
                <div class="category-section">
                    <div class="section-header">
                        <h2 class="section-title expense"><i class="fa-solid fa-arrow-right"></i>&nbsp;&nbsp;Schulden Ausgang</h2>
                        <div class="section-stats">
                            <?= count($debt_out_categories) ?> Kategorien
                        </div>
                    </div>

                    <?php if (empty($debt_out_categories)): ?>
                        <div class="empty-section">
                            <h4>Noch keine Schulden-Ausgang-Kategorien</h4>
                            <p>Erstelle Kategorien für verliehenes Geld.</p>
                            <div style="margin-top: 15px;">
                                <a href="add.php?type=debt_out" class="btn btn-small">+ Ausgangs-Kategorie</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="category-list">
                            <?php foreach ($debt_out_categories as $category): ?>
                                <div class="category-card">
                                    <div class="category-icon" style="background-color: <?= htmlspecialchars($category['color']) ?>;">
                                        <?= $category['icon'] ?>
                                    </div>

                                    <div class="category-info">
                                        <div class="category-name"><?= htmlspecialchars($category['name']) ?></div>
                                        <div class="category-usage">
                                            <?php if ($category['stats']['transaction_count'] > 0): ?>
                                                <?= $category['stats']['transaction_count'] ?> Transaktionen (alle User)
                                                • Zuletzt: <?= date('d.m.Y', strtotime($category['stats']['last_used'])) ?>
                                            <?php else: ?>
                                                Noch nicht verwendet
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="category-stats">
                                        <div class="stat-amount expense">
                                            -€<?= number_format($category['stats']['total_amount'], 2, ',', '.') ?>
                                        </div>
                                        <div class="stat-count">
                                            <?= $category['stats']['transaction_count'] ?> Einträge
                                        </div>
                                    </div>

                                    <div class="category-actions">
                                        <a href="edit.php?id=<?= $category['id'] ?>" class="btn btn-icon btn-edit" title="Bearbeiten"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <?php if ($category['stats']['transaction_count'] == 0): ?>
                                            <a href="delete.php?id=<?= $category['id'] ?>" class="btn btn-icon btn-delete"><i class="fa-solid fa-trash-can"></i></a>
                                        <?php else: ?>
                                            <button class="btn btn-icon btn-delete" disabled title="Kategorie wird verwendet und kann nicht gelöscht werden"> <i class="fa-solid fa-lock"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tips Section -->
            <div class="tips-section">
                <h3 class="tips-title">💡 Tipps für bessere Kategorien</h3>
                <div class="tips-grid">
                    <div class="tip-item">
                        <span class="tip-icon">🎯</span>
                        <strong>Spezifisch sein:</strong> Verwende aussagekräftige Namen wie "Lebensmittel" statt nur "Essen".
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">🎨</span>
                        <strong>Farben nutzen:</strong> Verwende ähnliche Farben für verwandte Kategorien.
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">📊</span>
                        <strong>Nicht zu viele:</strong> 5-10 Kategorien pro Typ reichen meist aus.
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">🤝</span>
                        <strong>Gemeinsam:</strong> Alle User verwenden dieselben Kategorien - stimmt euch ab!
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>