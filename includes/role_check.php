<?php

/**
 * Role-based Access Control Helper
 * Prüft Benutzerrechte basierend auf Rollen
 */

/**
 * Prüft ob der aktuelle Benutzer die erforderliche Rolle hat
 * @param string|array $required_roles Eine Rolle oder Array von erlaubten Rollen
 * @param array $currentUser Der aktuelle Benutzer aus Auth
 * @return bool
 */
function hasRole($required_roles, $currentUser)
{
    if (!$currentUser) return false;

    $user_role = $currentUser['role'] ?? 'user';

    // Wenn String, in Array konvertieren
    if (!is_array($required_roles)) {
        $required_roles = [$required_roles];
    }

    return in_array($user_role, $required_roles);
}

/**
 * Prüft ob Benutzer Zugriff auf Module hat
 * @param array $currentUser
 * @return bool
 */
function canAccessModules($currentUser)
{
    return hasRole(['admin', 'user'], $currentUser);
}

/**
 * Prüft ob Benutzer Einträge erstellen/bearbeiten kann
 * @param array $currentUser
 * @return bool
 */
function canEditEntries($currentUser)
{
    return hasRole(['admin', 'user'], $currentUser);
}

/**
 * Prüft ob Benutzer Startkapital ändern kann
 * @param array $currentUser
 * @return bool
 */
function canEditStartingBalance($currentUser)
{
    return hasRole(['admin'], $currentUser);
}

/**
 * Prüft ob Benutzer neue User erstellen kann
 * @param array $currentUser
 * @return bool
 */
function canCreateUsers($currentUser)
{
    return hasRole(['admin'], $currentUser);
}

/**
 * Leitet Viewer zum Dashboard um wenn sie auf Module zugreifen wollen
 * @param array $currentUser
 */
function restrictViewerAccess($currentUser)
{
    if (hasRole(['viewer'], $currentUser)) {
        $_SESSION['error'] = 'Sie haben keine Berechtigung für diese Aktion.';
        header('Location: /dashboard.php');
        exit;
    }
}

/**
 * Zeigt Fehler und stoppt Ausführung wenn keine Berechtigung
 * @param string|array $required_roles
 * @param array $currentUser
 */
function requireRole($required_roles, $currentUser)
{
    if (!hasRole($required_roles, $currentUser)) {
        $_SESSION['error'] = 'Sie haben keine Berechtigung für diese Aktion.';
        header('Location: /dashboard.php');
        exit;
    }
}
