<?php
require_once __DIR__ . '/../../../includes/session_config.php';
require_once __DIR__ . '/../../../includes/url_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . url('app/views/auth/login.php'));
    exit();
}

// Get user information from database
require_once "../../core/Database.php";
$db = new Database();
$conn = $db->connect();

$user_id = $_SESSION['user_id'];

// SECURE: Using prepared statement to prevent SQL injection
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
if (!$stmt) {
    die("Database error occurred");
}

$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$user = $result->fetch_assoc();
$role = $user['role'];
$full_name = $user['first_name'] . ' ' . $user['last_name'];
$stmt->close();

// Redirect Executive Assistant to their specific dashboard
if ($role === 'executive_assistant') {
    header("Location: /GAMBYTES_Final/app/views/Users/Executive Assistant/dashboard.php");
    exit();
}

// For gamblers: find their latest booking with an interview score >= 4
$gamblerContractUrl = '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php';
if ($role === 'gambler') {
    $bStmt = $conn->prepare("
        SELECT br.id 
        FROM booking_record br
        JOIN initial_interview_record ii ON ii.booking_id = br.id
        WHERE br.email = ? AND ii.score >= 4
        ORDER BY br.created_at DESC LIMIT 1
    ");
    $bStmt->bind_param('s', $user['email']);
    $bStmt->execute();
    $bRow = $bStmt->get_result()->fetch_assoc();
    $bStmt->close();
    if ($bRow) {
        $gamblerContractUrl = '/GAMBYTES_Final/app/views/Users/Gamblers/contract/fill-contract.php?booking_id=' . $bRow['id'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gambytes</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css?v=<?= time() ?>">
    <style>
        /* ── Top Navbar ── */
        .top-navbar { background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:.85rem 1.5rem; display:flex; align-items:center; justify-content:space-between; margin-bottom:1.75rem; }
        .top-navbar-left .top-navbar-title { font-weight:700; font-size:1rem; color:#800000; }
        .top-navbar-right { display:flex; align-items:center; gap:1rem; }
        .top-navbar-user { display:flex; align-items:center; gap:.5rem; font-size:.9rem; font-weight:600; color:#343a40; }
        .top-navbar-user i { font-size:1.3rem; color:#800000; }

        /* ── Notification Bell ── */
        .notif-bell-wrap { position:relative; }
        .notif-bell-btn { background:linear-gradient(135deg,#800000,#5c0000); border:none; color:#fff; width:40px; height:40px; border-radius:10px; cursor:pointer; font-size:1rem; position:relative; transition:.2s; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 8px rgba(128,0,0,.3); }
        .notif-bell-btn:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(128,0,0,.4); }
        .notif-badge { position:absolute; top:-6px; right:-6px; background:#ffc107; color:#000; font-size:.65rem; font-weight:800; min-width:18px; height:18px; border-radius:9px; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #fff; }
        .notif-dropdown { display:none; position:absolute; right:0; top:calc(100% + 8px); width:300px; background:#fff; border-radius:14px; box-shadow:0 8px 30px rgba(0,0,0,.15); z-index:9999; overflow:hidden; }
        .notif-dropdown.open { display:block; }
        .notif-dropdown-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:.75rem 1rem; display:flex; justify-content:space-between; align-items:center; font-weight:700; font-size:.9rem; }
        .notif-mark-btn { background:rgba(255,255,255,.2); border:none; color:#fff; font-size:.75rem; padding:.25rem .6rem; border-radius:6px; cursor:pointer; }
        .notif-mark-btn:hover { background:rgba(255,255,255,.35); }
        .notif-item { padding:.75rem 1rem; border-bottom:1px solid #f0f0f0; font-size:.85rem; }
        .notif-item:last-child { border-bottom:none; }
        .notif-item strong { display:block; color:#343a40; }
        .notif-item span { color:#6c757d; font-size:.78rem; }
        .notif-empty { padding:1.5rem 1rem; text-align:center; color:#6c757d; font-size:.85rem; }
        .notif-dropdown-footer { padding:.6rem 1rem; background:#f8f9fa; text-align:center; }
        .notif-dropdown-footer a { color:#800000; font-size:.82rem; font-weight:600; text-decoration:none; }
        .notif-dropdown-footer a:hover { text-decoration:underline; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Modern Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="/GAMBYTES_Final/public/images/Logo.png" alt="Logo">
                    <span>Gambytes</span>
                </div>
                <div class="sidebar-user">
                    <div class="user-name">
                        <i class="fas fa-user-circle me-1"></i>
                        <?= htmlspecialchars($full_name) ?>
                    </div>
                    <div class="user-role"><?= ucfirst(str_replace('_', ' ', $role)) ?></div>
                </div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php" class="active">
                    <i class="fas fa-home"></i> Overview
                </a></li>
                
                <?php if ($role === 'supervisor'): ?>
                <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php">
                    <i class="fas fa-calendar-check"></i> Booking Management
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/interview-records.php">
                    <i class="fas fa-clipboard-list"></i> Interview Records
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/contract-review.php">
                    <i class="fas fa-file-contract"></i> Contract Management
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/policies.php">
                    <i class="fas fa-book"></i> Policies &amp; Guidelines
                </a></li>
                <?php endif; ?>

                <?php if ($role === 'gambler'): ?>
                <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/BookRehab.php">
                    <i class="fas fa-calendar-plus"></i> Book Rehabilitation
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php">
                    <i class="fas fa-clipboard-list"></i> My Interview
                </a></li>
                <li><a href="<?= htmlspecialchars($gamblerContractUrl) ?>">
                    <i class="fas fa-file-contract"></i> My Contracts
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-cbt-sessions.php">
                    <i class="fas fa-brain"></i> CBT Sessions
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-activities.php">
                    <i class="fas fa-tasks"></i> My Activities
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/parental-control-requests.php">
                    <i class="fas fa-shield-alt"></i> Parental Access
                </a></li>
                <?php elseif ($role === 'family'): ?>
                <li><a href="/GAMBYTES_Final/app/views/Users/Family member/my-contracts.php">
                    <i class="fas fa-file-contract"></i> My Contracts
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Family member/parental-control.php">
                    <i class="fas fa-shield-alt"></i> Parental Control
                </a></li>
                <?php elseif ($role === 'case_manager'): ?>
                <li><a href="/GAMBYTES_Final/app/views/Users/Case manager/my-patients.php">
                    <i class="fas fa-users"></i> My Patients
                </a></li>
                <li><a href="#">
                    <i class="fas fa-chart-line"></i> Treatment Progress
                </a></li>
                <li><a href="#">
                    <i class="fas fa-calendar-alt"></i> Schedule
                </a></li>
                <li><a href="#">
                    <i class="fas fa-file-medical"></i> Reports
                </a></li>
                <?php elseif ($role === 'admin'): ?>
                <li><a href="/GAMBYTES_Final/app/views/Users/admin%20department/payment/verify-payments.php">
                    <i class="fas fa-money-check-alt"></i> Payment Verification
                </a></li>
                <?php elseif ($role === 'nurse'): ?>
                <li><a href="#">
                    <i class="fas fa-pills"></i> Medications
                </a></li>
                <li><a href="#">
                    <i class="fas fa-heartbeat"></i> Patients
                </a></li>
                <?php elseif ($role === 'executive_assistant'): ?>
                <li><a href="/GAMBYTES_Final/app/views/Users/Executive Assistant/dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Contract Verification
                </a></li>
                <li><a href="#">
                    <i class="fas fa-chart-line"></i> Reports
                </a></li>
            <?php elseif ($role === 'supervisor'): ?>
                <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/conduct-interview.php">
                    <i class="fas fa-eye"></i> Supervision
                </a></li>
                <?php endif; ?>
                
                <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php">
                    <i class="fas fa-user"></i> Profile
                </a></li>
                <div class="menu-divider"></div>
                <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a></li>
            </ul>
        </div>
        
        <!-- Modern Main Content Area -->
        <div class="main-content">

            <!-- Top Navbar -->
            <div class="top-navbar">
                <div class="top-navbar-left">
                    <span class="top-navbar-title">
                        <?= ucfirst(str_replace('_', ' ', $role)) ?> Portal
                    </span>
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
                            <div id="notifList">
                                <div class="notif-empty">No notifications</div>
                            </div>
                            <?php if ($role === 'supervisor' || $role === 'admin'): ?>
                            <div class="notif-dropdown-footer">
                                <a href="/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php">View all bookings →</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Header -->
            <div class="page-header fade-in-up">
                <h1><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
                <p>Welcome back, <strong><?= htmlspecialchars($full_name) ?></strong>.
                </p>
            </div>
            <div class="row g-4">
                <!-- Welcome Card -->
                <div class="col-lg-8">
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <i class="fas fa-home"></i> Welcome to Gambytes
                        </div>
                        <div class="dash-card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Account Status:</strong> Active &nbsp;|&nbsp;
                                <strong>Role:</strong> <?= ucfirst(str_replace('_', ' ', $role)) ?>
                            </div>
                            <p class="mb-3">This dashboard provides you with a comprehensive view of your account status and quick access to all tools for your recovery journey.</p>
                            <div class="row text-center g-3">
                                <div class="col-md-4">
                                    <div class="p-3 border rounded">
                                        <i class="fas fa-database fa-2x mb-2" style="color:#800000;"></i>
                                        <h6>System</h6>
                                        <small class="text-muted">Gambytes Recovery</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 border rounded">
                                        <i class="fas fa-calendar fa-2x mb-2" style="color:#800000;"></i>
                                        <h6>Today</h6>
                                        <small class="text-muted"><?= date('M j, Y') ?></small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 border rounded">
                                        <i class="fas fa-clock fa-2x mb-2" style="color:#800000;"></i>
                                        <h6>Time</h6>
                                        <small class="text-muted" id="current-time"><?= date('h:i A') ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <i class="fas fa-history"></i> Recent Activity
                        </div>
                        <div class="dash-card-body">
                            <div class="timeline">
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-success"></div>
                                    <div class="timeline-content">
                                        <h6>Account Created</h6>
                                        <p class="text-muted mb-0">Welcome to Gambytes! Your recovery journey begins here.</p>
                                        <small class="text-muted">Today</small>
                                    </div>
                                </div>
                                <div class="timeline-item">
                                    <div class="timeline-marker" style="background:#800000;"></div>
                                    <div class="timeline-content">
                                        <h6>Profile Setup</h6>
                                        <p class="text-muted mb-0">Complete your profile to get personalized recommendations.</p>
                                        <small class="text-muted">Pending</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="col-lg-4">
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <i class="fas fa-bolt"></i> Quick Actions
                        </div>
                        <div class="dash-card-body">
                            <?php if ($role === 'gambler'): ?>
                                <a href="/GAMBYTES_Final/app/views/Users/Gamblers/BookRehab.php" class="quick-action-link">
                                    <i class="fas fa-calendar-plus"></i> Book Rehabilitation
                                </a>
                                <a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-activities.php" class="quick-action-link">
                                    <i class="fas fa-tasks"></i> My Activities
                                </a>
                                <a href="<?= htmlspecialchars($gamblerContractUrl) ?>" class="quick-action-link">
                                    <i class="fas fa-file-contract"></i> My Contracts
                                </a>
                            <?php elseif ($role === 'family'): ?>
                                <a href="/GAMBYTES_Final/app/views/Users/Family member/my-contracts.php" class="quick-action-link">
                                    <i class="fas fa-file-contract"></i> My Contracts
                                </a>
                            <?php elseif ($role === 'supervisor'): ?>
                                <a href="/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php" class="quick-action-link">
                                    <i class="fas fa-calendar-check"></i> Manage Bookings
                                </a>
                                <a href="#" class="quick-action-link">
                                    <i class="fas fa-chart-bar"></i> View Statistics
                                </a>
                                <a href="#" class="quick-action-link">
                                    <i class="fas fa-users"></i> Team Activities
                                </a>
                            <?php elseif ($role === 'admin'): ?>
                                <a href="/GAMBYTES_Final/app/views/Users/admin%20department/payment/verify-payments.php" class="quick-action-link">
                                    <i class="fas fa-money-check-alt"></i> Payment Verification
                                </a>
                            <?php elseif ($role === 'case_manager'): ?>
                                <a href="/GAMBYTES_Final/app/views/Users/Case manager/my-patients.php" class="quick-action-link">
                                    <i class="fas fa-users"></i> My Patients
                                </a>
                                <a href="#" class="quick-action-link">
                                    <i class="fas fa-chart-line"></i> Treatment Progress
                                </a>
                                <a href="#" class="quick-action-link">
                                    <i class="fas fa-file-medical"></i> Reports
                                </a>
                            <?php elseif ($role === 'executive_assistant'): ?>
                                <a href="/GAMBYTES_Final/app/views/Users/Executive Assistant/dashboard.php" class="quick-action-link">
                                    <i class="fas fa-file-contract"></i> Verify Contracts
                                </a>
                                <a href="#" class="quick-action-link">
                                    <i class="fas fa-chart-line"></i> View Reports
                                </a>
                            <?php elseif ($role === 'nurse'): ?>
                                <a href="#" class="quick-action-link">
                                    <i class="fas fa-pills"></i> Medications
                                </a>
                                <a href="#" class="quick-action-link">
                                    <i class="fas fa-heartbeat"></i> Patient Visits
                                </a>
                            <?php endif; ?>
                            <a href="#" class="quick-action-link">
                                <i class="fas fa-user-edit"></i> Update Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        setInterval(() => {
            const el = document.getElementById('current-time');
            if (el) {
                const now = new Date();
                el.textContent = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            }
        }, 60000);
    </script>

    <script>
        const NOTIF_API = '/GAMBYTES_Final/api/notifications.php';

        function toggleNotifDropdown() {
            const dd = document.getElementById('notifDropdown');
            dd.classList.toggle('open');
            if (dd.classList.contains('open')) loadNotifList();
        }

        function loadNotifList() {
            fetch(NOTIF_API + '?action=list')
                .then(r => r.json())
                .then(data => {
                    const list = document.getElementById('notifList');
                    if (!data.items || data.items.length === 0) {
                        list.innerHTML = '<div class="notif-empty">No notifications</div>';
                        return;
                    }
                    list.innerHTML = '';
                    data.items.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'notif-item';
                        div.style.display = 'flex';
                        div.style.justifyContent = 'space-between';
                        div.style.alignItems = 'flex-start';
                        div.style.gap = '.75rem';
                        div.style.cursor = item.link ? 'default' : 'default';
                        if (item.link) div.dataset.link = item.link;
                        
                        const content = document.createElement('div');
                        content.style.flex = '1';
                        if (item.link) content.style.cursor = 'pointer';
                        content.innerHTML = `
                            <strong><i class="fas fa-calendar-check me-1" style="color:#800000;"></i>${item.title}</strong>
                            <span style="display:block; margin-top:2px;">${item.message}</span>
                            <span style="color:#aaa;">${timeAgo(item.created_at)}</span>
                        `;
                        
                        const deleteBtn = document.createElement('button');
                        deleteBtn.style.flexShrink = '0';
                        deleteBtn.style.background = 'none';
                        deleteBtn.style.border = 'none';
                        deleteBtn.style.color = '#dc3545';
                        deleteBtn.style.cursor = 'pointer';
                        deleteBtn.style.fontSize = '.9rem';
                        deleteBtn.style.padding = '.25rem .5rem';
                        deleteBtn.style.borderRadius = '4px';
                        deleteBtn.title = 'Delete';
                        deleteBtn.innerHTML = '<i class="fas fa-times"></i>';
                        deleteBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            deleteNotif(item.id, e);
                        });
                        
                        div.appendChild(content);
                        div.appendChild(deleteBtn);
                        
                        if (item.link) {
                            content.addEventListener('click', function() {
                                window.location.href = item.link;
                            });
                        }
                        
                        list.appendChild(div);
                    });
                });
        }

        function deleteNotif(notifId, event) {
            event.stopPropagation();
            if (!confirm('Are you sure you want to delete this notification?')) return;
            fetch(NOTIF_API + '?action=delete&id=' + notifId)
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        loadNotifList();
                        pollNotifCount();
                    } else {
                        alert('Error deleting notification: ' + (d.message || 'Unknown error'));
                    }
                });
        }

        function markAllSeen() {
            fetch(NOTIF_API + '?action=mark_seen')
                .then(r => r.json())
                .then(() => {
                    document.getElementById('notifBadge').style.display = 'none';
                    document.getElementById('notifBadge').textContent = '0';
                    document.getElementById('notifList').innerHTML = '<div class="notif-empty">No notifications</div>';
                    document.getElementById('notifDropdown').classList.remove('open');
                });
        }

        function pollNotifCount() {
            fetch(NOTIF_API + '?action=count')
                .then(r => r.json())
                .then(data => {
                    const badge = document.getElementById('notifBadge');
                    if (data.count > 0) {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                });
        }

        function timeAgo(dateStr) {
            const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
            if (diff < 60) return 'just now';
            if (diff < 3600) return Math.floor(diff/60) + 'm ago';
            if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
            return Math.floor(diff/86400) + 'd ago';
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', e => {
            const wrap = document.getElementById('notifWrap');
            if (wrap && !wrap.contains(e.target)) {
                document.getElementById('notifDropdown').classList.remove('open');
            }
        });

        // Poll on load and every 30s — all roles
        pollNotifCount();
        setInterval(pollNotifCount, 30000);
    </script>
</body>
</html>
