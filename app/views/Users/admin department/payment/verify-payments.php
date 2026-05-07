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

$user_id = $_SESSION['user_id'];
$uStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->bind_param('i', $user_id);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

if (!$user || $user['role'] !== 'admin') {
    header("Location: " . url('app/views/auth/dashboard.php'));
    exit();
}
$full_name = $user['first_name'] . ' ' . $user['last_name'];

// Ensure receipts table has needed columns
$conn->query("CREATE TABLE IF NOT EXISTS `receipts` (
    `id`             INT(11)      NOT NULL AUTO_INCREMENT,
    `payment_id`     INT(11)      NOT NULL,
    `receipt_number` VARCHAR(100) NULL,
    `verified_by`    INT(11)      NULL,
    `verified_at`    DATETIME     NULL,
    `notes`          TEXT         NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("ALTER TABLE `receipts` ADD COLUMN IF NOT EXISTS `verified_by` INT(11) NULL");
$conn->query("ALTER TABLE `receipts` ADD COLUMN IF NOT EXISTS `verified_at` DATETIME NULL");
$conn->query("ALTER TABLE `receipts` ADD COLUMN IF NOT EXISTS `notes` TEXT NULL");
$conn->query("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `verified_by` INT(11) NULL");
$conn->query("ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `verified_at` DATETIME NULL");

// Load pending payments (paid but not yet verified)
$pendingStmt = $conn->query(
    "SELECT p.*, 
            CONCAT(u.first_name,' ',u.last_name) AS payer_name,
            u.email AS payer_email,
            u.role  AS payer_role,
            br.name AS booking_name,
            -- if payer is gambler: show linked family member
            CONCAT(fu.first_name,' ',fu.last_name) AS family_name,
            fu.email AS family_email,
            -- if payer is family: show linked gambler
            CONCAT(gu.first_name,' ',gu.last_name) AS gambler_name,
            gu.email AS gambler_email
     FROM payments p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN booking_record br ON br.id = p.booking_id
     -- family member linked to payer (when payer is gambler)
     LEFT JOIN parental_control_requests pcr_g ON pcr_g.gambler_id = p.user_id AND pcr_g.status = 'accepted' AND u.role = 'gambler'
     LEFT JOIN users fu ON fu.id = pcr_g.family_id
     -- gambler linked to payer (when payer is family)
     LEFT JOIN parental_control_requests pcr_f ON pcr_f.family_id = p.user_id AND pcr_f.status = 'accepted' AND u.role = 'family'
     LEFT JOIN users gu ON gu.id = pcr_f.gambler_id
     WHERE p.payment_status = 'paid'
     ORDER BY p.paid_at DESC"
);
$pendingPayments = $pendingStmt ? $pendingStmt->fetch_all(MYSQLI_ASSOC) : [];

// Load verified payments
$verifiedStmt = $conn->query(
    "SELECT p.*, 
            CONCAT(u.first_name,' ',u.last_name) AS payer_name,
            u.email AS payer_email,
            r.receipt_number, r.verified_at, r.id AS receipt_id,
            CONCAT(vu.first_name,' ',vu.last_name) AS verified_by_name
     FROM payments p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN receipts r ON r.payment_id = p.id
     LEFT JOIN users vu ON vu.id = r.verified_by
     WHERE p.payment_status = 'verified'
     ORDER BY p.verified_at DESC"
);
$verifiedPayments = $verifiedStmt ? $verifiedStmt->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification – Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
    <style>
        .main-content { margin-left:260px; flex:1; padding:2rem; }
        .fc-card { background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); overflow:hidden; margin-bottom:1.5rem; }
        .fc-card-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:1rem 1.5rem; font-weight:700; font-size:1rem; display:flex; align-items:center; gap:.6rem; }
        .fc-card-body { padding:1.5rem; }
        .pay-row { border:1.5px solid #e9ecef; border-radius:12px; padding:1.25rem 1.5rem; margin-bottom:1rem; background:#fff; }
        .info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:.65rem; margin-bottom:1rem; }
        .info-box { padding:.6rem .85rem; background:#f8f9fa; border-radius:8px; border-left:3px solid #800000; }
        .info-box .lbl { font-size:.7rem; color:#6c757d; font-weight:600; text-transform:uppercase; }
        .info-box .val { font-weight:700; color:#212529; font-size:.88rem; margin-top:1px; }
        .btn-verify { background:linear-gradient(135deg,#198754,#146c43); color:#fff; border:none; border-radius:10px; padding:.6rem 1.25rem; font-weight:700; font-size:.88rem; cursor:pointer; display:inline-flex; align-items:center; gap:.5rem; transition:all .2s; }
        .btn-verify:hover { transform:translateY(-1px); opacity:.9; }
        .btn-view { background:#fff; color:#800000; border:2px solid #800000; border-radius:10px; padding:.55rem 1.1rem; font-weight:600; font-size:.85rem; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; transition:all .2s; }
        .btn-view:hover { background:#800000; color:#fff; }
        .badge-pending  { background:#fff3cd; color:#664d03; padding:.25rem .75rem; border-radius:20px; font-size:.75rem; font-weight:700; }
        .badge-verified { background:#d1e7dd; color:#0f5132; padding:.25rem .75rem; border-radius:20px; font-size:.75rem; font-weight:700; }
        .top-navbar { background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.08); padding:1rem 1.5rem; margin-bottom:2rem; display:flex; justify-content:space-between; align-items:center; }
        .empty-state { text-align:center; padding:3rem; color:#6c757d; }
        .tab-btns { display:flex; gap:.5rem; margin-bottom:1.5rem; }
        .tab-btn { padding:.55rem 1.25rem; border-radius:10px; border:2px solid #dee2e6; background:#fff; font-weight:600; font-size:.88rem; cursor:pointer; transition:all .2s; color:#343a40; }
        .tab-btn.active { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border-color:#800000; }
        .tab-pane { display:none; }
        .tab-pane.active { display:block; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo"><img src="/GAMBYTES_Final/public/images/Logo.png" alt="Logo"><span>Gambytes</span></div>
            <div class="sidebar-user">
                <div class="user-name"><i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($full_name) ?></div>
                <div class="user-role">Admin Department</div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="#" class="active"><i class="fas fa-money-check-alt"></i> Payment Verification</a></li>
            <div class="menu-divider"></div>
            <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <span style="font-weight:700;font-size:1rem;color:#800000">Admin Department</span>
        </div>

        <div style="margin-bottom:1.75rem">
            <h1 style="color:#800000;font-size:1.8rem;font-weight:800;margin:0"><i class="fas fa-money-check-alt me-2"></i>Payment Verification</h1>
            <p style="color:#6c757d;margin:.25rem 0 0">Verify payments and issue official receipts to gamblers and their family members.</p>
        </div>

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.75rem">
            <div style="background:#fff3cd;border-radius:14px;padding:1.25rem;border-left:5px solid #ffc107">
                <div style="font-size:2rem;font-weight:800;color:#664d03"><?= count($pendingPayments) ?></div>
                <div style="font-size:.85rem;color:#664d03;font-weight:600">Pending Verification</div>
            </div>
            <div style="background:#d1e7dd;border-radius:14px;padding:1.25rem;border-left:5px solid #198754">
                <div style="font-size:2rem;font-weight:800;color:#0f5132"><?= count($verifiedPayments) ?></div>
                <div style="font-size:.85rem;color:#0f5132;font-weight:600">Verified & Receipted</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-btns">
            <button class="tab-btn active" onclick="switchTab('pending',this)">
                <i class="fas fa-hourglass-half me-1"></i> Pending
                <?php if (count($pendingPayments)): ?>
                <span style="background:#dc3545;color:#fff;border-radius:10px;padding:1px 7px;font-size:.72rem;margin-left:.3rem"><?= count($pendingPayments) ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-btn" onclick="switchTab('verified',this)">
                <i class="fas fa-check-circle me-1"></i> Verified
            </button>
        </div>

        <!-- Pending Tab -->
        <div id="tab-pending" class="tab-pane active">
            <?php if (empty($pendingPayments)): ?>
            <div class="fc-card"><div class="fc-card-body empty-state">
                <i class="fas fa-check-circle fa-3x mb-3" style="color:#198754;display:block"></i>
                <h5>No pending payments</h5>
                <p style="font-size:.9rem">All payments have been verified.</p>
            </div></div>
            <?php else: ?>
            <div class="fc-card">
                <div class="fc-card-header"><i class="fas fa-hourglass-half"></i> Payments Awaiting Verification</div>
                <div class="fc-card-body">
                    <?php foreach ($pendingPayments as $pay): 
                        // Determine linked party based on payer role
                        $isPayerFamily  = ($pay['payer_role'] === 'family');
                        $linkedLabel    = $isPayerFamily ? 'Gambler' : 'Family Member';
                        $linkedName     = $isPayerFamily ? ($pay['gambler_name'] ?? '') : ($pay['family_name'] ?? '');
                        $linkedEmail    = $isPayerFamily ? ($pay['gambler_email'] ?? '') : ($pay['family_email'] ?? '');
                    ?>
                    <div class="pay-row">
                        <div class="info-grid">
                            <div class="info-box">
                                <div class="lbl">Payer</div>
                                <div class="val"><?= htmlspecialchars($pay['payer_name']) ?></div>
                            </div>
                            <div class="info-box">
                                <div class="lbl">Role</div>
                                <div class="val"><?= ucfirst(htmlspecialchars($pay['payer_role'])) ?></div>
                            </div>
                            <div class="info-box">
                                <div class="lbl">Email</div>
                                <div class="val" style="font-size:.8rem"><?= htmlspecialchars($pay['payer_email']) ?></div>
                            </div>
                            <div class="info-box">
                                <div class="lbl">Amount</div>
                                <div class="val">₱<?= number_format($pay['amount'], 2) ?></div>
                            </div>
                            <div class="info-box">
                                <div class="lbl">Paid At</div>
                                <div class="val"><?= $pay['paid_at'] ? date('M j, Y g:i A', strtotime($pay['paid_at'])) : '—' ?></div>
                            </div>
                            <div class="info-box">
                                <div class="lbl">PayMongo Ref</div>
                                <div class="val" style="font-size:.75rem;font-family:monospace"><?= htmlspecialchars(substr($pay['paymongo_session_id'] ?? '—', 0, 20)) ?>...</div>
                            </div>
                            <div class="info-box">
                                <div class="lbl"><?= $linkedLabel ?></div>
                                <div class="val"><?= $linkedName ? htmlspecialchars($linkedName) : '<span style="color:#adb5bd">None linked</span>' ?></div>
                            </div>
                        </div>
                        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
                            <span class="badge-pending"><i class="fas fa-clock me-1"></i>Pending</span>
                            <button class="btn-verify" onclick="openVerifyModal(<?= (int)$pay['id'] ?>, '<?= htmlspecialchars(addslashes($pay['payer_name'])) ?>', '<?= htmlspecialchars(addslashes($pay['payer_email'])) ?>', '<?= htmlspecialchars(addslashes($pay['payer_role'])) ?>', '<?= htmlspecialchars(addslashes($linkedName)) ?>', '<?= htmlspecialchars(addslashes($linkedEmail)) ?>', '<?= $linkedLabel ?>')">
                                <i class="fas fa-check-circle"></i> Verify & Issue Receipt
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Verified Tab -->
        <div id="tab-verified" class="tab-pane">
            <?php if (empty($verifiedPayments)): ?>
            <div class="fc-card"><div class="fc-card-body empty-state">
                <i class="fas fa-receipt fa-3x mb-3" style="opacity:.3;display:block"></i>
                <h5>No verified payments yet</h5>
            </div></div>
            <?php else: ?>
            <div class="fc-card">
                <div class="fc-card-header"><i class="fas fa-check-circle"></i> Verified Payments</div>
                <div class="fc-card-body">
                    <?php foreach ($verifiedPayments as $pay): ?>
                    <div class="pay-row">
                        <div class="info-grid">
                            <div class="info-box">
                                <div class="lbl">Payer</div>
                                <div class="val"><?= htmlspecialchars($pay['payer_name']) ?></div>
                            </div>
                            <div class="info-box">
                                <div class="lbl">Amount</div>
                                <div class="val">₱<?= number_format($pay['amount'], 2) ?></div>
                            </div>
                            <div class="info-box">
                                <div class="lbl">Receipt No.</div>
                                <div class="val"><?= htmlspecialchars($pay['receipt_number'] ?? '—') ?></div>
                            </div>
                            <div class="info-box">
                                <div class="lbl">Verified At</div>
                                <div class="val"><?= $pay['verified_at'] ? date('M j, Y g:i A', strtotime($pay['verified_at'])) : '—' ?></div>
                            </div>
                            <div class="info-box">
                                <div class="lbl">Verified By</div>
                                <div class="val"><?= htmlspecialchars($pay['verified_by_name'] ?? '—') ?></div>
                            </div>
                        </div>
                        <div style="display:flex;gap:.75rem;align-items:center">
                            <span class="badge-verified"><i class="fas fa-check-circle me-1"></i>Verified</span>
                            <a href="/GAMBYTES_Final/app/views/Users/admin%20department/payment/view-receipt.php?receipt_id=<?= (int)$pay['receipt_id'] ?>" class="btn-view" target="_blank">
                                <i class="fas fa-eye"></i> View Receipt
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Verify Modal ── -->
<div class="modal-overlay" id="verifyModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:20px;box-shadow:0 16px 60px rgba(0,0,0,.2);max-width:480px;width:90%;overflow:hidden;animation:slideUp .25s ease">
        <div style="background:linear-gradient(135deg,#800000,#5c0000);color:#fff;padding:1.5rem">
            <h4 style="margin:0;font-weight:800;font-size:1.1rem"><i class="fas fa-check-circle me-2"></i>Verify Payment & Issue Receipt</h4>
            <p style="margin:.25rem 0 0;opacity:.85;font-size:.85rem">Confirm payment details before issuing the official receipt.</p>
        </div>
        <div style="padding:1.75rem">
            <div style="background:#f8f9fa;border-radius:10px;padding:1rem;margin-bottom:1.25rem;font-size:.88rem">
                <div style="margin-bottom:.5rem"><strong>Payer:</strong> <span id="modal-payer"></span></div>
                <div style="margin-bottom:.5rem"><strong>Role:</strong> <span id="modal-role"></span></div>
                <div style="margin-bottom:.5rem"><strong>Email:</strong> <span id="modal-email"></span></div>
                <div><strong id="modal-linked-label">Linked Party:</strong> <span id="modal-family"></span></div>
            </div>
            <div style="margin-bottom:1rem">
                <label style="font-weight:600;font-size:.85rem;display:block;margin-bottom:.35rem">Notes (optional)</label>
                <textarea id="modal-notes" rows="2" style="width:100%;border:1.5px solid #dee2e6;border-radius:8px;padding:.5rem .85rem;font-size:.88rem;resize:none" placeholder="Add any verification notes..."></textarea>
            </div>
            <div style="background:#d1e7dd;border-radius:8px;padding:.75rem 1rem;font-size:.82rem;color:#0f5132;margin-bottom:1.25rem">
                <i class="fas fa-info-circle me-1"></i>
                An official receipt will be generated and the gambler <?php if (true): ?>and their family member<?php endif; ?> will be notified.
            </div>
            <input type="hidden" id="modal-payment-id">
            <button class="btn-verify" style="width:100%;justify-content:center;padding:.85rem" id="confirmVerifyBtn" onclick="confirmVerify()">
                <i class="fas fa-check-circle"></i> Confirm & Issue Receipt
            </button>
            <button onclick="closeVerifyModal()" style="width:100%;margin-top:.6rem;background:#fff;color:#6c757d;border:2px solid #dee2e6;border-radius:10px;padding:.75rem;font-weight:600;cursor:pointer">
                Cancel
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

function openVerifyModal(paymentId, payer, email, role, linkedName, linkedEmail, linkedLabel) {
    document.getElementById('modal-payment-id').value  = paymentId;
    document.getElementById('modal-payer').textContent = payer;
    document.getElementById('modal-role').textContent  = role.charAt(0).toUpperCase() + role.slice(1);
    document.getElementById('modal-email').textContent = email;
    document.getElementById('modal-linked-label').textContent = (linkedLabel || 'Linked Party') + ':';
    document.getElementById('modal-family').textContent = linkedName || 'None linked';
    document.getElementById('modal-notes').value = '';
    document.getElementById('verifyModal').style.display = 'flex';
}
function closeVerifyModal() {
    document.getElementById('verifyModal').style.display = 'none';
}
document.getElementById('verifyModal').addEventListener('click', function(e) {
    if (e.target === this) closeVerifyModal();
});

function confirmVerify() {
    const btn       = document.getElementById('confirmVerifyBtn');
    const paymentId = document.getElementById('modal-payment-id').value;
    const notes     = document.getElementById('modal-notes').value;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    fetch('/GAMBYTES_Final/api/verify_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ payment_id: parseInt(paymentId), notes: notes })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeVerifyModal();
            alert('Receipt issued successfully! The gambler and family member have been notified.');
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to verify payment.'));
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm & Issue Receipt';
        }
    })
    .catch(() => {
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm & Issue Receipt';
    });
}
</script>
</body>
</html>
