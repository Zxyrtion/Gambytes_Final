<?php
session_start();
require_once "../../core/Database.php";

$db = new Database();
$conn = $db->connect();

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Find user with this token
    $sql = "SELECT id FROM users WHERE verification_token='$token' AND is_verified=0";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        // Get user ID
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        
        // Verify the user
        $update_sql = "UPDATE users SET is_verified=1, verification_token=NULL WHERE verification_token='$token'";
        if ($conn->query($update_sql)) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['message'] = "Email verified successfully! Please select your role.";
            header("Location: role_selection.php");
        } else {
            $_SESSION['error'] = "Verification failed. Please try again.";
            header("Location: login.php");
        }
    } else {
        $_SESSION['error'] = "Invalid or expired verification link.";
        header("Location: login.php");
    }
} else {
    $_SESSION['error'] = "No verification token provided.";
    header("Location: login.php");
}
?>
