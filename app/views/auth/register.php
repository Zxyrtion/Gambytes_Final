<?php
require_once __DIR__ . '/../../../includes/session_config.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/url_helper.php';
require_once __DIR__ . '/../../../includes/validation.php';
require_once "../../core/Database.php";
require_once "../../../PHPMailer-master/src/PHPMailer.php";
require_once "../../../PHPMailer-master/src/SMTP.php";
require_once "../../../PHPMailer-master/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail($email, $first_name, $token) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'davedelacerna09@gmail.com'; // Replace with your Gmail
        $mail->Password   = 'bwhx equz ltqn fdks'; // Your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('davedelacerna09@gmail.com', 'Gambytes');
        $mail->addAddress($email, $first_name);
        
        // Content - Generate full URL with domain
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $verification_link = $protocol . '://' . $host . '/GAMBYTES_Final/app/views/auth/verify.php?token=' . $token;
        
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email Address - Gambytes';
        $mail->Body    = '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                                <div style="background-color: #800000; padding: 20px; text-align: center; color: #fff;">
                                    <h2 style="margin: 0;">Welcome to Gambytes!</h2>
                                </div>
                                <div style="background-color: #f8f8f8; padding: 30px; text-align: center;">
                                    <div style="background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); display: inline-block; max-width: 600px; width: 100%;">
                                        <h3 style="color: #800000;">Hello ' . htmlspecialchars($first_name) . ',</h3>
                                        <p>Thank you for registering with Gambytes. To complete your registration and activate your account, please verify your email address by clicking the button below:</p>
                                        <p style="margin: 30px 0;">
                                            <a href="' . $verification_link . '" style="background-color: #800000; color: #ffffff; padding: 12px 25px; border-radius: 5px; text-decoration: none; font-weight: bold;">Verify Email Address</a>
                                        </p>
                                        <p style="margin-top: 20px; font-size: 13px; color: #666;">Or copy and paste this link:</p>
                                        <p style="word-break: break-all; color: #800000; font-size: 12px; background: #f5f5f5; padding: 10px; border-radius: 5px;">' . $verification_link . '</p>
                                        <p>This link will expire in 24 hours.</p>
                                        <p>If you did not create an account, please ignore this email.</p>
                                        <p style="font-size: 12px; color: #888; margin-top: 30px;">Best regards,<br>The Gambytes Team</p>
                                    </div>
                                </div>';
        
        $mail->send();
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}

$db = new Database();
$conn = $db->connect();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    requireCsrfToken();
    
    $first_name = $_POST['first_name'] ?? '';
    $middle_name = $_POST['middle_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    $validator = new Validator();
    $validator->required($first_name, 'First Name');
    $validator->required($last_name, 'Last Name');
    $validator->required($email, 'Email');
    $validator->email($email, 'Email');
    $validator->required($password, 'Password');
    $validator->password($password);
    $validator->matches($password, $confirm_password, 'Passwords');
    
    if ($validator->passes()) {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $_SESSION['error'] = "Email address is already registered";
                $stmt->close();
            } else {
                $stmt->close();
                
                // Create new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $verification_token = bin2hex(random_bytes(32));
                
                $insertStmt = $conn->prepare("INSERT INTO users (first_name, middle_name, last_name, email, password, role, verification_token, is_verified) VALUES (?, ?, ?, ?, ?, NULL, ?, 0)");
                
                if ($insertStmt) {
                    $insertStmt->bind_param('ssssss', $first_name, $middle_name, $last_name, $email, $hashed_password, $verification_token);
                    
                    if ($insertStmt->execute()) {
                        // Send verification email
                        sendVerificationEmail($email, $first_name, $verification_token);
                        
                        $_SESSION['message'] = "Registration successful! Please check your email to verify your account.";
                        $insertStmt->close();
                        header("Location: login.php");
                        exit();
                    } else {
                        $_SESSION['error'] = "Registration failed. Please try again.";
                        $insertStmt->close();
                    }
                } else {
                    $_SESSION['error'] = "Database error occurred";
                }
            }
        } else {
            $_SESSION['error'] = "Database error occurred";
        }
    } else {
        $_SESSION['error'] = $validator->getFirstError();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Gambytes</title>
    
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
                <a href="<?= url('app/views/auth/login.php') ?>" class="btn btn-outline">Login</a>
            </div>
        </div>
    </nav>

    <!-- Modern Auth Container -->
    <div class="auth-container">
        <div class="auth-box-wide fade-in-up">
            <!-- Left Side - Registration Form -->
            <div class="auth-left">
                <h2><i class="fas fa-user-plus me-2"></i>Create Account</h2>
                <p class="subtitle">Join Gambytes to start your recovery journey</p>
                
                <!-- Alert Messages -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($_SESSION['error']) ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Registration Form -->
                <form method="POST" action="">
                    <?= csrfField() ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="fas fa-user text-muted"></i>
                                    </span>
                                    <input type="text" 
                                           name="first_name" 
                                           class="form-control" 
                                           placeholder="First Name" 
                                           required 
                                           autocomplete="given-name">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="fas fa-user text-muted"></i>
                                    </span>
                                    <input type="text" 
                                           name="last_name" 
                                           class="form-control" 
                                           placeholder="Last Name" 
                                           required 
                                           autocomplete="family-name">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="fas fa-user text-muted"></i>
                            </span>
                            <input type="text" 
                                   name="middle_name" 
                                   class="form-control" 
                                   placeholder="Middle Name (Optional)" 
                                   autocomplete="additional-name">
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="fas fa-envelope text-muted"></i>
                            </span>
                            <input type="email" 
                                   name="email" 
                                   class="form-control" 
                                   placeholder="Email Address" 
                                   required 
                                   autocomplete="email">
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" 
                                   name="password" 
                                   id="register_password" 
                                   class="form-control border-end-0" 
                                   placeholder="Password" 
                                   required 
                                   pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&]{8,}"
                                   title="Must contain at least one uppercase letter, one lowercase letter, one number, and be at least 8 characters long"
                                   autocomplete="new-password">
                            <button type="button" 
                                    class="btn btn-outline-secondary" 
                                    onclick="togglePassword('register_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" 
                                   name="confirm_password" 
                                   id="confirm_password" 
                                   class="form-control border-end-0" 
                                   placeholder="Confirm Password" 
                                   required 
                                   autocomplete="new-password">
                            <button type="button" 
                                    class="btn btn-outline-secondary" 
                                    onclick="togglePassword('confirm_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>
                        Create Account
                    </button>
                </form>

                <!-- Auth Links -->
                <div class="auth-links">
                    <p class="mb-0">
                        Already have an account? 
                        <a href="<?= url('app/views/auth/login.php') ?>">
                            <strong>Sign In</strong>
                        </a>
                    </p>
                </div>
            </div>
            
            <!-- Right Side - Branding -->
            <div class="auth-right">
                <div class="auth-right-content">
                    <img src="<?= asset('images/Logo.png') ?>" alt="Gambytes Logo" class="logo-large">
                    <h2>Join Gambytes</h2>
                    <p>Start your journey to recovery today. Our professional team is ready to support you every step of the way.</p>
                    
                    <div class="feature-list">
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Free Registration</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Expert Guidance</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Supportive Community</span>
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

        // Password strength indicator
        document.getElementById('register_password').addEventListener('input', function() {
            const password = this.value;
            const confirmPassword = document.getElementById('confirm_password');
            
            // Check password strength
            const hasUpper = /[A-Z]/.test(password);
            const hasLower = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasLength = password.length >= 8;
            
            if (hasUpper && hasLower && hasNumber && hasLength) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
            
            // Check password match
            if (confirmPassword.value && password !== confirmPassword.value) {
                confirmPassword.classList.remove('is-valid');
                confirmPassword.classList.add('is-invalid');
            } else if (confirmPassword.value && password === confirmPassword.value) {
                confirmPassword.classList.remove('is-invalid');
                confirmPassword.classList.add('is-valid');
            }
        });

        // Confirm password validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('register_password').value;
            const confirmPassword = this.value;
            
            if (password && confirmPassword && password === confirmPassword) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-error');
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