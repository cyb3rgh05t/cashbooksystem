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
            // Erstes Fälligkeitsdatum berechnen
            $next_due_date = $start_date;

            $stmt = $pdo->prepare("
                INSERT INTO recurring_transactions (user_id, category_id, amount, note, frequency, start_date, end_date, next_due_date, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
            ");

            $stmt->execute([
                $user_id,
                $category_id,
                floatval($amount),
                $note,
                $frequency,
                $start_date,
                $end_date ?: null,
                $next_due_date
            ]);

            $_SESSION['success'] = 'Wiederkehrende Transaktion erfolgreich erstellt!';
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
    'frequency' => $_POST['frequency'] ?? 'monthly',
    'start_date' => $_POST['start_date'] ?? date('Y-m-d'),
    'end_date' => $_POST['end_date'] ?? ''
];
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neue wiederkehrende Transaktion - Meine Firma Finance</title>
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
        <<aside class="sidebar">
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
                        <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;"><i class="fas fa-sync"></i>&nbsp;&nbsp;Neue Wiederkehrende Transaktion</h1>
                        <p style="color: var(--clr-surface-a50);">Automatisiere regelmäßige Einnahmen oder Ausgaben</p>
                    </div>
                    <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
                </div>

                <div class="form-container">
                    <div class="form-card">
                        <div class="form-header">
                            <h2><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrende Transaktion erstellen</h2>
                            <p>Richte automatische Buchungen für regelmäßige Einnahmen oder Ausgaben ein</p>
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
                                Du musst zuerst <a href="../categories/add.php" style="color: var(--clr-primary-a20);">Kategorien erstellen</a>,
                                bevor du wiederkehrende Transaktionen hinzufügen kannst.
                            </div>
                        <?php else: ?>
                            <form method="POST" id="recurringForm">
                                <div class="form-group">
                                    <label class="form-label" for="category_id">Kategorie *</label>
                                    <select id="category_id" name="category_id" class="form-select" required onchange="updatePreview()">
                                        <option value="">Kategorie wählen...</option>
                                        <?php if (!empty($income_categories)): ?>
                                            <optgroup label="💰 Einnahmen 💰">
                                                <?php foreach ($income_categories as $category): ?>
                                                    <option value="<?= $category['id'] ?>"
                                                        data-icon="<?= htmlspecialchars($category['icon']) ?>"
                                                        data-color="<?= $category['color'] ?>"
                                                        data-type="income"
                                                        <?= $form_data['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($category['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <?php if (!empty($expense_categories)): ?>
                                            <optgroup label="💸 Ausgaben 💸">
                                                <?php foreach ($expense_categories as $category): ?>
                                                    <option value="<?= $category['id'] ?>"
                                                        data-icon="<?= htmlspecialchars($category['icon']) ?>"
                                                        data-color="<?= $category['color'] ?>"
                                                        data-type="expense"
                                                        <?= $form_data['category_id'] == $category['id'] ? 'selected' : '' ?>>
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
                                                value="<?= htmlspecialchars($form_data['amount']) ?>"
                                                placeholder="0,00" required oninput="updatePreview()">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="note">Beschreibung</label>
                                        <input type="text" id="note" name="note"
                                            class="form-input"
                                            value="<?= htmlspecialchars($form_data['note']) ?>"
                                            placeholder="z.B. Miete, Gehalt, Spotify..."
                                            oninput="updatePreview()">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Häufigkeit *</label>
                                    <div class="frequency-selector">
                                        <div class="frequency-option">
                                            <input type="radio" id="freq_daily" name="frequency" value="daily"
                                                class="frequency-radio" <?= $form_data['frequency'] === 'daily' ? 'checked' : '' ?>
                                                onchange="updatePreview()">
                                            <label for="freq_daily" class="frequency-label">
                                                <div class="frequency-icon">📅</div>
                                                <div class="frequency-name">Täglich</div>
                                                <div class="frequency-desc">Jeden Tag</div>
                                            </label>
                                        </div>
                                        <div class="frequency-option">
                                            <input type="radio" id="freq_weekly" name="frequency" value="weekly"
                                                class="frequency-radio" <?= $form_data['frequency'] === 'weekly' ? 'checked' : '' ?>
                                                onchange="updatePreview()">
                                            <label for="freq_weekly" class="frequency-label">
                                                <div class="frequency-icon">📆</div>
                                                <div class="frequency-name">Wöchentlich</div>
                                                <div class="frequency-desc">Jede Woche</div>
                                            </label>
                                        </div>
                                        <div class="frequency-option">
                                            <input type="radio" id="freq_monthly" name="frequency" value="monthly"
                                                class="frequency-radio" <?= $form_data['frequency'] === 'monthly' ? 'checked' : '' ?>
                                                onchange="updatePreview()">
                                            <label for="freq_monthly" class="frequency-label">
                                                <div class="frequency-icon">🗓️</div>
                                                <div class="frequency-name">Monatlich</div>
                                                <div class="frequency-desc">Jeden Monat</div>
                                            </label>
                                        </div>
                                        <div class="frequency-option">
                                            <input type="radio" id="freq_yearly" name="frequency" value="yearly"
                                                class="frequency-radio" <?= $form_data['frequency'] === 'yearly' ? 'checked' : '' ?>
                                                onchange="updatePreview()">
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
                                            value="<?= htmlspecialchars($form_data['start_date']) ?>"
                                            min="<?= date('Y-m-d') ?>" required
                                            onchange="updatePreview()">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="end_date">Enddatum (optional)</label>
                                        <input type="date" id="end_date" name="end_date"
                                            class="form-input"
                                            value="<?= htmlspecialchars($form_data['end_date']) ?>"
                                            onchange="updatePreview()">
                                        <small style="color: var(--clr-surface-a50); font-size: 12px;">
                                            Leer lassen für unbegrenzte Wiederholung
                                        </small>
                                    </div>
                                </div>

                                <div class="preview-section">
                                    <div class="preview-title">🔍 Vorschau</div>
                                    <div class="preview-item">
                                        <span class="preview-label">Kategorie:</span>
                                        <span class="preview-value" id="previewCategory">Keine ausgewählt</span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">Betrag:</span>
                                        <span class="preview-value" id="previewAmount">€0,00</span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">Beschreibung:</span>
                                        <span class="preview-value" id="previewNote">Wiederkehrende Transaktion</span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">Häufigkeit:</span>
                                        <span class="preview-value" id="previewFrequency">Monatlich</span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">Erste Ausführung:</span>
                                        <span class="preview-value" id="previewStartDate"><?= date('d.m.Y') ?></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">Läuft bis:</span>
                                        <span class="preview-value" id="previewEndDate">Unbegrenzt</span>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <a href="index.php" class="btn btn-cancel">Abbrechen</a>
                                    <button type="submit" class="btn"><i class="fa-solid fa-floppy-disk"></i> Wiederkehrende Transaktion erstellen</button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <div class="info-box">
                            <div class="info-title">💡 Wie funktionieren wiederkehrende Transaktionen?</div>
                            <div class="info-text">
                                Wiederkehrende Transaktionen werden automatisch zu den angegebenen Zeitpunkten erstellt.
                                Du kannst sie jederzeit pausieren, bearbeiten oder löschen. Die nächste Fälligkeit wird
                                automatisch berechnet und die Transaktionen werden bei deinem nächsten Login erstellt.
                            </div>
                        </div>
                    </div>
                </div>
            </main>
    </div>

    <script>
        const frequencyLabels = {
            'daily': 'Täglich',
            'weekly': 'Wöchentlich',
            'monthly': 'Monatlich',
            'yearly': 'Jährlich'
        };

        function updatePreview() {
            const categorySelect = document.getElementById('category_id');
            const amount = document.getElementById('amount').value || '0';
            const note = document.getElementById('note').value || 'Wiederkehrende Transaktion';
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const frequency = document.querySelector('input[name="frequency"]:checked')?.value || 'monthly';

            // Hex zu RGBA mit Transparenz konvertieren
            function hexToRgba(hex, alpha = 0.1) {
                const r = parseInt(hex.slice(1, 3), 16);
                const g = parseInt(hex.slice(3, 5), 16);
                const b = parseInt(hex.slice(5, 7), 16);
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            }

            // Kategorie Preview
            if (categorySelect.value) {
                const option = categorySelect.options[categorySelect.selectedIndex];
                const categoryIcon = option.getAttribute('data-icon');
                const categoryColor = option.getAttribute('data-color');
                const categoryName = option.text;
                const categoryType = option.getAttribute('data-type');

                document.getElementById('previewIcon').innerHTML = categoryIcon;
                document.getElementById('previewName').textContent = categoryName;
                document.getElementById('categoryPreview').style.borderLeft = `4px solid ${categoryColor}`;
                document.getElementById('categoryPreview').style.background = hexToRgba(categoryColor, 0.1); // ← Transparenz hinzugefügt
                document.getElementById('categoryPreview').classList.add('visible');

                document.getElementById('previewCategory').textContent = `${categoryIcon} ${categoryName}`;
                document.getElementById('previewAmount').textContent = `${categoryType === 'income' ? '+' : '-'}€${parseFloat(amount).toFixed(2).replace('.', ',')}`;
                document.getElementById('previewAmount').style.color = categoryType === 'income' ? '#4ade80' : '#f87171';
            } else {
                document.getElementById('categoryPreview').classList.remove('visible');
                document.getElementById('categoryPreview').style.background = 'transparent'; // ← Background zurücksetzen
                document.getElementById('previewCategory').textContent = 'Keine ausgewählt';
                document.getElementById('previewAmount').textContent = `€${parseFloat(amount).toFixed(2).replace('.', ',')}`;
                document.getElementById('previewAmount').style.color = 'var(--clr-light-a0)';
            }

            // Andere Felder
            document.getElementById('previewNote').textContent = note;
            document.getElementById('previewFrequency').textContent = frequencyLabels[frequency];

            if (startDate) {
                const startDateObj = new Date(startDate);
                document.getElementById('previewStartDate').textContent = startDateObj.toLocaleDateString('de-DE');
            }

            if (endDate) {
                const endDateObj = new Date(endDate);
                document.getElementById('previewEndDate').textContent = endDateObj.toLocaleDateString('de-DE');
            } else {
                document.getElementById('previewEndDate').textContent = 'Unbegrenzt';
            }
        }

        // Initial preview update
        document.addEventListener('DOMContentLoaded', function() {
            updatePreview();

            // Validate end date
            document.getElementById('end_date').addEventListener('change', function() {
                const startDate = document.getElementById('start_date').value;
                if (startDate && this.value && this.value <= startDate) {
                    alert('Das Enddatum muss nach dem Startdatum liegen.');
                    this.value = '';
                }
            });

            // Focus auf ersten Input
            document.getElementById('category_id').focus();
        });

        // Amount-Input formatieren
        document.getElementById('amount').addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
                updatePreview();
            }
        });
    </script>
</body>

</html>