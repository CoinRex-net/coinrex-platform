<?php
require_once __DIR__ . '/includes/config.php';

if (isAdminLoggedIn()) {
    header('Location: ' . ADMIN_BASE_URL . '/dashboard.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $token = (string) ($_POST['csrf_token'] ?? '');
    requireAdminCsrf($token);

    $result = adminLogin($email, $password);
    if (!empty($result['success'])) {
        header('Location: ' . ADMIN_BASE_URL . '/dashboard.php');
        exit();
    }
    $error_message = (string) ($result['message'] ?? 'Login failed');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | AdminHub - CoinRex</title>
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMIN_BASE_URL; ?>/assets/css/auth.css">
</head>
<body>

    <div class="auth-container">

        <!-- Header -->
        <div class="auth-header">
            <div class="logo-wrapper">
                <img src="<?php echo BASE_URL; ?>/assets/images/favicon.png" alt="CoinRex" class="logo-img">
                <div class="logo-text">
                    <h1>AdminHub</h1>
                    <span>Administration Panel</span>
                </div>
            </div>
            <p>CoinRex Admin Portal</p>
        </div>

        <!-- Login Card -->
        <div class="auth-card">

            <?php if ($error_message !== ''): ?>
                <div class="error-message" id="errorContainer">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                <!-- Email -->
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" id="email" placeholder="admin@example.com" required autocomplete="email">
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper password-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" class="password-toggle" id="passwordToggle" aria-label="Toggle password visibility">
                            <i class="far fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" id="rememberMe">
                        <span class="check-box"><i class="fas fa-check"></i></span>
                        <span class="check-label">Remember me</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="auth-btn" id="loginSubmitBtn">
                    <span class="btn-spinner" id="btnSpinner"></span>
                    <span id="btnText"><i class="fas fa-sign-in-alt"></i> Sign In</span>
                </button>

                <!-- Keyboard Hint -->
                <div class="keyboard-hint" id="keyboardHint">
                    Press <kbd>↵</kbd> Enter to login
                </div>
            </form>

        </div>

        <!-- Footer -->
        <div class="auth-footer">
            <p>&copy; <?php echo date('Y'); ?> CoinRex. All rights reserved.</p>
            <span class="version">AdminHub v2.0</span>
        </div>

    </div>

    <script src="<?php echo ADMIN_BASE_URL; ?>/assets/js/auth.js"></script>
</body>
</html>
