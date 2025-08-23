<?php

/**
 * Lizenzserver-Konfiguration für Cashbook System
 * Zentrale Konfiguration für die Lizenzverifizierung
 */

return [
    // Lizenzserver API URL - passe diese an deine Server-URL an
    'api_url' => 'https://license.meinefirma.dev/api', // WICHTIG: Anpassen!

    // Alternativ für lokale Tests:
    // 'api_url' => 'http://localhost/lizenzserver/api',

    // Lizenz-Einstellungen
    'enabled' => true, // Lizenzprüfung aktivieren/deaktivieren
    'cache_duration' => 86400, // Cache-Dauer in Sekunden (24 Stunden)
    'offline_grace_period' => 7, // Tage offline-Betrieb bei gültiger Lizenz

    // Hardware-ID Generation
    'hardware_id_method' => 'auto', // 'auto', 'manual', 'ip_based'

    // Features, die lizenzabhängig sind
    'licensed_features' => [
        'investments' => true,
        'recurring' => true,
        'multi_user' => true,
        'api_access' => true,
        'reports' => true
    ],

    // Standard-Features (immer verfügbar)
    'free_features' => [
        'basic_transactions' => true,
        'categories' => true,
        'dashboard' => true
    ]
];
