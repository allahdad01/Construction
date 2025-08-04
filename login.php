<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: public/dashboard/');
    exit;
}

// Check for remember me token
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Get user by remember token
        $stmt = $conn->prepare("
            SELECT u.*, c.company_name, c.company_code, c.subscription_status, c.is_active as company_active
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            LEFT JOIN remember_tokens rt ON u.id = rt.user_id
            WHERE rt.token = ? AND rt.expires_at > NOW() AND u.is_active = TRUE
        ");
        $stmt->execute([$_COOKIE['remember_token']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['company_id'] = $user['company_id'];
            $_SESSION['company_name'] = $user['company_name'];
            $_SESSION['company_code'] = $user['company_code'];
            $_SESSION['last_activity'] = time();
            
            // Update last login
            $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Redirect based on role
            if ($user['role'] === 'super_admin') {
                header('Location: public/super-admin/');
            } else {
                header('Location: public/dashboard/');
            }
            exit;
        }
    } catch (Exception $e) {
        // Clear invalid remember token
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $db = new Database();
            $conn = $db->getConnection();

            // Get user by email
            $stmt = $conn->prepare("
                SELECT u.*, c.company_name, c.company_code, c.subscription_status, c.is_active as company_active
                FROM users u
                LEFT JOIN companies c ON u.company_id = c.id
                WHERE u.email = ? AND u.is_active = TRUE
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // Check if user's company is active
                if ($user['company_id'] && !$user['company_active']) {
                    $error = 'Your company account has been suspended. Please contact your administrator.';
                } else {
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['company_id'] = $user['company_id'];
                    $_SESSION['company_name'] = $user['company_name'];
                    $_SESSION['company_code'] = $user['company_code'];
                    $_SESSION['last_activity'] = time();

                    // Update last login
                    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);

                    // Set remember me cookie if requested
                    if ($remember_me) {
                        $token = bin2hex(random_bytes(32));
                        $expires_at = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days
                        
                        // Store token in database
                        $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                        $stmt->execute([$user['id'], $token, $expires_at]);
                        
                        // Set cookie
                        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                    }

                    // Redirect based on role
                    if ($user['role'] === 'super_admin') {
                        header('Location: public/super-admin/');
                    } else {
                        header('Location: public/dashboard/');
                    }
                    exit;
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (Exception $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Construction Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            min-height: 600px;
        }

        .login-sidebar {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
            padding: 3rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .login-form {
            padding: 3rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-control {
            border: 2px solid #e3e6f0;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(78, 115, 223, 0.3);
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .icon-large {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 2rem 0;
        }

        .feature-list li {
            padding: 0.5rem 0;
            display: flex;
            align-items: center;
        }

        .feature-list li i {
            margin-right: 0.5rem;
            color: rgba(255, 255, 255, 0.8);
        }

        @media (max-width: 768px) {
            .login-sidebar {
                display: none;
            }
            
            .login-form {
                padding: 2rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="row g-0 h-100">
            <!-- Sidebar -->
            <div class="col-lg-5">
                <div class="login-sidebar">
                    <div class="icon-large">
                        <i class="fas fa-hard-hat"></i>
                    </div>
                    <h2 class="mb-4">Construction Management System</h2>
                    <p class="mb-4">Streamline your construction operations with our comprehensive management platform.</p>
                    
                    <ul class="feature-list">
                        <li><i class="fas fa-check"></i> Employee & Machine Management</li>
                        <li><i class="fas fa-check"></i> Contract & Project Tracking</li>
                        <li><i class="fas fa-check"></i> Financial Reporting</li>
                        <li><i class="fas fa-check"></i> Real-time Analytics</li>
                        <li><i class="fas fa-check"></i> Multi-tenant Architecture</li>
                    </ul>
                </div>
            </div>

            <!-- Login Form -->
            <div class="col-lg-7">
                <div class="login-form">
                    <div class="text-center mb-4">
                        <h3 class="text-dark mb-2">Welcome Back!</h3>
                        <p class="text-muted">Sign in to your account to continue</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="loginForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       placeholder="Enter your email" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Enter your password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                            <label class="form-check-label" for="remember_me">
                                Remember me
                            </label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Sign In
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <p class="text-muted">
                            <a href="#" class="text-decoration-none">Forgot your password?</a>
                        </p>
                        <p class="text-muted">
                            Don't have an account? 
                            <a href="#" class="text-decoration-none">Contact your administrator</a>
                        </p>
                    </div>

                    <div class="text-center mt-4">
                        <small class="text-muted">
                            &copy; 2024 Construction Management System. All rights reserved.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle password visibility
            const togglePassword = document.getElementById('togglePassword');
            const password = document.getElementById('password');

            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });

            // Form validation
            const form = document.getElementById('loginForm');
            form.addEventListener('submit', function(e) {
                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value.trim();

                if (!email || !password) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });

            // Auto-focus on email field
            document.getElementById('email').focus();
        });
    </script>
</body>
</html>