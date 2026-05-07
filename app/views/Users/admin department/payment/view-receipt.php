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

$user_id    = (int)$_SESSION['user_id'];
$receipt_id = (int)($_GET['receipt_id'] ?? 0);

// Load current user
$uStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->bind_param('i', $user_id);
$uStmt->execute();
$me = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

if (!$me) { header("Location: " . url('app/views/auth/login.php')); exit(); }

// Load receipt with full details
$rStmt = $conn->prepare(
    "SELECT r.*,
            p.amount, p.payment_status, p.paid_at, p.paymongo_session_id, p.paymongo_payment_id,
            p.booking_id, p.user_id AS payer_id,
            CONCAT(pu.first_name,' ',pu.last_name) AS payer_name,
            pu.email AS payer_email,
            pu.role  AS payer_role,
            CONCAT(au.first_name,' ',au.last_name) AS admin_name,
            au.email AS admin_email
     FROM receipts r
     JOIN payments p  ON p.id  = r.payment_id
     JOIN users pu    ON pu.id = p.user_id
     LEFT JOIN users au ON au.id = r.verified_by
     WHERE r.id = ?
     LIMIT 1"
);
$rStmt->bind_param('i', $receipt_id);
$rStmt->execute();
$receipt = $rStmt->get_result()->fetch_assoc();
$rStmt->close();

if (!$receipt) {
    die('<div style="text-align:center;padding:3rem;font-family:sans-serif"><h3>Receipt not found.</h3><a href="/GAMBYTES_Final/app/views/auth/dashboard.php">Back to Dashboard</a></div>');
}

// Access control: admin/supervisor/case_manager, the payer, the linked gambler, or their family member can view
$payer_id = (int)$receipt['payer_id'];
$canView  = in_array($me['role'], ['admin', 'supervisor', 'case_manager']);

// Payer themselves
if (!$canView && $user_id === $payer_id) $canView = true;

if (!$canView) {
    // If payer is a family member, the linked gambler can also view
    $fToG = $conn->prepare(
        "SELECT gambler_id FROM parental_control_requests WHERE family_id = ? AND status = 'accepted' LIMIT 1"
    );
    $fToG->bind_param('i', $payer_id);
    $fToG->execute();
    $fToGRow = $fToG->get_result()->fetch_assoc();
    $fToG->close();
    if ($fToGRow && (int)$fToGRow['gambler_id'] === $user_id) $canView = true;
}

if (!$canView) {
    // If payer is a gambler, the linked family member can also view
    $fChk = $conn->prepare("SELECT id FROM parental_control_requests WHERE family_id = ? AND gambler_id = ? AND status = 'accepted' LIMIT 1");
    $fChk->bind_param('ii', $user_id, $payer_id);
    $fChk->execute();
    if ($fChk->get_result()->fetch_assoc()) $canView = true;
    $fChk->close();
}

if (!$canView) {
    header("Location: " . url('app/views/auth/dashboard.php'));
    exit();
}

// Load linked party info (gambler ↔ family)
$linkedPartyRow = null;
$payer_role = $receipt['payer_role'] ?? null;

if ($payer_role === 'family') {
    // Payer is family — show the linked gambler
    $lpStmt = $conn->prepare(
        "SELECT CONCAT(u.first_name,' ',u.last_name) AS linked_name, u.email AS linked_email, 'Gambler' AS linked_role
         FROM parental_control_requests pcr
         JOIN users u ON u.id = pcr.gambler_id
         WHERE pcr.family_id = ? AND pcr.status = 'accepted'
         LIMIT 1"
    );
    $lpStmt->bind_param('i', $payer_id);
    $lpStmt->execute();
    $linkedPartyRow = $lpStmt->get_result()->fetch_assoc();
    $lpStmt->close();
} else {
    // Payer is gambler — show the linked family member
    $lpStmt = $conn->prepare(
        "SELECT CONCAT(u.first_name,' ',u.last_name) AS linked_name, u.email AS linked_email, 'Family Member' AS linked_role
         FROM parental_control_requests pcr
         JOIN users u ON u.id = pcr.family_id
         WHERE pcr.gambler_id = ? AND pcr.status = 'accepted'
         LIMIT 1"
    );
    $lpStmt->bind_param('i', $payer_id);
    $lpStmt->execute();
    $linkedPartyRow = $lpStmt->get_result()->fetch_assoc();
    $lpStmt->close();
}

$paidAt     = $receipt['paid_at']     ? date('F j, Y g:i A', strtotime($receipt['paid_at']))     : '—';
$verifiedAt = $receipt['verified_at'] ? date('F j, Y g:i A', strtotime($receipt['verified_at'])) : '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Receipt <?= htmlspecialchars($receipt['receipt_number']) ?> – Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background:#f4f6f9; font-family:'Inter',sans-serif; }
        .receipt-wrapper { max-width:760px; margin:2rem auto; padding:1rem; }

        /* ── Invoice Card ── */
        .invoice-card { background:#fff; border-radius:4px; box-shadow:0 4px 24px rgba(0,0,0,.10); overflow:hidden; }

        /* ── Header band ── */
        .invoice-header {
            background:#800000;
            color:#fff;
            padding:1.75rem 2rem;
            display:flex;
            justify-content:space-between;
            align-items:flex-end;
        }
        .invoice-header .org-name  { font-size:1.5rem; font-weight:800; margin:0 0 .2rem; }
        .invoice-header .org-sub   { font-size:.78rem; opacity:.85; margin:0; line-height:1.5; }
        .invoice-header .inv-label { font-size:2.4rem; font-weight:900; letter-spacing:2px; opacity:.95; }

        /* ── Meta row (BILL TO + invoice details) ── */
        .invoice-meta {
            display:flex;
            justify-content:space-between;
            padding:1.5rem 2rem;
            gap:1rem;
            border-bottom:1px solid #e9ecef;
        }
        .invoice-meta .bill-to .bt-label { font-size:.7rem; font-weight:700; color:#800000; text-transform:uppercase; letter-spacing:.8px; margin-bottom:.4rem; }
        .invoice-meta .bill-to .bt-name  { font-weight:700; font-size:.95rem; color:#212529; }
        .invoice-meta .bill-to .bt-info  { font-size:.82rem; color:#6c757d; margin-top:.15rem; }

        .invoice-meta .inv-details { text-align:right; }
        .invoice-meta .inv-details table { border-collapse:collapse; }
        .invoice-meta .inv-details td { padding:.18rem .5rem; font-size:.82rem; }
        .invoice-meta .inv-details td:first-child { font-weight:700; color:#800000; text-align:right; }
        .invoice-meta .inv-details td:last-child  { color:#212529; font-weight:600; }

        /* ── Service table ── */
        .inv-table { width:100%; border-collapse:collapse; }
        .inv-table thead tr { background:#800000; color:#fff; }
        .inv-table thead th { padding:.65rem 1rem; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; text-align:left; }
        .inv-table thead th:last-child { text-align:right; }
        .inv-table tbody tr { border-bottom:1px solid #f0f0f0; }
        .inv-table tbody tr:nth-child(even) { background:#fdf5f5; }
        .inv-table tbody td { padding:.65rem 1rem; font-size:.88rem; color:#343a40; }
        .inv-table tbody td:last-child { text-align:right; font-weight:600; }
        .inv-table-wrap { padding:0 2rem 1rem; }

        /* ── Totals ── */
        .inv-totals { display:flex; justify-content:flex-end; padding:.5rem 2rem 1.5rem; }
        .inv-totals table { border-collapse:collapse; min-width:260px; }
        .inv-totals td { padding:.3rem .75rem; font-size:.88rem; }
        .inv-totals td:first-child { color:#6c757d; font-weight:500; text-align:right; }
        .inv-totals td:last-child  { font-weight:700; color:#212529; text-align:right; min-width:100px; }
        .inv-totals .total-row td  { font-weight:800; font-size:1rem; }
        .inv-totals .total-row td:first-child { color:#800000; }
        .inv-totals .total-row td:last-child  {
            background:#800000; color:#fff;
            border-radius:4px; padding:.4rem .75rem;
        }

        /* ── Verified stamp ── */
        .inv-verified {
            margin:0 2rem 1.5rem;
            background:#fdf5f5;
            border:1.5px solid #c9a0a0;
            border-radius:8px;
            padding:.75rem 1rem;
            display:flex; align-items:center; gap:.75rem;
            font-size:.83rem; color:#5c0000;
        }
        .inv-verified i { font-size:1.1rem; color:#800000; flex-shrink:0; }

        /* ── Footer ── */
        .inv-footer {
            background:#f8f9fa;
            border-top:1px solid #e9ecef;
            padding:1rem 2rem;
            font-size:.75rem;
            color:#6c757d;
            text-align:center;
        }

        /* ── Buttons ── */
        .btn-print { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border:none; border-radius:10px; padding:.7rem 1.6rem; font-weight:700; font-size:.88rem; cursor:pointer; display:inline-flex; align-items:center; gap:.5rem; }
        .btn-back  { background:#fff; color:#6c757d; border:2px solid #dee2e6; border-radius:10px; padding:.65rem 1.4rem; font-weight:600; font-size:.88rem; text-decoration:none; display:inline-flex; align-items:center; gap:.5rem; }

        @media print {
            body { background:#fff; }
            .no-print { display:none !important; }
            .invoice-card { box-shadow:none; }
            .receipt-wrapper { margin:0; padding:0; max-width:100%; }
        }
    </style>
</head>
<body>
<div class="receipt-wrapper">

    <!-- Action buttons -->
    <div style="display:flex;gap:.75rem;margin-bottom:1rem;flex-wrap:wrap" class="no-print">
        <?php
            if ($me['role'] === 'case_manager') {
                $backUrl = '/GAMBYTES_Final/app/views/Users/Case%20manager/my-patients.php';
            } elseif ($me['role'] === 'gambler' || $me['role'] === 'family') {
                $backUrl = '/GAMBYTES_Final/app/views/auth/dashboard.php';
            } else {
                $backUrl = '/GAMBYTES_Final/app/views/Users/admin%20department/payment/verify-payments.php';
            }
        ?>
        <a href="<?= $backUrl ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
        <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Print Receipt</button>
    </div>

    <div class="invoice-card">

        <!-- ── Invoice Header ── -->
        <div class="invoice-header">
            <div>
                <p class="org-name"><i class="fas fa-shield-alt me-2"></i>Gambytes</p>
                <p class="org-sub">
                    Philippine Amusement and Gaming Corporation<br>
                    Rehabilitation Services Department<br>
                    gambytes@pagcor.ph | (02) 8831-9000
                </p>
            </div>
            <div class="inv-label">RECEIPT</div>
        </div>

        <!-- ── Bill To + Invoice Details ── -->
        <div class="invoice-meta">
            <div class="bill-to">
                <div class="bt-label">Bill To:</div>
                <div class="bt-name"><?= htmlspecialchars($receipt['payer_name']) ?></div>
                <div class="bt-info"><?= htmlspecialchars($receipt['payer_email']) ?></div>
                <div class="bt-info"><?= ucfirst(htmlspecialchars($payer_role ?? '')) ?></div>
                <?php if ($linkedPartyRow): ?>
                <div class="bt-info" style="margin-top:.35rem;color:#800000;font-weight:600">
                    <?= htmlspecialchars($linkedPartyRow['linked_role']) ?>: <?= htmlspecialchars($linkedPartyRow['linked_name']) ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="inv-details">
                <table>
                    <tr>
                        <td>Receipt #:</td>
                        <td><?= htmlspecialchars($receipt['receipt_number']) ?></td>
                    </tr>
                    <tr>
                        <td>Date Paid:</td>
                        <td><?= $receipt['paid_at'] ? date('Y-m-d', strtotime($receipt['paid_at'])) : '—' ?></td>
                    </tr>
                    <tr>
                        <td>Verified On:</td>
                        <td><?= $receipt['verified_at'] ? date('Y-m-d', strtotime($receipt['verified_at'])) : '—' ?></td>
                    </tr>
                    <tr>
                        <td>Issued By:</td>
                        <td><?= htmlspecialchars($receipt['admin_name'] ?? 'Admin Dept.') ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- ── Service Table ── -->
        <div class="inv-table-wrap">
            <table class="inv-table">
                <thead>
                    <tr>
                        <th>Service Description</th>
                        <th>Duration</th>
                        <th>Sessions</th>
                        <th>Location</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Rehabilitation Treatment Program</td>
                        <td>6 Months</td>
                        <td>Weekly (Individual &amp; Group)</td>
                        <td>Rehabilitation Center</td>
                        <td>₱50,000.00</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ── Totals ── -->
        <div class="inv-totals">
            <table>
                <tr>
                    <td>Subtotal</td>
                    <td>₱50,000.00</td>
                </tr>
                <tr>
                    <td>Tax (0%)</td>
                    <td>₱0.00</td>
                </tr>
                <tr class="total-row">
                    <td>AMOUNT PAID</td>
                    <td>₱50,000.00</td>
                </tr>
            </table>
        </div>

        <!-- ── Verified stamp ── -->
        <div class="inv-verified">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>Official Receipt – Verified &amp; Paid</strong><br>
                Payment method: GCash / Card (via PayMongo)
                <?php if ($receipt['paymongo_session_id']): ?>
                &nbsp;·&nbsp; Ref: <span style="font-family:monospace;font-size:.78rem"><?= htmlspecialchars($receipt['paymongo_session_id']) ?></span>
                <?php endif; ?>
                <?php if ($receipt['notes']): ?>
                <br><em>Notes: <?= htmlspecialchars($receipt['notes']) ?></em>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Footer ── -->
        <div class="inv-footer">
            This is an official receipt issued by the Gambytes Rehabilitation Services Department.
            Please keep this for your records. For inquiries, contact the admin department.
        </div>

    </div><!-- /.invoice-card -->
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
