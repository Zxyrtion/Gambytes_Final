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
$stmt    = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header("Location: " . url('app/views/auth/login.php'));
    exit();
}

$user      = $result->fetch_assoc();
$full_name = $user['first_name'] . ' ' . $user['last_name'];
$email     = $user['email'] ?? '';
$stmt->close();

// For gamblers: find their latest booking with an interview score >= 4
$gamblerContractUrl = '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php';
if ($user['role'] === 'gambler') {
    $bStmt = $conn->prepare("
        SELECT br.id 
        FROM booking_record br
        JOIN initial_interview_record ii ON ii.booking_id = br.id
        WHERE br.email = ? AND ii.score >= 4
        ORDER BY br.created_at DESC LIMIT 1
    ");
    $bStmt->bind_param('s', $email);
    $bStmt->execute();
    $bRow = $bStmt->get_result()->fetch_assoc();
    $bStmt->close();
    if ($bRow) {
        $gamblerContractUrl = '/GAMBYTES_Final/app/views/Users/Gamblers/contract/fill-contract.php?booking_id=' . $bRow['id'];
    }
}

// ── Auto-create tables if missing ─────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `booking_record` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `calendly_event_uri` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    `start_time` DATETIME DEFAULT NULL,
    `end_time` DATETIME DEFAULT NULL,
    `status` ENUM('booked','approved','interviewed','completed','cancelled','no_show') NOT NULL DEFAULT 'booked',
    `notes` LONGTEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email` (`email`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'general',
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NULL,
    `link` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Handle booking submission ──────────────────────────────────────────────
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_submit'])) {
    $b_name       = trim($_POST['b_name']       ?? $full_name);
    $b_email      = trim($_POST['b_email']      ?? $email);
    $b_date       = $_POST['b_date']       ?? '';
    $b_time_start = $_POST['b_time_start'] ?? '';
    $b_time_end   = $_POST['b_time_end']   ?? '';
    $b_reason     = trim($_POST['b_reason'] ?? '');

    if ($b_date && $b_time_start && $b_time_end) {
        $start_dt  = date('Y-m-d H:i:s', strtotime("$b_date $b_time_start"));
        $end_dt    = date('Y-m-d H:i:s', strtotime("$b_date $b_time_end"));
        $event_uri = 'manual_' . $user_id . '_' . time();
        $b_status  = 'booked';

        // ── Check if slot is already taken ────────────────────────────────
        $chk = $conn->prepare(
            "SELECT id FROM booking_record
             WHERE start_time = ? AND (status NOT IN ('cancelled','no_show') OR status IS NULL OR status = '')
             LIMIT 1"
        );
        $chk->bind_param('s', $start_dt);
        $chk->execute();
        $chk->store_result();
        $slotTaken = $chk->num_rows > 0;
        $chk->close();

        if ($slotTaken) {
            $error_msg = "Sorry, that time slot has already been booked. Please choose another.";
        } else {

        $ins = $conn->prepare(
            "INSERT INTO booking_record
             (email, name, start_time, end_time, status, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        if (!$ins) {
            $error_msg = "Database error: " . $conn->error;
        } else {
        $ins->bind_param('sssss', $b_email, $b_name, $start_dt, $end_dt, $b_status);

        if ($ins->execute()) {
            $booking_id = $conn->insert_id;
            $ins->close();

            // ── Push notification to all supervisors/admins ───────────────
            $formatted_date = date('M j, Y g:i A', strtotime($start_dt));
            $notif_title    = "New Booking: {$b_name}";
            $notif_message  = "{$b_name} booked a rehabilitation session on {$formatted_date}.";
            $notif_link     = '/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php';
            $notif_type     = 'new_booking';

            $sup = $conn->query("SELECT id FROM users WHERE role IN ('supervisor','admin')");
            if ($sup) {
                $nStmt = $conn->prepare(
                    "INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                     VALUES (?, ?, ?, ?, ?, 0, NOW())"
                );
                if ($nStmt) {
                    while ($sRow = $sup->fetch_assoc()) {
                        $sid = $sRow['id'];
                        $nStmt->bind_param('issss', $sid, $notif_type, $notif_title, $notif_message, $notif_link);
                        $nStmt->execute();
                    }
                    $nStmt->close();
                }
            }

            $_SESSION['last_booking'] = [
                'id'         => $booking_id,
                'name'       => $b_name,
                'email'      => $b_email,
                'start_time' => $start_dt,
                'end_time'   => $end_dt,
                'status'     => 'booked',
            ];

            header("Location: /GAMBYTES_Final/app/views/Users/Gamblers/booking-confirmation.php");
            exit();
        } else {
            $error_msg = "Failed to save booking: " . $ins->error;
            $ins->close();
        }
        } // end else (!$ins)
        } // end else (slot not taken)
    } else {
        $error_msg = "Please select a date and time slot before confirming.";
    }
}

// ── Fetch already-booked slots so we can grey them out ────────────────────
$booked_slots = [];
$bq = $conn->query("SELECT DATE(start_time) as bdate, TIME_FORMAT(start_time,'%H:%i') as btime
                    FROM booking_record WHERE (status NOT IN ('cancelled','no_show') OR status IS NULL OR status = '')");
while ($row = $bq->fetch_assoc()) {
    $booked_slots[$row['bdate']][] = $row['btime'];
}
$booked_json = json_encode($booked_slots);

// ── Fetch this user's own bookings ────────────────────────────────────────
$my_bookings = [];
$mbq = $conn->prepare(
    "SELECT id, name, start_time, end_time, status, created_at
     FROM booking_record
     WHERE email = ?
     ORDER BY start_time DESC"
);
$mbq->bind_param('s', $email);
$mbq->execute();
$mbr = $mbq->get_result();
while ($row = $mbr->fetch_assoc()) {
    $my_bookings[] = $row;
}
$mbq->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Rehabilitation - Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css?v=<?= time() ?>">
    <style>
        /* ── Calendar ── */
        .rehab-calendar-wrap { background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); overflow:hidden; }
        .cal-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:1.25rem 1.5rem; display:flex; align-items:center; justify-content:space-between; }
        .cal-header h5 { margin:0; font-weight:700; font-size:1.1rem; }
        .cal-nav-btn { background:rgba(255,255,255,.2); border:none; color:#fff; width:34px; height:34px; border-radius:8px; cursor:pointer; font-size:1rem; transition:.2s; display:flex; align-items:center; justify-content:center; }
        .cal-nav-btn:hover { background:rgba(255,255,255,.35); }
        .cal-grid { padding:1rem 1.25rem 1.25rem; }
        .cal-weekdays { display:grid; grid-template-columns:repeat(7,1fr); text-align:center; margin-bottom:.5rem; }
        .cal-weekdays span { font-size:.75rem; font-weight:700; color:#6c757d; padding:.4rem 0; text-transform:uppercase; }
        .cal-days { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; }
        .cal-day { height:42px; display:flex; align-items:center; justify-content:center; border-radius:10px; font-size:.9rem; font-weight:500; cursor:pointer; transition:all .2s; border:2px solid transparent; color:#343a40; }
        .cal-day:hover:not(.cal-empty):not(.cal-past):not(.cal-fully-booked) { background:rgba(128,0,0,.1); border-color:#800000; color:#800000; }
        .cal-day.cal-today { border-color:#800000; color:#800000; font-weight:700; }
        .cal-day.cal-selected { background:linear-gradient(135deg,#800000,#5c0000); color:#fff !important; border-color:#800000; }
        .cal-day.cal-past { color:#ccc; cursor:not-allowed; }
        .cal-day.cal-empty { cursor:default; }
        .cal-day.cal-has-slots::after { content:''; display:block; width:5px; height:5px; background:#28a745; border-radius:50%; position:absolute; bottom:4px; }
        .cal-day.cal-has-slots { position:relative; }
        .cal-day.cal-fully-booked { background:#f8d7da; color:#721c24; cursor:not-allowed; }

        /* ── Time Slots ── */
        .time-slots-panel { background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); overflow:hidden; }
        .time-slots-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:1rem 1.5rem; font-weight:700; display:flex; align-items:center; gap:.5rem; }
        .time-slots-body { padding:1.25rem; }
        .slots-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(110px,1fr)); gap:.6rem; }
        .slot-btn { padding:.6rem .5rem; border:2px solid #dee2e6; border-radius:10px; background:#fff; font-size:.85rem; font-weight:600; color:#343a40; cursor:pointer; transition:all .2s; text-align:center; }
        .slot-btn:hover:not(.slot-booked) { border-color:#800000; color:#800000; background:rgba(128,0,0,.05); }
        .slot-btn.slot-selected { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border-color:#800000; }
        .slot-btn.slot-booked { background:#f8f9fa; color:#adb5bd; cursor:not-allowed; border-color:#e9ecef; text-decoration:line-through; }
        .no-date-msg { color:#6c757d; font-size:.95rem; text-align:center; padding:2rem 1rem; }

        /* ── Booking Summary ── */
        .booking-summary { background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); overflow:hidden; }
        .summary-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:1rem 1.5rem; font-weight:700; display:flex; align-items:center; gap:.5rem; }
        .summary-body { padding:1.5rem; }
        .summary-row { display:flex; align-items:flex-start; gap:.75rem; padding:.6rem 0; border-bottom:1px solid #f0f0f0; }
        .summary-row:last-child { border-bottom:none; }
        .summary-icon { width:36px; height:36px; background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.85rem; flex-shrink:0; }
        .summary-label { font-size:.78rem; color:#6c757d; }
        .summary-value { font-weight:600; color:#343a40; font-size:.95rem; }
        .btn-confirm { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border:none; border-radius:12px; padding:.9rem 2rem; font-weight:700; font-size:1rem; width:100%; cursor:pointer; transition:all .3s; margin-top:1rem; }
        .btn-confirm:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 6px 20px rgba(128,0,0,.35); }
        .btn-confirm:disabled { opacity:.5; cursor:not-allowed; }

        /* ── Page header ── */
        .rehab-page-header { margin-bottom:1.75rem; }
        .rehab-page-header h1 { color:#800000; font-size:1.9rem; font-weight:800; }
        .rehab-page-header p { color:#6c757d; }
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
                <div class="user-role"><?= ucfirst(str_replace('_', ' ', $user['role'] ?? '')) ?></div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php">
                <i class="fas fa-home"></i> Overview
            </a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/BookRehab.php" class="active">
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
            <li><a href="#">
                <i class="fas fa-user"></i> Profile
            </a></li>
            <div class="menu-divider"></div>
            <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="rehab-page-header">
            <h1><i class="fas fa-calendar-plus me-2"></i>Book Rehabilitation</h1>
            <p>Welcome, <strong><?= htmlspecialchars($full_name) ?></strong>! Pick a date and available time slot below.</p>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger mb-3"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="rehabForm">
            <input type="hidden" name="book_submit" value="1">
            <input type="hidden" name="b_date"       id="hDate">
            <input type="hidden" name="b_time_start" id="hTimeStart">
            <input type="hidden" name="b_time_end"   id="hTimeEnd">

            <div class="row g-4" style="align-items:stretch;">

                <!-- LEFT: Patient Info + Summary -->
                <div class="col-lg-4" style="display:flex; flex-direction:column;">

                    <!-- Patient Info -->
                    <div class="booking-summary mb-4">
                        <div class="summary-header"><i class="fas fa-user-edit"></i> Your Details</div>
                        <div class="summary-body">
                            <div class="form-group mb-3">
                                <label class="form-label fw-bold" style="font-size:.85rem;">Full Name</label>
                                <input type="text" name="b_name" class="form-control"
                                       value="<?= htmlspecialchars($full_name) ?>" required>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label fw-bold" style="font-size:.85rem;">Email</label>
                                <input type="email" name="b_email" class="form-control"
                                       value="<?= htmlspecialchars($email) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label fw-bold" style="font-size:.85rem;">Reason for Visit <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea name="b_reason" class="form-control" rows="3"
                                          placeholder="Brief description..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Booking Summary -->
                    <div class="booking-summary">
                        <div class="summary-header"><i class="fas fa-calendar-check"></i> Booking Summary</div>
                        <div class="summary-body">
                            <div class="summary-row">
                                <div class="summary-icon"><i class="fas fa-calendar"></i></div>
                                <div>
                                    <div class="summary-label">Selected Date</div>
                                    <div class="summary-value" id="sumDate">—</div>
                                </div>
                            </div>
                            <div class="summary-row">
                                <div class="summary-icon"><i class="fas fa-clock"></i></div>
                                <div>
                                    <div class="summary-label">Time Slot</div>
                                    <div class="summary-value" id="sumTime">—</div>
                                </div>
                            </div>
                            <div class="summary-row">
                                <div class="summary-icon"><i class="fas fa-stethoscope"></i></div>
                                <div>
                                    <div class="summary-label">Service</div>
                                    <div class="summary-value">Rehabilitation Consultation</div>
                                </div>
                            </div>
                            <button type="submit" class="btn-confirm" id="confirmBtn" disabled>
                                <i class="fas fa-check-circle me-2"></i>Confirm Booking
                            </button>
                        </div>
                    </div>

                </div>

                <!-- RIGHT: Calendar + Time Slots -->
                <div class="col-lg-8">

                    <!-- Calendar -->
                    <div class="rehab-calendar-wrap mb-4">
                        <div class="cal-header">
                            <button type="button" class="cal-nav-btn" id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                            <h5 id="calMonthLabel"></h5>
                            <button type="button" class="cal-nav-btn" id="nextMonth"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <div class="cal-grid">
                            <div class="cal-weekdays">
                                <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span>
                                <span>Thu</span><span>Fri</span><span>Sat</span>
                            </div>
                            <div class="cal-days" id="calDays"></div>
                        </div>
                    </div>

                    <!-- Time Slots -->
                    <div class="time-slots-panel">
                        <div class="time-slots-header">
                            <i class="fas fa-clock"></i>
                            <span id="slotsTitle">Available Time Slots</span>
                        </div>
                        <div class="time-slots-body">
                            <div id="slotsContainer">
                                <p class="no-date-msg"><i class="fas fa-hand-point-up me-2"></i>Select a date on the calendar to see available slots.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>

        <!-- ── My Booked Schedules ── -->
        <div class="rehab-calendar-wrap mt-4">
            <div class="cal-header" style="border-radius:0;">
                <i class="fas fa-list-alt me-2"></i>
                <span style="font-weight:700; font-size:1.05rem;">My Booked Schedules</span>
            </div>
            <div style="padding:1.25rem;">
                <?php if (empty($my_bookings)): ?>
                    <p class="no-date-msg" style="padding:2rem 1rem;">
                        <i class="fas fa-calendar-times me-2"></i>You have no bookings yet.
                    </p>
                <?php else: ?>
                <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:.9rem;">
                    <thead>
                        <tr style="background:linear-gradient(135deg,#800000,#5c0000); color:#fff;">
                            <th style="padding:.75rem 1rem; text-align:left; font-weight:600;">#</th>
                            <th style="padding:.75rem 1rem; text-align:left; font-weight:600;">Date</th>
                            <th style="padding:.75rem 1rem; text-align:left; font-weight:600;">Time</th>
                            <th style="padding:.75rem 1rem; text-align:left; font-weight:600;">Service</th>
                            <th style="padding:.75rem 1rem; text-align:left; font-weight:600;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_bookings as $i => $bk):
                            $statusColors = [
                                'booked'    => ['bg'=>'#d4edda','color'=>'#155724','label'=>'Booked'],
                                'cancelled' => ['bg'=>'#f8d7da','color'=>'#721c24','label'=>'Cancelled'],
                                'completed' => ['bg'=>'#d1ecf1','color'=>'#0c5460','label'=>'Completed'],
                                'no_show'   => ['bg'=>'#fff3cd','color'=>'#856404','label'=>'No Show'],
                            ];
                            $sc = $statusColors[$bk['status']] ?? ['bg'=>'#e9ecef','color'=>'#495057','label'=>ucfirst($bk['status'])];
                        ?>
                        <tr style="border-bottom:1px solid #f0f0f0; <?= $i % 2 === 0 ? '' : 'background:#fafafa;' ?>">
                            <td style="padding:.75rem 1rem; color:#6c757d;"><?= $i + 1 ?></td>
                            <td style="padding:.75rem 1rem; font-weight:600;">
                                <?= date('F j, Y', strtotime($bk['start_time'])) ?>
                            </td>
                            <td style="padding:.75rem 1rem;">
                                <?= date('g:i A', strtotime($bk['start_time'])) ?>
                                &ndash;
                                <?= date('g:i A', strtotime($bk['end_time'])) ?>
                            </td>
                            <td style="padding:.75rem 1rem;">Rehabilitation Consultation</td>
                            <td style="padding:.75rem 1rem;">
                                <span style="background:<?= $sc['bg'] ?>; color:<?= $sc['color'] ?>;
                                             padding:.3rem .85rem; border-radius:20px; font-weight:600; font-size:.8rem;">
                                    <?= $sc['label'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Booked slots from PHP ──────────────────────────────────────────────────
const BOOKED = <?= $booked_json ?>;

// Debug: Show booked slots
console.log('BOOKED slots:', BOOKED);

// ── Time slots offered each day (1-hour blocks, 8 AM – 5 PM) ─────────────
const ALL_SLOTS = [];
for (let h = 8; h < 17; h++) {
    const start = `${String(h).padStart(2,'0')}:00`;
    const end   = `${String(h + 1).padStart(2,'0')}:00`;
    ALL_SLOTS.push({ start, end, label: fmt12(start) + ' – ' + fmt12(end) });
}

console.log('ALL_SLOTS:', ALL_SLOTS);

function fmt12(t) {
    const [h, m] = t.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12  = h % 12 || 12;
    return `${h12}:${String(m).padStart(2,'0')} ${ampm}`;
}

// ── Calendar state ─────────────────────────────────────────────────────────
const today = new Date();
today.setHours(0,0,0,0);
let viewYear  = today.getFullYear();
let viewMonth = today.getMonth();
let selectedDate = null;
let selectedSlot = null;

const MONTHS = ['January','February','March','April','May','June',
                'July','August','September','October','November','December'];

function renderCalendar() {
    document.getElementById('calMonthLabel').textContent = `${MONTHS[viewMonth]} ${viewYear}`;
    const grid = document.getElementById('calDays');
    grid.innerHTML = '';

    const firstDay = new Date(viewYear, viewMonth, 1).getDay();
    const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

    // Empty cells before first day
    for (let i = 0; i < firstDay; i++) {
        const el = document.createElement('div');
        el.className = 'cal-day cal-empty';
        grid.appendChild(el);
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(viewYear, viewMonth, d);
        const dateStr = `${viewYear}-${String(viewMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const el = document.createElement('div');
        el.className = 'cal-day';
        el.textContent = d;

        const isPast = date < today;
        const isToday = date.getTime() === today.getTime();
        const bookedCount = (BOOKED[dateStr] || []).length;
        const isFullyBooked = bookedCount >= ALL_SLOTS.length;

        if (isPast)          el.classList.add('cal-past');
        else if (isFullyBooked) el.classList.add('cal-fully-booked');
        else {
            if (bookedCount > 0) el.classList.add('cal-has-slots');
            el.addEventListener('click', () => selectDate(dateStr, el));
        }
        if (isToday) el.classList.add('cal-today');
        if (selectedDate === dateStr) el.classList.add('cal-selected');

        grid.appendChild(el);
    }
}

function selectDate(dateStr, el) {
    selectedDate = dateStr;
    selectedSlot = null;
    document.getElementById('hDate').value = dateStr;
    document.getElementById('hTimeStart').value = '';
    document.getElementById('hTimeEnd').value = '';
    updateSummary();
    renderCalendar();
    renderSlots(dateStr);
}

function renderSlots(dateStr) {
    const container = document.getElementById('slotsContainer');
    const title     = document.getElementById('slotsTitle');
    const d = new Date(dateStr + 'T00:00:00');
    title.textContent = `Available Slots — ${MONTHS[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;

    const bookedToday = BOOKED[dateStr] || [];
    console.log(`Booked slots for ${dateStr}:`, bookedToday);
    
    const grid = document.createElement('div');
    grid.className = 'slots-grid';

    ALL_SLOTS.forEach(slot => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'slot-btn';
        btn.textContent = slot.label;

        const isBooked = bookedToday.includes(slot.start);
        console.log(`Slot ${slot.start}: booked=${isBooked}, bookedToday=`, bookedToday);
        
        if (isBooked) {
            btn.classList.add('slot-booked');
            btn.disabled = true;
        } else {
            btn.addEventListener('click', () => pickSlot(slot, btn));
        }
        grid.appendChild(btn);
    });

    container.innerHTML = '';
    container.appendChild(grid);
}

function pickSlot(slot, btn) {
    document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('slot-selected'));
    btn.classList.add('slot-selected');
    selectedSlot = slot;
    document.getElementById('hTimeStart').value = slot.start;
    document.getElementById('hTimeEnd').value   = slot.end;
    updateSummary();
}

function updateSummary() {
    const sumDate = document.getElementById('sumDate');
    const sumTime = document.getElementById('sumTime');
    const btn     = document.getElementById('confirmBtn');

    if (selectedDate) {
        const d = new Date(selectedDate + 'T00:00:00');
        sumDate.textContent = `${MONTHS[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;
    } else {
        sumDate.textContent = '—';
    }

    if (selectedSlot) {
        sumTime.textContent = selectedSlot.label;
    } else {
        sumTime.textContent = '—';
    }

    btn.disabled = !(selectedDate && selectedSlot);
}

// ── Navigation ─────────────────────────────────────────────────────────────
document.getElementById('prevMonth').addEventListener('click', () => {
    viewMonth--;
    if (viewMonth < 0) { viewMonth = 11; viewYear--; }
    renderCalendar();
});
document.getElementById('nextMonth').addEventListener('click', () => {
    viewMonth++;
    if (viewMonth > 11) { viewMonth = 0; viewYear++; }
    renderCalendar();
});

// ── Init ───────────────────────────────────────────────────────────────────
renderCalendar();
</script>
</body>
</html>
