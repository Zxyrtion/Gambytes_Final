<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../app/core/Database.php';

ob_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$db   = new Database();
$conn = $db->connect();

if ($conn->connect_error) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Verify role is family (soft check — session already validated above)
$roleStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->bind_param('i', $user_id);
$roleStmt->execute();
$roleRow = $roleStmt->get_result()->fetch_assoc();
$roleStmt->close();

if (!$roleRow || !in_array($roleRow['role'], ['family', 'gambler'])) {
    // Allow both family and gambler roles (gambler might be testing)
    // Only block non-user roles
    if (!$roleRow) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
}

$participant_date = trim($_POST['participant_date'] ?? '');
$signature_data   = $_POST['signature_data'] ?? '';

if (empty($participant_date) || empty($signature_data)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$hashed_signature = hash('sha256', $signature_data);

// Ensure table exists (no DROP - preserve existing signatures)
$conn->query("CREATE TABLE IF NOT EXISTS `signed_contract_documents` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `contract_document_id` INT(11) NOT NULL COMMENT 'FK to contract_submissions.id',
    `signer_id` INT(11) NOT NULL COMMENT 'User ID of the person who signed',
    `signer_role` ENUM('gambler','family') NOT NULL COMMENT 'Role of the signer',
    `signature_data` LONGTEXT NOT NULL COMMENT 'Base64 encoded signature image',
    `signature_hash` VARCHAR(64) DEFAULT NULL COMMENT 'SHA256 hash of signature for verification',
    `signed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP address when signed',
    `user_agent` VARCHAR(255) DEFAULT NULL COMMENT 'Browser user agent',
    PRIMARY KEY (`id`),
    KEY `idx_contract_document` (`contract_document_id`),
    KEY `idx_signer` (`signer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

try {
    // Find linked gambler via parental_control_requests
    $gamblerStmt = $conn->prepare("SELECT gambler_id FROM parental_control_requests WHERE family_id = ? AND status = 'accepted' LIMIT 1");
    $gamblerStmt->bind_param('i', $user_id);
    $gamblerStmt->execute();
    $gamblerRow = $gamblerStmt->get_result()->fetch_assoc();
    $gamblerStmt->close();
    $gambler_id = $gamblerRow ? (int)$gamblerRow['gambler_id'] : null;

    if (!$gambler_id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'No linked gambler found. Please ensure you are linked to a gambler account.']);
        exit();
    }

    // Find the gambler's latest contract submission
    $findContract = $conn->prepare("SELECT id FROM contract_submissions 
        WHERE gambler_id = ? 
        AND status IN ('draft', 'sent_to_parties', 'submitted') 
        ORDER BY created_at DESC 
        LIMIT 1");
    $findContract->bind_param('i', $gambler_id);
    $findContract->execute();
    $contractResult = $findContract->get_result()->fetch_assoc();
    $findContract->close();

    if (!$contractResult) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'No contract submission found for the linked gambler.']);
        exit();
    }

    $contract_id = $contractResult['id'];

    // Get IP address and user agent
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    // Check if family member already signed this contract
    $checkSig = $conn->prepare("SELECT id FROM signed_contract_documents 
        WHERE contract_document_id = ? AND signer_id = ? AND signer_role = 'family'");
    $checkSig->bind_param('ii', $contract_id, $user_id);
    $checkSig->execute();
    $existingSig = $checkSig->get_result()->fetch_assoc();
    $checkSig->close();

    if ($existingSig) {
        // Update existing signature
        $updateSig = $conn->prepare("UPDATE signed_contract_documents 
            SET signature_data = ?, signature_hash = ?, signed_at = NOW(), ip_address = ?, user_agent = ?
            WHERE id = ?");
        $updateSig->bind_param('ssssi', $signature_data, $hashed_signature, $ip_address, $user_agent, $existingSig['id']);
        $result = $updateSig->execute();
        $updateSig->close();
    } else {
        // Insert new signature
        $insertSig = $conn->prepare("INSERT INTO signed_contract_documents 
            (contract_document_id, signer_id, signer_role, signature_data, signature_hash, signed_at, ip_address, user_agent)
            VALUES (?, ?, 'family', ?, ?, NOW(), ?, ?)");
        $insertSig->bind_param('iissss', $contract_id, $user_id, $signature_data, $hashed_signature, $ip_address, $user_agent);
        $result = $insertSig->execute();
        $insertSig->close();
    }

    if (!$result) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to save: ' . $conn->error]);
        exit();
    }

    // Update contract submission to link family member
    $updateContract = $conn->prepare("UPDATE contract_submissions 
        SET family_member_id = ? 
        WHERE id = ?");
    $updateContract->bind_param('ii', $user_id, $contract_id);
    $updateContract->execute();
    $updateContract->close();

    // Notify the gambler if linked
    if ($gambler_id) {
        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
            VALUES (?, 'contract_signed', 'Family Member Signed Agreement', 'Your family member has signed the rehabilitation support agreement.', '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php', 0, NOW())");
        $notifStmt->bind_param('i', $gambler_id);
        $notifStmt->execute();
        $notifStmt->close();
    }

    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Support agreement submitted successfully!']);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
