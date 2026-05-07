
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
if (!$user || $user['role'] !== 'gambler') { header("Location: " . url('app/views/auth/dashboard.php')); exit(); }
$full_name = $user['first_name'] . ' ' . $user['last_name'];

// Ensure cbt_session_progress table exists
$conn->query("CREATE TABLE IF NOT EXISTS `cbt_session_progress` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `booking_id` INT(11) NOT NULL,
    `gambler_id` INT(11) NULL,
    `session_number` INT(11) NOT NULL,
    `status` ENUM('locked','unlocked','completed') DEFAULT 'locked',
    `unlocked_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `unlocked_by` INT(11) NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_session` (`booking_id`, `session_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure session file uploads table exists
$conn->query("CREATE TABLE IF NOT EXISTS `cbt_session_files` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `booking_id` INT(11) NOT NULL,
    `gambler_id` INT(11) NOT NULL,
    `session_number` INT(11) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `notes` TEXT NULL,
    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_booking_session` (`booking_id`, `session_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Find booking for this gambler
$bStmt = $conn->prepare(
    "SELECT br.id AS booking_id FROM booking_record br
     WHERE LOWER(br.email) = LOWER(?)
     LIMIT 1"
);
if (!$bStmt) { $no_booking = true; } else {
$bStmt->bind_param('s', $user['email']); $bStmt->execute();
$booking = $bStmt->get_result()->fetch_assoc(); $bStmt->close();
}

if (!$booking) { $no_booking = true; } else {
    $booking_id = (int)$booking['booking_id'];
    // Load progress
    $progress = [];
    $pStmt = $conn->prepare("SELECT * FROM cbt_session_progress WHERE booking_id = ? ORDER BY session_number");
    $pStmt->bind_param('i', $booking_id); $pStmt->execute();
    foreach ($pStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $progress[$row['session_number']] = $row;
    $pStmt->close();
    // Load uploaded files per session
    $files = [];
    $fStmt = $conn->prepare("SELECT * FROM cbt_session_files WHERE booking_id = ? AND gambler_id = ? ORDER BY session_number, uploaded_at DESC");
    $fStmt->bind_param('ii', $booking_id, $user_id); $fStmt->execute();
    foreach ($fStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $files[$row['session_number']][] = $row;
    $fStmt->close();
}

// Handle file upload POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_session_file') {
    header('Content-Type: application/json');
    $sess_num   = (int)($_POST['session_number'] ?? 0);
    $bid        = (int)($_POST['booking_id']     ?? 0);
    $notes      = trim($_POST['notes']           ?? '');
    if (!$sess_num || !$bid || empty($_FILES['session_file']['name'])) {
        echo json_encode(['success'=>false,'message'=>'Missing required fields']); exit();
    }
    $upload_dir = __DIR__ . '/../../Case manager/Session_activity/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $ext     = strtolower(pathinfo($_FILES['session_file']['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf','doc','docx','jpg','jpeg','png'];
    if (!in_array($ext, $allowed)) { echo json_encode(['success'=>false,'message'=>'File type not allowed']); exit(); }
    $safe = 'gambler_' . $user_id . '_s' . $sess_num . '_' . time() . '.' . $ext;
    if (!move_uploaded_file($_FILES['session_file']['tmp_name'], $upload_dir . $safe)) {
        echo json_encode(['success'=>false,'message'=>'Upload failed']); exit();
    }
    $fpath = '/GAMBYTES_Final/app/views/Users/Case manager/Session_activity/' . $safe;
    $fname = $_FILES['session_file']['name'];
    $ins = $conn->prepare("INSERT INTO cbt_session_files (booking_id, gambler_id, session_number, file_path, file_name, notes) VALUES (?,?,?,?,?,?)");
    $ins->bind_param('iiisss', $bid, $user_id, $sess_num, $fpath, $fname, $notes);
    $ins->execute(); $ins->close();
    echo json_encode(['success'=>true,'file_name'=>$fname,'file_path'=>$fpath]); exit();
}

function statusBadge($s) {
    if ($s==='completed') return ['label'=>'Completed','bg'=>'#d1e7dd','color'=>'#0f5132','icon'=>'check-circle'];
    if ($s==='unlocked')  return ['label'=>'Available','bg'=>'#fff3cd','color'=>'#664d03','icon'=>'unlock'];
    return                       ['label'=>'Locked',   'bg'=>'#f8d7da','color'=>'#842029','icon'=>'lock'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My CBT Sessions &ndash; Gambytes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
<style>
.main-content{margin-left:260px;flex:1;padding:2rem}
.fc-card{background:#fff;border-radius:16px;box-shadow:0 2px 15px rgba(0,0,0,.08);overflow:hidden;margin-bottom:1.5rem}
.fc-card-header{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;padding:1rem 1.5rem;font-weight:700;font-size:1rem;display:flex;align-items:center;gap:.6rem}
.fc-card-body{padding:1.5rem}
.session-card{border:2px solid #e9ecef;border-radius:16px;padding:1.5rem;margin-bottom:1.25rem;background:#fff;transition:all .2s;position:relative}
.session-card.available{border-color:#ffc107;background:#fffdf5}
.session-card.completed{border-color:#198754;background:#f8fff9}
.session-number{position:absolute;top:-12px;left:1.5rem;background:linear-gradient(135deg,#800000,#5c0000);color:#fff;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.9rem;box-shadow:0 2px 8px rgba(128,0,0,.3)}
.content-panel{display:none;margin-top:1rem;padding:1.25rem;background:#fafafa;border-radius:12px;border:1px solid #e9ecef}
.content-panel.open{display:block}
.btn-maroon{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;border:none;border-radius:10px;padding:.5rem 1.1rem;font-weight:700;font-size:.85rem;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;text-decoration:none;transition:all .2s}
.btn-maroon:hover{opacity:.88;color:#fff}
.upload-zone{border:2px dashed #dee2e6;border-radius:10px;padding:1rem;text-align:center;cursor:pointer;transition:.2s;background:#fff}
.upload-zone:hover{border-color:#800000;background:#fff8f0}
.file-item{display:flex;align-items:center;gap:.6rem;padding:.5rem .75rem;background:#f8f9fa;border-radius:8px;margin-bottom:.4rem;font-size:.83rem}
</style>
</head>
<body>
<div class="dashboard-container">
<div class="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo"><img src="/GAMBYTES_Final/public/images/Logo.png" alt="Logo"><span>Gambytes</span></div>
    <div class="sidebar-user">
      <div class="user-name"><i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($full_name) ?></div>
      <div class="user-role">Gambler</div>
    </div>
  </div>
  <ul class="sidebar-menu">
    <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
    <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/BookRehab.php"><i class="fas fa-calendar-plus"></i> Book Rehabilitation</a></li>
    <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php"><i class="fas fa-clipboard-list"></i> My Interview</a></li>
    <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-cbt-sessions.php" class="active"><i class="fas fa-brain"></i> CBT Sessions</a></li>
    <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-activities.php"><i class="fas fa-tasks"></i> My Activities</a></li>
    <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/parental-control-requests.php"><i class="fas fa-shield-alt"></i> Parental Access</a></li>
    <div class="menu-divider"></div>
    <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
  </ul>
</div>
<div class="main-content">
  <div style="margin-bottom:1.75rem">
    <h1 style="color:#800000;font-size:1.8rem;font-weight:800;margin:0"><i class="fas fa-brain me-2"></i>My CBT Sessions</h1>
    <p style="color:#6c757d;margin:.25rem 0 0">Cognitive Behavioral Therapy Program for Gambling Recovery</p>
  </div>
