<?php
require_once __DIR__ . '/../../../../../includes/session_config.php';
require_once __DIR__ . '/../../../../../includes/url_helper.php';
require_once __DIR__ . '/../../../../../includes/env_loader.php';

require_once __DIR__ . '/../../../../core/Database.php';
$db   = new Database();
$conn = $db->connect();

$status        = $_GET['status']         ?? '';
$booking_id    = (int)($_GET['booking_id']    ?? 0);
$payment_db_id = (int)($_GET['payment_db_id'] ?? 0);
$uid_param     = (int)($_GET['uid']           ?? 0);
$tok_param     = $_GET['tok']                 ?? '';

// ── Restore session if lost after PayMongo redirect ───────────────────────────
if (!isset($_SESSION['user_id']) && $uid_param && $tok_param) {
    // Validate token (same formula as create_checkout.php, valid for current hour)
    $expected = hash('sha256', $uid_param . $booking_id . date('YmdH') . 'gambytes_pay');
    if (hash_equals($expected, $tok_param)) {
        $_SESSION['user_id'] = $uid_param;
    }
}

// If still no session, redirect to login
if (!isset($_SESSION['user_id'])) {
    header("Location: " . url('app/views/auth/login.php'));
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Load user
$uStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->bind_param('i', $user_id);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

if (!$user) {
    header("Location: " . url('app/views/auth/login.php'));
    exit();
}

$full_name = $user['first_name'] . ' ' . $user['last_name'];

// ── If success, verify with PayMongo and mark payment as paid ─────────────────
$payment     = null;
$isSuccess   = false;
$isCancelled = ($status === 'cancel');

if ($status === 'success' && $payment_db_id) {
    // Load payment record
    $pStmt = $conn->prepare("SELECT * FROM payments WHERE id = ? AND user_id = ? LIMIT 1");
    $pStmt->bind_param('ii', $payment_db_id, $user_id);
    $pStmt->execute();
    $payment = $pStmt->get_result()->fetch_assoc();
    $pStmt->close();

    if ($payment && $payment['paymongo_session_id']) {
        // Verify with PayMongo
        $pm_key = defined('PAYMONGO_SECRET_KEY') ? PAYMONGO_SECRET_KEY : getenv('PAYMONGO_SECRET_KEY');
        $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions/' . $payment['paymongo_session_id']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode($pm_key . ':'),
            ],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp       = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpStatus === 200) {
            $sessionData   = json_decode($resp, true);
            $sessionStatus = $sessionData['data']['attributes']['status'] ?? '';
            $payments      = $sessionData['data']['attributes']['payments'] ?? [];

            if ($sessionStatus === 'completed' || !empty($payments)) {
                $pmPaymentId = $payments[0]['id'] ?? null;
                $isSuccess   = true;

                // Mark as paid (pending admin verification) if not already
                if ($payment['payment_status'] !== 'paid' && $payment['payment_status'] !== 'verified') {
                    $updStmt = $conn->prepare(
                        "UPDATE payments SET payment_status='paid', paymongo_payment_id=?, paid_at=NOW(), updated_at=NOW() WHERE id=?"
                    );
                    $updStmt->bind_param('si', $pmPaymentId, $payment_db_id);
                    $updStmt->execute();
                    $updStmt->close();

                    // Notify admin department
                    $adminRes = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 5");
                    if ($adminRes) {
                        $notifTitle = 'New Payment – Verification Required';
                        $notifMsg   = $full_name . ' has paid ₱50,000 for the rehabilitation program. Please verify and issue a receipt.';
                        $notifLink  = '/GAMBYTES_Final/app/views/Users/admin%20department/payment/verify-payments.php';
                        $notifType  = 'payment';
                        while ($adm = $adminRes->fetch_assoc()) {
                            $aid = (int)$adm['id'];
                            $n = $conn->prepare("INSERT INTO notifications (user_id,type,title,message,link,is_read,created_at) VALUES (?,?,?,?,?,0,NOW())");
                            $n->bind_param('issss', $aid, $notifType, $notifTitle, $notifMsg, $notifLink);
                            $n->execute();
                            $n->close();
                        }
                    }
                }

                // Reload payment
                $pStmt2 = $conn->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
                $pStmt2->bind_param('i', $payment_db_id);
                $pStmt2->execute();
                $payment = $pStmt2->get_result()->fetch_assoc();
                $pStmt2->close();
            }
        }
    }

    // If payment was already marked paid before (e.g. page refresh)
    if ($payment && in_array($payment['payment_status'], ['paid', 'verified'])) {
        $isSuccess = true;
    }
}

$isVerified   = $payment && $payment['payment_status'] === 'verified';
$dashboardUrl = '/GAMBYTES_Final/app/views/auth/dashboard.php';
$paidAt = $payment && $payment['paid_at'] ? date('F j, Y g:i A', strtotime($payment['paid_at'])) : date('F j, Y g:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isSuccess ? 'Payment Successful' : 'Payment' ?> – Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
    <style>
        body { background:#f4f6f9; font-family:'Inter',sans-serif; }
        .receipt-wrapper { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem; }

        /* ── Invoice Card ── */
        .invoice-card { background:#fff; border-radius:4px; box-shadow:0 4px 24px rgba(0,0,0,.10); max-width:680px; width:100%; overflow:hidden; }

        /* ── Header band ── */
        .invoice-header {
            background:#800000;
            color:#fff;
            padding:1.75rem 2rem;
            display:flex;
            justify-content:space-between;
            align-items:flex-end;
        }
        .invoice-header .org-name  { font-size:1.4rem; font-weight:800; margin:0 0 .2rem; }
        .invoice-header .org-sub   { font-size:.76rem; opacity:.85; margin:0; line-height:1.5; }
        .invoice-header .inv-label { font-size:2rem; font-weight:900; letter-spacing:2px; opacity:.95; }

        /* ── Meta row ── */
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
        .inv-table thead th { padding:.6rem 1rem; font-size:.73rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; text-align:left; }
        .inv-table thead th:last-child { text-align:right; }
        .inv-table tbody tr { border-bottom:1px solid #f0f0f0; }
        .inv-table tbody tr:nth-child(even) { background:#fdf5f5; }
        .inv-table tbody td { padding:.65rem 1rem; font-size:.88rem; color:#343a40; }
        .inv-table tbody td:last-child { text-align:right; font-weight:600; }
        .inv-table-wrap { padding:0 2rem 1rem; }

        /* ── Totals ── */
        .inv-totals { display:flex; justify-content:flex-end; padding:.5rem 2rem 1.5rem; }
        .inv-totals table { border-collapse:collapse; min-width:240px; }
        .inv-totals td { padding:.28rem .75rem; font-size:.88rem; }
        .inv-totals td:first-child { color:#6c757d; font-weight:500; text-align:right; }
        .inv-totals td:last-child  { font-weight:700; color:#212529; text-align:right; min-width:100px; }
        .inv-totals .total-row td  { font-weight:800; font-size:1rem; }
        .inv-totals .total-row td:first-child { color:#800000; }
        .inv-totals .total-row td:last-child  { background:#800000; color:#fff; border-radius:4px; padding:.4rem .75rem; }

        /* ── Status notice ── */
        .inv-notice {
            margin:0 2rem 1.5rem;
            background:#fdf5f5;
            border:1.5px solid #c9a0a0;
            border-radius:8px;
            padding:.75rem 1rem;
            display:flex; align-items:flex-start; gap:.75rem;
            font-size:.83rem; color:#5c0000;
        }
        .inv-notice i { font-size:1.1rem; color:#800000; flex-shrink:0; margin-top:.1rem; }

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
        .btn-dashboard { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border:none; border-radius:10px; padding:.8rem 1.75rem; font-weight:700; font-size:.9rem; text-decoration:none; display:inline-flex; align-items:center; gap:.6rem; box-shadow:0 4px 14px rgba(128,0,0,.3); transition:all .2s; }
        .btn-dashboard:hover { transform:translateY(-2px); color:#fff; }
        .btn-retry { background:#fff; color:#800000; border:2px solid #800000; border-radius:10px; padding:.75rem 1.5rem; font-weight:700; font-size:.9rem; text-decoration:none; display:inline-flex; align-items:center; gap:.6rem; transition:all .2s; }
        .btn-retry:hover { background:#800000; color:#fff; }

        /* ── Cancel / Pending card ── */
        .simple-card { background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.10); max-width:480px; width:100%; overflow:hidden; }
        .simple-header { background:#800000; color:#fff; padding:2.5rem; text-align:center; }
        .simple-header .s-icon { font-size:3rem; margin-bottom:.6rem; }
        .simple-header h2 { font-weight:800; margin:0; font-size:1.4rem; }
        .simple-header p  { opacity:.85; margin:.3rem 0 0; font-size:.88rem; }
        .simple-body { padding:2rem; text-align:center; }

        @media print {
            .no-print { display:none !important; }
            body { background:#fff; }
            .invoice-card { box-shadow:none; }
            .receipt-wrapper { padding:0; }
        }
    </style>
</head>
<body>
<div class="receipt-wrapper">

        <?php if ($isSuccess): ?>
        <!-- ── SUCCESS – Invoice Style ── -->
        <div class="invoice-card">

            <!-- Header -->
            <div class="invoice-header">
                <div>
                    <p class="org-name"><i class="fas fa-shield-alt me-2"></i>Gambytes</p>
                    <p class="org-sub">
                        Philippine Amusement and Gaming Corporation<br>
                        Rehabilitation Services Department<br>
                        gambytes@pagcor.ph | (02) 8831-9000
                    </p>
                </div>
                <div class="inv-label">INVOICE</div>
            </div>

            <!-- Bill To + Invoice Details -->
            <div class="invoice-meta">
                <div class="bill-to">
                    <div class="bt-label">Bill To:</div>
                    <div class="bt-name"><?= htmlspecialchars($full_name) ?></div>
                    <div class="bt-info"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                </div>
                <div class="inv-details">
                    <table>
                        <?php if ($payment && $payment['paymongo_session_id']): ?>
                        <tr>
                            <td>Invoice #:</td>
                            <td style="font-family:monospace;font-size:.75rem"><?= htmlspecialchars(substr($payment['paymongo_session_id'], 0, 18)) ?>…</td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td>Date:</td>
                            <td><?= date('Y-m-d') ?></td>
                        </tr>
                        <tr>
                            <td>Status:</td>
                            <td style="color:#800000;font-weight:700">Pending Verification</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Service Table -->
            <div class="inv-table-wrap">
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th>Service Description</th>
                            <th>Duration</th>
                            <th>Sessions</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Rehabilitation Treatment Program</td>
                            <td>6 Months</td>
                            <td>Weekly (Individual &amp; Group)</td>
                            <td>₱50,000.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="inv-totals">
                <table>
                    <tr><td>Subtotal</td><td>₱50,000.00</td></tr>
                    <tr><td>Tax (0%)</td><td>₱0.00</td></tr>
                    <tr class="total-row"><td>AMOUNT PAID</td><td>₱50,000.00</td></tr>
                </table>
            </div>

            <!-- Notice -->
            <div class="inv-notice">
                <i class="fas fa-hourglass-half"></i>
                <div>
                    <strong>Pending Verification</strong> — The admin department will verify your payment and issue an official receipt shortly. You will be notified once it's done.
                </div>
            </div>

            <!-- Footer -->
            <div class="inv-footer" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
                <span>This is a payment confirmation. An official receipt will be issued after admin verification.</span>
                <a href="<?= $dashboardUrl ?>" class="btn-dashboard no-print">
                    <i class="fas fa-home"></i> Go to Dashboard
                </a>
            </div>

        </div><!-- /.invoice-card -->

        <?php elseif ($isCancelled): ?>
        <!-- ── CANCELLED ── -->
        <div class="simple-card">
            <div class="simple-header">
                <div class="s-icon"><i class="fas fa-times-circle"></i></div>
                <h2>Payment Cancelled</h2>
                <p>Your payment was not completed.</p>
            </div>
            <div class="simple-body">
                <p style="color:#6c757d;font-size:.92rem;margin-bottom:1.5rem">
                    You cancelled the payment process. No charges were made. You can try again whenever you're ready.
                </p>
                <div style="display:flex;gap:.75rem;flex-wrap:wrap;justify-content:center">
                    <a href="/GAMBYTES_Final/app/views/Users/admin department/payment/pay.php?booking_id=<?= $booking_id ?>" class="btn-retry">
                        <i class="fas fa-redo"></i> Try Again
                    </a>
                    <a href="/GAMBYTES_Final/app/views/auth/dashboard.php" class="btn-dashboard">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- ── PENDING / UNKNOWN ── -->
        <div class="simple-card">
            <div class="simple-header">
                <div class="s-icon"><i class="fas fa-hourglass-half"></i></div>
                <h2>Payment Pending</h2>
                <p>We're still verifying your payment.</p>
            </div>
            <div class="simple-body">
                <p style="color:#6c757d;font-size:.92rem;margin-bottom:1.5rem">
                    Your payment is being processed. If you completed the payment, please wait a moment and refresh this page.
                </p>
                <div style="display:flex;gap:.75rem;flex-wrap:wrap;justify-content:center">
                    <button onclick="window.location.reload()" style="background:linear-gradient(135deg,#800000,#5c0000);color:#fff;border:none;border-radius:10px;padding:.8rem 1.5rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <a href="/GAMBYTES_Final/app/views/auth/dashboard.php" class="btn-dashboard">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

</div><!-- /.receipt-wrapper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
