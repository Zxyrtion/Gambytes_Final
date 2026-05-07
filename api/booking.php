<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../app/core/Database.php';

header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit();
}

$db   = new Database();
$conn = $db->connect();

// ── Auto-create tables if missing ─────────────────────────────────────────
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

$conn->query("CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'general',
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NULL,
    `link` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$user_id    = $_SESSION['user_id'];
$event_uri  = $input['event_uri']  ?? '';
$name       = $input['name']       ?? '';
$email      = $input['email']      ?? '';
$start_time = $input['start_time'] ?? '';
$end_time   = $input['end_time']   ?? '';

// Fallback: get user info from DB if Calendly didn't send name/email
if (empty($name) || empty($email)) {
    $uStmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
    $uStmt->bind_param('i', $user_id);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    if ($uRow) {
        $name  = $name  ?: ($uRow['first_name'] . ' ' . $uRow['last_name']);
        $email = $email ?: $uRow['email'];
    }
}

// Generate a unique URI if Calendly didn't provide one
if (empty($event_uri)) {
    $event_uri = 'manual_' . $user_id . '_' . time();
}

// Format times
if ($start_time) $start_time = date('Y-m-d H:i:s', strtotime($start_time));
if ($end_time)   $end_time   = date('Y-m-d H:i:s', strtotime($end_time));
if (!$start_time) $start_time = date('Y-m-d H:i:s');
if (!$end_time)   $end_time   = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Insert into bookings
$stmt = $conn->prepare(
    "INSERT INTO booking_record (email, name, start_time, end_time, status, created_at)
     VALUES (?, ?, ?, ?, 'booked', NOW())"
);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
    exit();
}

$stmt->bind_param('ssss', $email, $name, $start_time, $end_time);

if ($stmt->execute()) {
    $booking_id = $conn->insert_id;
    $stmt->close();

    // ── Push notification to all supervisors/admins ───────────────────────
    $formatted_date = date('M j, Y g:i A', strtotime($start_time));
    $notif_title    = "New Booking: {$name}";
    $notif_message  = "{$name} booked a rehabilitation session on {$formatted_date}.";
    $notif_link     = '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php';
    $notif_type     = 'new_booking';

    $sup = $conn->query("SELECT id FROM users WHERE role IN ('supervisor','admin')");
    if ($sup) {
        $nStmt = $conn->prepare(
            "INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())"
        );
        if ($nStmt) {
            while ($sRow = $sup->fetch_assoc()) {
                $sid = $sRow['id'];
                $nStmt->bind_param('issss', $sid, $notif_type, $notif_title, $notif_message, $notif_link);
                $nStmt->execute();
            }
            $nStmt->close();
        }
    }

    // Store in session for confirmation page
    $_SESSION['last_booking'] = [
        'id'         => $booking_id,
        'name'       => $name,
        'email'      => $email,
        'start_time' => $start_time,
        'end_time'   => $end_time,
        'status'     => 'booked'
    ];

    echo json_encode(['success' => true, 'booking_id' => $booking_id]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
