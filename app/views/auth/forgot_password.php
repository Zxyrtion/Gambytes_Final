<?php
require_once __DIR__ . '/../../../includes/session_config.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/url_helper.php';
require_once "../../core/Database.php";
require_once "../../../PHPMailer-master/src/PHPMailer.php";
require_once "../../../PHPMailer-master/src/SMTP.php";
require_once "../../../PHPMailer-master/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    requireCsrfToken();
    
    $email = $_POST['email'] ?? '';
    
    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address";
        header("Location: forgot_password.php");
        exit();
    }
    
    // Check if email exists
    $db = new Database();
    $conn = $db->connect();
    
    // SECURE: Using prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    if (!$stmt) {
        $_SESSION['error'] = "Database error occurred";
        header("Location: forgot_password.php");
        exit();
    }
    
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Generate reset token
        $reset_token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime('+1 hour'));
        
        // Update user with reset token
        $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE email = ?");
        if ($updateStmt) {
            $updateStmt->bind_param('sss', $reset_token, $expiry, $email);
            if ($updateStmt->execute()) {
                // Send reset email
                sendPasswordResetEmail($email, $reset_token);
                
                $_SESSION['message'] = "Password reset link has been sent to your email.";
                $updateStmt->close();
                $stmt->close();
                header("Location: login.php");
                exit();
            } else {
                $_SESSION['error'] = "Error generating reset token.";
                $updateStmt->close();
            }
        } else {
            $_SESSION['error'] = "Database error occurred";
        }
    } else {
        $_SESSION['error'] = "Email address not found.";
    }
    $stmt->close();
    header("Location: forgot_password.php");
    exit();
}

function sendPasswordResetEmail($email, $token) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'davedelacerna09@gmail.com';
        $mail->Password   = 'bwhx equz ltqn fdks';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('davedelacerna09@gmail.com', 'Gambytes');
        $mail->addAddress($email);
        
        // Content - Generate full URL with domain
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $reset_link = $protocol . '://' . $host . '/GAMBYTES_Final/app/views/auth/reset_password.php?token=' . $token;
        
        $mail->isHTML(true);
        $mail->Subject = 'Reset Your Gambytes Password';
        $mail->Body    = '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                                <div style="background-color: #800000; padding: 20px; text-align: center; color: #fff;">
                                    <h2 style="margin: 0;">Password Reset Request</h2>
                                </div>
                                <div style="background-color: #f8f8f8; padding: 30px; text-align: center;">
                                    <div style="background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); display: inline-block; max-width: 600px; width: 100%;">
                                        <h3 style="color: #800000;">Password Reset Request</h3>
                                        <p>You requested to reset your password. Click the button below to create a new password:</p>
                                        <p style="margin: 30px 0;">
                                            <a href="' . $reset_link . '" style="background-color: #800000; color: #ffffff; padding: 12px 25px; border-radius: 5px; text-decoration: none; font-weight: bold;">Reset Password</a>
                                        </p>
                                        <p style="margin-top: 20px; font-size: 13px; color: #666;">Or copy and paste this link:</p>
                                        <p style="word-break: break-all; color: #800000; font-size: 12px; background: #f5f5f5; padding: 10px; border-radius: 5px;">' . $reset_link . '</p>
                                        <p>This link will expire in 1 hour.</p>
                                        <p>If you did not request this reset, please ignore this email.</p>
                                        <p style="font-size: 12px; color: #888; margin-top: 30px;">Best regards,<br>The Gambytes Team</p>
                                    </div>
                                </div>';
        
        $mail->send();
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Gambytes</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= asset('style.css') ?>?v=<?= time() ?>">
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

    <!-- Forgot Password Container -->
    <div class="auth-container">
        <div class="auth-box fade-in-up">
            <h2><i class="fas fa-key me-2"></i>Reset Password</h2>
            <p class="text-center text-muted mb-4">Enter your email address and we'll send you a link to reset your password</p>
            
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

            <!-- Forgot Password Form -->
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

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-2"></i>
                    Send Reset Link
                </button>
            </form>

            <!-- Auth Links -->
            <div class="auth-links">
                <p class="mb-0">
                    Remember your password? 
                    <a href="<?= url('app/views/auth/login.php') ?>">
                        <strong>Back to Login</strong>
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Auto-hide alerts -->
    <script>
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
