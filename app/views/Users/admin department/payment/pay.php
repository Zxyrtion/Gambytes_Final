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

// Load user
$uStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->bind_param('i', $user_id);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

if (!$user || !in_array($user['role'], ['gambler', 'family'])) {
    header("Location: " . url('app/views/auth/dashboard.php'));
    exit();
}

$full_name = $user['first_name'] . ' ' . $user['last_name'];

// Load booking info if available
$booking = null;
if ($booking_id) {
    $bStmt = $conn->prepare("SELECT * FROM booking_record WHERE id = ?");
    $bStmt->bind_param('i', $booking_id);
    $bStmt->execute();
    $booking = $bStmt->get_result()->fetch_assoc();
    $bStmt->close();
}

// Check if already paid — ensure payments table has all needed columns
$alreadyPaid = false;
$conn->query("CREATE TABLE IF NOT EXISTS `payments` (
    `id`                INT(11)       NOT NULL AUTO_INCREMENT,
    `user_id`           INT(11)       NOT NULL,
    `booking_id`        INT(11)       NULL,
    `amount`            DECIMAL(10,2) NOT NULL DEFAULT 50000.00,
    `currency`          VARCHAR(10)   NOT NULL DEFAULT 'PHP',
    `payment_status`    VARCHAR(50)   NOT NULL DEFAULT 'pending',
    `paymongo_session_id` VARCHAR(255) NULL,
    `paymongo_payment_id` VARCHAR(255) NULL,
    `paid_at`           DATETIME      NULL,
    `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add missing columns if the table already existed with old structure
$conn->query("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `booking_id` INT(11) NULL");
$conn->query("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `currency` VARCHAR(10) NOT NULL DEFAULT 'PHP'");
$conn->query("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `paymongo_session_id` VARCHAR(255) NULL");
$conn->query("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `paymongo_payment_id` VARCHAR(255) NULL");
$conn->query("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
$conn->query("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

if ($booking_id) {
    // Check if ANYONE (gambler or family) has already paid for this booking
    $pChk = $conn->prepare("SELECT id, payment_status FROM payments WHERE booking_id = ? AND payment_status IN ('paid','verified') LIMIT 1");
    if (!$pChk) {
        die("Query error: " . $conn->error);
    }
    $pChk->bind_param('i', $booking_id);
    $pChk->execute();
    $paidRow = $pChk->get_result()->fetch_assoc();
    $pChk->close();
    if ($paidRow) $alreadyPaid = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment – Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
    <style>
        body { background:#f4f6f9; font-family:'Inter',sans-serif; }
        .pay-wrapper { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem; }
        .pay-card { background:#fff; border-radius:20px; box-shadow:0 8px 40px rgba(0,0,0,.12); max-width:520px; width:100%; overflow:hidden; }
        .pay-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:2rem; text-align:center; }
        .pay-header .logo { font-size:2rem; font-weight:800; letter-spacing:-1px; margin-bottom:.25rem; }
        .pay-header p { opacity:.85; font-size:.9rem; margin:0; }
        .pay-body { padding:2rem; }
        .billing-row { display:flex; justify-content:space-between; align-items:center; padding:.75rem 0; border-bottom:1px solid #f0f0f0; font-size:.92rem; }
        .billing-row:last-child { border-bottom:none; }
        .billing-row .label { color:#6c757d; font-weight:500; }
        .billing-row .value { font-weight:700; color:#212529; }
        .total-row { background:#f8f9fa; border-radius:12px; padding:1rem 1.25rem; margin:1.25rem 0; display:flex; justify-content:space-between; align-items:center; }
        .total-row .label { font-weight:700; color:#343a40; font-size:1rem; }
        .total-row .amount { font-size:1.6rem; font-weight:800; color:#800000; }
        .btn-pay { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border:none; border-radius:14px; padding:1rem 2rem; font-weight:700; font-size:1rem; width:100%; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:.6rem; box-shadow:0 4px 16px rgba(128,0,0,.35); transition:all .2s; }
        .btn-pay:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 8px 24px rgba(128,0,0,.45); }
        .btn-pay:disabled { opacity:.6; cursor:not-allowed; }
        .btn-back { background:#fff; color:#6c757d; border:2px solid #dee2e6; border-radius:12px; padding:.7rem 1.5rem; font-weight:600; font-size:.9rem; text-decoration:none; display:inline-flex; align-items:center; gap:.5rem; transition:all .2s; }
        .btn-back:hover { border-color:#800000; color:#800000; }
        .badge-secure { background:#e8f5e9; color:#2e7d32; border-radius:20px; padding:.3rem .85rem; font-size:.78rem; font-weight:700; display:inline-flex; align-items:center; gap:.35rem; }
        .divider { border:none; border-top:2px solid #f0f0f0; margin:1.25rem 0; }
        /* Modal */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9999; align-items:center; justify-content:center; }
        .modal-overlay.show { display:flex; }
        .modal-box { background:#fff; border-radius:20px; box-shadow:0 16px 60px rgba(0,0,0,.2); max-width:480px; width:90%; overflow:hidden; animation:slideUp .25s ease; }
        @keyframes slideUp { from{transform:translateY(30px);opacity:0} to{transform:translateY(0);opacity:1} }
        .modal-head { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:1.5rem; }
        .modal-head h4 { margin:0; font-weight:800; font-size:1.15rem; }
        .modal-head p { margin:.25rem 0 0; opacity:.85; font-size:.85rem; }
        .modal-body-inner { padding:1.75rem; }
        .modal-item { display:flex; justify-content:space-between; align-items:flex-start; padding:.65rem 0; border-bottom:1px solid #f0f0f0; font-size:.9rem; }
        .modal-item:last-child { border-bottom:none; }
        .modal-item .mi-label { color:#6c757d; }
        .modal-item .mi-val { font-weight:700; color:#212529; text-align:right; max-width:60%; }
        .modal-total { background:linear-gradient(135deg,#f8f9fa,#e9ecef); border-radius:12px; padding:1rem 1.25rem; margin:1rem 0; display:flex; justify-content:space-between; align-items:center; }
        .modal-total .mt-label { font-weight:700; color:#343a40; }
        .modal-total .mt-amount { font-size:1.5rem; font-weight:800; color:#800000; }
        .modal-note { background:#fff8e1; border-left:4px solid #ffc107; border-radius:8px; padding:.75rem 1rem; font-size:.82rem; color:#5a4a00; margin-bottom:1.25rem; }
        .btn-agree { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border:none; border-radius:12px; padding:.85rem 1.5rem; font-weight:700; font-size:.95rem; width:100%; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:.6rem; box-shadow:0 4px 14px rgba(128,0,0,.3); transition:all .2s; }
        .btn-agree:hover:not(:disabled) { transform:translateY(-2px); }
        .btn-agree:disabled { opacity:.6; cursor:not-allowed; }
        .btn-cancel-modal { background:#fff; color:#6c757d; border:2px solid #dee2e6; border-radius:12px; padding:.75rem 1.5rem; font-weight:600; font-size:.9rem; width:100%; cursor:pointer; margin-top:.6rem; transition:all .2s; }
        .btn-cancel-modal:hover { border-color:#800000; color:#800000; }
    </style>
</head>
<body>
<div class="pay-wrapper">
    <div class="pay-card">
        <div class="pay-header">
            <div class="logo"><i class="fas fa-shield-alt me-2"></i>Gambytes</div>
            <p>Rehabilitation Program Payment</p>
        </div>
        <div class="pay-body">

            <?php if ($alreadyPaid): ?>
            <!-- Already paid -->
            <div style="text-align:center;padding:1.5rem 0">
                <i class="fas fa-check-circle fa-3x mb-3" style="color:#198754;display:block"></i>
                <h4 style="color:#198754;font-weight:800">Payment Already Completed</h4>
                <p style="color:#6c757d;font-size:.9rem">Your payment for the rehabilitation program has been successfully processed.</p>
                <a href="/GAMBYTES_Final/app/views/auth/dashboard.php" class="btn-back mt-2">
                    <i class="fas fa-home"></i> Back to Dashboard
                </a>
            </div>

            <?php else: ?>

            <!-- Payer info -->
            <div style="margin-bottom:1.25rem">
                <div style="font-size:.78rem;color:#6c757d;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.5rem">Billed To</div>
                <div style="font-weight:700;color:#212529;font-size:1rem"><?= htmlspecialchars($full_name) ?></div>
                <div style="font-size:.85rem;color:#6c757d"><?= htmlspecialchars($user['email'] ?? '') ?></div>
            </div>

            <hr class="divider">

            <!-- Billing breakdown -->
            <div style="margin-bottom:.5rem">
                <div style="font-size:.78rem;color:#6c757d;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.75rem">Billing Summary</div>
                <div class="billing-row">
                    <span class="label">Program</span>
                    <span class="value">Rehabilitation Treatment</span>
                </div>
                <div class="billing-row">
                    <span class="label">Duration</span>
                    <span class="value">6 Months</span>
                </div>
                <div class="billing-row">
                    <span class="label">Sessions</span>
                    <span class="value">Weekly (Individual & Group)</span>
                </div>
                <div class="billing-row">
                    <span class="label">Location</span>
                    <span class="value">Rehabilitation Center</span>
                </div>
            </div>

            <div class="total-row">
                <span class="label"><i class="fas fa-receipt me-2" style="color:#800000"></i>Total Amount</span>
                <span class="amount">₱50,000.00</span>
            </div>

            <div class="badge-secure mb-3">
                <i class="fas fa-lock"></i> Secured by PayMongo
            </div>

            <button type="button" class="btn-pay" onclick="openBillingModal()">
                <i class="fas fa-credit-card"></i> Pay Now
            </button>

            <div style="text-align:center;margin-top:1rem">
                <a href="javascript:history.back()" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Go Back
                </a>
            </div>

            <div style="text-align:center;margin-top:1.25rem;font-size:.78rem;color:#adb5bd">
                <i class="fas fa-shield-alt me-1"></i> Payments are processed securely via PayMongo. We do not store your card details.
            </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Billing Confirmation Modal ── -->
<div class="modal-overlay" id="billingModal">
    <div class="modal-box">
        <div class="modal-head">
            <h4><i class="fas fa-file-invoice-dollar me-2"></i>Confirm Your Payment</h4>
            <p>Please review your billing details before proceeding.</p>
        </div>
        <div class="modal-body-inner">
            <div class="modal-item">
                <span class="mi-label">Patient Name</span>
                <span class="mi-val"><?= htmlspecialchars($full_name) ?></span>
            </div>
            <div class="modal-item">
                <span class="mi-label">Program</span>
                <span class="mi-val">Rehabilitation Treatment – 6 Months</span>
            </div>
            <div class="modal-item">
                <span class="mi-label">Includes</span>
                <span class="mi-val">Weekly individual & group therapy sessions, aftercare support</span>
            </div>
            <div class="modal-item">
                <span class="mi-label">Payment Method</span>
                <span class="mi-val">Card / GCash / Maya / Online Banking</span>
            </div>

            <div class="modal-total">
                <span class="mt-label"><i class="fas fa-receipt me-1" style="color:#800000"></i> Total Due</span>
                <span class="mt-amount">₱50,000.00</span>
            </div>

            <div class="modal-note">
                <i class="fas fa-info-circle me-1"></i>
                By clicking <strong>Agree & Proceed to Payment</strong>, you confirm that you have read and agreed to the rehabilitation program terms and authorize this payment of <strong>₱50,000.00</strong>.
            </div>

            <button type="button" class="btn-agree" id="agreeBtn" onclick="proceedToPayment()">
                <i class="fas fa-check-circle"></i> Agree & Proceed to Payment
            </button>
            <button type="button" class="btn-cancel-modal" onclick="closeBillingModal()">
                Cancel
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const BOOKING_ID = <?= (int)$booking_id ?>;

function openBillingModal() {
    document.getElementById('billingModal').classList.add('show');
}
function closeBillingModal() {
    document.getElementById('billingModal').classList.remove('show');
}
// Close on overlay click
document.getElementById('billingModal').addEventListener('click', function(e) {
    if (e.target === this) closeBillingModal();
});

function proceedToPayment() {
    const btn = document.getElementById('agreeBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating payment session...';

    fetch('/GAMBYTES_Final/api/create_checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ booking_id: BOOKING_ID })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.checkout_url) {
            // Redirect to PayMongo hosted checkout
            window.location.href = data.checkout_url;
        } else {
            alert('Error: ' + (data.message || 'Failed to create payment session. Please try again.'));
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Agree & Proceed to Payment';
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Agree & Proceed to Payment';
    });
}
</script>
</body>
</html>
