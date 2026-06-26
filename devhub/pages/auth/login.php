<?php
require_once __DIR__ . '/../../includes/config.php';
requireFeatureAccess('devhub_auth');
$redirect = urlencode(BASE_URI . '/devhub/index.php');
header('Location: ' . BASE_URL . '/auth/auth.php?redirect=' . $redirect . '&tab=login');
exit();
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | DevHub - CoinRex</title>
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/auth.css">
</head>
<body>
<main class="auth-main">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">
                <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="CoinRex" class="auth-logo-img">
                <h2>DevHub</h2>
                <p class="auth-tagline">Project Owner Portal</p>
            </div>
            
            <?php if($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="input-group">
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="input-field">
                        <div class="input-control">
                            <input type="email" name="email" id="loginEmail" placeholder="your@email.com" required>
                            <label for="loginEmail">Email Address</label>
                            <span class="input-border"></span>
                        </div>
                    </div>
                </div>
                
                <div class="input-group">
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="input-field">
                        <div class="input-control">
                            <input type="password" name="password" id="loginPassword" placeholder="Enter your password" required>
                            <label for="loginPassword">Password</label>
                            <span class="input-border"></span>
                            <button type="button" class="password-toggle" onclick="togglePassword('loginPassword', 'loginToggleIcon')">
                                <i class="fas fa-eye" id="loginToggleIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="auth-submit">
                    <span>Sign In</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>
            
            <div class="auth-links">
                Don't have an account? <a href="register.php">Register as Project Owner</a>
            </div>
            
            <div class="back-link">
                <a href="<?php echo BASE_URL; ?>/index.php">
                    <i class="fas fa-arrow-left"></i> Back to CoinRex
                </a>
            </div>
        </div>
    </div>
</main>
    
    <script>
        function togglePassword(fieldId, iconId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(iconId);
            
            if (!passwordInput || !toggleIcon) {
                return;
            }
            
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
