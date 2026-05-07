<?php
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

$user_id = $_SESSION['user_id'];
$signature_data = $_POST['signature_data'] ?? '';

if (empty($signature_data)) {
    echo json_encode(['success' => false, 'message' => 'Missing signature data']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Get the stored signature from signed_contract_documents for this user
    $stmt = $conn->prepare("SELECT signature_data, signature_hash 
                           FROM signed_contract_documents 
                           WHERE signer_id = ? AND signer_role = 'gambler'
                           ORDER BY signed_at DESC LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$result || !$result['signature_hash']) {
        echo json_encode(['success' => false, 'message' => 'No stored signature found']);
        exit();
    }
    
    $stored_hash = $result['signature_hash'];
    
    // Hash the provided signature data
    $computed_hash = hash('sha256', $signature_data);
    
    // Verify the signature
    $is_valid = hash_equals($stored_hash, $computed_hash);
    
    echo json_encode([
        'success' => true,
        'valid' => $is_valid,
        'algorithm' => 'sha256',
        'message' => $is_valid ? 'Signature verified successfully' : 'Signature verification failed'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
