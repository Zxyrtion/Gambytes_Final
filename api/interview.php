<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../app/core/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$db   = new Database();
$conn = $db->connect();

// Ensure 'interviewed' and 'admission' are valid enum values
$conn->query("ALTER TABLE booking_record MODIFY COLUMN status ENUM('booked','approved','interviewed','admission','cancelled','no_show','completed') DEFAULT 'booked'");

// ── Auto-create Initial_Interview_Record table if missing ───────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `Initial_Interview_Record` (
    `id`                INT(11)      NOT NULL AUTO_INCREMENT,
    `booking_id`        INT(11)      NOT NULL,
    `interviewer_id`    INT(11)      NOT NULL,
    `gambling_history`  TEXT         NULL,
    `health_assessment` TEXT         NULL,
    `social_background` TEXT         NULL,
    `treatment_goals`   TEXT         NULL,
    `remarks`           TEXT         NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_booking` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit();
}

$booking_id        = (int)($input['booking_id']        ?? 0);
$gambling_history  = trim($input['gambling_history']   ?? '');
$health_assessment = trim($input['health_assessment']  ?? '');
$social_background = trim($input['social_background']  ?? '');
$treatment_goals   = trim($input['treatment_goals']    ?? '');
$remarks           = trim($input['remarks']            ?? '');
$interviewer_id    = (int)$_SESSION['user_id'];

if (!$booking_id) {
    echo json_encode(['success' => false, 'error' => 'Booking ID is required']);
    exit();
}

// Verify booking exists and is approved
$chk = $conn->prepare("SELECT id, email, name FROM booking_record WHERE id = ? AND status = 'approved' LIMIT 1");
$chk->bind_param('i', $booking_id);
$chk->execute();
$booking = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$booking) {
    echo json_encode(['success' => false, 'error' => 'Booking not found or not approved']);
    exit();
}

// Insert interview record
$stmt = $conn->prepare(
    "INSERT INTO Initial_Interview_Record
     (booking_id, interviewer_id, gambling_history, health_assessment, social_background, treatment_goals, remarks, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
);
$stmt->bind_param('iisssss',
    $booking_id,
    $interviewer_id,
    $gambling_history,
    $health_assessment,
    $social_background,
    $treatment_goals,
    $remarks
);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
    exit();
}
$interview_id = $conn->insert_id;
$stmt->close();

// Update booking status to 'admission'
$upd = $conn->prepare("UPDATE booking_record SET status = 'admission' WHERE id = ?");
$upd->bind_param('i', $booking_id);
$upd->execute();
$upd->close();

// Notify the gambler
$notif_title   = "Initial Interview Completed";
$notif_message = "Your initial interview has been recorded. The next step will be communicated soon.";
$notif_link    = '/GAMBYTES_Final/app/views/auth/dashboard.php';
$notif_type    = 'interview_done';

// Find gambler's user_id by email
$uStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$uStmt->bind_param('s', $booking['email']);
$uStmt->execute();
$uRow = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

if ($uRow) {
    $gambler_id = $uRow['id'];
    $nStmt = $conn->prepare(
        "INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
         VALUES (?, ?, ?, ?, ?, 0, NOW())"
    );
    $nStmt->bind_param('issss', $gambler_id, $notif_type, $notif_title, $notif_message, $notif_link);
    $nStmt->execute();
    $nStmt->close();
}

echo json_encode([
    'success'      => true,
    'interview_id' => $interview_id,
    'message'      => 'Initial interview saved successfully'
]);
