<?php
require_once 'includes/auth.php';

// Wenn bereits eingeloggt, weiterleiten zum Dashboard
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';
if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

$success_message = '';
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Check for logout message
if (isset($_SESSION['logout_message'])) {
    $success_message = $_SESSION['logout_message'];
    unset($_SESSION['logout_message']);
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Meine Firma Finance</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--clr-surface-a0) 0%, var(--clr-surface-tonal-a0) 100%);
        }

        .login-card {
            background-color: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-title {
            color: var(--clr-primary-a20);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-subtitle {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background-color: rgba(248, 113, 113, 0.1);
            border: 1px solid #f87171;
            color: #fca5a5;
        }

        .alert-success {
            background-color: rgba(74, 222, 128, 0.1);
            border: 1px solid #4ade80;
            color: #86efac;
        }

        .btn-full {
            width: 100%;
            justify-content: center;
            margin-top: 10px;
        }

        .form-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--clr-surface-a20);
        }

        .form-footer p {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        /* Demo Info Box */
        .demo-info {
            background: var(--clr-surface-tonal-a10);
            border: 1px solid var(--clr-primary-a20);
            border-radius: 6px;
            padding: 12px;
            margin-top: 20px;
            font-size: 13px;
        }

        .demo-info-title {
            color: var(--clr-primary-a20);
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center !important;
            justify-content: center;
            gap: 6px;
        }

        .demo-credentials {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .demo-credential {
            text-align: center;
        }

        .demo-credential strong {
            color: var(--clr-primary-a20);
            display: block;
            margin-bottom: 2px;
        }

        .demo-credential span {
            color: var(--clr-surface-a50);
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <img src="assets/images/logo.png" alt="Meine Firma Finance Logo" class="login-logo-image">
                    <h1 class="login-title">Meine Firma Finance</h1>
                </div>
                <p class="login-subtitle">Dein persönlicher Finanztracker</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <!-- Login Form - OHNE Tabs -->
            <form action="auth/login.php" method="POST">
                <div class="form-group">
                    <label class="form-label" for="username">Benutzername</label>
                    <input type="text" id="username" name="username" class="form-input" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Passwort</label>
                    <input type="password" id="password" name="password" class="form-input" required>
                </div>

                <button type="submit" class="btn btn-full">Anmelden</button>
            </form>

            <!-- Demo Credentials -->
            <div class="demo-info">
                <div class="demo-info-title">
                    Demo-Zugangsdaten
                </div>
                <div class="demo-credentials">
                    <div class="demo-credential">
                        <strong>Admin</strong>
                        <span>admin / admin123</span>
                    </div>
                    <div class="demo-credential">
                        <strong>Demo User</strong>
                        <span>demo / demo123</span>
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <?php
                // Erweiterte Version - zeigt Startjahr bis aktuelles Jahr (falls unterschiedlich):
                $start_year = 2024; // oder wann auch immer du angefangen hast
                $current_year = date('Y');
                ?>
                <p>© <?= $start_year == $current_year ? $current_year : $start_year . ' - ' . $current_year ?> · Flammang Yves</p>
            </div>
        </div>
    </div>
</body>

</html>