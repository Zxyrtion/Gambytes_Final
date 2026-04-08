<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /GAMBYTES_Final/app/views/auth/login.php");
    exit();
}

// Get user information
require_once __DIR__ . '/../../../core/Database.php';
$db = new Database();
$conn = $db->connect();

$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id='$user_id'";
$result = $conn->query($sql);
$user = $result->fetch_assoc();
$full_name = $user['first_name'] . ' ' . $user['last_name'];
$email = $user['email'];

// Include models
require_once __DIR__ . '/../../../../includes/CalendlyService.php';
require_once __DIR__ . '/../../../models/ScheduleModel.php';

$message = '';
$error = '';
$bookingData = null;
$autoSync = false;

// Auto-sync bookings on page load
try {
    $calendlyService = new CalendlyService();
    $scheduleModel = new ScheduleModel();
    
    // Get the latest booking from Calendly API for this user's email
    $userBookings = $calendlyService->getScheduledEventsByEmail($email, ['page_size' => 1]);
    
    if (!empty($userBookings['collection'])) {
        // Get the most recent booking
        $latestBooking = $userBookings['collection'][0];
        
        // Extract relevant data from Calendly booking
        $startTime = $latestBooking['start_time'] ?? '';
        $endTime = $latestBooking['end_time'] ?? '';
        $calendlyEventUri = $latestBooking['uri'] ?? '';
        $bookedName = (!empty($latestBooking['invitee']['name'])) ? $latestBooking['invitee']['name'] : $full_name;
        $bookedEmail = (!empty($latestBooking['invitee']['email'])) ? $latestBooking['invitee']['email'] : $email;
        
        if ($startTime && $endTime && $calendlyEventUri) {
            // Check if this booking already exists in the database by event URI
            $existingBooking = $scheduleModel->getBookingByEventUri($calendlyEventUri);
            
            if ($existingBooking) {
                $message = "This booking is already synced with our system!";
                $bookingData = $existingBooking;
                $autoSync = true;
            } else {
                $bookingDataToSave = [
                    'calendly_event_uri' => $calendlyEventUri,
                    'email' => $bookedEmail,
                    'name' => $bookedName,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => 'booked'
                ];
                
                $saveResult = $scheduleModel->createBooking($bookingDataToSave);
                
                if ($saveResult['success']) {
                    $message = "Booking successfully saved to our system! Your consultation is scheduled.";
                    $bookingData = $bookingDataToSave;
                    $autoSync = true;
                } else {
                    $error = "Could not save booking: " . ($saveResult['error'] ?? 'Unknown error');
                }
            }
        } else {
            $error = "Could not retrieve complete booking details from Calendly. Please try booking again.";
        }
    } else {
        $error = "No bookings found. Please complete your booking on Calendly first.";
    }
} catch (Exception $e) {
    $error = "Error syncing booking: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - Gambytes</title>
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <h3>Dashboard</h3>
            <ul class="sidebar-menu">
                <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/direct-calendly.php" class="active"><i class="fas fa-calendar-check"></i> Book Rehabilitation</a></li>
                <li><a href="/GAMBYTES_Final/app/views/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <div class="main-content booking-page">
            <div class="content-card booking-card">
                <div class="booking-header">
                    <h2><i class="fas fa-check-circle"></i> Booking Confirmation</h2>
                    <p>Thank you for booking with us, <strong><?php echo htmlspecialchars($full_name); ?></strong>!</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <strong>Sync Issue:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                    <div style="margin-top: 20px;">
                        <p><strong>What to do:</strong></p>
                        <ul style="text-align: left; display: inline-block;">
                            <li>Make sure you completed the booking on Calendly</li>
                            <li>Try refreshing this page</li>
                            <li>If the issue persists, please go back and book again</li>
                        </ul>
                        <div style="margin-top: 20px;">
                            <button onclick="location.reload();" class="btn-primary" style="padding: 12px 25px; margin-right: 10px;">
                                <i class="fas fa-sync-alt"></i> Refresh Page
                            </button>
                            <a href="/GAMBYTES_Final/app/views/Users/Gamblers/direct-calendly.php" class="btn-secondary" style="display: inline-block; padding: 12px 25px;">
                                <i class="fas fa-arrow-left"></i> Back to Booking
                            </a>
                        </div>
                    </div>
                <?php elseif ($message && $autoSync): ?>
                    <div class="alert alert-info" style="background: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                    </div>
                    <?php if ($bookingData): ?>
                        <div style="margin-top: 25px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                            <h4><i class="fas fa-calendar"></i> Your Booking Details</h4>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($bookingData['name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($bookingData['email']); ?></p>
                            <p><strong>Start Time:</strong> <?php echo htmlspecialchars(date('F j, Y \a\t g:i A', strtotime($bookingData['start_time']))); ?></p>
                            <p><strong>End Time:</strong> <?php echo htmlspecialchars(date('F j, Y \a\t g:i A', strtotime($bookingData['end_time']))); ?></p>
                            <p><strong>Status:</strong> <span class="status-badge status-booked">Booked</span></p>
                        </div>
                    <?php endif; ?>
                    <div style="margin-top: 25px;">
                        <a href="/GAMBYTES_Final/app/views/auth/booking-management.php" class="btn-primary" style="display: inline-block; padding: 12px 25px;">
                            <i class="fas fa-list"></i> View All Bookings
                        </a>
                        <a href="/GAMBYTES_Final/app/views/auth/dashboard.php" class="btn-secondary" style="display: inline-block; padding: 12px 25px; margin-left: 10px;">
                            <i class="fas fa-home"></i> Back to Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <div style="margin-top: 20px; text-align: center;">
                        <p><i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #1e73be;"></i></p>
                        <p style="margin-top: 15px; font-size: 16px;">Syncing your booking with our system...</p>
                        <p class="text-muted">If this takes more than 5 seconds, please refresh the page.</p>
                        <button onclick="location.reload();" class="btn-primary" style="margin-top: 15px; padding: 10px 20px;">
                            <i class="fas fa-sync-alt"></i> Refresh Now
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
