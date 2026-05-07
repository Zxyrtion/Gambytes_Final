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

if (!$user || $user['role'] !== 'gambler') {
    header("Location: " . url('app/views/auth/dashboard.php')); exit();
}
$full_name = $user['first_name'] . ' ' . $user['last_name'];

$activity_id = (int)($_GET['activity_id'] ?? 0);
if (!$activity_id) { header("Location: /GAMBYTES_Final/app/views/Users/Gamblers/my-activities.php"); exit(); }

// Load activity (must belong to this gambler and be opened)
$aStmt = $conn->prepare(
    "SELECT ia.*, CONCAT(u.first_name,' ',u.last_name) AS case_manager_name
     FROM interventions_assessments ia
     JOIN users u ON u.id = ia.created_by
     WHERE ia.id = ? AND ia.gambler_id = ?
     LIMIT 1"
);
$aStmt->bind_param('ii', $activity_id, $user_id); $aStmt->execute();
$activity = $aStmt->get_result()->fetch_assoc(); $aStmt->close();

if (!$activity) { header("Location: /GAMBYTES_Final/app/views/Users/Gamblers/my-activities.php"); exit(); }

// Check if already submitted
$subStmt = $conn->prepare("SELECT * FROM activity_submissions WHERE activity_id = ? AND gambler_id = ? LIMIT 1");
$subStmt->bind_param('ii', $activity_id, $user_id); $subStmt->execute();
$submission = $subStmt->get_result()->fetch_assoc(); $subStmt->close();

$today = date('Y-m-d');
$isOpen = ($today >= $activity['open_date'] && $today <= $activity['close_date']);
$isClosed = ($today > $activity['close_date']);
$isNotOpenYet = ($today < $activity['open_date']);

function activityStatusBadge($open, $close, $today) {
    if ($today < $open)  return ['label' => 'Not Open Yet', 'bg' => '#e2e3e5', 'color' => '#41464b'];
    if ($today <= $close) return ['label' => 'Open',        'bg' => '#d1e7dd', 'color' => '#0f5132'];
    return                       ['label' => 'Closed',      'bg' => '#f8d7da', 'color' => '#842029'];
}
$statusBadge = activityStatusBadge($activity['open_date'], $activity['close_date'], $today);

// Detect which CBT session this activity belongs to based on title
function detectCbtSession($title) {
    $t = strtolower($title);
    if (str_contains($t, 'session 1') || str_contains($t, 'assessment')) return 1;
    if (str_contains($t, 'session 2') || str_contains($t, 'consequences')) return 2;
    if (str_contains($t, 'session 3') || str_contains($t, 'hard to stop') || str_contains($t, 'distorted')) return 3;
    if (str_contains($t, 'session 4') || str_contains($t, 'urges') || str_contains($t, 'triggers')) return 4;
    if (str_contains($t, 'session 5') || str_contains($t, 'lifestyle')) return 5;
    if (str_contains($t, 'session 6') || str_contains($t, 'relapse') || str_contains($t, 'preventing')) return 6;
    return 0;
}
$cbtSession = detectCbtSession($activity['title']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($activity['title']); ?> &ndash; Gambytes</title>
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
.description-box{background:#f8f9fa;border-radius:12px;padding:1.25rem 1.5rem;border-left:4px solid #800000;font-size:.95rem;color:#343a40;line-height:1.7;white-space:pre-wrap}
.cbt-section{border:1.5px solid #e9ecef;border-radius:12px;padding:1.25rem 1.5rem;margin-bottom:1.25rem;background:#fafafa}
.cbt-section-title{font-weight:700;color:#800000;font-size:.95rem;margin-bottom:.75rem;padding-bottom:.5rem;border-bottom:2px solid #f0e0e0}
.cbt-intro{background:#fff3cd;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.25rem;border-left:4px solid #ffc107;font-size:.9rem}
.cbt-intro ul{margin:.5rem 0 0;padding-left:1.25rem}
.cbt-label{font-weight:600;color:#343a40;font-size:.88rem;margin-bottom:.35rem;display:block}
.cbt-q{font-size:.9rem;color:#495057;margin-bottom:.75rem}
.cbt-note{font-size:.85rem;color:#6c757d;font-style:italic;margin-bottom:.75rem}
.cbt-input{font-size:.88rem;border-radius:8px}
.cbt-table{font-size:.85rem}
.cbt-table th{background:#f8f9fa;font-weight:700;color:#343a40}
.cbt-check{padding:.3rem .5rem;border-radius:6px}
.cbt-check:hover{background:#f8f9fa}
.cbt-area-label{font-weight:700;color:#800000;font-size:.9rem;margin-bottom:.5rem;padding:.3rem .6rem;background:#f8e8e8;border-radius:6px;display:inline-block}
.cbt-steps{font-size:.9rem;color:#343a40;line-height:1.8}
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
      <div class="user-role">Gambler</div>
    </div>
  </div>
  <ul class="sidebar-menu">
    <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
    <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/BookRehab.php"><i class="fas fa-calendar-plus"></i> Book Rehabilitation</a></li>
    <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php"><i class="fas fa-clipboard-list"></i> My Interview</a></li>
    <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php"><i class="fas fa-file-contract"></i> My Contracts</a></li>
    <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-cbt-sessions.php"><i class="fas fa-brain"></i> CBT Sessions</a></li>
    <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-activities.php" class="active"><i class="fas fa-tasks"></i> My Activities</a></li>
    <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/parental-control-requests.php"><i class="fas fa-shield-alt"></i> Parental Access</a></li>
    <li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
    <div class="menu-divider"></div>
    <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
  </ul>
</div>

<!-- Main Content -->
<div class="main-content">

  <!-- Navbar -->
  <div class="top-navbar">
    <span style="font-weight:700;font-size:1rem;color:#800000">Gambler Portal</span>
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
    <a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-activities.php" style="color:#800000;font-size:.88rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;margin-bottom:.5rem">
      <i class="fas fa-arrow-left"></i> Back to My Activities
    </a>
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
      <div>
        <h1 style="color:#800000;font-size:1.8rem;font-weight:800;margin:0">
          <i class="fas fa-tasks me-2"></i><?php echo htmlspecialchars($activity['title']); ?>
        </h1>
        <p style="color:#6c757d;margin:.25rem 0 0">Assigned by <?php echo htmlspecialchars($activity['case_manager_name']); ?></p>
      </div>
      <span style="background:<?php echo $statusBadge['bg']; ?>;color:<?php echo $statusBadge['color']; ?>;padding:.45rem 1rem;border-radius:20px;font-size:.85rem;font-weight:700">
        <?php echo $statusBadge['label']; ?>
      </span>
    </div>
  </div>

  <!-- Activity Details -->
  <div class="fc-card">
    <div class="fc-card-header"><i class="fas fa-info-circle"></i> Activity Details</div>
    <div class="fc-card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;margin-bottom:1.25rem">
        <div class="info-box">
          <div class="lbl">Case Manager</div>
          <div class="val"><?php echo htmlspecialchars($activity['case_manager_name']); ?></div>
        </div>
        <div class="info-box">
          <div class="lbl">Open Date</div>
          <div class="val"><?php echo date('M j, Y', strtotime($activity['open_date'])); ?></div>
        </div>
        <div class="info-box">
          <div class="lbl">Close Date</div>
          <div class="val"><?php echo date('M j, Y', strtotime($activity['close_date'])); ?></div>
        </div>
        <?php if (!empty($activity['opened_at'])): ?>
        <div class="info-box">
          <div class="lbl">Opened On</div>
          <div class="val"><?php echo date('M j, Y g:i A', strtotime($activity['opened_at'])); ?></div>
        </div>
        <?php endif; ?>
      </div>

      <?php if (!empty($activity['description'])): ?>
      <div style="margin-bottom:1.25rem">
        <div style="font-weight:700;color:#343a40;margin-bottom:.5rem;font-size:.9rem"><i class="fas fa-align-left me-1" style="color:#800000"></i> Instructions</div>
        <div class="description-box"><?php echo htmlspecialchars($activity['description']); ?></div>
      </div>
      <?php endif; ?>

      <?php if (!empty($activity['document_path'])): ?>
      <div>
        <div style="font-weight:700;color:#343a40;margin-bottom:.5rem;font-size:.9rem"><i class="fas fa-paperclip me-1" style="color:#800000"></i> Reference Document</div>
        <a href="<?php echo htmlspecialchars($activity['document_path']); ?>" target="_blank"
           style="display:inline-flex;align-items:center;gap:.5rem;background:#fff;border:2px solid #800000;color:#800000;border-radius:10px;padding:.6rem 1.1rem;font-weight:700;font-size:.88rem;text-decoration:none;transition:all .2s"
           onmouseover="this.style.background='#800000';this.style.color='#fff'"
           onmouseout="this.style.background='#fff';this.style.color='#800000'">
          <i class="fas fa-download"></i> <?php echo htmlspecialchars($activity['document_name'] ?? 'Download File'); ?>
        </a>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- CBT Session Workbook Content -->
  <?php if ($cbtSession > 0 && $activity['status'] === 'opened' && !$submission): ?>
  <div class="fc-card">
    <div class="fc-card-header"><i class="fas fa-book-open"></i> Session <?php echo $cbtSession; ?> Workbook Exercises</div>
    <div class="fc-card-body" id="cbtContent">
      <?php include __DIR__ . '/cbt_session_content.php'; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Submission Section -->
  <div class="fc-card">
    <div class="fc-card-header"><i class="fas fa-paper-plane"></i> Your Submission</div>
    <div class="fc-card-body">

      <?php if ($submission): ?>
      <!-- Already submitted -->
      <div style="background:#d1e7dd;border-radius:12px;padding:1.25rem 1.5rem;margin-bottom:1rem">
        <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.75rem">
          <i class="fas fa-check-circle" style="color:#0f5132;font-size:1.3rem"></i>
          <strong style="color:#0f5132;font-size:1rem">Activity Submitted</strong>
          <span style="color:#6c757d;font-size:.82rem;margin-left:auto"><?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?></span>
        </div>
        <?php if (!empty($submission['notes'])): ?>
        <div style="background:#fff;border-radius:8px;padding:.75rem 1rem;border-left:3px solid #0f5132;font-size:.9rem;color:#343a40;margin-bottom:.75rem">
          <?php echo nl2br(htmlspecialchars($submission['notes'])); ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($submission['file_path'])): ?>
        <a href="<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank"
           style="display:inline-flex;align-items:center;gap:.4rem;color:#0f5132;font-weight:600;font-size:.85rem;text-decoration:none;background:#fff;border:1.5px solid #0f5132;border-radius:8px;padding:.35rem .75rem">
          <i class="fas fa-download"></i> <?php echo htmlspecialchars($submission['file_name'] ?? 'Download File'); ?>
        </a>
        <?php else: ?>
        <span style="font-size:.82rem;color:#6c757d;font-style:italic">No file attached</span>
        <?php endif; ?>
      </div>

      <?php elseif ($activity['status'] !== 'opened'): ?>
      <div style="text-align:center;padding:2.5rem;color:#664d03">
        <i class="fas fa-lock fa-3x mb-3" style="opacity:.5;display:block"></i>
        <h5>Activity Not Yet Opened</h5>
        <p style="font-size:.9rem;color:#6c757d">Your case manager has not opened this activity yet. Please wait for them to open it.</p>
      </div>

      <?php elseif ($isClosed): ?>
      <div style="text-align:center;padding:2.5rem;color:#842029">
        <i class="fas fa-times-circle fa-3x mb-3" style="opacity:.5;display:block"></i>
        <h5>Submission Deadline Passed</h5>
        <p style="font-size:.9rem;color:#6c757d">The deadline for this activity was <?php echo date('M j, Y', strtotime($activity['close_date'])); ?>.</p>
      </div>

      <?php else: ?>
      <div id="submitAlert" style="display:none" class="alert mb-3"></div>
      <form id="submitForm" enctype="multipart/form-data">
        <input type="hidden" name="activity_id" value="<?php echo $activity_id; ?>">

        <?php if ($cbtSession > 0): ?>
        <p style="font-size:.9rem;color:#6c757d;margin-bottom:1rem">
          <i class="fas fa-info-circle me-1" style="color:#800000"></i>
          Complete the workbook exercises above, then click <strong>Submit Activity</strong> to save your answers.
        </p>
        <div class="mb-3">
          <label class="form-label" style="font-weight:600;color:#343a40">Additional Notes <span style="color:#6c757d;font-weight:400">(optional)</span></label>
          <textarea name="notes" id="notesField" class="form-control" rows="3" placeholder="Any additional notes or comments..." style="border-radius:10px;border:1.5px solid #dee2e6"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label" style="font-weight:600;color:#343a40">Upload File <span style="color:#6c757d;font-weight:400">(optional)</span></label>
          <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="border-radius:10px;border:1.5px solid #dee2e6">
        </div>
        <?php else: ?>
        <div class="mb-3">
          <label class="form-label" style="font-weight:600;color:#343a40">Upload File <span style="color:#6c757d;font-weight:400">(optional)</span></label>
          <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="border-radius:10px;border:1.5px solid #dee2e6">
          <div class="form-text">Accepted: PDF, DOC, DOCX, JPG, PNG</div>
        </div>
        <div class="mb-4">
          <label class="form-label" style="font-weight:600;color:#343a40">Notes / Response <span style="color:#6c757d;font-weight:400">(optional)</span></label>
          <textarea name="notes" class="form-control" rows="5" placeholder="Write your response, reflection, or notes here..." style="border-radius:10px;border:1.5px solid #dee2e6"></textarea>
        </div>
        <?php endif; ?>

        <button type="button" class="btn-maroon" id="submitBtn" onclick="submitActivity()" style="padding:.65rem 1.5rem;font-size:.95rem">
          <i class="fas fa-paper-plane"></i> Submit Activity
        </button>
      </form>
      <?php endif; ?>

    </div>
  </div>

</div><!-- /main-content -->
</div><!-- /dashboard-container -->

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
function goNotif(link){ if(link && link !== '#') window.location.href = link; }
function loadNotifs(){
  fetch('/GAMBYTES_Final/api/notifications.php?action=list')
    .then(r => r.json())
    .then(d => {
      const l = document.getElementById('notifList');
      if (!d.items || !d.items.length) { l.innerHTML = '<div class="notif-empty">No notifications</div>'; return; }
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

// ── Submit Activity ───────────────────────────────────────────────────────────
function submitActivity(){
  const form = document.getElementById('submitForm');
  const alertEl = document.getElementById('submitAlert');
  const btn = document.getElementById('submitBtn');
  if (!form || !btn) return;

  alertEl.style.display = 'none';
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

  const fd = new FormData(form);

  // Collect CBT workbook answers from #cbtContent and append to notes
  const cbtContent = document.getElementById('cbtContent');
  if (cbtContent) {
    let cbtAnswers = '';
    cbtContent.querySelectorAll('input[type="text"], input[type="number"], textarea').forEach(el => {
      if (el.name && el.value.trim()) {
        const label = el.closest('.mb-2, .mb-3, .mb-4, td, div')?.querySelector('label')?.textContent?.trim()
                   || el.placeholder || el.name;
        cbtAnswers += label + ':\n' + el.value.trim() + '\n\n';
      }
    });
    cbtContent.querySelectorAll('input[type="radio"]:checked').forEach(el => {
      const row = el.closest('tr');
      if (row) {
        const q = row.querySelector('td:first-child')?.textContent?.trim();
        if (q) cbtAnswers += q + ': ' + el.value + '\n';
      }
    });
    cbtContent.querySelectorAll('input[type="checkbox"]:checked').forEach(el => {
      const lbl = document.querySelector('label[for="' + el.id + '"]')?.textContent?.trim();
      if (lbl) cbtAnswers += '✓ ' + lbl + '\n';
    });

    if (cbtAnswers) {
      const existingNotes = fd.get('notes') || '';
      fd.set('notes', (existingNotes ? existingNotes + '\n\n' : '') + '=== Workbook Answers ===\n' + cbtAnswers);
    }
  }

  fetch('/GAMBYTES_Final/api/case_manager.php?action=submit_activity', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      alertEl.className = 'alert alert-success';
      alertEl.textContent = 'Activity submitted successfully! Reloading...';
      alertEl.style.display = 'block';
      setTimeout(() => location.reload(), 1200);
    } else {
      alertEl.className = 'alert alert-danger';
      alertEl.textContent = d.message || 'Failed to submit activity.';
      alertEl.style.display = 'block';
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Activity';
    }
  })
  .catch(() => {
    alertEl.className = 'alert alert-danger';
    alertEl.textContent = 'Network error. Please try again.';
    alertEl.style.display = 'block';
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Activity';
  });
}
</script>
</body>
</html>
