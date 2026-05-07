<?php
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

if (!$user || $user['role'] !== 'gambler') {
    header("Location: " . url('app/views/auth/dashboard.php'));
    exit();
}

$full_name = $user['first_name'] . ' ' . $user['last_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parental Control Requests – Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
    <style>
        /* ── Cards ── */
        .pc-card { background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); overflow:hidden; margin-bottom:1.5rem; }
        .pc-card-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:1rem 1.5rem; font-weight:700; font-size:1rem; display:flex; align-items:center; gap:.6rem; }
        .pc-card-body { padding:1.5rem; }

        /* ── Request rows ── */
        .req-row { display:flex; align-items:center; gap:.85rem; padding:1rem; border-radius:12px; background:#f8f9fa; margin-bottom:.75rem; flex-wrap:wrap; }
        .req-row:last-child { margin-bottom:0; }
        .req-avatar { width:46px; height:46px; border-radius:50%; background:linear-gradient(135deg,#800000,#5c0000); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:1.05rem; flex-shrink:0; }
        .req-info { flex:1; min-width:0; }
        .req-info .r-name { font-weight:700; color:#212529; font-size:.95rem; }
        .req-info .r-email { font-size:.78rem; color:#6c757d; }
        .req-info .r-date  { font-size:.75rem; color:#adb5bd; margin-top:.15rem; }
        .status-pill { display:inline-flex; align-items:center; gap:.35rem; padding:.3rem .85rem; border-radius:20px; font-size:.78rem; font-weight:700; }
        .pill-pending  { background:#fff3cd; color:#856404; }
        .pill-accepted { background:#d1e7dd; color:#0f5132; }
        .pill-declined { background:#f8d7da; color:#842029; }

        /* ── Buttons ── */
        .btn-accept  { background:linear-gradient(135deg,#198754,#146c43); color:#fff; border:none; border-radius:8px; padding:.42rem 1rem; font-size:.82rem; font-weight:600; cursor:pointer; transition:.2s; }
        .btn-accept:hover  { opacity:.88; }
        .btn-decline { background:transparent; color:#dc3545; border:1.5px solid #dc3545; border-radius:8px; padding:.4rem 1rem; font-size:.82rem; font-weight:600; cursor:pointer; transition:.2s; }
        .btn-decline:hover { background:#dc3545; color:#fff; }
        .btn-revoke  { background:transparent; color:#6c757d; border:1.5px solid #dee2e6; border-radius:8px; padding:.4rem 1rem; font-size:.82rem; font-weight:600; cursor:pointer; transition:.2s; }
        .btn-revoke:hover  { background:#6c757d; color:#fff; }

        /* ── Notification bell ── */
        .top-navbar { background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:.85rem 1.5rem; display:flex; align-items:center; justify-content:space-between; margin-bottom:1.75rem; }
        .top-navbar-left .top-navbar-title { font-weight:700; font-size:1rem; color:#800000; }
        .top-navbar-right { display:flex; align-items:center; gap:1rem; }
        .notif-bell-wrap { position:relative; }
        .notif-bell-btn { background:linear-gradient(135deg,#800000,#5c0000); border:none; color:#fff; width:40px; height:40px; border-radius:10px; cursor:pointer; font-size:1rem; position:relative; transition:.2s; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 8px rgba(128,0,0,.3); }
        .notif-bell-btn:hover { transform:translateY(-1px); }
        .notif-badge { position:absolute; top:-6px; right:-6px; background:#ffc107; color:#000; font-size:.65rem; font-weight:800; min-width:18px; height:18px; border-radius:9px; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #fff; }
        .notif-dropdown { display:none; position:absolute; right:0; top:calc(100% + 8px); width:300px; background:#fff; border-radius:14px; box-shadow:0 8px 30px rgba(0,0,0,.15); z-index:9999; overflow:hidden; }
        .notif-dropdown.open { display:block; }
        .notif-dropdown-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:.75rem 1rem; display:flex; justify-content:space-between; align-items:center; font-weight:700; font-size:.9rem; }
        .notif-mark-btn { background:rgba(255,255,255,.2); border:none; color:#fff; font-size:.75rem; padding:.25rem .6rem; border-radius:6px; cursor:pointer; }
        .notif-item { padding:.75rem 1rem; border-bottom:1px solid #f0f0f0; font-size:.85rem; }
        .notif-item:last-child { border-bottom:none; }
        .notif-item strong { display:block; color:#343a40; }
        .notif-item span { color:#6c757d; font-size:.78rem; }
        .notif-empty { padding:1.5rem 1rem; text-align:center; color:#6c757d; font-size:.85rem; }
        .notif-dropdown-footer { padding:.6rem 1rem; background:#f8f9fa; text-align:center; }
        .notif-dropdown-footer a { color:#800000; font-size:.82rem; font-weight:600; text-decoration:none; }

        .act-empty { text-align:center; color:#adb5bd; font-size:.88rem; padding:2rem 1rem; }

        /* ── Info banner ── */
        .info-banner { background:#fff8e1; border-left:4px solid #ffc107; border-radius:10px; padding:1rem 1.25rem; font-size:.88rem; color:#5a4a00; margin-bottom:1.5rem; display:flex; align-items:flex-start; gap:.75rem; }
        .info-banner i { color:#ffc107; margin-top:.1rem; flex-shrink:0; }
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
                <div class="user-name"><i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($full_name) ?></div>
                <div class="user-role">Gambler</div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/BookRehab.php"><i class="fas fa-calendar-plus"></i> Book Rehabilitation</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php"><i class="fas fa-clipboard-list"></i> My Interview</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php"><i class="fas fa-file-contract"></i> My Contracts</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-cbt-sessions.php"><i class="fas fa-brain"></i> CBT Sessions</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-activities.php"><i class="fas fa-tasks"></i> My Activities</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/parental-control-requests.php" class="active"><i class="fas fa-shield-alt"></i> Parental Access</a></li>
            <li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
            <div class="menu-divider"></div>
            <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">

        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="top-navbar-left">
                <span class="top-navbar-title">Gambler Portal</span>
            </div>
            <div class="top-navbar-right">
                <div class="notif-bell-wrap" id="notifWrap">
                    <button type="button" class="notif-bell-btn" id="notifBellBtn" onclick="toggleNotifDropdown()">
                        <i class="fas fa-bell"></i>
                        <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
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
        <div style="margin-bottom:1.75rem;">
            <h1 style="color:#800000;font-size:1.8rem;font-weight:800;margin:0;">
                <i class="fas fa-shield-alt me-2"></i>Parental Access Requests
            </h1>
            <p style="color:#6c757d;margin:.25rem 0 0;">Family members who want to monitor your rehabilitation activity will appear here. You decide who gets access.</p>
        </div>

        <!-- Info Banner -->
        <div class="info-banner">
            <i class="fas fa-info-circle fa-lg"></i>
            <div>
                <strong>Your privacy matters.</strong> Accepting a request allows a family member to view your bookings, interview records, and contracts. You can revoke access at any time.
            </div>
        </div>

        <!-- Requests Card -->
        <div class="pc-card">
            <div class="pc-card-header">
                <i class="fas fa-user-shield"></i> Access Requests
                <span id="pendingBadge" style="background:rgba(255,255,255,.25);padding:.15rem .65rem;border-radius:20px;font-size:.75rem;margin-left:auto;display:none;"></span>
            </div>
            <div class="pc-card-body" id="requestsWrap">
                <div class="act-empty"><i class="fas fa-spinner fa-spin me-1"></i> Loading…</div>
            </div>
        </div>

    </div><!-- /main-content -->
</div><!-- /dashboard-container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API = '/GAMBYTES_Final/api/parental_control.php';

// ── Notification bell ─────────────────────────────────────────────────────────
function toggleNotifDropdown() {
    const dd = document.getElementById('notifDropdown');
    dd.classList.toggle('open');
    if (dd.classList.contains('open')) loadNotifs();
}
document.addEventListener('click', e => {
    const wrap = document.getElementById('notifWrap');
    if (wrap && !wrap.contains(e.target)) document.getElementById('notifDropdown').classList.remove('open');
});
function loadNotifs() {
    fetch('/GAMBYTES_Final/api/notifications.php?action=list')
        .then(r => r.json()).then(d => {
            const list = document.getElementById('notifList');
            if (!d.items || !d.items.length) { list.innerHTML = '<div class="notif-empty">No notifications</div>'; return; }
            list.innerHTML = d.items.map(n => `
                <div class="notif-item" style="cursor:pointer;" onclick="goNotif('${n.link || '#'}')">
                    <strong>${escHtml(n.title)}</strong>
                    <span>${escHtml(n.message || '')}</span>
                    <span style="font-size:.72rem;color:#adb5bd;">${n.created_at}</span>
                </div>`).join('');
        });
}
function goNotif(link) { if (link && link !== '#') window.location.href = link; }
function markAllSeen() {
    fetch('/GAMBYTES_Final/api/notifications.php?action=mark_seen').then(() => {
        document.getElementById('notifBadge').style.display = 'none';
        document.getElementById('notifList').innerHTML = '<div class="notif-empty">No notifications</div>';
    });
}
function pollNotifCount() {
    fetch('/GAMBYTES_Final/api/notifications.php?action=count')
        .then(r => r.json()).then(d => {
            const badge = document.getElementById('notifBadge');
            if (d.count > 0) { badge.textContent = d.count; badge.style.display = 'flex'; }
            else badge.style.display = 'none';
        });
}
pollNotifCount();
setInterval(pollNotifCount, 30000);

// ── Utility ───────────────────────────────────────────────────────────────────
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function initials(fn, ln) {
    return ((fn||'').charAt(0) + (ln||'').charAt(0)).toUpperCase();
}
function statusPill(status) {
    const map = {
        pending:  ['pill-pending',  'fa-clock',       'Pending'],
        accepted: ['pill-accepted', 'fa-check-circle','Accepted'],
        declined: ['pill-declined', 'fa-times-circle','Declined'],
    };
    const [cls, icon, label] = map[status] || ['pill-pending','fa-question','Unknown'];
    return `<span class="status-pill ${cls}"><i class="fas ${icon}"></i>${label}</span>`;
}

// ── Load requests ─────────────────────────────────────────────────────────────
function loadRequests() {
    fetch(`${API}?action=my_requests`)
        .then(r => r.json()).then(d => {
            const wrap = document.getElementById('requestsWrap');
            const pb   = document.getElementById('pendingBadge');

            if (!d.success || !d.requests.length) {
                wrap.innerHTML = `<div class="act-empty">
                    <i class="fas fa-shield-alt fa-2x mb-2" style="color:#dee2e6;display:block;"></i>
                    No parental access requests yet.
                </div>`;
                pb.style.display = 'none';
                return;
            }

            const pending = d.requests.filter(r => r.status === 'pending').length;
            if (pending > 0) {
                pb.textContent = pending + ' pending';
                pb.style.display = 'inline-block';
            } else {
                pb.style.display = 'none';
            }

            wrap.innerHTML = d.requests.map(req => {
                const ini = initials(req.first_name, req.last_name);
                let actions = '';
                if (req.status === 'pending') {
                    actions = `
                        <button class="btn-accept" onclick="respond(${req.id}, 'accepted', this)">
                            <i class="fas fa-check me-1"></i>Accept
                        </button>
                        <button class="btn-decline" onclick="respond(${req.id}, 'declined', this)">
                            <i class="fas fa-times me-1"></i>Decline
                        </button>`;
                } else if (req.status === 'accepted') {
                    actions = `
                        ${statusPill('accepted')}
                        <button class="btn-revoke" onclick="revokeAccess(${req.id}, this)" title="Revoke access">
                            <i class="fas fa-ban me-1"></i>Revoke
                        </button>`;
                } else {
                    actions = statusPill(req.status);
                }

                return `<div class="req-row" id="req-${req.id}">
                    <div class="req-avatar">${ini}</div>
                    <div class="req-info">
                        <div class="r-name">${escHtml(req.first_name + ' ' + req.last_name)}</div>
                        <div class="r-email">${escHtml(req.email)}</div>
                        <div class="r-date"><i class="fas fa-clock me-1"></i>Requested: ${req.requested_at}</div>
                    </div>
                    <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;" id="actions-${req.id}">
                        ${actions}
                    </div>
                </div>`;
            }).join('');
        });
}
loadRequests();

// ── Respond to request ────────────────────────────────────────────────────────
function respond(requestId, response, btn) {
    const row = document.getElementById(`actions-${requestId}`);
    row.querySelectorAll('button').forEach(b => b.disabled = true);

    const fd = new FormData();
    fd.append('action', 'respond');
    fd.append('request_id', requestId);
    fd.append('response', response);

    fetch(API, { method:'POST', body:fd })
        .then(r => r.json()).then(d => {
            if (d.success) {
                loadRequests(); // refresh
            } else {
                row.querySelectorAll('button').forEach(b => b.disabled = false);
                alert(d.message || 'Failed to respond.');
            }
        });
}

// ── Revoke access ─────────────────────────────────────────────────────────────
function revokeAccess(requestId, btn) {
    if (!confirm('Are you sure you want to revoke this family member\'s access?')) return;
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'revoke');
    fd.append('request_id', requestId);

    fetch(API, { method:'POST', body:fd })
        .then(r => r.json()).then(d => {
            if (d.success) {
                loadRequests();
            } else {
                btn.disabled = false;
                alert(d.message || 'Failed to revoke access.');
            }
        });
}
</script>
</body>
</html>
