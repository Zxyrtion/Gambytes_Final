<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /GAMBYTES_Final/app/views/auth/login.php");
    exit();
}

// Get user information from database
require_once __DIR__ . '/../../../core/Database.php';
$db = new Database();
$conn = $db->connect();

$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id='$user_id'";
$result = $conn->query($sql);
$user = $result->fetch_assoc();
$full_name = $user['first_name'] . ' ' . $user['last_name'];
$email = $user['email'] ?? '';
$phone = $user['phone'] ?? '';

// Include Calendly Service
require_once __DIR__ . '/../../../../includes/CalendlyService.php';
$calendlyService = new CalendlyService();

// Get available event types
try {
    $eventTypes = $calendlyService->getEventTypes();
    
    // Filter for rehabilitation/consultation types
    $rehabEventTypes = array_filter($eventTypes['collection'] ?? [], function($eventType) {
        $name = strtolower($eventType['name'] ?? '');
        return strpos($name, 'consultation') !== false || 
               strpos($name, 'rehab') !== false || 
               strpos($name, 'therapy') !== false ||
               strpos($name, 'free') !== false;
    });
    
    // If no specifically labeled rehabilitation services exist, show all available event types.
    if (empty($rehabEventTypes)) {
        $rehabEventTypes = $eventTypes['collection'] ?? [];
        $showAllEventTypes = true;
    } else {
        $showAllEventTypes = false;
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    $rehabEventTypes = [];
    $showAllEventTypes = false;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Rehabilitation - Gambytes</title>
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <h3>Dashboard</h3>
            <ul class="sidebar-menu">
                <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
                <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/direct-calendly.php" class="active"><i class="fas fa-calendar-check"></i> Book Rehabilitation</a></li>
                <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php#settings"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php#reports"><i class="fas fa-chart-line"></i> Reports</a></li>
                <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php#messages"><i class="fas fa-envelope"></i> Messages</a></li>
                <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php#notifications"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="/GAMBYTES_Final/app/views/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <div class="main-content booking-page">
            <div class="content-card booking-card">
                <div class="booking-header">
                    <h2 class="booking-title"><i class="fas fa-calendar-check"></i> Book Rehabilitation Consultation</h2>
                    <p class="booking-welcome">Welcome <strong><?php echo htmlspecialchars($full_name); ?></strong>! Select your preferred consultation service.</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> Error: <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($rehabEventTypes)): ?>
                    <?php if (!empty($showAllEventTypes)): ?>

                    <?php endif; ?>

                    <div class="booking-grid">
                        <?php foreach ($rehabEventTypes as $eventType): ?>
                            <div class="booking-card" onclick="redirectToCalendly('<?php echo htmlspecialchars($eventType['scheduling_url']); ?>')">
                                <h4><?php echo htmlspecialchars($eventType['name']); ?></h4>
                                <p><i class="fas fa-clock"></i> Duration: <?php echo htmlspecialchars($eventType['duration_minutes'] ?? ''); ?> Hours</p>
                                <p><i class="fas fa-info-circle"></i> Click to continue booking on Calendly.</p>
                                <button type="button" class="btn-primary booking-button" onclick="event.stopPropagation(); redirectToCalendly('<?php echo htmlspecialchars($eventType['scheduling_url']); ?>')">
                                    <i class="fas fa-calendar-plus"></i> Book Schedule
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="margin-top: 40px; padding: 20px; background: #e3f2fd; border-radius: 8px; border-left: 4px solid #2196F3;">
                        <h4><i class="fas fa-info-circle"></i> After Booking</h4>
                        <p>After you complete your booking on Calendly, you will be automatically redirected back here to confirm your booking with our system.</p>
                        <p style="margin-top: 10px; font-size: 14px; color: #555;">
                            <i class="fas fa-clock"></i> You can also manually access the confirmation page by clicking the button below:
                        </p>
                        <a href="/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php" class="btn-primary" style="display: inline-block; margin-top: 10px; padding: 12px 25px;">
                            <i class="fas fa-sync-alt"></i> Check Booking Status
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> No rehabilitation services are currently available. Please try again later.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function redirectToCalendly(url) {
            if (!url) {
                alert('Calendly booking link is not available for this service.');
                return;
            }
            // Redirect to Calendly
            window.location.href = url;
        }
        
        // After returning from Calendly, redirect to booking confirmation
        window.addEventListener('focus', function() {
            // Check if we just returned from Calendly (browser was in background)
            const returnedFromCalendly = sessionStorage.getItem('bookingStartTime');
            if (returnedFromCalendly && Date.now() - parseInt(returnedFromCalendly) < 300000) { // 5 minutes
                // Redirect to confirmation page
                setTimeout(function() {
                    window.location.href = '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php';
                }, 1500);
            }
        });
        
        // Mark booking start time when clicking a booking button
        document.querySelectorAll('.btn-primary.booking-button').forEach(button => {
            button.addEventListener('click', function() {
                sessionStorage.setItem('bookingStartTime', Date.now().toString());
            });
        });
    </script>
</body>
</html>
