<?php
/**
 * api/verify_payment.php
 * Admin verifies a payment and generates an official receipt.
 * Notifies the payer and the other linked party (gambler ↔ family).
 */
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../app/core/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db   = new Database();
$conn = $db->connect();

$admin_id = (int)$_SESSION['user_id'];

// Verify admin role
$aStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$aStmt->bind_param('i', $admin_id);
$aStmt->execute();
$admin = $aStmt->get_result()->fetch_assoc();
$aStmt->close();

if (!$admin || $admin['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$payment_id = (int)($input['payment_id'] ?? 0);
$notes      = trim($input['notes'] ?? '');

if (!$payment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
    exit();
}

// Ensure columns exist
$conn->query("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `verified_by` INT(11) NULL");
$conn->query("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `verified_at` DATETIME NULL");
$conn->query("ALTER TABLE `receipts`  ADD COLUMN IF NOT EXISTS `verified_by` INT(11) NULL");
$conn->query("ALTER TABLE `receipts`  ADD COLUMN IF NOT EXISTS `verified_at` DATETIME NULL");
$conn->query("ALTER TABLE `receipts`  ADD COLUMN IF NOT EXISTS `notes` TEXT NULL");

// Load payment with payer info
$pStmt = $conn->prepare(
    "SELECT p.*,
            CONCAT(u.first_name,' ',u.last_name) AS payer_name,
            u.email AS payer_email,
            u.role  AS payer_role
     FROM payments p
     JOIN users u ON u.id = p.user_id
     WHERE p.id = ? AND p.payment_status = 'paid'
     LIMIT 1"
);
$pStmt->bind_param('i', $payment_id);
$pStmt->execute();
$payment = $pStmt->get_result()->fetch_assoc();
$pStmt->close();

if (!$payment) {
    echo json_encode(['success' => false, 'message' => 'Payment not found or already verified']);
    exit();
}

// Generate receipt number: RCP-YYYYMMDD-XXXXX
$receipt_number = 'RCP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

$conn->begin_transaction();
try {
    // 1. Update payment status to verified
    $upd = $conn->prepare(
        "UPDATE payments SET payment_status='verified', verified_by=?, verified_at=NOW(), updated_at=NOW() WHERE id=?"
    );
    $upd->bind_param('ii', $admin_id, $payment_id);
    $upd->execute();
    $upd->close();

    // 2. Insert receipt record
    $ins = $conn->prepare(
        "INSERT INTO receipts (payment_id, receipt_number, verified_by, verified_at, notes, created_at)
         VALUES (?, ?, ?, NOW(), ?, NOW())"
    );
    $ins->bind_param('isis', $payment_id, $receipt_number, $admin_id, $notes);
    $ins->execute();
    $receipt_id = (int)$conn->insert_id;
    $ins->close();

    // 3. Build receipt link — use %20 for the space in "admin department" so the URL is valid
    $receipt_link = '/GAMBYTES_Final/app/views/Users/admin%20department/payment/view-receipt.php?receipt_id=' . $receipt_id;

    $notifTitle = 'Payment Verified – Official Receipt Issued';
    $notifType  = 'payment_verified';

    $payer_user_id = (int)$payment['user_id'];
    $payer_role    = $payment['payer_role'];

    // 4. Notify the payer directly
    $notifMsgPayer = 'Your payment of ₱' . number_format($payment['amount'], 2) . ' has been verified. Receipt No: ' . $receipt_number . '. Click to view your official receipt.';
    $n1 = $conn->prepare("INSERT INTO notifications (user_id,type,title,message,link,is_read,created_at) VALUES (?,?,?,?,?,0,NOW())");
    $n1->bind_param('issss', $payer_user_id, $notifType, $notifTitle, $notifMsgPayer, $receipt_link);
    $n1->execute();
    $n1->close();

    // 5. Notify the other linked party
    if ($payer_role === 'gambler') {
        // Payer is gambler — notify linked family member
        $fStmt = $conn->prepare(
            "SELECT pcr.family_id
             FROM parental_control_requests pcr
             WHERE pcr.gambler_id = ? AND pcr.status = 'accepted'
             LIMIT 1"
        );
        $fStmt->bind_param('i', $payer_user_id);
        $fStmt->execute();
        $familyRow = $fStmt->get_result()->fetch_assoc();
        $fStmt->close();

        if ($familyRow) {
            $family_id   = (int)$familyRow['family_id'];
            $notifMsgFam = 'The payment of ₱' . number_format($payment['amount'], 2) . ' for your family member\'s rehabilitation has been verified. Receipt No: ' . $receipt_number . '. Click to view the official receipt.';
            $n2 = $conn->prepare("INSERT INTO notifications (user_id,type,title,message,link,is_read,created_at) VALUES (?,?,?,?,?,0,NOW())");
            $n2->bind_param('issss', $family_id, $notifType, $notifTitle, $notifMsgFam, $receipt_link);
            $n2->execute();
            $n2->close();
        }
    } else {
        // Payer is family member — notify the linked gambler
        $gStmt = $conn->prepare(
            "SELECT pcr.gambler_id
             FROM parental_control_requests pcr
             WHERE pcr.family_id = ? AND pcr.status = 'accepted'
             LIMIT 1"
        );
        $gStmt->bind_param('i', $payer_user_id);
        $gStmt->execute();
        $gamblerRow = $gStmt->get_result()->fetch_assoc();
        $gStmt->close();

        if ($gamblerRow) {
            $gambler_id   = (int)$gamblerRow['gambler_id'];
            $notifMsgGam  = 'The payment of ₱' . number_format($payment['amount'], 2) . ' for your rehabilitation program has been verified by your family member. Receipt No: ' . $receipt_number . '. Click to view the official receipt.';
            $n3 = $conn->prepare("INSERT INTO notifications (user_id,type,title,message,link,is_read,created_at) VALUES (?,?,?,?,?,0,NOW())");
            $n3->bind_param('issss', $gambler_id, $notifType, $notifTitle, $notifMsgGam, $receipt_link);
            $n3->execute();
            $n3->close();
        }
    }

    $conn->commit();

    echo json_encode([
        'success'        => true,
        'receipt_id'     => $receipt_id,
        'receipt_number' => $receipt_number,
        'receipt_link'   => $receipt_link,
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
