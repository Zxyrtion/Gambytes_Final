<?php
session_start();
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
        
        // Content
        $verification_link = "http://localhost/GAMBYTES_FINAL/app/views/auth/verify.php?token=" . $token;
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email Address - Gambytes';
        $mail->Body    = '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                                <div style="background-color: #d32f2f; padding: 20px; text-align: center; color: #fff;">
                                    <h2 style="margin: 0;">Welcome to Gambytes!</h2>
                                </div>
                                <div style="background-color: #f8f8f8; padding: 30px; text-align: center;">
                                    <div style="background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); display: inline-block; max-width: 600px; width: 100%;">
                                        <h3 style="color: #d32f2f;">Hello ' . $first_name . ',</h3>
                                        <p>Thank you for registering with Gambytes. To complete your registration and activate your account, please verify your email address by clicking the button below:</p>
                                        <p style="margin: 30px 0;">
                                            <a href="' . $verification_link . '" style="background-color: #d32f2f; color: #ffffff; padding: 12px 25px; border-radius: 5px; text-decoration: none; font-weight: bold;">Verify Email Address</a>
                                        </p>
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

    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Generate verification token
    $verification_token = bin2hex(random_bytes(32));
    $is_verified = 0;

    $sql = "INSERT INTO users (first_name, middle_name, last_name, email, password, role, verification_token, is_verified)
            VALUES ('$first_name', '$middle_name', '$last_name', '$email', '$password', NULL, '$verification_token', '$is_verified')";

    if ($conn->query($sql)) {
        // Send verification email
        sendVerificationEmail($email, $first_name, $verification_token);
        
        $_SESSION['message'] = "Registration successful! Please check your email to verify your account and select your role.";
        header("Location: login.php");
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Register - Gambytes</title>
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
</head>
<body>

<script>
function togglePassword() {
    const passwordField = document.getElementById('password');
    const toggleBtn = event.target;
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleBtn.textContent = '';
    } else {
        passwordField.type = 'password';
        toggleBtn.textContent = '';
    }
}
</script>

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
        <h2>Create Account</h2>

        <input type="text" name="first_name" placeholder="First Name" required>
        <input type="text" name="middle_name" placeholder="Middle Name" required>
        <input type="text" name="last_name" placeholder="Last Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&]{6,}" title="Must contain at least one uppercase letter, one lowercase letter, one number, and be at least 6 characters long">
        
        <small style="color: #666; font-size: 12px; margin-top: 5px;">
            Password must contain at least: one uppercase letter, one lowercase letter, one number, and be at least 6 characters long
        </small>

        <button type="submit">Register</button>

        <p>Already have an account? <a href="login.php">Login</a></p>
    </form>
</div>

</body>
</html>