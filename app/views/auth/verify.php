<?php
require_once __DIR__ . '/../../../includes/session_config.php';
require_once __DIR__ . '/../../../includes/url_helper.php';
require_once "../../core/Database.php";

$db = new Database();
$conn = $db->connect();

$verification_success = false;
$error_message = "";

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // SECURE: Using prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, email, first_name FROM users WHERE verification_token = ? AND is_verified = 0");
    if (!$stmt) {
        $error_message = "Database error occurred";
    } else {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Get user info
            $user = $result->fetch_assoc();
            $user_id = $user['id'];
            $user_email = $user['email'];
            $user_name = $user['first_name'];
            
            // Verify the user
            $updateStmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE verification_token = ?");
            if ($updateStmt) {
                $updateStmt->bind_param('s', $token);
                if ($updateStmt->execute()) {
                    $verification_success = true;
                    
                    // Automatically log the user in
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['email'] = $user_email;
                    $_SESSION['message'] = "Your account has been verified successfully!";
                    
                    $updateStmt->close();
                } else {
                    $error_message = "Verification failed. Please try again.";
                    $updateStmt->close();
                }
            } else {
                $error_message = "Database error occurred";
            }
            $stmt->close();
        } else {
            $error_message = "Invalid or expired verification link.";
            $stmt->close();
        }
    }
} else {
    $error_message = "No verification token provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Gambytes</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= asset('style.css') ?>?v=<?= time() ?>">
    
    <style>
        .verification-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #fff9e6 0%, #ffe4b3 100%);
            padding: 2rem;
        }
        
        .verification-modal {
            background: var(--pure-white);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
            padding: 3rem;
            text-align: center;
            animation: modalSlideIn 0.5s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .verification-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #800000 0%, #5c0000 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            animation: iconPulse 1s ease-in-out;
        }
        
        .verification-icon.success {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }
        
        .verification-icon.error {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        @keyframes iconPulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }
        
        .verification-icon i {
            font-size: 4rem;
            color: var(--pure-white);
        }
        
        .verification-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark-gray);
            margin-bottom: 1rem;
        }
        
        .verification-message {
            font-size: 1.1rem;
            color: var(--secondary-gray);
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        
        .countdown-text {
            font-size: 0.9rem;
            color: var(--secondary-gray);
            margin-top: 1.5rem;
        }
        
        .countdown-number {
            font-weight: 700;
            color: #800000;
        }
        
        .checkmark-circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 2;
            stroke-miterlimit: 10;
            stroke: #28a745;
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }
        
        .checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: block;
            stroke-width: 3;
            stroke: #fff;
            stroke-miterlimit: 10;
            margin: 0 auto;
            box-shadow: inset 0px 0px 0px #28a745;
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }
        
        .checkmark-check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }
        
        @keyframes stroke {
            100% {
                stroke-dashoffset: 0;
            }
        }
        
        @keyframes scale {
            0%, 100% {
                transform: none;
            }
            50% {
                transform: scale3d(1.1, 1.1, 1);
            }
        }
        
        @keyframes fill {
            100% {
                box-shadow: inset 0px 0px 0px 30px #28a745;
            }
        }
    </style>
</head>
<body>

<div class="verification-container">
    <div class="verification-modal">
        <?php if ($verification_success): ?>
            <!-- Success State -->
            <div class="verification-icon success">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="verification-title">Account Verified!</h1>
            <p class="verification-message">
                Congratulations <?= isset($user_name) ? htmlspecialchars($user_name) : '' ?>! 🎉<br>
                Your email has been successfully verified. Let's set up your account by selecting your role.
            </p>
            <a href="<?= url('app/views/auth/role_selection.php') ?>" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-user-tag me-2"></i>Continue to Role Selection
            </a>
            <p class="countdown-text">
                Redirecting to role selection in <span class="countdown-number" id="countdown">5</span> seconds...
            </p>
        <?php else: ?>
            <!-- Error State -->
            <div class="verification-icon error">
                <i class="fas fa-times"></i>
            </div>
            <h1 class="verification-title">Verification Failed</h1>
            <p class="verification-message">
                <?= htmlspecialchars($error_message) ?><br>
                The verification link may have expired or is invalid. Please try registering again or contact support.
            </p>
            <a href="<?= url('app/views/auth/register.php') ?>" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">
                <i class="fas fa-user-plus me-2"></i>Register Again
            </a>
            <a href="<?= url('app/views/auth/login.php') ?>" class="btn btn-secondary" style="width: 100%;">
                <i class="fas fa-sign-in-alt me-2"></i>Back to Login
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($verification_success): ?>
<script>
    // Countdown and auto-redirect to role selection
    let countdown = 5;
    const countdownElement = document.getElementById('countdown');
    
    const timer = setInterval(() => {
        countdown--;
        countdownElement.textContent = countdown;
        
        if (countdown <= 0) {
            clearInterval(timer);
            window.location.href = '<?= url('app/views/auth/role_selection.php') ?>';
        }
    }, 1000);
</script>
<?php endif; ?>

</body>
</html>
