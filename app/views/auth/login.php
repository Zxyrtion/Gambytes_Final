<?php
session_start();
require_once "../../core/Database.php";

$db = new Database();
$conn = $db->connect();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Check if email is verified
            if ($user['is_verified'] == 0) {
                $_SESSION['error'] = "Please verify your email address before logging in.";
                header("Location: login.php");
                exit();
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            // If role not set yet, redirect to role selection
            if ($user['role'] === NULL || $user['role'] === '') {
                header("Location: role_selection.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $_SESSION['error'] = "Wrong password";
        }
    } else {
        $_SESSION['error'] = "User not found";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login - Gambytes</title>
    <script>
        function toggleLoginPassword() {
            const passwordField = document.getElementById('login_password');
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
        <a href="register.php">Register</a>
    </div>
</header>

<div class="auth-container">
    <form class="auth-box" method="POST" action="">
        <h2>Login</h2>
        
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

        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" id="login_password" placeholder="Password" required>

        <button type="submit">Login</button>

        <p><a href="forgot_password.php">Forgot Password?</a></p>
        <p>Don't have an account? <a href="register.php">Register</a></p>
    </form>
</div>

</body>
</html>