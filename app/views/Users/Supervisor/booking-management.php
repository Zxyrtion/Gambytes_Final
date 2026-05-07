<?php
require_once __DIR__ . '/../../../../includes/session_config.php';
require_once __DIR__ . '/../../../../includes/url_helper.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . url('app/views/auth/login.php'));
    exit();
}

// Check if user is supervisor or admin
require_once __DIR__ . '/../../../core/Database.php';
$db = new Database();
$conn = $db->connect();

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$role = $user['role'];

// Only allow supervisors and admins
if (!in_array($role, ['supervisor', 'admin'])) {
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
    <title>Booking Management - Gambytes</title>
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
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

        /* ── Modals ── */
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9999; align-items:center; justify-content:center; }
        .modal.active { display:flex; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="/GAMBYTES_Final/public/images/Logo.png" alt="Logo">
                    <span>Gambytes</span>
                </div>
                <div class="sidebar-user">
                    <div class="user-name"><i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($full_name) ?></div>
                    <div class="user-role"><?= ucfirst(str_replace('_', ' ', $role)) ?></div>
                </div>
            </div>
        <ul class="sidebar-menu">
            <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php" class="active"><i class="fas fa-calendar-check"></i> Booking Management</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/interview-records.php"><i class="fas fa-clipboard-list"></i> Interview Records</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/contract-review.php"><i class="fas fa-file-contract"></i> Contract Management</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/policies.php"><i class="fas fa-book"></i> Policies &amp; Guidelines</a></li>
            <li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
            <div class="menu-divider"></div>
            <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        </div>
        
        <!-- Main Content Area -->
        <div class="main-content">

            <!-- Top Navbar -->
            <div class="top-navbar">
                <div class="top-navbar-left">
                    <span class="top-navbar-title">Booking Management</span>
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
                                <div class="notif-empty">No new bookings</div>
                            </div>
                            <div class="notif-dropdown-footer">
                                <a href="/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php">View all bookings →</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-header">
                <h1><i class="fas fa-calendar-check"></i> Booking Management</h1>
                <p>Welcome, <strong><?php echo $full_name; ?></strong>! Manage all rehabilitation bookings here.</p>
            </div>
            
            <!-- Filters -->
            <div class="filters-card">
                <h3>Search & Filter</h3>
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="searchInput">Search (Name/Email)</label>
                        <input type="text" id="searchInput" placeholder="Enter name or email...">
                    </div>
                    <div class="filter-group">
                        <label for="statusFilter">Status</label>
                        <select id="statusFilter">
                            <option value="">All Status</option>
                            <option value="booked">Booked</option>
                            <option value="approved">Approved</option>
                            <option value="interviewed">Interviewed</option>
                            <option value="admission">Admission</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="no_show">No Show</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="dateFromFilter">From Date</label>
                        <input type="date" id="dateFromFilter">
                    </div>
                    <div class="filter-group">
                        <label for="dateToFilter">To Date</label>
                        <input type="date" id="dateToFilter">
                    </div>
                    <div class="filter-group">
                        <button class="btn-search" onclick="applyFilters()"><i class="fas fa-search"></i> Search</button>
                    </div>
                </div>
            </div>
            
            <!-- Bookings Table -->
            <div class="bookings-table-container">
                <h3>Bookings List</h3>
                <div id="loadingContainer" class="loading" style="display:none;">
                    <i class="fas fa-spinner fa-spin"></i> Loading bookings...
                </div>
                <div id="noDataContainer" class="no-data" style="display:none;">
                    <i class="fas fa-inbox"></i> No bookings found.
                </div>
                <table class="bookings-table" id="bookingsTable" style="display:none;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Status</th>
                            <th>Booked On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bookingsTableBody">
                    </tbody>
                </table>
                <div class="pagination" id="paginationContainer"></div>
            </div>
        </div>
</div>
    
    <!-- Modal for Initial Interview -->
    <div id="interviewModal" class="modal">
        <div class="modal-content" style="max-width:580px; border-radius:16px; overflow:hidden; padding:0;">
            <div class="modal-header" style="background:linear-gradient(135deg,#0d6efd,#0a58ca); color:#fff; padding:1.25rem 1.5rem; display:flex; justify-content:space-between; align-items:center; border-radius:0;">
                <h2 style="margin:0; font-size:1.15rem; font-weight:700; color:#fff;"><i class="fas fa-comments me-2"></i>Initial Interview</h2>
                <button onclick="closeInterviewModal()" style="background:rgba(255,255,255,.2); border:none; color:#fff; width:32px; height:32px; border-radius:8px; font-size:1.1rem; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
            </div>
            <div style="padding:1.5rem;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:.85rem; margin-bottom:1.25rem;">
                    <div style="padding:.75rem 1rem; background:#f0f4ff; border-radius:10px; border-left:4px solid #0d6efd;">
                        <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Patient</div>
                        <div style="font-weight:700; color:#212529;" id="ivPatientName">—</div>
                    </div>
                    <div style="padding:.75rem 1rem; background:#f0f4ff; border-radius:10px; border-left:4px solid #0d6efd;">
                        <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Email</div>
                        <div style="font-weight:600; color:#212529; font-size:.88rem;" id="ivPatientEmail">—</div>
                    </div>
                </div>
                <input type="hidden" id="ivBookingId">
                <form id="interviewForm">
                    <div style="margin-bottom:1rem;">
                        <label style="font-weight:600; font-size:.88rem; color:#343a40; display:block; margin-bottom:.4rem;">
                            <i class="fas fa-dice me-1" style="color:#0d6efd;"></i> Gambling History
                        </label>
                        <textarea id="iv_gambling_history" rows="3" class="form-control" placeholder="Describe the patient's gambling history, frequency, duration..."></textarea>
                    </div>
                    <div style="margin-bottom:1rem;">
                        <label style="font-weight:600; font-size:.88rem; color:#343a40; display:block; margin-bottom:.4rem;">
                            <i class="fas fa-heartbeat me-1" style="color:#0d6efd;"></i> Physical & Mental Health Assessment
                        </label>
                        <textarea id="iv_health_assessment" rows="3" class="form-control" placeholder="Current physical and mental health status, any existing conditions..."></textarea>
                    </div>
                    <div style="margin-bottom:1rem;">
                        <label style="font-weight:600; font-size:.88rem; color:#343a40; display:block; margin-bottom:.4rem;">
                            <i class="fas fa-users me-1" style="color:#0d6efd;"></i> Social & Family Background
                        </label>
                        <textarea id="iv_social_background" rows="3" class="form-control" placeholder="Family support, social environment, employment status..."></textarea>
                    </div>
                    <div style="margin-bottom:1rem;">
                        <label style="font-weight:600; font-size:.88rem; color:#343a40; display:block; margin-bottom:.4rem;">
                            <i class="fas fa-bullseye me-1" style="color:#0d6efd;"></i> Treatment Goals
                        </label>
                        <textarea id="iv_treatment_goals" rows="2" class="form-control" placeholder="Patient's goals and expectations from rehabilitation..."></textarea>
                    </div>
                    <div style="margin-bottom:1.25rem;">
                        <label style="font-weight:600; font-size:.88rem; color:#343a40; display:block; margin-bottom:.4rem;">
                            <i class="fas fa-clipboard-check me-1" style="color:#0d6efd;"></i> Interviewer's Remarks
                        </label>
                        <textarea id="iv_remarks" rows="2" class="form-control" placeholder="Additional observations and recommendations..."></textarea>
                    </div>
                    <div style="display:flex; gap:.75rem; justify-content:flex-end;">
                        <button type="button" onclick="closeInterviewModal()" style="background:#6c757d; color:#fff; border:none; padding:.55rem 1.4rem; border-radius:8px; font-weight:600; cursor:pointer;">Cancel</button>
                        <button type="submit" style="background:linear-gradient(135deg,#0d6efd,#0a58ca); color:#fff; border:none; padding:.55rem 1.6rem; border-radius:8px; font-weight:600; cursor:pointer;">
                            <i class="fas fa-save me-1"></i> Save Interview
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for Viewing Details -->
    <div id="detailsModal" class="modal">
        <div class="modal-content" style="max-width:520px; border-radius:16px; overflow:hidden; padding:0;">
            <div class="modal-header" style="background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:1.25rem 1.5rem; display:flex; justify-content:space-between; align-items:center; border-radius:0;">
                <h2 style="margin:0; font-size:1.15rem; font-weight:700; color:#fff;"><i class="fas fa-calendar-check me-2"></i>Booking Details</h2>
                <button class="close-btn" onclick="closeDetailsModal();" style="background:rgba(255,255,255,.2); border:none; color:#fff; width:32px; height:32px; border-radius:8px; font-size:1.1rem; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
            </div>
            <div id="detailsContent" style="padding:1.5rem;"></div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
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
                        list.innerHTML = '<div class="notif-empty">No new bookings</div>';
                        return;
                    }
                    list.innerHTML = data.items.map(item => `
                        <div class="notif-item" style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;">
                            <div style="flex:1">
                                <strong><i class="fas fa-calendar-check me-1" style="color:#800000;"></i>${item.title}</strong>
                                <span style="display:block; margin-top:2px;">${item.message}</span>
                                <span style="color:#aaa;">${timeAgo(item.created_at)}</span>
                            </div>
                            <button style="flex-shrink:0;background:none;border:none;color:#dc3545;cursor:pointer;font-size:.9rem;padding:.25rem .5rem;border-radius:4px" onclick="deleteNotif(${item.id}, event)" title="Delete">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `).join('');
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
                    document.getElementById('notifList').innerHTML = '<div class="notif-empty">No new bookings</div>';
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

        document.addEventListener('click', e => {
            const wrap = document.getElementById('notifWrap');
            if (wrap && !wrap.contains(e.target)) {
                document.getElementById('notifDropdown').classList.remove('open');
            }
        });

        pollNotifCount();
        setInterval(pollNotifCount, 30000);

        const API_URL = '/GAMBYTES_Final/api/booking-management.php';

        let currentPage = 1;
        let bookingsPerPage = 25;
        let currentFilters = {};
        
        // Load initial data
        window.addEventListener('DOMContentLoaded', function() {
            loadStatistics();
            loadBookings();
        });
        
        function loadStatistics() {
            fetch(`${API_URL}?action=stats`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (document.getElementById('totalBookings'))
                            document.getElementById('totalBookings').textContent = data.data.total;
                        if (document.getElementById('bookedCount'))
                            document.getElementById('bookedCount').textContent = data.data.booked;
                        if (document.getElementById('approvedCount'))
                            document.getElementById('approvedCount').textContent = data.data.approved || 0;
                        if (document.getElementById('completedCount'))
                            document.getElementById('completedCount').textContent = data.data.completed;
                        if (document.getElementById('cancelledCount'))
                            document.getElementById('cancelledCount').textContent = data.data.cancelled;
                    }
                })
                .catch(error => console.error('Error loading statistics:', error));
        }
        
        function loadBookings(page = 1) {
            currentPage = page;
            const offset = (page - 1) * bookingsPerPage;
            
            let url = `${API_URL}?action=all-bookings&limit=${bookingsPerPage}&offset=${offset}`;
            
            // Add filters to URL
            if (currentFilters.status) {
                url += `&status=${encodeURIComponent(currentFilters.status)}`;
            }
            if (currentFilters.search) {
                url += `&search=${encodeURIComponent(currentFilters.search)}`;
            }
            if (currentFilters.dateFrom) {
                url += `&date_from=${encodeURIComponent(currentFilters.dateFrom)}`;
            }
            if (currentFilters.dateTo) {
                url += `&date_to=${encodeURIComponent(currentFilters.dateTo)}`;
            }
            
            document.getElementById('loadingContainer').style.display = 'block';
            document.getElementById('bookingsTable').style.display = 'none';
            document.getElementById('noDataContainer').style.display = 'none';
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loadingContainer').style.display = 'none';
                    
                    if (data.success && data.data && data.data.length > 0) {
                        renderBookingsTable(data.data);
                        renderPagination(data.pagination);
                        document.getElementById('bookingsTable').style.display = 'table';
                    } else {
                        document.getElementById('noDataContainer').style.display = 'block';
                        document.getElementById('paginationContainer').innerHTML = '';
                    }
                })
                .catch(error => {
                    console.error('Error loading bookings:', error);
                    document.getElementById('loadingContainer').style.display = 'none';
                    document.getElementById('noDataContainer').style.display = 'block';
                });
        }
        
        function renderBookingsTable(bookings) {
            const tbody = document.getElementById('bookingsTableBody');
            tbody.innerHTML = '';
            
            bookings.forEach(booking => {
                const startTime = new Date(booking.start_time);
                const endTime = new Date(booking.end_time);
                const createdAt = new Date(booking.created_at);
                
                const bookingStatus = booking.status || 'booked';
                const statusClass = `status-${bookingStatus}`;
                
                const statusColors = {
                    'booked':      { bg:'#fff3cd', color:'#856404' },
                    'approved':    { bg:'#d1e7dd', color:'#0f5132' },
                    'interviewed': { bg:'#cfe2ff', color:'#084298' },
                    'admission':   { bg:'#e0d7ff', color:'#4a0080' },
                    'completed':   { bg:'#d1e7dd', color:'#0f5132' },
                    'cancelled':   { bg:'#f8d7da', color:'#842029' },
                    'no_show':     { bg:'#e2e3e5', color:'#41464b' },
                };
                const sc = statusColors[bookingStatus] || { bg:'#fff3cd', color:'#856404' };
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${booking.id}</td>
                    <td>${booking.name}</td>
                    <td>${booking.email}</td>
                    <td>${startTime.toLocaleString()}</td>
                    <td>${endTime.toLocaleString()}</td>
                    <td><span class="status-badge ${statusClass}" style="background:${sc.bg};color:${sc.color};padding:.3rem .75rem;border-radius:20px;font-size:.78rem;font-weight:700;">${bookingStatus.toUpperCase()}</span></td>
                    <td>${createdAt.toLocaleDateString()}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-action btn-view" onclick="viewBookingDetails(${booking.id})">
                                <i class="fas fa-eye"></i> View
                            </button>
                            ${(bookingStatus === 'booked' || bookingStatus === '') ? `
                                <button class="btn-action" style="background:#28a745;" onclick="approveBooking(${booking.id})">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            ` : ''}
                            ${bookingStatus === 'approved' ? `
                                <button class="btn-action" style="background:#0d6efd;" onclick="conductInterview(${booking.id}, '${booking.name.replace(/'/g,"\\'")}', '${booking.email}')">
                                    <i class="fas fa-comments"></i> Interview
                                </button>
                            ` : ''}
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
        
        function renderPagination(pagination) {
            const container = document.getElementById('paginationContainer');
            container.innerHTML = '';
            
            const totalPages = pagination.pages;
            const currentPage = Math.floor(pagination.offset / pagination.limit) + 1;
            
            // Previous button
            if (currentPage > 1) {
                const prevBtn = document.createElement('button');
                prevBtn.textContent = 'Previous';
                prevBtn.classList.add('btn-action');
                prevBtn.onclick = () => loadBookings(currentPage - 1);
                container.appendChild(prevBtn);
            }
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = i;
                pageBtn.classList.add('btn-action');
                if (i === currentPage) {
                    pageBtn.style.backgroundColor = '#3498db';
                    pageBtn.style.color = 'white';
                }
                pageBtn.onclick = () => loadBookings(i);
                container.appendChild(pageBtn);
            }
            
            // Next button
            if (currentPage < totalPages) {
                const nextBtn = document.createElement('button');
                nextBtn.textContent = 'Next';
                nextBtn.classList.add('btn-action');
                nextBtn.onclick = () => loadBookings(currentPage + 1);
                container.appendChild(nextBtn);
            }
            
            // Info text
            const info = document.createElement('div');
            info.style.marginTop = '10px';
            info.style.color = '#7f8c8d';
            info.textContent = `Page ${currentPage} of ${totalPages} (Total: ${pagination.total})`;
            container.appendChild(info);
        }
        
        function applyFilters() {
            currentFilters = {
                search: document.getElementById('searchInput').value,
                status: document.getElementById('statusFilter').value,
                dateFrom: document.getElementById('dateFromFilter').value,
                dateTo: document.getElementById('dateToFilter').value
            };
            
            loadBookings(1); // Reset to first page
            loadStatistics();
        }
        
        function viewBookingDetails(bookingId) {
            fetch(`${API_URL}?action=booking-detail&id=${bookingId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const booking = data.data;
                        // normalize empty status
                        if (!booking.status || booking.status === '') booking.status = 'booked';
                        const startTime = new Date(booking.start_time);
                        const endTime = new Date(booking.end_time);
                        const createdAt = new Date(booking.created_at);
                        
                        const statusColors = {
                            booked:      { bg:'#fff3cd', color:'#856404', icon:'fa-clock' },
                            approved:    { bg:'#d1e7dd', color:'#0f5132', icon:'fa-check-circle' },
                            interviewed: { bg:'#cfe2ff', color:'#084298', icon:'fa-comments' },
                            admission:   { bg:'#e0d7ff', color:'#4a0080', icon:'fa-hospital' },
                            completed:   { bg:'#d1ecf1', color:'#0c5460', icon:'fa-flag-checkered' },
                            cancelled:   { bg:'#f8d7da', color:'#842029', icon:'fa-times-circle' },
                            no_show:     { bg:'#e2e3e5', color:'#41464b', icon:'fa-user-slash' }
                        };
                        const sc = statusColors[booking.status] || { bg:'#e2e3e5', color:'#41464b', icon:'fa-circle' };

                        const content = `
                            <div style="display:grid; gap:.85rem;">
                                <div style="display:flex; align-items:center; gap:.75rem; padding:.75rem 1rem; background:#f8f9fa; border-radius:10px; border-left:4px solid #800000;">
                                    <i class="fas fa-hashtag" style="color:#800000; width:18px; text-align:center;"></i>
                                    <div>
                                        <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Booking ID</div>
                                        <div style="font-weight:700; color:#212529;">#${booking.id}</div>
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; gap:.75rem; padding:.75rem 1rem; background:#f8f9fa; border-radius:10px; border-left:4px solid #800000;">
                                    <i class="fas fa-user" style="color:#800000; width:18px; text-align:center;"></i>
                                    <div>
                                        <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Name</div>
                                        <div style="font-weight:600; color:#212529;">${booking.name}</div>
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; gap:.75rem; padding:.75rem 1rem; background:#f8f9fa; border-radius:10px; border-left:4px solid #800000;">
                                    <i class="fas fa-envelope" style="color:#800000; width:18px; text-align:center;"></i>
                                    <div>
                                        <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Email</div>
                                        <div style="font-weight:600; color:#212529;">${booking.email}</div>
                                    </div>
                                </div>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:.85rem;">
                                    <div style="display:flex; align-items:center; gap:.75rem; padding:.75rem 1rem; background:#f8f9fa; border-radius:10px; border-left:4px solid #800000;">
                                        <i class="fas fa-play-circle" style="color:#800000; width:18px; text-align:center;"></i>
                                        <div>
                                            <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Start Time</div>
                                            <div style="font-weight:600; color:#212529; font-size:.88rem;">${startTime.toLocaleString()}</div>
                                        </div>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:.75rem; padding:.75rem 1rem; background:#f8f9fa; border-radius:10px; border-left:4px solid #800000;">
                                        <i class="fas fa-stop-circle" style="color:#800000; width:18px; text-align:center;"></i>
                                        <div>
                                            <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">End Time</div>
                                            <div style="font-weight:600; color:#212529; font-size:.88rem;">${endTime.toLocaleString()}</div>
                                        </div>
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; gap:.75rem; padding:.75rem 1rem; background:#f8f9fa; border-radius:10px; border-left:4px solid #800000;">
                                    <i class="fas fa-${sc.icon}" style="color:#800000; width:18px; text-align:center;"></i>
                                    <div>
                                        <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Status</div>
                                        <span style="display:inline-block; margin-top:2px; padding:.2rem .75rem; border-radius:20px; font-size:.8rem; font-weight:700; background:${sc.bg}; color:${sc.color};">${booking.status.toUpperCase()}</span>
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; gap:.75rem; padding:.75rem 1rem; background:#f8f9fa; border-radius:10px; border-left:4px solid #800000;">
                                    <i class="fas fa-calendar-plus" style="color:#800000; width:18px; text-align:center;"></i>
                                    <div>
                                        <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Date Booked</div>
                                        <div style="font-weight:600; color:#212529;">${createdAt.toLocaleString()}</div>
                                    </div>
                                </div>
                                ${booking.notes ? `
                                <div style="display:flex; align-items:flex-start; gap:.75rem; padding:.75rem 1rem; background:#fff8e1; border-radius:10px; border-left:4px solid #ffc107;">
                                    <i class="fas fa-sticky-note" style="color:#ffc107; width:18px; text-align:center; margin-top:2px;"></i>
                                    <div>
                                        <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Notes</div>
                                        <div style="font-weight:500; color:#212529; white-space:pre-wrap;">${booking.notes}</div>
                                    </div>
                                </div>` : ''}
                            </div>
                            <div style="margin-top:1.25rem; text-align:right;">
                                <button onclick="closeDetailsModal()" style="background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border:none; padding:.5rem 1.5rem; border-radius:8px; font-weight:600; cursor:pointer; font-size:.9rem;">Close</button>
                            </div>
                        `;
                        
                        document.getElementById('detailsContent').innerHTML = content;
                        document.getElementById('detailsModal').classList.add('active');
                    }
                })
                .catch(error => console.error('Error loading booking details:', error));
        }
        
        function editBooking(bookingId) {
            fetch(`${API_URL}?action=booking-detail&id=${bookingId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const booking = data.data;
                        document.getElementById('editBookingId').value = booking.id;
                        document.getElementById('editBookingName').value = booking.name;
                        document.getElementById('editBookingEmail').value = booking.email;
                        document.getElementById('editStatusSelect').value = booking.status;
                        document.getElementById('editNotesTextarea').value = '';
                        
                        // Store booking ID in data attribute
                        document.getElementById('editModal').dataset.bookingId = booking.id;
                        
                        document.getElementById('editModal').classList.add('active');
                    }
                })
                .catch(error => console.error('Error loading booking for edit:', error));
        }
        
        function saveBookingStatus(event) {
            event.preventDefault();
            
            const bookingId = document.getElementById('editModal').dataset.bookingId;
            const newStatus = document.getElementById('editStatusSelect').value;
            const notes = document.getElementById('editNotesTextarea').value;
            
            // Update status
            fetch(`${API_URL}?action=update-status`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    booking_id: bookingId,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add notes if provided
                    if (notes.trim()) {
                        return fetch(`${API_URL}?action=add-notes`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                booking_id: bookingId,
                                notes: notes
                            })
                        });
                    }
                    return Promise.resolve();
                } else {
                    throw new Error(data.error);
                }
            })
            .then(() => {
                alert('Booking updated successfully!');
                closeEditModal();
                loadBookings(currentPage);
                loadStatistics();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating booking: ' + error.message);
            });
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.remove('active');
        }

        function approveBooking(bookingId) {
            if (!confirm('Approve this booking? The gambler will be notified.')) return;

            fetch('/GAMBYTES_Final/api/approve_booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_id: bookingId })
            })
            .then(r => r.text())
            .then(raw => {
                let data;
                try { data = JSON.parse(raw); } catch(e) {
                    alert('Server error:\n' + raw.substring(0, 300));
                    return;
                }
                if (data.success) {
                    loadBookings(currentPage);
                    loadStatistics();
                } else {
                    alert('Error: ' + (data.error || JSON.stringify(data)));
                }
            })
            .catch(err => alert('Network error: ' + err.message));
        }
        
        function conductInterview(bookingId, name, email) {
            window.location.href = '/GAMBYTES_Final/app/views/Users/Supervisor/conduct-interview.php?booking_id=' + bookingId;
        }

        function closeInterviewModal() {
            document.getElementById('interviewModal').classList.remove('active');
        }

        document.getElementById('interviewForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const bookingId = document.getElementById('ivBookingId').value;
            const payload = {
                booking_id:        bookingId,
                gambling_history:  document.getElementById('iv_gambling_history').value,
                health_assessment: document.getElementById('iv_health_assessment').value,
                social_background: document.getElementById('iv_social_background').value,
                treatment_goals:   document.getElementById('iv_treatment_goals').value,
                remarks:           document.getElementById('iv_remarks').value
            };

            fetch('/GAMBYTES_Final/api/interview.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Initial interview saved successfully!');
                    closeInterviewModal();
                    loadBookings(currentPage);
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => alert('Error: ' + err.message));
        });

        // Close interview modal on outside click
        window.addEventListener('click', function(event) {
            const editModal = document.getElementById('editModal');
            const detailsModal = document.getElementById('detailsModal');
            const interviewModal = document.getElementById('interviewModal');

            if (event.target == editModal)      editModal.classList.remove('active');
            if (event.target == detailsModal)   detailsModal.classList.remove('active');
            if (event.target == interviewModal) interviewModal.classList.remove('active');
        });
    </script>
</body>
</html>
