<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../app/core/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Only supervisors/admins can approve
$db      = new Database();
$conn    = $db->connect();
$user_id = (int)$_SESSION['user_id'];

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

$roleStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->bind_param('i', $user_id);
$roleStmt->execute();
$roleRow = $roleStmt->get_result()->fetch_assoc();
$roleStmt->close();

if (!in_array($roleRow['role'] ?? '', ['supervisor', 'admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit();
}

$input      = json_decode(file_get_contents('php://input'), true);
$booking_id = (int)($input['booking_id'] ?? 0);

if (!$booking_id) {
    echo json_encode(['success' => false, 'error' => 'Missing booking_id']);
    exit();
}

// Get booking details
$bStmt = $conn->prepare("SELECT * FROM booking_record WHERE id = ?");
$bStmt->bind_param('i', $booking_id);
$bStmt->execute();
$booking = $bStmt->get_result()->fetch_assoc();
$bStmt->close();

if (!$booking) {
    echo json_encode(['success' => false, 'error' => 'Booking not found']);
    exit();
}

if ($booking['status'] === 'approved') {
    echo json_encode(['success' => false, 'error' => 'Already approved']);
    exit();
}

// Normalize: treat empty string as 'booked'
if (!in_array($booking['status'], ['booked', '', 'approved', 'interviewed', 'completed', 'cancelled', 'no_show'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid booking status']);
    exit();
}

// Update booking status to approved
$upStmt = $conn->prepare("UPDATE booking_record SET status = 'approved' WHERE id = ?");
$upStmt->bind_param('i', $booking_id);
$upStmt->execute();
$upStmt->close();

// Find the gambler by email and send them a notification
$uStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'gambler' LIMIT 1");
$uStmt->bind_param('s', $booking['email']);
$uStmt->execute();
$gambler = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

if ($gambler) {
    $gambler_id     = $gambler['id'];
    $formatted_date = date('F j, Y \a\t g:i A', strtotime($booking['start_time']));
    $notif_title    = 'Booking Approved!';
    $notif_message  = "Your rehabilitation session on {$formatted_date} has been approved by the supervisor.";
    $notif_link     = '/GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php';
    $notif_type     = 'booking_approved';

    $nStmt = $conn->prepare(
        "INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
         VALUES (?, ?, ?, ?, ?, 0, NOW())"
    );
    $nStmt->bind_param('issss', $gambler_id, $notif_type, $notif_title, $notif_message, $notif_link);
    $nStmt->execute();
    $nStmt->close();
}

echo json_encode(['success' => true, 'message' => 'Booking approved and gambler notified.']);
