<?php
session_start();
require_once "../../core/Database.php";
require_once "../../../PHPMailer-master/src/PHPMailer.php";
require_once "../../../PHPMailer-master/src/SMTP.php";
require_once "../../../PHPMailer-master/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    
    // Check if email exists
    $db = new Database();
    $conn = $db->connect();
    
    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        // Generate reset token
        $reset_token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime('+1 hour'));
        
        // Update user with reset token
        $update_sql = "UPDATE users SET reset_token='$reset_token', reset_expiry='$expiry' WHERE email='$email'";
        if ($conn->query($update_sql)) {
            // Send reset email
            sendPasswordResetEmail($email, $reset_token);
            
            $_SESSION['message'] = "Password reset link has been sent to your email.";
            header("Location: login.php");
        } else {
            $_SESSION['error'] = "Error generating reset token.";
        }
    } else {
        $_SESSION['error'] = "Email address not found.";
        header("Location: forgot_password.php");
    }
}

function sendPasswordResetEmail($email, $token) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'davedelacerna09@gmail.com'; // Your Gmail
        $mail->Password   = 'bwhx equz ltqn fdks'; // Your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('davedelacerna09@gmail.com', 'Gambytes');
        $mail->addAddress($email);
        
        // Content
        $reset_link = "http://localhost/GAMBYTES_FINAL/app/views/auth/reset_password.php?token=" . $token;
        $mail->isHTML(true);
        $mail->Subject = 'Reset Your Gambytes Password';
        $mail->Body    = '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                                <div style="background-color: #d32f2f; padding: 20px; text-align: center; color: #fff;">
                                    <h2 style="margin: 0;">Password Reset Request</h2>
                                </div>
                                <div style="background-color: #f8f8f8; padding: 30px; text-align: center;">
                                    <div style="background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); display: inline-block; max-width: 600px; width: 100%;">
                                        <h3 style="color: #d32f2f;">Password Reset Request</h3>
                                        <p>You requested to reset your password. Click the button below to create a new password:</p>
                                        <p style="margin: 30px 0;">
                                            <a href="' . $reset_link . '" style="background-color: #d32f2f; color: #ffffff; padding: 12px 25px; border-radius: 5px; text-decoration: none; font-weight: bold;">Reset Password</a>
                                        </p>
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
    <title>Forgot Password - Gambytes</title>
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
</head>
<body>

<!-- NAVBAR -->
<header class="navbar">
    <div class="logo">
        <img src="/GAMBYTES_Final/public/images/Logo.png" alt="Gambytes Logo">
        Gambytes
    </div>
    <div class="nav-links">
        <a href="homepage.php">Home</a>
        <a href="login.php">Login</a>
    </div>
</header>

<div class="auth-container">
    <form class="auth-box" method="POST" action="">
        <h2>Reset Password</h2>
        
        <?php
        if (isset($_SESSION['error'])) {
            echo '<p style="color: red; margin-bottom: 15px;">' . $_SESSION['error'] . '</p>';
            unset($_SESSION['error']);
        }
        
        if (isset($_SESSION['message'])) {
            echo '<p style="color: green; margin-bottom: 15px;">' . $_SESSION['message'] . '</p>';
            unset($_SESSION['message']);
        }
        ?>

        <input type="email" name="email" placeholder="Enter your email address" required>
        <button type="submit">Send Reset Link</button>

        <p>Remember your password? <a href="login.php">Back to Login</a></p>
    </form>
</div>

</body>
</html>
