<?php
/**
 * API: Contract Workflow Management
 * Handles the complete workflow: Supervisor → Gambler → Family
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

// Get user info
$stmt = $conn->prepare("SELECT id, role, first_name, last_name, email FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/**
 * Log workflow state change
 */
function logWorkflowChange($conn, $contract_id, $old_status, $new_status, $user_id, $notes = null) {
    $stmt = $conn->prepare("INSERT INTO workflow_state_log (contract_document_id, previous_status, new_status, changed_by_user_id, notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('issis', $contract_id, $old_status, $new_status, $user_id, $notes);
    $stmt->execute();
    $stmt->close();
}

// ═══════════════════════════════════════════════════════════════════════════
// SUPERVISOR ACTIONS
// ═══════════════════════════════════════════════════════════════════════════

// Get eligible gamblers (completed interview, score >= 4, not yet sent contract)
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
            fam.id AS family_id,
            fam.email AS family_email,
            cd.id AS existing_contract_id,
            cd.status AS contract_status
        FROM users u
        JOIN booking_record br ON LOWER(br.email) = LOWER(u.email)
        JOIN Initial_Interview_Record ii ON ii.booking_id = br.id
        LEFT JOIN parental_control_requests pcr ON pcr.gambler_id = u.id AND pcr.status = 'accepted'
        LEFT JOIN users fam ON fam.id = pcr.family_id
        LEFT JOIN contract_documents cd ON cd.gambler_id = u.id AND cd.booking_id = br.id
        WHERE u.role = 'gambler' 
        AND ii.score >= 4
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
if ($action === 'send_policies_contract' && in_array($user['role'], ['supervisor', 'admin'])) {
    $gambler_id = (int)($_POST['gambler_id'] ?? 0);
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $policy_ids = json_decode($_POST['policy_ids'] ?? '[]', true);
    
    if (!$gambler_id || !$booking_id) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }
    
    // Verify gambler has completed interview with score >= 4
    $verifyStmt = $conn->prepare("
        SELECT ii.score FROM Initial_Interview_Record ii
        JOIN booking_record br ON br.id = ii.booking_id
        WHERE br.id = ? AND br.email = (SELECT email FROM users WHERE id = ?)
        AND ii.score >= 4
    ");
    $verifyStmt->bind_param('ii', $booking_id, $gambler_id);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result()->fetch_assoc();
    $verifyStmt->close();
    
    if (!$verifyResult) {
        echo json_encode(['success' => false, 'message' => 'Gambler has not completed interview or score is below 4']);
        exit();
    }
    
    // Get family member if linked
    $famStmt = $conn->prepare("SELECT family_id FROM parental_control_requests WHERE gambler_id = ? AND status = 'accepted' LIMIT 1");
    $famStmt->bind_param('i', $gambler_id);
    $famStmt->execute();
    $famRow = $famStmt->get_result()->fetch_assoc();
    $famStmt->close();
    $family_id = $famRow ? (int)$famRow['family_id'] : null;
    
    // Check if contract already exists
    $checkStmt = $conn->prepare("SELECT id, status FROM contract_documents WHERE gambler_id = ? AND booking_id = ? LIMIT 1");
    $checkStmt->bind_param('ii', $gambler_id, $booking_id);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    
    if ($existing && $existing['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Contract already sent to this gambler']);
        exit();
    }
    
    // Generate contract content (MOA template)
    $contract_content = generateContractTemplate($conn, $gambler_id, $family_id);
    
    // Create or update contract document
    if ($existing) {
        $stmt = $conn->prepare("UPDATE contract_documents SET status = 'sent', contract_content = ?, sent_to_gambler_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $contract_content, $existing['id']);
        $stmt->execute();
        $stmt->close();
        $contract_id = $existing['id'];
        logWorkflowChange($conn, $contract_id, 'pending', 'sent', $user_id, 'Contract sent by supervisor');
    } else {
        $insStmt = $conn->prepare("INSERT INTO contract_documents (gambler_id, family_id, booking_id, supervisor_id, contract_content, status, sent_to_gambler_at) VALUES (?, ?, ?, ?, ?, 'sent', NOW())");
        $insStmt->bind_param('iiiis', $gambler_id, $family_id, $booking_id, $user_id, $contract_content);
        $insStmt->execute();
        $contract_id = $conn->insert_id;
        $insStmt->close();
        logWorkflowChange($conn, $contract_id, null, 'sent', $user_id, 'Initial contract creation and send');
    }
    
    // Link selected policies
    if (!empty($policy_ids)) {
        // Clear existing policies first
        $conn->query("DELETE FROM contract_document_policies WHERE contract_document_id = $contract_id");
        
        $docStmt = $conn->prepare("INSERT INTO contract_document_policies (contract_document_id, policy_file_id) VALUES (?, ?)");
        foreach ($policy_ids as $policy_id) {
            $pid = (int)$policy_id;
            $docStmt->bind_param('ii', $contract_id, $pid);
            $docStmt->execute();
        }
        $docStmt->close();
    }
    
    // Send notification to gambler
    $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'contract_available', 'Policies & Contract Available', 'Your supervisor has sent you policies and a rehabilitation contract. Click \"Apply for Treatment Rehabilitation\" on your interview page to view them.', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 0, NOW())");
    $notifStmt->bind_param('i', $gambler_id);
    $notifStmt->execute();
    $notifStmt->close();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Policies and contract sent successfully', 
        'contract_id' => $contract_id
    ]);
    exit();
}

// Get all sent contracts (for supervisor dashboard)
if ($action === 'get_all_contracts' && in_array($user['role'], ['supervisor', 'admin'])) {
    $stmt = $conn->query("
        SELECT 
            cd.*,
            CONCAT(g.first_name, ' ', g.last_name) AS gambler_name,
            g.email AS gambler_email,
            CONCAT(f.first_name, ' ', f.last_name) AS family_name,
            f.email AS family_email,
            COUNT(cdp.id) AS policy_count
        FROM contract_documents cd
        JOIN users g ON g.id = cd.gambler_id
        LEFT JOIN users f ON f.id = cd.family_id
        LEFT JOIN contract_document_policies cdp ON cdp.contract_document_id = cd.id
        GROUP BY cd.id
        ORDER BY cd.created_at DESC
    ");
    
    $contracts = [];
    while ($row = $stmt->fetch_assoc()) {
        $contracts[] = $row;
    }
    
    echo json_encode(['success' => true, 'contracts' => $contracts]);
    exit();
}

// ═══════════════════════════════════════════════════════════════════════════
// GAMBLER ACTIONS
// ═══════════════════════════════════════════════════════════════════════════

// Check if gambler has available contract (after interview)
if ($action === 'check_contract_availability' && $user['role'] === 'gambler') {
    $booking_id = (int)($_GET['booking_id'] ?? 0);
    
    $stmt = $conn->prepare("
        SELECT cd.*, 
               CONCAT(s.first_name, ' ', s.last_name) AS supervisor_name,
               ii.score
        FROM contract_documents cd
        JOIN users s ON s.id = cd.supervisor_id
        JOIN booking_record br ON br.id = cd.booking_id
        JOIN Initial_Interview_Record ii ON ii.booking_id = br.id
        WHERE cd.gambler_id = ? 
        AND (cd.booking_id = ? OR ? = 0)
        AND cd.status IN ('sent', 'viewed_by_gambler', 'signed_by_gambler', 'sent_to_family', 'signed_by_family', 'completed')
        AND ii.score >= 4
        ORDER BY cd.sent_to_gambler_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('iii', $user_id, $booking_id, $booking_id);
    $stmt->execute();
    $contract = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($contract) {
        // Get associated policies
        $policyStmt = $conn->prepare("
            SELECT pf.* 
            FROM policy_files pf
            JOIN contract_document_policies cdp ON cdp.policy_file_id = pf.id
            WHERE cdp.contract_document_id = ?
        ");
        $policyStmt->bind_param('i', $contract['id']);
        $policyStmt->execute();
        $policies = $policyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $policyStmt->close();
        
        $contract['policies'] = $policies;
    }
    
    echo json_encode(['success' => true, 'contract' => $contract]);
    exit();
}

// Mark contract as viewed by gambler
if ($action === 'mark_viewed' && $user['role'] === 'gambler') {
    $contract_id = (int)($_POST['contract_id'] ?? 0);
    
    $stmt = $conn->prepare("UPDATE contract_documents SET status = 'viewed_by_gambler', updated_at = NOW() WHERE id = ? AND gambler_id = ? AND status = 'sent'");
    $stmt->bind_param('ii', $contract_id, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        logWorkflowChange($conn, $contract_id, 'sent', 'viewed_by_gambler', $user_id, 'Gambler viewed contract');
    }
    
    echo json_encode(['success' => true]);
    exit();
}

// Gambler signs contract
if ($action === 'sign_contract' && $user['role'] === 'gambler') {
    $contract_id = (int)($_POST['contract_id'] ?? 0);
    $signature = $_POST['signature'] ?? '';
    
    if (!$contract_id || !$signature) {
        echo json_encode(['success' => false, 'message' => 'Missing signature data']);
        exit();
    }
    
    // Verify contract belongs to gambler and is in correct state
    $verifyStmt = $conn->prepare("SELECT status FROM contract_documents WHERE id = ? AND gambler_id = ?");
    $verifyStmt->bind_param('ii', $contract_id, $user_id);
    $verifyStmt->execute();
    $contractData = $verifyStmt->get_result()->fetch_assoc();
    $verifyStmt->close();
    
    if (!$contractData) {
        echo json_encode(['success' => false, 'message' => 'Contract not found']);
        exit();
    }
    
    if (!in_array($contractData['status'], ['sent', 'viewed_by_gambler'])) {
        echo json_encode(['success' => false, 'message' => 'Contract already signed or in invalid state']);
        exit();
    }
    
    $stmt = $conn->prepare("UPDATE contract_documents SET status = 'signed_by_gambler', gambler_signature = ?, signed_at_gambler = NOW(), updated_at = NOW() WHERE id = ? AND gambler_id = ?");
    $stmt->bind_param('sii', $signature, $contract_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    logWorkflowChange($conn, $contract_id, $contractData['status'], 'signed_by_gambler', $user_id, 'Gambler signed contract');
    
    // Notify supervisor
    $supStmt = $conn->prepare("SELECT supervisor_id FROM contract_documents WHERE id = ?");
    $supStmt->bind_param('i', $contract_id);
    $supStmt->execute();
    $supRow = $supStmt->get_result()->fetch_assoc();
    $supStmt->close();
    
    if ($supRow) {
        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'contract_signed', 'Gambler Signed Contract', 'A gambler has signed their rehabilitation contract.', '/GAMBYTES_Final/app/views/Users/Supervisor/policies.php', 0, NOW())");
        $notifStmt->bind_param('i', $supRow['supervisor_id']);
        $notifStmt->execute();
        $notifStmt->close();
    }
    
    echo json_encode(['success' => true, 'message' => 'Contract signed successfully']);
    exit();
}

// Send contract to family
if ($action === 'send_to_family' && $user['role'] === 'gambler') {
    $contract_id = (int)($_POST['contract_id'] ?? 0);
    
    $stmt = $conn->prepare("SELECT family_id, status FROM contract_documents WHERE id = ? AND gambler_id = ?");
    $stmt->bind_param('ii', $contract_id, $user_id);
    $stmt->execute();
    $contractData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$contractData) {
        echo json_encode(['success' => false, 'message' => 'Contract not found']);
        exit();
    }
    
    if ($contractData['status'] !== 'signed_by_gambler') {
        echo json_encode(['success' => false, 'message' => 'Contract must be signed by gambler first']);
        exit();
    }
    
    if (!$contractData['family_id']) {
        echo json_encode(['success' => false, 'message' => 'No family member linked to your account']);
        exit();
    }
    
    // Update status
    $updStmt = $conn->prepare("UPDATE contract_documents SET status = 'sent_to_family', sent_to_family_at = NOW(), updated_at = NOW() WHERE id = ?");
    $updStmt->bind_param('i', $contract_id);
    $updStmt->execute();
    $updStmt->close();
    
    logWorkflowChange($conn, $contract_id, 'signed_by_gambler', 'sent_to_family', $user_id, 'Contract sent to family for counter-signature');
    
    // Notify family
    $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'contract_review', 'Contract Requires Your Signature', 'Your family member has signed a rehabilitation contract and needs your counter-signature.', '/GAMBYTES_Final/app/views/Users/Family member/contract-review.php?contract_id=?', 0, NOW())");
    $notifStmt->bind_param('ii', $contractData['family_id'], $contract_id);
    $notifStmt->execute();
    $notifStmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Contract sent to family member']);
    exit();
}

// ═══════════════════════════════════════════════════════════════════════════
// FAMILY ACTIONS
// ═══════════════════════════════════════════════════════════════════════════

// Get family's assigned contracts
if ($action === 'get_family_contracts' && $user['role'] === 'family') {
    $stmt = $conn->prepare("
        SELECT 
            cd.*,
            CONCAT(g.first_name, ' ', g.last_name) AS gambler_name,
            g.email AS gambler_email
        FROM contract_documents cd
        JOIN users g ON g.id = cd.gambler_id
        WHERE cd.family_id = ? 
        AND cd.status IN ('sent_to_family', 'signed_by_family', 'completed')
        ORDER BY cd.sent_to_family_at DESC
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $contracts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get policies for each contract
    foreach ($contracts as &$contract) {
        $policyStmt = $conn->prepare("
            SELECT pf.* 
            FROM policy_files pf
            JOIN contract_document_policies cdp ON cdp.policy_file_id = pf.id
            WHERE cdp.contract_document_id = ?
        ");
        $policyStmt->bind_param('i', $contract['id']);
        $policyStmt->execute();
        $contract['policies'] = $policyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $policyStmt->close();
    }
    
    echo json_encode(['success' => true, 'contracts' => $contracts]);
    exit();
}

// Family signs contract
if ($action === 'family_sign' && $user['role'] === 'family') {
    $contract_id = (int)($_POST['contract_id'] ?? 0);
    $signature = $_POST['signature'] ?? '';
    
    if (!$contract_id || !$signature) {
        echo json_encode(['success' => false, 'message' => 'Missing signature data']);
        exit();
    }
    
    $stmt = $conn->prepare("UPDATE contract_documents SET status = 'signed_by_family', family_signature = ?, signed_at_family = NOW(), updated_at = NOW() WHERE id = ? AND family_id = ? AND status = 'sent_to_family'");
    $stmt->bind_param('sii', $signature, $contract_id, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected === 0) {
        echo json_encode(['success' => false, 'message' => 'Contract not found or already signed']);
        exit();
    }
    
    logWorkflowChange($conn, $contract_id, 'sent_to_family', 'signed_by_family', $user_id, 'Family member signed contract');
    
    // Mark as completed
    $conn->query("UPDATE contract_documents SET status = 'completed' WHERE id = $contract_id");
    logWorkflowChange($conn, $contract_id, 'signed_by_family', 'completed', $user_id, 'Contract workflow completed');
    
    // Notify gambler and supervisor
    $notifyStmt = $conn->prepare("SELECT gambler_id, supervisor_id FROM contract_documents WHERE id = ?");
    $notifyStmt->bind_param('i', $contract_id);
    $notifyStmt->execute();
    $notifyData = $notifyStmt->get_result()->fetch_assoc();
    $notifyStmt->close();
    
    if ($notifyData) {
        // Notify gambler
        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'contract_completed', 'Family Member Signed Contract', 'Your family member has signed the rehabilitation contract. The process is now complete.', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 0, NOW())");
        $notifStmt->bind_param('i', $notifyData['gambler_id']);
        $notifStmt->execute();
        $notifStmt->close();
        
        // Notify supervisor
        $notifStmt2 = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'contract_completed', 'Contract Fully Signed', 'A rehabilitation contract has been signed by both gambler and family member.', '/GAMBYTES_Final/app/views/Users/Supervisor/policies.php', 0, NOW())");
        $notifStmt2->bind_param('i', $notifyData['supervisor_id']);
        $notifStmt2->execute();
        $notifStmt2->close();
    }
    
    echo json_encode(['success' => true, 'message' => 'Contract signed successfully. Process completed!']);
    exit();
}

// ═══════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════

function generateContractTemplate($conn, $gambler_id, $family_id) {
    // Get gambler info
    $gStmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
    $gStmt->bind_param('i', $gambler_id);
    $gStmt->execute();
    $gambler = $gStmt->get_result()->fetch_assoc();
    $gStmt->close();
    
    // Get family info if exists
    $family = null;
    if ($family_id) {
        $fStmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
        $fStmt->bind_param('i', $family_id);
        $fStmt->execute();
        $family = $fStmt->get_result()->fetch_assoc();
        $fStmt->close();
    }
    
    $gambler_name = $gambler['first_name'] . ' ' . $gambler['last_name'];
    $family_name = $family ? ($family['first_name'] . ' ' . $family['last_name']) : 'N/A';
    $date = date('F j, Y');
    
    return "
    <div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>
        <h1 style='text-align: center; color: #800000;'>MEMORANDUM OF AGREEMENT</h1>
        <h2 style='text-align: center;'>Rehabilitation Treatment Program</h2>
        
        <p style='text-align: center; margin: 20px 0;'><strong>Date:</strong> $date</p>
        
        <h3>PARTIES:</h3>
        <p><strong>FIRST PARTY (Participant):</strong> $gambler_name</p>
        <p><strong>SECOND PARTY (Family Representative):</strong> $family_name</p>
        <p><strong>THIRD PARTY (Service Provider):</strong> Gambytes Rehabilitation Center</p>
        
        <h3>WHEREAS:</h3>
        <p>The FIRST PARTY has voluntarily sought treatment for gambling disorder and has completed the initial assessment interview;</p>
        <p>The SECOND PARTY agrees to support the FIRST PARTY throughout the rehabilitation process;</p>
        <p>The THIRD PARTY agrees to provide comprehensive rehabilitation services;</p>
        
        <h3>NOW THEREFORE, the parties agree as follows:</h3>
        
        <h4>1. TREATMENT COMMITMENT</h4>
        <p>The FIRST PARTY commits to:</p>
        <ul>
            <li>Attend all scheduled therapy sessions</li>
            <li>Participate actively in group counseling</li>
            <li>Complete assigned therapeutic activities</li>
            <li>Maintain abstinence from gambling activities</li>
            <li>Comply with all program rules and regulations</li>
        </ul>
        
        <h4>2. FAMILY SUPPORT</h4>
        <p>The SECOND PARTY commits to:</p>
        <ul>
            <li>Provide emotional support to the FIRST PARTY</li>
            <li>Attend family counseling sessions when required</li>
            <li>Monitor and report any concerning behaviors</li>
            <li>Assist in creating a supportive home environment</li>
        </ul>
        
        <h4>3. SERVICE PROVIDER OBLIGATIONS</h4>
        <p>The THIRD PARTY commits to:</p>
        <ul>
            <li>Provide evidence-based treatment interventions</li>
            <li>Maintain confidentiality of all information</li>
            <li>Conduct regular progress assessments</li>
            <li>Provide crisis intervention when needed</li>
            <li>Coordinate with family members as appropriate</li>
        </ul>
        
        <h4>4. DURATION</h4>
        <p>This agreement shall remain in effect for the duration of the treatment program, typically 90 days, unless terminated earlier by mutual consent or program completion.</p>
        
        <h4>5. CONFIDENTIALITY</h4>
        <p>All parties agree to maintain the confidentiality of information shared during the treatment process, except as required by law or for treatment purposes.</p>
        
        <h4>6. TERMINATION</h4>
        <p>This agreement may be terminated by:</p>
        <ul>
            <li>Successful completion of the program</li>
            <li>Voluntary withdrawal by the FIRST PARTY</li>
            <li>Violation of program rules</li>
            <li>Mutual agreement of all parties</li>
        </ul>
        
        <h4>7. ACKNOWLEDGMENT</h4>
        <p>By signing below, all parties acknowledge that they have read, understood, and agree to the terms of this Memorandum of Agreement.</p>
        
        <div style='margin-top: 40px;'>
            <p><strong>FIRST PARTY (Participant):</strong></p>
            <p>Name: $gambler_name</p>
            <p>Signature: ________________________</p>
            <p>Date: ________________________</p>
        </div>
        
        <div style='margin-top: 30px;'>
            <p><strong>SECOND PARTY (Family Representative):</strong></p>
            <p>Name: $family_name</p>
            <p>Signature: ________________________</p>
            <p>Date: ________________________</p>
        </div>
        
        <div style='margin-top: 30px;'>
            <p><strong>THIRD PARTY (Service Provider):</strong></p>
            <p>Name: Gambytes Rehabilitation Center</p>
            <p>Authorized Representative: ________________________</p>
            <p>Date: ________________________</p>
        </div>
    </div>
    ";
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
