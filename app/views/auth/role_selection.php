<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once "../../core/Database.php";
$db = new Database();
$conn = $db->connect();

$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id='$user_id'";
$result = $conn->query($sql);
$user = $result->fetch_assoc();

// Check if email is verified (should only be on this page during registration after email verification)
if ($user['is_verified'] == 0) {
    $_SESSION['error'] = "Please verify your email first.";
    header("Location: login.php");
    exit();
}

// If role already set, redirect to dashboard
if ($user['role'] !== NULL && $user['role'] !== '') {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role = $_POST['role'];
    
    $update_sql = "UPDATE users SET role='$role' WHERE id='$user_id'";
    if ($conn->query($update_sql)) {
        $_SESSION['role'] = $role;
        $_SESSION['message'] = "Role selected successfully!";
        header("Location: dashboard.php");
    } else {
        echo "Error updating role: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Select Role - Gambytes</title>
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
        <a href="logout.php">Logout</a>
    </div>
</header>

<div class="auth-container">
    <form class="auth-box" method="POST" action="">
        <h2>Select Your Role</h2>
        
        <p>Welcome, <?php echo $user['first_name'] . ' ' . $user['last_name']; ?>! Please select your role in the system.</p>
        
        <select name="role" required>
            <option value="">Select Role</option>
            <option value="gambler">Online Gambler</option>
            <option value="family">Family Member</option>
            <option value="admin">Admin Department</option>
            <option value="case_manager">Case Manager</option>
            <option value="nurse">Nurse</option>
            <option value="supervisor">Supervisor</option>
        </select>

        <button type="submit">Confirm Role</button>
    </form>
</div>

</body>
</html>