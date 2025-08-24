<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
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

// Get debt type from URL parameter
$debt_type = $_GET['type'] ?? 'debt_out';
if (!in_array($debt_type, ['debt_in', 'debt_out'])) {
    $debt_type = 'debt_out';
}

// Load debt categories
$stmt = $pdo->prepare("SELECT * FROM categories WHERE type = ? ORDER BY name");
$stmt->execute([$debt_type]);
$categories = $stmt->fetchAll();

// Form processing
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = $_POST['category_id'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $date = $_POST['date'] ?? '';

    // Validation
    if (empty($category_id)) {
        $errors[] = 'Bitte wähle eine Kategorie aus.';
    }

    if (empty($amount)) {
        $errors[] = 'Betrag ist erforderlich.';
    } elseif (!is_numeric($amount) || floatval($amount) <= 0) {
        $errors[] = 'Betrag muss eine positive Zahl sein.';
    }

    if (empty($date)) {
        $errors[] = 'Datum ist erforderlich.';
    } elseif (!strtotime($date)) {
        $errors[] = 'Ungültiges Datum.';
    }

    if (empty($note)) {
        $note = $debt_type === 'debt_out' ? 'Verliehenes Geld' : 'Erhaltenes Geld';
    }

    // Check if category exists
    if (!empty($category_id)) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND type = ?");
        $stmt->execute([$category_id, $debt_type]);
        if (!$stmt->fetch()) {
            $errors[] = 'Ungültige Kategorie ausgewählt.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO transactions (user_id, category_id, amount, note, date, created_at)
                VALUES (?, ?, ?, ?, ?, datetime('now'))
            ");

            $stmt->execute([
                $user_id,
                $category_id,
                floatval($amount),
                $note,
                $date
            ]);

            $_SESSION['success'] = $debt_type === 'debt_out' ?
                'Geld erfolgreich als verliehen eingetragen!' :
                'Erhaltenes Geld erfolgreich eingetragen!';
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
        }
    }
}

// Standardwerte für Formular
$form_data = [
    'category_id' => $_POST['category_id'] ?? '',
    'amount' => $_POST['amount'] ?? '',
    'note' => $_POST['note'] ?? '',
    'date' => $_POST['date'] ?? date('Y-m-d')
];

$page_title = $debt_type === 'debt_out' ? 'Geld verleihen' : 'Geld erhalten / leihen';
$page_color = $debt_type === 'debt_out' ? '#f97316' : '#22c55e';
$page_icon = $debt_type === 'debt_out' ? 'fa-solid fa-arrow-right' : 'fa-solid fa-arrow-left';
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Meine Firma Finance</title>
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-..."
        crossorigin="anonymous"
        referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/income.css">
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
                    <li><a href="../debts/index.php" class="active">
                            <i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden
                        </a></li>
                    <li><a href="../recurring/index.php"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend</a></li>
                    <li><a href="../investments/index.php"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto</a></li>
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
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;"><i class="<?= $page_icon ?>"></i>&nbsp;&nbsp;<?= $page_title ?></h1>
                    <p style="color: var(--clr-surface-a50);"><?= $debt_type === 'debt_out' ? 'Erfasse verliehenes Geld' : 'Erfasse erhaltenes / geliehenes Geld' ?></p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
            </div>

            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h2 style="color: <?= $page_color ?>"><i class="<?= $page_icon ?> "></i>&nbsp;&nbsp;<?= $debt_type === 'debt_out' ? 'Geld verleihen' : 'Geld erhalten / leihen' ?></h2>
                        <p>Erfasse alle Details der Transaktion - wird für alle User sichtbar</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <strong>Fehler:</strong><br>
                            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($categories)): ?>
                        <div class="alert alert-error">
                            <strong>Keine Kategorien vorhanden!</strong><br>
                            Du musst zuerst <a href="../categories/add.php?type=<?= $debt_type ?>" style="color: <?= $page_color ?>;">Schulden-Kategorien erstellen</a>,
                            bevor du Transaktionen hinzufügen kannst.
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label" for="category_id">Kategorie *</label>
                                <select id="category_id" name="category_id" class="form-select" required onchange="updateCategoryPreview()">
                                    <option value="">Kategorie wählen...</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>"
                                            data-icon="<?= htmlspecialchars($category['icon']) ?>"
                                            data-color="<?= $category['color'] ?>"
                                            <?= $form_data['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="categoryPreview" class="category-preview">
                                    <span class="category-icon" id="previewIcon"><?= $debt_type === 'debt_out' ? '<i class="fa-solid fa-money-bill-wave"></i>' : '<i class="fa-solid fa-sack-dollar"></i>' ?></span>
                                    <span class="category-name" id="previewName">Kategorie</span>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="amount">Betrag *</label>
                                    <div class="amount-input-wrapper">
                                        <span class="currency-symbol">€</span>
                                        <input type="number" id="amount" name="amount"
                                            class="form-input amount-input"
                                            step="0.01" min="0.01"
                                            value="<?= htmlspecialchars($form_data['amount']) ?>"
                                            placeholder="0,00" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="date">Datum *</label>
                                    <input type="date" id="date" name="date"
                                        class="form-input"
                                        value="<?= htmlspecialchars($form_data['date']) ?>"
                                        max="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="note">Beschreibung</label>
                                <textarea id="note" name="note"
                                    class="form-textarea" rows="3"
                                    placeholder="<?= $debt_type === 'debt_out' ? 'An wen wurde das Geld verliehen?' : 'Von wem wurde das Geld erhalten?' ?> (optional)"><?= htmlspecialchars($form_data['note']) ?></textarea>
                            </div>

                            <div class="form-actions">
                                <a href="index.php" class="btn btn-cancel">Abbrechen</a>
                                <button type="submit" class="btn" style="background-color: <?= $page_color ?>;"><i class="fa-solid fa-floppy-disk"></i> <?= $debt_type === 'debt_out' ? 'Geld verleihen' : 'Geld erhalten' ?></button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <div class="tips">
                        <h4>💡 Tipps für bessere Übersicht</h4>
                        <ul>
                            <li>Wähle die passende Kategorie für deine <?= $debt_type === 'debt_out' ? 'Verleihaktion' : 'Geldaufnahme' ?></li>
                            <li>Gib eine aussagekräftige Beschreibung ein</li>
                            <li>Trage das korrekte <?= $debt_type === 'debt_out' ? 'Verleih-' : 'Empfangs-' ?>datum ein</li>
                            <li>Alle User können diese Transaktion sehen und bearbeiten</li>
                        </ul>

                        <div class="income-examples">
                            <h5><?= $debt_type === 'debt_out' ? '<i class="fa-solid fa-money-bill-wave"></i>' : '<i class="fa-solid fa-sack-dollar"></i>' ?> Beispiele für <?= $debt_type === 'debt_out' ? 'verliehenes Geld' : 'erhaltenes Geld' ?>:</h5>
                            <ul>
                                <?php if ($debt_type === 'debt_out'): ?>
                                    <li onclick="fillExample('Darlehen an Max Mustermann')">Darlehen an Freunde</li>
                                    <li onclick="fillExample('Vorschuss für Urlaub')">Vorschuss</li>
                                    <li onclick="fillExample('Kautionsübernahme')">Kaution</li>
                                    <li onclick="fillExample('Notfall-Kredit')">Notfallhilfe</li>
                                    <li onclick="fillExample('Geschäftspartner Darlehen')">Geschäftsdarlehen</li>
                                    <li onclick="fillExample('Familienunterstützung')">Familienkredit</li>
                                <?php else: ?>
                                    <li onclick="fillExample('Darlehen von Eltern')">Darlehen von Familie</li>
                                    <li onclick="fillExample('Kredit von Freunden')">Kredit von Freunden</li>
                                    <li onclick="fillExample('Vorschuss vom Arbeitgeber')">Gehaltsvorschuss</li>
                                    <li onclick="fillExample('Rückzahlung alter Schulden')">Rückzahlung</li>
                                    <li onclick="fillExample('Geschäftskredit')">Geschäftskredit</li>
                                    <li onclick="fillExample('Notfall-Finanzierung')">Notfallfinanzierung</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function updateCategoryPreview() {
            const select = document.getElementById('category_id');
            const preview = document.getElementById('categoryPreview');
            const icon = document.getElementById('previewIcon');
            const name = document.getElementById('previewName');

            if (select.value) {
                const option = select.options[select.selectedIndex];
                const categoryIcon = option.getAttribute('data-icon');
                const categoryColor = option.getAttribute('data-color');
                const categoryName = option.text;

                // Hex zu RGBA mit Transparenz konvertieren
                function hexToRgba(hex, alpha = 0.1) {
                    const r = parseInt(hex.slice(1, 3), 16);
                    const g = parseInt(hex.slice(3, 5), 16);
                    const b = parseInt(hex.slice(5, 7), 16);
                    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
                }

                icon.innerHTML = categoryIcon;
                name.textContent = categoryName;
                preview.style.borderLeft = `4px solid ${categoryColor}`;
                preview.style.background = hexToRgba(categoryColor, 0.1); // 10% Transparenz
                preview.classList.add('visible');
            } else {
                preview.classList.remove('visible');
            }
        }

        function fillExample(text) {
            document.getElementById('note').value = text;
            document.getElementById('note').focus();
        }

        // Initial preview update
        document.addEventListener('DOMContentLoaded', function() {
            updateCategoryPreview();

            // Focus auf ersten Input
            const firstInput = document.querySelector('select, input');
            if (firstInput) firstInput.focus();
        });

        // Amount-Input formatieren
        document.getElementById('amount').addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    </script>
</body>

</html>