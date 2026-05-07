<?php
/**
 * api/create_checkout.php
 * Creates a PayMongo Checkout Session for the rehabilitation program payment.
 */
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../app/core/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db   = new Database();
$conn = $db->connect();

$user_id = (int)$_SESSION['user_id'];

// Load user
$uStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->bind_param('i', $user_id);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

if (!$user || !in_array($user['role'], ['gambler', 'family'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Parse request body
$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$booking_id = (int)($input['booking_id'] ?? 0);

// Ensure payments table has all needed columns
$conn->query("CREATE TABLE IF NOT EXISTS `payments` (
    `id`                    INT(11)       NOT NULL AUTO_INCREMENT,
    `user_id`               INT(11)       NOT NULL,
    `booking_id`            INT(11)       NULL,
    `amount`                DECIMAL(10,2) NOT NULL DEFAULT 50000.00,
    `currency`              VARCHAR(10)   NOT NULL DEFAULT 'PHP',
    `payment_status`        VARCHAR(50)   NOT NULL DEFAULT 'pending',
    `paymongo_session_id`   VARCHAR(255)  NULL,
    `paymongo_payment_id`   VARCHAR(255)  NULL,
    `paid_at`               DATETIME      NULL,
    `created_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `booking_id` INT(11) NULL");
$conn->query("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `currency` VARCHAR(10) NOT NULL DEFAULT 'PHP'");
$conn->query("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `paymongo_session_id` VARCHAR(255) NULL");
$conn->query("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `paymongo_payment_id` VARCHAR(255) NULL");
$conn->query("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
$conn->query("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

// Check if already paid — check by booking_id so either gambler OR family can't double-pay
if ($booking_id) {
    $pChk = $conn->prepare("SELECT id FROM payments WHERE booking_id = ? AND payment_status IN ('paid','verified') LIMIT 1");
    $pChk->bind_param('i', $booking_id);
    $pChk->execute();
    if ($pChk->get_result()->fetch_assoc()) {
        $pChk->close();
        echo json_encode(['success' => false, 'message' => 'Payment has already been completed for this booking.']);
        exit();
    }
    $pChk->close();
}

// ── PayMongo config ───────────────────────────────────────────────────────────
$paymongo_key = defined('PAYMONGO_SECRET_KEY') ? PAYMONGO_SECRET_KEY : getenv('PAYMONGO_SECRET_KEY');
if (!$paymongo_key) {
    echo json_encode(['success' => false, 'message' => 'PayMongo configuration missing']);
    exit();
}

define('PAYMONGO_API_URL',  'https://api.paymongo.com/v1');
define('TREATMENT_AMOUNT', 5000000); // ₱50,000.00 in centavos

$full_name = trim($user['first_name'] . ' ' . $user['last_name']);
$email     = $user['email'] ?? '';

// ── Build success & cancel URLs (URL-encode the space in folder name) ─────────
$base = 'http://' . $_SERVER['HTTP_HOST'] . '/GAMBYTES_Final';

// Generate a short-lived token to survive the PayMongo redirect (session may be lost)
$redirect_token = hash('sha256', $user_id . $booking_id . date('YmdH') . 'gambytes_pay');

$success_url = $base . '/app/views/Users/admin%20department/payment/receipt.php?status=success&booking_id=' . $booking_id . '&uid=' . $user_id . '&tok=' . $redirect_token;
$cancel_url  = $base . '/app/views/Users/admin%20department/payment/pay.php?booking_id=' . $booking_id;

// ── Create a pending payment record first ─────────────────────────────────────
$insStmt = $conn->prepare(
    "INSERT INTO payments (user_id, booking_id, amount, currency, payment_status, created_at)
     VALUES (?, ?, 50000.00, 'PHP', 'pending', NOW())"
);
$insStmt->bind_param('ii', $user_id, $booking_id);
$insStmt->execute();
$payment_db_id = (int)$conn->insert_id;
$insStmt->close();

// ── Call PayMongo Checkout Sessions API ───────────────────────────────────────
$payload = [
    'data' => [
        'attributes' => [
            'billing' => [
                'name'  => $full_name,
                'email' => $email,
            ],
            'send_email_receipt' => true,
            'show_description'   => true,
            'show_line_items'    => true,
            'cancel_url'         => $cancel_url,
            'success_url'        => $success_url . '&payment_db_id=' . $payment_db_id,
            'description'        => 'Gambytes Rehabilitation Program – 6 Months Treatment',
            'line_items'         => [
                [
                    'currency'    => 'PHP',
                    'amount'      => TREATMENT_AMOUNT,
                    'name'        => '6-Month Rehabilitation Treatment Program',
                    'description' => 'Includes weekly individual & group therapy sessions, aftercare support, and rehabilitation services.',
                    'quantity'    => 1,
                ]
            ],
            'payment_method_types' => ['card', 'gcash'],
            'metadata' => [
                'payment_db_id' => (string)$payment_db_id,
                'booking_id'    => (string)$booking_id,
                'user_id'       => (string)$user_id,
            ],
        ]
    ]
];

$ch = curl_init(PAYMONGO_API_URL . '/checkout_sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($paymongo_key . ':'),
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response    = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error  = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    // Clean up pending record
    $conn->query("DELETE FROM payments WHERE id = $payment_db_id");
    echo json_encode(['success' => false, 'message' => 'Connection error: ' . $curl_error]);
    exit();
}

$result = json_decode($response, true);

if ($http_status !== 200 || empty($result['data'])) {
    // Clean up pending record
    $conn->query("DELETE FROM payments WHERE id = $payment_db_id");
    $errMsg = $result['errors'][0]['detail'] ?? ('PayMongo error (HTTP ' . $http_status . ')');
    // Include full response in dev for debugging
    echo json_encode(['success' => false, 'message' => $errMsg, 'debug' => $result]);
    exit();
}

$session_id   = $result['data']['id'];
$checkout_url = $result['data']['attributes']['checkout_url'];

// Save session ID to payment record
$updStmt = $conn->prepare("UPDATE payments SET paymongo_session_id = ? WHERE id = ?");
$updStmt->bind_param('si', $session_id, $payment_db_id);
$updStmt->execute();
$updStmt->close();

echo json_encode([
    'success'      => true,
    'checkout_url' => $checkout_url,
    'session_id'   => $session_id,
]);
