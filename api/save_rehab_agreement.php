<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../app/core/Database.php';

// Catch any PHP warnings/notices that would break JSON output
ob_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = new Database();
$conn = $db->connect();

if ($conn->connect_error) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$user_id          = (int)$_SESSION['user_id'];
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

$conn->query("CREATE TABLE IF NOT EXISTS `contract_submissions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `template_id` INT(11) NOT NULL DEFAULT 0,
    `gambler_id` INT(11) NOT NULL,
    `family_member_id` INT(11) NULL,
    `booking_id` INT(11) NULL,
    `gambler_data` LONGTEXT NULL,
    `family_data` LONGTEXT NULL,
    `gambler_sig` LONGTEXT NULL,
    `family_sig` LONGTEXT NULL,
    `status` ENUM('draft','submitted','reviewed','sent_to_parties','completed') NOT NULL DEFAULT 'draft',
    `sent_at` DATETIME NULL,
    `submitted_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

try {
    // Find or create the gambler's contract submission
    $findContract = $conn->prepare("SELECT id, booking_id, family_member_id FROM contract_submissions 
        WHERE gambler_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1");
    
    if (!$findContract) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error (prepare findContract): ' . $conn->error]);
        exit();
    }
    
    $findContract->bind_param('i', $user_id);
    $findContract->execute();
    $contractResult = $findContract->get_result()->fetch_assoc();
    $findContract->close();

    $contract_id = null;
    $booking_id = null;
    $family_member_id = null; // Declare outside IF block so it's available later

    if ($contractResult) {
        $contract_id = $contractResult['id'];
        $booking_id = $contractResult['booking_id'];
        
        // Get family_member_id from existing contract
        $family_member_id = $contractResult['family_member_id'] ?? null;
        
        // If not set, try to find from parental_control_requests
        if (!$family_member_id) {
            $famStmt = $conn->prepare("SELECT family_id FROM parental_control_requests WHERE gambler_id = ? AND status = 'accepted' LIMIT 1");
            if ($famStmt) {
                $famStmt->bind_param('i', $user_id);
                $famStmt->execute();
                $famRow = $famStmt->get_result()->fetch_assoc();
                $famStmt->close();
                if ($famRow) {
                    $family_member_id = (int)$famRow['family_id'];
                }
            }
        }
    } else {
        // Create a new contract submission if none exists
        // Try to find booking_id from booking_record
        $userEmail = '';
        $emailStmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
        if ($emailStmt) {
            $emailStmt->bind_param('i', $user_id);
            $emailStmt->execute();
            $emailRow = $emailStmt->get_result()->fetch_assoc();
            $emailStmt->close();
            $userEmail = $emailRow ? $emailRow['email'] : '';
        }
        
        $bookingStmt = $conn->prepare("SELECT id FROM booking_record WHERE email = ? ORDER BY created_at DESC LIMIT 1");
        if ($bookingStmt) {
            $bookingStmt->bind_param('s', $userEmail);
            $bookingStmt->execute();
            $bookingRow = $bookingStmt->get_result()->fetch_assoc();
            $bookingStmt->close();
            $booking_id = $bookingRow ? (int)$bookingRow['id'] : null;
        }

        // Find linked family member
        $family_member_id = null;
        $famStmt = $conn->prepare("SELECT family_id FROM parental_control_requests WHERE gambler_id = ? AND status = 'accepted' LIMIT 1");
        if ($famStmt) {
            $famStmt->bind_param('i', $user_id);
            $famStmt->execute();
            $famRow = $famStmt->get_result()->fetch_assoc();
            $famStmt->close();
            if ($famRow) {
                $family_member_id = (int)$famRow['family_id'];
            }
        }

        // Create contract submission
        $createContract = $conn->prepare("INSERT INTO contract_submissions 
            (gambler_id, family_member_id, booking_id, status, created_at) 
            VALUES (?, ?, ?, 'draft', NOW())");
        
        if (!$createContract) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Database error (prepare createContract): ' . $conn->error]);
            exit();
        }
        
        $createContract->bind_param('iii', $user_id, $family_member_id, $booking_id);
        
        if (!$createContract->execute()) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Database error (execute createContract): ' . $createContract->error]);
            exit();
        }
        
        $contract_id = $conn->insert_id;
        $createContract->close();
    }

    if (!$contract_id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to create contract submission.']);
        exit();
    }

    // Get IP address and user agent
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    // Check if gambler already signed this contract
    $checkSig = $conn->prepare("SELECT id FROM signed_contract_documents 
        WHERE contract_document_id = ? AND signer_id = ? AND signer_role = 'gambler'");
    
    if (!$checkSig) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error (prepare checkSig): ' . $conn->error]);
        exit();
    }
    
    $checkSig->bind_param('ii', $contract_id, $user_id);
    $checkSig->execute();
    $existingSig = $checkSig->get_result()->fetch_assoc();
    $checkSig->close();

    if ($existingSig) {
        // Update existing signature
        $updateSig = $conn->prepare("UPDATE signed_contract_documents 
            SET signature_data = ?, signature_hash = ?, signed_at = NOW(), ip_address = ?, user_agent = ?
            WHERE id = ?");
        
        if (!$updateSig) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Database error (prepare updateSig): ' . $conn->error]);
            exit();
        }
        
        $updateSig->bind_param('ssssi', $signature_data, $hashed_signature, $ip_address, $user_agent, $existingSig['id']);
        
        if (!$updateSig->execute()) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Database error (execute updateSig): ' . $updateSig->error]);
            exit();
        }
        
        $result = true;
        $updateSig->close();
    } else {
        // Insert new signature
        $insertSig = $conn->prepare("INSERT INTO signed_contract_documents 
            (contract_document_id, signer_id, signer_role, signature_data, signature_hash, signed_at, ip_address, user_agent)
            VALUES (?, ?, 'gambler', ?, ?, NOW(), ?, ?)");
        
        if (!$insertSig) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Database error (prepare insertSig): ' . $conn->error]);
            exit();
        }
        
        $insertSig->bind_param('iissss', $contract_id, $user_id, $signature_data, $hashed_signature, $ip_address, $user_agent);
        
        if (!$insertSig->execute()) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Database error (execute insertSig): ' . $insertSig->error]);
            exit();
        }
        
        $result = true;
        $insertSig->close();
    }

    if (!$result) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to save signature: ' . $conn->error]);
        exit();
    }

    // Update contract submission status to 'submitted' AND link family member
    $updateStatus = $conn->prepare("UPDATE contract_submissions 
        SET status = 'submitted', submitted_at = NOW() 
        WHERE id = ?");
    
    if (!$updateStatus) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error (prepare updateStatus): ' . $conn->error]);
        exit();
    }
    
    $updateStatus->bind_param('i', $contract_id);
    
    if (!$updateStatus->execute()) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error (execute updateStatus): ' . $updateStatus->error]);
        exit();
    }
    
    $updateStatus->close();

    // IMPORTANT: Update family_member_id if we found one and it's not already set
    if ($family_member_id) {
        $updateFamily = $conn->prepare("UPDATE contract_submissions 
            SET family_member_id = ? 
            WHERE id = ?");
        
        if ($updateFamily) {
            $updateFamily->bind_param('ii', $family_member_id, $contract_id);
            $updateFamily->execute();
            $updateFamily->close();
        }
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Rehab agreement submitted successfully! Your contract is now pending verification.'
    ]);

} catch (Exception $e) {
    ob_end_clean();
    error_log("save_rehab_agreement.php Exception: " . $e->getMessage());
    error_log("save_rehab_agreement.php Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
