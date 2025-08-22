<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/init_logger.php';

// Require login mit Auth-Klasse
$auth->requireLogin();

// Get current user
$currentUser = $auth->getCurrentUser();
$user_id = $currentUser['id'];

// Database connection
$db = new Database();
$pdo = $db->getConnection();

$transaction_id = $_GET['id'] ?? '';

// Transaktion laden und Berechtigung prüfen
if (empty($transaction_id)) {
    $_SESSION['error'] = 'Keine Transaktion angegeben.';
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name, c.icon as category_icon, c.color as category_color, c.type as category_type
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.id = ? AND c.type IN ('debt_in', 'debt_out')
");
$stmt->execute([$transaction_id]);
$transaction = $stmt->fetch();

if (!$transaction) {
    $_SESSION['error'] = 'Transaktion nicht gefunden.';
    header('Location: index.php');
    exit;
}

// Debt type from transaction
$debt_type = $transaction['category_type'];

// Load debt categories of the same type
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

    // Check if category exists and has correct type
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
                UPDATE transactions 
                SET category_id = ?, amount = ?, note = ?, date = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $category_id,
                floatval($amount),
                $note,
                $date,
                $transaction_id
            ]);

            $_SESSION['success'] = $debt_type === 'debt_out' ?
                'Verliehenes Geld erfolgreich aktualisiert!' :
                'Erhaltenes Geld erfolgreich aktualisiert!';
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
        }
    }

    // Formular-Daten mit POST-Werten überschreiben bei Fehlern
    $transaction['category_id'] = $category_id;
    $transaction['amount'] = $amount;
    $transaction['note'] = $note;
    $transaction['date'] = $date;
}

$page_title = $debt_type === 'debt_out' ? 'Verliehenes Geld bearbeiten' : 'Erhaltenes Geld bearbeiten';
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
                    <li>
                        <a href="../../logout.php"><i class="fa-solid fa-right-from-bracket"></i>&nbsp;&nbsp;Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 style="color: <?= $page_color ?>; margin-bottom: 5px;"><i class="<?= $page_icon ?>"></i>&nbsp;&nbsp;<?= $page_title ?></h1>
                    <p style="color: var(--clr-surface-a50);">Aktualisiere die Details dieser Transaktion - Sichtbar für alle User</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
            </div>

            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="<?= $page_icon ?>"></i>&nbsp;&nbsp;Transaktion bearbeiten</h2>
                        <p>Ändere die Details und speichere deine Anpassungen</p>
                    </div>

                    <div class="current-info">
                        <h4>📋 Aktuelle Transaktion</h4>
                        <div class="current-info-grid">
                            <div class="current-info-item">
                                <strong>Kategorie:</strong><br>
                                <span class="current-info-value">
                                    <?= htmlspecialchars($transaction['category_icon']) ?> <?= htmlspecialchars($transaction['category_name']) ?>
                                </span>
                            </div>
                            <div class="current-info-item">
                                <strong>Betrag:</strong><br>
                                <span class="current-info-value">€<?= number_format($transaction['amount'], 2, ',', '.') ?></span>
                            </div>
                            <div class="current-info-item">
                                <strong>Datum:</strong><br>
                                <span class="current-info-value"><?= date('d.m.Y', strtotime($transaction['date'])) ?></span>
                            </div>
                            <div class="current-info-item">
                                <strong>Beschreibung:</strong><br>
                                <span class="current-info-value"><?= htmlspecialchars($transaction['note']) ?: 'Keine Beschreibung' ?></span>
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
                                        <?= $transaction['category_id'] == $category['id'] ? 'selected' : '' ?>>
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
                                        value="<?= htmlspecialchars($transaction['amount']) ?>"
                                        placeholder="0,00" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="date">Datum *</label>
                                <input type="date" id="date" name="date"
                                    class="form-input"
                                    value="<?= htmlspecialchars($transaction['date']) ?>"
                                    max="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="note">Beschreibung</label>
                            <textarea id="note" name="note"
                                class="form-textarea" rows="3"
                                placeholder="<?= $debt_type === 'debt_out' ? 'An wen wurde das Geld verliehen?' : 'Von wem wurde das Geld erhalten?' ?> (optional)"><?= htmlspecialchars($transaction['note']) ?></textarea>
                        </div>

                        <div class="form-actions">
                            <a href="index.php" class="btn btn-cancel">Abbrechen</a>
                            <a href="delete.php?id=<?= $transaction['id'] ?>" class="btn btn-delete"><i class="fa-solid fa-trash-can"></i> Löschen</a>
                            <button type="submit" class="btn" style="background-color: <?= $page_color ?>;"><i class="fa-solid fa-floppy-disk"></i> Änderungen speichern</button>
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