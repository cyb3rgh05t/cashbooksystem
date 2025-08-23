<?php

declare(strict_types=1);

class Database
{
    /** @var string */
    private $db_file;

    /** @var ?PDO */
    private $connection = null;

    /**
     * @param string|null $dbPath Optional custom absolute/relative path to the SQLite file.
     */
    public function __construct(?string $dbPath = null)
    {
        // Default location: <project-root>/database/finance_tracker.db
        $this->db_file = $dbPath ?? (__DIR__ . '/../database/finance_tracker.db');
        $this->initializeDatabase();
    }

    /**
     * Returns a shared PDO connection (singleton-like per instance).
     *
     * @return PDO
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            try {
                // Ensure directory exists
                $dbDir = dirname($this->db_file);
                if (!is_dir($dbDir)) {
                    if (!mkdir($dbDir, 0775, true) && !is_dir($dbDir)) {
                        throw new RuntimeException('Failed to create database directory: ' . $dbDir);
                    }
                }

                $this->connection = new PDO('sqlite:' . $this->db_file);
                $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                // Important for SQLite: enforce foreign keys
                $this->connection->exec('PRAGMA foreign_keys = ON');
            } catch (PDOException $e) {
                // Fail fast with a clear message in development; adapt to logging for production
                die('Database connection failed: ' . $e->getMessage());
            }
        }

        return $this->connection;
    }

    /**
     * Creates schema if missing and performs one-time setup.
     * Safe to call multiple times.
     */
    private function initializeDatabase(): void
    {
        $pdo = $this->getConnection();

        // Create tables if they don't exist yet
        $schemaSql = <<<SQL

            -- Users Tabelle (mit erweiterten Feldern für Auth-System)
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                full_name TEXT,
                role TEXT DEFAULT 'user' CHECK(role IN ('admin', 'user', 'viewer')),
                is_active INTEGER DEFAULT 1,
                last_login DATETIME,
                starting_balance REAL DEFAULT 0.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                timezone TEXT DEFAULT 'Europe/Berlin',
                timezone_manual INTEGER DEFAULT 0
            );

            -- Sessions Tabelle für Login-Tracking
            CREATE TABLE IF NOT EXISTS sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT UNIQUE NOT NULL,
                user_id INTEGER NOT NULL,
                ip_address TEXT,
                user_agent TEXT,
                last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            -- System Logs Tabelle für Audit-Trail
            CREATE TABLE IF NOT EXISTS system_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                level TEXT NOT NULL CHECK(level IN ('INFO', 'SUCCESS', 'WARNING', 'ERROR', 'CRITICAL')),
                category TEXT,
                message TEXT NOT NULL,
                user_id INTEGER,
                ip_address TEXT,
                user_agent TEXT,
                additional_data TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                type TEXT NOT NULL CHECK (type IN ('income','expense','debt_in','debt_out')),
                color TEXT,
                icon TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                category_id INTEGER,
                amount REAL NOT NULL,
                note TEXT,
                date TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                recurring_transaction_id INTEGER DEFAULT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
                FOREIGN KEY (recurring_transaction_id) REFERENCES recurring_transactions(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS recurring_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                amount REAL NOT NULL,
                note TEXT,
                frequency TEXT NOT NULL CHECK (frequency IN ('daily','weekly','monthly','yearly')),
                start_date TEXT NOT NULL,
                end_date TEXT,
                next_due_date TEXT NOT NULL,
                is_active INTEGER DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS investments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                symbol TEXT NOT NULL,
                name TEXT NOT NULL,
                amount REAL NOT NULL,
                purchase_price REAL NOT NULL,
                purchase_date TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            -- Index für bessere Performance
            CREATE INDEX IF NOT EXISTS idx_investments_user_id ON investments(user_id);
            CREATE INDEX IF NOT EXISTS idx_investments_symbol ON investments(symbol);
            CREATE INDEX IF NOT EXISTS idx_transactions_user_id ON transactions(user_id);
            CREATE INDEX IF NOT EXISTS idx_transactions_date ON transactions(date);
            CREATE INDEX IF NOT EXISTS idx_categories_type ON categories(type);
            CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);
            CREATE INDEX IF NOT EXISTS idx_sessions_session ON sessions(session_id);
            CREATE INDEX IF NOT EXISTS idx_logs_timestamp ON system_logs(timestamp);
            CREATE INDEX IF NOT EXISTS idx_logs_user ON system_logs(user_id);
            CREATE INDEX IF NOT EXISTS idx_logs_level ON system_logs(level);
        SQL;

        $pdo->beginTransaction();
        try {
            $pdo->exec($schemaSql);
            $this->migrateExistingUsers();
            $this->migrateRecurringTransactions();
            $this->insertDefaultData(); // NEU: Default-User erstellen
            $pdo->commit();
        } catch (Throwable $t) {
            $pdo->rollBack();
            die('Database schema initialization failed: ' . $t->getMessage());
        }
    }

    /**
     * Default-Daten einfügen (Admin und Demo User)
     * NUR wenn noch KEIN Admin existiert
     */
    private function insertDefaultData(): void
    {
        $pdo = $this->getConnection();

        // Prüfe ob IRGENDEIN Admin User existiert (nicht nur der Demo-Admin)
        $stmt = $pdo->prepare("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin' AND is_active = 1");
        $stmt->execute();
        $result = $stmt->fetch();
        $adminExists = ($result && $result['admin_count'] > 0);

        if (!$adminExists) {
            try {
                // Admin User erstellen (Passwort: admin123)
                $stmt = $pdo->prepare("
                INSERT INTO users (username, password, email, full_name, role, is_active, starting_balance) 
                VALUES (:username, :password, :email, :full_name, :role, :is_active, :balance)
            ");

                $stmt->execute([
                    ':username' => 'admin',
                    ':password' => password_hash('admin123', PASSWORD_DEFAULT),
                    ':email' => 'admin@cashbook.local',
                    ':full_name' => 'System Administrator',
                    ':role' => 'admin',
                    ':is_active' => 1,
                    ':balance' => 0.00
                ]);

                $admin_id = (int)$pdo->lastInsertId();

                // Demo User erstellen (Passwort: demo123)
                $stmt->execute([
                    ':username' => 'demo',
                    ':password' => password_hash('demo123', PASSWORD_DEFAULT),
                    ':email' => 'demo@cashbook.local',
                    ':full_name' => 'Demo User',
                    ':role' => 'user',
                    ':is_active' => 1,
                    ':balance' => 0.00
                ]);

                $demo_id = (int)$pdo->lastInsertId();

                // Standard-Kategorien direkt hier erstellen (ohne eigene Transaktion)
                $this->insertDefaultCategories($admin_id);

                error_log("Default users created: admin (ID: $admin_id) and demo (ID: $demo_id)");
            } catch (PDOException $e) {
                error_log("Could not create default users: " . $e->getMessage());
            }
        } else {
            error_log("Admin user already exists, skipping default user creation");
        }
    }

    /**
     * Default-Kategorien einfügen (ohne eigene Transaktion)
     */
    private function insertDefaultCategories(int $user_id): void
    {
        $pdo = $this->getConnection();

        // Prüfe ob bereits Kategorien existieren
        $check = $pdo->prepare('SELECT COUNT(*) AS cnt FROM categories');
        $check->execute();
        $row = $check->fetch();
        if ($row && (int)$row['cnt'] > 0) {
            return;
        }

        $default_categories = [
            // Income categories
            ['name' => 'Gehalt',      'type' => 'income',  'color' => '#4ade80', 'icon' => '<i class="fa-solid fa-wallet"></i>'],
            ['name' => 'Freelance',   'type' => 'income',  'color' => '#22c55e', 'icon' => '<i class="fa-brands fa-codepen"></i>'],
            ['name' => 'Bonus',       'type' => 'income',  'color' => '#10b981', 'icon' => '<i class="fa-solid fa-skull"></i>'],

            // Expense categories
            ['name' => 'Lebensmittel', 'type' => 'expense', 'color' => '#f97316', 'icon' => '<i class="fa-solid fa-bowl-food"></i>'],
            ['name' => 'Miete',       'type' => 'expense', 'color' => '#9333ea', 'icon' => '<i class="fa-solid fa-money-bill-transfer"></i>'],
            ['name' => 'Transport',   'type' => 'expense', 'color' => '#78716c', 'icon' => '<i class="fa-solid fa-car"></i>'],
            ['name' => 'Freizeit',    'type' => 'expense', 'color' => '#ec4899', 'icon' => '<i class="fa-brands fa-free-code-camp"></i>'],
            ['name' => 'Gesundheit',  'type' => 'expense', 'color' => '#ef4444', 'icon' => '<i class="fa-solid fa-notes-medical"></i>'],

            // Debt categories
            ['name' => 'Firma → Privat', 'type' => 'debt_out', 'color' => '#fbbf24', 'icon' => '<i class="fa-solid fa-money-bill-wave"></i>'],
            ['name' => 'Privat → Firma', 'type' => 'debt_in',  'color' => '#22c55e', 'icon' => '<i class="fa-solid fa-sack-dollar"></i>'],
            ['name' => 'Darlehen vergeben', 'type' => 'debt_out', 'color' => '#f97316', 'icon' => '<i class="fa-solid fa-handshake"></i>'],
            ['name' => 'Darlehen erhalten', 'type' => 'debt_in',  'color' => '#3b82f6', 'icon' => '<i class="fa-solid fa-wallet"></i>'],
        ];

        $stmt = $pdo->prepare('
            INSERT INTO categories (user_id, name, type, color, icon)
            VALUES (?, ?, ?, ?, ?)
        ');

        foreach ($default_categories as $c) {
            $stmt->execute([$user_id, $c['name'], $c['type'], $c['color'], $c['icon']]);
        }
    }

    /**
     * Migration für bestehende Benutzer - erweitert Felder
     */
    private function migrateExistingUsers(): void
    {
        $pdo = $this->getConnection();

        try {
            // Prüfe ob alle neuen Spalten existieren
            $stmt = $pdo->prepare("PRAGMA table_info(users)");
            $stmt->execute();
            $columns = $stmt->fetchAll();

            $columnNames = array_column($columns, 'name');

            // Füge fehlende Spalten hinzu
            if (!in_array('starting_balance', $columnNames)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN starting_balance REAL DEFAULT 0.00");
            }

            if (!in_array('full_name', $columnNames)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN full_name TEXT");
            }

            if (!in_array('role', $columnNames)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'user'");
            }

            if (!in_array('is_active', $columnNames)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN is_active INTEGER DEFAULT 1");
            }

            if (!in_array('last_login', $columnNames)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN last_login DATETIME");
            }

            if (!in_array('updated_at', $columnNames)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP");
            }

            // Migriere password_hash zu password wenn nötig
            if (in_array('password_hash', $columnNames) && !in_array('password', $columnNames)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN password TEXT");
                $pdo->exec("UPDATE users SET password = password_hash WHERE password IS NULL");
            }
        } catch (PDOException $e) {
            // Spalten existieren bereits oder anderer Fehler - das ist okay
            error_log("Migration info: " . $e->getMessage());
        }
    }

    /**
     * Migration für wiederkehrende Transaktionen
     */
    private function migrateRecurringTransactions(): void
    {
        $pdo = $this->getConnection();

        try {
            // Prüfe ob recurring_transaction_id Spalte bereits existiert
            $stmt = $pdo->prepare("PRAGMA table_info(transactions)");
            $stmt->execute();
            $columns = $stmt->fetchAll();

            $hasRecurringId = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'recurring_transaction_id') {
                    $hasRecurringId = true;
                    break;
                }
            }

            // Füge Spalte hinzu wenn sie nicht existiert
            if (!$hasRecurringId) {
                $pdo->exec("ALTER TABLE transactions ADD COLUMN recurring_transaction_id INTEGER DEFAULT NULL");
            }
        } catch (PDOException $e) {
            // Spalte existiert bereits oder anderer Fehler - das ist okay
            error_log("Recurring migration info: " . $e->getMessage());
        }
    }


    /**
     * Delete a user and all related data
     * 
     * @param int $user_id User ID to delete
     * @param int $admin_id ID of admin performing the deletion (for security check)
     * @return array Result array with success status and message
     */
    public function deleteUser(int $user_id, int $admin_id): array
    {
        $pdo = $this->getConnection();

        try {
            // Sicherheitschecks

            // 1. Prüfe ob der löschende User ein Admin ist
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$admin_id]);
            $adminUser = $stmt->fetch();

            if (!$adminUser || $adminUser['role'] !== 'admin') {
                return [
                    'success' => false,
                    'message' => 'Nur Administratoren können Benutzer löschen.'
                ];
            }

            // 2. Verhindere Selbstlöschung
            if ($user_id === $admin_id) {
                return [
                    'success' => false,
                    'message' => 'Du kannst dich nicht selbst löschen.'
                ];
            }

            // 3. Hole User-Daten
            $stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $userToDelete = $stmt->fetch();

            if (!$userToDelete) {
                return [
                    'success' => false,
                    'message' => 'Benutzer nicht gefunden.'
                ];
            }

            // 4. Prüfe ob es der letzte Admin ist
            if ($userToDelete['role'] === 'admin') {
                $stmt = $pdo->prepare("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin' AND is_active = 1");
                $stmt->execute();
                $adminCount = $stmt->fetch()['admin_count'];

                if ($adminCount <= 1) {
                    return [
                        'success' => false,
                        'message' => 'Der letzte Administrator kann nicht gelöscht werden.'
                    ];
                }
            }

            // 5. Starte Transaktion für sicheres Löschen
            $pdo->beginTransaction();

            // Lösche alle abhängigen Daten (CASCADE sollte das meiste erledigen, aber zur Sicherheit)

            // Lösche Sessions des Users
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // Lösche den User (CASCADE löscht automatisch: transactions, categories, investments, recurring_transactions)
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);

            $pdo->commit();

            // Log die Aktion
            error_log("User '{$userToDelete['username']}' (ID: $user_id) wurde von Admin (ID: $admin_id) gelöscht.");

            return [
                'success' => true,
                'message' => "Benutzer '{$userToDelete['username']}' wurde erfolgreich gelöscht."
            ];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Fehler beim Löschen des Users: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Fehler beim Löschen des Benutzers: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get all users with their details
     * 
     * @return array Array of all users
     */
    public function getAllUsers(): array
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare("
        SELECT 
            id, 
            username, 
            email, 
            full_name, 
            role, 
            is_active, 
            last_login, 
            created_at,
            starting_balance
        FROM users 
        ORDER BY created_at DESC
    ");
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Count users by role
     * 
     * @param string $role Role to count ('admin', 'user', 'viewer')
     * @return int Number of users with that role
     */
    public function countUsersByRole(string $role): int
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ? AND is_active = 1");
        $stmt->execute([$role]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Ensures a basic category set exists for a given user.
     * Call this after creating a new user account.
     * FIXED: Prüft jetzt ob überhaupt Kategorien existieren, nicht user-spezifisch
     *
     * @param int $user_id
     */
    public function ensureDefaultCategories(int $user_id): void
    {
        $pdo = $this->getConnection();

        // FIXED: If ANY categories exist, do nothing (shared categories)
        $check = $pdo->prepare('SELECT COUNT(*) AS cnt FROM categories');
        $check->execute();
        $row = $check->fetch();
        if ($row && (int)$row['cnt'] > 0) {
            return;
        }

        $default_categories = [
            // Income categories
            ['name' => 'Gehalt',      'type' => 'income',  'color' => '#4ade80', 'icon' => '<i class="fa-solid fa-wallet"></i>'],
            ['name' => 'Freelance',   'type' => 'income',  'color' => '#22c55e', 'icon' => '<i class="fa-brands fa-codepen"></i>'],
            ['name' => 'Bonus',       'type' => 'income',  'color' => '#10b981', 'icon' => '<i class="fa-solid fa-skull"></i>'],

            // Expense categories
            ['name' => 'Lebensmittel', 'type' => 'expense', 'color' => '#f97316', 'icon' => '<i class="fa-solid fa-bowl-food"></i>'],
            ['name' => 'Miete',       'type' => 'expense', 'color' => '#9333ea', 'icon' => '<i class="fa-solid fa-money-bill-transfer"></i>'],
            ['name' => 'Transport',   'type' => 'expense', 'color' => '#78716c', 'icon' => '<i class="fa-solid fa-car"></i>'],
            ['name' => 'Freizeit',    'type' => 'expense', 'color' => '#ec4899', 'icon' => '<i class="fa-brands fa-free-code-camp"></i>'],
            ['name' => 'Gesundheit',  'type' => 'expense', 'color' => '#ef4444', 'icon' => '<i class="fa-solid fa-notes-medical"></i>'],

            // Debt categories
            ['name' => 'Firma → Privat', 'type' => 'debt_out', 'color' => '#fbbf24', 'icon' => '<i class="fa-solid fa-money-bill-wave"></i>'],
            ['name' => 'Privat → Firma', 'type' => 'debt_in',  'color' => '#22c55e', 'icon' => '<i class="fa-solid fa-sack-dollar"></i>'],
            ['name' => 'Darlehen vergeben', 'type' => 'debt_out', 'color' => '#f97316', 'icon' => '<i class="fa-solid fa-handshake"></i>'],
            ['name' => 'Darlehen erhalten', 'type' => 'debt_in',  'color' => '#3b82f6', 'icon' => '<i class="fa-solid fa-wallet"></i>'],
        ];

        $stmt = $pdo->prepare('
            INSERT INTO categories (user_id, name, type, color, icon)
            VALUES (?, ?, ?, ?, ?)
        ');

        $pdo->beginTransaction();
        try {
            foreach ($default_categories as $c) {
                $stmt->execute([$user_id, $c['name'], $c['type'], $c['color'], $c['icon']]);
            }
            $pdo->commit();
        } catch (Throwable $t) {
            $pdo->rollBack();
            die('Seeding default categories failed: ' . $t->getMessage());
        }
    }

    /**
     * Creates a new user account with hashed password
     * UPDATED: Unterstützt neue User-Felder
     *
     * @param string $username
     * @param string $email
     * @param string $password_plain
     * @param string|null $full_name
     * @param string $role
     * @return int User ID
     */
    public function createUser(string $username, string $email, string $password_plain, ?string $full_name = null, string $role = 'user'): int
    {
        $pdo = $this->getConnection();

        // Check if user already exists
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            throw new RuntimeException('User already exists');
        }

        $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('
            INSERT INTO users (username, email, password, full_name, role, is_active, starting_balance)
            VALUES (?, ?, ?, ?, ?, 1, 0.00)
        ');

        $stmt->execute([$username, $email, $password_hash, $full_name ?? $username, $role]);
        $user_id = (int)$pdo->lastInsertId();

        // Create default categories for new user (only if no categories exist)
        $this->ensureDefaultCategories($user_id);

        return $user_id;
    }

    /**
     * Authenticate user by username/email and password
     * UPDATED: Unterstützt beide password Feldnamen
     *
     * @param string $identifier Username or email
     * @param string $password
     * @return array|null User data or null if authentication failed
     */
    public function authenticateUser(string $identifier, string $password): ?array
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('
            SELECT *
            FROM users 
            WHERE (username = ? OR email = ?) AND is_active = 1
        ');
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        // Unterstütze beide Feldnamen für Abwärtskompatibilität
        $password_field = $user['password'] ?? $user['password_hash'] ?? null;

        if (!$password_field || !password_verify($password, $password_field)) {
            return null;
        }

        // Update last login
        $updateStmt = $pdo->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
        $updateStmt->execute([$user['id']]);

        // Remove password from return data
        unset($user['password'], $user['password_hash']);
        return $user;
    }

    // ========================================
    // STARTING BALANCE METHODS
    // ========================================

    /**
     * Get user's starting balance
     * FIXED: Verwendet das Startkapital des ersten Users (gemeinsames System)
     *
     * @param int $user_id
     * @return float Starting balance
     */
    public function getStartingBalance(int $user_id): float
    {
        $pdo = $this->getConnection();

        // FIXED: Immer den ersten User nehmen (gemeinsames Startkapital)
        $stmt = $pdo->prepare('SELECT starting_balance FROM users ORDER BY id ASC LIMIT 1');
        $stmt->execute();
        $result = $stmt->fetchColumn();

        return $result !== false ? (float)$result : 0.00;
    }

    /**
     * Update user's starting balance
     * FIXED: Verwendet gemeinsames Startkapital (erste User)
     *
     * @param int $user_id
     * @param float $starting_balance
     * @return bool Success
     */
    public function updateStartingBalance(int $user_id, float $starting_balance): bool
    {
        $pdo = $this->getConnection();

        // FIXED: Immer den ersten User updaten (gemeinsames Startkapital)
        $stmt = $pdo->prepare('
            UPDATE users 
            SET starting_balance = ?
            WHERE id = (SELECT id FROM users ORDER BY id ASC LIMIT 1)
        ');

        return $stmt->execute([$starting_balance]);
    }

    // ========================================
    // FINANCIAL CALCULATION METHODS
    // ========================================

    /**
     * Get total income for all users (shared system)
     * FIXED: Konsistente Berechnung ohne user_id Filter
     *
     * @param string|null $month Optional month filter (Y-m format)
     * @return float Total income
     */
    public function getTotalIncome(?string $month = null): float
    {
        $pdo = $this->getConnection();

        if ($month) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(t.amount), 0) as total 
                FROM transactions t
                JOIN categories c ON t.category_id = c.id
                WHERE c.type = 'income' AND strftime('%Y-%m', t.date) = ?
            ");
            $stmt->execute([$month]);
        } else {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(t.amount), 0) as total 
                FROM transactions t
                JOIN categories c ON t.category_id = c.id
                WHERE c.type = 'income'
            ");
            $stmt->execute();
        }

        return (float)$stmt->fetchColumn();
    }

    /**
     * Get total expenses for all users (shared system)
     * FIXED: Konsistente Berechnung ohne user_id Filter
     *
     * @param string|null $month Optional month filter (Y-m format)
     * @return float Total expenses
     */
    public function getTotalExpenses(?string $month = null): float
    {
        $pdo = $this->getConnection();

        if ($month) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(t.amount), 0) as total 
                FROM transactions t
                JOIN categories c ON t.category_id = c.id
                WHERE c.type = 'expense' AND strftime('%Y-%m', t.date) = ?
            ");
            $stmt->execute([$month]);
        } else {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(t.amount), 0) as total 
                FROM transactions t
                JOIN categories c ON t.category_id = c.id
                WHERE c.type = 'expense'
            ");
            $stmt->execute();
        }

        return (float)$stmt->fetchColumn();
    }

    /**
     * Get total debt incoming (money received)
     * FIXED: Konsistente Berechnung ohne user_id Filter
     *
     * @param string|null $month Optional month filter (Y-m format)
     * @return float Total debt incoming
     */
    public function getTotalDebtIncoming(?string $month = null): float
    {
        $pdo = $this->getConnection();

        if ($month) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(t.amount), 0) as total 
                FROM transactions t
                JOIN categories c ON t.category_id = c.id
                WHERE c.type = 'debt_in' AND strftime('%Y-%m', t.date) = ?
            ");
            $stmt->execute([$month]);
        } else {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(t.amount), 0) as total 
                FROM transactions t
                JOIN categories c ON t.category_id = c.id
                WHERE c.type = 'debt_in'
            ");
            $stmt->execute();
        }

        return (float)$stmt->fetchColumn();
    }

    /**
     * Get total debt outgoing (money lent out)
     * FIXED: Konsistente Berechnung ohne user_id Filter
     *
     * @param string|null $month Optional month filter (Y-m format)
     * @return float Total debt outgoing
     */
    public function getTotalDebtOutgoing(?string $month = null): float
    {
        $pdo = $this->getConnection();

        if ($month) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(t.amount), 0) as total 
                FROM transactions t
                JOIN categories c ON t.category_id = c.id
                WHERE c.type = 'debt_out' AND strftime('%Y-%m', t.date) = ?
            ");
            $stmt->execute([$month]);
        } else {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(t.amount), 0) as total 
                FROM transactions t
                JOIN categories c ON t.category_id = c.id
                WHERE c.type = 'debt_out'
            ");
            $stmt->execute();
        }

        return (float)$stmt->fetchColumn();
    }

    /**
     * Calculate total wealth including investments
     * FIXED: Konsistente Berechnung mit gemeinsamen Werten
     *
     * @param int $user_id User ID for starting balance
     * @return array Wealth breakdown
     */
    public function getTotalWealth(int $user_id): array
    {
        $starting_balance = $this->getStartingBalance($user_id);
        $total_income = $this->getTotalIncome();
        $total_expenses = $this->getTotalExpenses();
        $total_debt_in = $this->getTotalDebtIncoming();
        $total_debt_out = $this->getTotalDebtOutgoing();
        $investment_stats = $this->getTotalInvestmentValue($user_id);
        $total_investments = $investment_stats['total_current_value'] ?? 0;

        // Base wealth calculation
        $base_wealth = $starting_balance + $total_income - $total_expenses;

        // Include debts in wealth calculation
        $wealth_with_debts = $base_wealth + $total_debt_in - $total_debt_out;

        // Total wealth including investments
        $total_wealth = $wealth_with_debts + $total_investments;

        return [
            'starting_balance' => $starting_balance,
            'total_income' => $total_income,
            'total_expenses' => $total_expenses,
            'total_debt_in' => $total_debt_in,
            'total_debt_out' => $total_debt_out,
            'net_debt_position' => $total_debt_in - $total_debt_out,
            'base_wealth' => $base_wealth,
            'wealth_with_debts' => $wealth_with_debts,
            'total_investments' => $total_investments,
            'total_wealth' => $total_wealth,
            'investment_stats' => $investment_stats
        ];
    }

    // ========================================
    // RECURRING TRANSACTIONS METHODS
    // ========================================

    /**
     * Get due recurring transactions
     * FIXED: Ohne user_id Filter für gemeinsames System
     *
     * @param int $user_id
     * @param int $days_ahead
     * @return array
     */
    public function getDueRecurringTransactions(int $user_id, int $days_ahead = 7): array
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare("
            SELECT rt.*, c.name as category_name, c.icon as category_icon, c.color as category_color, c.type as transaction_type
            FROM recurring_transactions rt
            JOIN categories c ON rt.category_id = c.id
            WHERE rt.is_active = 1 AND rt.next_due_date <= ?
            ORDER BY rt.next_due_date ASC
        ");

        $due_date = date('Y-m-d', strtotime("+$days_ahead days"));
        $stmt->execute([$due_date]);

        return $stmt->fetchAll();
    }

    /**
     * Process due recurring transactions
     * FIXED: Ohne user_id Filter aber behält user_id für neue Transaktionen
     *
     * @param int $user_id
     * @return int Number of processed transactions
     */
    public function processDueRecurringTransactions(int $user_id): int
    {
        $pdo = $this->getConnection();
        $processed_count = 0;

        try {
            $pdo->beginTransaction();

            // Get all overdue recurring transactions
            $stmt = $pdo->prepare("
                SELECT rt.*, c.name as category_name, c.type as category_type
                FROM recurring_transactions rt
                JOIN categories c ON rt.category_id = c.id
                WHERE rt.is_active = 1 AND rt.next_due_date <= ?
                ORDER BY rt.next_due_date ASC
            ");
            $stmt->execute([date('Y-m-d')]);
            $due_transactions = $stmt->fetchAll();

            foreach ($due_transactions as $recurring) {
                // Create new transaction
                $insert_stmt = $pdo->prepare("
                    INSERT INTO transactions (user_id, category_id, amount, note, date, recurring_transaction_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $note = $recurring['note'] . ' (Automatisch erstellt)';
                $insert_stmt->execute([
                    $user_id,
                    $recurring['category_id'],
                    $recurring['amount'],
                    $note,
                    $recurring['next_due_date'],
                    $recurring['id']
                ]);

                // Calculate next due date
                $next_due = $this->calculateNextDueDate(
                    $recurring['next_due_date'],
                    $recurring['frequency']
                );

                // Update recurring transaction
                $update_stmt = $pdo->prepare("
                    UPDATE recurring_transactions 
                    SET next_due_date = ? 
                    WHERE id = ?
                ");
                $update_stmt->execute([$next_due, $recurring['id']]);

                $processed_count++;
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error processing recurring transactions: " . $e->getMessage());
        }

        return $processed_count;
    }

    /**
     * Calculate next due date based on frequency
     *
     * @param string $current_date
     * @param string $frequency
     * @return string Next due date
     */
    private function calculateNextDueDate(string $current_date, string $frequency): string
    {
        $date = new DateTime($current_date);

        switch ($frequency) {
            case 'daily':
                $date->add(new DateInterval('P1D'));
                break;
            case 'weekly':
                $date->add(new DateInterval('P1W'));
                break;
            case 'monthly':
                $date->add(new DateInterval('P1M'));
                break;
            case 'yearly':
                $date->add(new DateInterval('P1Y'));
                break;
        }

        return $date->format('Y-m-d');
    }

    // ========================================
    // INVESTMENT METHODS
    // ========================================

    /**
     * Add new investment
     *
     * @param int $user_id
     * @param string $symbol
     * @param string $name
     * @param float $amount
     * @param float $purchase_price
     * @param string $purchase_date
     * @return int Investment ID
     */
    public function addInvestment(int $user_id, string $symbol, string $name, float $amount, float $purchase_price, string $purchase_date): int
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('
            INSERT INTO investments (user_id, symbol, name, amount, purchase_price, purchase_date)
            VALUES (?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([$user_id, $symbol, $name, $amount, $purchase_price, $purchase_date]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Get all investments with current values (only real data)
     * FIXED: Lädt alle Investments für gemeinsame Anzeige
     *
     * @param int $user_id
     * @return array Array of investments with current market data or error info
     */
    public function getInvestmentsWithCurrentValue(int $user_id): array
    {
        $pdo = $this->getConnection();

        // Get all investments from database (shared across all users for viewing)
        $stmt = $pdo->prepare('
            SELECT * FROM investments 
            ORDER BY created_at DESC
        ');
        $stmt->execute();
        $investments = $stmt->fetchAll();

        if (empty($investments)) {
            return [];
        }

        // Try to load CryptoAPI with multiple possible paths
        $crypto_api = null;
        $api_paths = [
            __DIR__ . '/crypto_api.php',
            dirname(__DIR__) . '/config/crypto_api.php',
            './config/crypto_api.php',
            'crypto_api.php'
        ];

        foreach ($api_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                if (class_exists('CryptoAPI')) {
                    $crypto_api = new CryptoAPI();
                    break;
                }
            }
        }

        // If CryptoAPI couldn't be loaded, return investments without current prices
        if (!$crypto_api) {
            foreach ($investments as &$investment) {
                $purchase_value = $investment['amount'] * $investment['purchase_price'];
                $investment['current_price'] = null;
                $investment['purchase_value'] = $purchase_value;
                $investment['current_value'] = null;
                $investment['profit_loss'] = null;
                $investment['profit_loss_percent'] = null;
                $investment['price_change_24h'] = null;
                $investment['api_error'] = 'CryptoAPI nicht verfügbar';
                $investment['data_status'] = 'api_unavailable';
            }
            return $investments;
        }

        // Collect all unique symbols
        $symbols = array_unique(array_column($investments, 'symbol'));

        // Get prices for all symbols at once
        $prices = $crypto_api->getCurrentPrices($symbols);

        // Add current market data to each investment
        foreach ($investments as &$investment) {
            $symbol = strtolower($investment['symbol']);
            $converted_symbol = $crypto_api->convertSymbolToId($investment['symbol']);
            $purchase_value = $investment['amount'] * $investment['purchase_price'];

            // Check if prices were fetched successfully
            if ($prices === false) {
                // API call failed completely
                $investment['current_price'] = null;
                $investment['purchase_value'] = $purchase_value;
                $investment['current_value'] = null;
                $investment['profit_loss'] = null;
                $investment['profit_loss_percent'] = null;
                $investment['price_change_24h'] = null;
                $investment['api_error'] = $crypto_api->getLastError() ?: 'API nicht verfügbar';
                $investment['data_status'] = 'api_error';
            } elseif (isset($prices[$converted_symbol]) && is_numeric($prices[$converted_symbol])) {
                // Success: we have current price data
                $current_price = (float)$prices[$converted_symbol];
                $price_change_24h = $prices[$converted_symbol . '_change'] ?? 0;

                $current_value = $investment['amount'] * $current_price;
                $profit_loss = $current_value - $purchase_value;
                $profit_loss_percent = $purchase_value > 0 ?
                    (($profit_loss / $purchase_value) * 100) : 0;

                $investment['current_price'] = $current_price;
                $investment['purchase_value'] = $purchase_value;
                $investment['current_value'] = $current_value;
                $investment['profit_loss'] = $profit_loss;
                $investment['profit_loss_percent'] = $profit_loss_percent;
                $investment['price_change_24h'] = $price_change_24h;
                $investment['api_error'] = null;
                $investment['data_status'] = 'current';
            } else {
                // Symbol not found in API response
                $investment['current_price'] = null;
                $investment['purchase_value'] = $purchase_value;
                $investment['current_value'] = null;
                $investment['profit_loss'] = null;
                $investment['profit_loss_percent'] = null;
                $investment['price_change_24h'] = null;
                $investment['api_error'] = "Symbol '{$investment['symbol']}' nicht gefunden";
                $investment['data_status'] = 'symbol_not_found';
            }
        }

        return $investments;
    }

    /**
     * Get total investment value statistics (only real data)
     * FIXED: Berechnet für alle Investments
     *
     * @param int $user_id
     * @return array Array with total values and statistics or error info
     */
    public function getTotalInvestmentValue(int $user_id): array
    {
        $investments = $this->getInvestmentsWithCurrentValue($user_id);

        if (empty($investments)) {
            return [
                'investment_count' => 0,
                'total_purchase_value' => 0.00,
                'total_current_value' => 0.00,
                'total_profit_loss' => 0.00,
                'total_profit_loss_percent' => 0.00,
                'data_status' => 'no_investments',
                'api_error' => null
            ];
        }

        // Check if we have any API errors
        $has_api_errors = false;
        $error_count = 0;
        $working_investments = [];

        foreach ($investments as $investment) {
            if ($investment['data_status'] === 'current') {
                $working_investments[] = $investment;
            } else {
                $has_api_errors = true;
                $error_count++;
            }
        }

        // Calculate totals only from working investments
        $total_purchase_value = 0;
        $total_current_value = 0;

        foreach ($investments as $investment) {
            $total_purchase_value += $investment['purchase_value'];

            if ($investment['current_value'] !== null) {
                $total_current_value += $investment['current_value'];
            } else {
                // For broken investments, we can't calculate current value
                $total_current_value = null;
                break;
            }
        }

        // Calculate profit/loss only if we have current values
        if ($total_current_value !== null) {
            $total_profit_loss = $total_current_value - $total_purchase_value;
            $total_profit_loss_percent = $total_purchase_value > 0 ?
                (($total_profit_loss / $total_purchase_value) * 100) : 0;
            $data_status = $has_api_errors ? 'partial_data' : 'current';
        } else {
            $total_profit_loss = null;
            $total_profit_loss_percent = null;
            $data_status = 'api_unavailable';
        }

        return [
            'investment_count' => count($investments),
            'total_purchase_value' => $total_purchase_value,
            'total_current_value' => $total_current_value,
            'total_profit_loss' => $total_profit_loss,
            'total_profit_loss_percent' => $total_profit_loss_percent,
            'data_status' => $data_status,
            'error_count' => $error_count,
            'working_count' => count($working_investments),
            'api_error' => $has_api_errors ? 'Einige Preise konnten nicht abgerufen werden' : null
        ];
    }

    /**
     * Get single investment by ID
     *
     * @param int $investment_id
     * @return array|false Investment data or false if not found
     */
    public function getInvestmentById(int $investment_id)
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM investments WHERE id = ?');
        $stmt->execute([$investment_id]);

        return $stmt->fetch() ?: false;
    }

    /**
     * Update existing investment
     *
     * @param int $investment_id
     * @param string $symbol
     * @param string $name
     * @param float $amount
     * @param float $purchase_price
     * @param string $purchase_date
     * @return bool Success
     */
    public function updateInvestment(int $investment_id, string $symbol, string $name, float $amount, float $purchase_price, string $purchase_date): bool
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('
            UPDATE investments 
            SET symbol = ?, name = ?, amount = ?, purchase_price = ?, purchase_date = ?
            WHERE id = ?
        ');

        return $stmt->execute([$symbol, $name, $amount, $purchase_price, $purchase_date, $investment_id]);
    }

    /**
     * Delete investment by ID
     *
     * @param int $investment_id
     * @return bool Success
     */
    public function deleteInvestment(int $investment_id): bool
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('DELETE FROM investments WHERE id = ?');
        return $stmt->execute([$investment_id]);
    }

    /**
     * Check if investment belongs to user (for security)
     * FIXED: Erlaubt allen Usern Zugriff (gemeinsames System)
     *
     * @param int $investment_id
     * @param int $user_id
     * @return bool
     */
    public function isInvestmentOwner(int $investment_id, int $user_id): bool
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('SELECT id FROM investments WHERE id = ?');
        $stmt->execute([$investment_id]);
        $result = $stmt->fetchColumn();

        // FIXED: Allow all users to access all investments (shared system)
        return $result !== false;
    }

    // ========================================
    // TRANSACTION METHODS
    // ========================================

    /**
     * Get transactions with optional filters
     * FIXED: Lädt alle Transaktionen für gemeinsame Anzeige
     *
     * @param int $user_id
     * @param array $filters Optional filters (type, month, category_id, limit)
     * @return array Array of transactions
     */
    public function getTransactions(int $user_id, array $filters = []): array
    {
        $pdo = $this->getConnection();

        $sql = "
            SELECT t.*, c.name as category_name, c.icon as category_icon, 
                   c.color as category_color, c.type as transaction_type
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
        ";

        $where_conditions = [];
        $params = [];

        // Type filter
        if (!empty($filters['type'])) {
            $where_conditions[] = "c.type = ?";
            $params[] = $filters['type'];
        }

        // Month filter
        if (!empty($filters['month'])) {
            $where_conditions[] = "strftime('%Y-%m', t.date) = ?";
            $params[] = $filters['month'];
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $where_conditions[] = "t.category_id = ?";
            $params[] = $filters['category_id'];
        }

        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(' AND ', $where_conditions);
        }

        $sql .= " ORDER BY t.date DESC, t.created_at DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Add new transaction
     *
     * @param int $user_id
     * @param int $category_id
     * @param float $amount
     * @param string $note
     * @param string $date
     * @param int|null $recurring_transaction_id
     * @return int Transaction ID
     */
    public function addTransaction(int $user_id, int $category_id, float $amount, string $note, string $date, ?int $recurring_transaction_id = null): int
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('
            INSERT INTO transactions (user_id, category_id, amount, note, date, recurring_transaction_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([$user_id, $category_id, $amount, $note, $date, $recurring_transaction_id]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Get transaction by ID
     *
     * @param int $transaction_id
     * @return array|false Transaction data or false if not found
     */
    public function getTransactionById(int $transaction_id)
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('
            SELECT t.*, c.name as category_name, c.type as category_type
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE t.id = ?
        ');
        $stmt->execute([$transaction_id]);

        return $stmt->fetch() ?: false;
    }

    /**
     * Update existing transaction
     *
     * @param int $transaction_id
     * @param int $category_id
     * @param float $amount
     * @param string $note
     * @param string $date
     * @return bool Success
     */
    public function updateTransaction(int $transaction_id, int $category_id, float $amount, string $note, string $date): bool
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('
            UPDATE transactions 
            SET category_id = ?, amount = ?, note = ?, date = ?
            WHERE id = ?
        ');

        return $stmt->execute([$category_id, $amount, $note, $date, $transaction_id]);
    }

    /**
     * Delete transaction by ID
     *
     * @param int $transaction_id
     * @return bool Success
     */
    public function deleteTransaction(int $transaction_id): bool
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('DELETE FROM transactions WHERE id = ?');
        return $stmt->execute([$transaction_id]);
    }

    /**
     * Check if transaction exists
     * FIXED: Erlaubt allen Usern Zugriff (gemeinsames System)
     *
     * @param int $transaction_id
     * @param int $user_id
     * @return bool
     */
    public function isTransactionOwner(int $transaction_id, int $user_id): bool
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('SELECT id FROM transactions WHERE id = ?');
        $stmt->execute([$transaction_id]);
        $result = $stmt->fetchColumn();

        // FIXED: Allow all users to access all transactions (shared system)
        return $result !== false;
    }

    // ========================================
    // CATEGORY METHODS
    // ========================================

    /**
     * Get all categories
     * FIXED: Lädt alle Kategorien für gemeinsame Nutzung
     *
     * @param string|null $type Optional type filter
     * @return array Array of categories
     */
    public function getCategories(?string $type = null): array
    {
        $pdo = $this->getConnection();

        if ($type) {
            $stmt = $pdo->prepare('SELECT * FROM categories WHERE type = ? ORDER BY name');
            $stmt->execute([$type]);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM categories ORDER BY type, name');
            $stmt->execute();
        }

        return $stmt->fetchAll();
    }

    /**
     * Get category by ID
     *
     * @param int $category_id
     * @return array|false Category data or false if not found
     */
    public function getCategoryById(int $category_id)
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$category_id]);

        return $stmt->fetch() ?: false;
    }

    /**
     * Add new category
     *
     * @param int $user_id
     * @param string $name
     * @param string $type
     * @param string $color
     * @param string $icon
     * @return int Category ID
     */
    public function addCategory(int $user_id, string $name, string $type, string $color, string $icon): int
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('
            INSERT INTO categories (user_id, name, type, color, icon)
            VALUES (?, ?, ?, ?, ?)
        ');

        $stmt->execute([$user_id, $name, $type, $color, $icon]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Update existing category
     *
     * @param int $category_id
     * @param string $name
     * @param string $type
     * @param string $color
     * @param string $icon
     * @return bool Success
     */
    public function updateCategory(int $category_id, string $name, string $type, string $color, string $icon): bool
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('
            UPDATE categories 
            SET name = ?, type = ?, color = ?, icon = ?
            WHERE id = ?
        ');

        return $stmt->execute([$name, $type, $color, $icon, $category_id]);
    }

    /**
     * Delete category by ID
     *
     * @param int $category_id
     * @return bool Success
     */
    public function deleteCategory(int $category_id): bool
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
        return $stmt->execute([$category_id]);
    }

    /**
     * Check if category is in use
     *
     * @param int $category_id
     * @return bool True if category is used by any transaction
     */
    public function isCategoryInUse(int $category_id): bool
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE category_id = ?');
        $stmt->execute([$category_id]);

        return (int)$stmt->fetchColumn() > 0;
    }


    /**
     * Get category usage statistics
     *
     * @param int $category_id
     * @return array Usage statistics
     */
    public function getCategoryStats(int $category_id): array
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as transaction_count,
                COALESCE(SUM(amount), 0) as total_amount,
                MAX(date) as last_used
            FROM transactions 
            WHERE category_id = ?
        ");
        $stmt->execute([$category_id]);
        $stats = $stmt->fetch();

        return [
            'transaction_count' => (int)($stats['transaction_count'] ?? 0),
            'total_amount' => (float)($stats['total_amount'] ?? 0.00),
            'last_used' => $stats['last_used'] ?? null
        ];
    }
}
