<?php

/**
 * Authentication Class für Cashbook System
 * Mit WIRKLICH STRIKTER Lizenzprüfung und Logging
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']),
        'samesite' => 'Strict'
    ]);

    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/logger.class.php';
require_once __DIR__ . '/init_logger.php';

// Lade License Helper nur wenn vorhanden
if (file_exists(__DIR__ . '/license.class.php')) {
    require_once __DIR__ . '/license.class.php';
}

class Auth
{
    private $db;
    private $pdo;
    private $logger;
    private $license = null;
    private $sessionTimeout = 3600;
    private $licensingEnabled = true;
    private $licenseCheckInterval = 60; // Prüfe jede Minute!

    public function __construct()
    {
        $this->db = new Database();
        $this->pdo = $this->db->getConnection();
        $this->logger = new Logger($this->pdo);

        // License Helper initialisieren
        if (class_exists('LicenseHelper')) {
            $this->license = new LicenseHelper($this->pdo, $this->logger);
        }

        // Prüfe ob Lizenz-Config existiert
        if (file_exists(__DIR__ . '/../config/license.php')) {
            $config = require __DIR__ . '/../config/license.php';
            $this->licensingEnabled = $config['enabled'] ?? true;
        }

        $this->initializeSession();

        // Debug-Logging aktivieren
        $this->debugLog("Auth initialized - Licensing: " . ($this->licensingEnabled ? 'ON' : 'OFF'));
    }

    /**
     * Debug-Logging für Konsole
     */
    private function debugLog($message, $data = null)
    {
        // Logging in Session für spätere Ausgabe
        if (!isset($_SESSION['debug_logs'])) {
            $_SESSION['debug_logs'] = [];
        }

        $timestamp = date('H:i:s');
        $logEntry = "[{$timestamp}] AUTH: {$message}";

        if ($data !== null) {
            $logEntry .= " | Data: " . json_encode($data);
        }

        $_SESSION['debug_logs'][] = $logEntry;

        // Auch in DB-Logger
        $this->logger->info($message, $_SESSION['user_id'] ?? null, 'AUTH_DEBUG', $data);

        // Für Konsole vorbereiten (wird im Dashboard ausgegeben)
        $_SESSION['last_debug_log'] = $logEntry;
    }

    /**
     * Session Security initialisieren
     */
    private function initializeSession()
    {
        if (!isset($_SESSION['initialized'])) {
            session_regenerate_id(true);
            $_SESSION['initialized'] = true;
        }
    }

    /**
     * Hole GLOBALEN Lizenzschlüssel
     */
    private function getGlobalLicenseKey()
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT license_key 
                FROM users 
                WHERE license_key IS NOT NULL AND license_key != ''
                ORDER BY 
                    CASE WHEN role = 'admin' THEN 0 ELSE 1 END,
                    id ASC
                LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->fetch();

            $key = $result ? $result['license_key'] : null;
            $this->debugLog("Global license key lookup", ['found' => $key !== null]);

            return $key;
        } catch (Exception $e) {
            $this->debugLog("Error getting global license", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * User Login mit ABSOLUT STRIKTER Lizenzprüfung
     */
    public function login($username, $password, $licenseKey = null)
    {
        $this->debugLog("Login attempt", ['username' => $username]);

        // Validate input
        if (empty($username) || empty($password)) {
            $this->logger->warning("Login attempt with empty credentials");
            return ['success' => false, 'message' => 'Bitte alle Felder ausfüllen'];
        }

        // Get user from database
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        // Check password
        $password_field = isset($user['password_hash']) ? $user['password_hash'] : ($user['password'] ?? '');

        if (!$user || !password_verify($password, $password_field)) {
            $this->debugLog("Login failed - invalid credentials");
            $this->logger->warning("Failed login attempt", null, 'AUTH', ['username' => $username]);
            return ['success' => false, 'message' => 'Ungültige Anmeldedaten'];
        }

        $this->debugLog("Credentials valid, checking license...");

        // ABSOLUT STRIKTE LIZENZPRÜFUNG
        if ($this->licensingEnabled && $this->license !== null) {

            // Wenn neue Lizenz mitgegeben und User ist Admin
            if (!empty($licenseKey) && $user['role'] === 'admin') {
                $this->debugLog("Admin providing new license key");
                $this->updateUserLicense($user['id'], $licenseKey);

                // WICHTIG: Cache löschen!
                $this->clearLicenseCache();
            }

            // Hole GLOBALE Lizenz
            $globalLicenseKey = $this->getGlobalLicenseKey();

            if (!$globalLicenseKey) {
                $this->debugLog("NO GLOBAL LICENSE FOUND - blocking login");

                $_SESSION['pending_user_id'] = $user['id'];
                $_SESSION['pending_username'] = $user['username'];
                $_SESSION['is_admin'] = ($user['role'] === 'admin');

                return [
                    'success' => false,
                    'require_license' => true,
                    'user_id' => $user['id'],
                    'message' => 'Keine Systemlizenz vorhanden.',
                    'license_error' => true
                ];
            }

            $this->debugLog("Validating license online", ['key' => substr($globalLicenseKey, 0, 7) . '...']);

            // IMMER ONLINE VALIDIEREN BEIM LOGIN - KEIN CACHE!
            // Lösche Cache vor Validierung
            $this->clearLicenseCache();

            // Force Online Check
            $validation = $this->license->validateLicense($globalLicenseKey, true); // true = FORCE ONLINE

            $this->debugLog("License validation result", [
                'valid' => $validation['valid'],
                'error' => $validation['error'] ?? null,
                'cached' => $validation['cached'] ?? false
            ]);

            if (!$validation['valid']) {
                $this->debugLog("LICENSE INVALID - blocking login");

                $_SESSION['pending_user_id'] = $user['id'];
                $_SESSION['pending_username'] = $user['username'];
                $_SESSION['is_admin'] = ($user['role'] === 'admin');

                return [
                    'success' => false,
                    'require_license' => true,
                    'user_id' => $user['id'],
                    'message' => 'Systemlizenz ungültig: ' . ($validation['error'] ?? 'Lizenz abgelaufen oder deaktiviert'),
                    'license_error' => true
                ];
            }

            $this->debugLog("License valid - storing in session");

            // Speichere Lizenz-Info
            $this->license->storeLicenseInSession($globalLicenseKey, $validation);
            $_SESSION['global_license_key'] = $globalLicenseKey;
            $_SESSION['last_license_check'] = time();
        }

        // Login erfolgreich
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'] ?? 'user';
        $_SESSION['last_activity'] = time();
        $_SESSION['just_logged_in'] = true;

        // Entferne pending flags
        unset($_SESSION['pending_user_id']);
        unset($_SESSION['pending_username']);
        unset($_SESSION['is_admin']);

        // Update last login
        $stmt = $this->pdo->prepare("UPDATE users SET last_login = datetime('now') WHERE id = :id");
        $stmt->execute([':id' => $user['id']]);

        // Session tracking
        $sessionId = session_id();
        $stmt = $this->pdo->prepare("
            INSERT INTO sessions (session_id, user_id, ip_address, user_agent) 
            VALUES (:session_id, :user_id, :ip, :agent)
        ");
        $stmt->execute([
            ':session_id' => $sessionId,
            ':user_id' => $user['id'],
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        $this->debugLog("Login successful for user: " . $user['username']);
        $this->logger->info("User logged in successfully", $user['id'], 'AUTH');

        return [
            'success' => true,
            'message' => 'Anmeldung erfolgreich',
            'user' => $user
        ];
    }

    /**
     * Cache komplett löschen
     */
    private function clearLicenseCache()
    {
        if ($this->license && $this->pdo) {
            try {
                // Lösche ALLEN Cache
                $stmt = $this->pdo->prepare("DELETE FROM license_cache");
                $stmt->execute();

                // Lösche auch Session-Cache
                unset($_SESSION['license']);
                unset($_SESSION['last_license_check']);

                $this->debugLog("License cache cleared completely");
            } catch (Exception $e) {
                $this->debugLog("Error clearing cache", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Aktualisiere Lizenzschlüssel
     */
    public function updateUserLicense($userId, $licenseKey)
    {
        try {
            // Prüfe ob license_key Spalte existiert
            $stmt = $this->pdo->query("PRAGMA table_info(users)");
            $columns = $stmt->fetchAll();
            $hasLicenseKey = false;

            foreach ($columns as $column) {
                if ($column['name'] === 'license_key') {
                    $hasLicenseKey = true;
                    break;
                }
            }

            if (!$hasLicenseKey) {
                $this->pdo->exec("ALTER TABLE users ADD COLUMN license_key TEXT");
            }

            // Update Lizenzschlüssel
            $stmt = $this->pdo->prepare("UPDATE users SET license_key = :key WHERE id = :id");
            $stmt->execute([':key' => $licenseKey, ':id' => $userId]);

            // WICHTIG: Cache löschen!
            $this->clearLicenseCache();

            $this->debugLog("License key updated for user", ['user_id' => $userId]);

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to update license key: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user is logged in mit STRIKTER Lizenzprüfung
     */
    public function isLoggedIn()
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            $inactive = time() - $_SESSION['last_activity'];
            if ($inactive > $this->sessionTimeout) {
                $this->debugLog("Session timeout - logging out");
                $this->logout();
                $_SESSION['error'] = 'Sitzung abgelaufen.';
                return false;
            }
        }

        // NUR DIESE ZEILE GEÄNDERT: 1800 statt 60 Sekunden (30 Minuten statt 1 Minute)
        if (!isset($_SESSION['last_license_check']) || (time() - $_SESSION['last_license_check']) > 1800) {
            $now = time();
            $this->debugLog("License check needed (30 min interval)");

            $globalLicenseKey = $_SESSION['global_license_key'] ?? null;

            if (!$globalLicenseKey || !$this->license) {
                $this->debugLog("No license key or system - logout");
                $this->logout();
                $_SESSION['error'] = 'Keine Systemlizenz vorhanden.';
                return false;
            }

            // GLEICHE LOGIK WIE VORHER: Cache löschen und online prüfen
            $this->clearLicenseCache();
            $validation = $this->license->validateLicense($globalLicenseKey, true); // FORCE ONLINE

            $this->debugLog("License revalidation result", [
                'valid' => $validation['valid'],
                'error' => $validation['error'] ?? null
            ]);

            if (!$validation['valid']) {
                $this->debugLog("LICENSE INVALID - FORCING LOGOUT!");
                $this->logout();
                $_SESSION['error'] = 'Systemlizenz ungültig oder abgelaufen: ' . ($validation['error'] ?? '');
                return false;
            }

            // Update Session
            $this->license->storeLicenseInSession($globalLicenseKey, $validation);
            $_SESSION['last_license_check'] = $now;

            $this->debugLog("License check passed - session continues");
        }

        // Update last activity
        $_SESSION['last_activity'] = time();
        $this->updateSessionActivity();

        return true;
    }

    /**
     * Update session activity in database
     */
    private function updateSessionActivity()
    {
        $sessionId = session_id();
        $stmt = $this->pdo->prepare("
            UPDATE sessions 
            SET last_activity = datetime('now') 
            WHERE session_id = :session_id
        ");
        $stmt->execute([':session_id' => $sessionId]);
    }

    /**
     * Validiere eine Lizenz ohne Login
     */
    public function validateLicenseKey($licenseKey, $userId = null)
    {
        if (!$this->license) {
            return ['valid' => false, 'error' => 'Lizenzsystem nicht verfügbar'];
        }

        // IMMER online validieren
        $this->clearLicenseCache();
        $validation = $this->license->validateLicense($licenseKey, true);

        if ($validation['valid'] && $userId) {
            $this->updateUserLicense($userId, $licenseKey);
        }

        return $validation;
    }

    /**
     * Logout
     */
    public function logout()
    {
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId) {
            $sessionId = session_id();
            $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE session_id = :session_id");
            $stmt->execute([':session_id' => $sessionId]);

            $this->debugLog("User logged out", ['user_id' => $userId]);
            $this->logger->info("User logged out", $userId, 'AUTH');
        }

        // Clear session
        $_SESSION = [];
        session_destroy();

        // Start new session for messages
        session_start();
    }

    /**
     * Require login
     */
    public function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: /index.php');
            exit;
        }
    }

    /**
     * Get current user
     */
    public function getCurrentUser()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user && $this->license !== null) {
            $user['license'] = $this->license->getLicenseFromSession();
        }

        return $user;
    }

    /**
     * Check if user has feature access
     */
    public function hasFeature($feature)
    {
        if (!$this->licensingEnabled || $this->license === null) {
            return true;
        }
        return $this->license->hasFeature($feature);
    }

    /**
     * Register new user
     */
    public function register($data)
    {
        // Validate input
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            return ['success' => false, 'message' => 'Bitte alle Felder ausfüllen'];
        }

        // Check if username exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->execute([':username' => $data['username']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Benutzername bereits vergeben'];
        }

        // Check if email exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $data['email']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'E-Mail bereits registriert'];
        }

        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password, full_name, role, is_active, starting_balance) 
                VALUES (:username, :email, :password, :full_name, :role, :is_active, :balance)
            ");

            $stmt->execute([
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':password' => $passwordHash,
                ':full_name' => $data['full_name'] ?? $data['username'],
                ':role' => $data['role'] ?? 'user',
                ':is_active' => 1,
                ':balance' => $data['starting_balance'] ?? 0
            ]);

            $this->logger->info("New user registered", $this->pdo->lastInsertId(), 'AUTH', ['username' => $data['username']]);

            // Prüfe ob globale Lizenz vorhanden
            $globalLicense = $this->getGlobalLicenseKey();

            if (!$globalLicense) {
                $_SESSION['pending_user_id'] = $this->pdo->lastInsertId();
                $_SESSION['pending_username'] = $data['username'];

                return [
                    'success' => true,
                    'message' => 'Registrierung erfolgreich! Bitte aktivieren Sie eine Systemlizenz.',
                    'require_license' => true
                ];
            }

            return [
                'success' => true,
                'message' => 'Registrierung erfolgreich! Sie können sich jetzt anmelden.'
            ];
        } catch (Exception $e) {
            $this->logger->error("Registration failed: " . $e->getMessage(), null, 'AUTH');
            return ['success' => false, 'message' => 'Registrierung fehlgeschlagen'];
        }
    }

    /**
     * Get License Helper Instance
     */
    public function getLicenseHelper()
    {
        return $this->license;
    }

    /**
     * Check if licensing is enabled
     */
    public function isLicensingEnabled()
    {
        return $this->licensingEnabled && $this->license !== null;
    }

    /**
     * Check if current user is admin
     */
    public function isAdmin()
    {
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }

    /**
     * Get debug logs for output
     */
    public function getDebugLogs()
    {
        return $_SESSION['debug_logs'] ?? [];
    }
}

// WICHTIG: Auth-Instanz global verfügbar machen
$auth = new Auth();
