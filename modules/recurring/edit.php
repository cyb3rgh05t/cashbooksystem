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

$recurring_id = $_GET['id'] ?? '';

// Wiederkehrende Transaktion laden und Berechtigung prüfen
if (empty($recurring_id)) {
    $_SESSION['error'] = 'Keine wiederkehrende Transaktion angegeben.';
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT rt.*, c.name as category_name, c.icon as category_icon, c.color as category_color, c.type as transaction_type
    FROM recurring_transactions rt
    JOIN categories c ON rt.category_id = c.id
    WHERE rt.id = ?
");
$stmt->execute([$recurring_id]);
$recurring = $stmt->fetch();

if (!$recurring) {
    $_SESSION['error'] = 'Wiederkehrende Transaktion nicht gefunden oder keine Berechtigung.';
    header('Location: index.php');
    exit;
}

// Kategorien für Dropdown laden
$stmt = $pdo->prepare("SELECT * FROM categories ORDER BY type, name");
$stmt->execute();
$categories = $stmt->fetchAll();

// Nach Typ trennen
$income_categories = array_filter($categories, fn($c) => $c['type'] === 'income');
$expense_categories = array_filter($categories, fn($c) => $c['type'] === 'expense');

// Form-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = $_POST['category_id'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $frequency = $_POST['frequency'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';

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

    if (empty($frequency)) {
        $errors[] = 'Bitte wähle eine Häufigkeit aus.';
    } elseif (!in_array($frequency, ['daily', 'weekly', 'monthly', 'yearly'])) {
        $errors[] = 'Ungültige Häufigkeit ausgewählt.';
    }

    if (empty($start_date)) {
        $errors[] = 'Startdatum ist erforderlich.';
    } elseif (!strtotime($start_date)) {
        $errors[] = 'Ungültiges Startdatum.';
    }

    if (!empty($end_date) && !strtotime($end_date)) {
        $errors[] = 'Ungültiges Enddatum.';
    }

    if (!empty($start_date) && !empty($end_date) && $end_date <= $start_date) {
        $errors[] = 'Enddatum muss nach dem Startdatum liegen.';
    }

    if (empty($note)) {
        $note = 'Wiederkehrende Transaktion'; // Standard-Beschreibung
    }

    // Prüfe ob Kategorie dem Benutzer gehört
    if (!empty($category_id)) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        if (!$stmt->fetch()) {
            $errors[] = 'Ungültige Kategorie ausgewählt.';
        }
    }
    if (empty($errors)) {
        try {
            // Wenn Häufigkeit oder Startdatum geändert wurde, nächste Fälligkeit neu berechnen
            $next_due_date = $recurring['next_due_date'];
            if ($frequency !== $recurring['frequency'] || $start_date !== $recurring['start_date']) {
                // Wenn Startdatum in der Zukunft liegt, als nächste Fälligkeit verwenden
                if ($start_date > date('Y-m-d')) {
                    $next_due_date = $start_date;
                } else {
                    // Sonst nächste Fälligkeit basierend auf neuer Häufigkeit berechnen
                    $next_due_date = $this->calculateNextDueDate(date('Y-m-d'), $frequency);
                }
            }

            $stmt = $pdo->prepare("
    UPDATE recurring_transactions 
    SET category_id = ?, amount = ?, note = ?, frequency = ?, start_date = ?, end_date = ?, next_due_date = ?
    WHERE id = ?
");

            $stmt->execute([
                $category_id,
                floatval($amount),
                $note,
                $frequency,
                $start_date,
                $end_date ?: null,
                $next_due_date,
                $recurring_id
            ]);

            $_SESSION['success'] = 'Wiederkehrende Transaktion erfolgreich aktualisiert!';
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
        }
    }

    // Formular-Daten mit POST-Werten überschreiben bei Fehlern
    $recurring['category_id'] = $category_id;
    $recurring['amount'] = $amount;
    $recurring['note'] = $note;
    $recurring['frequency'] = $frequency;
    $recurring['start_date'] = $start_date;
    $recurring['end_date'] = $end_date;
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
    <title>Wiederkehrende Transaktion bearbeiten - Meine Firma Finance</title>
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
                    </li>
                    <li><a href="../settings/license.php"><i class="fas fa-key"></i>&nbsp;&nbsp;Lizenz</a></li>
                    <li>
                        <a href="../../logout.php"><i class="fa-solid fa-right-from-bracket"></i>&nbsp;&nbsp;Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrende Transaktion bearbeiten</h1>
                    <p style="color: var(--clr-surface-a50);">Aktualisiere die Details deiner wiederkehrenden Transaktion</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
            </div>

            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrende Transaktion bearbeiten</h2>
                        <p>Ändere die Details und speichere deine Anpassungen</p>
                    </div>

                    <div class="current-info">
                        <h4>📋 Aktuelle wiederkehrende Transaktion</h4>
                        <div class="current-recurring">
                            <div class="current-icon" style="background-color: <?= htmlspecialchars($recurring['category_color']) ?>;">
                                <?= $recurring['category_icon'] ?>
                            </div>
                            <div class="current-details">
                                <h5><?= htmlspecialchars($recurring['note']) ?></h5>
                                <div class="current-amount <?= $recurring['transaction_type'] ?>">
                                    <?= $recurring['transaction_type'] === 'income' ? '+' : '-' ?>€<?= number_format($recurring['amount'], 2, ',', '.') ?>
                                </div>
                                <span class="current-frequency">
                                    <?= $frequency_labels[$recurring['frequency']] ?>
                                </span>
                            </div>
                        </div>

                        <div class="info-grid">
                            <div class="info-item">
                                <strong>Kategorie:</strong><br>
                                <span class="info-value">
                                    <?= $recurring['category_icon'] ?> <?= htmlspecialchars($recurring['category_name']) ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <strong>Nächste Fälligkeit:</strong><br>
                                <span class="info-value"><?= date('d.m.Y', strtotime($recurring['next_due_date'])) ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Startdatum:</strong><br>
                                <span class="info-value"><?= date('d.m.Y', strtotime($recurring['start_date'])) ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Enddatum:</strong><br>
                                <span class="info-value">
                                    <?= $recurring['end_date'] ? date('d.m.Y', strtotime($recurring['end_date'])) : 'Unbegrenzt' ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <strong>Status:</strong><br>
                                <span class="info-value"><?= $recurring['is_active'] ? '✅ Aktiv' : '⏸️ Pausiert' ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Erstellt:</strong><br>
                                <span class="info-value"><?= date('d.m.Y', strtotime($recurring['created_at'])) ?></span>
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
                                <?php if (!empty($income_categories)): ?>
                                    <optgroup label="<i class=" fa-solid fa-sack-dollar"></i> Einnahmen">
                                        <?php foreach ($income_categories as $category): ?>
                                            <option value="<?= $category['id'] ?>"
                                                data-icon="<?= htmlspecialchars($category['icon']) ?>"
                                                data-color="<?= $category['color'] ?>"
                                                data-type="income"
                                                <?= $recurring['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if (!empty($expense_categories)): ?>
                                    <optgroup label="<i class=" fa-solid fa-money-bill-wave"></i> Ausgaben">
                                        <?php foreach ($expense_categories as $category): ?>
                                            <option value="<?= $category['id'] ?>"
                                                data-icon="<?= htmlspecialchars($category['icon']) ?>"
                                                data-color="<?= $category['color'] ?>"
                                                data-type="expense"
                                                <?= $recurring['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
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
                                        value="<?= htmlspecialchars($recurring['amount']) ?>"
                                        placeholder="0,00" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="note">Beschreibung</label>
                                <input type="text" id="note" name="note"
                                    class="form-input"
                                    value="<?= htmlspecialchars($recurring['note']) ?>"
                                    placeholder="z.B. Miete, Gehalt, Spotify...">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Häufigkeit *</label>
                            <div class="frequency-selector">
                                <div class="frequency-option">
                                    <input type="radio" id="freq_daily" name="frequency" value="daily"
                                        class="frequency-radio" <?= $recurring['frequency'] === 'daily' ? 'checked' : '' ?>>
                                    <label for="freq_daily" class="frequency-label">
                                        <div class="frequency-icon">📅</div>
                                        <div class="frequency-name">Täglich</div>
                                        <div class="frequency-desc">Jeden Tag</div>
                                    </label>
                                </div>
                                <div class="frequency-option">
                                    <input type="radio" id="freq_weekly" name="frequency" value="weekly"
                                        class="frequency-radio" <?= $recurring['frequency'] === 'weekly' ? 'checked' : '' ?>>
                                    <label for="freq_weekly" class="frequency-label">
                                        <div class="frequency-icon">📆</div>
                                        <div class="frequency-name">Wöchentlich</div>
                                        <div class="frequency-desc">Jede Woche</div>
                                    </label>
                                </div>
                                <div class="frequency-option">
                                    <input type="radio" id="freq_monthly" name="frequency" value="monthly"
                                        class="frequency-radio" <?= $recurring['frequency'] === 'monthly' ? 'checked' : '' ?>>
                                    <label for="freq_monthly" class="frequency-label">
                                        <div class="frequency-icon">🗓️</div>
                                        <div class="frequency-name">Monatlich</div>
                                        <div class="frequency-desc">Jeden Monat</div>
                                    </label>
                                </div>
                                <div class="frequency-option">
                                    <input type="radio" id="freq_yearly" name="frequency" value="yearly"
                                        class="frequency-radio" <?= $recurring['frequency'] === 'yearly' ? 'checked' : '' ?>>
                                    <label for="freq_yearly" class="frequency-label">
                                        <div class="frequency-icon">📅</div>
                                        <div class="frequency-name">Jährlich</div>
                                        <div class="frequency-desc">Jedes Jahr</div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="date-grid">
                            <div class="form-group">
                                <label class="form-label" for="start_date">Startdatum *</label>
                                <input type="date" id="start_date" name="start_date"
                                    class="form-input"
                                    value="<?= htmlspecialchars($recurring['start_date']) ?>"
                                    required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="end_date">Enddatum (optional)</label>
                                <input type="date" id="end_date" name="end_date"
                                    class="form-input"
                                    value="<?= htmlspecialchars($recurring['end_date']) ?>">
                                <small style="color: var(--clr-surface-a50); font-size: 12px;">
                                    Leer lassen für unbegrenzte Wiederholung
                                </small>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="index.php" class="btn btn-cancel">Abbrechen</a>
                            <a href="delete.php?id=<?= $recurring['id'] ?>" class="btn btn-delete"> <i class="fa-solid fa-trash-can"></i> Löschen</a>
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

                icon.innerHTML = categoryIcon; // ← innerHTML!
                name.textContent = categoryName;
                preview.style.borderLeft = `4px solid ${categoryColor}`;
                preview.style.background = hexToRgba(categoryColor, 0.1); // ← Transparenz!
                preview.classList.add('visible');
            } else {
                preview.classList.remove('visible');
            }
        }

        // Initial preview update
        document.addEventListener('DOMContentLoaded', function() {
            updateCategoryPreview();

            // Validate end date
            document.getElementById('end_date').addEventListener('change', function() {
                const startDate = document.getElementById('start_date').value;
                if (startDate && this.value && this.value <= startDate) {
                    alert('Das Enddatum muss nach dem Startdatum liegen.');
                    this.value = '';
                }
            });
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