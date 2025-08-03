<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: public/dashboard/');
    exit;
}
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

        /* Statistics Section */
        .stats-section {
            background: linear-gradient(135deg, var(--construction-gray), var(--dark-color));
            color: white;
            padding: 80px 0;
        }

        .stat-item {
            text-align: center;
            animation: fadeInUp 1s ease-out both;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 900;
            color: var(--construction-orange);
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Testimonials Section */
        .testimonials-section {
            background: var(--light-color);
            padding: 100px 0;
        }

        .testimonial-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            position: relative;
            margin: 20px 0;
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
        }

        /* Footer */
        .footer {
            background: var(--dark-color);
            color: white;
            padding: 60px 0 30px;
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

    <!-- Hero Section -->
    <section class="hero-section construction-bg">
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
                        <a href="login.php" class="btn btn-construction">
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
            
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4 class="mb-3">Employee Management</h4>
                        <p class="text-muted">Track employee attendance, salaries, and performance with advanced HR tools.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <h4 class="mb-3">Machine Management</h4>
                        <p class="text-muted">Monitor equipment usage, maintenance schedules, and operational costs.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <h4 class="mb-3">Contract Management</h4>
                        <p class="text-muted">Manage project contracts, track progress, and handle payments efficiently.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h4 class="mb-3">Financial Analytics</h4>
                        <p class="text-muted">Comprehensive financial reporting and expense tracking for better decision making.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="500">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-globe"></i>
                        </div>
                        <h4 class="mb-3">Multi-tenant SaaS</h4>
                        <p class="text-muted">Secure, scalable platform supporting multiple construction companies.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="600">
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

    <!-- Statistics Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12" data-aos="fade-up">
                    <h2 class="display-4 fw-bold mb-4">Platform Statistics</h2>
                    <p class="lead">Trusted by construction companies worldwide</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="stat-item">
                        <div class="stat-number" data-count="500">0</div>
                        <div class="stat-label">Active Companies</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-item">
                        <div class="stat-number" data-count="2500">0</div>
                        <div class="stat-label">Managed Employees</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-item">
                        <div class="stat-number" data-count="1500">0</div>
                        <div class="stat-label">Active Machines</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="stat-item">
                        <div class="stat-number" data-count="1000">0</div>
                        <div class="stat-label">Active Contracts</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials-section">
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

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8" data-aos="fade-up">
                    <h2 class="display-4 fw-bold mb-4">Ready to Transform Your Construction Business?</h2>
                    <p class="lead mb-5">Join hundreds of construction companies already using our platform to streamline their operations.</p>
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="login.php" class="btn btn-light btn-lg">
                            <i class="fas fa-rocket me-2"></i>Start Free Trial
                        </a>
                        <a href="#contact" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-phone me-2"></i>Contact Sales
                        </a>
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

        // Animated counter
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 100;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current);
            }, 20);
        }

        // Intersection Observer for counters
        const observerOptions = {
            threshold: 0.5
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counters = entry.target.querySelectorAll('.stat-number');
                    counters.forEach(counter => {
                        const target = parseInt(counter.getAttribute('data-count'));
                        animateCounter(counter, target);
                    });
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // Observe stats section
        const statsSection = document.querySelector('.stats-section');
        if (statsSection) {
            observer.observe(statsSection);
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
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