<?php
require_once __DIR__ . '/../../../../includes/session_config.php';
require_once __DIR__ . '/../../../../includes/url_helper.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . url('app/views/auth/login.php'));
    exit();
}

require_once __DIR__ . '/../../../core/Database.php';
$db = new Database();
$conn = $db->connect();

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || $user['role'] !== 'executive_assistant') {
    header("Location: " . url('app/views/auth/dashboard.php'));
    exit();
}

$full_name = $user['first_name'] . ' ' . $user['last_name'];

// Ensure required tables and columns exist
$conn->query("CREATE TABLE IF NOT EXISTS `contract_form_templates` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `uploaded_by` INT(11) NOT NULL,
    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)
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
    `supervisor_notes` TEXT NULL,
    `ea_verification_status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `ea_verified_by` INT(11) NULL,
    `ea_verified_at` DATETIME NULL,
    `ea_notes` TEXT NULL,
    `sent_at` DATETIME NULL,
    `submitted_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add EA columns if missing on existing installs
$conn->query("ALTER TABLE `contract_submissions` ADD COLUMN IF NOT EXISTS `ea_verification_status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
$conn->query("ALTER TABLE `contract_submissions` ADD COLUMN IF NOT EXISTS `ea_verified_by` INT(11) NULL");
$conn->query("ALTER TABLE `contract_submissions` ADD COLUMN IF NOT EXISTS `ea_verified_at` DATETIME NULL");
$conn->query("ALTER TABLE `contract_submissions` ADD COLUMN IF NOT EXISTS `ea_notes` TEXT NULL");

// Get pending contracts for verification
$pendingStmt = $conn->prepare("
    SELECT cs.id, cs.status, cs.ea_verification_status, cs.created_at, cs.submitted_at,
           CONCAT(gu.first_name, ' ', gu.last_name) AS gambler_name,
           gu.email AS gambler_email,
           ii.score AS interview_score,
           ii.diagnosis AS diagnosis
    FROM contract_submissions cs
    JOIN users gu ON gu.id = cs.gambler_id
    LEFT JOIN Initial_Interview_Record ii ON ii.booking_id = cs.booking_id
    WHERE cs.status IN ('submitted','sent_to_parties') AND cs.ea_verification_status = 'pending'
    ORDER BY cs.created_at DESC
");
$pendingContracts = [];
if ($pendingStmt) {
    $pendingStmt->execute();
    $pendingContracts = $pendingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pendingStmt->close();
}

// Stats: contract verification counts
$approvedCount = 0; $rejectedCount = 0; $totalSubmitted = 0;
$statsStmt = $conn->query("
    SELECT ea_verification_status, COUNT(*) as cnt
    FROM contract_submissions
    WHERE status != 'draft'
    GROUP BY ea_verification_status
");
if ($statsStmt) {
    while ($row = $statsStmt->fetch_assoc()) {
        $totalSubmitted += $row['cnt'];
        if ($row['ea_verification_status'] === 'approved') $approvedCount = (int)$row['cnt'];
        elseif ($row['ea_verification_status'] === 'rejected') $rejectedCount = (int)$row['cnt'];
    }
}

// Stats: total registered gamblers
$r = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='gambler'");
$totalGamblers = $r ? (int)($r->fetch_assoc()['c'] ?? 0) : 0;

// Stats: total bookings
$r = $conn->query("SELECT COUNT(*) AS c FROM booking_record");
$totalBookings = $r ? (int)($r->fetch_assoc()['c'] ?? 0) : 0;

// Stats: total payments verified
$r = $conn->query("SELECT COUNT(*) AS c FROM payments WHERE payment_status IN ('paid','verified')");
$totalPayments = $r ? (int)($r->fetch_assoc()['c'] ?? 0) : 0;

// Stats: total interviews done
$r = $conn->query("SELECT COUNT(*) AS c FROM Initial_Interview_Record");
if (!$r) $r = $conn->query("SELECT COUNT(*) AS c FROM initial_interview_record");
$totalInterviews = $r ? (int)($r->fetch_assoc()['c'] ?? 0) : 0;

// Stats: family signed documents
$r = $conn->query("SELECT COUNT(*) AS c FROM family_signed_documents");
$totalFamilySigned = $r ? (int)($r->fetch_assoc()['c'] ?? 0) : 0;

// Recent activity: last 8 notifications relevant to EA work
$recentActivity = [];
$actStmt = $conn->query("
    SELECT n.title, n.message, n.type, n.created_at, n.link,
           CONCAT(u.first_name,' ',u.last_name) AS user_name, u.role
    FROM notifications n
    JOIN users u ON u.id = n.user_id
    WHERE n.type IN ('contract_approved','contract_rejected','contract_signed','payment_verified','interview_done','booking_approved','new_booking')
    ORDER BY n.created_at DESC
    LIMIT 8
");
if ($actStmt) {
    $recentActivity = $actStmt->fetch_all(MYSQLI_ASSOC);
}

// Recently approved/rejected by this EA
$myVerifications = [];
$myStmt = $conn->prepare("
    SELECT cs.id, cs.ea_verification_status, cs.ea_verified_at, cs.ea_notes,
           CONCAT(gu.first_name,' ',gu.last_name) AS gambler_name
    FROM contract_submissions cs
    JOIN users gu ON gu.id = cs.gambler_id
    WHERE cs.ea_verified_by = ? AND cs.ea_verification_status IN ('approved','rejected')
    ORDER BY cs.ea_verified_at DESC
    LIMIT 5
");
if ($myStmt) {
    $myStmt->bind_param('i', $user_id);
    $myStmt->execute();
    $myVerifications = $myStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $myStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Assistant Dashboard – Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
    <style>
        .main-content { margin-left:260px; flex:1; padding:2rem; }
        .top-navbar { background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.08); padding:1rem 1.5rem; margin-bottom:2rem; display:flex; justify-content:space-between; align-items:center; }
        /* ── Stat Cards ── */
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:1.25rem; margin-bottom:2rem; }
        .stat-card { background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); padding:1.25rem 1.5rem; display:flex; align-items:center; gap:1rem; transition:transform .2s; }
        .stat-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.12); }
        .stat-icon { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; }
        .stat-info .stat-number { font-size:1.9rem; font-weight:800; line-height:1; }
        .stat-info .stat-label { font-size:.78rem; color:#6c757d; font-weight:600; margin-top:.2rem; }
        /* ── Section Cards ── */
        .dash-card { background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); overflow:hidden; margin-bottom:1.5rem; }
        .dash-card-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:.9rem 1.5rem; font-weight:700; font-size:.95rem; display:flex; align-items:center; gap:.6rem; justify-content:space-between; }
        .dash-card-body { padding:1.25rem 1.5rem; }
        /* ── Pending contract rows ── */
        .pending-row { border:1.5px solid #e9ecef; border-radius:12px; padding:1rem 1.25rem; margin-bottom:.85rem; background:#fff; transition:box-shadow .2s; }
        .pending-row:hover { box-shadow:0 4px 16px rgba(0,0,0,.08); }
        .pending-row:last-child { margin-bottom:0; }
        .info-pill { display:inline-flex; align-items:center; gap:.35rem; padding:.25rem .7rem; border-radius:20px; font-size:.75rem; font-weight:700; }
        /* ── Activity feed ── */
        .activity-item { display:flex; align-items:flex-start; gap:.85rem; padding:.75rem 0; border-bottom:1px solid #f0f0f0; }
        .activity-item:last-child { border-bottom:none; }
        .activity-dot { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.85rem; flex-shrink:0; }
        /* ── Quick actions ── */
        .quick-action { display:flex; align-items:center; gap:.85rem; padding:.85rem 1rem; border-radius:12px; background:#f8f9fa; margin-bottom:.6rem; text-decoration:none; color:#343a40; transition:all .2s; border:1.5px solid transparent; }
        .quick-action:hover { background:#fff; border-color:#800000; color:#800000; transform:translateX(4px); }
        .quick-action:last-child { margin-bottom:0; }
        .quick-action-icon { width:38px; height:38px; border-radius:10px; background:linear-gradient(135deg,#800000,#5c0000); color:#fff; display:flex; align-items:center; justify-content:center; font-size:.9rem; flex-shrink:0; }
        /* ── Buttons ── */
        .btn-maroon { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border:none; border-radius:10px; padding:.5rem 1.1rem; font-weight:700; font-size:.82rem; cursor:pointer; display:inline-flex; align-items:center; gap:.4rem; transition:all .2s; text-decoration:none; }
        .btn-maroon:hover { opacity:.88; color:#fff; }
        .btn-green { background:linear-gradient(135deg,#28a745,#1a6b2e); color:#fff; border:none; border-radius:10px; padding:.5rem 1.1rem; font-weight:700; font-size:.82rem; cursor:pointer; display:inline-flex; align-items:center; gap:.4rem; transition:all .2s; }
        .btn-green:hover { opacity:.88; }
        .btn-red { background:linear-gradient(135deg,#dc3545,#991b1b); color:#fff; border:none; border-radius:10px; padding:.5rem 1.1rem; font-weight:700; font-size:.82rem; cursor:pointer; display:inline-flex; align-items:center; gap:.4rem; transition:all .2s; }
        .btn-red:hover { opacity:.88; }
        /* ── Notification bell ── */
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
    </style>
</head>
<body>
<div class="dashboard-container">

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="/GAMBYTES_Final/public/images/Logo.png" alt="Logo">
                <span>Gambytes</span>
            </div>
            <div class="sidebar-user">
                <div class="user-name"><i class="fas fa-user-tie me-1"></i><?= htmlspecialchars($full_name) ?></div>
                <div class="user-role">Executive Assistant</div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/GAMBYTES_Final/app/views/Users/Executive Assistant/dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Executive Assistant/contract-verification.php"><i class="fas fa-file-contract"></i> Contract Verification</a></li>
            <li><a href="#"><i class="fas fa-chart-line"></i> Reports</a></li>
            <li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
            <div class="menu-divider"></div>
            <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">

        <!-- Top Navbar -->
        <div class="top-navbar">
          <span style="font-weight:700;font-size:1rem;color:#800000">Executive Assistant Portal</span>
          <div style="display:flex;align-items:center;gap:1rem">
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
        </div>

        <!-- Page Header -->
        <div style="margin-bottom:1.75rem">
          <h1 style="color:#800000;font-size:1.8rem;font-weight:800;margin:0"><i class="fas fa-tachometer-alt me-2"></i>Executive Assistant Dashboard</h1>
          <p style="color:#6c757d;margin:.25rem 0 0">Welcome back, <strong><?= htmlspecialchars($full_name) ?></strong>! Here's your overview for today.</p>
        </div>

        <!-- ── Two-column layout ── -->
        <div class="row g-4">

          <!-- LEFT: Pending Contracts + My Recent Verifications -->
          <div class="col-lg-8">

            <!-- Pending Contracts -->
            <div class="dash-card">
              <div class="dash-card-header">
                <span><i class="fas fa-clock me-2"></i>Pending Contract Verifications</span>
                <span style="background:rgba(255,255,255,.2);padding:.2rem .65rem;border-radius:20px;font-size:.78rem"><?= count($pendingContracts) ?> pending</span>
              </div>
              <div class="dash-card-body">
                <?php if (empty($pendingContracts)): ?>
                <div style="text-align:center;padding:2.5rem;color:#6c757d">
                  <i class="fas fa-check-circle fa-3x mb-3" style="color:#28a745;opacity:.5;display:block"></i>
                  <h5 style="color:#28a745">All Caught Up!</h5>
                  <p style="font-size:.9rem">No contracts are currently pending verification.</p>
                  <a href="/GAMBYTES_Final/app/views/Users/Executive%20Assistant/contract-verification.php" class="btn-maroon" style="margin-top:.5rem">
                    <i class="fas fa-file-contract"></i> View All Contracts
                  </a>
                </div>
                <?php else: ?>
                <?php foreach ($pendingContracts as $c): ?>
                <div class="pending-row">
                  <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:.5rem;margin-bottom:.75rem">
                    <div>
                      <div style="font-weight:700;color:#212529;font-size:.95rem"><i class="fas fa-user-circle me-1" style="color:#800000"></i><?= htmlspecialchars($c['gambler_name']) ?></div>
                      <div style="font-size:.78rem;color:#6c757d;margin-top:.15rem"><?= htmlspecialchars($c['gambler_email']) ?></div>
                    </div>
                    <span class="info-pill" style="background:#fff3cd;color:#856404"><i class="fas fa-clock"></i> Pending</span>
                  </div>
                  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.5rem;margin-bottom:.75rem">
                    <?php if ($c['interview_score']): ?>
                    <div style="background:#f8f9fa;border-radius:8px;padding:.5rem .75rem;border-left:3px solid #800000">
                      <div style="font-size:.7rem;color:#6c757d;font-weight:600;text-transform:uppercase">Score</div>
                      <div style="font-weight:700;color:#800000"><?= $c['interview_score'] ?>/9</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($c['diagnosis']): ?>
                    <div style="background:#f8f9fa;border-radius:8px;padding:.5rem .75rem;border-left:3px solid #800000">
                      <div style="font-size:.7rem;color:#6c757d;font-weight:600;text-transform:uppercase">Diagnosis</div>
                      <div style="font-weight:700;color:#212529;font-size:.82rem"><?= htmlspecialchars($c['diagnosis']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div style="background:#f8f9fa;border-radius:8px;padding:.5rem .75rem;border-left:3px solid #800000">
                      <div style="font-size:.7rem;color:#6c757d;font-weight:600;text-transform:uppercase">Submitted</div>
                      <div style="font-weight:700;color:#212529;font-size:.82rem"><?= date('M j, Y', strtotime($c['created_at'])) ?></div>
                    </div>
                  </div>
                  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                    <a href="/GAMBYTES_Final/app/views/Users/Executive%20Assistant/view-contract.php?id=<?= $c['id'] ?>" class="btn-maroon" style="font-size:.8rem;padding:.4rem .9rem">
                      <i class="fas fa-eye"></i> View
                    </a>
                    <button class="btn-green" style="font-size:.8rem;padding:.4rem .9rem" onclick="verifyContract(<?= $c['id'] ?>, 'approved')">
                      <i class="fas fa-check"></i> Approve
                    </button>
                    <button class="btn-red" style="font-size:.8rem;padding:.4rem .9rem" onclick="verifyContract(<?= $c['id'] ?>, 'rejected')">
                      <i class="fas fa-times"></i> Reject
                    </button>
                  </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <!-- My Recent Verifications -->
            <?php if (!empty($myVerifications)): ?>
            <div class="dash-card">
              <div class="dash-card-header"><i class="fas fa-history me-2"></i>My Recent Verifications</div>
              <div class="dash-card-body" style="padding:0">
                <table style="width:100%;border-collapse:collapse;font-size:.88rem">
                  <thead><tr style="background:#f8f9fa">
                    <th style="padding:.75rem 1.25rem;font-weight:700;color:#343a40;text-align:left">Gambler</th>
                    <th style="padding:.75rem 1.25rem;font-weight:700;color:#343a40;text-align:left">Decision</th>
                    <th style="padding:.75rem 1.25rem;font-weight:700;color:#343a40;text-align:left">Date</th>
                    <th style="padding:.75rem 1.25rem;font-weight:700;color:#343a40;text-align:left">Notes</th>
                  </tr></thead>
                  <tbody>
                  <?php foreach ($myVerifications as $v): ?>
                  <tr style="border-bottom:1px solid #f0f0f0">
                    <td style="padding:.75rem 1.25rem;font-weight:600"><?= htmlspecialchars($v['gambler_name']) ?></td>
                    <td style="padding:.75rem 1.25rem">
                      <?php if ($v['ea_verification_status'] === 'approved'): ?>
                      <span class="info-pill" style="background:#d1e7dd;color:#0f5132"><i class="fas fa-check-circle"></i> Approved</span>
                      <?php else: ?>
                      <span class="info-pill" style="background:#f8d7da;color:#842029"><i class="fas fa-times-circle"></i> Rejected</span>
                      <?php endif; ?>
                    </td>
                    <td style="padding:.75rem 1.25rem;color:#6c757d;font-size:.82rem"><?= date('M j, Y', strtotime($v['ea_verified_at'])) ?></td>
                    <td style="padding:.75rem 1.25rem;color:#6c757d;font-size:.82rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($v['ea_notes'] ?? '—') ?></td>
                  </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <?php endif; ?>

          </div><!-- /col-lg-8 -->

          <!-- RIGHT: Quick Actions + Recent Activity -->
          <div class="col-lg-4">

            <!-- Quick Actions -->
            <div class="dash-card mb-4">
              <div class="dash-card-header"><i class="fas fa-bolt me-2"></i>Quick Actions</div>
              <div class="dash-card-body">
                <a href="/GAMBYTES_Final/app/views/Users/Executive%20Assistant/contract-verification.php" class="quick-action">
                  <div class="quick-action-icon"><i class="fas fa-file-contract"></i></div>
                  <div><div style="font-weight:700;font-size:.9rem">Contract Verification</div><div style="font-size:.78rem;color:#6c757d">Review &amp; verify contracts</div></div>
                </a>
                <a href="/GAMBYTES_Final/app/views/Users/Executive%20Assistant/view-contract.php" class="quick-action">
                  <div class="quick-action-icon"><i class="fas fa-eye"></i></div>
                  <div><div style="font-weight:700;font-size:.9rem">View Contracts</div><div style="font-size:.78rem;color:#6c757d">Browse all submissions</div></div>
                </a>
                <a href="/GAMBYTES_Final/app/views/Users/Executive%20Assistant/view-family-contract.php" class="quick-action">
                  <div class="quick-action-icon"><i class="fas fa-users"></i></div>
                  <div><div style="font-weight:700;font-size:.9rem">Family Contracts</div><div style="font-size:.78rem;color:#6c757d">View family agreements</div></div>
                </a>
              </div>
            </div>


<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:linear-gradient(135deg,#28a745,#1a6b2e);color:#fff;border:none">
        <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Approve Contract</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:1.25rem">
        <label class="form-label" style="font-weight:600;font-size:.88rem">Feedback / Notes <span style="color:#6c757d;font-weight:400">(optional)</span></label>
        <textarea id="approveFeedback" class="form-control" rows="3" placeholder="e.g. All documents are in order. Welcome to the program!" style="border-radius:10px"></textarea>
      </div>
      <div class="modal-footer" style="border:none;padding:.75rem 1.25rem">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:10px">Cancel</button>
        <button type="button" id="confirmApproveBtn" class="btn-green" style="border-radius:10px;padding:.55rem 1.5rem"><i class="fas fa-check me-1"></i>Confirm Approve</button>
      </div>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:linear-gradient(135deg,#dc3545,#991b1b);color:#fff;border:none">
        <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Reject Contract</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:1.25rem">
        <label class="form-label" style="font-weight:600;font-size:.88rem">Reason for Rejection <span style="color:#dc3545">*</span></label>
        <textarea id="rejectFeedback" class="form-control" rows="3" placeholder="e.g. Signature is missing. Please re-submit." style="border-radius:10px"></textarea>
        <div id="rejectError" style="color:#dc3545;font-size:.82rem;margin-top:.35rem;display:none"><i class="fas fa-exclamation-circle me-1"></i>Please provide a reason.</div>
      </div>
      <div class="modal-footer" style="border:none;padding:.75rem 1.25rem">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:10px">Cancel</button>
        <button type="button" id="confirmRejectBtn" class="btn-red" style="border-radius:10px;padding:.55rem 1.5rem"><i class="fas fa-times me-1"></i>Confirm Reject</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── Notifications ─────────────────────────────────────────────────────────────
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
    .then(r => r.json()).then(d => {
      const l = document.getElementById('notifList');
      if (!d.items || !d.items.length) { l.innerHTML = '<div class="notif-empty">No notifications</div>'; return; }
      l.innerHTML = d.items.map(n =>
        `<div class="notif-item">
          <div class="notif-content" onclick="if('${n.link||''}' !== '#') window.location.href='${n.link||''}'">
            <strong>${escHtml(n.title)}</strong>
            <span>${escHtml(n.message||'')}</span>
          </div>
          <button class="notif-delete" onclick="deleteNotif(${n.id}, event)" title="Delete notification">
            <i class="fas fa-times"></i>
          </button>
        </div>`).join('');
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
function markAllSeen(){
  fetch('/GAMBYTES_Final/api/notifications.php?action=mark_seen')
    .then(() => { document.getElementById('notifBadge').style.display = 'none'; });
}
function pollNotif(){
  fetch('/GAMBYTES_Final/api/notifications.php?action=count')
    .then(r => r.json()).then(d => {
      const b = document.getElementById('notifBadge');
      if (d.count > 0) { b.textContent = d.count; b.style.display = 'flex'; }
      else b.style.display = 'none';
    });
}
pollNotif(); setInterval(pollNotif, 30000);

// ── Contract Verification ─────────────────────────────────────────────────────
let _cid = null;
function verifyContract(id, action) {
  _cid = id;
  if (action === 'approved') {
    document.getElementById('approveFeedback').value = '';
    new bootstrap.Modal(document.getElementById('approveModal')).show();
  } else {
    document.getElementById('rejectFeedback').value = '';
    document.getElementById('rejectError').style.display = 'none';
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
  }
}
document.getElementById('confirmApproveBtn').addEventListener('click', function(){
  submitVerify(_cid, 'approved', document.getElementById('approveFeedback').value.trim(), this);
});
document.getElementById('confirmRejectBtn').addEventListener('click', function(){
  const notes = document.getElementById('rejectFeedback').value.trim();
  if (!notes) { document.getElementById('rejectError').style.display = 'block'; return; }
  document.getElementById('rejectError').style.display = 'none';
  submitVerify(_cid, 'rejected', notes, this);
});
function submitVerify(id, action, notes, btn) {
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
  fetch('/GAMBYTES_Final/api/verify_contract.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({contract_id: id, action, notes})
  })
  .then(r => r.json()).then(d => {
    const modalEl = document.getElementById(action === 'approved' ? 'approveModal' : 'rejectModal');
    bootstrap.Modal.getInstance(modalEl)?.hide();
    if (d.success) {
      showToast(action === 'approved' ? '✅ Contract approved!' : '❌ Contract rejected.', action === 'approved' ? '#28a745' : '#dc3545');
      setTimeout(() => location.reload(), 1500);
    } else {
      alert('Error: ' + (d.message || 'Failed'));
      btn.disabled = false;
    }
  }).catch(() => { alert('Network error.'); btn.disabled = false; });
}
function showToast(msg, bg) {
  const t = document.createElement('div');
  t.style.cssText = `position:fixed;top:1.5rem;right:1.5rem;z-index:9999;background:${bg};color:#fff;padding:.85rem 1.5rem;border-radius:12px;font-weight:700;font-size:.95rem;box-shadow:0 4px 20px rgba(0,0,0,.2)`;
  t.textContent = msg; document.body.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .4s'; setTimeout(() => t.remove(), 400); }, 2500);
}
</script>
</body>
</html>

