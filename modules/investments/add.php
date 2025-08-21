<?php
session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../config/crypto_api.php';

$db = new Database();
$crypto_api = new CryptoAPI();
$user_id = $_SESSION['user_id'];

// Form-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $symbol = strtoupper(trim($_POST['symbol'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $amount = $_POST['amount'] ?? '';
    $purchase_price = $_POST['purchase_price'] ?? '';
    $purchase_date = $_POST['purchase_date'] ?? '';

    $errors = [];

    // Validierung
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
            $investment_id = $db->addInvestment(
                $user_id,
                $symbol,
                $name,
                floatval($amount),
                floatval($purchase_price),
                $purchase_date
            );

            $_SESSION['success'] = 'Investment "' . htmlspecialchars($symbol) . '" erfolgreich hinzugefügt!';
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
        }
    }
}

// Standardwerte für Formular
$form_data = [
    'symbol' => $_POST['symbol'] ?? '',
    'name' => $_POST['name'] ?? '',
    'amount' => $_POST['amount'] ?? '',
    'purchase_price' => $_POST['purchase_price'] ?? '',
    'purchase_date' => $_POST['purchase_date'] ?? date('Y-m-d')
];
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neues Investment - Meine Firma Finance</title>
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
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto - Neues Investment</h1>
                    <p style="color: var(--clr-surface-a50);">Füge ein neues Kryptowährung-Investment hinzu</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
            </div>

            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto - Investment hinzufügen</h2>
                        <p>Erfasse die Details deines Krypto-Investments - wird für alle User sichtbar</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <strong>Fehler:</strong><br>
                            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="investmentForm">
                        <div class="form-group">
                            <label class="form-label" for="crypto_search">Kryptowährung suchen *</label>
                            <div class="crypto-search">
                                <input type="text" id="crypto_search"
                                    class="form-input"
                                    placeholder="z.B. Bitcoin, XRP, Ethereum..."
                                    oninput="searchCrypto(this.value)"
                                    autocomplete="off">
                                <div id="searchResults" class="search-results"></div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="symbol">Symbol *</label>
                                <input type="text" id="symbol" name="symbol"
                                    class="form-input"
                                    value="<?= htmlspecialchars($form_data['symbol']) ?>"
                                    placeholder="z.B. BTC, XRP, ETH"
                                    style="text-transform: uppercase;"
                                    required readonly>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="name">Name *</label>
                                <input type="text" id="name" name="name"
                                    class="form-input"
                                    value="<?= htmlspecialchars($form_data['name']) ?>"
                                    placeholder="z.B. Bitcoin, Ripple"
                                    required readonly>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="amount">Anzahl *</label>
                                <input type="number" id="amount" name="amount"
                                    class="form-input"
                                    step="0.000001" min="0.000001"
                                    value="<?= htmlspecialchars($form_data['amount']) ?>"
                                    placeholder="0.000000" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="purchase_price">Einkaufspreis (€) *</label>
                                <div class="amount-input-wrapper">
                                    <span class="currency-symbol">€</span>
                                    <input type="number" id="purchase_price" name="purchase_price"
                                        class="form-input amount-input"
                                        step="0.01" min="0.01"
                                        value="<?= htmlspecialchars($form_data['purchase_price']) ?>"
                                        placeholder="0,00" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="purchase_date">Kaufdatum *</label>
                            <input type="date" id="purchase_date" name="purchase_date"
                                class="form-input"
                                value="<?= htmlspecialchars($form_data['purchase_date']) ?>"
                                max="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-actions">
                            <a href="index.php" class="btn btn-cancel">Abbrechen</a>
                            <button type="submit" class="btn"><i class="fa-solid fa-floppy-disk"></i> Investment speichern</button>
                        </div>
                    </form>

                    <div class="info-box">
                        <div class="info-title">💡 Beliebte Kryptowährungen</div>
                        <div class="info-text">
                            Bitcoin (BTC), Ethereum (ETH), Ripple (XRP), Cardano (ADA), Dogecoin (DOGE),
                            Polkadot (DOT), Chainlink (LINK), Litecoin (LTC), Bitcoin Cash (BCH), Stellar (XLM)
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let searchTimeout;
        let currentRequest = null;

        function searchCrypto(query) {
            clearTimeout(searchTimeout);

            const resultsDiv = document.getElementById('searchResults');

            // Cancel previous request if still pending
            if (currentRequest) {
                currentRequest.abort();
                currentRequest = null;
            }

            if (query.length < 2) {
                resultsDiv.style.display = 'none';
                return;
            }

            // Show loading indicator
            resultsDiv.innerHTML = '<div class="search-loading">🔍 Suche läuft...</div>';
            resultsDiv.style.display = 'block';

            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        }

        async function performSearch(query) {
            try {
                // Create new AbortController for this request
                const controller = new AbortController();
                currentRequest = controller;

                const response = await fetch('crypto_search.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        query: query,
                        limit: 15
                    }),
                    signal: controller.signal
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                currentRequest = null;

                if (data.error) {
                    displayError(data.error, data.fallback_used);
                    return;
                }

                displaySearchResults(data.results, data.api_available);

            } catch (error) {
                currentRequest = null;

                if (error.name === 'AbortError') {
                    // Request was cancelled, ignore
                    return;
                }

                console.error('Search error:', error);
                displayError('Suche fehlgeschlagen. Bitte versuche es erneut.');
            }
        }

        function displaySearchResults(cryptos, apiAvailable = true) {
            const resultsDiv = document.getElementById('searchResults');

            if (!cryptos || cryptos.length === 0) {
                resultsDiv.innerHTML = '<div class="search-no-results">Keine Kryptowährungen gefunden</div>';
                resultsDiv.style.display = 'block';
                return;
            }

            let html = '';

            // Show API status if not available
            if (!apiAvailable) {
                html += '<div class="search-info">⚠️ Offline-Modus: Begrenzte Ergebnisse</div>';
            }

            cryptos.forEach(crypto => {
                const rankBadge = crypto.rank && crypto.rank < 100 ?
                    `<span class="rank-badge">#${crypto.rank}</span>` : '';

                html += `
            <div class="search-result-item" onclick="selectCrypto('${crypto.symbol}', '${crypto.name}', '${crypto.id}')">
                <div class="search-result-main">
                    <div class="search-result-symbol">${crypto.symbol}</div>
                    <div class="search-result-name">${crypto.name}</div>
                </div>
                ${rankBadge}
            </div>
        `;
            });

            resultsDiv.innerHTML = html;
            resultsDiv.style.display = 'block';
        }

        function displayError(errorMessage, fallbackUsed = false) {
            const resultsDiv = document.getElementById('searchResults');

            let html = `<div class="search-error">❌ ${errorMessage}</div>`;

            if (fallbackUsed) {
                html += '<div class="search-info">💡 Versuche bekannte Symbole wie BTC, ETH, XRP</div>';
            }

            resultsDiv.innerHTML = html;
            resultsDiv.style.display = 'block';
        }

        function selectCrypto(symbol, name, id) {
            document.getElementById('symbol').value = symbol;
            document.getElementById('name').value = name;
            document.getElementById('crypto_search').value = `${symbol} - ${name}`;
            document.getElementById('searchResults').style.display = 'none';

            // Focus auf Amount-Input
            document.getElementById('amount').focus();
        }

        // Click outside to close search results
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.crypto-search')) {
                document.getElementById('searchResults').style.display = 'none';
            }
        });

        // Escape key to close search results
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('searchResults').style.display = 'none';

                // Cancel current search request
                if (currentRequest) {
                    currentRequest.abort();
                    currentRequest = null;
                }
            }
        });

        // Form validation
        document.getElementById('investmentForm').addEventListener('submit', function(e) {
            const symbol = document.getElementById('symbol').value;
            const name = document.getElementById('name').value;

            if (!symbol || !name) {
                e.preventDefault();
                alert('Bitte wähle eine Kryptowährung aus der Suche aus.');
                return false;
            }
        });

        // Number input formatting
        document.getElementById('purchase_price').addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });

        // Focus auf ersten Input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('crypto_search').focus();
        });
    </script>
</body>

</html>