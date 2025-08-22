<?php

/**
 * Timezone Helper für Cashbook System - FINALE VERSION
 * Datenbank-Einstellungen haben IMMER Priorität über Browser-Erkennung
 * Speichere als: includes/timezone.php
 */

class TimezoneHelper
{
    private static $userTimezone = null;
    private static $isManuallySet = null; // Cache für DB-Abfrage

    /**
     * Initialisiert die Zeitzone für den aktuellen Benutzer
     * Wird nach dem Login aufgerufen
     */
    public static function initializeTimezone()
    {
        // IMMER zuerst aus Datenbank laden wenn User eingeloggt
        if (isset($_SESSION['user_id'])) {
            $dbData = self::getUserTimezoneDataFromDB($_SESSION['user_id']);

            if ($dbData && $dbData['timezone']) {
                // Zeitzone aus DB verwenden
                self::$userTimezone = $dbData['timezone'];
                self::$isManuallySet = ($dbData['timezone_manual'] == 1);

                $_SESSION['user_timezone'] = $dbData['timezone'];
                $_SESSION['timezone_manually_set'] = self::$isManuallySet;

                date_default_timezone_set($dbData['timezone']);
                return;
            }
        }

        // Fallback wenn keine DB-Einstellung vorhanden
        if (isset($_SESSION['user_timezone'])) {
            self::$userTimezone = $_SESSION['user_timezone'];
            date_default_timezone_set(self::$userTimezone);
            return;
        }

        // Standard-Fallback
        self::setTimezone('Europe/Berlin', false);
    }

    /**
     * Setzt die Zeitzone für den aktuellen Benutzer
     * @param string $timezone Die Zeitzone
     * @param bool $isManual Ob dies eine manuelle Einstellung ist
     */
    public static function setTimezone($timezone, $isManual = true)
    {
        if (self::isValidTimezone($timezone)) {
            self::$userTimezone = $timezone;
            self::$isManuallySet = $isManual;

            $_SESSION['user_timezone'] = $timezone;
            $_SESSION['timezone_manually_set'] = $isManual;

            date_default_timezone_set($timezone);

            // WICHTIG: Speichere in DB mit Manual-Flag
            if (isset($_SESSION['user_id'])) {
                self::saveUserTimezone($_SESSION['user_id'], $timezone, $isManual);
            }

            return true;
        }
        return false;
    }

    /**
     * Automatische Zeitzone vom Browser setzen
     * NUR wenn NICHT manuell in DB gesetzt
     */
    public static function setTimezoneFromBrowser($timezone)
    {
        // WICHTIG: IMMER aus DB prüfen, nicht aus Session!
        if (isset($_SESSION['user_id'])) {
            $dbData = self::getUserTimezoneDataFromDB($_SESSION['user_id']);

            // Wenn in DB als manuell markiert, NICHT überschreiben
            if ($dbData && $dbData['timezone_manual'] == 1) {
                // Setze Session-Variablen aus DB (für diese Session)
                $_SESSION['timezone_manually_set'] = true;
                $_SESSION['user_timezone'] = $dbData['timezone'];
                self::$userTimezone = $dbData['timezone'];
                self::$isManuallySet = true;
                date_default_timezone_set($dbData['timezone']);

                return false; // Nicht überschrieben
            }
        }

        // Nur setzen wenn nicht manuell in DB
        return self::setTimezone($timezone, false);
    }

    /**
     * Konvertiert einen UTC Timestamp in die Benutzer-Zeitzone
     */
    public static function convertToUserTimezone($utcDateTime, $format = 'd.m.Y H:i')
    {
        if (!$utcDateTime) {
            return '';
        }

        try {
            $date = new DateTime($utcDateTime, new DateTimeZone('UTC'));
            $userTz = self::$userTimezone ?: 'Europe/Berlin';
            $date->setTimezone(new DateTimeZone($userTz));
            return $date->format($format);
        } catch (Exception $e) {
            return date($format, strtotime($utcDateTime));
        }
    }

    /**
     * Konvertiert lokale Zeit zu UTC für Datenbank-Speicherung
     */
    public static function convertToUTC($localDateTime)
    {
        if (!$localDateTime) {
            return null;
        }

        try {
            $userTz = self::$userTimezone ?: 'Europe/Berlin';
            $date = new DateTime($localDateTime, new DateTimeZone($userTz));
            $date->setTimezone(new DateTimeZone('UTC'));
            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return $localDateTime;
        }
    }

    /**
     * Holt die aktuelle Zeit in der Benutzer-Zeitzone
     */
    public static function getCurrentUserTime($format = 'Y-m-d H:i:s')
    {
        $userTz = self::$userTimezone ?: 'Europe/Berlin';
        $date = new DateTime('now', new DateTimeZone($userTz));
        return $date->format($format);
    }

    /**
     * Gibt die aktuelle Zeitzone zurück
     */
    public static function getCurrentTimezone()
    {
        return self::$userTimezone ?: 'Europe/Berlin';
    }

    /**
     * Prüft ob die aktuelle Einstellung manuell ist
     */
    public static function isManuallySet()
    {
        // Erst Cache prüfen
        if (self::$isManuallySet !== null) {
            return self::$isManuallySet;
        }

        // Dann aus DB laden
        if (isset($_SESSION['user_id'])) {
            $dbData = self::getUserTimezoneDataFromDB($_SESSION['user_id']);
            if ($dbData) {
                self::$isManuallySet = ($dbData['timezone_manual'] == 1);
                return self::$isManuallySet;
            }
        }

        return false;
    }

    /**
     * Prüft ob eine Zeitzone gültig ist
     */
    private static function isValidTimezone($timezone)
    {
        return in_array($timezone, timezone_identifiers_list());
    }

    /**
     * Holt Zeitzone UND Manual-Flag aus der Datenbank
     * WICHTIG: Diese Methode ist zentral für die korrekte Funktion!
     */
    private static function getUserTimezoneDataFromDB($userId)
    {
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = new Database();
            $pdo = $db->getConnection();

            // Prüfe ob Spalten existieren
            $stmt = $pdo->prepare("PRAGMA table_info(users)");
            $stmt->execute();
            $columns = $stmt->fetchAll();
            $columnNames = array_column($columns, 'name');

            $hasTimezone = in_array('timezone', $columnNames);
            $hasManual = in_array('timezone_manual', $columnNames);

            if (!$hasTimezone) {
                return null;
            }

            // Hole Daten aus DB
            if ($hasManual) {
                $stmt = $pdo->prepare("SELECT timezone, timezone_manual FROM users WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("SELECT timezone, 0 as timezone_manual FROM users WHERE id = ?");
            }

            $stmt->execute([$userId]);
            $result = $stmt->fetch();

            return $result ?: null;
        } catch (Exception $e) {
            error_log("Failed to get timezone from DB: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Speichert die Zeitzone in der Datenbank
     * WICHTIG: Manual-Flag wird IMMER mit gespeichert!
     */
    private static function saveUserTimezone($userId, $timezone, $isManual = false)
    {
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = new Database();
            $pdo = $db->getConnection();

            // Prüfe und erstelle Spalten wenn nötig
            $stmt = $pdo->prepare("PRAGMA table_info(users)");
            $stmt->execute();
            $columns = $stmt->fetchAll();
            $columnNames = array_column($columns, 'name');

            if (!in_array('timezone', $columnNames)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN timezone TEXT DEFAULT 'Europe/Berlin'");
            }

            if (!in_array('timezone_manual', $columnNames)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN timezone_manual INTEGER DEFAULT 0");
            }

            // Update Zeitzone UND Manual-Flag
            $stmt = $pdo->prepare("UPDATE users SET timezone = ?, timezone_manual = ? WHERE id = ?");
            $result = $stmt->execute([$timezone, $isManual ? 1 : 0, $userId]);

            // Cache aktualisieren
            self::$isManuallySet = $isManual;

            return $result;
        } catch (Exception $e) {
            error_log("Failed to save timezone: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gibt eine Liste aller verfügbaren Zeitzonen zurück
     */
    public static function getTimezoneList()
    {
        $timezones = [];
        $regions = [
            'Europe' => 'Europa',
            'America' => 'Amerika',
            'Asia' => 'Asien',
            'Africa' => 'Afrika',
            'Pacific' => 'Pazifik',
            'Atlantic' => 'Atlantik',
            'Indian' => 'Indischer Ozean'
        ];

        foreach ($regions as $region => $label) {
            $timezones[$label] = [];
            $tzlist = timezone_identifiers_list(constant("DateTimeZone::" . strtoupper($region)));

            foreach ($tzlist as $tz) {
                $city = str_replace('_', ' ', substr($tz, strlen($region) + 1));
                $timezones[$label][$tz] = $city;
            }
        }

        return $timezones;
    }

    /**
     * Reset auf automatische Erkennung
     */
    public static function resetToAutomatic($userId)
    {
        // Session-Variablen löschen
        unset($_SESSION['timezone_manually_set']);
        self::$isManuallySet = false;

        try {
            require_once __DIR__ . '/../config/database.php';
            $db = new Database();
            $pdo = $db->getConnection();

            // In DB auf automatisch setzen
            $stmt = $pdo->prepare("UPDATE users SET timezone_manual = 0 WHERE id = ?");
            return $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Failed to reset timezone: " . $e->getMessage());
            return false;
        }
    }
}

// WICHTIG: Initialisierung NUR wenn Session aktiv UND User eingeloggt
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    TimezoneHelper::initializeTimezone();
}
