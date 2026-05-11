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
    <link rel="stylesheet" href="<?php echo ADMIN_BASE_URL; ?>/assets/css/admin.css">
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
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="admin@example.com" required>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="far fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="auth-btn">
                    <i class="fas fa-sign-in-alt"></i> Login to AdminHub
                </button>
            </form>
            
            <div class="back-link">
                <a href="<?php echo BASE_URL; ?>/index.php">
                    <i class="fas fa-arrow-left"></i> Back to CoinRex
                </a>
            </div>
        </div>
        
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
