<?php

/**
 * Lizenz-System Setup Script
 * Führt alle notwendigen Schritte automatisch aus
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>Lizenz-System Setup</title>
    <style>
        body { font-family: Arial; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 800px; margin: 0 auto; }
        h1 { color: #333; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .code { background: #f0f0f0; padding: 10px; border-radius: 4px; margin: 10px 0; }
        pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>
<h1>🔧 Cashbook Lizenz-System Setup</h1>";

// 1. Prüfe Voraussetzungen
echo "<h2>1️⃣ Prüfe Voraussetzungen</h2>";

// PHP Version
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    echo "<p class='success'>✅ PHP Version " . PHP_VERSION . " ist kompatibel</p>";
} else {
    echo "<p class='error'>❌ PHP Version " . PHP_VERSION . " ist zu alt (mindestens 7.4 erforderlich)</p>";
}

// SQLite
if (extension_loaded('pdo_sqlite')) {
    echo "<p class='success'>✅ SQLite PDO Extension ist installiert</p>";
} else {
    echo "<p class='error'>❌ SQLite PDO Extension fehlt</p>";
}

// 2. Erstelle Config-Datei wenn nicht vorhanden
echo "<h2>2️⃣ Erstelle Konfigurationsdatei</h2>";

$configFile = __DIR__ . '/config/license.php';
if (!file_exists($configFile)) {
    $configContent = '<?php
/**
 * Lizenzserver-Konfiguration für Cashbook System
 */

return [
    // Lizenzserver API URL - WICHTIG: Anpassen!
    \'api_url\' => \'http://localhost/lizenzserver/api\', // Ändere dies zu deiner Server-URL!
    
    // Lizenz-Einstellungen
    \'enabled\' => true, // Lizenzprüfung aktivieren
    \'cache_duration\' => 86400, // Cache-Dauer in Sekunden (24 Stunden)
    \'offline_grace_period\' => 7, // Tage offline-Betrieb bei gültiger Lizenz
    
    // Hardware-ID Generation
    \'hardware_id_method\' => \'auto\', // \'auto\', \'manual\', \'ip_based\'
    
    // Features, die lizenzabhängig sind
    \'licensed_features\' => [
        \'investments\' => true,
        \'recurring\' => true,
        \'multi_user\' => true,
        \'api_access\' => true,
        \'reports\' => true
    ],
    
    // Standard-Features (immer verfügbar)
    \'free_features\' => [
        \'basic_transactions\' => true,
        \'categories\' => true,
        \'dashboard\' => true
    ]
];';

    if (file_put_contents($configFile, $configContent)) {
        echo "<p class='success'>✅ config/license.php wurde erstellt</p>";
        echo "<p class='warning'>⚠️ WICHTIG: Bearbeite config/license.php und setze die richtige api_url!</p>";
    } else {
        echo "<p class='error'>❌ Konnte config/license.php nicht erstellen</p>";
    }
} else {
    echo "<p class='success'>✅ config/license.php existiert bereits</p>";
}

// 3. Datenbank-Migration
echo "<h2>3️⃣ Datenbank-Migration</h2>";

try {
    require_once 'config/database.php';
    $db = new Database();
    $pdo = $db->getConnection();

    // Prüfe ob license_key Spalte existiert
    $stmt = $pdo->query("PRAGMA table_info(users)");
    $columns = $stmt->fetchAll();
    $hasLicenseKey = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'license_key') {
            $hasLicenseKey = true;
            break;
        }
    }

    if (!$hasLicenseKey) {
        $pdo->exec("ALTER TABLE users ADD COLUMN license_key TEXT");
        echo "<p class='success'>✅ license_key Spalte wurde zur users Tabelle hinzugefügt</p>";
    } else {
        echo "<p class='success'>✅ license_key Spalte existiert bereits</p>";
    }

    // Erstelle license_cache Tabelle
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

    $pdo->exec($sql);
    echo "<p class='success'>✅ license_cache Tabelle wurde erstellt/aktualisiert</p>";

    // Erstelle Indizes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_license_key ON license_cache (license_key)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_hardware_id ON license_cache (hardware_id)");
    echo "<p class='success'>✅ Datenbank-Indizes wurden erstellt</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Datenbank-Fehler: " . $e->getMessage() . "</p>";
}

// 4. Zeige aktuelle Benutzer
echo "<h2>4️⃣ Aktuelle Benutzer</h2>";

try {
    $stmt = $pdo->query("SELECT id, username, email, role, license_key FROM users");
    $users = $stmt->fetchAll();

    if (count($users) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Rolle</th><th>Lizenz</th></tr>";

        foreach ($users as $user) {
            $hasLicense = !empty($user['license_key']) ? '✅' : '❌';
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['role']}</td>";
            echo "<td>{$hasLicense}</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Zeige SQL-Befehl zum Hinzufügen einer Lizenz
        if (count($users) > 0) {
            $firstUser = $users[0];
            echo "<div class='code'>";
            echo "<h3>📝 Lizenz für ersten Benutzer setzen:</h3>";
            echo "<pre>sqlite3 database/cashbook.db</pre>";
            echo "<pre>UPDATE users SET license_key = 'DEIN-LIZENZ-KEY' WHERE id = {$firstUser['id']};</pre>";
            echo "</div>";
        }
    } else {
        echo "<p class='warning'>Keine Benutzer gefunden</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>Fehler beim Laden der Benutzer: " . $e->getMessage() . "</p>";
}

// 5. Nächste Schritte
echo "<h2>5️⃣ Nächste Schritte</h2>";
echo "<ol>";
echo "<li><strong>Konfiguration anpassen:</strong> Bearbeite <code>config/license.php</code> und setze die richtige <code>api_url</code> zu deinem Lizenzserver</li>";
echo "<li><strong>Lizenzschlüssel hinzufügen:</strong> Verwende den SQL-Befehl oben oder die Web-Oberfläche</li>";
echo "<li><strong>Dateien kopieren:</strong> Stelle sicher, dass alle neuen Dateien vorhanden sind:
    <ul>
        <li>includes/license.class.php</li>
        <li>includes/auth.php (aktualisierte Version)</li>
        <li>activate-license.php</li>
        <li>auth/login.php</li>
    </ul>
</li>";
echo "<li><strong>Testen:</strong> Versuche dich einzuloggen - du solltest zur Lizenz-Aktivierung weitergeleitet werden</li>";
echo "</ol>";

// 6. Test-Links
echo "<h2>6️⃣ Test-Links</h2>";
echo "<p>";
echo "<a href='index.php' style='margin-right: 10px; padding: 10px 20px; background: #4f46e5; color: white; text-decoration: none; border-radius: 4px;'>Login-Seite</a>";
echo "<a href='activate-license.php' style='margin-right: 10px; padding: 10px 20px; background: #059669; color: white; text-decoration: none; border-radius: 4px;'>Lizenz-Aktivierung</a>";
echo "<a href='modules/settings/license.php' style='padding: 10px 20px; background: #dc2626; color: white; text-decoration: none; border-radius: 4px;'>Lizenz-Verwaltung</a>";
echo "</p>";

echo "</div></body></html>";
