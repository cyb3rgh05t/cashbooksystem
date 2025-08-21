<?php
session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once '../../config/database.php';

$db = new Database();
$pdo = $db->getConnection();
$user_id = $_SESSION['user_id'];

$category_id = $_GET['id'] ?? '';

// Kategorie laden und Berechtigung prüfen
if (empty($category_id)) {
    $_SESSION['error'] = 'Keine Kategorie angegeben.';
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$category_id]);
$category = $stmt->fetch();

if (!$category) {
    $_SESSION['error'] = 'Kategorie nicht gefunden oder keine Berechtigung.';
    header('Location: index.php');
    exit;
}

// Statistiken zur Kategorie laden (FIXED: date statt transaction_date)
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as transaction_count,
        COALESCE(SUM(amount), 0) as total_amount,
        MAX(date) as last_used
    FROM transactions 
    WHERE category_id = ?
");
$stmt->execute([$category_id]);
$stats = $stmt->fetch();

// FontAwesome Icons statt Emojis
$predefined_icons = [
    // 💰 Finanzen & Business
    '<i class="fa-solid fa-sack-dollar"></i>',
    '<i class="fa-solid fa-briefcase"></i>',
    '<i class="fa-solid fa-chart-line"></i>',
    '<i class="fa-solid fa-chart-bar"></i>',
    '<i class="fa-solid fa-credit-card"></i>',
    '<i class="fa-solid fa-coins"></i>',
    '<i class="fa-solid fa-building"></i>',
    '<i class="fa-solid fa-handshake"></i>',
    '<i class="fa-solid fa-piggy-bank"></i>',
    '<i class="fa-solid fa-receipt"></i>',
    '<i class="fa-solid fa-calculator"></i>',
    '<i class="fa-solid fa-percent"></i>',

    // 🏠 Haushalt & Leben
    '<i class="fa-solid fa-house"></i>',
    '<i class="fa-solid fa-bed"></i>',
    '<i class="fa-solid fa-couch"></i>',
    '<i class="fa-solid fa-shower"></i>',
    '<i class="fa-solid fa-toilet"></i>',
    '<i class="fa-solid fa-broom"></i>',
    '<i class="fa-solid fa-soap"></i>',
    '<i class="fa-solid fa-lightbulb"></i>',
    '<i class="fa-solid fa-plug"></i>',
    '<i class="fa-solid fa-wrench"></i>',
    '<i class="fa-solid fa-hammer"></i>',
    '<i class="fa-solid fa-paint-roller"></i>',

    // 🚗 Transport & Reisen
    '<i class="fa-solid fa-car"></i>',
    '<i class="fa-solid fa-bicycle"></i>',
    '<i class="fa-solid fa-train"></i>',
    '<i class="fa-solid fa-bus"></i>',
    '<i class="fa-solid fa-plane"></i>',
    '<i class="fa-solid fa-ship"></i>',
    '<i class="fa-solid fa-motorcycle"></i>',
    '<i class="fa-solid fa-gas-pump"></i>',
    '<i class="fa-solid fa-parking"></i>',
    '<i class="fa-solid fa-taxi"></i>',
    '<i class="fa-solid fa-map-location-dot"></i>',
    '<i class="fa-solid fa-suitcase"></i>',

    // 🛒 Shopping & Lifestyle
    '<i class="fa-solid fa-cart-shopping"></i>',
    '<i class="fa-solid fa-bag-shopping"></i>',
    '<i class="fa-solid fa-store"></i>',
    '<i class="fa-solid fa-shirt"></i>',
    '<i class="fa-solid fa-gem"></i>',
    '<i class="fa-solid fa-glasses"></i>',
    '<i class="fa-solid fa-watch"></i>',
    '<i class="fa-solid fa-shoe-prints"></i>',
    '<i class="fa-solid fa-scissors"></i>',
    '<i class="fa-solid fa-spray-can"></i>',

    // 🍕 Essen & Trinken
    '<i class="fa-solid fa-pizza-slice"></i>',
    '<i class="fa-solid fa-burger"></i>',
    '<i class="fa-solid fa-utensils"></i>',
    '<i class="fa-solid fa-mug-hot"></i>',
    '<i class="fa-solid fa-wine-glass"></i>',
    '<i class="fa-solid fa-beer"></i>',
    '<i class="fa-solid fa-ice-cream"></i>',
    '<i class="fa-solid fa-cookie"></i>',
    '<i class="fa-solid fa-apple-whole"></i>',
    '<i class="fa-solid fa-carrot"></i>',
    '<i class="fa-solid fa-fish"></i>',
    '<i class="fa-solid fa-cheese"></i>',

    // 📱 Technologie
    '<i class="fa-solid fa-laptop"></i>',
    '<i class="fa-solid fa-mobile-screen"></i>',
    '<i class="fa-solid fa-desktop"></i>',
    '<i class="fa-solid fa-tablet"></i>',
    '<i class="fa-solid fa-headphones"></i>',
    '<i class="fa-solid fa-camera"></i>',
    '<i class="fa-solid fa-tv"></i>',
    '<i class="fa-solid fa-gamepad"></i>',
    '<i class="fa-solid fa-wifi"></i>',
    '<i class="fa-solid fa-phone"></i>',
    '<i class="fa-solid fa-microchip"></i>',
    '<i class="fa-solid fa-keyboard"></i>',

    // 🏥 Gesundheit & Wellness
    '<i class="fa-solid fa-hospital"></i>',
    '<i class="fa-solid fa-pills"></i>',
    '<i class="fa-solid fa-stethoscope"></i>',
    '<i class="fa-solid fa-heart-pulse"></i>',
    '<i class="fa-solid fa-tooth"></i>',
    '<i class="fa-solid fa-eye"></i>',
    '<i class="fa-solid fa-dumbbell"></i>',
    '<i class="fa-solid fa-spa"></i>',
    '<i class="fa-solid fa-leaf"></i>',

    // 🎓 Bildung & Arbeit
    '<i class="fa-solid fa-graduation-cap"></i>',
    '<i class="fa-solid fa-book"></i>',
    '<i class="fa-solid fa-pen"></i>',
    '<i class="fa-solid fa-chalkboard"></i>',
    '<i class="fa-solid fa-microscope"></i>',
    '<i class="fa-solid fa-flask"></i>',

    // 🎬 Entertainment
    '<i class="fa-solid fa-film"></i>',
    '<i class="fa-solid fa-music"></i>',
    '<i class="fa-solid fa-masks-theater"></i>',
    '<i class="fa-solid fa-ticket"></i>',
    '<i class="fa-solid fa-guitar"></i>',
    '<i class="fa-solid fa-headphones"></i>',

    // 🏆 Sport & Freizeit
    '<i class="fa-solid fa-trophy"></i>',
    '<i class="fa-solid fa-football"></i>',
    '<i class="fa-solid fa-basketball"></i>',
    '<i class="fa-solid fa-baseball"></i>',
    '<i class="fa-solid fa-golf-ball-tee"></i>',
    '<i class="fa-solid fa-tennis-ball"></i>',
    '<i class="fa-solid fa-volleyball"></i>',
    '<i class="fa-solid fa-chess"></i>',

    // 🐕 Tiere & Natur
    '<i class="fa-solid fa-dog"></i>',
    '<i class="fa-solid fa-cat"></i>',
    '<i class="fa-solid fa-fish"></i>',
    '<i class="fa-solid fa-bird"></i>',
    '<i class="fa-solid fa-tree"></i>',
    '<i class="fa-solid fa-seedling"></i>',

    // ⭐ Verschiedenes
    '<i class="fa-solid fa-star"></i>',
    '<i class="fa-solid fa-gift"></i>',
    '<i class="fa-solid fa-heart"></i>',
    '<i class="fa-solid fa-fire"></i>',
    '<i class="fa-solid fa-sun"></i>',
    '<i class="fa-solid fa-moon"></i>',
    '<i class="fa-solid fa-cloud"></i>',
    '<i class="fa-solid fa-umbrella"></i>',
    '<i class="fa-solid fa-key"></i>',
    '<i class="fa-solid fa-lock"></i>',
    '<i class="fa-solid fa-bell"></i>',
    '<i class="fa-solid fa-flag"></i>',
    '<i class="fa-solid fa-bullseye"></i>',
    '<i class="fa-solid fa-rocket"></i>',
    '<i class="fa-solid fa-globe"></i>',
    '<i class="fa-solid fa-folder"></i>'
];

$predefined_colors = [
    '#e6a309',
    '#ebad36',
    '#f0b753',
    '#f4c16c',
    '#f8cb85',
    '#fbd59d',
    '#ef4444',
    '#f97316',
    '#eab308',
    '#22c55e',
    '#06b6d4',
    '#3b82f6',
    '#8b5cf6',
    '#ec4899',
    '#f43f5e',
    '#84cc16',
    '#10b981',
    '#0ea5e9'
];

// Form-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $icon = $_POST['icon'] ?? '';
    $color = $_POST['color'] ?? '';

    $errors = [];

    // Validierung
    if (empty($name)) {
        $errors[] = 'Name ist erforderlich.';
    } elseif (strlen($name) < 2) {
        $errors[] = 'Name muss mindestens 2 Zeichen lang sein.';
    } elseif (strlen($name) > 50) {
        $errors[] = 'Name darf maximal 50 Zeichen lang sein.';
    }

    if (empty($icon)) {
        $errors[] = 'Bitte wähle ein Icon aus.';
    }

    if (empty($color)) {
        $errors[] = 'Bitte wähle eine Farbe aus.';
    } elseif (!preg_match('/^#[0-9a-f]{6}$/i', $color)) {
        $errors[] = 'Ungültiger Farbcode-Format.';
    }

    // Prüfe ob Name bereits existiert (für diesen Benutzer und Typ, außer bei der aktuellen Kategorie)
    if (!empty($name)) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND type = ? AND id != ?");
        $stmt->execute([$name, $category['type'], $category_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Eine andere Kategorie mit diesem Namen existiert bereits für diesen Typ.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE categories 
                SET name = ?, icon = ?, color = ?
                WHERE id = ?
            ");

            $stmt->execute([$name, $icon, $color, $category_id]);

            $_SESSION['success'] = 'Kategorie "' . htmlspecialchars($name) . '" erfolgreich aktualisiert!';
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
        }
    }

    // Update category data with form values on error
    $category['name'] = $name;
    $category['icon'] = $icon;
    $category['color'] = $color;
}

// Type mapping für Anzeige
$type_mapping = [
    'income' => '<i class="fa-solid fa-sack-dollar"></i> Einnahme',
    'expense' => '<i class="fa-solid fa-money-bill-wave"></i> Ausgabe',
    'debt_in' => '<i class="fa-solid fa-arrow-left"></i> Schuld Eingang',
    'debt_out' => '<i class="fa-solid fa-arrow-right"></i> Schuld Ausgang'
];
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategorie bearbeiten - Meine Firma Finance</title>
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
                <p class="sidebar-welcome">Willkommen, <?= htmlspecialchars($_SESSION['username']) ?></p>
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
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;"><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorie bearbeiten</h1>
                    <p style="color: var(--clr-surface-a50);">Aktualisiere die Details deiner Kategorie</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
            </div>

            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorie bearbeiten</h2>
                        <p>Ändere Name, Icon und Farbe deiner Kategorie</p>
                    </div>

                    <div class="current-info">
                        <h4>📋 Aktuelle Kategorie</h4>
                        <div class="current-category">
                            <div class="current-icon" style="background-color: <?= htmlspecialchars($category['color']) ?>;">
                                <?= $category['icon'] ?>
                            </div>
                            <div class="current-details">
                                <h5><?= htmlspecialchars($category['name']) ?></h5>
                                <span class="current-type <?= $category['type'] ?>">
                                    <?= $type_mapping[$category['type']] ?? $category['type'] ?>
                                </span>
                            </div>
                        </div>

                        <div class="stats-grid">
                            <div class="stat-item">
                                <strong>Transaktionen:</strong><br>
                                <span class="stat-value"><?= $stats['transaction_count'] ?></span>
                            </div>
                            <div class="stat-item">
                                <strong>Gesamtbetrag:</strong><br>
                                <span class="stat-value">€<?= number_format($stats['total_amount'], 2, ',', '.') ?></span>
                            </div>
                            <div class="stat-item">
                                <strong>Erstellt:</strong><br>
                                <span class="stat-value"><?= date('d.m.Y', strtotime($category['created_at'])) ?></span>
                            </div>
                            <div class="stat-item">
                                <strong>Zuletzt verwendet:</strong><br>
                                <span class="stat-value">
                                    <?= $stats['last_used'] ? date('d.m.Y', strtotime($stats['last_used'])) : 'Nie' ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($stats['transaction_count'] > 0): ?>
                            <div class="warning-box">
                                ⚠️ <strong>Hinweis:</strong> Diese Kategorie wird in <?= $stats['transaction_count'] ?> Transaktionen verwendet.
                                Änderungen wirken sich auf alle bestehenden Einträge aus.
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <strong>Fehler:</strong><br>
                            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="name">Name der Kategorie *</label>
                            <input type="text" id="name" name="name"
                                class="form-input"
                                value="<?= htmlspecialchars($category['name']) ?>"
                                placeholder="z.B. Lebensmittel, Gehalt, Miete..."
                                maxlength="50" required
                                oninput="updatePreview()">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Icon auswählen *</label>
                            <div class="icon-selector">
                                <?php foreach ($predefined_icons as $icon): ?>
                                    <div class="icon-option">
                                        <input type="radio" id="icon_<?= md5($icon) ?>" name="icon" value="<?= htmlspecialchars($icon) ?>"
                                            class="icon-radio" <?= $category['icon'] === $icon ? 'checked' : '' ?>
                                            onchange="updatePreview()">
                                        <label for="icon_<?= md5($icon) ?>" class="icon-label">
                                            <?= $icon ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Farbe auswählen *</label>
                            <div class="color-selector">
                                <?php foreach ($predefined_colors as $color): ?>
                                    <div class="color-option">
                                        <input type="radio" id="color_<?= substr($color, 1) ?>" name="color" value="<?= $color ?>"
                                            class="color-radio" <?= $category['color'] === $color ? 'checked' : '' ?>
                                            onchange="updatePreview()">
                                        <label for="color_<?= substr($color, 1) ?>" class="color-label"
                                            style="background-color: <?= $color ?>;"></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="preview-section">
                            <div class="preview-title">🔄 Neue Vorschau</div>
                            <div class="category-preview">
                                <div class="preview-icon" id="previewIcon" style="background-color: <?= htmlspecialchars($category['color']) ?>;">
                                    <?= $category['icon'] ?>
                                </div>
                                <div class="preview-name" id="previewName">
                                    <?= htmlspecialchars($category['name']) ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="index.php" class="btn btn-cancel">Abbrechen</a>
                            <?php if ($stats['transaction_count'] == 0): ?>
                                <a href="delete.php?id=<?= $category['id'] ?>" class="btn btn-delete"
                                    onclick="return confirm('Kategorie wirklich löschen?')">
                                    <i class="fa-solid fa-trash-can"></i> Löschen
                                </a>
                            <?php else: ?>
                                <button type="button" class="btn btn-delete" disabled title="Kategorie wird verwendet und kann nicht gelöscht werden">
                                    <i class="fa-solid fa-lock"></i> Wird verwendet
                                </button>
                            <?php endif; ?>
                            <button type="submit" class="btn"><i class="fa-solid fa-floppy-disk"></i> Änderungen speichern</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        function updatePreview() {
            const name = document.getElementById('name').value || '<?= htmlspecialchars($category['name']) ?>';
            const selectedIcon = document.querySelector('input[name="icon"]:checked');
            const selectedColor = document.querySelector('input[name="color"]:checked');

            document.getElementById('previewName').textContent = name;

            if (selectedIcon) {
                document.getElementById('previewIcon').innerHTML = selectedIcon.value;
            }

            if (selectedColor) {
                document.getElementById('previewIcon').style.backgroundColor = selectedColor.value;
            }
        }

        // Initial preview update
        document.addEventListener('DOMContentLoaded', function() {
            updatePreview();
        });
    </script>
</body>

</html>