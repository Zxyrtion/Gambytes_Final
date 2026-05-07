<?php
require_once __DIR__ . '/../../../../../includes/session_config.php';
require_once __DIR__ . '/../../../../../includes/url_helper.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . url('app/views/auth/login.php'));
    exit();
}
require_once __DIR__ . '/../../../../core/Database.php';
$db   = new Database();
$conn = $db->connect();

$user_id    = $_SESSION['user_id'];
$booking_id = (int)($_GET['booking_id'] ?? 0);

// Verify user is a gambler
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || $user['role'] !== 'gambler') {
    header("Location: " . url('app/views/auth/dashboard.php'));
    exit();
}

if (!$booking_id) {
    header("Location: /GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php");
    exit();
}

// Verify booking belongs to this user's email
$bStmt = $conn->prepare("SELECT br.*, ii.score, ii.diagnosis FROM booking_record br
    LEFT JOIN Initial_Interview_Record ii ON ii.booking_id = br.id
    WHERE br.id = ? AND LOWER(br.email) = LOWER(?)");
$bStmt->bind_param('is', $booking_id, $user['email']);
$bStmt->execute();
$booking = $bStmt->get_result()->fetch_assoc();
$bStmt->close();

if (!$booking) {
    header("Location: /GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php");
    exit();
}

// Check if contract workflow has been initiated by supervisor
// The new workflow uses contract_documents table with gambler_id
$checkWorkflow = $conn->prepare("SELECT id, status FROM contract_documents WHERE gambler_id = ? AND booking_id = ? LIMIT 1");
if (!$checkWorkflow) {
    error_log("SQL Error in review_terms.php: " . $conn->error);
    die("Database error: " . $conn->error);
}
$checkWorkflow->bind_param('ii', $user_id, $booking_id);
$checkWorkflow->execute();
$workflowExists = $checkWorkflow->get_result()->fetch_assoc();
$checkWorkflow->close();

// Get family member if linked
$family_id = null;
$famStmt = $conn->prepare("SELECT family_id FROM parental_control_requests WHERE gambler_id = ? AND status = 'accepted' LIMIT 1");
if ($famStmt) {
    $famStmt->bind_param('i', $user_id);
    $famStmt->execute();
    $famRow = $famStmt->get_result()->fetch_assoc();
    $famStmt->close();
    $family_id = $famRow ? (int)$famRow['family_id'] : null;
}

// If no workflow exists, create a pending one (gambler is applying for treatment)
if (!$workflowExists) {
    // Create pending contract (supervisor will send it later)
    $insertWorkflow = $conn->prepare("INSERT INTO contract_documents (gambler_id, family_id, booking_id, supervisor_id, status, created_at) VALUES (?, ?, ?, 1, 'pending', NOW())");
    if ($insertWorkflow) {
        $insertWorkflow->bind_param('iii', $user_id, $family_id, $booking_id);
        $insertWorkflow->execute();
        $insertWorkflow->close();
    }
}

// IMPORTANT: Also create contract_submissions record with family_member_id
// This ensures family member can see the contract in their "My Contracts" page
$checkSubmission = $conn->prepare("SELECT id FROM contract_submissions WHERE gambler_id = ? AND booking_id = ? LIMIT 1");
if ($checkSubmission) {
    $checkSubmission->bind_param('ii', $user_id, $booking_id);
    $checkSubmission->execute();
    $existingSubmission = $checkSubmission->get_result()->fetch_assoc();
    $checkSubmission->close();
    
    if (!$existingSubmission && $family_id) {
        // Create contract submission with family member linked
        $template_id = 0; // Default template
        $status = 'draft';
        
        $insertSubmission = $conn->prepare("INSERT INTO contract_submissions (gambler_id, family_member_id, booking_id, template_id, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($insertSubmission) {
            $insertSubmission->bind_param('iiiis', $user_id, $family_id, $booking_id, $template_id, $status);
            $insertSubmission->execute();
            $submission_id = $conn->insert_id;
            $insertSubmission->close();
            
            // Send notification to family member
            if ($family_id) {
                $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'contract_available', 'Contract Available for Review', 'Your family member has applied for treatment rehabilitation. Please review the contract.', '/GAMBYTES_Final/app/views/Users/Family member/my-contracts.php', 0, NOW())");
                $notifStmt->bind_param('i', $family_id);
                $notifStmt->execute();
                $notifStmt->close();
            }
        }
    }
}

// Redirect to fill-contract page
header("Location: /GAMBYTES_Final/app/views/Users/Gamblers/contract/fill-contract.php?booking_id=" . $booking_id);
exit();
