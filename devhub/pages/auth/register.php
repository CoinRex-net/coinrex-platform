<?php
require_once __DIR__ . '/../../includes/config.php';
$redirect = urlencode(BASE_URI . '/devhub/index.php');
header('Location: ' . BASE_URL . '/auth/auth.php?redirect=' . $redirect . '&tab=register');
exit();
?>
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    if (empty($full_name)) $errors[] = 'Full name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($username)) $errors[] = 'Username is required';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
    
    if (empty($errors)) {
        $db = getDevHubDB();
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already registered';
        }
        
        // Check if username exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = 'Username already taken';
        }
        
        // Also check in developer_verification table for existing pending/verified users
        $stmt = $db->prepare("SELECT id FROM developer_verification WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $errors[] = 'Email or username already has a verification record';
        }
    }
    
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $referral_code = strtoupper(substr(md5(uniqid()), 0, 8));
            
            // Start transaction
            $db->beginTransaction();
            
            // 1. Insert into users table
            $sql = "INSERT INTO users (full_name, email, username, password, referral_code, role, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'project_owner', 'active', NOW())";
            
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([$full_name, $email, $username, $hashed_password, $referral_code]);
            
            if ($result) {
                $user_id = $db->lastInsertId();
                
                // 2. Insert into developer_verification table
                $stmt2 = $db->prepare("
                    INSERT INTO developer_verification 
                    (user_id, full_name, email, username, password_hash, status, has_verified_badge, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'pending', 0, NOW())
                ");
                $stmt2->execute([$user_id, $full_name, $email, $username, $hashed_password]);
                
                // Commit transaction
                $db->commit();
                
                $success = 'Registration successful! You can now login.';
                
                // Auto redirect after 2 seconds
                echo '<script>setTimeout(function(){ window.location.href = "login.php"; }, 2000);</script>';
            } else {
                $db->rollBack();
                $error = 'Registration failed. Please try again.';
            }
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | DevHub - CoinRex</title>
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
                <p class="auth-tagline">Create your Project Owner Account</p>
            </div>
            
            <?php if($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                    <p style="margin-top: 10px; font-size: 12px;">Redirecting to login...</p>
                </div>
            <?php endif; ?>
            
            <?php if(!$success): ?>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="John Doe" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="johndoe" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="your@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder="Min 6 characters" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="far fa-eye" id="toggleIcon1"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="far fa-eye" id="toggleIcon2"></i>
                        </button>
                    </div>
                </div>
                
                    <button type="submit" class="auth-submit">
                    <span>Create Account</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>
            <?php endif; ?>
            
            <div class="auth-links">
                Already have an account? <a href="login.php">Login to DevHub</a>
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
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(fieldId === 'password' ? 'toggleIcon1' : 'toggleIcon2');
            
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