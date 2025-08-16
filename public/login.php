<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'super_admin') {
        header('Location: super-admin/');
    } elseif ($role === 'company_admin') {
        header('Location: admin/dashboard/');
    } elseif ($role === 'driver' || $role === 'driver_assistant') {
        header('Location: employee/dashboard/');
    } else {
        header('Location: dashboard/');
    }
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
                header('Location: super-admin/');
            } elseif ($user['role'] === 'company_admin') {
                header('Location: admin/dashboard/');
            } elseif ($user['role'] === 'driver' || $user['role'] === 'driver_assistant') {
                header('Location: employee/dashboard/');
            } else {
                header('Location: dashboard/');
            }
            exit;
        }
    } catch (Exception $e) {
        // Clear invalid remember token
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

// Fetch palette from system settings
$db2 = new Database();
$conn2 = $db2->getConnection();
function getSystemSettingLocal($conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['setting_value'] : $default;
}
$primary_color = getSystemSettingLocal($conn2, 'primary_color', '#243447');
$secondary_color = getSystemSettingLocal($conn2, 'secondary_color', '#222E3D');
$accent_color = getSystemSettingLocal($conn2, 'accent_color', '#F17300');
$favicon = getSystemSettingLocal($conn2, 'platform_favicon', '');
$platform_logo = getSystemSettingLocal($conn2, 'platform_logo', '');
$platform_name = getSystemSettingLocal($conn2, 'platform_name', 'Construction Management System');

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
                        header('Location: super-admin/');
                    } elseif ($user['role'] === 'company_admin') {
                        header('Location: admin/dashboard/');
                    } elseif ($user['role'] === 'driver' || $user['role'] === 'driver_assistant') {
                        header('Location: employee/dashboard/');
                    } else {
                        header('Location: dashboard/');
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
    <title>Login - <?php echo htmlspecialchars($platform_name); ?></title>
    <?php if (!empty($favicon)): ?>
    <link rel="icon" href="<?php echo $base_url; ?><?php echo htmlspecialchars($favicon); ?>">
    <link rel="shortcut icon" href="<?php echo $base_url; ?><?php echo htmlspecialchars($favicon); ?>">
    <?php endif; ?>
    <link rel="manifest" href="manifest.json">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($primary_color); ?>;
            --secondary-color: <?php echo htmlspecialchars($secondary_color); ?>;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #FFD54A;
            --danger-color: #e74a3b;
            --light-color: #F6F0E6;
            --dark-color: <?php echo htmlspecialchars($secondary_color); ?>;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(1200px 600px at -10% -10%, rgba(255, 213, 74, 0.15), transparent 60%),
                        radial-gradient(1200px 600px at 110% 110%, rgba(241, 115, 0, 0.12), transparent 60%),
                        linear-gradient(135deg, var(--primary-color) 0%, <?php echo htmlspecialchars($accent_color); ?> 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Floating shape accents */
        .shape { position: absolute; border-radius: 50%; filter: blur(40px); opacity: 0.25; animation: float 12s ease-in-out infinite; }
        .shape.s1 { width: 280px; height: 280px; background: var(--construction-orange); top: -60px; left: -60px; animation-delay: 0s; }
        .shape.s2 { width: 320px; height: 320px; background: var(--construction-yellow); bottom: -80px; right: -80px; animation-delay: 3s; }
        @keyframes float { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-18px) } }

        .login-container {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: saturate(180%) blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 24px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            width: 100%;
            max-width: 980px;
            min-height: 620px;
        }

        .login-sidebar {
            background: linear-gradient(145deg, var(--primary-color) 0%, <?php echo htmlspecialchars($accent_color); ?> 100%);
            color: white;
            padding: 5rem 2.25rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .brand-logo { height: 170px; width: auto; margin-bottom: 1rem; filter: drop-shadow(0 4px 12px rgba(0,0,0,0.2)); }
        .tagline { opacity: 0.9; max-width: 380px; }

        .login-form { padding: 3rem 2.25rem; display: flex; flex-direction: column; justify-content: center; animation: fadeIn 0.6s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px) } to { opacity: 1; transform: translateY(0) } }

        .input-group .input-group-text { background: #fff; border: 2px solid #e9eef5; border-right: 0; border-radius: 10px 0 0 10px; color: var(--dark-color); }
        .form-control { border: 2px solid #e9eef5; border-radius: 0 10px 10px 0; padding: 12px 16px; font-size: 16px; transition: all 0.25s ease; }
        .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.2rem rgba(36, 52, 71, 0.15); }
        .input-group .btn { border-radius: 0 10px 10px 0; }

        .btn-primary { background: linear-gradient(135deg, var(--primary-color) 0%, <?php echo htmlspecialchars($accent_color); ?> 100%); border: none; border-radius: 12px; padding: 12px 24px; font-weight: 600; transition: transform 0.18s ease, box-shadow 0.18s ease; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(36, 52, 71, 0.25); }
        .btn-primary:disabled { opacity: 0.8; cursor: not-allowed; }

        .actions-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .muted-link { color: #6b7480; text-decoration: none; }
        .muted-link:hover { color: var(--primary-color); text-decoration: underline; }

        .divider { display: flex; align-items: center; text-align: center; margin: 1.25rem 0; color: #99a2ad; }
        .divider::before, .divider::after { content: ""; flex: 1; border-bottom: 1px solid #e6eaf0; }
        .divider:not(:empty)::before { margin-right: .75em; }
        .divider:not(:empty)::after { margin-left: .75em; }

        .alert { border-radius: 12px; border: none; }

        @media (max-width: 768px) {
            .login-sidebar { display: none; }
            .login-form { padding: 2rem 1.25rem; }
            .login-container { min-height: auto; }
        }
    </style>
</head>
<body>
    <div class="shape s1"></div>
    <div class="shape s2"></div>

    <div class="login-container">
        <div class="row g-0 h-100">
            <!-- Sidebar -->
            <div class="col-lg-5">
                <div class="login-sidebar">
                    <?php if (!empty($platform_logo)): ?>
                        <img class="brand-logo" src="<?php echo htmlspecialchars(str_replace('public/public/', 'public/', $base_url . $platform_logo)); ?>" alt="Logo">
                    <?php else: ?>
                        <div class="icon-large"><i class="fas fa-hard-hat"></i></div>
                    <?php endif; ?>
                    <h2 class="mb-2"><?php echo htmlspecialchars($platform_name); ?></h2>
                    <p class="tagline mb-4">Streamline your construction operations with our comprehensive management platform.</p>
                    <ul class="feature-list text-start w-100" style="max-width:420px">
                        <li><i class="fas fa-check me-2"></i> Employee & Machine Management</li>
                        <li><i class="fas fa-check me-2"></i> Contract & Project Tracking</li>
                        <li><i class="fas fa-check me-2"></i> Financial Reporting</li>
                        <li><i class="fas fa-check me-2"></i> Real-time Analytics</li>
                        <li><i class="fas fa-check me-2"></i> Multi-tenant Architecture</li>
                    </ul>
                </div>
            </div>
            <!-- Login Form -->
            <div class="col-lg-7">
                <div class="login-form">
                    <div class="text-center mb-4">
                        <h3 class="text-dark mb-1">Welcome Back</h3>
                        <p class="text-muted mb-0">Sign in to continue to <?php echo htmlspecialchars($platform_name); ?>.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" id="loginForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="you@example.com" required autocomplete="username">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Your password" required autocomplete="current-password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>

                        <div class="mb-3 actions-row">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                                <label class="form-check-label" for="remember_me">Remember me</label>
                            </div>
                            <a href="#" class="muted-link" data-bs-toggle="modal" data-bs-target="#forgotModal">Forgot password?</a>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" id="signInBtn">
                                <span class="btn-text"><i class="fas fa-sign-in-alt me-2"></i>Sign In</span>
                                <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Signing In...</span>
                            </button>
                        </div>
                    </form>

                    <div class="divider">or</div>

                    <div class="text-center">
                        <p class="text-muted mb-0">Don't have an account?
                            <?php $contact_email = getSystemSettingLocal($conn2, 'contact_email', ''); ?>
                            <?php if (!empty($contact_email)): ?>
                                <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="text-decoration-none">Contact your administrator</a>
                            <?php else: ?>
                                <a href="<?php echo $base_url; ?>contact.php" class="text-decoration-none">Contact your administrator</a>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="text-center mt-4">
                        <small class="text-muted">&copy; 2024 <?php echo htmlspecialchars($platform_name); ?>. All rights reserved.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-key me-2"></i>Reset Password</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Enter your account email</label>
              <input type="email" class="form-control" id="forgotEmail" placeholder="you@example.com">
            </div>
            <div id="forgotFeedback" class="small"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" id="sendResetBtn"><i class="fas fa-paper-plane me-1"></i>Send Reset Link</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        const emailField = document.getElementById('email');
        const form = document.getElementById('loginForm');
        const btn = document.getElementById('signInBtn');
        const btnText = btn?.querySelector('.btn-text');
        const btnLoading = btn?.querySelector('.btn-loading');

        emailField && emailField.focus();

        if (togglePassword && password) {
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }

        if (form && btn && btnText && btnLoading) {
            form.addEventListener('submit', function() {
                btn.disabled = true;
                btnText.classList.add('d-none');
                btnLoading.classList.remove('d-none');
            });
        }

        // Forgot password submit
        const sendBtn = document.getElementById('sendResetBtn');
        const emailInput = document.getElementById('forgotEmail');
        const feedback = document.getElementById('forgotFeedback');
        if (sendBtn) {
            sendBtn.addEventListener('click', function(){
                const email = (emailInput.value || '').trim();
                if (!email) {
                    feedback.className = 'small text-danger';
                    feedback.textContent = 'Please enter your email.';
                    return;
                }
                feedback.className = 'small text-muted';
                feedback.textContent = 'Sending reset link...';
                fetch('api/request-password-reset.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        feedback.className = 'small text-success';
                        feedback.textContent = data.message || 'If the email exists, a reset link has been sent.';
                    } else {
                        feedback.className = 'small text-danger';
                        feedback.textContent = data.message || 'Failed to send reset link.';
                    }
                }).catch(() => {
                    feedback.className = 'small text-danger';
                    feedback.textContent = 'Network error. Please try again.';
                });
            });
        }
    });
    </script>
        <script>
    // Register Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
                .then(registration => {
                    console.log('Service Worker registered successfully:', registration.scope);
                })
                .catch(error => {
                    console.log('Service Worker registration failed:', error);
                });
        });
    }
</script>
</body>
</html>