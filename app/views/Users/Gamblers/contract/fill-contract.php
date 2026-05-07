<?php
require_once __DIR__ . '/../../../../../includes/session_config.php';
require_once __DIR__ . '/../../../../../includes/url_helper.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . url('app/views/auth/login.php')); exit(); }
require_once __DIR__ . '/../../../../core/Database.php';
$db = new Database(); $conn = $db->connect();
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$user || $user['role'] !== 'gambler') { header("Location: " . url('app/views/auth/dashboard.php')); exit(); }
$full_name = $user['first_name'] . ' ' . $user['last_name'];

$submission_id = (int)($_GET['submission_id'] ?? 0);
$booking_id    = (int)($_GET['booking_id']    ?? 0);
$no_template   = false;

// ── Ensure required tables exist (always run before any prepare()) ──────────
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
    `sent_at` DATETIME NULL,
    `submitted_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ea_verification_status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `ea_verified_by` INT(11) NULL,
    `ea_verified_at` DATETIME NULL,
    `ea_notes` TEXT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add ea columns and family_member_id if they are missing from an older version of the table
$ea_cols = [
    'family_member_id'       => "ALTER TABLE `contract_submissions` ADD COLUMN `family_member_id` INT(11) NULL AFTER `gambler_id`",
    'ea_verification_status' => "ALTER TABLE `contract_submissions` ADD COLUMN `ea_verification_status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'",
    'ea_verified_by'         => "ALTER TABLE `contract_submissions` ADD COLUMN `ea_verified_by` INT(11) NULL",
    'ea_verified_at'         => "ALTER TABLE `contract_submissions` ADD COLUMN `ea_verified_at` DATETIME NULL",
    'ea_notes'               => "ALTER TABLE `contract_submissions` ADD COLUMN `ea_notes` TEXT NULL",
];
foreach ($ea_cols as $col => $sql) {
    $colCheck = $conn->query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_submissions' AND COLUMN_NAME = '$col'");
    $colRow = $colCheck ? $colCheck->fetch_assoc() : null;
    if (!$colRow || (int)$colRow['cnt'] === 0) {
        $conn->query($sql);
    }
}

// ── Only create submission if gambler has explicitly applied for treatment ──
if (!$submission_id && $booking_id) {
    // First verify this gambler has actually applied for treatment (score >= 4)
    $verifyStmt = $conn->prepare("SELECT ii.score FROM Initial_Interview_Record ii 
                                  JOIN booking_record br ON ii.booking_id = br.id 
                                  WHERE br.id = ? AND br.email = ? LIMIT 1");
    $verifyStmt->bind_param('is', $booking_id, $user['email']);
    $verifyStmt->execute();
    $interviewResult = $verifyStmt->get_result()->fetch_assoc();
    $verifyStmt->close();
    
    // Check if gambler qualifies for treatment (score >= 4)
    if (!$interviewResult || (int)$interviewResult['score'] < 4) {
        header("Location: /GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php");
        exit();
    }

    // Check if a submission already exists for this gambler + booking
    $chk = $conn->prepare("SELECT id FROM contract_submissions WHERE gambler_id = ? AND booking_id = ? LIMIT 1");
    if (!$chk) {
        error_log("SQL Error in fill-contract.php line 88: " . $conn->error);
        die("Database error: Unable to prepare statement. Please contact administrator. Error: " . $conn->error);
    }
    $chk->bind_param('ii', $user_id, $booking_id);
    $chk->execute();
    $existing_sub = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($existing_sub) {
        $submission_id = (int)$existing_sub['id'];
    } else {
        // Find the latest active template — use 0 as fallback (agreement is hardcoded)
        $tpl_res = $conn->query("SELECT id FROM contract_form_templates WHERE is_active = 1 ORDER BY uploaded_at DESC LIMIT 1");
        $tpl = $tpl_res ? $tpl_res->fetch_assoc() : null;
        $template_id = $tpl ? (int)$tpl['id'] : 0;

        // IMPORTANT: Look up linked family member from parental_control_requests
        $family_member_id = null;
        
        // First ensure the parental_control_requests table exists
        $conn->query("CREATE TABLE IF NOT EXISTS `parental_control_requests` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `gambler_id` INT(11) NOT NULL,
            `family_id` INT(11) NOT NULL,
            `status` ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_gambler` (`gambler_id`),
            INDEX `idx_family` (`family_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Now safely query for linked family member
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

        $sql = "INSERT INTO contract_submissions (gambler_id, family_member_id, booking_id, status, created_at) VALUES (?, ?, ?, 'draft', NOW())";
        $ins = $conn->prepare($sql);
        if ($ins === false) {
            error_log("SQL Error in contract submission: " . $conn->error . " SQL: " . $sql);
            header("Location: /GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php?error=db_error");
            exit();
        }
        $ins->bind_param('iii', $user_id, $family_member_id, $booking_id);
        $ins->execute();
        $submission_id = (int)$conn->insert_id;
        $ins->close();
    }
}

if (!$submission_id) {
    header("Location: /GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php");
    exit();
}

// ── Load submission data server-side (no AJAX needed) ────────────────────────
$submission_data = null;
$contract_status = null; // 'draft', 'submitted', 'approved', 'rejected'
$ea_feedback     = null;

if ($submission_id) {
    $sd = $conn->prepare(
        "SELECT cs.id, cs.status,
                cs.ea_verification_status, cs.ea_notes, cs.submitted_at, cs.ea_verified_at,
                COALESCE(CONCAT(ea.first_name,' ',ea.last_name), '') AS ea_verified_by_name,
                'Rehabilitation Agreement' AS template_title,
                '' AS template_filename
         FROM contract_submissions cs
         LEFT JOIN users ea ON ea.id = cs.ea_verified_by
         WHERE cs.id = ? AND cs.gambler_id = ?"
    );
    
    if (!$sd) {
        error_log("SQL Error in fill-contract.php line 166: " . $conn->error);
        die("Database error: Unable to load contract submission. Error: " . $conn->error);
    }
    
    $sd->bind_param('ii', $submission_id, $user_id);
    $sd->execute();
    $row = $sd->get_result()->fetch_assoc();
    $sd->close();
    if ($row) {
        $row['template_url'] = $row['template_filename'] ? '/GAMBYTES_Final/uploads/contract_templates/' . rawurlencode($row['template_filename']) : '';
        $submission_data = $row;
        $contract_status = $row['ea_verification_status'] ?? 'pending';
        $ea_feedback     = $row['ea_notes'] ?? null;
        // If already submitted/approved/rejected, show status page not the form
        if (in_array($row['status'], ['submitted', 'reviewed', 'completed'])) {
            $show_status_page = true;
        }
    }
}

// Check if gambler has already submitted a contract for this booking
$hasSubmitted = false;
if ($booking_id) {
    $checkStmt = $conn->prepare("SELECT id FROM contract_submissions WHERE gambler_id = ? AND booking_id = ? AND status != 'draft' LIMIT 1");
    if ($checkStmt) {
        $checkStmt->bind_param('ii', $user_id, $booking_id);
        $checkStmt->execute();
        $hasSubmitted = (bool)$checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
    }
}

// If resubmit=1 (after rejection), clear old records so form shows again
if (isset($_GET['resubmit']) && $_GET['resubmit'] == '1' && $contract_status === 'rejected') {
    $conn->query("UPDATE contract_submissions SET status = 'draft', ea_verification_status = 'pending', submitted_at = NULL WHERE gambler_id = $user_id AND booking_id = $booking_id AND status = 'submitted'");
    $hasSubmitted = false;
    $show_status_page = false;
    $contract_status = null;
}

if ($hasSubmitted && !isset($show_status_page)) {
    $show_status_page = true;
    $contract_status  = $contract_status ?? 'pending';
}

// ── Load policies from supervisor ────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `policy_files` (
    `id` INT(11) NOT NULL AUTO_INCREMENT, `doc_title` VARCHAR(255) NOT NULL,
    `doc_type` VARCHAR(100) NOT NULL DEFAULT 'Other',
    `doc_category` VARCHAR(100) NOT NULL DEFAULT 'General',
    `description` TEXT NULL, `filename` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL, `uploaded_by` INT(11) NOT NULL,
    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pRes = $conn->query("SELECT * FROM policy_files ORDER BY doc_category, uploaded_at DESC");
$policies = [];
$grouped_policies = [];
while ($pRow = $pRes->fetch_assoc()) {
    $pRow['url'] = '/GAMBYTES_Final/uploads/policies/' . rawurlencode($pRow['filename']);
    $grouped_policies[$pRow['doc_category']][] = $pRow;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Rehabilitation Application – Gambytes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
<style>

.fc-card{background:#fff;border-radius:16px;box-shadow:0 2px 15px rgba(0,0,0,.08);overflow:hidden;margin-bottom:1.5rem}
.fc-card-header{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;padding:1rem 1.5rem;font-weight:700;font-size:1rem;display:flex;align-items:center;gap:.6rem}
.fc-card-body{padding:1.75rem}
.field-group{margin-bottom:1rem}
.field-group label{display:block;font-weight:600;font-size:.85rem;color:#343a40;margin-bottom:.35rem}
.field-group input,.field-group textarea,.field-group select{width:100%;border:1.5px solid #dee2e6;border-radius:8px;padding:.5rem .85rem;font-size:.88rem;transition:.2s}
.field-group input:focus,.field-group textarea:focus,.field-group select:focus{outline:none;border-color:#800000;box-shadow:0 0 0 3px rgba(128,0,0,.1)}
.sig-canvas-wrap{border:2px solid #dee2e6;border-radius:10px;background:#f8f9fa;overflow:hidden}
.sig-canvas-wrap canvas{display:block;cursor:crosshair;touch-action:none;width:100%;height:150px}
.btn-maroon{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;border:none;border-radius:10px;padding:.65rem 1.75rem;font-weight:700;font-size:.95rem;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:.5rem}
.btn-maroon:hover{opacity:.88}
.btn-clear{background:#6c757d;color:#fff;border:none;border-radius:8px;padding:.4rem 1rem;font-size:.82rem;font-weight:600;cursor:pointer}
.info-banner{background:#fff8e1;border-left:4px solid #ffc107;border-radius:10px;padding:.85rem 1.1rem;font-size:.88rem;color:#5a4a00;margin-bottom:1.25rem;display:flex;align-items:flex-start;gap:.75rem}
.top-navbar{background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);padding:.85rem 1.5rem;display:flex;align-items:center;justify-content:space-between;margin-bottom:1.75rem}
.top-navbar-title{font-weight:700;font-size:1rem;color:#800000}
.notif-bell-wrap{position:relative}
.notif-bell-btn{background:linear-gradient(135deg,#800000,#5c0000);border:none;color:#fff;width:40px;height:40px;border-radius:10px;cursor:pointer;font-size:1rem;position:relative;transition:.2s;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(128,0,0,.3)}
.notif-badge{position:absolute;top:-6px;right:-6px;background:#ffc107;color:#000;font-size:.65rem;font-weight:800;min-width:18px;height:18px;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px;border:2px solid #fff}
.notif-dropdown{display:none;position:absolute;right:0;top:calc(100% + 8px);width:300px;background:#fff;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.15);z-index:9999;overflow:hidden}
.notif-dropdown.open{display:block}
.notif-dropdown-header{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;padding:.75rem 1rem;display:flex;justify-content:space-between;align-items:center;font-weight:700;font-size:.9rem}
.notif-mark-btn{background:rgba(255,255,255,.2);border:none;color:#fff;font-size:.75rem;padding:.25rem .6rem;border-radius:6px;cursor:pointer}
.notif-item{padding:.75rem 1rem;border-bottom:1px solid #f0f0f0;font-size:.85rem}
.notif-item:last-child{border-bottom:none}
.notif-item strong{display:block;color:#343a40}
.notif-item span{color:#6c757d;font-size:.78rem}
.notif-empty{padding:1.5rem 1rem;text-align:center;color:#6c757d;font-size:.85rem}
.policy-item{border-left:4px solid #800000;padding:.85rem 1.25rem;margin-bottom:.75rem;background:#fafafa;border-radius:0 10px 10px 0;display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap}
.policy-item-title{font-weight:700;font-size:.95rem;color:#212529;margin-bottom:.2rem}
.policy-item-meta{font-size:.8rem;color:#6c757d}
.badge-cat{display:inline-block;padding:.2rem .65rem;border-radius:20px;font-size:.72rem;font-weight:700;background:#e0e7ff;color:#3730a3}
.section-divider{border:none;border-top:3px solid #f0f0f0;margin:2rem 0}
.agree-box{background:linear-gradient(135deg,#fff5f5,#ffe8e8);border:2px solid #f5c2c7;border-radius:14px;padding:1.25rem 1.5rem;margin-bottom:1.5rem}
.agree-check{display:flex;align-items:flex-start;gap:.85rem;cursor:pointer}
.agree-check input[type=checkbox]{width:20px;height:20px;margin-top:2px;accent-color:#800000;flex-shrink:0;cursor:pointer}
</style>
</head>
<body>
<div class="dashboard-container">
<div class="sidebar">
<div class="sidebar-header">
<div class="sidebar-logo"><img src="/GAMBYTES_Final/public/images/Logo.png" alt="Logo"><span>Gambytes</span></div>
<div class="sidebar-user">
<div class="user-name"><i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($full_name) ?></div>
<div class="user-role">Gambler</div>
</div>
</div>
<ul class="sidebar-menu">
<li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/BookRehab.php"><i class="fas fa-calendar-plus"></i> Book Rehabilitation</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php"><i class="fas fa-clipboard-list"></i> My Interview</a></li>
<li><a href="#" class="active"><i class="fas fa-file-contract"></i> My Contracts</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-cbt-sessions.php"><i class="fas fa-brain"></i> CBT Sessions</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-activities.php"><i class="fas fa-tasks"></i> My Activities</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/parental-control-requests.php"><i class="fas fa-shield-alt"></i> Parental Access</a></li>
<li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
<div class="menu-divider"></div>
<li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul>
</div>
<div class="main-content">
<div class="top-navbar">
<span class="top-navbar-title">Gambler Portal</span>
<div class="notif-bell-wrap" id="notifWrap">
<button type="button" class="notif-bell-btn" onclick="toggleNotifDropdown()">
<i class="fas fa-bell"></i><span class="notif-badge" id="notifBadge" style="display:none">0</span>
</button>
<div class="notif-dropdown" id="notifDropdown">
<div class="notif-dropdown-header"><span><i class="fas fa-bell me-1"></i> Notifications</span><button onclick="markAllSeen()" class="notif-mark-btn">Mark all read</button></div>
<div id="notifList"><div class="notif-empty">No notifications</div></div>
</div>
</div>
</div>


<div id="contractContent">

<?php if (!empty($show_status_page)): ?>
<!-- ═══ CONTRACT STATUS PAGE ═══ -->
<div style="margin-bottom:1.75rem">
  <h1 style="color:#800000;font-size:1.8rem;font-weight:800;margin:0"><i class="fas fa-file-contract me-2"></i>My Contract</h1>
  <p style="color:#6c757d;margin:.25rem 0 0">Your rehabilitation agreement status.</p>
</div>

<?php
  $statusConfig = [
    'pending'  => ['color'=>'#f59e0b','bg'=>'#fffbeb','border'=>'#fde68a','icon'=>'fa-clock',         'label'=>'Pending Review',  'desc'=>'Your contract has been submitted and is currently being reviewed by the Executive Assistant.'],
    'approved' => ['color'=>'#16a34a','bg'=>'#f0fdf4','border'=>'#bbf7d0','icon'=>'fa-check-circle',  'label'=>'Approved',        'desc'=>'Congratulations! Your rehabilitation contract has been approved. You may now proceed to payment.'],
    'rejected' => ['color'=>'#dc2626','bg'=>'#fff5f5','border'=>'#fecaca','icon'=>'fa-times-circle',  'label'=>'Rejected',        'desc'=>'Your contract was not approved. Please review the feedback below and re-submit.'],
  ];
  $cfg = $statusConfig[$contract_status] ?? $statusConfig['pending'];
?>

<div class="fc-card">
  <div class="fc-card-body" style="padding:2rem;">

    <!-- Status Banner -->
    <div style="background:<?= $cfg['bg'] ?>;border:2px solid <?= $cfg['border'] ?>;border-radius:16px;padding:1.5rem 2rem;display:flex;align-items:center;gap:1.25rem;margin-bottom:1.5rem;">
      <div style="width:56px;height:56px;background:<?= $cfg['color'] ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fas <?= $cfg['icon'] ?>" style="color:#fff;font-size:1.5rem;"></i>
      </div>
      <div>
        <div style="font-size:1.2rem;font-weight:800;color:<?= $cfg['color'] ?>;"><?= $cfg['label'] ?></div>
        <div style="font-size:.9rem;color:#6b7280;margin-top:.2rem;"><?= $cfg['desc'] ?></div>
      </div>
    </div>

    <!-- Submission Details -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem;">
      <div style="background:#f8f9fa;border-radius:12px;padding:1rem 1.25rem;border-left:4px solid #800000;">
        <div style="font-size:.75rem;color:#6c757d;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Submitted By</div>
        <div style="font-weight:700;color:#212529;margin-top:.25rem;"><?= htmlspecialchars($full_name) ?></div>
      </div>
      <div style="background:#f8f9fa;border-radius:12px;padding:1rem 1.25rem;border-left:4px solid #800000;">
        <div style="font-size:.75rem;color:#6c757d;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Document</div>
        <div style="font-weight:700;color:#212529;margin-top:.25rem;">Rehabilitation Agreement</div>
      </div>
      <?php if ($submission_data && $submission_data['submitted_at']): ?>
      <div style="background:#f8f9fa;border-radius:12px;padding:1rem 1.25rem;border-left:4px solid #800000;">
        <div style="font-size:.75rem;color:#6c757d;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Submitted Date</div>
        <div style="font-weight:700;color:#212529;margin-top:.25rem;"><?= date('M j, Y', strtotime($submission_data['submitted_at'])) ?></div>
      </div>
      <?php endif; ?>
      <div style="background:#f8f9fa;border-radius:12px;padding:1rem 1.25rem;border-left:4px solid #800000;">
        <div style="font-size:.75rem;color:#6c757d;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Status</div>
        <div style="font-weight:700;color:<?= $cfg['color'] ?>;margin-top:.25rem;"><?= $cfg['label'] ?></div>
      </div>
    </div>

    <!-- Executive Assistant Verification Section -->
    <div style="margin-top:1.5rem;padding-top:1.5rem;border-top:2px solid #e5e7eb;">
      <div style="font-size:.78rem;font-weight:700;color:#800000;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem;padding-bottom:.4rem;border-bottom:2px solid #f0f0f0;">
        <i class="fas fa-user-check me-1"></i> Executive Assistant Verification
      </div>
      
      <?php if ($contract_status === 'pending'): ?>
        <div style="background:#f8f9fa;padding:1.25rem;border-radius:10px;border-left:4px solid #ffc107;">
          <div style="display:flex;align-items:center;gap:.75rem;">
            <i class="fas fa-hourglass-half fa-lg" style="color:#ffc107;"></i>
            <div>
              <div style="font-weight:700;color:#856404;">Pending Verification</div>
              <div style="font-size:.85rem;color:#6c757d;margin-top:.25rem;">Your contract is currently being reviewed by the Executive Assistant. Please wait for verification to proceed.</div>
            </div>
          </div>
        </div>
      <?php elseif ($contract_status === 'approved'): ?>
        <div style="background:#d1e7dd;padding:1.25rem;border-radius:10px;border-left:4px solid #198754;">
          <div style="display:flex;align-items:flex-start;gap:.75rem;">
            <i class="fas fa-check-circle fa-lg" style="color:#198754;flex-shrink:0;margin-top:.15rem;"></i>
            <div style="flex:1;">
              <div style="font-weight:700;color:#0f5132;font-size:1rem;">Verified & Approved ✅</div>
              <div style="font-size:.85rem;color:#0f5132;margin-top:.5rem;">
                <strong>Verified by:</strong> <?= htmlspecialchars($submission_data['ea_verified_by_name'] ?: 'Executive Assistant') ?> <br>
                <strong>Date:</strong> <?= $submission_data['ea_verified_at'] ? date('F j, Y \a\t g:i A', strtotime($submission_data['ea_verified_at'])) : '—' ?>
              </div>
              <?php if (!empty($ea_feedback)): ?>
              <div style="background:rgba(255,255,255,.6);padding:.75rem;border-radius:8px;margin-top:.75rem;border-left:3px solid #198754;">
                <div style="font-size:.78rem;font-weight:700;color:#0f5132;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.4rem;">
                  <i class="fas fa-comment-dots me-1"></i> Verification Notes
                </div>
                <div style="font-size:.9rem;color:#0f5132;line-height:1.6;">
                  <?= nl2br(htmlspecialchars($ea_feedback)) ?>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php elseif ($contract_status === 'rejected'): ?>
        <div style="background:#f8d7da;padding:1.25rem;border-radius:10px;border-left:4px solid #dc3545;">
          <div style="display:flex;align-items:flex-start;gap:.75rem;">
            <i class="fas fa-times-circle fa-lg" style="color:#dc3545;flex-shrink:0;margin-top:.15rem;"></i>
            <div style="flex:1;">
              <div style="font-weight:700;color:#842029;font-size:1rem;">Contract Rejected ❌</div>
              <div style="font-size:.85rem;color:#842029;margin-top:.5rem;">
                <strong>Rejected by:</strong> <?= htmlspecialchars($submission_data['ea_verified_by_name'] ?: 'Executive Assistant') ?> <br>
                <strong>Date:</strong> <?= $submission_data['ea_verified_at'] ? date('F j, Y \a\t g:i A', strtotime($submission_data['ea_verified_at'])) : '—' ?>
              </div>
              <?php if (!empty($ea_feedback)): ?>
              <div style="background:rgba(255,255,255,.6);padding:.75rem;border-radius:8px;margin-top:.75rem;border-left:3px solid #dc3545;">
                <div style="font-size:.78rem;font-weight:700;color:#842029;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.4rem;">
                  <i class="fas fa-comment-dots me-1"></i> Reason for Rejection
                </div>
                <div style="font-size:.9rem;color:#842029;line-height:1.6;">
                  <?= nl2br(htmlspecialchars($ea_feedback)) ?>
                </div>
              </div>
              <?php else: ?>
              <div style="background:rgba(255,255,255,.6);padding:.75rem;border-radius:8px;margin-top:.75rem;border-left:3px solid #dc3545;">
                <div style="font-size:.9rem;color:#842029;">Please contact the administrator for more information regarding this rejection.</div>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Action Buttons -->
    <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:1rem;">
      <?php if ($contract_status === 'approved'): ?>
        <?php
          // Check if booking has already been paid by anyone
          $bookingAlreadyPaid = false;
          if ($booking_id) {
              $payChk = $conn->prepare("SELECT id FROM payments WHERE booking_id = ? AND payment_status IN ('paid','verified') LIMIT 1");
              $payChk->bind_param('i', $booking_id);
              $payChk->execute();
              $bookingAlreadyPaid = (bool)$payChk->get_result()->fetch_assoc();
              $payChk->close();
          }
        ?>
        <?php if ($bookingAlreadyPaid): ?>
        <span style="background:#d1e7dd;color:#0f5132;border:none;border-radius:12px;padding:.75rem 2rem;font-weight:700;font-size:1rem;display:inline-flex;align-items:center;gap:.6rem;">
          <i class="fas fa-check-circle"></i> Payment Completed
        </span>
        <?php else: ?>
        <a href="/GAMBYTES_Final/app/views/Users/admin department/payment/pay.php?booking_id=<?= $booking_id ?>"
           style="background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;border:none;border-radius:12px;padding:.75rem 2rem;font-weight:700;font-size:1rem;text-decoration:none;display:inline-flex;align-items:center;gap:.6rem;box-shadow:0 4px 14px rgba(22,163,74,.35);">
          <i class="fas fa-credit-card"></i> Pay Now
        </a>
        <?php endif; ?>
      <?php elseif ($contract_status === 'rejected'): ?>
        <a href="/GAMBYTES_Final/app/views/Users/Gamblers/contract/fill-contract.php?booking_id=<?= $booking_id ?>&resubmit=1"
           style="background:linear-gradient(135deg,#800000,#5c0000);color:#fff;border:none;border-radius:12px;padding:.75rem 2rem;font-weight:700;font-size:1rem;text-decoration:none;display:inline-flex;align-items:center;gap:.6rem;box-shadow:0 4px 14px rgba(128,0,0,.3);">
          <i class="fas fa-redo"></i> Re-submit Contract
        </a>
      <?php endif; ?>
      <a href="/GAMBYTES_Final/app/views/auth/dashboard.php"
         style="background:#f3f4f6;color:#374151;border:1.5px solid #d1d5db;border-radius:12px;padding:.75rem 1.5rem;font-weight:600;font-size:.95rem;text-decoration:none;display:inline-flex;align-items:center;gap:.6rem;">
        <i class="fas fa-home"></i> Back to Dashboard
      </a>
    </div>

  </div>
</div>

<?php else: ?>
<div style="margin-bottom:1.75rem">
<h1 style="color:#800000;font-size:1.8rem;font-weight:800;margin:0"><i class="fas fa-file-contract me-2"></i>Rehabilitation Application</h1>
<p style="color:#6c757d;margin:.25rem 0 0">Review the policies below, then fill out and sign the contract form.</p>
</div>

<!-- ═══ SECTION 1: POLICIES & GUIDELINES ═══ -->
<div class="fc-card">
<div class="fc-card-header"><i class="fas fa-book-open"></i> Section 1 – Policies &amp; Guidelines</div>
<div class="fc-card-body">
<?php if (empty($grouped_policies)): ?>
<div style="text-align:center;padding:2rem;color:#6c757d">
<i class="fas fa-folder-open fa-2x mb-2" style="opacity:.4;display:block"></i>
<p style="font-size:.9rem">No policies have been uploaded by the supervisor yet.</p>
</div>
<?php else: ?>
<?php foreach ($grouped_policies as $category => $docs): ?>
<div style="margin-bottom:1.5rem">
<div style="font-weight:700;font-size:.88rem;color:#800000;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.75rem;padding-bottom:.4rem;border-bottom:2px solid #f0f0f0">
<i class="fas fa-folder me-1"></i><?= htmlspecialchars($category) ?>
</div>
<?php foreach ($docs as $doc): ?>
<div class="policy-item">
<div style="flex:1;min-width:0">
<div class="policy-item-title"><?= htmlspecialchars($doc['doc_title']) ?></div>
<?php if ($doc['description']): ?><div class="policy-item-meta"><?= htmlspecialchars($doc['description']) ?></div><?php endif; ?>
<div style="margin-top:.35rem">
<span class="badge-cat"><?= htmlspecialchars($doc['doc_type']) ?></span>
<span style="font-size:.75rem;color:#6c757d;margin-left:.5rem"><?= date('M j, Y', strtotime($doc['uploaded_at'])) ?></span>
</div>
</div>
<div style="display:flex;gap:.5rem;flex-shrink:0">
<a href="<?= htmlspecialchars($doc['url']) ?>" target="_blank" style="background:#fff;border:1.5px solid #0d6efd;color:#0d6efd;padding:.4rem .9rem;border-radius:8px;font-size:.82rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.35rem"><i class="fas fa-eye"></i> View</a>
<a href="<?= htmlspecialchars($doc['url']) ?>" download="<?= htmlspecialchars($doc['original_name']) ?>" style="background:#fff;border:1.5px solid #6c757d;color:#6c757d;padding:.4rem .9rem;border-radius:8px;font-size:.82rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.35rem"><i class="fas fa-download"></i> Download</a>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>

<hr class="section-divider">

<?php if ($submission_id): ?>
<!-- ═══ SECTION 2: REHAB AGREEMENT ═══ -->
<div id="rehabAgreementContent">
<div style="margin-bottom:1.25rem">
<h2 style="color:#800000;font-size:1.35rem;font-weight:800;margin:0"><i class="fas fa-file-contract me-2"></i>Section 2 – Rehab Agreement</h2>
<p style="color:#6c757d;margin:.2rem 0 0;font-size:.9rem">Review the rehabilitation agreement below.</p>
</div>

<div class="fc-card">
<div class="fc-card-body" style="padding:2rem 3rem;">

<div style="font-family:'Times New Roman', serif; font-size:12pt; line-height:1.5;">

<p style="text-align:right; margin-bottom:20pt;"><?= date('F j, Y') ?></p>

<table style="width:100%; border-collapse:collapse; margin-bottom:20pt;">
<tr>
<td style="width:150px; vertical-align:top; padding-bottom:10pt;"><strong>MEMORANDUM TO:</strong></td>
<td style="vertical-align:top; padding-bottom:10pt;">
<?= htmlspecialchars($full_name) ?><br>
Rehabilitation Participant<br>
Gambytes Recovery Program
</td>
</tr>
<tr>
<td style="width:150px; vertical-align:top; padding-bottom:10pt;"><strong>FROM:</strong></td>
<td style="vertical-align:top; padding-bottom:10pt;">
Rehabilitation Services Department<br>
Philippine Amusement and Gaming Corporation
</td>
</tr>
<tr>
<td style="width:150px; vertical-align:top; padding-bottom:10pt;"><strong>CASE:</strong></td>
<td style="vertical-align:top; padding-bottom:10pt;">Rehabilitation Agreement for Gambling Addiction Treatment</td>
</tr>
<tr>
<td style="width:150px; vertical-align:top; padding-bottom:10pt;"><strong>SUBJECT:</strong></td>
<td style="vertical-align:top; padding-bottom:10pt;">
Terms and Conditions for Participation in the<br>
Gambytes Rehabilitation Program
</td>
</tr>
</table>

<hr style="border:none; border-top:1px solid #000; margin:20pt 0;">

<h3 style="font-weight:700; margin-bottom:15pt;">SUMMARY</h3>

<p style="margin-bottom:15pt; text-align:justify;">This memorandum outlines the preliminary results of your rehabilitation assessment, submissions of treatment evaluation data and rebuttal comments, and the Department's reconsideration of your treatment plan valuation, including an error in the assessment of your gambling behavior patterns.</p>

<p style="margin-bottom:15pt; text-align:justify;">The rehabilitation program is designed to provide comprehensive treatment for gambling addiction through structured counseling, support groups, and personalized recovery planning. Your participation requires commitment to the treatment schedule and adherence to program guidelines.</p>

<h4 style="font-weight:700; margin:20pt 0 10pt;">TREATMENT PROGRAM DETAILS:</h4>

<p style="margin-bottom:10pt;"><strong>Duration:</strong> 6 months performing interventions with follow-up sessions</p>
<p style="margin-bottom:10pt;"><strong>Frequency:</strong> Per-week (individual and group therapy)</p>
<p style="margin-bottom:10pt;"><strong>Location:</strong> Rehabilitation Center</p>
<p style="margin-bottom:15pt;"><strong>Cost:</strong> Covered under PAGCOR's Responsible Gaming Program</p>

<h4 style="font-weight:700; margin:20pt 0 10pt;">PARTICIPANT RESPONSIBILITIES:</h4>

<ul style="margin-bottom:15pt; padding-left:20pt;">
<li>Attend all scheduled therapy sessions</li>
<li>Complete assigned homework and recovery tasks</li>
<li>Maintain abstinence from all gambling activities</li>
<li>Participate actively in support group meetings</li>
<li>Follow financial management guidelines</li>
<li>Submit to regular progress assessments</li>
</ul>

<h4 style="font-weight:700; margin:20pt 0 10pt;">PROGRAM BENEFITS:</h4>

<ul style="margin-bottom:15pt; padding-left:20pt;">
<li>Professional counseling and therapy services</li>
<li>Peer support and group therapy sessions</li>
<li>Financial management education</li>
<li>Family counseling and support services</li>
<li>Relapse prevention planning</li>
<li>Aftercare and follow-up support</li>
</ul>

<h4 style="font-weight:700; margin:20pt 0 10pt;">CONFIDENTIALITY AGREEMENT:</h4>

<p style="margin-bottom:15pt; text-align:justify;">All information shared during treatment sessions is strictly confidential and protected under professional ethics and privacy laws. No information will be disclosed without your written consent, except as required by law.</p>

<h4 style="font-weight:700; margin:20pt 0 10pt;">TERMINATION POLICY:</h4>

<p style="margin-bottom:15pt; text-align:justify;">Either party may terminate this agreement with 7 days written notice. Early termination may affect continued access to program benefits and support services.</p>

<div style="margin-top:30pt;">
<p style="margin-bottom:5pt;"><strong>Participant Signature:</strong></p>
<div style="display:flex; justify-content:center; margin-bottom:10px;">
<div style="border:1px solid #ccc; border-radius:5px; background-color:#fff;">
<canvas id="signaturePad" width="500" height="200" style="touch-action: none; border: 1px solid #000; cursor: crosshair; display:block;"></canvas>
</div>
</div>
<div style="text-align:center;">
<button type="button" class="btn btn-sm btn-outline-secondary" id="clearSignature">Clear Signature</button>
</div>
<p style="margin-top:15pt; margin-bottom:5pt;"><strong>Date:</strong> <input type="date" class="form-control" style="display:inline-block; width:auto; min-width:150px;" id="participantDate"></p>
</div>


<div style="text-align:center; margin-top:30pt;">
<button type="button" class="btn-maroon" id="submitRehabAgreement"><i class="fas fa-paper-plane"></i> Submit Rehab Agreement</button>
</div>

</div>

</div>
</div>
</div>

</div>
</div>
</div>

<?php endif; // end show_status_page vs form ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Notifications
function toggleNotifDropdown(){const dd=document.getElementById('notifDropdown');dd.classList.toggle('open');if(dd.classList.contains('open'))loadNotifs();}
document.addEventListener('click',e=>{const w=document.getElementById('notifWrap');if(w&&!w.contains(e.target))document.getElementById('notifDropdown').classList.remove('open');});
function goNotif(link){if(link&&link!=='#')window.location.href=link;}
function loadNotifs(){fetch('/GAMBYTES_Final/api/notifications.php?action=list').then(r=>r.json()).then(d=>{const l=document.getElementById('notifList');if(!d.items||!d.items.length){l.innerHTML='<div class="notif-empty">No notifications</div>';return;}l.innerHTML=d.items.map(n=>`<div class="notif-item" style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;padding:.75rem 1rem;border-bottom:1px solid #f0f0f0"><div class="notif-content" style="flex:1;cursor:pointer" onclick="goNotif(${JSON.stringify(n.link||'')})">${n.link?'<i class="fas fa-external-link-alt me-1" style="color:#800000;font-size:.7rem"></i>':''}<strong>${escHtml(n.title)}</strong><span style="display:block;color:#6c757d;font-size:.78rem">${escHtml(n.message||'')}</span></div><button class="notif-delete" onclick="deleteNotif(${n.id}, event)" style="flex-shrink:0;background:none;border:none;color:#dc3545;cursor:pointer;font-size:.9rem;padding:.25rem .5rem;border-radius:4px" title="Delete"><i class="fas fa-times"></i></button></div>`).join('');});}
function deleteNotif(notifId,event){event.stopPropagation();if(!confirm('Are you sure you want to delete this notification?'))return;fetch('/GAMBYTES_Final/api/notifications.php?action=delete&id='+notifId).then(r=>r.json()).then(d=>{if(d.success){loadNotifs();pollNotif();}else alert('Error deleting notification: '+(d.message||'Unknown error'));});}
function pollNotif(){fetch('/GAMBYTES_Final/api/notifications.php?action=count').then(r=>r.json()).then(d=>{const b=document.getElementById('notifBadge');if(d.count>0){b.textContent=d.count;b.style.display='flex';}else b.style.display='none';});}
pollNotif(); setInterval(pollNotif,30000);

function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// SHA-256 hashing function for client-side verification
async function sha256(message) {
    const msgBuffer = new TextEncoder().encode(message);
    const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    return hashHex;
}

// Custom signature implementation like sign.php
let signatureCtx = null;
let drawing = false;
let lastX = 0;
let lastY = 0;

document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('signaturePad');
    if (canvas) {
        // Set canvas size properly
        canvas.width = 200;
        canvas.height = 200;
        
        signatureCtx = canvas.getContext('2d');
        
        // Clear signature button
        document.getElementById('clearSignature').addEventListener('click', function() {
            clearSignature();
        });
        
        // Mouse events
        canvas.addEventListener('mousedown', function(e) {
            drawing = true;
            const rect = canvas.getBoundingClientRect();
            lastX = e.clientX - rect.left;
            lastY = e.clientY - rect.top;
        });
        
        canvas.addEventListener('mousemove', function(e) {
            if (!drawing) return;
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            signatureCtx.beginPath();
            signatureCtx.moveTo(lastX, lastY);
            signatureCtx.lineTo(x, y);
            signatureCtx.strokeStyle = '#000';
            signatureCtx.lineWidth = 2;
            signatureCtx.lineCap = 'round';
            signatureCtx.stroke();
            
            lastX = x;
            lastY = y;
        });
        
        canvas.addEventListener('mouseup', function() {
            drawing = false;
        });
        
        canvas.addEventListener('mouseleave', function() {
            drawing = false;
        });
        
        // Touch events for mobile
        canvas.addEventListener('touchstart', function(e) {
            e.preventDefault();
            drawing = true;
            const rect = canvas.getBoundingClientRect();
            const touch = e.touches[0];
            lastX = touch.clientX - rect.left;
            lastY = touch.clientY - rect.top;
        }, {passive: false});
        
        canvas.addEventListener('touchmove', function(e) {
            e.preventDefault();
            if (!drawing) return;
            const rect = canvas.getBoundingClientRect();
            const touch = e.touches[0];
            const x = touch.clientX - rect.left;
            const y = touch.clientY - rect.top;
            
            signatureCtx.beginPath();
            signatureCtx.moveTo(lastX, lastY);
            signatureCtx.lineTo(x, y);
            signatureCtx.strokeStyle = '#000';
            signatureCtx.lineWidth = 2;
            signatureCtx.lineCap = 'round';
            signatureCtx.stroke();
            
            lastX = x;
            lastY = y;
        }, {passive: false});
        
        canvas.addEventListener('touchend', function() {
            drawing = false;
        });
    }
    
    // Set today's date as default for participant date
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById('participantDate');
    if (dateInput) {
        dateInput.value = today;
    }
});

function clearSignature() {
    const canvas = document.getElementById('signaturePad');
    if (canvas && signatureCtx) {
        signatureCtx.clearRect(0, 0, canvas.width, canvas.height);
    }
}

function isSignatureEmpty() {
    const canvas = document.getElementById('signaturePad');
    if (!canvas || !signatureCtx) return true;
    const data = signatureCtx.getImageData(0, 0, canvas.width, canvas.height).data;
    for (let i = 3; i < data.length; i += 4) {
        if (data[i] > 0) return false;
    }
    return true;
}

// Rehab Agreement Submission
document.getElementById('submitRehabAgreement').addEventListener('click', function() {
    const participantDate = document.getElementById('participantDate').value;
    
    // Check if signature is empty
    if (isSignatureEmpty()) {
        alert('Please provide your signature before submitting.');
        return;
    }
    
    if (!participantDate) {
        alert('Please select the signing date.');
        return;
    }
    
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    
    // Get signature data
    const canvas = document.getElementById('signaturePad');
    const signatureData = canvas.toDataURL();
    
    // Save to database
    const formData = new FormData();
    formData.append('participant_date', participantDate);
    formData.append('signature_data', signatureData);
    
    fetch('/GAMBYTES_Final/api/save_rehab_agreement.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Rehab Agreement submitted successfully! Your digital signature has been securely hashed and recorded.');
            window.location.href = '/GAMBYTES_Final/app/views/auth/dashboard.php';
        } else {
            alert('Error: ' + (data.message || 'Failed to save rehab agreement'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error. Please try again.');
    })
    .finally(() => {
        // Re-enable button
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Rehab Agreement';
    });
});
</script>
<?php endif; ?>
</body>
</html>