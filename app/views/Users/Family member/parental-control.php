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

if (!$user || $user['role'] !== 'family') {
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
    <title>Parental Control – Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
    <style>
        /* ── Cards ── */
        .pc-card { background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); overflow:hidden; margin-bottom:1.5rem; }
        .pc-card-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:1rem 1.5rem; font-weight:700; font-size:1rem; display:flex; align-items:center; gap:.6rem; }
        .pc-card-body { padding:1.5rem; }

        /* ── Search ── */
        .search-wrap { position:relative; }
        .search-wrap input { border-radius:10px; border:1.5px solid #dee2e6; padding:.65rem 1rem .65rem 2.5rem; font-size:.92rem; width:100%; transition:.2s; }
        .search-wrap input:focus { border-color:#800000; box-shadow:0 0 0 3px rgba(128,0,0,.1); outline:none; }
        .search-wrap .search-icon { position:absolute; left:.85rem; top:50%; transform:translateY(-50%); color:#adb5bd; }
        #searchResults { border:1.5px solid #dee2e6; border-radius:10px; background:#fff; box-shadow:0 4px 20px rgba(0,0,0,.1); max-height:280px; overflow-y:auto; display:none; }
        .result-item { padding:.75rem 1rem; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #f0f0f0; cursor:pointer; transition:.15s; }
        .result-item:last-child { border-bottom:none; }
        .result-item:hover { background:#fdf5f5; }
        .result-item .ri-name { font-weight:600; color:#212529; font-size:.9rem; }
        .result-item .ri-email { font-size:.78rem; color:#6c757d; }

        /* ── Gambler list ── */
        .gambler-row { display:flex; align-items:center; justify-content:space-between; padding:.9rem 1rem; border-radius:10px; background:#f8f9fa; margin-bottom:.6rem; gap:.75rem; flex-wrap:wrap; }
        .gambler-row:last-child { margin-bottom:0; }
        .gambler-avatar { width:42px; height:42px; border-radius:50%; background:linear-gradient(135deg,#800000,#5c0000); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:1rem; flex-shrink:0; }
        .gambler-info { flex:1; min-width:0; }
        .gambler-info .g-name { font-weight:700; color:#212529; font-size:.92rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .gambler-info .g-email { font-size:.78rem; color:#6c757d; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .status-pill { display:inline-flex; align-items:center; gap:.35rem; padding:.3rem .85rem; border-radius:20px; font-size:.78rem; font-weight:700; }
        .pill-pending  { background:#fff3cd; color:#856404; }
        .pill-accepted { background:#d1e7dd; color:#0f5132; }
        .pill-declined { background:#f8d7da; color:#842029; }

        /* ── Activity panel ── */
        #activityPanel { display:none; }
        .act-section-title { font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#800000; margin:1.25rem 0 .6rem; display:flex; align-items:center; gap:.4rem; }
        .act-table { width:100%; border-collapse:collapse; font-size:.85rem; }
        .act-table th { background:#f8f9fa; color:#6c757d; font-weight:600; font-size:.75rem; text-transform:uppercase; letter-spacing:.4px; padding:.55rem .75rem; border-bottom:2px solid #dee2e6; }
        .act-table td { padding:.55rem .75rem; border-bottom:1px solid #f0f0f0; color:#343a40; vertical-align:top; }
        .act-table tr:last-child td { border-bottom:none; }
        .act-empty { text-align:center; color:#adb5bd; font-size:.85rem; padding:1.25rem; }

        /* ── Notification bell (same as dashboard) ── */
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

        .btn-maroon { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border:none; border-radius:8px; padding:.45rem 1.1rem; font-size:.85rem; font-weight:600; cursor:pointer; transition:.2s; }
        .btn-maroon:hover { opacity:.88; color:#fff; }
        .btn-outline-maroon { background:transparent; color:#800000; border:1.5px solid #800000; border-radius:8px; padding:.4rem 1rem; font-size:.82rem; font-weight:600; cursor:pointer; transition:.2s; }
        .btn-outline-maroon:hover { background:#800000; color:#fff; }
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
                <div class="user-role">Family</div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Family member/my-contracts.php"><i class="fas fa-file-contract"></i> My Contracts</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Family member/parental-control.php" class="active"><i class="fas fa-shield-alt"></i> Parental Control</a></li>
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
                <span class="top-navbar-title">Family Portal</span>
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
                <i class="fas fa-shield-alt me-2"></i>Parental Control
            </h1>
            <p style="color:#6c757d;margin:.25rem 0 0;">Search for a family member in the system and request access to monitor their rehabilitation activity.</p>
        </div>

        <!-- Search Section -->
        <div class="pc-card">
            <div class="pc-card-header">
                <i class="fas fa-search"></i> Find a Family Member (Gambler)
            </div>
            <div class="pc-card-body">
                <p style="font-size:.88rem;color:#6c757d;margin-bottom:1rem;">
                    Type the name or email of the person you want to monitor. They must be registered as a <strong>Gambler</strong> in the system.
                </p>
                <div class="search-wrap">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" placeholder="Search by name or email…" autocomplete="off">
                </div>
                <div id="searchResults" class="mt-1"></div>
            </div>
        </div>

        <!-- My Monitored Gamblers -->
        <div class="pc-card">
            <div class="pc-card-header">
                <i class="fas fa-users"></i> My Monitored Members
            </div>
            <div class="pc-card-body" id="gamblerListWrap">
                <div class="act-empty"><i class="fas fa-spinner fa-spin me-1"></i> Loading…</div>
            </div>
        </div>

        <!-- Activity Panel (shown when a gambler is selected) -->
        <div class="pc-card" id="activityPanel">
            <div class="pc-card-header" id="activityHeader">
                <i class="fas fa-chart-line"></i> <span id="activityTitle">Activity</span>
            </div>
            <div class="pc-card-body" id="activityBody">
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
                <div class="notif-item ${n.is_read == 0 ? 'fw-semibold' : ''}" style="cursor:pointer;" onclick="goNotif('${n.link || '#'}')">
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

// ── Search gamblers ───────────────────────────────────────────────────────────
let searchTimer;
let hasActiveLink = false; // tracks if family already has a pending/accepted gambler

document.getElementById('searchInput').addEventListener('input', function() {
    if (hasActiveLink) return; // locked — do nothing
    clearTimeout(searchTimer);
    const q = this.value.trim();
    const box = document.getElementById('searchResults');
    if (q.length < 2) { box.style.display = 'none'; return; }
    searchTimer = setTimeout(() => {
        fetch(`${API}?action=search_gamblers&q=${encodeURIComponent(q)}`)
            .then(r => r.json()).then(d => {
                hasActiveLink = d.has_active_link || false;
                if (hasActiveLink) { box.style.display = 'none'; lockSearchCard(); return; }
                if (!d.success || !d.results.length) {
                    box.innerHTML = '<div class="result-item"><span class="ri-email">No results found.</span></div>';
                    box.style.display = 'block';
                    return;
                }
                box.innerHTML = d.results.map(u => {
                    const already = u.request_status;
                    let btn = '';
                    if (!already) {
                        btn = `<button class="btn-maroon" onclick="sendRequest(${u.id}, this)"><i class="fas fa-paper-plane me-1"></i>Request Access</button>`;
                    } else {
                        btn = statusPill(already);
                    }
                    return `<div class="result-item">
                        <div>
                            <div class="ri-name">${escHtml(u.first_name + ' ' + u.last_name)}</div>
                            <div class="ri-email">${escHtml(u.email)}</div>
                        </div>
                        <div>${btn}</div>
                    </div>`;
                }).join('');
                box.style.display = 'block';
            });
    }, 350);
});

// Lock the search card when family already has an active link
function lockSearchCard() {
    const input = document.getElementById('searchInput');
    input.disabled = true;
    input.placeholder = 'You already have an active parental control link.';
    const wrap = document.querySelector('.pc-card-body');
    if (wrap && !document.getElementById('lockNotice')) {
        const notice = document.createElement('div');
        notice.id = 'lockNotice';
        notice.style.cssText = 'background:#fff3cd;border-left:4px solid #ffc107;border-radius:10px;padding:.85rem 1.1rem;font-size:.87rem;color:#856404;margin-top:.85rem;display:flex;align-items:center;gap:.6rem;';
        notice.innerHTML = '<i class="fas fa-lock"></i><span>You can only monitor <strong>one person</strong> at a time. Remove the current link first before adding another.</span>';
        wrap.appendChild(notice);
    }
}

// Hide search results when clicking outside
document.addEventListener('click', e => {
    const wrap = document.querySelector('.search-wrap');
    const box  = document.getElementById('searchResults');
    if (wrap && !wrap.contains(e.target) && !box.contains(e.target)) box.style.display = 'none';
});

// ── Send request ──────────────────────────────────────────────────────────────
function sendRequest(gamblerId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending…';
    const fd = new FormData();
    fd.append('action', 'send_request');
    fd.append('gambler_id', gamblerId);
    fetch(API, { method:'POST', body:fd })
        .then(r => r.json()).then(d => {
            if (d.success) {
                btn.outerHTML = statusPill('pending');
                loadGamblerList(); // refresh list
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Request Access';
                alert(d.message || 'Failed to send request.');
            }
        });
}

// ── Load monitored gamblers list ──────────────────────────────────────────────
function loadGamblerList() {
    fetch(`${API}?action=my_gamblers`)
        .then(r => r.json()).then(d => {
            const wrap = document.getElementById('gamblerListWrap');
            if (!d.success || !d.gamblers.length) {
                wrap.innerHTML = `<div class="act-empty">
                    <i class="fas fa-user-friends fa-2x mb-2" style="color:#dee2e6;display:block;"></i>
                    No monitored members yet. Use the search above to find and request access.
                </div>`;
                // No active link — unlock search
                hasActiveLink = false;
                return;
            }

            // Check if any gambler has pending or accepted status
            const activeEntry = d.gamblers.find(g => g.status === 'pending' || g.status === 'accepted');
            hasActiveLink = !!activeEntry;
            if (hasActiveLink) lockSearchCard();

            wrap.innerHTML = d.gamblers.map(g => {
                const ini = initials(g.first_name, g.last_name);
                let actionBtn = '';
                if (g.status === 'accepted') {
                    actionBtn = `<button class="btn-outline-maroon" onclick="loadActivity(${g.gambler_id}, '${escHtml(g.first_name + ' ' + g.last_name)}')">
                        <i class="fas fa-eye me-1"></i>View Activity
                    </button>`;
                } else if (g.status === 'pending') {
                    // No re-send when one-gambler rule is active — just show status
                    actionBtn = '';
                } else if (g.status === 'declined') {
                    // Declined = no active link, allow requesting again
                    actionBtn = `<button class="btn-maroon" onclick="sendRequest(${g.gambler_id}, this)">
                        <i class="fas fa-paper-plane me-1"></i>Request Again
                    </button>`;
                }
                return `<div class="gambler-row">
                    <div class="gambler-avatar">${ini}</div>
                    <div class="gambler-info">
                        <div class="g-name">${escHtml(g.first_name + ' ' + g.last_name)}</div>
                        <div class="g-email">${escHtml(g.email)}</div>
                    </div>
                    <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;">
                        ${statusPill(g.status)}
                        ${actionBtn}
                    </div>
                </div>`;
            }).join('');
        });
}
loadGamblerList();

// ── Load gambler activity ─────────────────────────────────────────────────────
function loadActivity(gamblerId, name) {
    const panel = document.getElementById('activityPanel');
    const body  = document.getElementById('activityBody');
    const title = document.getElementById('activityTitle');
    title.textContent = name + ' – Activity';
    body.innerHTML = '<div class="act-empty"><i class="fas fa-spinner fa-spin me-1"></i> Loading activity…</div>';
    panel.style.display = 'block';
    panel.scrollIntoView({ behavior:'smooth', block:'start' });

    fetch(`${API}?action=gambler_activity&gambler_id=${gamblerId}`)
        .then(r => r.text()).then(raw => {
            let d;
            try { d = JSON.parse(raw); } catch(e) {
                body.innerHTML = `<div class="act-empty" style="color:#842029;text-align:left;white-space:pre-wrap;font-size:.8rem;padding:1rem;">`
                    + `<strong>Server error:</strong><br>${escHtml(raw.substring(0, 800))}</div>`;
                return;
            }
            if (!d.success) { body.innerHTML = `<div class="act-empty">${escHtml(d.message)}</div>`; return; }

            // ── Bookings ──────────────────────────────────────────────────────
            let bookHtml = '<div class="act-section-title"><i class="fas fa-calendar-check"></i> Rehabilitation Bookings</div>';
            if (d.bookings.length) {
                bookHtml += `<table class="act-table">
                    <thead><tr><th>#</th><th>Start Time</th><th>End Time</th><th>Status</th><th>Booked On</th></tr></thead>
                    <tbody>${d.bookings.map((b,i) => `<tr>
                        <td>${i+1}</td>
                        <td>${b.start_time || '—'}</td>
                        <td>${b.end_time || '—'}</td>
                        <td><span class="status-pill ${b.status==='completed'?'pill-accepted':b.status==='cancelled'?'pill-declined':'pill-pending'}">${escHtml(b.status)}</span></td>
                        <td>${b.created_at || '—'}</td>
                    </tr>`).join('')}</tbody>
                </table>`;
            } else {
                bookHtml += '<div class="act-empty">No bookings found.</div>';
            }

            // ── Interviews ────────────────────────────────────────────────────
            let intHtml = '<div class="act-section-title"><i class="fas fa-clipboard-list"></i> Interview Records</div>';
            if (d.interviews.length) {
                intHtml += `<table class="act-table">
                    <thead><tr><th>Score</th><th>Diagnosis</th><th>Remarks</th><th>Interviewer</th><th>Date</th></tr></thead>
                    <tbody>${d.interviews.map(iv => `<tr>
                        <td>${escHtml(iv.score ?? '—')}</td>
                        <td>${escHtml(iv.diagnosis ?? '—')}</td>
                        <td>${escHtml(iv.remarks ?? '—')}</td>
                        <td>${escHtml(iv.interviewer ?? '—')}</td>
                        <td>${iv.created_at || '—'}</td>
                    </tr>`).join('')}</tbody>
                </table>`;
            } else {
                intHtml += '<div class="act-empty">No interview records found.</div>';
            }

            // ── Contracts ─────────────────────────────────────────────────────
            let conHtml = '<div class="act-section-title"><i class="fas fa-file-contract"></i> Contracts</div>';
            const allContracts = [
                ...(d.contracts || []).map(c => ({
                    type: 'MOA / Rehab Contract',
                    status: c.status,
                    ea_status: null,
                    date: c.submitted_at || '—'
                })),
                ...(d.contract_subs || []).map(c => ({
                    type: 'Contract Submission',
                    status: c.status,
                    ea_status: c.ea_verification_status,
                    date: c.submitted_at || c.created_at || '—'
                }))
            ];
            if (allContracts.length) {
                conHtml += `<table class="act-table">
                    <thead><tr><th>Type</th><th>Status</th><th>EA Verification</th><th>Date</th></tr></thead>
                    <tbody>${allContracts.map(c => {
                        const sCls = ['completed','signed_by_family','sent_to_parties'].includes(c.status) ? 'pill-accepted'
                                   : c.status === 'pending' || c.status === 'draft' ? 'pill-pending'
                                   : 'pill-pending';
                        const eaCls = c.ea_status === 'approved' ? 'pill-accepted'
                                    : c.ea_status === 'rejected' ? 'pill-declined'
                                    : c.ea_status ? 'pill-pending' : '';
                        return `<tr>
                            <td>${escHtml(c.type)}</td>
                            <td><span class="status-pill ${sCls}">${escHtml(c.status)}</span></td>
                            <td>${c.ea_status ? `<span class="status-pill ${eaCls}">${escHtml(c.ea_status)}</span>` : '—'}</td>
                            <td>${c.date}</td>
                        </tr>`;
                    }).join('')}</tbody>
                </table>`;
            } else {
                conHtml += '<div class="act-empty">No contracts found.</div>';
            }

            // ── Payments ──────────────────────────────────────────────────────
            let payHtml = '<div class="act-section-title"><i class="fas fa-money-bill-wave"></i> Payments</div>';
            if (d.payments && d.payments.length) {
                payHtml += `<table class="act-table">
                    <thead><tr><th>#</th><th>Amount</th><th>Status</th><th>Paid At</th><th>Receipt No.</th><th>Verified At</th></tr></thead>
                    <tbody>${d.payments.map((p,i) => {
                        const statusCls = p.payment_status === 'verified' ? 'pill-accepted'
                                        : p.payment_status === 'paid'     ? 'pill-pending'
                                        : 'pill-declined';
                        return `<tr>
                            <td>${i+1}</td>
                            <td>₱${parseFloat(p.amount||0).toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
                            <td><span class="status-pill ${statusCls}">${escHtml(p.payment_status)}</span></td>
                            <td>${p.paid_at || '—'}</td>
                            <td>${p.receipt_number ? escHtml(p.receipt_number) : '—'}</td>
                            <td>${p.verified_at || '—'}</td>
                        </tr>`;
                    }).join('')}</tbody>
                </table>`;
            } else {
                payHtml += '<div class="act-empty">No payment records found.</div>';
            }

            // ── CBT Sessions ──────────────────────────────────────────────────
            let cbtHtml = '<div class="act-section-title"><i class="fas fa-brain"></i> CBT Sessions</div>';
            if (d.cbt_sessions && d.cbt_sessions.length) {
                cbtHtml += `<table class="act-table">
                    <thead><tr><th>Session</th><th>Status</th><th>Unlocked At</th><th>Completed At</th><th>Notes</th></tr></thead>
                    <tbody>${d.cbt_sessions.map(s => {
                        const statusCls = s.status === 'completed' ? 'pill-accepted'
                                        : s.status === 'unlocked'  ? 'pill-pending'
                                        : 'pill-declined';
                        const statusLabel = s.status === 'completed' ? 'Completed'
                                          : s.status === 'unlocked'  ? 'Available'
                                          : 'Locked';
                        return `<tr>
                            <td><strong>Session ${escHtml(s.session_number)}</strong></td>
                            <td><span class="status-pill ${statusCls}">${statusLabel}</span></td>
                            <td>${s.unlocked_at || '—'}</td>
                            <td>${s.completed_at || '—'}</td>
                            <td>${s.notes ? escHtml(s.notes) : '—'}</td>
                        </tr>`;
                    }).join('')}</tbody>
                </table>`;
            } else {
                cbtHtml += '<div class="act-empty">No CBT session records found.</div>';
            }

            body.innerHTML = bookHtml + intHtml + conHtml + payHtml + cbtHtml;
        });
}
</script>
</body>
</html>
