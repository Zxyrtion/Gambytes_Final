<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is supervisor or admin
require_once "../../core/Database.php";
$db = new Database();
$conn = $db->connect();

$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id='$user_id'";
$result = $conn->query($sql);
$user = $result->fetch_assoc();
$role = $user['role'];

// Only allow supervisors and admins
if (!in_array($role, ['supervisor', 'admin'])) {
    header("Location: dashboard.php");
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
</head>
<body>
    <div class="page-container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <h3><i class="fas fa-bars"></i> Navigation</h3>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="booking-management.php" class="active"><i class="fas fa-calendar-check"></i> Booking Management</a></li>
                <li><a href="dashboard.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-calendar-check"></i> Booking Management</h1>
                <p>Welcome, <strong><?php echo $full_name; ?></strong>! Manage all rehabilitation bookings here.</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-container" id="statsContainer">
                <div class="stat-card">
                    <div class="stat-number" id="totalBookings">0</div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="bookedCount">0</div>
                    <div class="stat-label">Booked</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="completedCount">0</div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="cancelledCount">0</div>
                    <div class="stat-label">Cancelled</div>
                </div>
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
    
    <!-- Modal for Editing Booking -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Booking</h2>
                <button class="close-btn" onclick="closeEditModal();">&times;</button>
            </div>
            <form onsubmit="saveBookingStatus(event);">
                <div class="form-group">
                    <label>Booking ID</label>
                    <input type="text" id="editBookingId" disabled>
                </div>
                <div class="form-group">
                    <label>Booking Owner</label>
                    <input type="text" id="editBookingName" disabled>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="editBookingEmail" disabled>
                </div>
                <div class="form-group">
                    <label for="editStatusSelect">Status</label>
                    <select id="editStatusSelect">
                        <option value="booked">Booked</option>
                        <option value="completed">Completed</option>
                        <option value="no_show">No Show</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editNotesTextarea">Add Notes</label>
                    <textarea id="editNotesTextarea" placeholder="Add supervisor notes..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeEditModal();">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal for Viewing Details -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Booking Details</h2>
                <button class="close-btn" onclick="closeDetailsModal();">&times;</button>
            </div>
            <div id="detailsContent"></div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
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
                        document.getElementById('totalBookings').textContent = data.data.total;
                        document.getElementById('bookedCount').textContent = data.data.booked;
                        document.getElementById('completedCount').textContent = data.data.completed;
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
                    
                    if (data.success && data.data.length > 0) {
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
                
                const statusClass = `status-${booking.status}`;
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${booking.id}</td>
                    <td>${booking.name}</td>
                    <td>${booking.email}</td>
                    <td>${startTime.toLocaleString()}</td>
                    <td>${endTime.toLocaleString()}</td>
                    <td><span class="status-badge ${statusClass}">${booking.status.toUpperCase()}</span></td>
                    <td>${createdAt.toLocaleDateString()}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-action btn-view" onclick="viewBookingDetails(${booking.id})">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn-action btn-edit" onclick="editBooking(${booking.id})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
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
                        const startTime = new Date(booking.start_time);
                        const endTime = new Date(booking.end_time);
                        const createdAt = new Date(booking.created_at);
                        
                        const content = `
                            <div class="form-group">
                                <label>Booking ID</label>
                                <input type="text" value="${booking.id}" disabled>
                            </div>
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" value="${booking.name}" disabled>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" value="${booking.email}" disabled>
                            </div>
                            <div class="form-group">
                                <label>Start Time</label>
                                <input type="text" value="${startTime.toLocaleString()}" disabled>
                            </div>
                            <div class="form-group">
                                <label>End Time</label>
                                <input type="text" value="${endTime.toLocaleString()}" disabled>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <input type="text" value="${booking.status.toUpperCase()}" disabled>
                            </div>
                            <div class="form-group">
                                <label>Calendly URL</label>
                                <input type="text" value="${booking.calendly_event_uri || 'N/A'}" disabled>
                            </div>
                            <div class="form-group">
                                <label>Date Booked</label>
                                <input type="text" value="${createdAt.toLocaleString()}" disabled>
                            </div>
                            ${booking.notes ? `
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea disabled>${booking.notes}</textarea>
                                </div>
                            ` : ''}
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
        
        // Close modals when clicking outside of them
        window.addEventListener('click', function(event) {
            const editModal = document.getElementById('editModal');
            const detailsModal = document.getElementById('detailsModal');
            
            if (event.target == editModal) {
                editModal.classList.remove('active');
            }
            if (event.target == detailsModal) {
                detailsModal.classList.remove('active');
            }
        });
    </script>
</body>
</html>
