<?php
session_start();
require_once "../../core/Database.php";

$db = new Database();
$conn = $db->connect();

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    // Check if token exists and is valid
    $sql = "SELECT * FROM users WHERE reset_token='$token' AND reset_expiry > '" . date('Y-m-d H:i:s') . "'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $confirm_password = $_POST['confirm_password'];
            
            if ($_POST['password'] === $confirm_password) {
                // Update password and clear reset token
                $update_sql = "UPDATE users SET password='$new_password', reset_token=NULL, reset_expiry=NULL WHERE id='" . $user['id'] . "'";
                if ($conn->query($update_sql)) {
                    $_SESSION['message'] = "Password has been reset successfully. You can now login.";
                    header("Location: login.php");
                } else {
                    $error = "Error updating password.";
                }
            } else {
                $error = "Passwords do not match.";
            }
        }
        ?>
        
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <title>Reset Password - Gambytes</title>
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
                    <h2>Create New Password</h2>
                    
                    <?php if (isset($error)): ?>
                        <p style="color: red; margin-bottom: 15px;"><?php echo $error; ?></p>
                    <?php endif; ?>

                    <input type="password" name="password" placeholder="New Password" required pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&]{8,}" title="Must contain at least one uppercase letter, one lowercase letter, one number, and be at least 8 characters long">
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" required pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&]{8,}" title="Must contain at least one uppercase letter, one lowercase letter, one number, and be at least 8 characters long">
                    
                    <small style="color: #666; font-size: 12px; margin-top: 5px;">
                        Password must contain at least: one uppercase letter, one lowercase letter, one number, and be at least 8 characters long
                    </small>

                    <button type="submit">Reset Password</button>

                    <p><a href="login.php">Back to Login</a></p>
                </form>
            </div>
        </body>
        </html>
        <?php
    } else {
        $_SESSION['error'] = "Invalid or expired reset token.";
        header("Location: forgot_password.php");
    }
} else {
    $_SESSION['error'] = "No reset token provided.";
    header("Location: forgot_password.php");
}
?>
