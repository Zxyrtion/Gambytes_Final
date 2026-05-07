<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "PHP Error: $errstr (Line: $errline)"]);
    exit();
});

require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../app/core/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$db = new Database();
$conn = $db->connect();

$user_id = $_SESSION['user_id'];

// Verify user is Executive Assistant
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || $user['role'] !== 'executive_assistant') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Executive Assistant role required.']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$contract_id = (int)($input['contract_id'] ?? 0);
$action = $input['action'] ?? '';
$notes = $input['notes'] ?? '';

if (!$contract_id || !in_array($action, ['approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Create contract_verifications table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `contract_verifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `contract_submission_id` INT(11) NOT NULL,
    `executive_assistant_id` INT(11) NOT NULL,
    `verification_status` ENUM('approved', 'rejected') NOT NULL,
    `verification_notes` TEXT NULL,
    `verified_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_contract_id` (`contract_submission_id`),
    KEY `idx_ea_id` (`executive_assistant_id`),
    KEY `idx_status` (`verification_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add columns if they don't exist
$conn->query("ALTER TABLE `contract_verifications` ADD COLUMN IF NOT EXISTS `verification_notes` TEXT NULL");
$conn->query("ALTER TABLE `contract_verifications` ADD COLUMN IF NOT EXISTS `verified_at` DATETIME NULL");

try {
    $conn->begin_transaction();
    
    // Update contract submission with verification status
    $updateContract = $conn->prepare("
        UPDATE contract_submissions 
        SET ea_verification_status = ?, 
            ea_verified_at = NOW(), 
            ea_verified_by = ?,
            ea_notes = ?
        WHERE id = ?
    ");
    if (!$updateContract) {
        throw new Exception('Prepare update failed: ' . $conn->error);
    }
    $updateContract->bind_param('sisi', $action, $user_id, $notes, $contract_id);
    if (!$updateContract->execute()) {
        throw new Exception('Execute update failed: ' . $updateContract->error);
    }
    
    if ($updateContract->affected_rows === 0) {
        throw new Exception('Contract not found or already verified');
    }
    $updateContract->close();
    
    // Create verification record
    $insertVerification = $conn->prepare("
        INSERT INTO contract_verifications 
        (contract_submission_id, executive_assistant_id, verification_status, verification_notes, verified_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    if (!$insertVerification) {
        throw new Exception('Prepare verification insert failed: ' . $conn->error);
    }
    $insertVerification->bind_param('iiss', $contract_id, $user_id, $action, $notes);
    if (!$insertVerification->execute()) {
        throw new Exception('Execute verification insert failed: ' . $insertVerification->error);
    }
    $insertVerification->close();
    
    // Get contract details for notification
    $contractDetails = $conn->prepare("
        SELECT cs.gambler_id, gu.email, gu.first_name
        FROM contract_submissions cs
        JOIN users gu ON gu.id = cs.gambler_id
        WHERE cs.id = ?
    ");
    $contractDetails->bind_param('i', $contract_id);
    $contractDetails->execute();
    $contractInfo = $contractDetails->get_result()->fetch_assoc();
    $contractDetails->close();
    
    if (!$contractInfo) {
        throw new Exception('Contract details not found');
    }
    
    // Create notification for gambler
    $notificationType = $action === 'approved' ? 'contract_approved' : 'contract_rejected';
    $notificationTitle = $action === 'approved' ? 'Contract Approved! ✅' : 'Contract Rejected ❌';

    if ($action === 'approved') {
        $notificationMessage = 'Your rehabilitation contract has been approved by the Executive Assistant. You can now proceed with the treatment program.';
        if (!empty($notes)) {
            $notificationMessage .= ' Feedback: ' . $notes;
        }
    } else {
        $notificationMessage = 'Your rehabilitation contract has been rejected by the Executive Assistant.';
        if (!empty($notes)) {
            $notificationMessage .= ' Reason: ' . $notes;
        } else {
            $notificationMessage .= ' Please contact the administrator for more information.';
        }
    }
    
    $insertNotification = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    if (!$insertNotification) {
        throw new Exception('Prepare notification failed: ' . $conn->error);
    }
    $notificationLink = '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php';
    $insertNotification->bind_param('issss', $contractInfo['gambler_id'], $notificationType, $notificationTitle, $notificationMessage, $notificationLink);
    if (!$insertNotification->execute()) {
        throw new Exception('Insert notification failed: ' . $insertNotification->error);
    }
    $insertNotification->close();
    
    // If family member exists, notify them too
    $familyCheck = $conn->prepare("
        SELECT u.id, u.first_name FROM users u
        WHERE u.role = 'family' AND u.id IN (
            SELECT DISTINCT family_member_id FROM contract_submissions 
            WHERE gambler_id = ? AND family_member_id IS NOT NULL
        )
    ");
    if ($familyCheck) {
        $familyCheck->bind_param('i', $contractInfo['gambler_id']);
        $familyCheck->execute();
        $familyMember = $familyCheck->get_result()->fetch_assoc();
        $familyCheck->close();
        
        if ($familyMember) {
            $familyMessage = $action === 'approved'
                ? 'The rehabilitation contract for your family member has been approved by the Executive Assistant.'
                : 'The rehabilitation contract for your family member requires review by the administrator.';
            
            $insertFamilyNotif = $conn->prepare("
                INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");
            if ($insertFamilyNotif) {
                $insertFamilyNotif->bind_param('issss', $familyMember['id'], $notificationType, $notificationTitle, $familyMessage, $notificationLink);
                if (!$insertFamilyNotif->execute()) {
                    throw new Exception('Failed to insert family notification: ' . $insertFamilyNotif->error);
                }
                $insertFamilyNotif->close();
            }
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Contract successfully {$action}",
        'contract_id' => $contract_id,
        'action' => $action
    ]);
    
} catch (Exception $e) {
    if ($conn) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
