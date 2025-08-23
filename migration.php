<?php

/**
 * Migration: Fügt Lizenz-Felder zur users-Tabelle hinzu
 * Führe dieses Script einmal aus, um die Datenbank zu aktualisieren
 */

require_once 'config/database.php';

echo "🔧 Starte Datenbank-Migration für Lizenzfelder...\n\n";

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // 1. Füge license_key Feld zur users Tabelle hinzu
    echo "➤ Füge license_key Feld zur users Tabelle hinzu...\n";

    // Prüfe ob Spalte bereits existiert
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
        echo "   ✅ license_key Feld hinzugefügt\n";
    } else {
        echo "   ℹ️ license_key Feld existiert bereits\n";
    }

    // 2. Erstelle license_cache Tabelle
    echo "\n➤ Erstelle license_cache Tabelle...\n";

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
    echo "   ✅ license_cache Tabelle erstellt/aktualisiert\n";

    // 3. Erstelle Index für bessere Performance
    echo "\n➤ Erstelle Indizes...\n";

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_license_key ON license_cache (license_key)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_hardware_id ON license_cache (hardware_id)");
    echo "   ✅ Indizes erstellt\n";

    // 4. Optional: Setze Standard-Lizenzschlüssel für Admin
    echo "\n➤ Prüfe Admin-Account...\n";

    $stmt = $pdo->prepare("SELECT id, username, license_key FROM users WHERE role = 'admin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();

    if ($admin && empty($admin['license_key'])) {
        echo "   ℹ️ Admin-Account gefunden: " . $admin['username'] . "\n";
        echo "   ⚠️ Noch kein Lizenzschlüssel hinterlegt\n";
        echo "\n   Um einen Lizenzschlüssel zu hinterlegen, führe aus:\n";
        echo "   UPDATE users SET license_key = 'DEIN-LIZENZ-KEY' WHERE id = " . $admin['id'] . ";\n";
    } elseif ($admin) {
        echo "   ✅ Admin hat bereits Lizenzschlüssel\n";
    }

    echo "\n✅ Migration erfolgreich abgeschlossen!\n\n";

    // Zeige Statistiken
    $stmt = $pdo->query("SELECT COUNT(*) as user_count FROM users");
    $userCount = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) as licensed_users FROM users WHERE license_key IS NOT NULL");
    $licensedUsers = $stmt->fetchColumn();

    echo "📊 Statistiken:\n";
    echo "   - Gesamte Benutzer: $userCount\n";
    echo "   - Benutzer mit Lizenz: $licensedUsers\n";
} catch (Exception $e) {
    echo "\n❌ Fehler bei der Migration: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n💡 Nächste Schritte:\n";
echo "1. Trage deinen Lizenzschlüssel in die Datenbank ein:\n";
echo "   sqlite3 database/cashbook.db\n";
echo "   UPDATE users SET license_key = 'DEIN-LIZENZ-KEY' WHERE username = 'dein_username';\n";
echo "\n2. Passe die config/license.php an:\n";
echo "   - Setze die richtige API-URL deines Lizenzservers\n";
echo "\n3. Teste die Anmeldung mit dem Lizenzschlüssel\n";
