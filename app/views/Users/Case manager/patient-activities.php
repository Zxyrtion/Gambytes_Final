<?php
require_once __DIR__ . '/../../../../includes/session_config.php';
require_once __DIR__ . '/../../../../includes/url_helper.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . url('app/views/auth/login.php')); exit(); }
require_once __DIR__ . '/../../../core/Database.php';
$db = new Database(); $conn = $db->connect();

$user_id = (int)$_SESSION['user_id'];
$uStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->bind_param('i', $user_id); $uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc(); $uStmt->close();

if (!$user || $user['role'] !== 'case_manager') {
    header("Location: " . url('app/views/auth/dashboard.php')); exit();
}
$full_name = $user['first_name'] . ' ' . $user['last_name'];

$booking_id = (int)($_GET['booking_id'] ?? 0);
if (!$booking_id) { header("Location: /GAMBYTES_Final/app/views/Users/Case manager/my-patients.php"); exit(); }

// Load booking + patient info
$bStmt = $conn->prepare(
    "SELECT br.*, u.id AS gambler_id, u.first_name, u.last_name
     FROM booking_record br
     LEFT JOIN users u ON LOWER(u.email) COLLATE utf8mb4_unicode_ci = LOWER(br.email) COLLATE utf8mb4_unicode_ci AND u.role = 'gambler'
     WHERE br.id = ?
     LIMIT 1"
);
$bStmt->bind_param('i', $booking_id); $bStmt->execute();
$booking = $bStmt->get_result()->fetch_assoc(); $bStmt->close();
if (!$booking) { header("Location: /GAMBYTES_Final/app/views/Users/Case manager/my-patients.php"); exit(); }

$gambler_id   = (int)($booking['gambler_id'] ?? 0);
$patient_name = trim(($booking['first_name'] ?? '') . ' ' . ($booking['last_name'] ?? ''));
if (!$patient_name) $patient_name = htmlspecialchars($booking['name'] ?? 'Unknown');
$patient_email = $booking['email'] ?? '';

// Load initial interview
$iStmt = $conn->prepare("SELECT * FROM Initial_Interview_Record WHERE booking_id = ? LIMIT 1");
$iStmt->bind_param('i', $booking_id); $iStmt->execute();
$interview = $iStmt->get_result()->fetch_assoc(); $iStmt->close();

// Load payment status
$pStmt = $conn->prepare(
    "SELECT payment_status FROM payments WHERE booking_id = ? AND payment_status IN ('paid','verified') LIMIT 1"
);
$pStmt->bind_param('i', $booking_id); $pStmt->execute();
$payRow = $pStmt->get_result()->fetch_assoc(); $pStmt->close();
$payment_status = $payRow['payment_status'] ?? 'pending';

// Load treatment activities
$aStmt = $conn->prepare(
    "SELECT ia.*,
            (SELECT COUNT(*) FROM activity_submissions WHERE activity_id = ia.id) AS submission_count
     FROM interventions_assessments ia
     WHERE ia.booking_id = ?
     ORDER BY ia.created_at DESC"
);
$aStmt->bind_param('i', $booking_id); $aStmt->execute();
$activities = $aStmt->get_result()->fetch_all(MYSQLI_ASSOC); $aStmt->close();

$today = date('Y-m-d');

function activityStatusBadge($open, $close, $today) {
    if ($today < $open)  return ['label' => 'Not Open Yet', 'bg' => '#e2e3e5', 'color' => '#41464b'];
    if ($today <= $close) return ['label' => 'Open',        'bg' => '#d1e7dd', 'color' => '#0f5132'];
    return                       ['label' => 'Closed',      'bg' => '#f8d7da', 'color' => '#842029'];
}

function severityBadge($score) {
    if ($score >= 8) return ['label' => 'Severe',   'bg' => '#f8d7da', 'color' => '#842029'];
    if ($score >= 6) return ['label' => 'Moderate', 'bg' => '#fff3cd', 'color' => '#664d03'];
    if ($score >= 4) return ['label' => 'Mild',     'bg' => '#d1e7dd', 'color' => '#0f5132'];
    return                  ['label' => 'At-Risk',  'bg' => '#e2e3e5', 'color' => '#41464b'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Activities &ndash; Gambytes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet">
<link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
<style>
.main-content{margin-left:260px;flex:1;padding:2rem}
.fc-card{background:#fff;border-radius:16px;box-shadow:0 2px 15px rgba(0,0,0,.08);overflow:hidden;margin-bottom:1.5rem}
.fc-card-header{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;padding:1rem 1.5rem;font-weight:700;font-size:1rem;display:flex;align-items:center;gap:.6rem}
.fc-card-body{padding:1.5rem}
.top-navbar{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.08);padding:1rem 1.5rem;margin-bottom:2rem;display:flex;justify-content:space-between;align-items:center}
.btn-maroon{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;border:none;border-radius:10px;padding:.55rem 1.25rem;font-weight:700;font-size:.88rem;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;text-decoration:none;transition:all .2s}
.btn-maroon:hover{opacity:.88;color:#fff}
.info-box{padding:.6rem .85rem;background:#f8f9fa;border-radius:8px;border-left:3px solid #800000}
.info-box .lbl{font-size:.7rem;color:#6c757d;font-weight:600;text-transform:uppercase}
.info-box .val{font-weight:700;color:#212529;font-size:.88rem;margin-top:1px}
.activity-row{border:1.5px solid #e9ecef;border-radius:12px;padding:1.25rem 1.5rem;margin-bottom:1rem;background:#fff;transition:box-shadow .2s}
.activity-row:hover{box-shadow:0 4px 16px rgba(0,0,0,.08)}
.notif-bell-wrap{position:relative}
.notif-bell-btn{background:linear-gradient(135deg,#800000,#5c0000);border:none;color:#fff;width:40px;height:40px;border-radius:10px;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(128,0,0,.3)}
.notif-badge{position:absolute;top:-6px;right:-6px;background:#ffc107;color:#000;font-size:.65rem;font-weight:800;min-width:18px;height:18px;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px;border:2px solid #fff}
.notif-dropdown{display:none;position:absolute;right:0;top:calc(100% + 8px);width:300px;background:#fff;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.15);z-index:9999;overflow:hidden}
.notif-dropdown.open{display:block}
.notif-dropdown-header{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;padding:.75rem 1rem;display:flex;justify-content:space-between;align-items:center;font-weight:700;font-size:.9rem}
.notif-mark-btn{background:rgba(255,255,255,.2);border:none;color:#fff;font-size:.75rem;padding:.25rem .6rem;border-radius:6px;cursor:pointer}
.notif-item{padding:.75rem 1rem;border-bottom:1px solid #f0f0f0;font-size:.85rem;cursor:pointer}
.notif-item:last-child{border-bottom:none}
.notif-item strong{display:block;color:#343a40}
.notif-item span{color:#6c757d;font-size:.78rem}
.notif-empty{padding:1.5rem 1rem;text-align:center;color:#6c757d;font-size:.85rem}
/* Fix modal z-index stacking */
.modal-backdrop{z-index:1040!important}
.modal{z-index:1050!important}
.modal-dialog{z-index:1060!important;position:relative}
.modal-content{position:relative;z-index:1070!important;background:#fff!important}
</style>
</head>
<body>
<div class="dashboard-container">

<!-- Sidebar -->
<div class="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo"><img src="/GAMBYTES_Final/public/images/Logo.png" alt="Logo"><span>Gambytes</span></div>
    <div class="sidebar-user">
      <div class="user-name"><i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($full_name); ?></div>
      <div class="user-role">Case Manager</div>
    </div>
  </div>
  <ul class="sidebar-menu">
    <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
    <li><a href="/GAMBYTES_Final/app/views/Users/Case manager/my-patients.php"><i class="fas fa-users"></i> My Patients</a></li>
    <li><a href="#"><i class="fas fa-chart-line"></i> Treatment Progress</a></li>
    <li><a href="#"><i class="fas fa-calendar-alt"></i> Schedule</a></li>
    <li><a href="#"><i class="fas fa-file-medical"></i> Reports</a></li>
    <div class="menu-divider"></div>
    <li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
    <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
  </ul>
</div>

<!-- Main Content -->
<div class="main-content">

  <!-- Navbar -->
  <div class="top-navbar">
    <span style="font-weight:700;font-size:1rem;color:#800000">Case Manager Portal</span>
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
  <div style="margin-bottom:1.75rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
    <div>
      <a href="/GAMBYTES_Final/app/views/Users/Case manager/my-patients.php" style="color:#800000;font-size:.88rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;margin-bottom:.5rem">
        <i class="fas fa-arrow-left"></i> Back to My Patients
      </a>
      <h1 style="color:#800000;font-size:1.8rem;font-weight:800;margin:0"><i class="fas fa-tasks me-2"></i>Treatment Activities</h1>
      <p style="color:#6c757d;margin:.25rem 0 0">Manage activities assigned to this patient.</p>
    </div>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
      <a href="/GAMBYTES_Final/app/views/Users/Case manager/cbt-sessions.php?booking_id=<?= $booking_id ?>" class="btn-maroon" style="background:linear-gradient(135deg,#5c0000,#3a0000)">
        <i class="fas fa-brain"></i> CBT Sessions
      </a>
      <button class="btn-maroon" data-bs-toggle="modal" data-bs-target="#addActivityModal">
        <i class="fas fa-plus"></i> Add Activity
      </button>
    </div>
  </div>


  <!-- Patient Info Card -->
  <div class="fc-card">
    <div class="fc-card-header"><i class="fas fa-user-injured"></i> Patient Information</div>
    <div class="fc-card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem">
        <div class="info-box">
          <div class="lbl">Patient Name</div>
          <div class="val"><?php echo htmlspecialchars($patient_name); ?></div>
        </div>
        <div class="info-box">
          <div class="lbl">Email</div>
          <div class="val" style="font-size:.78rem"><?php echo htmlspecialchars($patient_email); ?></div>
        </div>
        <?php if ($interview): ?>
        <div class="info-box">
          <div class="lbl">Interview Score</div>
          <div class="val">
            <?php $sev = severityBadge($interview['score']); ?>
            <span style="background:<?php echo $sev['bg']; ?>;color:<?php echo $sev['color']; ?>;padding:.2rem .65rem;border-radius:20px;font-size:.78rem;font-weight:700">
              <?php echo (int)$interview['score']; ?>/9 &ndash; <?php echo $sev['label']; ?>
            </span>
          </div>
        </div>
        <div class="info-box">
          <div class="lbl">Diagnosis</div>
          <div class="val" style="font-size:.78rem"><?php echo htmlspecialchars($interview['diagnosis'] ?? '—'); ?></div>
        </div>
        <?php endif; ?>
        <div class="info-box">
          <div class="lbl">Payment Status</div>
          <div class="val">
            <?php if ($payment_status !== 'pending'): ?>
            <span style="background:#d1e7dd;color:#0f5132;padding:.2rem .65rem;border-radius:20px;font-size:.78rem;font-weight:700">
              <i class="fas fa-check-circle me-1"></i><?php echo ucfirst($payment_status); ?>
            </span>
            <?php else: ?>
            <span style="background:#fff3cd;color:#664d03;padding:.2rem .65rem;border-radius:20px;font-size:.78rem;font-weight:700">
              <i class="fas fa-clock me-1"></i>Pending
            </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Activities List -->
  <div class="fc-card">
    <div class="fc-card-header"><i class="fas fa-clipboard-list"></i> Assigned Activities</div>
    <div class="fc-card-body">
      <?php if (empty($activities)): ?>
      <div style="text-align:center;padding:3rem;color:#6c757d">
        <i class="fas fa-tasks fa-3x mb-3" style="opacity:.3;display:block"></i>
        <h5>No activities assigned yet</h5>
        <p style="font-size:.9rem">Click "Add Activity" to create a new treatment activity for this patient.</p>
      </div>
      <?php else: ?>
      <div id="activitiesList">
        <?php foreach ($activities as $act):
          $status = activityStatusBadge($act['open_date'], $act['close_date'], $today);
          $canDelete = (int)$act['submission_count'] === 0;
        ?>
        <div class="activity-row">
          <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:.75rem;flex-wrap:wrap;gap:.5rem">
            <div style="flex:1">
              <h5 style="color:#800000;font-weight:700;margin:0 0 .25rem;font-size:1rem"><?php echo htmlspecialchars($act['title']); ?></h5>
              <p style="color:#6c757d;margin:0;font-size:.85rem"><?php echo nl2br(htmlspecialchars($act['description'] ?? '')); ?></p>
            </div>
            <span style="background:<?php echo $status['bg']; ?>;color:<?php echo $status['color']; ?>;padding:.35rem .75rem;border-radius:20px;font-size:.78rem;font-weight:700;white-space:nowrap">
              <?php echo $status['label']; ?>
            </span>
          </div>

          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.5rem;margin-bottom:.75rem">
            <div style="font-size:.82rem;color:#6c757d">
              <i class="fas fa-calendar-alt me-1" style="color:#800000"></i>
              <strong>Open:</strong> <?php echo date('M j, Y', strtotime($act['open_date'])); ?>
            </div>
            <div style="font-size:.82rem;color:#6c757d">
              <i class="fas fa-calendar-times me-1" style="color:#800000"></i>
              <strong>Close:</strong> <?php echo date('M j, Y', strtotime($act['close_date'])); ?>
            </div>
            <?php if (!empty($act['document_path'])): ?>
            <div style="font-size:.82rem;color:#6c757d">
              <i class="fas fa-file-alt me-1" style="color:#800000"></i>
              <a href="<?php echo htmlspecialchars($act['document_path']); ?>" target="_blank" style="color:#800000;font-weight:600;text-decoration:none">
                <?php echo htmlspecialchars($act['document_name'] ?? 'Download'); ?>
              </a>
            </div>
            <?php endif; ?>
            <div style="font-size:.82rem;color:#6c757d">
              <i class="fas fa-paper-plane me-1" style="color:#800000"></i>
              <strong>Submissions:</strong>
              <span style="background:#0d6efd;color:#fff;padding:.15rem .5rem;border-radius:12px;font-size:.75rem;font-weight:700;margin-left:.25rem">
                <?php echo (int)$act['submission_count']; ?>
              </span>
            </div>
          </div>

          <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            <?php if (($act['status'] ?? 'pending') === 'opened'): ?>
            <span style="background:#d1e7dd;color:#0f5132;padding:.35rem .75rem;border-radius:20px;font-size:.78rem;font-weight:700;display:inline-flex;align-items:center;gap:.4rem">
              <i class="fas fa-check-circle"></i> Opened
            </span>
            <?php else: ?>
            <button onclick="openActivity(<?php echo (int)$act['id']; ?>)" style="background:linear-gradient(135deg,#1a6e3c,#0f5132);color:#fff;border:none;border-radius:10px;padding:.4rem .9rem;font-weight:700;font-size:.82rem;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem">
              <i class="fas fa-folder-open"></i> Open Activity
            </button>
            <?php endif; ?>
            <button class="btn-maroon" style="font-size:.82rem;padding:.4rem .9rem" onclick="viewSubmissions(<?php echo (int)$act['id']; ?>)">
              <i class="fas fa-eye"></i> View Submissions
            </button>
            <?php if ($canDelete): ?>
            <button onclick="deleteActivity(<?php echo (int)$act['id']; ?>)" style="background:#dc3545;color:#fff;border:none;border-radius:10px;padding:.4rem .9rem;font-weight:600;font-size:.82rem;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem">
              <i class="fas fa-trash"></i> Delete
            </button>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /main-content -->
</div><!-- /dashboard-container -->


<!-- Add Activity Modal -->
<div class="modal fade" id="addActivityModal" tabindex="-1" aria-labelledby="addActivityModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:linear-gradient(135deg,#800000,#5c0000);color:#fff;border:none">
        <h5 class="modal-title" id="addActivityModalLabel"><i class="fas fa-plus-circle me-2"></i>Add New Activity</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding:1.5rem">
        <div id="addActivityAlert" style="display:none" class="alert"></div>
        <form id="addActivityForm" enctype="multipart/form-data">
          <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
          <input type="hidden" name="gambler_id" value="<?php echo $gambler_id; ?>">

          <div class="mb-3">
            <label class="form-label fw-600" style="font-weight:600;color:#343a40">Title <span style="color:#dc3545">*</span></label>
            <input type="text" name="title" class="form-control" placeholder="e.g. Week 1 Reflection Journal" required style="border-radius:10px;border:1.5px solid #dee2e6">
          </div>

          <div class="mb-3">
            <label class="form-label" style="font-weight:600;color:#343a40">Description</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Describe what the patient needs to do..." style="border-radius:10px;border:1.5px solid #dee2e6"></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label" style="font-weight:600;color:#343a40">Reference Document <span style="color:#6c757d;font-weight:400">(optional)</span></label>
            <input type="file" name="document" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="border-radius:10px;border:1.5px solid #dee2e6">
            <div class="form-text">Accepted: PDF, DOC, DOCX, JPG, PNG</div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label" style="font-weight:600;color:#343a40">Open Date <span style="color:#dc3545">*</span></label>
              <input type="date" name="open_date" class="form-control" required style="border-radius:10px;border:1.5px solid #dee2e6">
            </div>
            <div class="col-md-6">
              <label class="form-label" style="font-weight:600;color:#343a40">Close Date <span style="color:#dc3545">*</span></label>
              <input type="date" name="close_date" class="form-control" required style="border-radius:10px;border:1.5px solid #dee2e6">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer" style="border:none;padding:1rem 1.5rem">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:10px">Cancel</button>
        <button type="button" class="btn-maroon" id="submitActivityBtn" onclick="submitActivity()">
          <i class="fas fa-save"></i> Save Activity
        </button>
      </div>
    </div>
  </div>
</div>

<!-- View Submissions Modal -->
<div class="modal fade" id="submissionsModal" tabindex="-1" aria-labelledby="submissionsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:linear-gradient(135deg,#800000,#5c0000);color:#fff;border:none">
        <h5 class="modal-title" id="submissionsModalLabel"><i class="fas fa-inbox me-2"></i>Activity Submissions</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding:1.5rem">
        <div id="submissionsContent">
          <div style="text-align:center;padding:2rem;color:#6c757d">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p class="mt-2">Loading submissions...</p>
          </div>
        </div>
      </div>
      <div class="modal-footer" style="border:none">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:10px">Close</button>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Notification helpers ──────────────────────────────────────────────────────
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

function goNotif(link){ if(link && link !== '#') window.location.href = link; }

function loadNotifs(){
  fetch('/GAMBYTES_Final/api/notifications.php?action=list')
    .then(r => r.json())
    .then(d => {
      const l = document.getElementById('notifList');
      if (!d.items || !d.items.length) {
        l.innerHTML = '<div class="notif-empty">No notifications</div>';
        return;
      }
      l.innerHTML = d.items.map(n =>
        `<div class="notif-item" onclick="goNotif(${JSON.stringify(n.link||'')})">
          ${n.link ? '<i class="fas fa-external-link-alt me-1" style="color:#800000;font-size:.7rem"></i>' : ''}
          <strong>${escHtml(n.title)}</strong>
          <span>${escHtml(n.message||'')}</span>
        </div>`
      ).join('');
    });
}

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

// ── Open Activity ─────────────────────────────────────────────────────────────
function openActivity(activityId){
  if (!confirm('Mark this activity as opened? The gambler will be notified.')) return;

  fetch('/GAMBYTES_Final/api/case_manager.php?action=open_activity', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ activity_id: activityId })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) location.reload();
    else alert(d.message || 'Failed to open activity.');
  })
  .catch(() => alert('Network error. Please try again.'));
}

// ── Add Activity ──────────────────────────────────────────────────────────────
function submitActivity(){
  const form = document.getElementById('addActivityForm');
  const alertEl = document.getElementById('addActivityAlert');
  const btn = document.getElementById('submitActivityBtn');

  alertEl.style.display = 'none';

  const title = form.querySelector('[name="title"]').value.trim();
  const openDate = form.querySelector('[name="open_date"]').value;
  const closeDate = form.querySelector('[name="close_date"]').value;

  if (!title || !openDate || !closeDate) {
    alertEl.className = 'alert alert-danger';
    alertEl.textContent = 'Please fill in all required fields.';
    alertEl.style.display = 'block';
    return;
  }
  if (closeDate < openDate) {
    alertEl.className = 'alert alert-danger';
    alertEl.textContent = 'Close date must be on or after the open date.';
    alertEl.style.display = 'block';
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  const fd = new FormData(form);

  fetch('/GAMBYTES_Final/api/case_manager.php?action=create_activity', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      alertEl.className = 'alert alert-success';
      alertEl.textContent = 'Activity created successfully! Reloading...';
      alertEl.style.display = 'block';
      setTimeout(() => location.reload(), 1200);
    } else {
      alertEl.className = 'alert alert-danger';
      alertEl.textContent = d.message || 'Failed to create activity.';
      alertEl.style.display = 'block';
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save"></i> Save Activity';
    }
  })
  .catch(() => {
    alertEl.className = 'alert alert-danger';
    alertEl.textContent = 'Network error. Please try again.';
    alertEl.style.display = 'block';
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save"></i> Save Activity';
  });
}

// ── View Submissions ──────────────────────────────────────────────────────────
function viewSubmissions(activityId){
  const modal = new bootstrap.Modal(document.getElementById('submissionsModal'));
  const content = document.getElementById('submissionsContent');
  content.innerHTML = '<div style="text-align:center;padding:2rem;color:#6c757d"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading submissions...</p></div>';
  modal.show();

  fetch('/GAMBYTES_Final/api/case_manager.php?action=get_submissions&activity_id=' + activityId)
    .then(r => r.json())
    .then(d => {
      if (!d.success) {
        content.innerHTML = '<div class="alert alert-danger">Failed to load submissions.</div>';
        return;
      }
      if (!d.submissions || d.submissions.length === 0) {
        content.innerHTML = '<div style="text-align:center;padding:2rem;color:#6c757d"><i class="fas fa-inbox fa-3x mb-3" style="opacity:.3;display:block"></i><h6>No submissions yet</h6><p style="font-size:.88rem">The patient has not submitted this activity yet.</p></div>';
        return;
      }
      let html = '<div style="display:flex;flex-direction:column;gap:.75rem">';
      d.submissions.forEach((s, i) => {
        html += `
          <div style="border:1.5px solid #e9ecef;border-radius:12px;padding:1rem 1.25rem;background:#f8f9fa">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:.5rem;flex-wrap:wrap;gap:.5rem">
              <strong style="color:#343a40;font-size:.9rem"><i class="fas fa-user me-1" style="color:#800000"></i>${escHtml(s.gambler_name || 'Patient')}</strong>
              <span style="font-size:.78rem;color:#6c757d"><i class="fas fa-clock me-1"></i>${escHtml(s.submitted_at)}</span>
            </div>
            ${s.notes ? `<p style="font-size:.85rem;color:#495057;margin:.5rem 0;background:#fff;border-radius:8px;padding:.6rem .85rem;border-left:3px solid #800000">${escHtml(s.notes)}</p>` : ''}
            ${s.file_path ? `<a href="${escHtml(s.file_path)}" target="_blank" style="display:inline-flex;align-items:center;gap:.4rem;color:#800000;font-weight:600;font-size:.85rem;text-decoration:none;background:#fff;border:1.5px solid #800000;border-radius:8px;padding:.35rem .75rem;margin-top:.25rem"><i class="fas fa-download"></i>${escHtml(s.file_name || 'Download File')}</a>` : '<span style="font-size:.82rem;color:#6c757d;font-style:italic">No file attached</span>'}
          </div>`;
      });
      html += '</div>';
      content.innerHTML = html;
    })
    .catch(() => {
      content.innerHTML = '<div class="alert alert-danger">Network error loading submissions.</div>';
    });
}

// ── Delete Activity ───────────────────────────────────────────────────────────
function deleteActivity(activityId){
  if (!confirm('Delete this activity? This action cannot be undone.')) return;

  fetch('/GAMBYTES_Final/api/case_manager.php?action=delete_activity', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ activity_id: activityId })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) location.reload();
    else alert(d.message || 'Failed to delete activity.');
  })
  .catch(() => alert('Network error. Please try again.'));
}
</script>
</body>
</html>