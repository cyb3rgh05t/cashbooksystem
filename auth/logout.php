<?php

/**
 * Logout Handler mit Auth-Klasse
 * Für Cashbook System
 */

require_once '../includes/auth.php';

// Perform logout
$auth->logout();

// Set logout message
$_SESSION['logout_message'] = 'Sie wurden erfolgreich abgemeldet.';

// Redirect to login page
header('Location: ../index.php');
exit;
