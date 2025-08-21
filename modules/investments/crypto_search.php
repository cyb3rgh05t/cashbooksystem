<?php
// modules/investments/crypto_search.php - AJAX-Endpoint für Crypto-Suche

session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Set JSON content type
header('Content-Type: application/json');

require_once '../../config/crypto_api.php';

try {
    // Get search query from POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $query = trim($input['query'] ?? '');
    $limit = intval($input['limit'] ?? 15);

    // Validate input
    if (empty($query)) {
        echo json_encode(['results' => []]);
        exit;
    }

    if (strlen($query) < 2) {
        echo json_encode(['results' => []]);
        exit;
    }

    // Sanitize limit
    $limit = max(1, min(50, $limit)); // Between 1 and 50

    // Create API instance and search
    $crypto_api = new CryptoAPI();
    $results = $crypto_api->searchCryptocurrencies($query, $limit);

    if ($results === false) {
        // API failed, return error but with empty results
        echo json_encode([
            'results' => [],
            'error' => $crypto_api->getLastError(),
            'fallback_used' => true
        ]);
        exit;
    }

    // Format results for frontend
    $formatted_results = [];
    foreach ($results as $crypto) {
        $formatted_results[] = [
            'id' => $crypto['id'],
            'symbol' => strtoupper($crypto['symbol']),
            'name' => $crypto['name'],
            'rank' => $crypto['market_cap_rank'] ?? 999999
        ];
    }

    echo json_encode([
        'results' => $formatted_results,
        'count' => count($formatted_results),
        'query' => $query,
        'api_available' => $crypto_api->isApiAvailable()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error occurred',
        'results' => []
    ]);
}
