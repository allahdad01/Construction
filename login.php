<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Check if user is already logged in
if (isAuthenticated()) {
    header('Location: public/dashboard/');
    exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    try {
        if (empty($email) || empty($password)) {
            throw new Exception('Please enter both email and password.');
        }
        
        $db = new Database();
        $conn = $db->getConnection();
        
        // Get user by email
        $stmt = $conn->prepare("
            SELECT u.*, c.company_name, c.subscription_status, c.trial_end_date 
            FROM users u 
            LEFT JOIN companies c ON u.company_id = c.id 
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Check if company is active
            if ($user['company_id'] && $user['subscription_status'] === 'inactive') {
                throw new Exception('Your company subscription has expired. Please contact your administrator.');
            }
            
            // Check if trial has expired
            if ($user['trial_end_date'] && $user['trial_end_date'] < date('Y-m-d')) {
                throw new Exception('Your trial period has expired. Please upgrade your subscription.');
            }
            
            // Start session and store user data
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['company_id'] = $user['company_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            
            // Set remember me cookie if requested
            if ($remember_me) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/'); // 30 days
                
                // Store token in database
                $stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $stmt->execute([$token, $user['id']]);
            }
            
            // Log successful login
            $stmt = $conn->prepare("
                INSERT INTO user_logs (user_id, action, ip_address, user_agent) 
                VALUES (?, 'login', ?, ?)
            ");
            $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            
            $success = 'Login successful! Redirecting...';
            
            // Redirect based on role
            header('Location: public/dashboard/');
            exit;
            
        } else {
            throw new Exception('Invalid email or password.');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get system settings for branding
$db = new Database();
$conn = $db->getConnection();

function getSystemSetting($conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : $default;
}

$platform_name = getSystemSetting($conn, 'platform_name', 'Construction SaaS Platform');
$platform_description = getSystemSetting($conn, 'platform_description', 'Comprehensive construction management platform');
$platform_logo = getSystemSetting($conn, 'platform_logo', '');
$primary_color = getSystemSetting($conn, 'primary_color', '#4e73df');
$secondary_color = getSystemSetting($conn, 'secondary_color', '#858796');
$accent_color = getSystemSetting($conn, 'accent_color', '#1cc88a');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($platform_name); ?> - Login</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: <?php echo $primary_color; ?>;
            --secondary-color: <?php echo $secondary_color; ?>;
            --accent-color: <?php echo $accent_color; ?>;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --white-color: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --border-radius: 0.5rem;
            --border-radius-lg: 0.75rem;
            --border-radius-xl: 1rem;
            --box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --box-shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --box-shadow-xl: 0 1rem 3rem rgba(0, 0, 0, 0.175);
            --transition: all 0.15s ease-in-out;
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }

        /* Background Animation */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        /* Login Container */
        .login-container {
            background: white;
            border-radius: var(--border-radius-xl);
            box-shadow: var(--box-shadow-xl);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
            position: relative;
            z-index: 10;
            animation: slideInUp 0.6s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Login Header */
        .login-header {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .login-logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: white;
            backdrop-filter: blur(10px);
        }

        .login-logo img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .login-subtitle {
            font-size: 0.875rem;
            opacity: 0.9;
            margin: 0.5rem 0 0;
            position: relative;
            z-index: 1;
        }

        /* Login Form */
        .login-form {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            border-radius: var(--border-radius);
            border: 2px solid var(--gray-200);
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: var(--transition);
            background: white;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
            outline: none;
        }

        .form-control.is-invalid {
            border-color: var(--danger-color);
        }

        .invalid-feedback {
            color: var(--danger-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .input-group {
            position: relative;
        }

        .input-group-text {
            background: var(--gray-100);
            border: 2px solid var(--gray-200);
            border-right: none;
            color: var(--gray-600);
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius) 0 0 var(--border-radius);
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
        }

        .input-group .form-control:focus {
            border-left: none;
        }

        .input-group .form-control:focus + .input-group-text {
            border-color: var(--primary-color);
        }

        /* Buttons */
        .btn {
            border-radius: var(--border-radius);
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            transition: var(--transition);
            border: none;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4a5fd8, #5a6fd8);
            transform: translateY(-1px);
            box-shadow: var(--box-shadow-lg);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        /* Checkbox */
        .form-check {
            margin-bottom: 1rem;
        }

        .form-check-input {
            border-radius: 0.25rem;
            border: 2px solid var(--gray-300);
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-label {
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        /* Alerts */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .alert-success {
            background: linear-gradient(135deg, var(--success-color), #20c997);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, var(--danger-color), #e74c3c);
            color: white;
        }

        /* Links */
        .login-links {
            text-align: center;
            margin-top: 1.5rem;
        }

        .login-links a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .login-links a:hover {
            color: #4a5fd8;
            text-decoration: underline;
        }

        /* Divider */
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--gray-200);
        }

        .divider span {
            background: white;
            padding: 0 1rem;
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        /* Social Login */
        .social-login {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn-social {
            flex: 1;
            padding: 0.75rem;
            border-radius: var(--border-radius);
            border: 2px solid var(--gray-200);
            background: white;
            color: var(--gray-700);
            text-decoration: none;
            text-align: center;
            transition: var(--transition);
            font-size: 0.875rem;
        }

        .btn-social:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-1px);
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 576px) {
            .login-container {
                margin: 1rem;
                max-width: none;
            }

            .login-header {
                padding: 1.5rem;
            }

            .login-form {
                padding: 1.5rem;
            }

            .login-title {
                font-size: 1.25rem;
            }
        }

        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            .login-container {
                background: var(--gray-800);
                color: var(--gray-100);
            }

            .form-control {
                background: var(--gray-700);
                border-color: var(--gray-600);
                color: var(--gray-100);
            }

            .form-control:focus {
                background: var(--gray-700);
            }

            .form-check-label {
                color: var(--gray-300);
            }
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-200);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray-400);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-500);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Login Header -->
        <div class="login-header">
            <div class="login-logo">
                <?php if ($platform_logo): ?>
                    <img src="<?php echo htmlspecialchars($platform_logo); ?>" alt="Logo">
                <?php else: ?>
                    <i class="fas fa-building"></i>
                <?php endif; ?>
            </div>
            <h1 class="login-title"><?php echo htmlspecialchars($platform_name); ?></h1>
            <p class="login-subtitle"><?php echo htmlspecialchars($platform_description); ?></p>
        </div>

        <!-- Login Form -->
        <div class="login-form">
            <?php if ($error): ?>
                <div class="alert alert-danger fade-in">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success fade-in">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope me-2"></i>Email Address
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               required autocomplete="email" autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" class="form-control" id="password" name="password" 
                               required autocomplete="current-password">
                        <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                    <label class="form-check-label" for="remember_me">
                        Remember me for 30 days
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" id="loginBtn">
                    <span class="btn-text">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </span>
                    <span class="btn-loading" style="display: none;">
                        <span class="loading"></span> Signing in...
                    </span>
                </button>
            </form>

            <div class="login-links">
                <a href="forgot-password.php">
                    <i class="fas fa-key me-1"></i>Forgot your password?
                </a>
            </div>

            <div class="divider">
                <span>or</span>
            </div>

            <div class="social-login">
                <a href="#" class="btn-social">
                    <i class="fab fa-google me-2"></i>Google
                </a>
                <a href="#" class="btn-social">
                    <i class="fab fa-microsoft me-2"></i>Microsoft
                </a>
            </div>

            <div class="login-links mt-3">
                <span class="text-muted">Don't have an account?</span>
                <a href="register.php" class="ms-2">
                    <i class="fas fa-user-plus me-1"></i>Sign up
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const btnText = loginBtn.querySelector('.btn-text');
            const btnLoading = loginBtn.querySelector('.btn-loading');
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            // Password toggle functionality
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                const icon = this.querySelector('i');
                icon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
            });

            // Form submission with loading state
            loginForm.addEventListener('submit', function(e) {
                // Show loading state
                btnText.style.display = 'none';
                btnLoading.style.display = 'inline-block';
                loginBtn.disabled = true;

                // Simulate loading delay (remove in production)
                setTimeout(() => {
                    // Form will submit normally
                }, 1000);
            });

            // Enhanced form validation
            const inputs = loginForm.querySelectorAll('input[required]');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    validateField(this);
                });

                input.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid')) {
                        validateField(this);
                    }
                });
            });

            function validateField(field) {
                const value = field.value.trim();
                let isValid = true;
                let errorMessage = '';

                // Remove existing error styling
                field.classList.remove('is-invalid');
                const existingError = field.parentNode.querySelector('.invalid-feedback');
                if (existingError) {
                    existingError.remove();
                }

                // Email validation
                if (field.type === 'email' && value) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        isValid = false;
                        errorMessage = 'Please enter a valid email address.';
                    }
                }

                // Required field validation
                if (field.hasAttribute('required') && !value) {
                    isValid = false;
                    errorMessage = 'This field is required.';
                }

                // Password validation
                if (field.type === 'password' && value) {
                    if (value.length < 6) {
                        isValid = false;
                        errorMessage = 'Password must be at least 6 characters long.';
                    }
                }

                // Apply validation result
                if (!isValid) {
                    field.classList.add('is-invalid');
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.textContent = errorMessage;
                    field.parentNode.appendChild(errorDiv);
                }
            }

            // Enhanced accessibility
            document.addEventListener('keydown', function(e) {
                // Enter key to submit form
                if (e.key === 'Enter' && document.activeElement.tagName === 'INPUT') {
                    const form = document.activeElement.closest('form');
                    if (form && form.checkValidity()) {
                        form.submit();
                    }
                }
            });

            // Auto-focus on email field
            const emailInput = document.getElementById('email');
            if (emailInput && !emailInput.value) {
                emailInput.focus();
            }

            // Enhanced error handling
            window.addEventListener('error', function(e) {
                console.error('JavaScript Error:', e.error);
                showNotification('An error occurred. Please try again.', 'error');
            });

            // Notification system
            window.showNotification = function(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
                notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                notification.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                document.body.appendChild(notification);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 5000);
            };

            // Add shake animation to form on error
            const alerts = document.querySelectorAll('.alert-danger');
            alerts.forEach(alert => {
                if (alert.textContent.includes('Invalid') || alert.textContent.includes('error')) {
                    loginForm.classList.add('shake');
                    setTimeout(() => {
                        loginForm.classList.remove('shake');
                    }, 500);
                }
            });

            console.log('Enhanced login page loaded successfully!');
        });
    </script>
</body>
</html>