<?php

/**
 * Logger Class für Cashbook System
 * Protokolliert Systemereignisse in der Datenbank
 */

class Logger
{
    private $pdo;

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
     * Log message
     */
    private function log($level, $message, $userId = null, $category = null, $additionalData = null)
    {
        try {
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

            return true;
        } catch (Exception $e) {
            // Fallback to file logging if database fails
            $this->logToFile($level, $message . ' (DB Error: ' . $e->getMessage() . ')');
            return false;
        }
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
     * Fallback file logging
     */
    private function logToFile($level, $message)
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logFile = $logDir . '/system_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get recent logs
     */
    public function getRecentLogs($limit = 100, $level = null, $category = null)
    {
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

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Clean old logs
     */
    public function cleanOldLogs($days = 30)
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $stmt = $this->pdo->prepare("
            DELETE FROM system_logs 
            WHERE timestamp < :cutoff
        ");

        return $stmt->execute([':cutoff' => $cutoffDate]);
    }
}
