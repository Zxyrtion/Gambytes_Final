<?php
require_once __DIR__ . '/../../../includes/url_helper.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gambytes - Gambling Addiction Recovery & Support</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= asset('style.css') ?>?v=<?= time() ?>">
</head>
<body>

<!-- MODERN NAVBAR -->
<nav class="landing-navbar">
    <div class="container">
        <div class="navbar-content">
            <a href="<?= url('app/views/auth/homepage.php') ?>" class="landing-logo">
                <img src="<?= asset('images/Logo.png') ?>" alt="Gambytes Logo">
                <span>Gambytes</span>
            </a>
            <div class="landing-nav-links">
                <a href="#home"><i class="fas fa-home"></i> Home</a>
                <a href="#about"><i class="fas fa-info-circle"></i> About</a>
                <a href="#services"><i class="fas fa-hands-helping"></i> Services</a>
                <a href="#contact"><i class="fas fa-envelope"></i> Contact</a>
                <a href="<?= url('app/views/auth/login.php') ?>" class="nav-btn-login"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="<?= url('app/views/auth/register.php') ?>" class="nav-btn-signup"><i class="fas fa-user-plus"></i> Sign Up</a>
            </div>
        </div>
    </div>
</nav>

<!-- HERO SECTION -->
<section class="landing-hero" id="home">
    <div class="container">
        <div class="hero-grid">
            <div class="hero-left">
                <div class="hero-decorative-circle"></div>
                <h1 class="hero-title">GAMBYTES</h1>
                <p class="hero-subtitle">Professional Gambling Addiction Recovery & Support Services</p>
                <p class="hero-description">
                    Take the first step towards recovery. We provide comprehensive rehabilitation programs, 
                    self-exclusion support, and professional counseling to help you overcome gambling addiction.
                </p>
                <div class="hero-buttons">
                    <a href="<?= url('app/views/auth/register.php') ?>" class="btn-hero-primary">
                        <i class="fas fa-calendar-check me-2"></i>Book Rehabilitation
                </a>
                </div>
            </div>
            <div class="hero-right">
                <div class="hero-image-container">
                    <div class="hero-character-display">
                        <img src="<?= asset('images/Gamby.png') ?>" alt="Gamby - Your Recovery Companion" class="hero-character">
                        <div class="character-name">Gamby</div>
                    </div>
                    <div class="hero-decorative-circle-2"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ABOUT SECTION -->
<section class="landing-about" id="about">
    <div class="container">
        <div class="about-grid">
            <div class="about-left">
                <div class="about-image-wrapper">
                    <div class="about-logo-container">
                        <img src="<?= asset('images/Logo.png') ?>" alt="Recovery Journey" class="about-logo">
                        <div class="about-logo-bg"></div>
                    </div>
                </div>
            </div>
            <div class="about-right">
                <h2 class="section-title">
                    Where <span class="text-maroon">Recovery</span> Meets 
                    <span class="text-gold">Resposibility</span>
                </h2>
                <p class="about-text">
                    Born from a deep commitment to helping individuals overcome gambling addiction, 
                    Gambytes brings you professional recovery services with a modern approach. 
                    Each program is tailored to your needs, each session connects you to a path of healing.
                </p>
                <p class="about-text">
                    Like the journey of recovery itself, our approach represents the harmony between 
                    evidence-based treatment and compassionate care, creating transformative experiences 
                    that honor your strength while embracing proven recovery methods.
                </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SERVICES SECTION -->
<section class="landing-services" id="services">
    <div class="container">
        <h2 class="section-title-center">Why Choose <span class="text-maroon">Gambytes</span>?</h2>
        
        <div class="services-grid">
            <div class="service-card">
                <div class="service-icon-wrapper">
                    <div class="service-icon">
                        <i class="fas fa-hospital-user"></i>
                    </div>
                </div>
                <h3 class="service-title">Rehabilitation Program</h3>
                <p class="service-description">
                    Comprehensive inpatient and outpatient rehabilitation programs designed for lasting recovery
                </p>
            </div>
            
            <div class="service-card">
                <div class="service-icon-wrapper">
                    <div class="service-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
                <h3 class="service-title">Easy Booking</h3>
                <p class="service-description">
                    Quick and confidential appointment scheduling for counseling and treatment sessions
                </p>
            </div>
            
            <div class="service-card">
                <div class="service-icon-wrapper">
                    <div class="service-icon">
                        <i class="fas fa-ban"></i>
                    </div>
                </div>
                <h3 class="service-title">Self-Exclusion Support</h3>
                <p class="service-description">
                    Professional assistance with self-exclusion programs from online and physical gambling venues
                </p>
            </div>
            
            <div class="service-card">
                <div class="service-icon-wrapper">
                    <div class="service-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                </div>
                <h3 class="service-title">Professional Counseling</h3>
                <p class="service-description">
                    Licensed therapists specializing in gambling addiction and behavioral therapy
                </p>
            </div>
            
            <div class="service-card">
                <div class="service-icon-wrapper">
                    <div class="service-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <h3 class="service-title">Support Groups</h3>
                <p class="service-description">
                    Connect with others on the same journey through our moderated support communities
                </p>
            </div>
            
            <div class="service-card">
                <div class="service-icon-wrapper">
                    <div class="service-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                </div>
                <h3 class="service-title">24/7 Crisis Support</h3>
                <p class="service-description">
                    Round-the-clock emergency support for those experiencing gambling urges or crisis
                </p>
            </div>
        </div>
    </div>
</section>

<!-- CTA SECTION -->
<section class="landing-cta">
    <div class="container">
        <div class="cta-content">
            <h2 class="cta-title">Ready to Start Your Recovery Journey?</h2>
            <p class="cta-subtitle">Take the first step today. Your future self will thank you.</p>
            <a href="<?= url('app/views/auth/register.php') ?>" class="btn-cta">
                <i class="fas fa-calendar-plus me-2"></i>Book Your First Session
            </a>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="landing-footer" id="contact">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <h3 class="footer-title">Gambytes</h3>
                <p class="footer-text">
                    Your trusted partner for gambling addiction recovery and rehabilitation services.
                </p>
            </div>
            
            <div class="footer-col">
                <h4 class="footer-heading">Services</h4>
                <ul class="footer-links">
                    <li><a href="#"><i class="fas fa-angle-right"></i> Rehabilitation Programs</a></li>
                    <li><a href="#"><i class="fas fa-angle-right"></i> Self-Exclusion</a></li>
                    <li><a href="#"><i class="fas fa-angle-right"></i> Counseling</a></li>
                    <li><a href="#"><i class="fas fa-angle-right"></i> Support Groups</a></li>
                </ul>
            </div>
            
            <div class="footer-col">
                <h4 class="footer-heading">Support</h4>
                <ul class="footer-links">
                    <li><a href="#"><i class="fas fa-angle-right"></i> Help Center</a></li>
                    <li><a href="#"><i class="fas fa-angle-right"></i> Contact Us</a></li>
                    <li><a href="#"><i class="fas fa-angle-right"></i> FAQ</a></li>
                    <li><a href="#"><i class="fas fa-angle-right"></i> Resources</a></li>
                </ul>
            </div>
            
            <div class="footer-col">
                <h4 class="footer-heading">Connect</h4>
                <ul class="footer-links">
                    <li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li>
                    <li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li>
                    <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                    <li><a href="#"><i class="fas fa-envelope"></i> Newsletter</a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-contact">
            <div class="contact-item">
                <i class="fas fa-phone"></i>
                <span>+63 992 788 6336</span>
            </div>
            <div class="contact-item">
                <i class="fas fa-envelope"></i>
                <span>davedelacerna09@gmail.com</span>
            </div>
            <div class="contact-item">
                <i class="fas fa-map-marker-alt"></i>
                <span>Bago Saka, Bago Gallera, Talomo, Davao City 8000</span>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; 2026 Gambytes. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Smooth Scroll -->
<script>
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
</script>

</body>
</html>
