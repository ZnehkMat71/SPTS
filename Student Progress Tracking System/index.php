<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Progress Tracking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
            --dark-bg: #1a1a1a;
            --gradient-primary: linear-gradient(135deg, #1E3C72 0%, #2A5298 100%);
            --gradient-secondary: linear-gradient(135deg, #3498db, #2980b9);
            --box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            scroll-behavior: smooth;
            overflow-x: hidden;
        }
        
        .hero-section {
            background: var(--gradient-primary);
            color: white;
            padding: 150px 0 100px;
            margin-bottom: 80px;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" fill="none"/><circle cx="50" cy="50" r="40" stroke="rgba(255,255,255,0.1)" stroke-width="2" fill="none"/></svg>') repeat;
            opacity: 0.1;
            animation: backgroundMove 20s linear infinite;
        }

        @keyframes backgroundMove {
            0% { background-position: 0 0; }
            100% { background-position: 100px 100px; }
        }
        
        .feature-card {
            border: none;
            border-radius: 20px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            margin-bottom: 30px;
            background: white;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--gradient-secondary);
            transform: scaleX(0);
            transform-origin: left;
            transition: var(--transition);
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }
        
        .feature-icon {
            font-size: 3rem;
            background: var(--gradient-secondary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 25px;
            transition: var(--transition);
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1);
        }
        
        .cta-section {
            background-color: var(--light-bg);
            padding: 120px 0;
            margin-top: 80px;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(52, 152, 219, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .btn-primary {
            background: var(--gradient-secondary);
            border: none;
            padding: 15px 35px;
            border-radius: 50px;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--gradient-primary);
            z-index: -1;
            transition: var(--transition);
            opacity: 0;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-primary:hover::before {
            opacity: 1;
        }
        
        .navbar {
            background-color: rgba(44, 62, 80, 0.95);
            padding: 20px 0;
            backdrop-filter: blur(10px);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: var(--transition);
        }

        .navbar.scrolled {
            padding: 15px 0;
            background-color: rgba(44, 62, 80, 0.98);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            color: white !important;
            font-weight: 700;
            font-size: 2rem;
            letter-spacing: 1px;
            position: relative;
        }

        .navbar-brand::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--gradient-secondary);
            transform: scaleX(0);
            transition: var(--transition);
        }

        .navbar-brand:hover::after {
            transform: scaleX(1);
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            padding: 8px 20px !important;
            transition: var(--transition);
            position: relative;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--gradient-secondary);
            transition: var(--transition);
            transform: translateX(-50%);
        }

        .nav-link:hover {
            color: white !important;
            transform: translateY(-2px);
        }

        .nav-link:hover::after {
            width: 80%;
        }

        .footer {
            background-color: var(--dark-bg);
            color: white;
            padding: 80px 0 30px;
            position: relative;
            overflow: hidden;
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--gradient-secondary);
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 15px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            display: inline-block;
            position: relative;
        }

        .footer-links a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gradient-secondary);
            transition: var(--transition);
        }

        .footer-links a:hover {
            color: white;
            transform: translateX(5px);
        }

        .footer-links a:hover::after {
            width: 100%;
        }

        .social-icons {
            margin-top: 25px;
        }

        .social-icons a {
            color: white;
            font-size: 1.5rem;
            margin-right: 20px;
            transition: var(--transition);
            display: inline-block;
        }

        .social-icons a:hover {
            color: var(--secondary-color);
            transform: translateY(-5px) scale(1.1);
        }

        .section-title {
            position: relative;
            display: inline-block;
            margin-bottom: 50px;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: var(--gradient-secondary);
        }

        @media (max-width: 768px) {
            .hero-section {
                padding: 120px 0 80px;
            }
            
            .navbar {
                padding: 15px 0;
            }

            .feature-card {
                padding: 2rem;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light-bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gradient-secondary);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gradient-primary);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="images/ISAT-U-logo-2.png" alt="ISAT-U Logo" height="50" class="d-inline-block align-text-top me-2">
                SPTS
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="auth/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary ms-2" href="auth/user-signup.php">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <h1 class="display-4 fw-bold mb-4">Student Progress Tracking System</h1>
                    <p class="lead mb-4">Streamline student supervision and progress monitoring with our comprehensive tracking system. Designed for efficiency and success.</p>
                    <div class="d-flex gap-3">
                        <a href="auth/user-signup.php" class="btn btn-primary">Get Started</a>
                        <a href="#features" class="btn btn-outline-light">Learn More</a>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block" data-aos="fade-left">
                    <img src="https://cdn-icons-png.flaticon.com/512/3135/3135755.png" alt="Student Progress" class="img-fluid">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="container">
        <h2 class="text-center section-title" data-aos="fade-up">Key Features</h2>
        <div class="row">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-card text-center">
                    <i class="fas fa-clock feature-icon"></i>
                    <h3>Time Tracking</h3>
                    <p>Efficiently monitor and record student working hours and progress with our intuitive time tracking system.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-card text-center">
                    <i class="fas fa-chart-line feature-icon"></i>
                    <h3>Progress Monitoring</h3>
                    <p>Track and analyze student performance with detailed reports and real-time progress updates.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="feature-card text-center">
                    <i class="fas fa-users feature-icon"></i>
                    <h3>Supervisor Management</h3>
                    <p>Streamline communication between students and supervisors with our integrated messaging system.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container text-center">
            <h2 class="mb-4" data-aos="fade-up">Ready to Get Started?</h2>
            <p class="lead mb-4" data-aos="fade-up" data-aos-delay="100">Join thousands of students and supervisors who are already using our system.</p>
            <a href="auth/user-signup.php" class="btn btn-primary btn-lg" data-aos="fade-up" data-aos-delay="200">Sign Up Now</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4" data-aos="fade-up">
                    <h4>SPTS</h4>
                    <p>Empowering students and supervisors with efficient progress tracking solutions.</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <h4>Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="#features">Features</a></li>
                        
                        <li><a href="auth/login.php">Login</a></li>
                        <li><a href="auth/user-signup.php">Register</a></li>
                    </ul>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <h4>Contact Us</h4>
                    <ul class="footer-links">
                        <li><i class="fas fa-envelope me-2"></i> support@spts.com</li>
                        <li><i class="fas fa-phone me-2"></i> +1 234 567 890</li>
                        <li><i class="fas fa-map-marker-alt me-2"></i> Burgos St. La Paz, Iloilo City </li>
                    </ul>
                </div>
            </div>
            <hr class="mt-4 mb-4" style="border-color: rgba(255,255,255,0.1);">
            <div class="text-center">
                <p class="mb-0">&copy; 2024 Student Progress Tracking System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html> 