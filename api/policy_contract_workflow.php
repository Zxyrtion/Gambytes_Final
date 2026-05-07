<?php
/**
 * API: Policy & Contract Workflow
 * Handles sending policies and contracts to gamblers and families
 */
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../app/core/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = new Database();
$conn = $db->connect();
$user_id = $_SESSION['user_id'];

// Get user role
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Create tables if not exist
$conn->query("CREATE TABLE IF NOT EXISTS `policy_contract_assignments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `gambler_id` INT(11) NOT NULL,
    `family_id` INT(11) NULL,
    `booking_id` INT(11) NULL,
    `supervisor_id` INT(11) NOT NULL,
    `status` ENUM('sent','viewed_by_gambler','signed_by_gambler','sent_to_family','signed_by_family','completed') NOT NULL DEFAULT 'sent',
    `gambler_signature` LONGTEXT NULL,
    `gambler_signed_at` DATETIME NULL,
    `family_signature` LONGTEXT NULL,
    `family_signed_at` DATETIME NULL,
    `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_gambler` (`gambler_id`),
    INDEX `idx_family` (`family_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `policy_contract_documents` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `assignment_id` INT(11) NOT NULL,
    `policy_id` INT(11) NULL COMMENT 'Reference to policy_files table',
    `document_type` ENUM('policy','contract') NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_assignment` (`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ═══════════════════════════════════════════════════════════════════════════
// SUPERVISOR ACTIONS
// ═══════════════════════════════════════════════════════════════════════════

// Get eligible gamblers (those who completed interview with score >= 4)
if ($action === 'get_eligible_gamblers' && in_array($user['role'], ['supervisor', 'admin'])) {
    $stmt = $conn->query("
        SELECT DISTINCT
            u.id AS gambler_id,
            CONCAT(u.first_name, ' ', u.last_name) AS gambler_name,
            u.email AS gambler_email,
            br.id AS booking_id,
            ii.score,
            ii.diagnosis,
            ii.conducted_at,
            CONCAT(fam.first_name, ' ', fam.last_name) AS family_name,
            fam.id AS family_id
        FROM users u
        JOIN booking_record br ON LOWER(br.email) = LOWER(u.email)
        JOIN Initial_Interview_Record ii ON ii.booking_id = br.id
        LEFT JOIN parental_control_requests pcr ON pcr.gambler_id = u.id AND pcr.status = 'accepted'
        LEFT JOIN users fam ON fam.id = pcr.family_id
        WHERE u.role = 'gambler' AND ii.score >= 4
        ORDER BY ii.conducted_at DESC
    ");
    
    $gamblers = [];
    while ($row = $stmt->fetch_assoc()) {
        $gamblers[] = $row;
    }
    
    echo json_encode(['success' => true, 'gamblers' => $gamblers]);
    exit();
}

// Send policies and contract to gambler
if ($action === 'send_to_gambler' && in_array($user['role'], ['supervisor', 'admin'])) {
    $gambler_id = (int)($_POST['gambler_id'] ?? 0);
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $policy_ids = json_decode($_POST['policy_ids'] ?? '[]', true);
    
    if (!$gambler_id || !$booking_id) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }
    
    // Get family member if linked
    $famStmt = $conn->prepare("SELECT family_id FROM parental_control_requests WHERE gambler_id = ? AND status = 'accepted' LIMIT 1");
    $famStmt->bind_param('i', $gambler_id);
    $famStmt->execute();
    $famRow = $famStmt->get_result()->fetch_assoc();
    $famStmt->close();
    $family_id = $famRow ? (int)$famRow['family_id'] : null;
    
    // Check if already sent
    $checkStmt = $conn->prepare("SELECT id FROM policy_contract_assignments WHERE gambler_id = ? AND booking_id = ? LIMIT 1");
    $checkStmt->bind_param('ii', $gambler_id, $booking_id);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Policies and contract already sent to this gambler']);
        exit();
    }
    
    // Create assignment
    $insStmt = $conn->prepare("INSERT INTO policy_contract_assignments (gambler_id, family_id, booking_id, supervisor_id, status, sent_at) VALUES (?, ?, ?, ?, 'sent', NOW())");
    $insStmt->bind_param('iiii', $gambler_id, $family_id, $booking_id, $user_id);
    $insStmt->execute();
    $assignment_id = $conn->insert_id;
    $insStmt->close();
    
    // Link selected policies
    if (!empty($policy_ids)) {
        $docStmt = $conn->prepare("INSERT INTO policy_contract_documents (assignment_id, policy_id, document_type) VALUES (?, ?, 'policy')");
        foreach ($policy_ids as $policy_id) {
            $pid = (int)$policy_id;
            $docStmt->bind_param('ii', $assignment_id, $pid);
            $docStmt->execute();
        }
        $docStmt->close();
    }
    
    // Add contract document (no policy_id for contract)
    $contStmt = $conn->prepare("INSERT INTO policy_contract_documents (assignment_id, policy_id, document_type) VALUES (?, NULL, 'contract')");
    $contStmt->bind_param('i', $assignment_id);
    $contStmt->execute();
    $contStmt->close();
    
    // Send notification to gambler
    $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'policy_contract', 'Policies & Contract Available', 'Your supervisor has sent you policies and a rehabilitation contract. Please review after completing your interview.', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 0, NOW())");
    $notifStmt->bind_param('i', $gambler_id);
    $notifStmt->execute();
    $notifStmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Policies and contract sent successfully', 'assignment_id' => $assignment_id]);
    exit();
}

// ═══════════════════════════════════════════════════════════════════════════
// GAMBLER ACTIONS
// ═══════════════════════════════════════════════════════════════════════════

// Get gambler's assigned policies and contract
if ($action === 'get_gambler_assignment' && $user['role'] === 'gambler') {
    $stmt = $conn->prepare("
        SELECT pca.*, 
               CONCAT(u.first_name, ' ', u.last_name) AS supervisor_name
        FROM policy_contract_assignments pca
        JOIN users u ON u.id = pca.supervisor_id
        WHERE pca.gambler_id = ?
        ORDER BY pca.sent_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$assignment) {
        echo json_encode(['success' => true, 'assignment' => null]);
        exit();
    }
    
    // Get associated documents
    $docStmt = $conn->prepare("
        SELECT pcd.*, pf.doc_title, pf.filename, pf.original_name, pf.doc_type, pf.description
        FROM policy_contract_documents pcd
        LEFT JOIN policy_files pf ON pf.id = pcd.policy_id
        WHERE pcd.assignment_id = ?
    ");
    $docStmt->bind_param('i', $assignment['id']);
    $docStmt->execute();
    $docs = $docStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $docStmt->close();
    
    $assignment['documents'] = $docs;
    
    echo json_encode(['success' => true, 'assignment' => $assignment]);
    exit();
}

// Mark as viewed by gambler
if ($action === 'mark_viewed' && $user['role'] === 'gambler') {
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);
    
    $stmt = $conn->prepare("UPDATE policy_contract_assignments SET status = 'viewed_by_gambler' WHERE id = ? AND gambler_id = ? AND status = 'sent'");
    $stmt->bind_param('ii', $assignment_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true]);
    exit();
}

// Gambler signs contract
if ($action === 'sign_contract' && $user['role'] === 'gambler') {
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);
    $signature = $_POST['signature'] ?? '';
    
    if (!$assignment_id || !$signature) {
        echo json_encode(['success' => false, 'message' => 'Missing signature']);
        exit();
    }
    
    $stmt = $conn->prepare("UPDATE policy_contract_assignments SET status = 'signed_by_gambler', gambler_signature = ?, gambler_signed_at = NOW() WHERE id = ? AND gambler_id = ?");
    $stmt->bind_param('sii', $signature, $assignment_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Notify supervisor
    $assStmt = $conn->prepare("SELECT supervisor_id FROM policy_contract_assignments WHERE id = ?");
    $assStmt->bind_param('i', $assignment_id);
    $assStmt->execute();
    $assRow = $assStmt->get_result()->fetch_assoc();
    $assStmt->close();
    
    if ($assRow) {
        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'contract_signed', 'Gambler Signed Contract', 'A gambler has signed their rehabilitation contract.', '/GAMBYTES_Final/app/views/Users/Supervisor/policies.php', 0, NOW())");
        $notifStmt->bind_param('i', $assRow['supervisor_id']);
        $notifStmt->execute();
        $notifStmt->close();
    }
    
    echo json_encode(['success' => true, 'message' => 'Contract signed successfully']);
    exit();
}

// Send contract to family
if ($action === 'send_to_family' && $user['role'] === 'gambler') {
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);
    
    $stmt = $conn->prepare("SELECT family_id FROM policy_contract_assignments WHERE id = ? AND gambler_id = ? AND status = 'signed_by_gambler'");
    $stmt->bind_param('ii', $assignment_id, $user_id);
    $stmt->execute();
    $assRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$assRow || !$assRow['family_id']) {
        echo json_encode(['success' => false, 'message' => 'No family member linked or contract not signed yet']);
        exit();
    }
    
    // Update status
    $updStmt = $conn->prepare("UPDATE policy_contract_assignments SET status = 'sent_to_family' WHERE id = ?");
    $updStmt->bind_param('i', $assignment_id);
    $updStmt->execute();
    $updStmt->close();
    
    // Notify family
    $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'contract_review', 'Contract Requires Your Signature', 'Your family member has signed a rehabilitation contract and needs your counter-signature.', '/GAMBYTES_Final/app/views/Users/Family member/my-contracts.php', 0, NOW())");
    $notifStmt->bind_param('i', $assRow['family_id']);
    $notifStmt->execute();
    $notifStmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Contract sent to family member']);
    exit();
}

// ═══════════════════════════════════════════════════════════════════════════
// FAMILY ACTIONS
// ═══════════════════════════════════════════════════════════════════════════

// Get family's assigned contracts
if ($action === 'get_family_assignments' && $user['role'] === 'family') {
    $stmt = $conn->prepare("
        SELECT pca.*, 
               CONCAT(g.first_name, ' ', g.last_name) AS gambler_name,
               g.email AS gambler_email
        FROM policy_contract_assignments pca
        JOIN users g ON g.id = pca.gambler_id
        WHERE pca.family_id = ? AND pca.status IN ('sent_to_family', 'signed_by_family', 'completed')
        ORDER BY pca.sent_at DESC
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode(['success' => true, 'assignments' => $assignments]);
    exit();
}

// Family signs contract
if ($action === 'family_sign' && $user['role'] === 'family') {
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);
    $signature = $_POST['signature'] ?? '';
    
    if (!$assignment_id || !$signature) {
        echo json_encode(['success' => false, 'message' => 'Missing signature']);
        exit();
    }
    
    $stmt = $conn->prepare("UPDATE policy_contract_assignments SET status = 'signed_by_family', family_signature = ?, family_signed_at = NOW() WHERE id = ? AND family_id = ? AND status = 'sent_to_family'");
    $stmt->bind_param('sii', $signature, $assignment_id, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected === 0) {
        echo json_encode(['success' => false, 'message' => 'Contract not found or already signed']);
        exit();
    }
    
    // Notify gambler and supervisor
    $assStmt = $conn->prepare("SELECT gambler_id, supervisor_id FROM policy_contract_assignments WHERE id = ?");
    $assStmt->bind_param('i', $assignment_id);
    $assStmt->execute();
    $assRow = $assStmt->get_result()->fetch_assoc();
    $assStmt->close();
    
    if ($assRow) {
        // Notify gambler
        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'contract_completed', 'Family Member Signed Contract', 'Your family member has signed the rehabilitation contract.', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 0, NOW())");
        $notifStmt->bind_param('i', $assRow['gambler_id']);
        $notifStmt->execute();
        $notifStmt->close();
        
        // Notify supervisor
        $notifStmt2 = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'contract_completed', 'Contract Fully Signed', 'A rehabilitation contract has been signed by both gambler and family.', '/GAMBYTES_Final/app/views/Users/Supervisor/policies.php', 0, NOW())");
        $notifStmt2->bind_param('i', $assRow['supervisor_id']);
        $notifStmt2->execute();
        $notifStmt2->close();
    }
    
    echo json_encode(['success' => true, 'message' => 'Contract signed successfully']);
    exit();
}

// ═══════════════════════════════════════════════════════════════════════════
// COMMON ACTIONS
// ═══════════════════════════════════════════════════════════════════════════

// Get all policies for selection
if ($action === 'get_all_policies') {
    $stmt = $conn->query("SELECT id, doc_title, doc_type, doc_category, description FROM policy_files ORDER BY doc_category, doc_title");
    $policies = $stmt->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'policies' => $policies]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
