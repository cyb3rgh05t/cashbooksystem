<?php

/**
 * Globale Logger-Initialisierung
 * Diese Datei wird in alle wichtigen Seiten eingebunden
 */

// Logger-Klasse laden
require_once __DIR__ . '/logger.class.php';

// Globalen Logger erstellen
global $logger;

try {
    // Versuche mit Datenbank-Verbindung
    require_once __DIR__ . '/../config/database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    $logger = new Logger($pdo);
} catch (Exception $e) {
    // Fallback ohne Datenbank (nur File-Logging)
    $logger = new Logger(null);
    $logger->error("Datenbank nicht verfügbar, nur File-Logging aktiv: " . $e->getMessage());
}

// Automatisches Logging für jeden Seitenaufruf
function logPageAccess()
{
    global $logger;

    $page = $_SERVER['REQUEST_URI'] ?? 'Unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'Unknown';
    $userId = $_SESSION['user_id'] ?? null;

    // Grundlegende Seiten-Zugriffe loggen
    $logger->info(
        "Page Access: {$method} {$page}",
        $userId,
        'PAGE_ACCESS',
        [
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'session_id' => session_id()
        ]
    );
}

// Automatisches Error-Logging
function logError($error, $context = [])
{
    global $logger;

    $userId = $_SESSION['user_id'] ?? null;

    $logger->error(
        $error,
        $userId,
        'ERROR',
        $context
    );
}

// Automatisches Success-Logging
function logSuccess($message, $category = 'GENERAL')
{
    global $logger;

    $userId = $_SESSION['user_id'] ?? null;

    $logger->success(
        $message,
        $userId,
        $category
    );
}

// PHP-Fehler abfangen und loggen
set_error_handler(function ($severity, $message, $file, $line) {
    global $logger;

    // Ignoriere unterdrückte Fehler
    if (!(error_reporting() & $severity)) {
        return false;
    }

    $errorTypes = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE_ERROR',
        E_CORE_WARNING => 'CORE_WARNING',
        E_COMPILE_ERROR => 'COMPILE_ERROR',
        E_COMPILE_WARNING => 'COMPILE_WARNING',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        E_STRICT => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER_DEPRECATED'
    ];

    $errorType = $errorTypes[$severity] ?? 'UNKNOWN';

    $logger->error(
        "PHP {$errorType}: {$message}",
        $_SESSION['user_id'] ?? null,
        'PHP_ERROR',
        [
            'file' => $file,
            'line' => $line,
            'severity' => $severity
        ]
    );

    // Normale PHP-Fehlerbehandlung fortsetzen
    return false;
});

// Shutdown-Handler für fatale Fehler
register_shutdown_function(function () {
    global $logger;

    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $logger->critical(
            "FATAL: {$error['message']}",
            $_SESSION['user_id'] ?? null,
            'FATAL_ERROR',
            [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ]
        );
    }
});

// Exception-Handler
set_exception_handler(function ($exception) {
    global $logger;

    $logger->critical(
        "Uncaught Exception: " . $exception->getMessage(),
        $_SESSION['user_id'] ?? null,
        'EXCEPTION',
        [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]
    );

    // Benutzerfreundliche Fehlerseite anzeigen
    echo "<h1>Ein Fehler ist aufgetreten</h1>";
    echo "<p>Der Fehler wurde protokolliert. Bitte versuchen Sie es später erneut.</p>";
});

// Logging starten
logPageAccess();
