<?php
// Test ob HTTPS funktioniert
$url = 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=eur';

echo "Testing CoinGecko API...\n";

// Test mit cURL
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Für lokale Tests

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "cURL Response: HTTP $http_code\n";
    echo "Data: " . substr($response, 0, 100) . "\n";
    if ($error) echo "Error: $error\n";
}
