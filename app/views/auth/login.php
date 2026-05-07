<?php
require_once __DIR__ . '/../../../includes/session_config.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/url_helper.php';
require_once "../../core/Database.php";

$db = new Database();
$conn = $db->connect();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    requireCsrfToken();

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validate inputs
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Email and password are required";
        header("Location: login.php");
        exit();
    }

    // SECURE: Using prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    if (!$stmt) {
        $_SESSION['error'] = "Database error occurred";
        header("Location: login.php");
        exit();
    }
    
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Check if email is verified
            if ($user['is_verified'] == 0) {
                $_SESSION['error'] = "Please verify your email address before logging in.";
                $stmt->close();
                header("Location: login.php");
                exit();
            }

            // Regenerate session ID for security
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            // If role not set yet, redirect to role selection
            if ($user['role'] === NULL || $user['role'] === '') {
                $stmt->close();
                header("Location: role_selection.php");
            } else {
                $stmt->close();
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $_SESSION['error'] = "Invalid password";
        }
    } else {
        $_SESSION['error'] = "User not found";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gambytes</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= asset('style.css') ?>?v=<?= time() ?>">
    <style>
        /* Force two-column layout */
        .auth-box-wide {
            display: grid !important;
            grid-template-columns: 55% 45% !important;
            max-width: 1000px !important;
        }
    </style>
</head>
<body>
    <!-- Modern Navbar -->
    <nav class="navbar">
        <div class="container">
            <a href="<?= url('app/views/auth/homepage.php') ?>" class="logo">
                <img src="<?= asset('images/Logo.png') ?>" alt="Gambytes Logo">
                Gambytes
            </a>
            <div class="nav-links">
                <a href="<?= url('app/views/auth/homepage.php') ?>">Home</a>
                <a href="<?= url('app/views/auth/register.php') ?>" class="btn btn-outline">Register</a>
            </div>
        </div>
    </nav>

    <!-- Modern Auth Container -->
    <div class="auth-container">
        <div class="auth-box-wide fade-in-up">
            <!-- Left Side - Login Form -->
            <div class="auth-left">
                <h2><i class="fas fa-sign-in-alt me-2"></i>Welcome Back</h2>
                <p class="subtitle">Sign in to your Gambytes account</p>
                
                <!-- Alert Messages -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($_SESSION['error']) ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($_SESSION['message']) ?>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" action="">
                    <?= csrfField() ?>
                    
                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-envelope text-muted"></i>
                            </span>
                            <input type="email" 
                                   name="email" 
                                   class="form-control border-start-0" 
                                   placeholder="Enter your email address" 
                                   required 
                                   autocomplete="email">
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" 
                                   name="password" 
                                   id="login_password" 
                                   class="form-control border-start-0 border-end-0" 
                                   placeholder="Enter your password" 
                                   required 
                                   autocomplete="current-password">
                            <button type="button" 
                                    class="btn btn-outline-secondary border-start-0" 
                                    onclick="togglePassword('login_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Sign In
                    </button>
                </form>

                <!-- Auth Links -->
                <div class="auth-links">
                    <p class="mb-2">
                        <a href="<?= url('app/views/auth/forgot_password.php') ?>">
                            <i class="fas fa-key me-1"></i>Forgot Password?
                        </a>
                    </p>
                    <p class="mb-0">
                        Don't have an account? 
                        <a href="<?= url('app/views/auth/register.php') ?>">
                            <strong>Create Account</strong>
                        </a>
                    </p>
                </div>
            </div>
            
            <!-- Right Side - Branding -->
            <div class="auth-right">
                <div class="auth-right-content">
                    <img src="<?= asset('images/Logo.png') ?>" alt="Gambytes Logo" class="logo-large">
                    <h2>Gambytes</h2>
                    <p>Your trusted partner in gambling addiction recovery. We're here to support your journey to a healthier, happier life.</p>
                    
                    <div class="feature-list">
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Professional Support</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Confidential & Secure</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Personalized Care</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        function togglePassword(inputId, button) {
            const passwordField = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>