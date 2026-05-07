<?php
require_once __DIR__ . '/../../../../includes/session_config.php';
require_once __DIR__ . '/../../../../includes/url_helper.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . url('app/views/auth/login.php'));
    exit();
}

// Get user information
require_once __DIR__ . '/../../../core/Database.php';
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
$full_name = $user['first_name'] . ' ' . $user['last_name'];
$email = $user['email'];
$stmt->close();

// Include models
require_once __DIR__ . '/../../../models/ScheduleModel.php';

// ── Auto-create booking_record table if missing ───────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `booking_record` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `calendly_event_uri` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    `start_time` DATETIME DEFAULT NULL,
    `end_time` DATETIME DEFAULT NULL,
    `status` ENUM('booked','approved','interviewed','completed','cancelled','no_show') NOT NULL DEFAULT 'booked',
    `notes` LONGTEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email` (`email`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$message = '';
$error = '';
$bookingData = null;
$autoSync = false;

// Handle manual form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_save'])) {
    $name       = trim($_POST['name'] ?? $full_name);
    $postEmail  = trim($_POST['email'] ?? $email);
    $start_time = $_POST['start_time'] ?? '';
    $end_time   = $_POST['end_time']   ?? '';
    $event_uri  = 'manual_' . $_SESSION['user_id'] . '_' . time();
    
    $start_time = date('Y-m-d H:i:s', strtotime($start_time));
    $end_time   = date('Y-m-d H:i:s', strtotime($end_time));
    
    $saveStmt = $conn->prepare(
        "INSERT INTO booking_record (email, name, start_time, end_time, status, created_at)
         VALUES (?, ?, ?, ?, 'booked', NOW())"
    );
    $saveStmt->bind_param('ssss', $postEmail, $name, $start_time, $end_time);
    
    if ($saveStmt->execute()) {
        $message     = "Booking saved successfully! Your consultation is scheduled.";
        $bookingData = compact('name', 'postEmail', 'start_time', 'end_time');
        $bookingData['email'] = $postEmail;
        $bookingData['status'] = 'booked';
        $autoSync = true;
    } else {
        $error = "Failed to save: " . $saveStmt->error;
    }
    $saveStmt->close();
}

// Check session or DB for existing booking
if (!$autoSync) {
    try {
        if (!empty($_SESSION['last_booking'])) {
            $bookingData = $_SESSION['last_booking'];
            $message     = "Booking successfully saved! Your consultation is scheduled.";
            $autoSync    = true;
            unset($_SESSION['last_booking']);
        } else {
            $scheduleModel  = new ScheduleModel();
            $recentBookings = $scheduleModel->getBookingsByEmail($email);
            if (!empty($recentBookings)) {
                $bookingData = $recentBookings[0];
                $message     = "Your most recent booking is shown below.";
                $autoSync    = true;
            } else {
                $error = "No booking found yet.";
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - Gambytes</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css?v=<?= time() ?>">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="/GAMBYTES_Final/public/images/Logo.png" alt="Logo">
                    <span>Gambytes</span>
                </div>
                <div class="sidebar-user">
                    <div class="user-name"><i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($full_name) ?></div>
                    <div class="user-role"><?= ucfirst(str_replace('_', ' ', $user['role'] ?? '')) ?></div>
                </div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php">
                    <i class="fas fa-home"></i> Overview
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/BookRehab.php">
                    <i class="fas fa-calendar-plus"></i> Book Rehabilitation
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php">
                    <i class="fas fa-clipboard-list"></i> My Interview
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php">
                    <i class="fas fa-file-contract"></i> My Contracts
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-cbt-sessions.php">
                    <i class="fas fa-brain"></i> CBT Sessions
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-activities.php">
                    <i class="fas fa-tasks"></i> My Activities
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/parental-control-requests.php">
                    <i class="fas fa-shield-alt"></i> Parental Access
                </a></li>
                <li><a href="#">
                    <i class="fas fa-user"></i> Profile
                </a></li>
                <div class="menu-divider"></div>
                <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-check-circle me-2"></i>Booking Confirmation</h1>
                <p>Thank you, <strong><?= htmlspecialchars($full_name) ?></strong>! Please confirm your booking details below.</p>
            </div>

            <?php if ($error && empty($bookingData)): ?>
                <!-- No booking yet - show manual entry form -->
                <div class="dash-card mb-4">
                    <div class="dash-card-header">
                        <i class="fas fa-calendar-plus"></i> Confirm Your Booking
                    </div>
                    <div class="dash-card-body">
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            Your Calendly booking was completed! Please confirm the details below to save it to our system.
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="manual_save" value="1">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Full Name</label>
                                    <input type="text" name="name" class="form-control" 
                                           value="<?= htmlspecialchars($full_name) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Email</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($email) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Appointment Date & Time</label>
                                    <input type="datetime-local" name="start_time" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">End Time</label>
                                    <input type="datetime-local" name="end_time" class="form-control" required>
                                </div>
                                <div class="col-12 mt-3">
                                    <button type="submit" class="btn-book" style="width:auto; padding:0.75rem 2.5rem;">
                                        <i class="fas fa-save me-2"></i>Save Booking
                                    </button>
                                    <a href="/GAMBYTES_Final/app/views/Users/Gamblers/BookRehab.php" 
                                       class="btn-book ms-3" style="width:auto; padding:0.75rem 2rem; background:linear-gradient(135deg,#6c757d,#495057);">
                                        <i class="fas fa-arrow-left me-2"></i>Back
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

            <?php elseif ($message && $autoSync): ?>
                <!-- Success State -->
                <div class="dash-card mb-4">
                    <div class="dash-card-header" style="background:linear-gradient(135deg,#28a745,#1e7e34);">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                    </div>
                    <?php if ($bookingData): ?>
                    <div class="dash-card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="booking-detail-item">
                                    <div class="booking-detail-icon"><i class="fas fa-user"></i></div>
                                    <div>
                                        <small class="text-muted">Patient Name</small>
                                        <div class="fw-bold"><?= htmlspecialchars($bookingData['name']) ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="booking-detail-item">
                                    <div class="booking-detail-icon"><i class="fas fa-envelope"></i></div>
                                    <div>
                                        <small class="text-muted">Email</small>
                                        <div class="fw-bold"><?= htmlspecialchars($bookingData['email']) ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="booking-detail-item">
                                    <div class="booking-detail-icon"><i class="fas fa-calendar-check"></i></div>
                                    <div>
                                        <small class="text-muted">Start Time</small>
                                        <div class="fw-bold"><?= date('F j, Y \a\t g:i A', strtotime($bookingData['start_time'])) ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="booking-detail-item">
                                    <div class="booking-detail-icon"><i class="fas fa-calendar-times"></i></div>
                                    <div>
                                        <small class="text-muted">End Time</small>
                                        <div class="fw-bold"><?= date('F j, Y \a\t g:i A', strtotime($bookingData['end_time'])) ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="booking-detail-item">
                                    <div class="booking-detail-icon"><i class="fas fa-info-circle"></i></div>
                                    <div>
                                        <small class="text-muted">Status</small>
                                        <div><span class="badge" style="background:#800000; padding:0.5rem 1rem; border-radius:20px;">Booked</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-3 mt-4">
                            <a href="/GAMBYTES_Final/app/views/Users/Gamblers/BookRehab.php" class="btn-book" style="width:auto; padding:0.75rem 2rem;">
                                <i class="fas fa-calendar-plus me-2"></i>Book Another
                            </a>
                            <a href="/GAMBYTES_Final/app/views/auth/dashboard.php" class="btn-book" style="width:auto; padding:0.75rem 2rem; background:linear-gradient(135deg,#6c757d,#495057);">
                                <i class="fas fa-home me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Loading State -->
                <div class="dash-card">
                    <div class="dash-card-body text-center py-5">
                        <div class="mb-3" style="font-size:3rem; color:#800000;">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                        <h4>Syncing your booking...</h4>
                        <p class="text-muted">If this takes more than 5 seconds, please refresh the page.</p>
                        <button onclick="location.reload();" class="btn-book" style="width:auto; padding:0.75rem 2rem; margin-top:1rem;">
                            <i class="fas fa-sync-alt me-2"></i>Refresh Now
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
