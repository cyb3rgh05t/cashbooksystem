<?php

/**
 * Logger Class für Cashbook System
 * Protokolliert Systemereignisse in Datenbank UND Dateien
 */

class Logger
{
    private $pdo;
    private $logToFileEnabled = true; // Aktiviert File-Logging zusätzlich zur DB

    public function __construct($pdo = null)
    {
        if ($pdo === null) {
            require_once __DIR__ . '/../config/database.php';
            $db = new Database();
            $this->pdo = $db->getConnection();
        } else {
            $this->pdo = $pdo;
        }
    }

    /**
     * Log message - Schreibt in DB UND Datei
     */
    private function log($level, $message, $userId = null, $category = null, $additionalData = null)
    {
        $success = false;

        // 1. Versuche in Datenbank zu schreiben
        try {
            if ($this->pdo !== null) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO system_logs (level, message, user_id, category, ip_address, user_agent, additional_data) 
                    VALUES (:level, :message, :user_id, :category, :ip, :agent, :data)
                ");

                $stmt->execute([
                    ':level' => $level,
                    ':message' => $message,
                    ':user_id' => $userId,
                    ':category' => $category,
                    ':ip' => $this->getClientIP(),
                    ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                    ':data' => $additionalData ? json_encode($additionalData) : null
                ]);

                $success = true;
            }
        } catch (Exception $e) {
            // Bei DB-Fehler füge Fehler zur Nachricht hinzu
            $message .= ' (DB Error: ' . $e->getMessage() . ')';
        }

        // 2. IMMER auch in Datei schreiben (nicht nur als Fallback!)
        if ($this->logToFileEnabled) {
            $this->logToFile($level, $message, $userId, $category);
        }

        return $success;
    }

    /**
     * File logging - Verbesserte Version mit garantierter Ausführung
     */
    private function logToFile($level, $message, $userId = null, $category = null)
    {
        // Versuche verschiedene Verzeichnisse
        $possibleDirs = [
            __DIR__ . '/../logs',                    // Hauptverzeichnis
            __DIR__ . '/../database/logs',           // Alternative
            sys_get_temp_dir() . '/cashbook_logs',   // System Temp als Fallback
        ];

        $logDir = null;
        $logFile = null;

        // Finde oder erstelle ein funktionierendes Log-Verzeichnis
        foreach ($possibleDirs as $dir) {
            if (is_dir($dir) && is_writable($dir)) {
                $logDir = $dir;
                break;
            } elseif (!is_dir($dir)) {
                // Versuche Verzeichnis zu erstellen
                if (@mkdir($dir, 0755, true)) {
                    $logDir = $dir;
                    break;
                }
            }
        }

        // Wenn kein Verzeichnis funktioniert, verwende PHP error_log
        if (!$logDir) {
            error_log("CASHBOOK [{$level}] [{$category}] User:{$userId} - {$message}");
            return false;
        }

        // Erstelle Log-Dateiname mit Datum
        $logFile = $logDir . DIRECTORY_SEPARATOR . 'system_' . date('Y-m-d') . '.log';

        // Formatiere Log-Eintrag
        $timestamp = date('Y-m-d H:i:s');
        $userInfo = $userId ? "User:$userId" : "Guest";
        $catInfo = $category ? "[$category]" : "";
        $logEntry = "[{$timestamp}] [{$level}] {$catInfo} {$userInfo} - {$message}" . PHP_EOL;

        // Schreibe in Log-Datei
        $result = @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Wenn auch das fehlschlägt, nutze error_log als letzten Ausweg
        if ($result === false) {
            error_log("CASHBOOK LOG WRITE FAILED [{$level}]: {$message}");
            return false;
        }

        return true;
    }

    /**
     * Log levels
     */
    public function info($message, $userId = null, $category = null, $data = null)
    {
        return $this->log('INFO', $message, $userId, $category, $data);
    }

    public function success($message, $userId = null, $category = null, $data = null)
    {
        return $this->log('SUCCESS', $message, $userId, $category, $data);
    }

    public function warning($message, $userId = null, $category = null, $data = null)
    {
        return $this->log('WARNING', $message, $userId, $category, $data);
    }

    public function error($message, $userId = null, $category = null, $data = null)
    {
        return $this->log('ERROR', $message, $userId, $category, $data);
    }

    public function critical($message, $userId = null, $category = null, $data = null)
    {
        return $this->log('CRITICAL', $message, $userId, $category, $data);
    }

    /**
     * Get client IP
     */
    private function getClientIP()
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get recent logs from database
     */
    public function getRecentLogs($limit = 100, $level = null, $category = null)
    {
        if ($this->pdo === null) {
            return [];
        }

        $sql = "SELECT * FROM system_logs WHERE 1=1";
        $params = [];

        if ($level) {
            $sql .= " AND level = :level";
            $params[':level'] = $level;
        }

        if ($category) {
            $sql .= " AND category = :category";
            $params[':category'] = $category;
        }

        $sql .= " ORDER BY timestamp DESC LIMIT :limit";

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get recent logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clean old logs from database
     */
    public function cleanOldLogs($days = 30)
    {
        if ($this->pdo === null) {
            return false;
        }

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM system_logs 
                WHERE timestamp < :cutoff
            ");
            return $stmt->execute([':cutoff' => $cutoffDate]);
        } catch (Exception $e) {
            error_log("Failed to clean old logs: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get log file path for today
     */
    public function getCurrentLogFile()
    {
        $possibleDirs = [
            __DIR__ . '/../logs',
            __DIR__ . '/../database/logs',
            sys_get_temp_dir() . '/cashbook_logs',
        ];

        foreach ($possibleDirs as $dir) {
            $logFile = $dir . DIRECTORY_SEPARATOR . 'system_' . date('Y-m-d') . '.log';
            if (file_exists($logFile)) {
                return $logFile;
            }
        }

        return null;
    }

    /**
     * Read log file content
     */
    public function readLogFile($date = null)
    {
        if ($date === null) {
            $date = date('Y-m-d');
        }

        $possibleDirs = [
            __DIR__ . '/../logs',
            __DIR__ . '/../database/logs',
            sys_get_temp_dir() . '/cashbook_logs',
        ];

        foreach ($possibleDirs as $dir) {
            $logFile = $dir . DIRECTORY_SEPARATOR . 'system_' . $date . '.log';
            if (file_exists($logFile)) {
                return file_get_contents($logFile);
            }
        }

        return null;
    }

    /**
     * Test logging functionality
     */
    public function testLogging()
    {
        // Test Datenbank-Logging
        $dbTest = false;
        try {
            if ($this->pdo !== null) {
                $stmt = $this->pdo->prepare("SELECT 1 FROM system_logs LIMIT 1");
                $stmt->execute();
                $dbTest = true;
            }
        } catch (Exception $e) {
            $dbTest = false;
        }

        // Test File-Logging durch tatsächliches Schreiben
        $fileTest = false;
        $logDirectory = null;

        // Schreibe einen Test-Log
        $testMessage = "Test log entry at " . date('Y-m-d H:i:s');
        $this->logToFile('TEST', $testMessage, null, 'TEST');

        // Prüfe ob die Log-Datei existiert
        $logFile = $this->getCurrentLogFile();
        if ($logFile !== null) {
            $fileTest = true;
            $logDirectory = dirname($logFile);
        }

        return [
            'database_logging' => $dbTest,
            'file_logging' => $fileTest,
            'log_directory' => $logDirectory ?? 'none',
            'log_file' => $logFile ?? 'none'
        ];
    }
}
