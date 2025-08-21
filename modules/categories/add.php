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

// Form-Token generieren für Doppel-Submit Schutz
if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}

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

$errors = [];

// Form-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Token-Validierung (Schutz vor Doppel-Submit)
    $token = $_POST['form_token'] ?? '';
    if (!hash_equals($_SESSION['form_token'], $token)) {
        $errors[] = 'Ungültiges Formular. Bitte versuche es erneut.';
    } else {
        // Token verbrauchen (einmalige Verwendung)
        unset($_SESSION['form_token']);

        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? '';
        $icon = $_POST['icon'] ?? '';
        $color = $_POST['color'] ?? '';

        // Validierung
        if (empty($name)) {
            $errors[] = 'Name ist erforderlich.';
        } elseif (strlen($name) < 2) {
            $errors[] = 'Name muss mindestens 2 Zeichen lang sein.';
        } elseif (strlen($name) > 50) {
            $errors[] = 'Name darf maximal 50 Zeichen lang sein.';
        }

        // UPDATED: Schulden-Typen hinzugefügt
        if (empty($type) || !in_array($type, ['income', 'expense', 'debt_in', 'debt_out'])) {
            $errors[] = 'Bitte wähle einen gültigen Typ aus.';
        }

        if (empty($icon)) {
            $errors[] = 'Bitte wähle ein Icon aus.';
        }

        if (empty($color)) {
            $errors[] = 'Bitte wähle eine Farbe aus.';
        } elseif (!preg_match('/^#[0-9a-f]{6}$/i', $color)) {
            $errors[] = 'Ungültiger Farbcode-Format.';
        }

        // FIXED: Prüfe ob Name bereits existiert (ohne user_id Filter da gemeinsame Kategorien)
        if (!empty($name) && !empty($type)) {
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND type = ?");
            $stmt->execute([$name, $type]);
            if ($stmt->fetch()) {
                $errors[] = 'Eine Kategorie mit diesem Namen existiert bereits für diesen Typ.';
            }
        }

        if (empty($errors)) {
            try {
                // FIXED: Prüfe nochmals ob Kategorie bereits existiert (Race Condition Schutz)
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND type = ?");
                $stmt->execute([$name, $type]);
                if ($stmt->fetch()) {
                    $errors[] = 'Kategorie "' . htmlspecialchars($name) . '" existiert bereits.';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO categories (user_id, name, type, icon, color, created_at)
                        VALUES (?, ?, ?, ?, ?, datetime('now'))
                    ");

                    $stmt->execute([$user_id, $name, $type, $icon, $color]);

                    // Erfolg-Nachricht setzen und sofort weiterleiten (verhindert Doppel-Submit)
                    $_SESSION['success'] = 'Kategorie "' . htmlspecialchars($name) . '" erfolgreich erstellt und für alle User verfügbar!';

                    // JavaScript-basierte Weiterleitung + HTTP Header für Sicherheit
                    echo '<script>window.location.replace("index.php");</script>';
                    header('Location: index.php');
                    exit;
                }
            } catch (PDOException $e) {
                $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
            }
        }
    }
}

// Neues Token für nächste Form generieren
if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}

// Standardwerte für Formular
$form_data = [
    'name' => $_POST['name'] ?? '',
    'type' => $_POST['type'] ?? ($_GET['type'] ?? ''),
    'icon' => $_POST['icon'] ?? '<i class="fa-solid fa-folder"></i>',
    'color' => $_POST['color'] ?? '#e6a309'
];
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neue Kategorie - Meine Firma Finance</title>
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
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;"><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Neue Kategorie</h1>
                    <p style="color: var(--clr-surface-a50);">Erstelle eine neue gemeinsame Kategorie für alle User</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
            </div>

            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorie erstellen</h2>
                        <p>Definiere eine neue Kategorie mit Name, Typ, Icon und Farbe - wird für alle User sichtbar</p>
                    </div>

                    <!-- Shared Notice -->
                    <div style="background-color: rgba(59, 130, 246, 0.1); border: 1px solid #3b82f6; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                        <div style="color: #93c5fd; font-weight: 600; margin-bottom: 8px; font-size: 14px;">🤝 Gemeinsame Kategorie</div>
                        <div style="color: var(--clr-surface-a50); font-size: 13px;">
                            Diese Kategorie wird für alle registrierten User sichtbar und verwendbar sein.
                        </div>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <strong>Fehler:</strong><br>
                            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="categoryForm" onsubmit="return submitForm()">
                        <!-- CSRF-Token für Doppel-Submit Schutz -->
                        <input type="hidden" name="form_token" value="<?= htmlspecialchars($_SESSION['form_token']) ?>">

                        <div class="form-group">
                            <label class="form-label" for="name">Name der Kategorie *</label>
                            <input type="text" id="name" name="name"
                                class="form-input"
                                value="<?= htmlspecialchars($form_data['name']) ?>"
                                placeholder="z.B. Lebensmittel, Gehalt, Miete..."
                                maxlength="50" required
                                oninput="updatePreview()">
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Typ *</label>
                                <div class="type-selector">
                                    <div class="type-option">
                                        <input type="radio" id="type_income" name="type" value="income"
                                            class="type-radio" <?= $form_data['type'] === 'income' ? 'checked' : '' ?>
                                            onchange="updatePreview()">
                                        <label for="type_income" class="type-label">
                                            <div class="type-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                                            <div class="type-name">Einnahme</div>
                                        </label>
                                    </div>
                                    <div class="type-option">
                                        <input type="radio" id="type_expense" name="type" value="expense"
                                            class="type-radio" <?= $form_data['type'] === 'expense' ? 'checked' : '' ?>
                                            onchange="updatePreview()">
                                        <label for="type_expense" class="type-label">
                                            <div class="type-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
                                            <div class="type-name">Ausgabe</div>
                                        </label>
                                    </div>
                                    <!-- NEUE SCHULDEN-OPTIONEN -->
                                    <div class="type-option">
                                        <input type="radio" id="type_debt_in" name="type" value="debt_in"
                                            class="type-radio" <?= $form_data['type'] === 'debt_in' ? 'checked' : '' ?>
                                            onchange="updatePreview()">
                                        <label for="type_debt_in" class="type-label">
                                            <div class="type-icon"><i class="fa-solid fa-arrow-left"></i></div>
                                            <div class="type-name">Schuld Eingang</div>
                                        </label>
                                    </div>
                                    <div class="type-option">
                                        <input type="radio" id="type_debt_out" name="type" value="debt_out"
                                            class="type-radio" <?= $form_data['type'] === 'debt_out' ? 'checked' : '' ?>
                                            onchange="updatePreview()">
                                        <label for="type_debt_out" class="type-label">
                                            <div class="type-icon"><i class="fa-solid fa-arrow-right"></i></div>
                                            <div class="type-name">Schuld Ausgang</div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Icon auswählen *</label>
                            <div class="icon-selector">
                                <?php foreach ($predefined_icons as $icon): ?>
                                    <div class="icon-option">
                                        <input type="radio" id="icon_<?= md5($icon) ?>" name="icon" value="<?= htmlspecialchars($icon) ?>"
                                            class="icon-radio" <?= $form_data['icon'] === $icon ? 'checked' : '' ?>
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
                                            class="color-radio" <?= $form_data['color'] === $color ? 'checked' : '' ?>
                                            onchange="updatePreview()">
                                        <label for="color_<?= substr($color, 1) ?>" class="color-label"
                                            style="background-color: <?= $color ?>;"></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="preview-section">
                            <div class="preview-title">👁 Vorschau</div>
                            <div class="category-preview">
                                <div class="preview-icon" id="previewIcon" style="background-color: <?= htmlspecialchars($form_data['color']) ?>;">
                                    <?= $form_data['icon'] ?>
                                </div>
                                <div class="preview-name" id="previewName">
                                    <?= htmlspecialchars($form_data['name']) ?: 'Kategorie-Name' ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="index.php" class="btn btn-cancel">Abbrechen</a>
                            <button type="submit" class="btn" id="submitBtn"><i class="fa-solid fa-floppy-disk"></i> Kategorie erstellen</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        let formSubmitted = false;

        function submitForm() {
            if (formSubmitted) {
                return false; // Verhindert Doppel-Submit
            }

            // Formular validieren
            const name = document.getElementById('name').value.trim();
            const type = document.querySelector('input[name="type"]:checked');
            const icon = document.querySelector('input[name="icon"]:checked');
            const color = document.querySelector('input[name="color"]:checked');

            if (!name || !type || !icon || !color) {
                alert('Bitte fülle alle Pflichtfelder aus.');
                return false;
            }

            // Submit-Button deaktivieren
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Wird erstellt...';
            submitBtn.style.opacity = '0.6';

            formSubmitted = true;
            return true;
        }

        function updatePreview() {
            const name = document.getElementById('name').value || 'Kategorie-Name';
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

            // Focus auf ersten Input
            document.getElementById('name').focus();

            // Verhindere Browser-Rücktaste doppelte Submits
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });

        // Warnung bei Seitenverlassen während Formular-Eingabe
        let formChanged = false;
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('change', () => formChanged = true);
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged && !formSubmitted) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
</body>

</html>