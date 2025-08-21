<?php
// test-crypto.php - Diagnose-Skript für Crypto API Probleme
// Dieses Skript in das Hauptverzeichnis legen und über Browser aufrufen

header('Content-Type: text/plain; charset=utf-8');

echo "🔧 Meine Firma Finance - Crypto API Diagnose\n";
echo "==========================================\n\n";

// 1. PHP-Umgebung prüfen
echo "1. PHP-Umgebung:\n";
echo "   PHP Version: " . PHP_VERSION . "\n";
echo "   Betriebssystem: " . PHP_OS . "\n";
echo "   cURL verfügbar: " . (function_exists('curl_init') ? "✅ JA" : "❌ NEIN") . "\n";
echo "   JSON verfügbar: " . (function_exists('json_encode') ? "✅ JA" : "❌ NEIN") . "\n\n";

// 2. Verzeichnis-Struktur prüfen
echo "2. Verzeichnis-Struktur:\n";
$base_dir = __DIR__;
echo "   Hauptverzeichnis: $base_dir\n";

$config_dir = $base_dir . '/config';
echo "   Config-Verzeichnis: " . (is_dir($config_dir) ? "✅ EXISTS" : "❌ FEHLT") . "\n";

$cache_dir = $base_dir . '/cache';
echo "   Cache-Verzeichnis: " . (is_dir($cache_dir) ? "✅ EXISTS" : "🔧 WIRD ERSTELLT") . "\n";

// Cache-Verzeichnis erstellen falls nicht vorhanden
if (!is_dir($cache_dir)) {
    if (mkdir($cache_dir, 0755, true)) {
        echo "   Cache-Verzeichnis erstellt: ✅ OK\n";
    } else {
        echo "   Cache-Verzeichnis erstellen: ❌ FEHLGESCHLAGEN\n";
    }
}

echo "   Cache-Verzeichnis beschreibbar: " . (is_writable($cache_dir) ? "✅ JA" : "❌ NEIN") . "\n\n";

// 3. Crypto API laden und testen
echo "3. Crypto API Test:\n";

$crypto_api_file = $config_dir . '/crypto_api.php';
if (!file_exists($crypto_api_file)) {
    echo "   ❌ FEHLER: crypto_api.php nicht gefunden in $config_dir\n";
    echo "   Bitte stellen Sie sicher, dass die Datei existiert.\n\n";
    exit;
}

require_once $crypto_api_file;

if (!class_exists('CryptoAPI')) {
    echo "   ❌ FEHLER: CryptoAPI Klasse nicht gefunden\n";
    echo "   Möglicherweise Syntax-Fehler in crypto_api.php\n\n";
    exit;
}

echo "   CryptoAPI Klasse geladen: ✅ OK\n";

// API-Instanz erstellen (mit Debug-Modus)
try {
    $crypto_api = new CryptoAPI(true); // Debug an
    echo "   CryptoAPI Instanz erstellt: ✅ OK\n";
} catch (Exception $e) {
    echo "   ❌ FEHLER beim Erstellen der CryptoAPI Instanz: " . $e->getMessage() . "\n\n";
    exit;
}

echo "\n";

// 4. Vollständigen System-Test ausführen
if (method_exists($crypto_api, 'runSystemTest')) {
    $test_results = $crypto_api->runSystemTest();
} else {
    echo "4. Manuelle Tests:\n";

    // Internet-Test
    echo "   Internet-Verbindung: ";
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://httpbin.org/get',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response !== false && $http_code == 200) {
            echo "✅ OK\n";
        } else {
            echo "❌ FEHLER - $error (HTTP: $http_code)\n";
        }
    } else {
        echo "❌ cURL nicht verfügbar\n";
    }

    // CoinGecko API Test
    echo "   CoinGecko API: ";
    $api_available = $crypto_api->isApiAvailable();
    echo $api_available ? "✅ ERREICHBAR\n" : "❌ NICHT ERREICHBAR\n";

    if (!$api_available) {
        echo "   Letzter Fehler: " . $crypto_api->getLastError() . "\n";
    }

    // Preis-Test
    echo "   Bitcoin-Preis Test: ";
    $btc_price = $crypto_api->getCurrentPrice('BTC');
    if ($btc_price !== false) {
        echo "✅ €" . number_format($btc_price, 2) . "\n";
    } else {
        echo "❌ FEHLGESCHLAGEN\n";
        echo "   Fehler: " . $crypto_api->getLastError() . "\n";
    }
}

// 5. Detaillierte Diagnose
echo "\n5. Detaillierte Diagnose:\n";

// Cache-Statistiken
if (method_exists($crypto_api, 'getCacheStats')) {
    $cache_stats = $crypto_api->getCacheStats();
    echo "   Cache-Verzeichnis: " . $cache_stats['cache_directory'] . "\n";
    echo "   Cache-Dateien: " . $cache_stats['cache_files'] . "\n";
    echo "   Cache-Größe: " . formatBytes($cache_stats['cache_size']) . "\n";
}

// Performance-Metriken
if (method_exists($crypto_api, 'getPerformanceMetrics')) {
    $metrics = $crypto_api->getPerformanceMetrics();
    echo "   Max Requests/Minute: " . $metrics['max_requests_per_minute'] . "\n";
    echo "   Cache-Dauer: " . $metrics['cache_duration_seconds'] . "s\n";
    echo "   Debug-Modus: " . ($metrics['debug_mode'] ? "AN" : "AUS") . "\n";
}

// System Health Check
if (method_exists($crypto_api, 'systemHealthCheck')) {
    echo "\n6. Health Check:\n";
    $health = $crypto_api->systemHealthCheck();

    foreach ($health as $key => $value) {
        if ($key === 'errors' || $key === 'warnings') continue;

        if (is_bool($value)) {
            echo "   " . ucfirst($key) . ": " . ($value ? "✅ OK" : "❌ FEHLER") . "\n";
        } else {
            echo "   " . ucfirst($key) . ": $value\n";
        }
    }

    if (!empty($health['errors'])) {
        echo "\n   🚨 FEHLER:\n";
        foreach ($health['errors'] as $error) {
            echo "   - $error\n";
        }
    }

    if (!empty($health['warnings'])) {
        echo "\n   ⚠️  WARNUNGEN:\n";
        foreach ($health['warnings'] as $warning) {
            echo "   - $warning\n";
        }
    }
}

// 7. Lösungsvorschläge
echo "\n==========================================\n";
echo "🔧 LÖSUNGSVORSCHLÄGE:\n\n";

echo "Falls Probleme auftreten:\n\n";

echo "1. Cache-Probleme:\n";
echo "   - Stellen Sie sicher, dass der 'cache' Ordner existiert\n";
echo "   - Prüfen Sie Schreibrechte: chmod 755 cache/\n";
echo "   - Windows: Rechtsklick auf cache-Ordner → Eigenschaften → Sicherheit\n\n";

echo "2. API-Verbindungsprobleme:\n";
echo "   - Prüfen Sie Ihre Internet-Verbindung\n";
echo "   - Firewall/Antivirus könnte HTTPS-Verbindungen blockieren\n";
echo "   - CoinGecko könnte temporär überlastet sein (später versuchen)\n\n";

echo "3. cURL-Probleme:\n";
echo "   - Windows: Stellen Sie sicher, dass cURL aktiviert ist in php.ini\n";
echo "   - Linux: sudo apt-get install php-curl\n";
echo "   - XAMPP: cURL sollte standardmäßig aktiviert sein\n\n";

echo "4. Allgemeine Tipps:\n";
echo "   - Starten Sie den Webserver neu (Apache/Nginx)\n";
echo "   - Leeren Sie den Browser-Cache\n";
echo "   - Prüfen Sie das PHP Error Log für weitere Details\n\n";

echo "==========================================\n";
echo "Test abgeschlossen. Bei anhaltenden Problemen kontaktieren Sie den Support.\n";

// Hilfsfunktion für Dateigröße-Formatierung
function formatBytes($size, $precision = 2)
{
    $units = array('B', 'KB', 'MB', 'GB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}
