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

$today = date('Y-m-d');

function activityStatusBadge($open, $close, $today) {
    if ($today < $open)  return ['label' => 'Not Open Yet', 'bg' => '#e2e3e5', 'color' => '#41464b'];
    if ($today <= $close) return ['label' => 'Open',        'bg' => '#d1e7dd', 'color' => '#0f5132'];
    return                       ['label' => 'Closed',      'bg' => '#f8d7da', 'color' => '#842029'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Activities &ndash; Gambytes</title>
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
    <h1 style="color:#800000;font-size:1.8rem;font-weight:800;margin:0"><i class="fas fa-tasks me-2"></i>My Treatment Activities</h1>
    <p style="color:#6c757d;margin:.25rem 0 0">View and submit activities assigned by your case manager.</p>
  </div>

  <!-- Activities List -->
  <div class="fc-card">
    <div class="fc-card-header"><i class="fas fa-clipboard-list"></i> Assigned Activities</div>
    <div class="fc-card-body">
      <div id="activitiesContainer">
        <div style="text-align:center;padding:3rem;color:#6c757d">
          <i class="fas fa-spinner fa-spin fa-3x mb-3" style="opacity:.3;display:block"></i>
          <h5>Loading activities...</h5>
        </div>
      </div>
    </div>
  </div>

</div><!-- /main-content -->
</div><!-- /dashboard-container -->


<!-- Submit Activity Modal -->
<div class="modal fade" id="submitActivityModal" tabindex="-1" aria-labelledby="submitActivityModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:linear-gradient(135deg,#800000,#5c0000);color:#fff;border:none">
        <h5 class="modal-title" id="submitActivityModalLabel"><i class="fas fa-paper-plane me-2"></i>Submit Activity</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding:1.5rem">
        <div id="submitActivityAlert" style="display:none" class="alert"></div>
        <form id="submitActivityForm" enctype="multipart/form-data">
          <input type="hidden" name="activity_id" id="submitActivityId">

          <div class="mb-3">
            <label class="form-label" style="font-weight:600;color:#343a40">Activity Title</label>
            <input type="text" id="submitActivityTitle" class="form-control" readonly style="border-radius:10px;border:1.5px solid #dee2e6;background:#f8f9fa">
          </div>

          <div class="mb-3">
            <label class="form-label" style="font-weight:600;color:#343a40">Upload File <span style="color:#6c757d;font-weight:400">(optional)</span></label>
            <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="border-radius:10px;border:1.5px solid #dee2e6">
            <div class="form-text">Accepted: PDF, DOC, DOCX, JPG, PNG</div>
          </div>

          <div class="mb-3">
            <label class="form-label" style="font-weight:600;color:#343a40">Notes <span style="color:#6c757d;font-weight:400">(optional)</span></label>
            <textarea name="notes" class="form-control" rows="4" placeholder="Add any notes or comments about your submission..." style="border-radius:10px;border:1.5px solid #dee2e6"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer" style="border:none;padding:1rem 1.5rem">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:10px">Cancel</button>
        <button type="button" class="btn-maroon" id="submitActivityBtn" onclick="submitActivity()">
          <i class="fas fa-paper-plane"></i> Submit Activity
        </button>
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

// ── Load Activities ───────────────────────────────────────────────────────────
function loadActivities(highlightId){
  fetch('/GAMBYTES_Final/api/case_manager.php?action=get_my_activities')
    .then(r => r.json())
    .then(d => {
      const container = document.getElementById('activitiesContainer');
      if (!d.success) {
        container.innerHTML = '<div class="alert alert-danger">Failed to load activities.</div>';
        return;
      }
      if (!d.activities || d.activities.length === 0) {
        container.innerHTML = `
          <div style="text-align:center;padding:3rem;color:#6c757d">
            <i class="fas fa-tasks fa-3x mb-3" style="opacity:.3;display:block"></i>
            <h5>No activities assigned yet</h5>
            <p style="font-size:.9rem">Your case manager will assign activities once your treatment begins.</p>
          </div>`;
        return;
      }

      const today = '<?php echo $today; ?>';
      let html = '';
      d.activities.forEach(act => {
        const status = getStatusBadge(act.open_date, act.close_date, today);
        const isOpen = today >= act.open_date && today <= act.close_date;
        const hasSubmitted = !!act.my_submission_id;
        const isOpened = act.status === 'opened';
        const isHighlighted = highlightId && parseInt(act.id) === highlightId;

        html += `
          <div class="activity-row" id="activity-${act.id}" style="${isHighlighted ? 'border-color:#800000;box-shadow:0 0 0 3px rgba(128,0,0,.15);' : ''}">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:.75rem;flex-wrap:wrap;gap:.5rem">
              <div style="flex:1">
                <h5 style="color:#800000;font-weight:700;margin:0 0 .25rem;font-size:1rem">${escHtml(act.title)}</h5>
                <p style="color:#6c757d;margin:0;font-size:.85rem">${escHtml(act.description || '')}</p>
              </div>
              ${status.label !== 'Not Open Yet' ? `
              <span style="background:${status.bg};color:${status.color};padding:.35rem .75rem;border-radius:20px;font-size:.78rem;font-weight:700;white-space:nowrap">
                ${status.label}
              </span>` : ''}
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.5rem;margin-bottom:.75rem">
              <div style="font-size:.82rem;color:#6c757d">
                <i class="fas fa-user-md me-1" style="color:#800000"></i>
                <strong>Case Manager:</strong> ${escHtml(act.case_manager_name)}
              </div>
              <div style="font-size:.82rem;color:#6c757d">
                <i class="fas fa-calendar-alt me-1" style="color:#800000"></i>
                <strong>Open:</strong> ${formatDate(act.open_date)}
              </div>
              <div style="font-size:.82rem;color:#6c757d">
                <i class="fas fa-calendar-times me-1" style="color:#800000"></i>
                <strong>Close:</strong> ${formatDate(act.close_date)}
              </div>
              ${act.document_path ? `
              <div style="font-size:.82rem;color:#6c757d">
                <i class="fas fa-file-alt me-1" style="color:#800000"></i>
                <a href="${escHtml(act.document_path)}" target="_blank" style="color:#800000;font-weight:600;text-decoration:none">
                  ${escHtml(act.document_name || 'Download')}
                </a>
              </div>` : ''}
            </div>

            <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
              ${hasSubmitted ? `
                <span style="background:#d1e7dd;color:#0f5132;padding:.4rem .9rem;border-radius:10px;font-size:.82rem;font-weight:700;display:inline-flex;align-items:center;gap:.4rem">
                  <i class="fas fa-check-circle"></i> Submitted on ${formatDate(act.my_submitted_at)}
                </span>
                ${act.my_file_name ? `
                <span style="font-size:.82rem;color:#6c757d">
                  <i class="fas fa-paperclip me-1"></i>${escHtml(act.my_file_name)}
                </span>` : ''}
              ` : isOpened ? `
                <a href="/GAMBYTES_Final/app/views/Users/Gamblers/view-activity.php?activity_id=${act.id}" class="btn-maroon" style="font-size:.82rem;padding:.4rem .9rem;background:linear-gradient(135deg,#1a6e3c,#0f5132);text-decoration:none">
                  <i class="fas fa-folder-open"></i> Open Activity
                </a>
              ` : `
                <span style="background:#e2e3e5;color:#41464b;padding:.4rem .9rem;border-radius:10px;font-size:.82rem;font-weight:600">
                  <i class="fas fa-lock me-1"></i> Not yet opened by case manager
                </span>
              `}
            </div>
          </div>`;
      });
      container.innerHTML = html;

      // Scroll to highlighted activity if activity_id in URL
      if (highlightId) {
        setTimeout(() => {
          const el = document.getElementById('activity-' + highlightId);
          if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 200);
      }
    })
    .catch(() => {
      document.getElementById('activitiesContainer').innerHTML = '<div class="alert alert-danger">Network error loading activities.</div>';
    });
}

function getStatusBadge(open, close, today) {
  if (today < open)  return { label: 'Not Open Yet', bg: '#e2e3e5', color: '#41464b' };
  if (today <= close) return { label: 'Open',        bg: '#d1e7dd', color: '#0f5132' };
  return                     { label: 'Closed',      bg: '#f8d7da', color: '#842029' };
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// ── Submit Activity ───────────────────────────────────────────────────────────
let submitModal;
function openSubmitModal(activityId, title) {
  document.getElementById('submitActivityId').value = activityId;
  document.getElementById('submitActivityTitle').value = title;
  document.getElementById('submitActivityForm').reset();
  document.getElementById('submitActivityId').value = activityId;
  document.getElementById('submitActivityTitle').value = title;
  document.getElementById('submitActivityAlert').style.display = 'none';
  submitModal = new bootstrap.Modal(document.getElementById('submitActivityModal'));
  submitModal.show();
}

function submitActivity() {
  const form = document.getElementById('submitActivityForm');
  const alertEl = document.getElementById('submitActivityAlert');
  const btn = document.getElementById('submitActivityBtn');

  alertEl.style.display = 'none';

  const activityId = document.getElementById('submitActivityId').value;
  if (!activityId) {
    alertEl.className = 'alert alert-danger';
    alertEl.textContent = 'Invalid activity ID.';
    alertEl.style.display = 'block';
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

  const fd = new FormData(form);

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
      setTimeout(() => {
        submitModal.hide();
        loadActivities();
      }, 1200);
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

// Load activities on page load, then scroll/highlight if activity_id in URL
const urlParams = new URLSearchParams(window.location.search);
const highlightId = urlParams.get('activity_id') ? parseInt(urlParams.get('activity_id')) : null;

loadActivities(highlightId);
</script>
</body>
</html>
