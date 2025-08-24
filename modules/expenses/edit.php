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

$expense_id = $_GET['id'] ?? '';

// FIXED: Ausgabe laden (ohne user_id Filter da gemeinsame Nutzung)
if (empty($expense_id)) {
    $_SESSION['error'] = 'Keine Ausgabe angegeben.';
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name, c.icon as category_icon, c.color as category_color
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.id = ? AND c.type = 'expense'
");
$stmt->execute([$expense_id]);
$expense = $stmt->fetch();

if (!$expense) {
    $_SESSION['error'] = 'Ausgabe nicht gefunden.';
    header('Location: index.php');
    exit;
}

// FIXED: Kategorien für Dropdown laden (ohne user_id Filter)
$stmt = $pdo->prepare("SELECT * FROM categories WHERE type = 'expense' ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

// Form-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = $_POST['category_id'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $date = $_POST['date'] ?? '';

    $errors = [];

    // Validierung
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
        $note = 'Ausgabe'; // Standard-Beschreibung
    }

    // FIXED: Prüfe ob Kategorie existiert (ohne user_id Filter)
    if (!empty($category_id)) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND type = 'expense'");
        $stmt->execute([$category_id]);
        if (!$stmt->fetch()) {
            $errors[] = 'Ungültige Kategorie ausgewählt.';
        }
    }

    if (empty($errors)) {
        try {
            // FIXED: Update ohne user_id Filter
            $stmt = $pdo->prepare("
                UPDATE transactions 
                SET category_id = ?, amount = ?, note = ?, date = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $category_id,
                floatval($amount),
                $note,
                $date,
                $expense_id
            ]);

            $_SESSION['success'] = 'Ausgabe erfolgreich aktualisiert!';
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
        }
    }

    // Formular-Daten mit POST-Werten überschreiben bei Fehlern
    $expense['category_id'] = $category_id;
    $expense['amount'] = $amount;
    $expense['note'] = $note;
    $expense['date'] = $date;
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ausgabe bearbeiten - Meine Firma Finance</title>
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
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;"><i class="fa-solid fa-pen-to-square"></i> Ausgabe bearbeiten</h1>
                    <p style="color: var(--clr-surface-a50);">Aktualisiere die Details dieser Ausgabe - Sichtbar für alle User</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
            </div>

            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fa-solid fa-money-bill-wave"></i>&nbsp;&nbsp;Ausgaben bearbeiten</h2>
                        <p>Ändere die Details und speichere deine Anpassungen</p>
                    </div>

                    <div class="current-info">
                        <h4>📋 Aktuelle Ausgabe</h4>
                        <div class="current-info-grid">
                            <div class="current-info-item">
                                <strong>Kategorie:</strong><br>
                                <span class="current-info-value">
                                    <?= $expense['category_icon'] ?> <?= htmlspecialchars($expense['category_name']) ?>
                                </span>
                            </div>
                            <div class="current-info-item">
                                <strong>Betrag:</strong><br>
                                <span class="current-info-value">€<?= number_format($expense['amount'], 2, ',', '.') ?></span>
                            </div>
                            <div class="current-info-item">
                                <strong>Datum:</strong><br>
                                <span class="current-info-value"><?= date('d.m.Y', strtotime($expense['date'])) ?></span>
                            </div>
                            <div class="current-info-item">
                                <strong>Beschreibung:</strong><br>
                                <span class="current-info-value"><?= htmlspecialchars($expense['note']) ?: 'Keine Beschreibung' ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <strong>Fehler:</strong><br>
                            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="category_id">Kategorie *</label>
                            <select id="category_id" name="category_id" class="form-select" required onchange="updateCategoryPreview()">
                                <option value="">Kategorie wählen...</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"
                                        data-icon="<?= htmlspecialchars($category['icon']) ?>"
                                        data-color="<?= $category['color'] ?>"
                                        <?= $expense['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="categoryPreview" class="category-preview">
                                <span class="category-icon" id="previewIcon">📁</span>
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
                                        value="<?= htmlspecialchars($expense['amount']) ?>"
                                        placeholder="0,00" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="date">Datum *</label>
                                <input type="date" id="date" name="date"
                                    class="form-input"
                                    value="<?= htmlspecialchars($expense['date']) ?>"
                                    max="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="note">Beschreibung</label>
                            <textarea id="note" name="note"
                                class="form-textarea" rows="3"
                                placeholder="Was hast du gekauft? (optional)"><?= htmlspecialchars($expense['note']) ?></textarea>
                        </div>

                        <div class="form-actions">
                            <a href="index.php" class="btn btn-cancel">Abbrechen</a>
                            <a href="delete.php?id=<?= $expense['id'] ?>" class="btn btn-delete"> <i class="fa-solid fa-trash-can"></i> Löschen</a>
                            <button type="submit" class="btn"><i class="fa-solid fa-floppy-disk"></i> Änderungen speichern</button>
                        </div>
                    </form>
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

        // Initial preview update
        document.addEventListener('DOMContentLoaded', function() {
            updateCategoryPreview();
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