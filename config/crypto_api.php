<?php

require_once __DIR__ . '/../includes/timezone.php';
TimezoneHelper::initializeTimezone();

class CryptoAPI
{
    private $base_url = 'https://api.coingecko.com/api/v3';
    private $cache_file = 'cache/crypto_prices.json';
    private $cache_duration = 300; // 5 Minuten Cache
    private $search_cache_duration = 1800; // 30 Minuten Cache für Suchergebnisse
    private $last_error = '';

    // NEUE EIGENSCHAFTEN FÜR VERBESSERUNGEN
    private $request_count = 0;
    private $max_requests_per_minute = 45; // Unter CoinGecko Limit
    private $rate_limit_file = 'cache/api_rate_limit.json';
    private $debug_mode = false;
    private $log_file = 'cache/crypto_api.log';

    public function __construct($debug_mode = false)
    {
        $this->debug_mode = $debug_mode;

        // KORRIGIERT: Cache-Directory richtig erstellen
        $this->ensureCacheDirectoryExists();

        // Log-Datei initialisieren
        if ($this->debug_mode) {
            $this->logMessage("CryptoAPI initialisiert", 'INFO');
        }
    }

    /**
     * NEUE FUNKTION: Cache-Verzeichnis sicherstellen
     */
    private function ensureCacheDirectoryExists()
    {
        // Absoluten Pfad zum Cache-Verzeichnis bestimmen
        $cache_dir = dirname(__FILE__) . '/../cache';

        // Cache-Verzeichnis erstellen falls nicht vorhanden
        if (!is_dir($cache_dir)) {
            if (!mkdir($cache_dir, 0755, true)) {
                error_log("CryptoAPI: Konnte Cache-Verzeichnis nicht erstellen: $cache_dir");
                // Fallback: Verwende system temp directory
                $cache_dir = sys_get_temp_dir() . '/streamnet_crypto_cache';
                mkdir($cache_dir, 0755, true);
            }
        }

        // Prüfe Schreibrechte
        if (!is_writable($cache_dir)) {
            error_log("CryptoAPI: Cache-Verzeichnis nicht beschreibbar: $cache_dir");
            // Fallback: Verwende system temp directory
            $cache_dir = sys_get_temp_dir() . '/streamnet_crypto_cache';
            mkdir($cache_dir, 0755, true);
        }

        // Pfade aktualisieren mit absolutem Pfad
        $this->cache_file = $cache_dir . '/crypto_prices.json';
        $this->rate_limit_file = $cache_dir . '/api_rate_limit.json';
        $this->log_file = $cache_dir . '/crypto_api.log';

        // Info-Datei erstellen
        $info_file = $cache_dir . '/cache_info.txt';
        if (!file_exists($info_file)) {
            file_put_contents($info_file, "Meine Firma Finance Crypto Cache\nErstellt: " . TimezoneHelper::getCurrentUserTime('Y-m-d H:i:s'));
        }
    }

    // ========================================
    // BESTEHENDE FUNKTIONEN (UNVERÄNDERT)
    // ========================================

    /**
     * Search for cryptocurrencies by name or symbol
     * @param string $query Search term
     * @param int $limit Maximum number of results (default: 20)
     * @return array|false Array of crypto results or false on error
     */
    public function searchCryptocurrencies($query, $limit = 20)
    {
        if (empty($query) || strlen($query) < 2) {
            return [];
        }

        $query = strtolower(trim($query));
        $cache_key = 'search_' . md5($query . '_' . $limit);

        // Prüfe Cache zuerst
        $cached_data = $this->getFromCache($cache_key, $this->search_cache_duration);
        if ($cached_data !== false) {
            $this->logMessage("Search cache hit für: $query", 'DEBUG');
            return $cached_data;
        }

        // Falls API nicht verfügbar, nutze lokale Fallback-Liste
        if (!$this->isApiAvailable()) {
            $this->logMessage("API nicht verfügbar, nutze lokale Fallback für: $query", 'WARNING');
            return $this->getLocalSearchResults($query, $limit);
        }

        // CoinGecko Search API verwenden
        $url = "{$this->base_url}/search?query=" . urlencode($query);
        $response = $this->makeRequest($url);

        if ($response === false) {
            // Fallback auf lokale Daten
            $this->logMessage("API-Request fehlgeschlagen, nutze lokale Fallback für: $query", 'WARNING');
            return $this->getLocalSearchResults($query, $limit);
        }

        $results = [];
        $count = 0;

        // Coins verarbeiten
        if (isset($response['coins']) && is_array($response['coins'])) {
            foreach ($response['coins'] as $coin) {
                if ($count >= $limit) break;

                $results[] = [
                    'id' => $coin['id'],
                    'symbol' => strtoupper($coin['symbol']),
                    'name' => $coin['name'],
                    'market_cap_rank' => $coin['market_cap_rank'] ?? 999999
                ];
                $count++;
            }
        }

        // Cache speichern
        $this->saveToCache($cache_key, $results);
        $this->logMessage("Search erfolgreich für: $query, " . count($results) . " Ergebnisse", 'INFO');

        return $results;
    }

    /**
     * Get local search results as fallback
     * @param string $query
     * @param int $limit
     * @return array
     */
    private function getLocalSearchResults($query, $limit = 20)
    {
        // ERWEITERTE LISTE FÜR BESSERE ABDECKUNG
        $popularCryptos = [
            ['id' => 'bitcoin', 'symbol' => 'BTC', 'name' => 'Bitcoin', 'market_cap_rank' => 1],
            ['id' => 'ethereum', 'symbol' => 'ETH', 'name' => 'Ethereum', 'market_cap_rank' => 2],
            ['id' => 'tether', 'symbol' => 'USDT', 'name' => 'Tether', 'market_cap_rank' => 3],
            ['id' => 'binancecoin', 'symbol' => 'BNB', 'name' => 'BNB', 'market_cap_rank' => 4],
            ['id' => 'solana', 'symbol' => 'SOL', 'name' => 'Solana', 'market_cap_rank' => 5],
            ['id' => 'usd-coin', 'symbol' => 'USDC', 'name' => 'USD Coin', 'market_cap_rank' => 6],
            ['id' => 'ripple', 'symbol' => 'XRP', 'name' => 'XRP', 'market_cap_rank' => 7],
            ['id' => 'dogecoin', 'symbol' => 'DOGE', 'name' => 'Dogecoin', 'market_cap_rank' => 8],
            ['id' => 'cardano', 'symbol' => 'ADA', 'name' => 'Cardano', 'market_cap_rank' => 9],
            ['id' => 'avalanche-2', 'symbol' => 'AVAX', 'name' => 'Avalanche', 'market_cap_rank' => 10],
            ['id' => 'tron', 'symbol' => 'TRX', 'name' => 'TRON', 'market_cap_rank' => 11],
            ['id' => 'chainlink', 'symbol' => 'LINK', 'name' => 'Chainlink', 'market_cap_rank' => 12],
            ['id' => 'polkadot', 'symbol' => 'DOT', 'name' => 'Polkadot', 'market_cap_rank' => 13],
            ['id' => 'matic-network', 'symbol' => 'MATIC', 'name' => 'Polygon', 'market_cap_rank' => 14],
            ['id' => 'litecoin', 'symbol' => 'LTC', 'name' => 'Litecoin', 'market_cap_rank' => 15],
            ['id' => 'bitcoin-cash', 'symbol' => 'BCH', 'name' => 'Bitcoin Cash', 'market_cap_rank' => 16],
            ['id' => 'stellar', 'symbol' => 'XLM', 'name' => 'Stellar', 'market_cap_rank' => 17],
            ['id' => 'ethereum-classic', 'symbol' => 'ETC', 'name' => 'Ethereum Classic', 'market_cap_rank' => 18],
            ['id' => 'monero', 'symbol' => 'XMR', 'name' => 'Monero', 'market_cap_rank' => 19],
            ['id' => 'cosmos', 'symbol' => 'ATOM', 'name' => 'Cosmos', 'market_cap_rank' => 20],
            ['id' => 'algorand', 'symbol' => 'ALGO', 'name' => 'Algorand', 'market_cap_rank' => 21],
            ['id' => 'vechain', 'symbol' => 'VET', 'name' => 'VeChain', 'market_cap_rank' => 22],
            ['id' => 'filecoin', 'symbol' => 'FIL', 'name' => 'Filecoin', 'market_cap_rank' => 23],
            ['id' => 'hedera-hashgraph', 'symbol' => 'HBAR', 'name' => 'Hedera', 'market_cap_rank' => 24],
            ['id' => 'internet-computer', 'symbol' => 'ICP', 'name' => 'Internet Computer', 'market_cap_rank' => 25],
            ['id' => 'the-sandbox', 'symbol' => 'SAND', 'name' => 'The Sandbox', 'market_cap_rank' => 26],
            ['id' => 'decentraland', 'symbol' => 'MANA', 'name' => 'Decentraland', 'market_cap_rank' => 27],
            ['id' => 'aave', 'symbol' => 'AAVE', 'name' => 'Aave', 'market_cap_rank' => 28],
            ['id' => 'shiba-inu', 'symbol' => 'SHIB', 'name' => 'Shiba Inu', 'market_cap_rank' => 29],
            ['id' => 'cronos', 'symbol' => 'CRO', 'name' => 'Cronos', 'market_cap_rank' => 30]
        ];

        $results = array_filter($popularCryptos, function ($crypto) use ($query) {
            return (
                stripos($crypto['symbol'], $query) !== false ||
                stripos($crypto['name'], $query) !== false ||
                stripos($crypto['id'], $query) !== false
            );
        });

        return array_slice(array_values($results), 0, $limit);
    }

    /**
     * Get current price for multiple cryptocurrencies
     * @param array $symbols Array of symbols like ['BTC', 'ETH', 'XRP']
     * @return array|false Returns price data or false if API unavailable
     */
    public function getCurrentPrices($symbols)
    {
        if (empty($symbols)) {
            return false;
        }

        $cache_key = 'prices_' . md5(implode(',', $symbols));

        // Prüfe Cache zuerst
        $cached_data = $this->getFromCache($cache_key);
        if ($cached_data !== false) {
            $this->logMessage("Price cache hit für " . count($symbols) . " Symbole", 'DEBUG');
            return $cached_data;
        }

        // Konvertiere Symbole zu CoinGecko IDs
        $converted_symbols = array_map([$this, 'convertSymbolToId'], $symbols);
        $symbols_string = implode(',', $converted_symbols);

        $url = "{$this->base_url}/simple/price?ids={$symbols_string}&vs_currencies=eur&include_24hr_change=true&include_last_updated_at=true";

        $response = $this->makeRequestWithRetry($url);

        if ($response === false) {
            $this->last_error = 'Crypto-Preise konnten nicht abgerufen werden. API nicht erreichbar.';
            $this->logMessage("Price request fehlgeschlagen für: " . implode(',', $symbols), 'ERROR');
            return false;
        }

        // Format response for easier access
        $formatted_response = [];
        foreach ($response as $id => $data) {
            if (isset($data['eur']) && is_numeric($data['eur']) && $data['eur'] > 0) {
                $formatted_response[$id] = $data['eur'];
                $formatted_response[$id . '_change'] = $data['eur_24h_change'] ?? 0;
                $formatted_response[$id . '_updated'] = $data['last_updated_at'] ?? time();
            }
        }

        if (empty($formatted_response)) {
            $this->last_error = 'Keine gültigen Preisdaten erhalten.';
            $this->logMessage("Keine gültigen Preisdaten für: " . implode(',', $symbols), 'WARNING');
            return false;
        }

        $this->saveToCache($cache_key, $formatted_response);
        $this->logMessage("Preise erfolgreich abgerufen für " . count($formatted_response) . " Assets", 'INFO');
        return $formatted_response;
    }

    /**
     * Get single cryptocurrency current price
     * @param string $symbol Like 'BTC', 'ETH'
     * @return float|false Current price or false if not available
     */
    public function getCurrentPrice($symbol)
    {
        $prices = $this->getCurrentPrices([$symbol]);
        if ($prices === false) {
            return false;
        }

        $converted_symbol = $this->convertSymbolToId($symbol);
        return $prices[$converted_symbol] ?? false;
    }

    /**
     * Get last error message
     * @return string
     */
    public function getLastError()
    {
        return $this->last_error;
    }

    /**
     * Check if API is currently available
     * @return bool
     */
    public function isApiAvailable()
    {
        $test_response = $this->makeRequest($this->base_url . '/simple/price?ids=bitcoin&vs_currencies=eur');
        $available = $test_response !== false;
        $this->logMessage("API Verfügbarkeitscheck: " . ($available ? "VERFÜGBAR" : "NICHT VERFÜGBAR"), $available ? 'INFO' : 'WARNING');
        return $available;
    }

    /**
     * Convert common symbols to CoinGecko IDs - ERWEITERTE VERSION
     * @param string $symbol
     * @return string
     */
    public function convertSymbolToId($symbol)
    {
        // DEUTLICH ERWEITERTE SYMBOL-MAP
        $symbol_map = [
            // Top Cryptocurrencies
            'BTC' => 'bitcoin',
            'ETH' => 'ethereum',
            'USDT' => 'tether',
            'BNB' => 'binancecoin',
            'SOL' => 'solana',
            'USDC' => 'usd-coin',
            'XRP' => 'ripple',
            'DOGE' => 'dogecoin',
            'ADA' => 'cardano',
            'AVAX' => 'avalanche-2',
            'TRX' => 'tron',
            'LINK' => 'chainlink',
            'DOT' => 'polkadot',
            'MATIC' => 'matic-network',
            'LTC' => 'litecoin',
            'BCH' => 'bitcoin-cash',
            'XLM' => 'stellar',
            'ETC' => 'ethereum-classic',
            'XMR' => 'monero',
            'ATOM' => 'cosmos',

            // Additional Popular Coins
            'ALGO' => 'algorand',
            'VET' => 'vechain',
            'FIL' => 'filecoin',
            'HBAR' => 'hedera-hashgraph',
            'ICP' => 'internet-computer',
            'SAND' => 'the-sandbox',
            'MANA' => 'decentraland',
            'AAVE' => 'aave',
            'SHIB' => 'shiba-inu',
            'CRO' => 'cronos',
            'UNI' => 'uniswap',
            'NEAR' => 'near',
            'APT' => 'aptos',
            'QNT' => 'quant-network',
            'IMX' => 'immutable-x',
            'GRT' => 'the-graph',
            'LIDO' => 'lido-dao',
            'LDO' => 'lido-dao',
            'ARB' => 'arbitrum',
            'OP' => 'optimism',
            'MKR' => 'maker',
            'RUNE' => 'thorchain',
            'FTM' => 'fantom',
            'EGLD' => 'elrond-erd-2',
            'XTZ' => 'tezos',
            'FLOW' => 'flow',
            'KCS' => 'kucoin-shares',
            'CHZ' => 'chiliz',
            'MINA' => 'mina-protocol',
            'AXS' => 'axie-infinity',
            'THETA' => 'theta-token',
            'APE' => 'apecoin',
            'GMT' => 'stepn',
            'ENJ' => 'enjincoin',
            'BAT' => 'basic-attention-token',
            'COMP' => 'compound-governance-token',
            'YFI' => 'yearn-finance',
            'SNX' => 'havven',
            'CRV' => 'curve-dao-token',
            'SUSHI' => 'sushi',
            '1INCH' => '1inch',
            'OCEAN' => 'ocean-protocol',
            'FET' => 'fetch-ai',
            'REN' => 'republic-protocol',
            'ZIL' => 'zilliqa',
            'HOT' => 'holotoken',
            'ONT' => 'ontology',
            'ICX' => 'icon',
            'QTUM' => 'qtum',
            'ZEC' => 'zcash',
            'DASH' => 'dash',
            'DCR' => 'decred',
            'DGB' => 'digibyte',
            'RVN' => 'ravencoin',
            'SC' => 'siacoin'
        ];

        $converted = $symbol_map[strtoupper($symbol)] ?? strtolower($symbol);
        if ($converted !== strtolower($symbol)) {
            $this->logMessage("Symbol konvertiert: $symbol -> $converted", 'DEBUG');
        }
        return $converted;
    }

    // ========================================
    // NEUE VERBESSERTE FUNKTIONEN
    // ========================================

    /**
     * NEUE FUNKTION: Validierung für Investment-Daten
     */
    public function validateInvestmentData($symbol, $amount, $price)
    {
        $errors = [];

        // Symbol-Validierung
        if (!preg_match('/^[A-Z0-9]{1,10}$/i', $symbol)) {
            $errors[] = "Ungültiges Symbol-Format: $symbol";
        }

        // Realistische Mengen-Validierung
        if (!is_numeric($amount) || $amount <= 0 || $amount > 1000000000) {
            $errors[] = "Unrealistische Menge: $amount";
        }

        if (is_numeric($amount) && $amount < 0.00000001) {
            $errors[] = "Menge zu klein (minimum: 0.00000001)";
        }

        // Preis-Validierung
        if (!is_numeric($price) || $price <= 0 || $price > 10000000) {
            $errors[] = "Unrealistischer Preis: $price EUR";
        }

        $this->logMessage("Validierung für $symbol: " . (empty($errors) ? "OK" : implode(', ', $errors)), empty($errors) ? 'INFO' : 'WARNING');
        return $errors;
    }

    /**
     * NEUE FUNKTION: Rate-Limiting implementieren
     */
    private function checkRateLimit()
    {
        $current_time = time();

        if (file_exists($this->rate_limit_file)) {
            $rate_data = json_decode(file_get_contents($this->rate_limit_file), true);

            // Reset counter jede Minute
            if ($current_time - $rate_data['timestamp'] > 60) {
                $rate_data = ['count' => 0, 'timestamp' => $current_time];
            }

            if ($rate_data['count'] >= $this->max_requests_per_minute) {
                $this->last_error = "Rate limit erreicht. Warte 60 Sekunden.";
                $this->logMessage("Rate limit erreicht: {$rate_data['count']} requests in der letzten Minute", 'WARNING');
                return false;
            }

            $rate_data['count']++;
        } else {
            $rate_data = ['count' => 1, 'timestamp' => $current_time];
        }

        file_put_contents($this->rate_limit_file, json_encode($rate_data));
        return true;
    }

    /**
     * NEUE FUNKTION: Verbesserte API-Anfrage mit Retry-Logik
     */
    private function makeRequestWithRetry($url, $max_retries = 3)
    {
        if (!$this->checkRateLimit()) {
            return false;
        }

        for ($i = 0; $i < $max_retries; $i++) {
            $response = $this->makeRequest($url);

            if ($response !== false) {
                if ($i > 0) {
                    $this->logMessage("Request erfolgreich nach " . ($i + 1) . " Versuchen", 'INFO');
                }
                return $response;
            }

            // Exponential backoff: 1s, 2s, 4s
            if ($i < $max_retries - 1) {
                $sleep_time = pow(2, $i);
                $this->logMessage("Request fehlgeschlagen, warte {$sleep_time}s vor Retry " . ($i + 2), 'WARNING');
                sleep($sleep_time);
            }
        }

        $this->logMessage("Request nach $max_retries Versuchen endgültig fehlgeschlagen", 'ERROR');
        return false;
    }

    /**
     * NEUE FUNKTION: System Health-Check
     */
    public function systemHealthCheck()
    {
        $health = [
            'api_available' => false,
            'cache_writable' => false,
            'ssl_working' => false,
            'rate_limit_ok' => false,
            'cache_size' => 0,
            'last_update' => null,
            'errors' => [],
            'warnings' => []
        ];

        // API-Verfügbarkeit testen
        $health['api_available'] = $this->isApiAvailable();

        // Cache-Verzeichnis testen
        $cache_dir = dirname($this->cache_file);
        $health['cache_writable'] = is_writable($cache_dir);

        // Cache-Größe ermitteln
        if (file_exists($this->cache_file)) {
            $health['cache_size'] = filesize($this->cache_file);
            $health['last_update'] = date('Y-m-d H:i:s', filemtime($this->cache_file));
        }

        // SSL testen (für Produktion)
        $health['ssl_working'] = $this->testSSLConnection();

        // Rate-Limit Status
        $health['rate_limit_ok'] = $this->checkRateLimit();

        // Fehler sammeln
        if (!$health['api_available']) {
            $health['errors'][] = 'CoinGecko API nicht erreichbar';
        }

        if (!$health['cache_writable']) {
            $health['errors'][] = 'Cache-Verzeichnis nicht beschreibbar';
        }

        if (!$health['ssl_working']) {
            $health['warnings'][] = 'SSL-Verbindung fehlerhaft (nur für Produktion kritisch)';
        }

        if (!$health['rate_limit_ok']) {
            $health['warnings'][] = 'Rate limit erreicht';
        }

        $this->logMessage("Health Check: " . (empty($health['errors']) ? "OK" : implode(', ', $health['errors'])), empty($health['errors']) ? 'INFO' : 'ERROR');
        return $health;
    }

    /**
     * NEUE FUNKTION: SSL-Verbindung testen - KORRIGIERTE VERSION
     */
    private function testSSLConnection()
    {
        if (!function_exists('curl_init')) {
            return false;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->base_url . '/ping',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,  // Für lokale Entwicklung deaktiviert
            CURLOPT_SSL_VERIFYHOST => false   // Für lokale Entwicklung deaktiviert
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Erfolg wenn Response da ist und kein cURL-Fehler
        $success = ($response !== false && empty($error));

        $this->logMessage("SSL Test: " . ($success ? "OK" : "FEHLER - $error") . " (HTTP: $http_code)", $success ? 'DEBUG' : 'WARNING');
        return $success;
    }

    /**
     * NEUE FUNKTION: Debug-Logging - KORRIGIERTE VERSION
     */
    private function logMessage($message, $level = 'INFO')
    {
        if (!$this->debug_mode) {
            return;
        }

        $timestamp = TimezoneHelper::getCurrentUserTime('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;

        // Log in Datei schreiben (mit Fehlerbehandlung)
        if (isset($this->log_file)) {
            @file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
        }

        // In kritischen Fällen auch in PHP error log
        if ($level === 'ERROR') {
            error_log("CryptoAPI ERROR: $message");
        }

        // In Debug-Modus auch direkt ausgeben (für Entwicklung)
        if ($level === 'ERROR' && php_sapi_name() === 'cli') {
            echo $log_entry;
        }
    }

    /**
     * NEUE FUNKTION: Cache-Management - KORRIGIERTE VERSION
     */
    public function clearCache()
    {
        $files_removed = 0;
        $cache_dir = dirname($this->cache_file);

        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '/crypto_*.json');
            foreach ($files as $file) {
                if (@unlink($file)) {
                    $files_removed++;
                }
            }

            // Auch Rate-Limit-Datei löschen
            if (file_exists($this->rate_limit_file)) {
                @unlink($this->rate_limit_file);
            }
        }

        $this->logMessage("Cache geleert: $files_removed Dateien entfernt", 'INFO');
        return $files_removed;
    }

    /**
     * NEUE FUNKTION: Cache-Statistiken - KORRIGIERTE VERSION
     */
    public function getCacheStats()
    {
        $stats = [
            'cache_hits' => 0,
            'cache_misses' => 0,
            'cache_size' => 0,
            'cache_files' => 0,
            'oldest_cache' => null,
            'newest_cache' => null,
            'cache_directory' => dirname($this->cache_file)
        ];

        $cache_dir = dirname($this->cache_file);
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '/crypto_*.json');
            $stats['cache_files'] = count($files);

            $total_size = 0;
            $oldest = PHP_INT_MAX;
            $newest = 0;

            foreach ($files as $file) {
                $size = @filesize($file);
                if ($size !== false) {
                    $total_size += $size;
                }

                $mtime = @filemtime($file);
                if ($mtime !== false) {
                    $oldest = min($oldest, $mtime);
                    $newest = max($newest, $mtime);
                }
            }

            $stats['cache_size'] = $total_size;
            $stats['oldest_cache'] = $oldest < PHP_INT_MAX ? date('Y-m-d H:i:s', $oldest) : null;
            $stats['newest_cache'] = $newest > 0 ? date('Y-m-d H:i:s', $newest) : null;
        }

        return $stats;
    }

    // ========================================
    // BESTEHENDE PRIVATE FUNKTIONEN (TEILWEISE VERBESSERT)
    // ========================================

    /**
     * Make HTTP request to API using cURL - KORRIGIERTE VERSION
     * @param string $url
     * @return array|false
     */
    private function makeRequest($url)
    {
        $this->last_error = '';

        // Prüfe ob cURL verfügbar ist
        if (!function_exists('curl_init')) {
            $this->last_error = "cURL ist nicht verfügbar";
            $this->logMessage("cURL nicht verfügbar", 'ERROR');
            return false;
        }

        $ch = curl_init();

        // KORRIGIERT: Immer lokale Entwicklung für bessere Kompatibilität
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,           // Längeres Timeout
            CURLOPT_CONNECTTIMEOUT => 10,    // Längeres Connect-Timeout
            CURLOPT_USERAGENT => 'Meine Firma Finance/1.0 (Windows)',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => false, // SSL-Überprüfung deaktiviert für Kompatibilität
            CURLOPT_SSL_VERIFYHOST => false, // Host-Überprüfung deaktiviert
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Cache-Control: no-cache'
            ],
            // Windows-spezifische Einstellungen
            CURLOPT_CAINFO => false,         // CA-Bundle deaktiviert
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1  // HTTP 1.1 forcieren
        ]);

        $start_time = microtime(true);
        $response = curl_exec($ch);
        $request_time = microtime(true) - $start_time;

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $curl_info = curl_getinfo($ch);

        curl_close($ch);

        $this->logMessage("Request zu $url: HTTP $http_code in " . round($request_time * 1000, 2) . "ms", 'DEBUG');

        if ($response === false || !empty($error)) {
            $this->last_error = "cURL Fehler: " . $error;
            $this->logMessage("cURL Fehler für $url: $error | Info: " . json_encode($curl_info), 'ERROR');
            return false;
        }

        if ($http_code !== 200) {
            $this->last_error = "HTTP Fehler: $http_code";
            $this->logMessage("HTTP Fehler für $url: $http_code | Response: " . substr($response, 0, 200), 'ERROR');
            return false;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->last_error = "JSON Fehler: " . json_last_error_msg();
            $this->logMessage("JSON Fehler für $url: " . json_last_error_msg() . " | Response: " . substr($response, 0, 200), 'ERROR');
            return false;
        }

        $this->logMessage("Request erfolgreich: " . strlen($response) . " bytes empfangen", 'DEBUG');
        return $data;
    }

    /**
     * Get data from cache - KORRIGIERTE VERSION
     * @param string $key
     * @param int $max_age
     * @return mixed|false
     */
    private function getFromCache($key, $max_age = null)
    {
        $max_age = $max_age ?? $this->cache_duration;

        // KORRIGIERT: Verwende das Cache-Verzeichnis von ensureCacheDirectoryExists()
        $cache_dir = dirname($this->cache_file);
        $cache_file = $cache_dir . '/crypto_' . $key . '.json';

        if (!file_exists($cache_file)) {
            return false;
        }

        $file_age = time() - filemtime($cache_file);
        if ($file_age > $max_age) {
            // Cache ist abgelaufen, löschen
            @unlink($cache_file); // @ um Warnings zu vermeiden
            $this->logMessage("Cache abgelaufen und gelöscht: $key (Alter: {$file_age}s)", 'DEBUG');
            return false;
        }

        $data = @file_get_contents($cache_file);
        if ($data === false) {
            $this->logMessage("Cache-Datei konnte nicht gelesen werden: $cache_file", 'WARNING');
            return false;
        }

        $decoded = json_decode($data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Beschädigter Cache, löschen
            @unlink($cache_file);
            $this->logMessage("Beschädigter Cache gelöscht: $key", 'WARNING');
            return false;
        }

        return $decoded;
    }

    /**
     * Save data to cache - KORRIGIERTE VERSION
     * @param string $key
     * @param mixed $data
     * @return bool
     */
    private function saveToCache($key, $data)
    {
        // KORRIGIERT: Verwende das Cache-Verzeichnis von ensureCacheDirectoryExists()
        $cache_dir = dirname($this->cache_file);
        $cache_file = $cache_dir . '/crypto_' . $key . '.json';

        $json_data = json_encode($data, JSON_PRETTY_PRINT);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logMessage("JSON Encoding Fehler beim Cachen: " . json_last_error_msg(), 'ERROR');
            return false;
        }

        $result = @file_put_contents($cache_file, $json_data, LOCK_EX);

        if ($result === false) {
            $this->logMessage("Cache-Datei konnte nicht geschrieben werden: $cache_file", 'ERROR');
            return false;
        }

        $this->logMessage("Cache gespeichert: $key (" . strlen($json_data) . " bytes)", 'DEBUG');
        return true;
    }

    // ========================================
    // NEUE UTILITY-FUNKTIONEN
    // ========================================

    /**
     * NEUE FUNKTION: Kompletter Systemtest
     */
    public function runSystemTest()
    {
        $results = [];

        echo "🔧 Meine Firma Finance Crypto API System-Test\n";
        echo "==========================================\n\n";

        // 1. Cache-Verzeichnis Test
        echo "1. Cache-Verzeichnis Test...\n";
        $cache_dir = dirname($this->cache_file);
        $cache_writable = is_dir($cache_dir) && is_writable($cache_dir);
        $results['cache'] = $cache_writable;
        echo $cache_writable ? "   ✅ Cache-Verzeichnis OK: $cache_dir\n" : "   ❌ Cache-Verzeichnis Problem: $cache_dir\n";

        // 2. cURL Test
        echo "\n2. cURL Verfügbarkeit...\n";
        $curl_available = function_exists('curl_init');
        $results['curl'] = $curl_available;
        echo $curl_available ? "   ✅ cURL verfügbar\n" : "   ❌ cURL NICHT verfügbar\n";

        // 3. Internet-Verbindung Test
        echo "\n3. Internet-Verbindung Test...\n";
        $internet_ok = $this->testBasicConnectivity();
        $results['internet'] = $internet_ok;
        echo $internet_ok ? "   ✅ Internet-Verbindung OK\n" : "   ❌ Internet-Verbindung Problem\n";

        // 4. CoinGecko API Test
        echo "\n4. CoinGecko API Test...\n";
        $api_available = $this->isApiAvailable();
        $results['api'] = $api_available;
        echo $api_available ? "   ✅ CoinGecko API erreichbar\n" : "   ❌ CoinGecko API NICHT erreichbar\n";

        // 5. Preis-Test
        echo "\n5. Preis-Abruf Test (Bitcoin)...\n";
        $btc_price = $this->getCurrentPrice('BTC');
        $results['price_test'] = ($btc_price !== false);
        if ($btc_price !== false) {
            echo "   ✅ Bitcoin-Preis erfolgreich abgerufen: €" . number_format($btc_price, 2) . "\n";
        } else {
            echo "   ❌ Bitcoin-Preis konnte nicht abgerufen werden\n";
            echo "   Fehler: " . $this->getLastError() . "\n";
        }

        // 6. Cache-Test
        echo "\n6. Cache-Funktionalität Test...\n";
        $test_data = ['test' => time()];
        $cache_save = $this->saveToCache('test', $test_data);
        $cache_load = $this->getFromCache('test');
        $cache_ok = $cache_save && ($cache_load !== false);
        $results['cache_function'] = $cache_ok;
        echo $cache_ok ? "   ✅ Cache funktioniert\n" : "   ❌ Cache funktioniert NICHT\n";

        // Zusammenfassung
        echo "\n==========================================\n";
        $all_ok = array_reduce($results, function ($carry, $item) {
            return $carry && $item;
        }, true);
        echo $all_ok ? "🎉 ALLE TESTS BESTANDEN!\n" : "⚠️  EINIGE TESTS FEHLGESCHLAGEN!\n";

        if (!$all_ok) {
            echo "\n🔧 LÖSUNGSVORSCHLÄGE:\n";
            if (!$results['cache']) echo "   - Cache-Verzeichnis-Berechtigungen prüfen\n";
            if (!$results['curl']) echo "   - cURL PHP-Extension installieren\n";
            if (!$results['internet']) echo "   - Internet-Verbindung und Firewall prüfen\n";
            if (!$results['api']) echo "   - CoinGecko API möglicherweise temporär nicht verfügbar\n";
        }

        echo "\n";
        return $results;
    }

    /**
     * NEUE FUNKTION: Basis-Konnektivitätstest
     */
    private function testBasicConnectivity()
    {
        if (!function_exists('curl_init')) {
            return false;
        }

        // Test mit Google DNS (schneller als API-Test)
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://8.8.8.8',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_NOBODY => true  // Nur Header abrufen
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return ($response !== false && empty($error));
    }

    /**
     * NEUE FUNKTION: Performance-Metriken
     */
    public function getPerformanceMetrics()
    {
        return [
            'requests_this_session' => $this->request_count,
            'max_requests_per_minute' => $this->max_requests_per_minute,
            'cache_duration_seconds' => $this->cache_duration,
            'search_cache_duration_seconds' => $this->search_cache_duration,
            'last_error' => $this->last_error,
            'debug_mode' => $this->debug_mode
        ];
    }

    /**
     * NEUE FUNKTION: API-Konfiguration setzen
     */
    public function setConfiguration($config)
    {
        if (isset($config['cache_duration'])) {
            $this->cache_duration = max(60, intval($config['cache_duration'])); // Mindestens 1 Minute
        }

        if (isset($config['search_cache_duration'])) {
            $this->search_cache_duration = max(300, intval($config['search_cache_duration'])); // Mindestens 5 Minuten
        }

        if (isset($config['max_requests_per_minute'])) {
            $this->max_requests_per_minute = max(10, min(50, intval($config['max_requests_per_minute']))); // Zwischen 10 und 50
        }

        if (isset($config['debug_mode'])) {
            $this->debug_mode = (bool)$config['debug_mode'];
        }

        $this->logMessage("Konfiguration aktualisiert", 'INFO');
    }

    /**
     * NEUE FUNKTION: Bulk-Preisabruf für große Portfolios
     */
    public function getBulkPrices($symbols, $chunk_size = 20)
    {
        if (empty($symbols)) {
            return [];
        }

        // Große Symbol-Listen in Chunks aufteilen
        $symbol_chunks = array_chunk($symbols, $chunk_size);
        $all_prices = [];

        foreach ($symbol_chunks as $i => $chunk) {
            $this->logMessage("Verarbeite Chunk " . ($i + 1) . "/" . count($symbol_chunks) . " mit " . count($chunk) . " Symbolen", 'INFO');

            $chunk_prices = $this->getCurrentPrices($chunk);

            if ($chunk_prices !== false) {
                $all_prices = array_merge($all_prices, $chunk_prices);
            }

            // Kurze Pause zwischen Chunks um Rate-Limits zu vermeiden
            if ($i < count($symbol_chunks) - 1) {
                sleep(1);
            }
        }

        return $all_prices;
    }
}
