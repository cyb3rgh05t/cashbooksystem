<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_once 'includes/init_logger.php';
require_once 'includes/timezone.php';
require_once 'includes/role_check.php';

// Require login
$auth->requireLogin();

// Get current user
$currentUser = $auth->getCurrentUser();
$user_id = $currentUser['id'];

// Aktuelle Zeitzone holen
$current_timezone = TimezoneHelper::getCurrentTimezone();
$timezone_list = TimezoneHelper::getTimezoneList();
$is_manually_set = isset($_SESSION['timezone_manually_set']) && $_SESSION['timezone_manually_set'];

// Database connection
$db = new Database();
$pdo = $db->getConnection();

// Startkapital laden
$current_starting_balance = $db->getStartingBalance($user_id);

// Vermögen berechnen mit getTotalWealth() - gibt alle Werte zurück
$wealth_data = $db->getTotalWealth($user_id);

// Extrahiere die einzelnen Werte für die Anzeige
$total_income = $wealth_data['total_income'];
$total_income_all_time = $wealth_data['total_income'];
$total_expenses_all_time = $wealth_data['total_expenses'];
$total_expenses = $wealth_data['total_expenses'];
$total_debt_in = $wealth_data['total_debt_in'];
$total_debt_out = $wealth_data['total_debt_out'];
$net_debt_position = $wealth_data['net_debt_position'];
$total_investment_value = $wealth_data['total_investments'];
$total_balance_with_investments = $wealth_data['total_wealth'];

// Form-Verarbeitung
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // NEUEN USER ERSTELLEN (nur für Admins)
    if (isset($_POST['create_user']) && canCreateUsers($currentUser)) {
        $new_username = trim($_POST['new_username'] ?? '');
        $new_email = trim($_POST['new_email'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $new_full_name = trim($_POST['new_full_name'] ?? '');
        $new_role = $_POST['new_role'] ?? 'user';
        $new_starting_balance = floatval($_POST['new_starting_balance'] ?? 0);

        // Validierung
        if (empty($new_username)) {
            $errors[] = 'Benutzername für neuen User erforderlich.';
        }
        if (empty($new_email)) {
            $errors[] = 'E-Mail für neuen User erforderlich.';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ungültige E-Mail-Adresse.';
        }
        if (empty($new_password)) {
            $errors[] = 'Passwort für neuen User erforderlich.';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'Passwort muss mindestens 6 Zeichen lang sein.';
        }

        if (empty($errors)) {
            // Registrierung über Auth-Klasse
            $result = $auth->register([
                'username' => $new_username,
                'email' => $new_email,
                'password' => $new_password,
                'full_name' => $new_full_name,
                'role' => $new_role,
                'starting_balance' => $new_starting_balance
            ]);

            if ($result['success']) {
                $success_messages[] = "User '{$new_username}' erfolgreich erstellt!";
            } else {
                $errors[] = $result['message'];
            }
        }
    }

    // PROFIL UPDATE (für alle Rollen erlaubt)
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');

        if (empty($username) || empty($email)) {
            $errors[] = 'Benutzername und E-Mail sind erforderlich.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ungültige E-Mail-Adresse.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ? WHERE id = ?");
                $stmt->execute([$username, $email, $full_name, $user_id]);
                $success_messages[] = 'Profil erfolgreich aktualisiert!';

                // Session updaten
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;

                // CurrentUser neu laden
                $currentUser = $auth->getCurrentUser();
            } catch (Exception $e) {
                $errors[] = 'Fehler beim Aktualisieren des Profils.';
            }
        }
    }

    // PASSWORT ÄNDERN (für alle Rollen erlaubt)
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $errors[] = 'Alle Passwortfelder sind erforderlich.';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'Die neuen Passwörter stimmen nicht überein.';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'Das neue Passwort muss mindestens 6 Zeichen lang sein.';
        } else {
            // Prüfe aktuelles Passwort
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (!password_verify($current_password, $user['password'])) {
                $errors[] = 'Das aktuelle Passwort ist falsch.';
            } else {
                // Update Passwort
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$new_hash, $user_id]);
                $success_messages[] = 'Passwort erfolgreich geändert!';
            }
        }
    }



    // Zeitzone Update verarbeiten
    if (isset($_POST['update_timezone'])) {
        $new_timezone = $_POST['timezone'] ?? '';

        // Setze als MANUELLE Einstellung (true Flag)
        if (TimezoneHelper::setTimezone($new_timezone, true)) {
            $success_messages[] = 'Zeitzone erfolgreich aktualisiert und als persönliche Einstellung gespeichert!';
        } else {
            $errors[] = 'Ungültige Zeitzone ausgewählt.';
        }
    }

    // Zeitzone auf Automatik zurücksetzen
    if (isset($_POST['reset_timezone'])) {
        if (TimezoneHelper::resetToAutomatic($_SESSION['user_id'])) {
            unset($_SESSION['timezone_manually_set']);
            $success_messages[] = 'Zeitzone wurde auf automatische Erkennung zurückgesetzt!';
        }
    }

    // STARTKAPITAL UPDATE (nur für Admins)
    if (isset($_POST['update_balance']) && canEditStartingBalance($currentUser)) {
        $new_starting_balance = floatval($_POST['starting_balance'] ?? 0);

        if ($new_starting_balance < 0) {
            $errors[] = 'Startkapital kann nicht negativ sein.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET starting_balance = ? WHERE id = ?");
                $stmt->execute([$new_starting_balance, $user_id]);

                $success_messages[] = 'Startkapital auf €' . number_format($new_starting_balance, 2, ',', '.') . ' aktualisiert!';
                $current_starting_balance = $new_starting_balance;

                // Vermögen neu berechnen
                $cash_balance = $current_starting_balance + $total_income - $total_expenses;
                $total_balance_with_investments = $cash_balance + $total_investment_value - $net_debt_position;
            } catch (Exception $e) {
                $errors[] = 'Fehler beim Aktualisieren des Startkapitals.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einstellungen - Meine Firma Finance</title>
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-..."
        crossorigin="anonymous"
        referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/settings.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
</head>

<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a class="sidebar-logo">
                    <img src="/assets/images/logo.png" alt="Meine Firma Finance Logo" class="sidebar-logo-image">
                </a>
                <p class="sidebar-welcome">Willkommen, <?= htmlspecialchars($currentUser['full_name'] ?? $currentUser['username']) ?></p>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="dashboard.php"><i class="fa-solid fa-house"></i>&nbsp;&nbsp;Dashboard</a></li>

                    <?php if (canAccessModules($currentUser)): ?>
                        <li><a href="modules/expenses/index.php"><i class="fa-solid fa-money-bill-wave"></i>&nbsp;&nbsp;Ausgaben</a></li>
                        <li><a href="modules/income/index.php"><i class="fa-solid fa-sack-dollar"></i>&nbsp;&nbsp;Einnahmen</a></li>
                        <li><a href="modules/debts/index.php"><i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden</a></li>
                        <li><a href="modules/recurring/index.php"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend</a></li>
                        <li><a href="modules/investments/index.php"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto</a></li>
                        <li><a href="modules/categories/index.php"><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorien</a></li>
                    <?php endif; ?>
                    <li>
                        <a style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;" href="settings.php" class="active">
                            <i class="fa-solid fa-gear"></i>&nbsp;&nbsp;Einstellungen
                        </a>
                    </li>
                    <li><a href="modules/settings/license.php"><i class="fas fa-key"></i>&nbsp;&nbsp;Lizenz</a></li>
                    <li>
                        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i>&nbsp;&nbsp;Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;"><i class="fa-solid fa-gear"></i> Einstellungen</h1>
                    <p style="color: var(--clr-surface-a50);">Verwalte dein Startkapital und Kontoeinstellungen</p>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_messages)): ?>
                <div class="alert alert-success">
                    <?php foreach ($success_messages as $message): ?>
                        <p><?= htmlspecialchars($message) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Vermögensübersicht - KOMPLETT REPARIERT -->
            <div class="settings-card" style="grid-column: 1 / -1; background: linear-gradient(135deg, var(--clr-surface-a10) 0%, var(--clr-surface-tonal-a10) 100%);">
                <div class="settings-header card-header">
                    <h2><i class="fa-solid fa-dollar"></i>&nbsp;&nbsp;Vermögensübersicht</h2>

                </div>

                <div class="current-balance">
                    <div class="balance-amount <?= $total_balance_with_investments >= 0 ? 'positive' : 'negative' ?>">
                        €<?= number_format($total_balance_with_investments, 2, ',', '.') ?>
                    </div>
                    <div class="balance-label">Gesamtvermögen (inkl. Investments & Schulden)</div>
                </div>

                <div class="stats-overview">
                    <div class="stat-item">
                        <div class="stat-value neutral">€<?= number_format($current_starting_balance, 2, ',', '.') ?></div>
                        <div class="stat-label">Startkapital</div>
                    </div>

                    <div class="stat-item">
                        <div class="stat-value income">+€<?= number_format($total_income_all_time, 2, ',', '.') ?></div>
                        <div class="stat-label">Gesamt Einnahmen</div>
                    </div>

                    <div class="stat-item">
                        <div class="stat-value expense">-€<?= number_format($total_expenses_all_time, 2, ',', '.') ?></div>
                        <div class="stat-label">Gesamt Ausgaben</div>
                    </div>

                    <div class="stat-item">
                        <div class="stat-value <?= ($total_income_all_time - $total_expenses_all_time) >= 0 ? 'income' : 'expense' ?>">
                            <?= ($total_income_all_time - $total_expenses_all_time) >= 0 ? '+' : '' ?>€<?= number_format($total_income_all_time - $total_expenses_all_time, 2, ',', '.') ?>
                        </div>
                        <div class="stat-label">Bilanz (Ein-/Ausgaben)</div>
                    </div>

                    <!-- NEU: Schulden-Anzeige -->
                    <?php if ($total_debt_in > 0 || $total_debt_out > 0): ?>
                        <div class="stat-item">
                            <div class="stat-value income">+€<?= number_format($total_debt_in, 2, ',', '.') ?></div>
                            <div class="stat-label">Erhaltenes Geld</div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-value expense">-€<?= number_format($total_debt_out, 2, ',', '.') ?></div>
                            <div class="stat-label">Verliehenes Geld</div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-value <?= $net_debt_position >= 0 ? 'debt-positive' : 'debt-negative' ?>">
                                <?= $net_debt_position >= 0 ? '+' : '' ?>€<?= number_format($net_debt_position, 2, ',', '.') ?>
                            </div>
                            <div class="stat-label">Netto Schulden-Position</div>
                        </div>
                    <?php endif; ?>

                    <!-- NEU: Investment-Werte anzeigen -->
                    <?php if ($total_investment_value > 0): ?>
                        <div class="stat-item">
                            <div class="stat-value income">+€<?= number_format($total_investment_value, 2, ',', '.') ?></div>
                            <div class="stat-label">Investments</div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="info-box">
                    <div class="info-title">📈 Berechnung</div>
                    <div class="info-text">
                        <strong>Gesamtvermögen = Startkapital + Einnahmen - Ausgaben + Erhaltenes Geld - Verliehenes Geld + Investments</strong><br>
                        €<?= number_format($current_starting_balance, 2, ',', '.') ?> +
                        €<?= number_format($total_income_all_time, 2, ',', '.') ?> -
                        €<?= number_format($total_expenses_all_time, 2, ',', '.') ?>
                        <?php if ($total_debt_in > 0): ?> + €<?= number_format($total_debt_in, 2, ',', '.') ?><?php endif; ?>
                            <?php if ($total_debt_out > 0): ?> - €<?= number_format($total_debt_out, 2, ',', '.') ?><?php endif; ?>
                                <?php if ($total_investment_value > 0): ?> + €<?= number_format($total_investment_value, 2, ',', '.') ?><?php endif; ?> =
                                    €<?= number_format($total_balance_with_investments, 2, ',', '.') ?>
                    </div>
                </div>
            </div>

            <div class="settings-grid">
                <!-- Profil-Einstellungen -->
                <div class="settings-card">
                    <div class="settings-header">
                        <h2><i class="fa-solid fa-user"></i>&nbsp;&nbsp;Profil-Einstellungen</h2>
                        <p>Bearbeite deine persönlichen Daten</p>
                    </div>
                    <form method="POST" class="settings-form">
                        <div class="form-group">
                            <label class="form-label" for="username">Benutzername</label>
                            <input type="text" id="username" name="username" class="form-input"
                                value="<?= htmlspecialchars($currentUser['username']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">E-Mail</label>
                            <input type="email" id="email" name="email" class="form-input"
                                value="<?= htmlspecialchars($currentUser['email']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="full_name">Vollständiger Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-input"
                                value="<?= htmlspecialchars($currentUser['full_name'] ?? '') ?>">
                        </div>

                        <?php if ($currentUser['role'] === 'admin'): ?>
                            <div class="form-group">
                                <label class="form-label">Rolle</label>
                                <div style="padding: 10px; background: var(--clr-surface-a20); border-radius: 6px; color: #fbbf24;">
                                    <i class="fa-solid fa-crown"></i> Administrator
                                </div>
                            </div>
                        <?php endif; ?>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fa-solid fa-save"></i>&nbsp;&nbsp;Profil speichern
                        </button>
                    </form>
                </div>

                <!-- Passwort ändern -->
                <div class="settings-card">
                    <div class="settings-header">
                        <h2><i class="fa-solid fa-lock"></i>&nbsp;&nbsp;Passwort ändern</h2>
                        <p>Aktualisiere dein Passwort</p>
                    </div>
                    <form method="POST" class="settings-form">
                        <div class="form-group">
                            <label class="form-label" for="current_password">Aktuelles Passwort</label>
                            <input type="password" id="current_password" name="current_password" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="new_password">Neues Passwort</label>
                            <input type="password" id="new_password" name="new_password" class="form-input"
                                minlength="6" required>
                            <small style="color: var(--clr-surface-a50);">Mindestens 6 Zeichen</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Passwort bestätigen</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fa-solid fa-key"></i>&nbsp;&nbsp;Passwort ändern
                        </button>
                    </form>
                </div>

                <?php if (canEditStartingBalance($currentUser)): ?>
                    <!-- Startkapital (nur für Admins) -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h2><i class="fa-solid fa-coins"></i>&nbsp;&nbsp;Startkapital</h2>
                            <p>Definiere dein anfängliches Guthaben</p>
                        </div>
                        <form method="POST" class="settings-form">
                            <div class="form-group">
                                <label class="form-label" for="starting_balance">Startkapital (€)</label>
                                <input type="number" id="starting_balance" name="starting_balance"
                                    class="form-input" step="0.01"
                                    value="<?= number_format($current_starting_balance, 2, '.', '') ?>" required>
                                <small style="color: var(--clr-surface-a50);">
                                    Dieses Kapital wird zu deinem berechneten Vermögen hinzugefügt
                                </small>
                            </div>

                            <button type="submit" name="update_balance" class="btn btn-primary">
                                <i class="fa-solid fa-save"></i>&nbsp;&nbsp;Startkapital speichern
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if (canCreateUsers($currentUser)): ?>
                    <!-- Admin: Neuen User erstellen -->
                    <div class="settings-card" style="grid-column: 1 / -1;">
                        <div class="settings-header">
                            <h2 style="color: #fbbf24;"><i class="fa-solid fa-user-plus"></i>&nbsp;&nbsp;Neuen Benutzer erstellen (Admin)</h2>
                            <p>Erstelle neue Benutzerkonten für das System</p>
                        </div>
                        <form method="POST" class="settings-form">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                                <div class="form-group">
                                    <label class="form-label" for="new_username">Benutzername *</label>
                                    <input type="text" id="new_username" name="new_username" class="form-input" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="new_email">E-Mail *</label>
                                    <input type="email" id="new_email" name="new_email" class="form-input" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="new_password">Passwort *</label>
                                    <input type="password" id="new_password" name="new_password" class="form-input" minlength="6" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="new_full_name">Vollständiger Name</label>
                                    <input type="text" id="new_full_name" name="new_full_name" class="form-input">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="new_role">Rolle</label>
                                    <select id="new_role" name="new_role" class="form-select">
                                        <option value="user">Benutzer</option>
                                        <option value="admin">Administrator</option>
                                        <option value="viewer">Viewer</option>
                                    </select>
                                </div>

                                <!-- <div class="form-group">
                                    <label class="form-label" for="new_starting_balance">Startkapital (€)</label>
                                    <input type="number" id="new_starting_balance" name="new_starting_balance"
                                        class="form-input" step="0.01" value="0">
                                </div> -->
                            </div>

                            <button type="submit" name="create_user" class="btn btn-primary" style="background: #fbbf24; margin-top: 10px;">
                                <i class="fa-solid fa-user-plus"></i>&nbsp;&nbsp;Benutzer erstellen
                            </button>
                        </form>
                    </div>

                    <!-- Admin: User-Liste -->
                    <div class="settings-card" style="grid-column: 1 / -1;">
                        <div class="settings-header">
                            <h2 style="color: #fbbf24;"><i class="fa-solid fa-users"></i>&nbsp;&nbsp;Benutzerverwaltung</h2>
                            <p>Übersicht aller registrierten Benutzer</p>
                        </div>

                        <?php
                        // Alle User laden
                        $stmt = $pdo->prepare("SELECT id, username, email, full_name, role, is_active, last_login, created_at FROM users ORDER BY created_at DESC");
                        $stmt->execute();
                        $all_users = $stmt->fetchAll();
                        ?>

                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="border-bottom: 2px solid var(--clr-surface-a20);">
                                        <th style="padding: 12px; text-align: left; color: var(--clr-surface-a50);">Benutzername</th>
                                        <th style="padding: 12px; text-align: left; color: var(--clr-surface-a50);">E-Mail</th>
                                        <th style="padding: 12px; text-align: left; color: var(--clr-surface-a50);">Name</th>
                                        <th style="padding: 12px; text-align: center; color: var(--clr-surface-a50);">Rolle</th>
                                        <th style="padding: 12px; text-align: center; color: var(--clr-surface-a50);">Status</th>
                                        <!-- <th style="padding: 12px; text-align: center; color: var(--clr-surface-a50);">Startkapital</th> -->
                                        <th style="padding: 12px; text-align: left; color: var(--clr-surface-a50);">Letzter Login</th>
                                        <th style="padding: 12px; text-align: center; color: var(--clr-surface-a50);">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_users as $user): ?>
                                        <?php
                                        $isCurrentUser = ($user['id'] == $currentUser['id']);
                                        $isLastAdmin = ($user['role'] === 'admin' && $admin_count <= 1);
                                        $canDelete = !$isCurrentUser && !$isLastAdmin;
                                        ?>
                                        <tr style="border-bottom: 1px solid var(--clr-surface-a10); <?= $isCurrentUser ? 'background: rgba(34, 197, 94, 0.1);' : '' ?>">
                                            <td style="padding: 12px;">
                                                <?= htmlspecialchars($user['username']) ?>
                                                <?php if ($isCurrentUser): ?>
                                                    <span style="color: #22c55e; font-size: 0.8em;"> (Du)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 12px; color: var(--clr-surface-a70);">
                                                <?= htmlspecialchars($user['email']) ?>
                                            </td>
                                            <td style="padding: 12px; color: var(--clr-surface-a70);">
                                                <?= htmlspecialchars($user['full_name'] ?: '-') ?>
                                            </td>
                                            <td style="padding: 12px; text-align: center;">
                                                <?php if ($user['role'] === 'admin'): ?>
                                                    <span style="background: #fbbf24; color: #000; padding: 2px 8px; border-radius: 4px; font-size: 0.85em;">
                                                        <i class="fa-solid fa-crown"></i> Admin
                                                    </span>
                                                <?php elseif ($user['role'] === 'viewer'): ?>
                                                    <span style="background: #60a5fa; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 0.85em;">
                                                        <i class="fa-solid fa-eye"></i> Viewer
                                                    </span>
                                                <?php else: ?>
                                                    <span style="background: var(--clr-surface-a20); color: var(--clr-surface-a80); padding: 2px 8px; border-radius: 4px; font-size: 0.85em;">
                                                        <i class="fa-solid fa-user"></i> User
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 12px; text-align: center;">
                                                <?php if ($user['is_active']): ?>
                                                    <span style="color: #22c55e;">
                                                        <i class="fa-solid fa-circle-check"></i> Aktiv
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #ef4444;">
                                                        <i class="fa-solid fa-circle-xmark"></i> Inaktiv
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <!-- <td style="padding: 12px; text-align: center; color: var(--clr-surface-a70);">
                                                €<?= number_format($user['starting_balance'] ?? 0, 2, ',', '.') ?>
                                            </td> -->
                                            <td style="padding: 12px; color: var(--clr-surface-a70);">
                                                <?php if ($user['last_login']): ?>
                                                    <?= date('d.m.Y H:i', strtotime($user['last_login'])) ?>
                                                <?php else: ?>
                                                    <span style="color: var(--clr-surface-a50);">Noch nie</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 12px; text-align: center;">
                                                <?php if ($canDelete): ?>
                                                    <button onclick="confirmDeleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>')"
                                                        class="btn-delete-user"
                                                        style="background: #ef4444; color: white; border: none; padding: 4px 12px; border-radius: 4px; cursor: pointer; font-size: 0.85em;">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                <?php elseif ($isCurrentUser): ?>
                                                    <span style="color: var(--clr-surface-a50); font-size: 0.85em;">-</span>
                                                <?php elseif ($isLastAdmin): ?>
                                                    <span style="color: var(--clr-surface-a50); font-size: 0.75em;" title="Der letzte Admin kann nicht gelöscht werden">
                                                        <i class="fa-solid fa-lock"></i> Geschützt
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="info-box" style="margin-top: 20px; padding: 15px; background: var(--clr-surface-a05); border-radius: 8px;">
                            <p style="color: var(--clr-surface-a60); font-size: 0.9em; margin: 0;">
                                <i class="fa-solid fa-info-circle"></i>
                                <strong>Hinweise:</strong><br>
                                • Du kannst dich nicht selbst löschen<br>
                                • Der letzte Administrator kann nicht gelöscht werden<br>
                                • Beim Löschen eines Benutzers werden alle seine Daten (Transaktionen, Kategorien, etc.) unwiderruflich gelöscht
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3 style="color: var(--clr-primary-a20);">
                        <i class="fa-solid fa-globe"></i> Zeitzone-Einstellungen
                    </h3>
                </div>

                <form method="POST" style="padding: 20px;">
                    <!-- Status-Anzeige -->
                    <?php if ($is_manually_set): ?>
                        <div class="alert alert-info" style="margin-bottom: 20px;">
                            <i class="fa-solid fa-circle-info"></i>
                            <strong>Manuell eingestellt:</strong> Deine Zeitzone wird nicht automatisch geändert.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success" style="margin-bottom: 20px;">
                            <i class="fa-solid fa-check-circle"></i>
                            <strong>Automatische Erkennung aktiv:</strong> Die Zeitzone wird bei Login erkannt.
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label" for="timezone">Zeitzone</label>
                        <select name="timezone" id="timezone" class="form-input">
                            <?php foreach ($timezone_list as $region => $zones): ?>
                                <optgroup label="<?= htmlspecialchars($region) ?>">
                                    <?php foreach ($zones as $tz => $city): ?>
                                        <option value="<?= htmlspecialchars($tz) ?>"
                                            <?= $tz === $current_timezone ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($city) ?>
                                            <?php
                                            // Zeige aktuelle Zeit in dieser Zeitzone
                                            $testTime = new DateTime('now', new DateTimeZone($tz));
                                            echo '(' . $testTime->format('H:i') . ')';
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint">
                            Aktuelle Zeit in deiner Zeitzone:
                            <strong><?= TimezoneHelper::getCurrentUserTime('d.m.Y H:i:s') ?></strong>
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Browser-Zeitzone</label>
                        <div class="info-box" style="background: var(--clr-surface-tonal-a10); padding: 15px; border-radius: 8px;">
                            <p style="margin: 0;">
                                <strong>Erkannte Browser-Zeitzone:</strong>
                                <span id="detected-timezone">Wird ermittelt...</span>
                            </p>
                            <?php if ($is_manually_set): ?>
                                <p style="margin: 10px 0 0 0; color: var(--clr-surface-a50);">
                                    <i class="fa-solid fa-lock"></i>
                                    Die automatische Erkennung ist deaktiviert, da du eine manuelle Einstellung vorgenommen hast.
                                </p>
                            <?php else: ?>
                                <p style="margin: 10px 0 0 0; color: var(--clr-surface-a50);">
                                    <i class="fa-solid fa-sync-alt"></i>
                                    Bei jedem Login wird deine Browser-Zeitzone erkannt und automatisch verwendet.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-actions" style="display: flex; gap: 10px;">
                        <button type="submit" name="update_timezone" class="btn btn-primary">
                            <i class="fa-solid fa-save"></i>&nbsp;&nbsp;Zeitzone speichern
                        </button>

                        <?php if ($is_manually_set): ?>
                            <button type="submit" name="reset_timezone" class="btn btn-secondary"
                                onclick="return confirm('Möchtest du wirklich zur automatischen Erkennung zurückkehren?');">
                                <i class="fa-solid fa-undo"></i>&nbsp;&nbsp;Automatik aktivieren
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Zeige erkannte Browser-Zeitzone
            const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            const detectedElement = document.getElementById('detected-timezone');
            detectedElement.textContent = browserTimezone;

            // Prüfe ob Browser-Zeitzone mit aktueller übereinstimmt
            const currentTimezone = '<?= $current_timezone ?>';
            const isManuallySet = <?= $is_manually_set ? 'true' : 'false' ?>;

            if (browserTimezone === currentTimezone) {
                detectedElement.innerHTML = browserTimezone + ' <span style="color: #22c55e;">(✓ Stimmt überein)</span>';
            } else if (isManuallySet) {
                detectedElement.innerHTML = browserTimezone + ' <span style="color: #f97316;">(≠ Weicht von deiner Einstellung ab)</span>';
            } else {
                detectedElement.innerHTML = browserTimezone + ' <span style="color: #3b82f6;">(Wird beim nächsten Login verwendet)</span>';
            }
        });

        // Wenn Zeitzone manuell geändert wird, sende mit force-Flag
        document.querySelector('form').addEventListener('submit', function(e) {
            if (e.submitter && e.submitter.name === 'update_timezone') {
                // Dies wird als manuelle Einstellung gespeichert
                console.log('Manuelle Zeitzone-Einstellung wird gespeichert...');
            }
        });

        function confirmDeleteUser(userId, username) {
            if (confirm(`Bist du sicher, dass du den Benutzer "${username}" löschen möchtest?\n\nDiese Aktion kann nicht rückgängig gemacht werden und löscht ALLE Daten dieses Benutzers!`)) {
                window.location.href = `/includes/delete_user.php?id=${userId}`;
            }
        }
    </script>
</body>

</html>