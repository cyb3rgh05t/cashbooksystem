<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../config/crypto_api.php';
require_once '../../includes/init_logger.php';

// Require login mit Auth-Klasse
$auth->requireLogin();

// Get current user
$currentUser = $auth->getCurrentUser();
$user_id = $currentUser['id'];

// Database connection
$db = new Database();
$pdo = $db->getConnection();
$crypto_api = new CryptoAPI();


// Get investment ID
$investment_id = $_GET['id'] ?? null;

if (!$investment_id || !is_numeric($investment_id)) {
    $_SESSION['error'] = 'Ungültige Investment-ID.';
    header('Location: index.php');
    exit;
}

// Load investment
$investment = $db->getInvestmentById((int)$investment_id);

if (!$investment) {
    $_SESSION['error'] = 'Investment nicht gefunden.';
    header('Location: index.php');
    exit;
}

// Check if user owns this investment (security check)
if (!$db->isInvestmentOwner((int)$investment_id, $user_id)) {
    $_SESSION['error'] = 'Du kannst nur deine eigenen Investments bearbeiten.';
    header('Location: index.php');
    exit;
}

// Form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $symbol = strtoupper(trim($_POST['symbol'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $amount = $_POST['amount'] ?? '';
    $purchase_price = $_POST['purchase_price'] ?? '';
    $purchase_date = $_POST['purchase_date'] ?? '';

    $errors = [];

    // Validation
    if (empty($symbol)) {
        $errors[] = 'Kryptowährung-Symbol ist erforderlich.';
    }

    if (empty($name)) {
        $errors[] = 'Name der Kryptowährung ist erforderlich.';
    }

    if (empty($amount)) {
        $errors[] = 'Anzahl ist erforderlich.';
    } elseif (!is_numeric($amount) || floatval($amount) <= 0) {
        $errors[] = 'Anzahl muss eine positive Zahl sein.';
    }

    if (empty($purchase_price)) {
        $errors[] = 'Einkaufspreis ist erforderlich.';
    } elseif (!is_numeric($purchase_price) || floatval($purchase_price) <= 0) {
        $errors[] = 'Einkaufspreis muss eine positive Zahl sein.';
    }

    if (empty($purchase_date)) {
        $errors[] = 'Kaufdatum ist erforderlich.';
    } elseif (!strtotime($purchase_date)) {
        $errors[] = 'Ungültiges Kaufdatum.';
    }

    if (empty($errors)) {
        try {
            $success = $db->updateInvestment(
                (int)$investment_id,
                $symbol,
                $name,
                floatval($amount),
                floatval($purchase_price),
                $purchase_date
            );

            if ($success) {
                $_SESSION['success'] = 'Investment "' . htmlspecialchars($symbol) . '" erfolgreich aktualisiert!';
                header('Location: index.php');
                exit;
            } else {
                $errors[] = 'Fehler beim Aktualisieren des Investments.';
            }
        } catch (Exception $e) {
            $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
        }
    }
} else {
    // Pre-fill form with existing data
    $_POST['symbol'] = $investment['symbol'];
    $_POST['name'] = $investment['name'];
    $_POST['amount'] = $investment['amount'];
    $_POST['purchase_price'] = $investment['purchase_price'];
    $_POST['purchase_date'] = $investment['purchase_date'];
}

// Form data for display
$form_data = [
    'symbol' => $_POST['symbol'] ?? $investment['symbol'],
    'name' => $_POST['name'] ?? $investment['name'],
    'amount' => $_POST['amount'] ?? $investment['amount'],
    'purchase_price' => $_POST['purchase_price'] ?? $investment['purchase_price'],
    'purchase_date' => $_POST['purchase_date'] ?? $investment['purchase_date']
];
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investment bearbeiten - Meine Firma Finance</title>
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-..."
        crossorigin="anonymous"
        referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/investments.css">
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
                    <li><a href="index.php" class="active"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto</a></li>
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
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto - Investment bearbeiten</h1>
                    <p style="color: var(--clr-surface-a50);">Bearbeite die Details deines Kryptowährung-Investments</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
            </div>

            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto - Investment bearbeiten</h2>
                        <p>Aktualisiere die Details deines Investments</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <strong>Fehler:</strong><br>
                            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="investmentForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="symbol">Symbol *</label>
                                <input type="text" id="symbol" name="symbol"
                                    class="form-input"
                                    value="<?= htmlspecialchars($form_data['symbol']) ?>"
                                    placeholder="z.B. BTC, XRP, ETH"
                                    style="text-transform: uppercase;"
                                    required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="name">Name *</label>
                                <input type="text" id="name" name="name"
                                    class="form-input"
                                    value="<?= htmlspecialchars($form_data['name']) ?>"
                                    placeholder="z.B. Bitcoin, Ripple, Ethereum"
                                    required>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="amount">Anzahl *</label>
                                <input type="number" id="amount" name="amount"
                                    class="form-input"
                                    value="<?= htmlspecialchars($form_data['amount']) ?>"
                                    step="0.0001"
                                    min="0.0001"
                                    placeholder="z.B. 1.5"
                                    required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="purchase_price">Einkaufspreis (€) *</label>
                                <input type="number" id="purchase_price" name="purchase_price"
                                    class="form-input"
                                    value="<?= htmlspecialchars($form_data['purchase_price']) ?>"
                                    step="0.01"
                                    min="0.01"
                                    placeholder="z.B. 42000.00"
                                    required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="purchase_date">Kaufdatum *</label>
                            <input type="date" id="purchase_date" name="purchase_date"
                                class="form-input"
                                value="<?= htmlspecialchars($form_data['purchase_date']) ?>"
                                max="<?= date('Y-m-d') ?>"
                                required>
                        </div>

                        <div class="form-actions">
                            <a href="index.php" class="btn btn-cancel">Abbrechen</a>
                            <button type="submit" class="btn"><i class="fa-solid fa-floppy-disk"></i> Änderungen speichern</button>
                        </div>
                    </form>

                    <div class="tips">
                        <div class="info-title">💡 Investment-Info</div>
                        <div class="info-content">
                            <p><strong>Erstellt:</strong> <?= date('d.m.Y H:i', strtotime($investment['created_at'])) ?></p>
                            <p><strong>Aktueller Wert:</strong> Wird automatisch mit aktuellen Marktpreisen berechnet</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-uppercase symbol
        document.getElementById('symbol').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });

        // Number input formatting
        document.getElementById('purchase_price').addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });

        // Focus auf ersten Input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('symbol').focus();
        });
    </script>
</body>

</html>