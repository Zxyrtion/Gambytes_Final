<?php
require_once __DIR__ . '/../../../includes/session_config.php';
require_once __DIR__ . '/../../../includes/url_helper.php';
require_once __DIR__ . '/../../../includes/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . url('app/views/auth/login.php'));
    exit();
}

require_once "../../core/Database.php";
$db = new Database();
$conn = $db->connect();

$user_id = $_SESSION['user_id'];

// SECURE: Using prepared statement to prevent SQL injection
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
if (!$stmt) {
    die("Database error occurred");
}

$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header("Location: " . url('app/views/auth/login.php'));
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Check if email is verified
if ($user['is_verified'] == 0) {
    $_SESSION['error'] = "Please verify your email first.";
    header("Location: " . url('app/views/auth/login.php'));
    exit();
}

// If role already set, redirect to dashboard
if ($user['role'] !== NULL && $user['role'] !== '') {
    header("Location: " . url('app/views/auth/dashboard.php'));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    requireCsrfToken();
    
    $role = $_POST['role'] ?? '';
    
    if (empty($role)) {
        $_SESSION['error'] = "Please select a role";
    } else {
        // SECURE: Using prepared statement
        $updateStmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        if ($updateStmt) {
            $updateStmt->bind_param('si', $role, $user_id);
            if ($updateStmt->execute()) {
                $_SESSION['role'] = $role;
                $_SESSION['message'] = "Role selected successfully!";
                $updateStmt->close();
                header("Location: " . url('app/views/auth/dashboard.php'));
                exit();
            } else {
                $_SESSION['error'] = "Error updating role";
                $updateStmt->close();
            }
        } else {
            $_SESSION['error'] = "Database error occurred";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Your Role - Gambytes</title>
    
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
                <a href="<?= url('app/views/auth/logout.php') ?>"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
            </div>
        </div>
    </nav>

    <!-- Role Selection Container -->
    <div class="auth-container">
        <div class="auth-box fade-in-up" style="max-width: 700px;">
            <h2><i class="fas fa-user-tag me-2"></i>Select Your Role</h2>
            <p class="text-center text-muted mb-4">
                Welcome, <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>! 
                Please select your role to continue.
            </p>
            
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

            <!-- Role Selection Form -->
            <form method="POST" action="">
                <?= csrfField() ?>
                
                <div class="role-grid">
                    <label class="role-card">
                        <input type="radio" name="role" value="gambler" required>
                        <div class="role-content">
                            <div class="role-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <h3>Online Gambler</h3>
                            <p>Seeking help for gambling addiction</p>
                        </div>
                    </label>
                    
                    <label class="role-card">
                        <input type="radio" name="role" value="family" required>
                        <div class="role-content">
                            <div class="role-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3>Family Member</h3>
                            <p>Supporting a loved one's recovery</p>
                        </div>
                    </label>
                    
                    <label class="role-card">
                        <input type="radio" name="role" value="admin" required>
                        <div class="role-content">
                            <div class="role-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <h3>Admin Department</h3>
                            <p>Administrative staff member</p>
                        </div>
                    </label>
                    
                    <label class="role-card">
                        <input type="radio" name="role" value="case_manager" required>
                        <div class="role-content">
                            <div class="role-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <h3>Case Manager</h3>
                            <p>Managing patient cases</p>
                        </div>
                    </label>
                    
                    <label class="role-card">
                        <input type="radio" name="role" value="nurse" required>
                        <div class="role-content">
                            <div class="role-icon">
                                <i class="fas fa-user-nurse"></i>
                            </div>
                            <h3>Nurse Staff</h3>
                            <p>Providing medical care</p>
                        </div>
                    </label>
                    
                    <label class="role-card">
                        <input type="radio" name="role" value="executive_assistant" required>
                        <div class="role-content">
                            <div class="role-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <h3>Executive Assistant</h3>
                            <p>Contract verification & administrative oversight</p>
                        </div>
                    </label>
                    
                    <label class="role-card">
                        <input type="radio" name="role" value="supervisor" required>
                        <div class="role-content">
                            <div class="role-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <h3>Supervisor</h3>
                            <p>Overseeing operations</p>
                        </div>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check me-2"></i>
                    Confirm Role
                </button>
            </form>
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
