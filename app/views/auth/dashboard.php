<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information from database
require_once "../../core/Database.php";
$db = new Database();
$conn = $db->connect();

$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id='$user_id'";
$result = $conn->query($sql);
$user = $result->fetch_assoc();
$role = $user['role'];
$full_name = $user['first_name'] . ' ' . $user['last_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gambytes</title>
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <h3><i class="fas fa-bars"></i> Navigation</h3>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                
                <?php if ($role === 'supervisor' || $role === 'admin'): ?>
                    <li><a href="booking-management.php"><i class="fas fa-calendar-check"></i> Booking Management</a></li>
                <?php endif; ?>
                
                <li><a href="#profile"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/direct-calendly.php"><i class="fas fa-calendar-plus"></i> Book Rehabilitation</a></li>
                
                <?php if ($role === 'gambler'): ?>
                    <li><a href="#contract"><i class="fas fa-file-contract"></i> My Contracts</a></li>
                    <li><a href="#treatment"><i class="fas fa-chart-line"></i> Treatment Progress</a></li>
                <?php elseif ($role === 'admin'): ?>
                    <li><a href="#admin-panel"><i class="fas fa-cog"></i> Admin Panel</a></li>
                    <li><a href="#users"><i class="fas fa-users"></i> Users</a></li>
                <?php elseif ($role === 'case_manager'): ?>
                    <li><a href="#cases"><i class="fas fa-folder-open"></i> My Cases</a></li>
                <?php elseif ($role === 'nurse'): ?>
                    <li><a href="#medications"><i class="fas fa-pills"></i> Medications</a></li>
                    <li><a href="#patients"><i class="fas fa-heartbeat"></i> Patients</a></li>
                <?php elseif ($role === 'supervisor'): ?>
                    <li><a href="#supervision"><i class="fas fa-eye"></i> Supervision</a></li>
                <?php endif; ?>
                
                <li><a href="#reports"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="#messages"><i class="fas fa-envelope"></i> Messages</a></li>
                <li><a href="#notifications"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <p>Welcome back, <strong><?php echo $full_name; ?></strong>! | <strong>Role:</strong> <?php echo ucfirst(str_replace('_', ' ', $role)); ?></p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">12</div>
                    <div class="stat-label">Active Items</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">48</div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">6</div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">95%</div>
                    <div class="stat-label">Performance</div>
                </div>
            </div>
            
            <!-- Welcome Card -->
            <div class="content-card" style="margin-top: 25px;">
                <h2><i class="fas fa-hand-wave"></i> Welcome to Gambytes</h2>
                <p>You are logged in as <strong><?php echo $full_name; ?></strong> with the role of <strong><?php echo ucfirst(str_replace('_', ' ', $role)); ?></strong>.</p>
                <p>This dashboard provides you with a comprehensive view of your account status, important metrics, and quick access to all the tools you need.</p>
                
                <h3><i class="fas fa-info-circle"></i> System Information</h3>
                <p><i class="fas fa-database"></i> <strong>System:</strong> Gambytes Gambling Recovery Management System</p>
                <p><i class="fas fa-calendar"></i> <strong>Current Date:</strong> <?php echo date('F j, Y'); ?></p>
                <p><i class="fas fa-clock"></i> <strong>Current Time:</strong> <?php echo date('h:i A'); ?></p>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions" style="margin-top: 25px;">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                <ul>
                    <?php if ($role === 'gambler'): ?>
                        <li>Book a rehabilitation consultation</li>
                        <li>View your treatment progress</li>
                        <li>Review your contracts</li>
                        <li>Check your appointments</li>
                    <?php elseif ($role === 'supervisor' || $role === 'admin'): ?>
                        <li>Manage rehabilitation bookings</li>
                        <li>View booking statistics</li>
                        <li>Generate reports</li>
                        <li>Monitor team activities</li>
                    <?php elseif ($role === 'case_manager'): ?>
                        <li>Manage your assigned cases</li>
                        <li>Update case progress</li>
                        <li>Document patient interactions</li>
                        <li>Schedule follow-ups</li>
                    <?php elseif ($role === 'nurse'): ?>
                        <li>Review patient medications</li>
                        <li>Check patient visits</li>
                        <li>Update patient records</li>
                        <li>Schedule appointments</li>
                    <?php endif; ?>
                    <li>Update your profile information</li>
                    <li>Check your notifications</li>
                    <li>Access help and support</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
