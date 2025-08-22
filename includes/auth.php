<?php

/**
 * Authentication Class für Cashbook System
 * Adaptiert vom Billing System
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Session Cookie Einstellungen VOR session_start()
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

class Auth
{
    private $db;
    private $pdo;
    private $logger;
    private $sessionTimeout = 3600; // 1 Stunde

    public function __construct()
    {
        // Cashbook verwendet Database class anders als Billing
        $this->db = new Database();
        $this->pdo = $this->db->getConnection();
        $this->logger = new Logger($this->pdo);

        // Session Security
        $this->initializeSession();
    }

    /**
     * Session Security initialisieren
     */
    private function initializeSession()
    {
        // Session ID regenerieren für Sicherheit (nur wenn noch nicht initialisiert)
        if (!isset($_SESSION['initialized'])) {
            session_regenerate_id(true);
            $_SESSION['initialized'] = true;
        }
    }

    /**
     * User Login
     */
    public function login($username, $password)
    {
        // Validate input
        if (empty($username) || empty($password)) {
            $this->logger->warning("Login attempt with empty credentials");
            return ['success' => false, 'message' => 'Bitte alle Felder ausfüllen'];
        }

        // Get user from database
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        // Check password (unterstützt beide Feldnamen für Migration)
        $password_field = isset($user['password_hash']) ? $user['password_hash'] : ($user['password'] ?? null);
        if ($user && $password_field && password_verify($password, $password_field)) {
            // Successful login
            $this->createSession($user);

            // Log successful login
            $this->logger->success(
                "User '{$username}' logged in successfully",
                $user['id'],
                'AUTH'
            );

            return ['success' => true, 'message' => 'Login erfolgreich'];
        }

        // Log failed login
        $this->logger->error(
            "Failed login attempt for user '{$username}'",
            null,
            'AUTH'
        );

        return ['success' => false, 'message' => 'Ungültiger Benutzername oder Passwort'];
    }

    /**
     * Create user session
     */
    private function createSession($user)
    {
        // Regenerate session ID for security
        session_regenerate_id(true);

        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        // Create session entry in database
        $sessionId = session_id();
        $stmt = $this->pdo->prepare("
            INSERT INTO sessions (session_id, user_id, ip_address, user_agent) 
            VALUES (:session_id, :user_id, :ip, :agent)
        ");
        $stmt->execute([
            ':session_id' => $sessionId,
            ':user_id' => $user['id'],
            ':ip' => $this->getClientIP(),
            ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }

    /**
     * User Logout
     */
    public function logout()
    {
        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'Unknown';

        // Delete session from database
        if ($userId) {
            $sessionId = session_id();
            $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE session_id = :session_id");
            $stmt->execute([':session_id' => $sessionId]);

            // Log logout
            $this->logger->info(
                "User '{$username}' logged out",
                $userId,
                'AUTH'
            );
        }

        // Clear session
        $_SESSION = [];

        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // Destroy session
        session_destroy();

        // Start new session for messages
        session_start();
        session_regenerate_id(true);
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn()
    {
        // Check session
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
            return false;
        }

        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            $inactive = time() - $_SESSION['last_activity'];
            if ($inactive > $this->sessionTimeout) {
                $this->logout();
                $_SESSION['error'] = 'Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.';
                return false;
            }
        }

        // Update last activity
        $_SESSION['last_activity'] = time();

        // Update database session
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
     * Require login
     */
    public function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            // Save requested URL
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
        return $stmt->fetch();
    }

    /**
     * Register new user (für Cashbook System angepasst)
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

        // Insert user (mit erweiterten Feldern)
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

            $userId = $this->pdo->lastInsertId();

            // Log registration
            $this->logger->success(
                "New user registered: {$data['username']}",
                $userId,
                'AUTH'
            );

            return ['success' => true, 'message' => 'Registrierung erfolgreich'];
        } catch (Exception $e) {
            $this->logger->error(
                "Registration failed: " . $e->getMessage(),
                null,
                'AUTH'
            );
            return ['success' => false, 'message' => 'Registrierung fehlgeschlagen'];
        }
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
     * Clean up old sessions
     */
    public function cleanupSessions()
    {
        $expiredTime = date('Y-m-d H:i:s', time() - $this->sessionTimeout);

        $stmt = $this->pdo->prepare("
            DELETE FROM sessions 
            WHERE last_activity < :expired
        ");
        $deleted = $stmt->execute([':expired' => $expiredTime]);

        if ($deleted) {
            $this->logger->info(
                "Cleaned up expired sessions",
                null,
                'AUTH'
            );
        }
    }
}

// Create global auth instance
$auth = new Auth();
