<?php
session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';

$db = new Database();
$user_id = $_SESSION['user_id'];

// Aktuelles Startkapital laden
$current_starting_balance = $db->getStartingBalance($user_id);

// FIXED: Verwende die neuen konsistenten Methoden aus der Database-Klasse
$wealth_data = $db->getTotalWealth($user_id);

// Für Kompatibilität separate Variablen erstellen
$total_income_all_time = $wealth_data['total_income'];
$total_expenses_all_time = $wealth_data['total_expenses'];
$total_debt_in = $wealth_data['total_debt_in'];
$total_debt_out = $wealth_data['total_debt_out'];
$net_debt_position = $wealth_data['net_debt_position'];
$total_investment_value = $wealth_data['total_investments'];
$total_balance_with_investments = $wealth_data['total_wealth'];

// Form-Verarbeitung
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_starting_balance = $_POST['starting_balance'] ?? '';

    // Validierung
    if ($new_starting_balance === '') {
        $errors[] = 'Startkapital ist erforderlich.';
    } elseif (!is_numeric($new_starting_balance)) {
        $errors[] = 'Startkapital muss eine Zahl sein.';
    } else {
        $new_starting_balance = (float)$new_starting_balance;

        try {
            if ($db->updateStartingBalance($user_id, $new_starting_balance)) {
                $success = 'Startkapital erfolgreich auf €' . number_format($new_starting_balance, 2, ',', '.') . ' aktualisiert!';
                $current_starting_balance = $new_starting_balance;

                // Vermögen neu berechnen
                $wealth_data = $db->getTotalWealth($user_id);
                $total_balance_with_investments = $wealth_data['total_wealth'];
            } else {
                $errors[] = 'Fehler beim Aktualisieren des Startkapitals.';
            }
        } catch (Exception $e) {
            $errors[] = 'Datenbankfehler: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einstellungen - Meine Firma Finance</title>
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-..."
        crossorigin="anonymous"
        referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/settings.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
</head>

<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a class="sidebar-logo">
                    <img src="/assets/images/logo.png" alt="Meine Firma Finance Logo" class="sidebar-logo-image">

                </a>
                <p class="sidebar-welcome">Willkommen, <?= htmlspecialchars($_SESSION['username']) ?></p>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="dashboard.php"><i class="fa-solid fa-house"></i>&nbsp;&nbsp;Dashboard</a></li>
                    <li><a href="modules/expenses/index.php"><i class="fa-solid fa-money-bill-wave"></i>&nbsp;&nbsp;Ausgaben</a></li>
                    <li><a href="modules/income/index.php"><i class="fa-solid fa-sack-dollar"></i>&nbsp;&nbsp;Einnahmen</a></li>
                    <li><a href="modules/debts/index.php"><i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden</a></li>
                    <li><a href="modules/recurring/index.php"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend</a></li>
                    <li><a href="modules/investments/index.php"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto</a></li>
                    <li><a href="modules/categories/index.php"><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorien</a></li>

                    <li>
                        <a style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;" href="settings.php" class="active">
                            <i class="fa-solid fa-gear"></i>&nbsp;&nbsp;Einstellungen
                        </a>
                    </li>
                    <li>
                        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i>&nbsp;&nbsp;Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;">⚙️ Einstellungen</h1>
                    <p style="color: var(--clr-surface-a50);">Verwalte dein Startkapital und Kontoeinstellungen</p>
                </div>
                <a href="dashboard.php" class="btn btn-secondary">← Zurück zum Dashboard</a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Fehler:</strong><br>
                    <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                </div>
            <?php endif; ?>

            <div class="settings-grid">
                <!-- Startkapital Einstellungen -->
                <div class="settings-card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                        <div class="card-title">Startkapital</div>
                    </div>

                    <div class="current-balance">
                        <div class="balance-amount neutral">
                            €<?= number_format($current_starting_balance, 2, ',', '.') ?>
                        </div>
                        <div class="balance-label">Aktuelles Startkapital</div>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="starting_balance">Neues Startkapital</label>
                            <div class="form-group-inline">
                                <div class="currency-input-wrapper">
                                    <span class="currency-symbol">€</span>
                                    <input type="number" id="starting_balance" name="starting_balance"
                                        class="form-input currency-input"
                                        step="0.01"
                                        value="<?= number_format($current_starting_balance, 2, '.', '') ?>"
                                        placeholder="0,00" required>
                                </div>
                                <button type="submit" class="btn"><i class="fa-solid fa-floppy-disk"></i> Speichern</button>
                            </div>
                        </div>
                    </form>

                    <div class="info-box">
                        <div class="info-title">💡 Was ist Startkapital?</div>
                        <div class="info-text">
                            Das Startkapital ist der Geldbetrag, den du zu Beginn hattest (z.B. Kontostand beim Start der App).
                            Es wird zu deinen Einnahmen hinzugefügt und ist die Basis für die Berechnung deines Gesamtvermögens.
                        </div>
                    </div>
                </div>

                <!-- Vermögensübersicht - KOMPLETT REPARIERT -->
                <div class="settings-card">
                    <div class="card-header">
                        <div class="card-icon">📊</div>
                        <div class="card-title">Vermögensübersicht</div>
                    </div>

                    <div class="current-balance">
                        <div class="balance-amount <?= $total_balance_with_investments >= 0 ? 'positive' : 'negative' ?>">
                            €<?= number_format($total_balance_with_investments, 2, ',', '.') ?>
                        </div>
                        <div class="balance-label">Gesamtvermögen (inkl. Investments & Schulden)</div>
                    </div>

                    <div class="stats-overview">
                        <div class="stat-item">
                            <div class="stat-value neutral">€<?= number_format($current_starting_balance, 2, ',', '.') ?></div>
                            <div class="stat-label">Startkapital</div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-value income">+€<?= number_format($total_income_all_time, 2, ',', '.') ?></div>
                            <div class="stat-label">Gesamt Einnahmen</div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-value expense">-€<?= number_format($total_expenses_all_time, 2, ',', '.') ?></div>
                            <div class="stat-label">Gesamt Ausgaben</div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-value <?= ($total_income_all_time - $total_expenses_all_time) >= 0 ? 'income' : 'expense' ?>">
                                <?= ($total_income_all_time - $total_expenses_all_time) >= 0 ? '+' : '' ?>€<?= number_format($total_income_all_time - $total_expenses_all_time, 2, ',', '.') ?>
                            </div>
                            <div class="stat-label">Bilanz (Ein-/Ausgaben)</div>
                        </div>

                        <!-- NEU: Schulden-Anzeige -->
                        <?php if ($total_debt_in > 0 || $total_debt_out > 0): ?>
                            <div class="stat-item">
                                <div class="stat-value income">+€<?= number_format($total_debt_in, 2, ',', '.') ?></div>
                                <div class="stat-label">Erhaltenes Geld</div>
                            </div>

                            <div class="stat-item">
                                <div class="stat-value expense">-€<?= number_format($total_debt_out, 2, ',', '.') ?></div>
                                <div class="stat-label">Verliehenes Geld</div>
                            </div>

                            <div class="stat-item">
                                <div class="stat-value <?= $net_debt_position >= 0 ? 'debt-positive' : 'debt-negative' ?>">
                                    <?= $net_debt_position >= 0 ? '+' : '' ?>€<?= number_format($net_debt_position, 2, ',', '.') ?>
                                </div>
                                <div class="stat-label">Netto Schulden-Position</div>
                            </div>
                        <?php endif; ?>

                        <!-- NEU: Investment-Werte anzeigen -->
                        <?php if ($total_investment_value > 0): ?>
                            <div class="stat-item">
                                <div class="stat-value income">+€<?= number_format($total_investment_value, 2, ',', '.') ?></div>
                                <div class="stat-label">Investments</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="info-box">
                        <div class="info-title">📈 Berechnung</div>
                        <div class="info-text">
                            <strong>Gesamtvermögen = Startkapital + Einnahmen - Ausgaben + Erhaltenes Geld - Verliehenes Geld + Investments</strong><br>
                            €<?= number_format($current_starting_balance, 2, ',', '.') ?> +
                            €<?= number_format($total_income_all_time, 2, ',', '.') ?> -
                            €<?= number_format($total_expenses_all_time, 2, ',', '.') ?>
                            <?php if ($total_debt_in > 0): ?> + €<?= number_format($total_debt_in, 2, ',', '.') ?><?php endif; ?>
                                <?php if ($total_debt_out > 0): ?> - €<?= number_format($total_debt_out, 2, ',', '.') ?><?php endif; ?>
                                    <?php if ($total_investment_value > 0): ?> + €<?= number_format($total_investment_value, 2, ',', '.') ?><?php endif; ?> =
                                        €<?= number_format($total_balance_with_investments, 2, ',', '.') ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Amount-Input formatieren
        document.getElementById('starting_balance').addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });

        // Focus auf Input beim Laden
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('starting_balance');
            if (input) {
                input.focus();
                input.select();
            }
        });
    </script>
</body>

</html>