<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is authenticated
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard/');
    exit;
}

// Get available languages from database
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT * FROM languages WHERE is_active = 1 ORDER BY language_name");
$stmt->execute();
$available_languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current language preference from session or default to English
$current_language = $_SESSION['current_language'] ?? 'en';

// Get system settings for branding
function getSystemSettingLocal($conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : $default;
}

$current_settings = [
    'platform_name' => getSystemSettingLocal($conn, 'platform_name', 'Construction SaaS Platform'),
    'platform_logo' => getSystemSettingLocal($conn, 'platform_logo', ''),
    'contact_address' => getSystemSettingLocal($conn, 'contact_address', ''),
    'contact_phone' => getSystemSettingLocal($conn, 'contact_phone', ''),
    'contact_email' => getSystemSettingLocal($conn, 'contact_email', ''),
    'contact_website' => getSystemSettingLocal($conn, 'contact_website', ''),
    'contact_facebook' => getSystemSettingLocal($conn, 'contact_facebook', ''),
    'contact_twitter' => getSystemSettingLocal($conn, 'contact_twitter', ''),
    'contact_linkedin' => getSystemSettingLocal($conn, 'contact_linkedin', ''),
    'contact_instagram' => getSystemSettingLocal($conn, 'contact_instagram', '')
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Construction SaaS Platform - Advanced Construction Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --info-color: #36b9cc;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
            --construction-orange: #ff6b35;
            --construction-yellow: #f7931e;
            --construction-blue: #2c5aa0;
            --construction-gray: #4a4a4a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            overflow-x: hidden;
        }

        /* Navigation Styles */
        .navbar {
            transition: all 0.3s ease;
            padding: 1rem 0;
        }

        .navbar.scrolled {
            background: rgba(44, 90, 160, 0.98) !important;
            padding: 0.5rem 0;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .nav-link {
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--construction-orange);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .dropdown-item {
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: linear-gradient(45deg, var(--construction-blue), var(--primary-color));
            color: white;
        }

        /* Construction-themed background patterns */
        .construction-bg {
            background: linear-gradient(135deg, var(--construction-blue) 0%, var(--primary-color) 100%);
            position: relative;
            overflow: hidden;
        }

        .construction-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(255, 107, 53, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(247, 147, 30, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(44, 90, 160, 0.1) 0%, transparent 50%);
            animation: constructionFloat 20s ease-in-out infinite;
        }

        @keyframes constructionFloat {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(1deg); }
            66% { transform: translateY(10px) rotate(-1deg); }
        }

        /* Animated construction elements */
        .construction-element {
            position: absolute;
            opacity: 0.1;
            animation: constructionMove 15s linear infinite;
        }

        .construction-element:nth-child(1) {
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .construction-element:nth-child(2) {
            top: 20%;
            right: 15%;
            animation-delay: 3s;
        }

        .construction-element:nth-child(3) {
            bottom: 15%;
            left: 20%;
            animation-delay: 6s;
        }

        .construction-element:nth-child(4) {
            bottom: 25%;
            right: 10%;
            animation-delay: 9s;
        }

        @keyframes constructionMove {
            0% { transform: translateY(0px) rotate(0deg); opacity: 0.1; }
            50% { transform: translateY(-30px) rotate(180deg); opacity: 0.3; }
            100% { transform: translateY(0px) rotate(360deg); opacity: 0.1; }
        }

        /* Hero Section */
        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            color: white;
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 4rem;
            font-weight: 900;
            margin-bottom: 1.5rem;
            background: linear-gradient(45deg, #ffffff, #f0f0f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: titleGlow 3s ease-in-out infinite alternate;
        }

        @keyframes titleGlow {
            0% { filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.3)); }
            100% { filter: drop-shadow(0 0 20px rgba(255, 255, 255, 0.6)); }
        }

        .hero-subtitle {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            animation: fadeInUp 1s ease-out 0.5s both;
        }

        .hero-description {
            font-size: 1.1rem;
            margin-bottom: 3rem;
            opacity: 0.8;
            animation: fadeInUp 1s ease-out 0.7s both;
        }

        /* Animated buttons */
        .btn-construction {
            background: linear-gradient(45deg, var(--construction-orange), var(--construction-yellow));
            border: none;
            color: white;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 1s ease-out 0.9s both;
        }

        .btn-construction::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-construction:hover::before {
            left: 100%;
        }

        .btn-construction:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(255, 107, 53, 0.4);
        }

        /* Features Section */
        .features-section {
            padding: 100px 0;
            background: white;
            position: relative;
            z-index: 10;
        }

        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--construction-orange), var(--construction-yellow));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .feature-card h4 {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 15px;
        }

        .feature-card p {
            color: var(--gray-600);
            line-height: 1.6;
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(45deg, var(--construction-blue), var(--primary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2rem;
            animation: iconPulse 2s ease-in-out infinite;
        }

        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* Pricing Section */
        .pricing-section {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 80px 0;
            position: relative;
            z-index: 10;
        }

        .pricing-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 30px;
            color: var(--dark-color);
        }

        .pricing-card.featured {
            transform: scale(1.05);
            border: 3px solid var(--construction-orange);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .pricing-card.featured:hover {
            transform: scale(1.05) translateY(-10px);
        }

        .popular-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--construction-orange);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .pricing-header {
            margin-bottom: 30px;
        }

        .plan-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 15px;
        }

        .price {
            margin-bottom: 15px;
        }

        .currency {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--construction-orange);
        }

        .amount {
            font-size: 3rem;
            font-weight: 900;
            color: var(--construction-orange);
        }

        .period {
            font-size: 1rem;
            color: var(--secondary-color);
        }

        .plan-description {
            color: var(--secondary-color);
            font-size: 0.9rem;
        }

        .pricing-features {
            margin-bottom: 30px;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .feature-list li {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .pricing-footer {
            margin-top: auto;
        }

        /* Contact Section */
        .contact-section {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 80px 0;
            position: relative;
            z-index: 10;
        }

        .contact-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .contact-icon {
            width: 50px;
            height: 50px;
            background: var(--construction-orange);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .contact-details h6 {
            color: var(--construction-orange);
            margin-bottom: 5px;
            font-weight: 600;
        }

        .contact-details p {
            margin: 0;
            opacity: 0.9;
        }

        .social-links {
            margin-top: 30px;
        }

        .social-link {
            width: 45px;
            height: 45px;
            background: var(--construction-orange);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-link:hover {
            background: white;
            color: var(--construction-orange);
            transform: translateY(-3px);
        }

        .contact-form {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .contact-form .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }

        .contact-form .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .contact-form .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--construction-orange);
            color: white;
            box-shadow: 0 0 0 0.2rem rgba(255, 107, 53, 0.25);
        }

        .contact-form .form-label {
            color: white;
            font-weight: 500;
        }

        /* Testimonials Section */
        .testimonials-section {
            background: var(--light-color);
            padding: 100px 0;
            position: relative;
            z-index: 10;
        }

        .testimonial-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            position: relative;
            margin: 20px 0;
            transition: all 0.3s ease;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }

        .testimonial-card::before {
            content: '"';
            position: absolute;
            top: -20px;
            left: 30px;
            font-size: 4rem;
            color: var(--construction-orange);
            font-family: serif;
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, var(--construction-orange), var(--construction-yellow));
            color: white;
            padding: 100px 0;
            text-align: center;
            position: relative;
            z-index: 10;
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, #1a252f, #2c3e50);
            color: white;
            padding: 60px 0 30px;
            position: relative;
            z-index: 10;
        }

        .footer h5, .footer h6 {
            color: white;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .footer p, .footer li, .footer a {
            color: rgba(255, 255, 255, 0.8);
        }

        .footer a:hover {
            color: white;
            text-decoration: none;
        }

        .footer .list-unstyled li {
            margin-bottom: 10px;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.2rem;
            }
            
            .feature-card {
                margin-bottom: 30px;
            }
        }

        /* Loading Animation */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--construction-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Particle effects */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            animation: particleFloat 6s linear infinite;
        }

        @keyframes particleFloat {
            0% {
                transform: translateY(100vh) translateX(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100px) translateX(100px);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: rgba(44, 90, 160, 0.95); backdrop-filter: blur(10px);">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <?php if (!empty($current_settings['platform_logo'])): ?>
                    <img src="/constract360/construction/<?php echo htmlspecialchars($current_settings['platform_logo']); ?>" alt="Logo" style="height: 30px; margin-right: 10px;">
                <?php else: ?>
                    <i class="fas fa-hard-hat me-2"></i>
                <?php endif; ?>
                <?php echo htmlspecialchars($current_settings['platform_name'] ?? 'Construction SaaS'); ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pricing">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#testimonials">Testimonials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <!-- Language Switcher -->
                    <div class="dropdown me-3">
                        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-language me-1"></i>
                            <span id="currentLanguage">
                                <?php 
                                $current_lang_name = 'English';
                                foreach ($available_languages as $lang) {
                                    if ($lang['language_code'] === $current_language) {
                                        $current_lang_name = $lang['language_name_native'];
                                        break;
                                    }
                                }
                                echo htmlspecialchars($current_lang_name);
                                ?>
                            </span>
                        </button>
                        <ul class="dropdown-menu">
                            <?php foreach ($available_languages as $lang): ?>
                            <li>
                                <a class="dropdown-item <?php echo $lang['language_code'] === $current_language ? 'active' : ''; ?>" 
                                   href="#" onclick="changeLanguage('<?php echo $lang['language_code']; ?>', '<?php echo htmlspecialchars($lang['language_name_native']); ?>')">
                                    <?php echo htmlspecialchars($lang['language_name_native']); ?>
                                    <?php if ($lang['language_code'] === $current_language): ?>
                                        <i class="fas fa-check ms-auto"></i>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <!-- Login Button -->
                    <div class="d-flex gap-2">
                        <a href="../login.php" class="btn btn-construction btn-sm">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section construction-bg" id="home">
        <!-- Construction Elements -->
        <div class="construction-element">
            <i class="fas fa-hard-hat fa-3x"></i>
        </div>
        <div class="construction-element">
            <i class="fas fa-truck fa-3x"></i>
        </div>
        <div class="construction-element">
            <i class="fas fa-tools fa-3x"></i>
        </div>
        <div class="construction-element">
            <i class="fas fa-building fa-3x"></i>
        </div>

        <!-- Particles -->
        <div class="particles" id="particles"></div>

        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content" data-aos="fade-right">
                    <h1 class="hero-title">Construction SaaS Platform</h1>
                    <p class="hero-subtitle">Advanced Construction Management Solution</p>
                    <p class="hero-description">
                        Streamline your construction projects with our comprehensive SaaS platform. 
                        Manage employees, machines, contracts, and finances all in one place.
                    </p>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="../login.php" class="btn btn-construction">
                            <i class="fas fa-sign-in-alt me-2"></i>Get Started
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-play me-2"></i>Learn More
                        </a>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="text-center">
                        <div class="position-relative">
                            <div class="bg-white bg-opacity-10 rounded-4 p-5 backdrop-blur">
                                <i class="fas fa-chart-line fa-5x text-white mb-4"></i>
                                <h3 class="text-white mb-3">Real-time Analytics</h3>
                                <p class="text-white-50">Monitor your construction projects with advanced analytics and reporting tools.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12" data-aos="fade-up">
                    <h2 class="display-4 fw-bold mb-4">Powerful Features</h2>
                    <p class="lead text-muted">Everything you need to manage your construction business</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4 class="mb-3">Employee Management</h4>
                        <p class="text-muted">Track employee attendance, salaries, and performance with advanced HR tools.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <h4 class="mb-3">Machine Management</h4>
                        <p class="text-muted">Monitor equipment usage, maintenance schedules, and operational costs.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <h4 class="mb-3">Contract Management</h4>
                        <p class="text-muted">Manage project contracts, track progress, and handle payments efficiently.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h4 class="mb-3">Financial Analytics</h4>
                        <p class="text-muted">Comprehensive financial reporting and expense tracking for better decision making.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="500">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-globe"></i>
                        </div>
                        <h4 class="mb-3">Multi-tenant SaaS</h4>
                        <p class="text-muted">Secure, scalable platform supporting multiple construction companies.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="600">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h4 class="mb-3">Mobile Responsive</h4>
                        <p class="text-muted">Access your construction data anywhere, anytime with our mobile-friendly interface.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="pricing-section" id="pricing">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12" data-aos="fade-up">
                    <h2 class="display-4 fw-bold mb-4">Choose Your Plan</h2>
                    <p class="lead text-muted">Flexible pricing plans designed for construction companies of all sizes</p>
                </div>
            </div>
            
            <div class="row g-4 justify-content-center">
                <?php
                // Get active pricing plans from database
                $stmt = $conn->prepare("SELECT * FROM pricing_plans WHERE is_active = 1 ORDER BY price ASC");
                $stmt->execute();
                $pricing_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($pricing_plans)):
                    foreach ($pricing_plans as $index => $plan):
                        $features = json_decode($plan['features'], true) ?: [];
                        $is_popular = $plan['is_popular'];
                        $delay = ($index + 1) * 100;
                ?>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                    <div class="pricing-card <?php echo $is_popular ? 'featured' : ''; ?>">
                        <?php if ($is_popular): ?>
                        <div class="popular-badge">Most Popular</div>
                        <?php endif; ?>
                        <div class="pricing-header">
                            <h3 class="plan-name"><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                            <div class="price">
                                <span class="currency"><?php echo $plan['currency']; ?></span>
                                <span class="amount"><?php echo number_format($plan['price'], 0); ?></span>
                                <span class="period">/<?php echo $plan['billing_cycle']; ?></span>
                            </div>
                            <p class="plan-description"><?php echo htmlspecialchars($plan['description']); ?></p>
                        </div>
                        <div class="pricing-features">
                            <ul class="feature-list">
                                <?php foreach ($features as $feature): ?>
                                <li><i class="fas fa-check text-success me-2"></i><?php echo htmlspecialchars($feature); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="pricing-footer">
                            <a href="../login.php" class="btn <?php echo $is_popular ? 'btn-primary' : 'btn-outline-primary'; ?> btn-lg w-100">Get Started</a>
                        </div>
                    </div>
                </div>
                <?php 
                    endforeach;
                else:
                ?>
                <!-- Default pricing cards if no plans in database -->
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="pricing-card">
                        <div class="pricing-header">
                            <h3 class="plan-name">Basic</h3>
                            <div class="price">
                                <span class="currency">$</span>
                                <span class="amount">99</span>
                                <span class="period">/month</span>
                            </div>
                            <p class="plan-description">Perfect for small construction companies</p>
                        </div>
                        <div class="pricing-features">
                            <ul class="feature-list">
                                <li><i class="fas fa-check text-success me-2"></i>Up to 10 employees</li>
                                <li><i class="fas fa-check text-success me-2"></i>25 machines</li>
                                <li><i class="fas fa-check text-success me-2"></i>Basic reporting</li>
                                <li><i class="fas fa-check text-success me-2"></i>Email support</li>
                                <li><i class="fas fa-check text-success me-2"></i>Mobile app access</li>
                                <li><i class="fas fa-times text-muted me-2"></i>Advanced analytics</li>
                                <li><i class="fas fa-times text-muted me-2"></i>Priority support</li>
                            </ul>
                        </div>
                        <div class="pricing-footer">
                            <a href="../login.php" class="btn btn-outline-primary btn-lg w-100">Get Started</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="pricing-card featured">
                        <div class="popular-badge">Most Popular</div>
                        <div class="pricing-header">
                            <h3 class="plan-name">Professional</h3>
                            <div class="price">
                                <span class="currency">$</span>
                                <span class="amount">199</span>
                                <span class="period">/month</span>
                            </div>
                            <p class="plan-description">Ideal for growing construction businesses</p>
                        </div>
                        <div class="pricing-features">
                            <ul class="feature-list">
                                <li><i class="fas fa-check text-success me-2"></i>Up to 50 employees</li>
                                <li><i class="fas fa-check text-success me-2"></i>100 machines</li>
                                <li><i class="fas fa-check text-success me-2"></i>Advanced reporting</li>
                                <li><i class="fas fa-check text-success me-2"></i>Priority support</li>
                                <li><i class="fas fa-check text-success me-2"></i>Mobile app access</li>
                                <li><i class="fas fa-check text-success me-2"></i>Advanced analytics</li>
                                <li><i class="fas fa-check text-success me-2"></i>API access</li>
                            </ul>
                        </div>
                        <div class="pricing-footer">
                            <a href="../login.php" class="btn btn-primary btn-lg w-100">Get Started</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="pricing-card">
                        <div class="pricing-header">
                            <h3 class="plan-name">Enterprise</h3>
                            <div class="price">
                                <span class="currency">$</span>
                                <span class="amount">399</span>
                                <span class="period">/month</span>
                            </div>
                            <p class="plan-description">For large construction enterprises</p>
                        </div>
                        <div class="pricing-features">
                            <ul class="feature-list">
                                <li><i class="fas fa-check text-success me-2"></i>Unlimited employees</li>
                                <li><i class="fas fa-check text-success me-2"></i>Unlimited machines</li>
                                <li><i class="fas fa-check text-success me-2"></i>Custom reporting</li>
                                <li><i class="fas fa-check text-success me-2"></i>24/7 support</li>
                                <li><i class="fas fa-check text-success me-2"></i>Mobile app access</li>
                                <li><i class="fas fa-check text-success me-2"></i>Advanced analytics</li>
                                <li><i class="fas fa-check text-success me-2"></i>Custom integrations</li>
                            </ul>
                        </div>
                        <div class="pricing-footer">
                            <a href="../login.php" class="btn btn-outline-primary btn-lg w-100">Get Started</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials-section" id="testimonials">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12" data-aos="fade-up">
                    <h2 class="display-4 fw-bold mb-4">What Our Clients Say</h2>
                    <p class="lead text-muted">Real feedback from construction professionals</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="testimonial-card">
                        <p class="mb-4">"This platform has revolutionized how we manage our construction projects. The employee tracking and machine management features are incredible."</p>
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="fas fa-user text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">John Smith</h6>
                                <small class="text-muted">Construction Manager, ABC Construction</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="testimonial-card">
                        <p class="mb-4">"The financial analytics and reporting tools have given us complete visibility into our project costs and profitability."</p>
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-success rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="fas fa-user text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Sarah Johnson</h6>
                                <small class="text-muted">Project Director, XYZ Builders</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="testimonial-card">
                        <p class="mb-4">"The multi-tenant architecture allows us to manage multiple construction sites efficiently. Highly recommended!"</p>
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="fas fa-user text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Mike Davis</h6>
                                <small class="text-muted">CEO, Metro Construction</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section" id="contact">
        <div class="container">
            <?php if (isset($_GET['contact']) && $_GET['contact'] === 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Thank you for your message! We'll get back to you soon.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif (isset($_GET['contact']) && $_GET['contact'] === 'error'): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($_GET['message'] ?? 'An error occurred. Please try again.'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row text-center mb-5">
                <div class="col-12" data-aos="fade-up">
                    <h2 class="display-4 fw-bold mb-4">Get In Touch</h2>
                    <p class="lead text-muted">Ready to transform your construction business? Contact us today!</p>
                </div>
            </div>
            
            <div class="row g-5">
                <!-- Contact Information -->
                <div class="col-lg-4" data-aos="fade-right">
                    <div class="contact-info">
                        <h4 class="mb-4">Contact Information</h4>
                        
                        <div class="contact-item mb-3">
                            <div class="contact-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="contact-details">
                                <h6>Address</h6>
                                <p><?php echo htmlspecialchars($current_settings['contact_address'] ?? '123 Construction Street, Building City, BC 12345'); ?></p>
                            </div>
                        </div>
                        
                        <div class="contact-item mb-3">
                            <div class="contact-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="contact-details">
                                <h6>Phone</h6>
                                <p><?php echo htmlspecialchars($current_settings['contact_phone'] ?? '+1 (555) 123-4567'); ?></p>
                            </div>
                        </div>
                        
                        <div class="contact-item mb-3">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="contact-details">
                                <h6>Email</h6>
                                <p><?php echo htmlspecialchars($current_settings['contact_email'] ?? 'info@constructionsaas.com'); ?></p>
                            </div>
                        </div>
                        
                        <div class="contact-item mb-4">
                            <div class="contact-icon">
                                <i class="fas fa-globe"></i>
                            </div>
                            <div class="contact-details">
                                <h6>Website</h6>
                                <p><?php echo htmlspecialchars($current_settings['contact_website'] ?? 'www.constructionsaas.com'); ?></p>
                            </div>
                        </div>
                        
                        <!-- Social Media Links -->
                        <div class="social-links">
                            <h6 class="mb-3">Follow Us</h6>
                            <div class="d-flex gap-3">
                                <?php if (!empty($current_settings['contact_facebook'])): ?>
                                <a href="<?php echo htmlspecialchars($current_settings['contact_facebook']); ?>" class="social-link" target="_blank">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($current_settings['contact_twitter'])): ?>
                                <a href="<?php echo htmlspecialchars($current_settings['contact_twitter']); ?>" class="social-link" target="_blank">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($current_settings['contact_linkedin'])): ?>
                                <a href="<?php echo htmlspecialchars($current_settings['contact_linkedin']); ?>" class="social-link" target="_blank">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($current_settings['contact_instagram'])): ?>
                                <a href="<?php echo htmlspecialchars($current_settings['contact_instagram']); ?>" class="social-link" target="_blank">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Form -->
                <div class="col-lg-8" data-aos="fade-left">
                    <div class="contact-form">
                        <h4 class="mb-4">Send us a Message</h4>
                        <form id="contactForm" method="POST" action="../api/contact-submit.php">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="company" class="form-label">Company Name</label>
                                        <input type="text" class="form-control" id="company" name="company">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject *</label>
                                <select class="form-control" id="subject" name="subject" required>
                                    <option value="">Select a subject</option>
                                    <option value="general">General Inquiry</option>
                                    <option value="pricing">Pricing Information</option>
                                    <option value="demo">Request Demo</option>
                                    <option value="support">Technical Support</option>
                                    <option value="partnership">Partnership</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="message" class="form-label">Message *</label>
                                <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>Send Message
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5 class="mb-3">Construction SaaS Platform</h5>
                    <p class="text-muted">Advanced construction management solution for modern construction companies.</p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3">Features</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-muted text-decoration-none">Employee Management</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Machine Management</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Contract Management</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Financial Analytics</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3">Company</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-muted text-decoration-none">About Us</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Careers</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Contact</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Support</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3">Resources</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-muted text-decoration-none">Documentation</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">API Reference</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Blog</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Help Center</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3">Legal</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-muted text-decoration-none">Privacy Policy</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Terms of Service</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Cookie Policy</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">GDPR</a></li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">&copy; 2024 Construction SaaS Platform. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0 text-muted">Made with <i class="fas fa-heart text-danger"></i> for construction professionals</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            easing: 'ease-in-out',
            once: true
        });

        // Loading animation
        window.addEventListener('load', function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.style.opacity = '0';
            setTimeout(() => {
                loadingOverlay.style.display = 'none';
            }, 500);
        });

        // Particle effects
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 50;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 6 + 's';
                particle.style.animationDuration = (Math.random() * 3 + 3) + 's';
                particlesContainer.appendChild(particle);
            }
        }

        // Pricing card hover effects
        document.querySelectorAll('.pricing-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px)';
            });
            
            card.addEventListener('mouseleave', function() {
                if (this.classList.contains('featured')) {
                    this.style.transform = 'scale(1.05)';
                } else {
                    this.style.transform = 'translateY(0)';
                }
            });
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const offsetTop = target.offsetTop - 80; // Account for fixed header
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Parallax effect for hero section
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const parallax = document.querySelector('.hero-section');
            if (parallax) {
                const speed = scrolled * 0.5;
                parallax.style.transform = `translateY(${speed}px)`;
            }
        });

        // Navbar scroll effect
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Active navigation highlighting
        window.addEventListener('scroll', () => {
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.nav-link');
            
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (window.scrollY >= (sectionTop - 200)) {
                    current = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${current}`) {
                    link.classList.add('active');
                }
            });
        });

        // Language switcher functionality
        function changeLanguage(lang, langName) {
            const currentLanguageSpan = document.getElementById('currentLanguage');
            
            // Update the display
            currentLanguageSpan.textContent = langName || 'English';
            
            // Store language preference
            localStorage.setItem('preferredLanguage', lang);
            
            // Send language change to server
            fetch('../api/change-language.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    language: lang
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const toast = document.createElement('div');
                    toast.className = 'alert alert-success alert-dismissible fade show position-fixed';
                    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
                    toast.innerHTML = `
                        <i class="fas fa-language me-2"></i>
                        Language changed to ${langName}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(toast);
                    
                    // Auto-remove after 3 seconds
                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                    
                    // Reload page to apply language changes
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            })
            .catch(error => {
                console.error('Error changing language:', error);
                // Show error message
                const toast = document.createElement('div');
                toast.className = 'alert alert-danger alert-dismissible fade show position-fixed';
                toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
                toast.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Failed to change language
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(toast);
                
                // Auto-remove after 3 seconds
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        // Initialize language preference
        document.addEventListener('DOMContentLoaded', () => {
            const savedLanguage = localStorage.getItem('preferredLanguage');
            if (savedLanguage) {
                // Update display without server call on page load
                const currentLanguageSpan = document.getElementById('currentLanguage');
                const languageMap = {
                    'en': 'English',
                    'es': 'Español',
                    'fr': 'Français',
                    'ar': 'العربية'
                };
                currentLanguageSpan.textContent = languageMap[savedLanguage] || 'English';
            }
        });

        // Initialize particles
        createParticles();

        // Add hover effects to feature cards
        document.querySelectorAll('.feature-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Add typing effect to hero title
        function typeWriter(element, text, speed = 100) {
            let i = 0;
            element.innerHTML = '';
            
            function type() {
                if (i < text.length) {
                    element.innerHTML += text.charAt(i);
                    i++;
                    setTimeout(type, speed);
                }
            }
            
            type();
        }

        // Initialize typing effect after page load
        window.addEventListener('load', () => {
            const heroTitle = document.querySelector('.hero-title');
            if (heroTitle) {
                const originalText = heroTitle.textContent;
                typeWriter(heroTitle, originalText, 50);
            }
        });

        // Add scroll-triggered animations
        const scrollAnimations = () => {
            const elements = document.querySelectorAll('[data-aos]');
            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const elementVisible = 150;
                
                if (elementTop < window.innerHeight - elementVisible) {
                    element.classList.add('aos-animate');
                }
            });
        };

        window.addEventListener('scroll', scrollAnimations);
        scrollAnimations(); // Run once on page load
    </script>
</body>
</html>