<?php
/**
 * Login Page
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $error = 'Security token expired. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } elseif (!isValidEmail($email)) {
            $error = 'Please enter a valid email.';
        } else {
            $result = loginUser($email, $password);
            if ($result['success']) {
                header('Location: dashboard.php');
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>
<body class="auth-page theme-dark">
    <canvas id="three-bg" class="three-background"></canvas>
    
    <div class="auth-container">
        <div class="auth-card" id="authCard">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <h1><?= APP_NAME ?></h1>
                <p>Personal Financial Command Center</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert-custom alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= e($error) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                
                <div class="form-group-custom">
                    <label for="email">Email Address</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" 
                               value="<?= e($email) ?>"
                               placeholder="Enter your email" required
                               autocomplete="email">
                    </div>
                </div>
                
                <div class="form-group-custom">
                    <label for="password">Password</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" 
                               placeholder="Enter your password" required
                               autocomplete="current-password">
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary-custom btn-full">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php">Create Account</a></p>
            </div>
            
            <div class="demo-credentials">
                <p><strong>Demo Account:</strong></p>
                <p>Email: demo@myfinance.app</p>
                <p>Password: demo123</p>
            </div>
        </div>
    </div>
    
    <script src="assets/js/three-bg.js"></script>
    <script>
        function togglePassword() {
            const p = document.getElementById('password');
            const icon = document.querySelector('.password-toggle i');
            if (p.type === 'password') { p.type = 'text'; icon.classList.replace('fa-eye','fa-eye-slash'); }
            else { p.type = 'password'; icon.classList.replace('fa-eye-slash','fa-eye'); }
        }
        // GSAP entrance
        gsap.from('#authCard', { y: 60, opacity: 0, duration: 1, ease: 'power3.out' });
    </script>
</body>
</html>