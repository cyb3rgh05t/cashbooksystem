<?php

/**
 * License Helper Class für Cashbook System
 * Verwaltet die Kommunikation mit dem Lizenzserver
 */

class LicenseHelper
{
    private $config;
    private $pdo;
    private $logger;

    public function __construct($pdo = null, $logger = null)
    {
        $this->config = require __DIR__ . '/../config/license.php';
        $this->pdo = $pdo;
        $this->logger = $logger;

        // Tabelle für Lizenz-Cache erstellen falls nicht vorhanden
        $this->createLicenseTable();
    }

    /**
     * Erstellt die License-Tabelle in der SQLite-DB falls nicht vorhanden
     */
    private function createLicenseTable()
    {
        if (!$this->pdo) return;

        $sql = "CREATE TABLE IF NOT EXISTS license_cache (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            license_key TEXT UNIQUE NOT NULL,
            hardware_id TEXT NOT NULL,
            validation_data TEXT,
            last_validation DATETIME,
            expires_at DATETIME,
            is_valid INTEGER DEFAULT 0,
            features TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";

        try {
            $this->pdo->exec($sql);
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Fehler beim Erstellen der License-Tabelle: " . $e->getMessage());
            }
        }
    }

    /**
     * Generiert eine Hardware-ID für dieses System
     */
    public function generateHardwareId()
    {
        $method = $this->config['hardware_id_method'] ?? 'auto';

        switch ($method) {
            case 'ip_based':
                return md5($_SERVER['SERVER_ADDR'] ?? 'localhost');

            case 'manual':
                // Aus Datei lesen oder generieren
                $file = __DIR__ . '/../data/hardware_id.txt';
                if (file_exists($file)) {
                    return trim(file_get_contents($file));
                }
                $id = $this->generateUniqueId();
                if (!is_dir(dirname($file))) {
                    mkdir(dirname($file), 0755, true);
                }
                file_put_contents($file, $id);
                return $id;

            case 'auto':
            default:
                // Kombination aus verschiedenen Faktoren
                $factors = [
                    $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                    $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
                    php_uname('n'), // Hostname
                    PHP_OS
                ];
                return md5(implode('|', $factors));
        }
    }

    /**
     * Generiert eine eindeutige ID
     */
    private function generateUniqueId()
    {
        return 'CB-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12));
    }

    /**
     * Validiert eine Lizenz gegen den Server
     */
    public function validateLicense($licenseKey, $forceOnline = false)
    {
        if (!$this->config['enabled']) {
            return ['valid' => true, 'features' => [], 'message' => 'Lizenzprüfung deaktiviert'];
        }

        $hardwareId = $this->generateHardwareId();

        // Bei forceOnline NIEMALS Cache verwenden!
        if ($forceOnline) {
            // Lösche alten Cache für diese Lizenz
            if ($this->pdo) {
                try {
                    $stmt = $this->pdo->prepare("DELETE FROM license_cache WHERE license_key = ?");
                    $stmt->execute([$licenseKey]);
                } catch (Exception $e) {
                    // Ignore
                }
            }

            // Direkt online validieren
            return $this->validateOnline($licenseKey, $hardwareId);
        }

        // Nur wenn NICHT forced: Cache prüfen (aber sehr kurz!)
        $cached = $this->getCachedLicense($licenseKey, $hardwareId);
        if ($cached && $this->isCacheValid($cached)) {
            return [
                'valid' => true,
                'cached' => true,
                'data' => json_decode($cached['validation_data'], true),
                'features' => json_decode($cached['features'], true) ?? []
            ];
        }

        // Online-Validierung
        $result = $this->validateOnline($licenseKey, $hardwareId);

        // Cache das Ergebnis (aber nur wenn valid)
        if ($result['valid']) {
            $this->cacheLicense($licenseKey, $hardwareId, $result);
        } else {
            // Bei ungültiger Lizenz: Cache löschen!
            if ($this->pdo) {
                try {
                    $stmt = $this->pdo->prepare("DELETE FROM license_cache WHERE license_key = ?");
                    $stmt->execute([$licenseKey]);
                } catch (Exception $e) {
                    // Ignore
                }
            }
        }

        return $result;
    }

    /**
     * Online-Validierung gegen den Lizenzserver
     */
    private function validateOnline($licenseKey, $hardwareId)
    {
        $url = $this->config['api_url'] . '/validate.php';

        $data = [
            'license_key' => $licenseKey,
            'hardware_id' => $hardwareId,
            'app_version' => '2.0',
            'timestamp' => time()
        ];

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($data),
                'timeout' => 10
            ]
        ];

        $context = stream_context_create($options);

        try {
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                // Server nicht erreichbar - prüfe Offline-Grace-Period
                return $this->handleOfflineMode($licenseKey, $hardwareId);
            }

            $result = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'valid' => false,
                    'error' => 'Ungültige Server-Antwort'
                ];
            }

            return [
                'valid' => $result['valid'] ?? false,
                'data' => $result,
                'features' => $result['features'] ?? [],
                'expires_at' => $result['expires_at'] ?? null,
                'user_info' => $result['user_info'] ?? []
            ];
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Lizenz-Validierung fehlgeschlagen: " . $e->getMessage());
            }
            return $this->handleOfflineMode($licenseKey, $hardwareId);
        }
    }

    /**
     * Offline-Modus Handling
     */
    private function handleOfflineMode($licenseKey, $hardwareId)
    {
        $cached = $this->getCachedLicense($licenseKey, $hardwareId);

        if ($cached) {
            $lastValidation = strtotime($cached['last_validation']);
            $gracePeriod = $this->config['offline_grace_period'] * 86400;

            if ((time() - $lastValidation) < $gracePeriod) {
                return [
                    'valid' => true,
                    'offline' => true,
                    'cached' => true,
                    'data' => json_decode($cached['validation_data'], true),
                    'features' => json_decode($cached['features'], true) ?? [],
                    'message' => 'Offline-Modus (Lizenz-Cache gültig)'
                ];
            }
        }

        return [
            'valid' => false,
            'offline' => true,
            'error' => 'Lizenzserver nicht erreichbar und kein gültiger Cache vorhanden'
        ];
    }

    /**
     * Lizenz cachen - Simplified ohne Transaktionen
     */
    private function cacheLicense($licenseKey, $hardwareId, $validationResult)
    {
        if (!$this->pdo) return;

        // Skip caching if database might be locked
        try {
            // Test if database is accessible with a simple query
            $test = $this->pdo->query("SELECT 1");
            if (!$test) return; // Database not accessible
        } catch (Exception $e) {
            // Database locked or other issue - skip caching silently
            return;
        }

        try {
            // Lösche alten Cache-Eintrag falls vorhanden
            $stmt = $this->pdo->prepare("DELETE FROM license_cache WHERE license_key = ?");
            $stmt->execute([$licenseKey]);

            // Füge neuen Cache-Eintrag ein
            $stmt = $this->pdo->prepare("
            INSERT INTO license_cache 
            (license_key, hardware_id, validation_data, last_validation, expires_at, is_valid, features, updated_at)
            VALUES (?, ?, ?, datetime('now'), ?, ?, ?, datetime('now'))
        ");

            $expiresAt = isset($validationResult['expires_at'])
                ? date('Y-m-d H:i:s', $validationResult['expires_at'] / 1000)
                : null;

            $stmt->execute([
                $licenseKey,
                $hardwareId,
                json_encode($validationResult['data'] ?? []),
                $expiresAt,
                $validationResult['valid'] ? 1 : 0,
                json_encode($validationResult['features'] ?? [])
            ]);
        } catch (PDOException $e) {
            // Caching failed - log but don't throw
            if ($this->logger) {
                $this->logger->error("Cache-Update fehlgeschlagen: " . $e->getMessage());
            }
            // Continue without cache - no fatal error
        }
    }

    /**
     * Gecachte Lizenz abrufen
     */
    private function getCachedLicense($licenseKey, $hardwareId)
    {
        if (!$this->pdo) return null;

        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM license_cache 
                WHERE license_key = ? AND hardware_id = ? AND is_valid = 1
                LIMIT 1
            ");
            $stmt->execute([$licenseKey, $hardwareId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Prüft ob der Cache noch gültig ist
     */
    private function isCacheValid($cached)
    {
        if (!$cached) return false;

        // Cache ist NICHT gültig wenn Lizenz als ungültig markiert ist
        if ($cached['is_valid'] != 1) {
            return false;
        }

        $lastValidation = strtotime($cached['last_validation']);
        $cacheAge = time() - $lastValidation;

        // Cache nur für kurze Zeit gültig (5 Minuten statt 24h)
        // So werden Deaktivierungen schnell erkannt
        $maxCacheAge = min($this->config['cache_duration'], 300); // Max 5 Minuten

        return $cacheAge < $maxCacheAge;
    }

    /**
     * Speichert die Lizenz in der Session
     */
    public function storeLicenseInSession($licenseKey, $validationResult)
    {
        $_SESSION['license'] = [
            'key' => $licenseKey,
            'valid' => $validationResult['valid'],
            'features' => $validationResult['features'] ?? [],
            'expires_at' => $validationResult['expires_at'] ?? null,
            'validated_at' => time(),
            'hardware_id' => $this->generateHardwareId()
        ];
    }

    /**
     * Holt Lizenz-Info aus der Session
     */
    public function getLicenseFromSession()
    {
        return $_SESSION['license'] ?? null;
    }

    /**
     * Prüft ob ein Feature verfügbar ist
     */
    public function hasFeature($feature)
    {
        if (!$this->config['enabled']) {
            return true; // Alle Features verfügbar wenn Lizenzierung deaktiviert
        }

        // Prüfe ob es ein freies Feature ist
        if (isset($this->config['free_features'][$feature])) {
            return true;
        }

        // Prüfe lizenzierte Features
        $license = $this->getLicenseFromSession();
        if (!$license || !$license['valid']) {
            return false;
        }

        return in_array($feature, $license['features'] ?? []);
    }

    /**
     * Deaktiviert eine Lizenz
     */
    public function deactivateLicense($licenseKey)
    {
        $url = $this->config['api_url'] . '/deactivate.php';
        $hardwareId = $this->generateHardwareId();

        $data = [
            'license_key' => $licenseKey,
            'hardware_id' => $hardwareId
        ];

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($data),
                'timeout' => 10
            ]
        ];

        $context = stream_context_create($options);

        try {
            $response = @file_get_contents($url, false, $context);

            // Lösche Cache
            if ($this->pdo) {
                $stmt = $this->pdo->prepare("DELETE FROM license_cache WHERE license_key = ?");
                $stmt->execute([$licenseKey]);
            }

            // Lösche aus Session
            unset($_SESSION['license']);

            return json_decode($response, true);
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
