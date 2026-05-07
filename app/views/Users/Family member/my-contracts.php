<?php
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../../../../includes/session_config.php';
require_once __DIR__ . '/../../../../includes/url_helper.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . url('app/views/auth/login.php'));
    exit();
}
require_once __DIR__ . '/../../../core/Database.php';
$db   = new Database();
$conn = $db->connect();

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || $user['role'] !== 'family') {
    header("Location: " . url('app/views/auth/dashboard.php'));
    exit();
}

$full_name = $user['first_name'] . ' ' . $user['last_name'];

// DEBUG: Direct check for contracts
$debugCheck = $conn->query("SELECT COUNT(*) as cnt FROM contract_submissions WHERE family_member_id = $user_id");
$debugCount = $debugCheck->fetch_assoc()['cnt'];
error_log("DEBUG: Family member $user_id has $debugCount contract(s) in database");

// If debugging, show count at top of page
if (isset($_GET['debug'])) {
    echo "<div style='background:#fff3cd;padding:10px;margin:10px;border-radius:5px;'>";
    echo "<strong>DEBUG MODE:</strong> Found $debugCount contract(s) for family_member_id = $user_id";
    echo "</div>";
}

// ── Fetch contract submissions where this family member is linked ─────────────
// Also fetch ea_verification_status if the column exists (added by verify_contract.php)
$submissions = [];

// First, try a simple query to see if we can get the data
$simpleQuery = $conn->query("SELECT * FROM contract_submissions WHERE family_member_id = $user_id");
if ($simpleQuery) {
    error_log("Simple query found " . $simpleQuery->num_rows . " rows");
}

$subStmt = $conn->prepare(
    "SELECT cs.id, cs.status, cs.submitted_at, cs.sent_at,
            cs.gambler_id, cs.booking_id, cs.family_member_id,
            'Rehabilitation Agreement' AS template_title, 
            '' AS template_filename,
            COALESCE(CONCAT(gu.first_name,' ',gu.last_name), 'Unknown Gambler') AS gambler_name,
            COALESCE(cs.ea_verification_status, 'pending') AS ea_status,
            cs.ea_verified_at, cs.ea_notes,
            COALESCE(CONCAT(ea.first_name,' ',ea.last_name), '') AS ea_verified_by_name,
            scd_family.signature_data AS family_sig,
            scd_family.signed_at AS family_signed_at
     FROM contract_submissions cs
     LEFT JOIN users gu ON gu.id = cs.gambler_id
     LEFT JOIN users ea ON ea.id = cs.ea_verified_by
     LEFT JOIN signed_contract_documents scd_family ON scd_family.contract_document_id = cs.id AND scd_family.signer_role = 'family'
     WHERE cs.family_member_id = ?
     ORDER BY cs.created_at DESC"
);

// DEBUG: Log the query
error_log("Family Contracts Query - User ID: $user_id");

if (!$subStmt) {
    error_log("QUERY FAILED: " . $conn->error);
    // Show error in debug mode
    if (isset($_GET['debug'])) {
        echo "<div style='background:#f8d7da;padding:10px;margin:10px;border-radius:5px;color:#721c24;'>";
        echo "<strong>QUERY ERROR:</strong> " . $conn->error;
        echo "</div>";
    }
} else {
    $subStmt->bind_param('i', $user_id);
    $subStmt->execute();
    $res = $subStmt->get_result();
    
    // DEBUG: Log row count
    $rowCount = $res->num_rows;
    error_log("Family Contracts - Rows found: " . $rowCount);
    
    if (isset($_GET['debug'])) {
        echo "<div style='background:#d1ecf1;padding:10px;margin:10px;border-radius:5px;color:#0c5460;'>";
        echo "<strong>QUERY RESULT:</strong> Found $rowCount row(s) from query";
        echo "</div>";
    }
    
    while ($row = $res->fetch_assoc()) {
        // DEBUG: Log each row
        error_log("Family Contract found - ID: " . $row['id'] . ", Gambler: " . $row['gambler_name'] . ", Status: " . $row['status']);
        
        if (isset($_GET['debug'])) {
            echo "<div style='background:#d4edda;padding:10px;margin:10px;border-radius:5px;color:#155724;'>";
            echo "<strong>CONTRACT FOUND:</strong> ID=" . $row['id'] . ", Gambler=" . $row['gambler_name'] . ", Status=" . $row['status'];
            echo "</div>";
        }
        
        if ($row['template_filename']) {
            $row['template_url'] = '/GAMBYTES_Final/uploads/contract_templates/' . rawurlencode($row['template_filename']);
        } else {
            $row['template_url'] = ''; // No template file
        }
        $submissions[] = $row;
    }
    $subStmt->close();
}

// ── Check parental control link ───────────────────────────────────────────────
$pcStmt = $conn->prepare("SELECT pcr.gambler_id FROM parental_control_requests pcr WHERE pcr.family_id = ? AND pcr.status = 'accepted' LIMIT 1");
$pcStmt->bind_param('i', $user_id);
$pcStmt->execute();
$pcRow = $pcStmt->get_result()->fetch_assoc();
$pcStmt->close();
$hasLinkedGambler = (bool)$pcRow;

// ── Helper: check if a booking has already been paid ─────────────────────────
function isBookingPaid($conn, $booking_id) {
    if (!$booking_id) return false;
    $chk = $conn->prepare("SELECT id FROM payments WHERE booking_id = ? AND payment_status IN ('paid','verified') LIMIT 1");
    $chk->bind_param('i', $booking_id);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();
    return (bool)$row;
}

// Helper: map status to badge
function statusBadge($status, $eaStatus = '') {
    if ($eaStatus === 'approved') {
        return '<span style="background:#d1e7dd;color:#0f5132;padding:.25rem .75rem;border-radius:20px;font-size:.78rem;font-weight:700;">Approved</span>';
    }
    if ($eaStatus === 'rejected') {
        return '<span style="background:#f8d7da;color:#842029;padding:.25rem .75rem;border-radius:20px;font-size:.78rem;font-weight:700;">Rejected</span>';
    }
    switch ($status) {
        case 'completed':
            return '<span style="background:#cff4fc;color:#055160;padding:.25rem .75rem;border-radius:20px;font-size:.78rem;font-weight:700;">Signed</span>';
        case 'sent_to_parties':
            return '<span style="background:#fff3cd;color:#664d03;padding:.25rem .75rem;border-radius:20px;font-size:.78rem;font-weight:700;">Action Required</span>';
        case 'submitted':
        case 'reviewed':
            return '<span style="background:#fff3cd;color:#664d03;padding:.25rem .75rem;border-radius:20px;font-size:.78rem;font-weight:700;">Pending Review</span>';
        case 'draft':
            return '<span style="background:#e2e3e5;color:#41464b;padding:.25rem .75rem;border-radius:20px;font-size:.78rem;font-weight:700;">Draft</span>';
        default:
            return '<span style="background:#e2e3e5;color:#41464b;padding:.25rem .75rem;border-radius:20px;font-size:.78rem;font-weight:700;">' . htmlspecialchars($status) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Contracts – Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
    <style>
        .main-content { margin-left:260px; flex:1; padding:2rem; }
        .fc-card { background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); overflow:hidden; margin-bottom:1.5rem; }
        .fc-card-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:1rem 1.5rem; font-weight:700; font-size:1rem; display:flex; align-items:center; gap:.6rem; }
        .fc-card-body { padding:1.75rem; }
        .info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.75rem; margin-bottom:1.25rem; }
        .info-box { padding:.75rem 1rem; background:#f8f9fa; border-radius:10px; border-left:4px solid #800000; }
        .info-box .lbl { font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
        .info-box .val { font-weight:700; color:#212529; margin-top:2px; font-size:.92rem; }
        .btn-maroon { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border:none; border-radius:12px; padding:.65rem 1.5rem; font-weight:600; font-size:.9rem; cursor:pointer; display:inline-flex; align-items:center; gap:.5rem; text-decoration:none; box-shadow:0 4px 14px rgba(128,0,0,.3); transition:all .2s; }
        .btn-maroon:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(128,0,0,.4); color:#fff; }
        .btn-outline { background:#fff; color:#800000; border:2px solid #800000; border-radius:12px; padding:.6rem 1.4rem; font-weight:600; font-size:.9rem; cursor:pointer; display:inline-flex; align-items:center; gap:.5rem; text-decoration:none; transition:all .2s; }
        .btn-outline:hover { background:#800000; color:#fff; }
        .approved-banner { background:linear-gradient(135deg,#d1e7dd,#c3e6cb); border:1.5px solid #a3cfbb; border-radius:14px; padding:1.25rem 1.5rem; display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; }
        .top-navbar { background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.08); padding:1rem 1.5rem; margin-bottom:2rem; display:flex; justify-content:space-between; align-items:center; }
        .notif-bell-wrap { position:relative; }
        .notif-bell-btn { background:linear-gradient(135deg,#800000,#5c0000); border:none; color:#fff; width:40px; height:40px; border-radius:10px; cursor:pointer; font-size:1rem; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 8px rgba(128,0,0,.3); }
        .notif-badge { position:absolute; top:-6px; right:-6px; background:#ffc107; color:#000; font-size:.65rem; font-weight:800; min-width:18px; height:18px; border-radius:9px; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #fff; }
        .notif-dropdown { display:none; position:absolute; right:0; top:calc(100% + 8px); width:300px; background:#fff; border-radius:14px; box-shadow:0 8px 30px rgba(0,0,0,.15); z-index:9999; overflow:hidden; }
        .notif-dropdown.open { display:block; }
        .notif-dropdown-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:.75rem 1rem; display:flex; justify-content:space-between; align-items:center; font-weight:700; font-size:.9rem; }
        .notif-mark-btn { background:rgba(255,255,255,.2); border:none; color:#fff; font-size:.75rem; padding:.25rem .6rem; border-radius:6px; cursor:pointer; }
        .notif-item { padding:.75rem 1rem; border-bottom:1px solid #f0f0f0; font-size:.85rem; display:flex; justify-content:space-between; align-items:flex-start; gap:.75rem; }
        .notif-item:last-child { border-bottom:none; }
        .notif-item strong { display:block; color:#343a40; }
        .notif-item span { color:#6c757d; font-size:.78rem; }
        .notif-content { flex:1; cursor:pointer; }
        .notif-delete { flex-shrink:0; background:none; border:none; color:#dc3545; cursor:pointer; font-size:.9rem; padding:.25rem .5rem; border-radius:4px; transition:.2s; }
        .notif-delete:hover { background:rgba(220,53,69,.1); }
        .notif-empty { padding:1.5rem 1rem; text-align:center; color:#6c757d; font-size:.85rem; }
        .contract-row { border:1.5px solid #e9ecef; border-radius:14px; padding:1.25rem 1.5rem; margin-bottom:1rem; background:#fff; transition:box-shadow .2s; }
        .contract-row:hover { box-shadow:0 4px 16px rgba(0,0,0,.08); }
        .section-label { font-size:.78rem; font-weight:700; color:#800000; text-transform:uppercase; letter-spacing:.5px; margin-bottom:1rem; padding-bottom:.4rem; border-bottom:2px solid #f0f0f0; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="/GAMBYTES_Final/public/images/Logo.png" alt="Logo">
                <span>Gambytes</span>
            </div>
            <div class="sidebar-user">
                <div class="user-name"><i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($full_name) ?></div>
                <div class="user-role">Family Member</div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="#" class="active"><i class="fas fa-file-contract"></i> My Contracts</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Family member/parental-control.php"><i class="fas fa-shield-alt"></i> Parental Control</a></li>
            <li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
            <div class="menu-divider"></div>
            <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <span style="font-weight:700;font-size:1rem;color:#800000">Family Portal</span>
            <div class="notif-bell-wrap" id="notifWrap">
                <button type="button" class="notif-bell-btn" onclick="toggleNotifDropdown()">
                    <i class="fas fa-bell"></i>
                    <span class="notif-badge" id="notifBadge" style="display:none">0</span>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-dropdown-header">
                        <span><i class="fas fa-bell me-1"></i> Notifications</span>
                        <button onclick="markAllSeen()" class="notif-mark-btn">Mark all read</button>
                    </div>
                    <div id="notifList"><div class="notif-empty">No notifications</div></div>
                </div>
            </div>
        </div>

        <div style="margin-bottom:1.75rem">
            <h1 style="color:#800000;font-size:1.8rem;font-weight:800;margin:0"><i class="fas fa-file-contract me-2"></i>My Contracts</h1>
            <p style="color:#6c757d;margin:.25rem 0 0">View and manage your rehabilitation contracts.</p>
        </div>

        <?php if (empty($submissions) && !$hasLinkedGambler): ?>
        <!-- No contracts at all -->
        <div class="fc-card">
            <div class="fc-card-body" style="text-align:center;padding:3rem;color:#6c757d">
                <i class="fas fa-file-contract fa-3x mb-3" style="opacity:.3;display:block"></i>
                <h4 style="color:#6c757d;font-weight:700">No Contracts Yet</h4>
                <p style="font-size:.92rem">
                    You are not yet linked to a gambler. Once a gambler links you via parental control and submits a contract, it will appear here.
                </p>
                <a href="/GAMBYTES_Final/app/views/auth/dashboard.php" class="btn-maroon mt-2">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php elseif (empty($submissions) && $hasLinkedGambler): ?>
        <!-- Linked but no contracts yet -->
        <div class="fc-card">
            <div class="fc-card-body" style="text-align:center;padding:3rem;color:#6c757d">
                <i class="fas fa-file-contract fa-3x mb-3" style="opacity:.3;display:block"></i>
                <h4 style="color:#6c757d;font-weight:700">No Contracts Yet</h4>
                <p style="font-size:.92rem">
                    No contracts have been sent to you yet. Please wait for the gambler to apply for treatment rehabilitation.
                </p>
                <a href="/GAMBYTES_Final/app/views/auth/dashboard.php" class="btn-maroon mt-2">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php else: ?>

        <?php if (!empty($submissions)): ?>
        <!-- ── Contract Submissions ── -->
        <div class="fc-card">
            <div class="fc-card-header"><i class="fas fa-pen-nib"></i> Rehabilitation Contracts</div>
            <div class="fc-card-body">
                <?php foreach ($submissions as $sub): ?>
                <?php
                    $isApproved  = $sub['ea_status'] === 'approved';
                    $isRejected  = $sub['ea_status'] === 'rejected';
                    // Family can sign if status is 'submitted', 'reviewed', or 'sent_to_parties' and they haven't signed yet
                    $needsSign   = in_array($sub['status'], ['submitted', 'reviewed', 'sent_to_parties']) && empty($sub['family_sig']);
                    $hasSigned   = !empty($sub['family_sig']);
                    $signedDate  = $sub['submitted_at'] ? date('F j, Y', strtotime($sub['submitted_at'])) : '—';
                ?>
                <div class="contract-row">
                    <?php if ($isApproved): ?>
                    <div class="approved-banner">
                        <div style="background:#198754;color:#fff;width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.2rem">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;color:#0f5132;font-size:1rem">Approved</div>
                            <div style="font-size:.85rem;color:#0f5132">Congratulations! Your rehabilitation contract has been approved. You may now proceed to payment.</div>
                        </div>
                    </div>
                    <?php elseif ($isRejected): ?>
                    <div style="background:#f8d7da;border:1.5px solid #f5c2c7;border-radius:12px;padding:1rem 1.25rem;display:flex;align-items:center;gap:.85rem;margin-bottom:1.25rem">
                        <i class="fas fa-times-circle fa-lg" style="color:#842029;flex-shrink:0"></i>
                        <div>
                            <div style="font-weight:700;color:#842029">Contract Rejected</div>
                            <div style="font-size:.85rem;color:#842029">This contract was rejected. Please contact the supervisor for more information.</div>
                        </div>
                    </div>
                    <?php elseif ($needsSign): ?>
                    <div style="background:#fff3cd;border:1.5px solid #ffc107;border-radius:12px;padding:1rem 1.25rem;display:flex;align-items:center;gap:.85rem;margin-bottom:1.25rem">
                        <i class="fas fa-exclamation-triangle fa-lg" style="color:#856404;flex-shrink:0"></i>
                        <div>
                            <div style="font-weight:700;color:#664d03">Action Required</div>
                            <div style="font-size:.85rem;color:#664d03">Please review and sign this contract.</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="info-grid">
                        <div class="info-box">
                            <div class="lbl">Submitted By</div>
                            <div class="val"><?= htmlspecialchars($sub['gambler_name']) ?></div>
                        </div>
                        <div class="info-box">
                            <div class="lbl">Document</div>
                            <div class="val"><?= htmlspecialchars($sub['template_title']) ?></div>
                        </div>
                        <div class="info-box">
                            <div class="lbl">Signed Date</div>
                            <div class="val"><?= $signedDate ?></div>
                        </div>
                        <div class="info-box">
                            <div class="lbl">Status</div>
                            <div class="val"><?= statusBadge($sub['status'], $sub['ea_status']) ?></div>
                        </div>
                    </div>

                    <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.5rem">
                        <?php if ($isApproved): ?>
                        <?php if (isBookingPaid($conn, $sub['booking_id'])): ?>
                        <span style="background:#d1e7dd;color:#0f5132;padding:.6rem 1.4rem;border-radius:12px;font-weight:700;font-size:.9rem;display:inline-flex;align-items:center;gap:.5rem;">
                            <i class="fas fa-check-circle"></i> Payment Completed
                        </span>
                        <?php else: ?>
                        <a href="/GAMBYTES_Final/app/views/Users/admin department/payment/pay.php?booking_id=<?= (int)$sub['booking_id'] ?>"
                           class="btn-maroon">
                            <i class="fas fa-credit-card"></i> Pay Now
                        </a>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($needsSign): ?>
                        <a href="/GAMBYTES_Final/app/views/Users/Family member/fill-contract.php?submission_id=<?= (int)$sub['id'] ?>"
                           class="btn-maroon">
                            <i class="fas fa-pen-nib"></i> Sign Contract
                        </a>
                        <?php elseif ($hasSigned): ?>
                        <a href="/GAMBYTES_Final/app/views/Users/Family member/fill-contract.php?submission_id=<?= (int)$sub['id'] ?>"
                           class="btn-outline">
                            <i class="fas fa-eye"></i> View Contract
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>

        <div style="margin-top:1rem">
            <a href="/GAMBYTES_Final/app/views/auth/dashboard.php" class="btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function toggleNotifDropdown(){
    const dd = document.getElementById('notifDropdown');
    dd.classList.toggle('open');
    if (dd.classList.contains('open')) loadNotifs();
}
document.addEventListener('click', e => {
    const w = document.getElementById('notifWrap');
    if (w && !w.contains(e.target)) document.getElementById('notifDropdown').classList.remove('open');
});
function loadNotifs(){
    fetch('/GAMBYTES_Final/api/notifications.php?action=list')
        .then(r => r.json())
        .then(d => {
            const l = document.getElementById('notifList');
            if (!d.items || !d.items.length) { l.innerHTML = '<div class="notif-empty">No notifications</div>'; return; }
            l.innerHTML = d.items.map(n =>
                `<div class="notif-item">
                    <div class="notif-content" onclick="goNotif(${JSON.stringify(n.link||'')})">
                        <strong>${escHtml(n.title)}</strong>
                        <span>${escHtml(n.message||'')}</span>
                    </div>
                    <button class="notif-delete" onclick="deleteNotif(${n.id}, event)" title="Delete notification">
                        <i class="fas fa-times"></i>
                    </button>
                </div>`
            ).join('');
        });
}
function deleteNotif(notifId, event){
    event.stopPropagation();
    if (!confirm('Are you sure you want to delete this notification?')) return;
    fetch('/GAMBYTES_Final/api/notifications.php?action=delete&id=' + notifId)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                loadNotifs();
                pollNotif();
            } else {
                alert('Error deleting notification: ' + (d.message || 'Unknown error'));
            }
        });
}
function goNotif(link){ if(link && link!=='#') window.location.href=link; }
function markAllSeen(){
    fetch('/GAMBYTES_Final/api/notifications.php?action=mark_seen')
        .then(() => { document.getElementById('notifBadge').style.display = 'none'; });
}
function pollNotif(){
    fetch('/GAMBYTES_Final/api/notifications.php?action=count')
        .then(r => r.json())
        .then(d => {
            const b = document.getElementById('notifBadge');
            if (d.count > 0) { b.textContent = d.count; b.style.display = 'flex'; }
            else b.style.display = 'none';
        });
}
pollNotif();
setInterval(pollNotif, 30000);
</script>
</body>
</html>
