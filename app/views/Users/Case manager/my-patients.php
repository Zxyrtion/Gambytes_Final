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

if (!$user || $user['role'] !== 'case_manager') {
    header("Location: " . url('app/views/auth/dashboard.php')); exit();
}
$full_name = $user['first_name'] . ' ' . $user['last_name'];

// Ensure activity tables exist
$conn->query("CREATE TABLE IF NOT EXISTS `treatment_activities` (
    `id` INT(11) NOT NULL AUTO_INCREMENT, `booking_id` INT(11) NOT NULL,
    `gambler_id` INT(11) NOT NULL, `created_by` INT(11) NOT NULL,
    `title` VARCHAR(255) NOT NULL, `description` TEXT NULL,
    `document_path` VARCHAR(500) NULL, `document_name` VARCHAR(255) NULL,
    `open_date` DATE NOT NULL, `close_date` DATE NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `activity_submissions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT, `activity_id` INT(11) NOT NULL,
    `gambler_id` INT(11) NOT NULL, `file_path` VARCHAR(500) NULL,
    `file_name` VARCHAR(255) NULL, `notes` TEXT NULL,
    `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Load all patients who have completed interviews (score >= 4 = eligible for treatment)
$patients = [];
$pStmt = $conn->query(
    "SELECT
        br.id            AS booking_id,
        br.name          AS patient_name,
        br.email         AS patient_email,
        br.start_time,
        br.status        AS booking_status,
        ii.id            AS interview_id,
        ii.score,
        ii.diagnosis,
        ii.remarks,
        ii.q1, ii.q2, ii.q3, ii.q4, ii.q5,
        ii.q6, ii.q7, ii.q8, ii.q9,
        ii.created_at    AS interview_date,
        CONCAT(iv.first_name,' ',iv.last_name) AS interviewer_name,
        u.id             AS user_id,
        -- payment status
        (SELECT payment_status FROM payments WHERE booking_id = br.id AND payment_status IN ('paid','verified') LIMIT 1) AS payment_status,
        -- receipt id (for verified payments)
        (SELECT r.id FROM receipts r JOIN payments p2 ON p2.id = r.payment_id WHERE p2.booking_id = br.id LIMIT 1) AS receipt_id,
        -- activity count
        (SELECT COUNT(*) FROM treatment_activities WHERE booking_id = br.id) AS activity_count,
        -- submission count
        (SELECT COUNT(*) FROM activity_submissions sub
         JOIN treatment_activities ta ON ta.id = sub.activity_id
         WHERE ta.booking_id = br.id) AS submission_count
     FROM booking_record br
     JOIN Initial_Interview_Record ii ON ii.booking_id = br.id
     LEFT JOIN users u ON LOWER(u.email) COLLATE utf8mb4_unicode_ci = LOWER(br.email) COLLATE utf8mb4_unicode_ci AND u.role = 'gambler'
     LEFT JOIN users iv ON iv.id = ii.interviewer_id
     WHERE ii.score >= 4
     ORDER BY ii.score DESC, br.created_at DESC"
);
if ($pStmt) $patients = $pStmt->fetch_all(MYSQLI_ASSOC);

function severityBadge($score) {
    if ($score >= 8) return ['label'=>'Severe',   'bg'=>'#f8d7da','color'=>'#842029'];
    if ($score >= 6) return ['label'=>'Moderate', 'bg'=>'#fff3cd','color'=>'#664d03'];
    if ($score >= 4) return ['label'=>'Mild',     'bg'=>'#d1e7dd','color'=>'#0f5132'];
    return                  ['label'=>'At-Risk',  'bg'=>'#e2e3e5','color'=>'#41464b'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Patients &ndash; Gambytes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
<style>
.main-content{margin-left:260px;flex:1;padding:2rem}
.fc-card{background:#fff;border-radius:16px;box-shadow:0 2px 15px rgba(0,0,0,.08);overflow:hidden;margin-bottom:1.5rem}
.fc-card-header{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;padding:1rem 1.5rem;font-weight:700;font-size:1rem;display:flex;align-items:center;gap:.6rem}
.fc-card-body{padding:1.5rem}
.patient-row{border:1.5px solid #e9ecef;border-radius:12px;padding:1.25rem 1.5rem;margin-bottom:1rem;background:#fff;transition:box-shadow .2s}
.patient-row:hover{box-shadow:0 4px 16px rgba(0,0,0,.08)}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.65rem;margin-bottom:1rem}
.info-box{padding:.6rem .85rem;background:#f8f9fa;border-radius:8px;border-left:3px solid #800000}
.info-box .lbl{font-size:.7rem;color:#6c757d;font-weight:600;text-transform:uppercase}
.info-box .val{font-weight:700;color:#212529;font-size:.88rem;margin-top:1px}
.btn-maroon{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;border:none;border-radius:10px;padding:.55rem 1.25rem;font-weight:700;font-size:.88rem;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;text-decoration:none;transition:all .2s}
.btn-maroon:hover{opacity:.88;color:#fff}
.top-navbar{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.08);padding:1rem 1.5rem;margin-bottom:2rem;display:flex;justify-content:space-between;align-items:center}
.stat-card{border-radius:14px;padding:1.25rem;border-left:5px solid}
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
.search-box{border:1.5px solid #dee2e6;border-radius:10px;padding:.5rem 1rem;font-size:.9rem;width:260px;outline:none;transition:.2s}
.search-box:focus{border-color:#800000;box-shadow:0 0 0 3px rgba(128,0,0,.1)}
</style>
</head>
<body>
<div class="dashboard-container">
<div class="sidebar">
<div class="sidebar-header">
<div class="sidebar-logo"><img src="/GAMBYTES_Final/public/images/Logo.png" alt="Logo"><span>Gambytes</span></div>
<div class="sidebar-user">
<div class="user-name"><i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($full_name) ?></div>
<div class="user-role">Case Manager</div>
</div>
</div>
<ul class="sidebar-menu">
<li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Case manager/my-patients.php" class="active"><i class="fas fa-users"></i> My Patients</a></li>
<li><a href="#"><i class="fas fa-chart-line"></i> Treatment Progress</a></li>
<li><a href="#"><i class="fas fa-calendar-alt"></i> Schedule</a></li>
<li><a href="#"><i class="fas fa-file-medical"></i> Reports</a></li>
<div class="menu-divider"></div>
<li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
<li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul>
</div>

<div class="main-content">
<!-- Navbar -->
<div class="top-navbar">
<span style="font-weight:700;font-size:1rem;color:#800000">Case Manager Portal</span>
<div style="display:flex;align-items:center;gap:1rem">
<input type="text" class="search-box" id="searchInput" placeholder="Search patient..." oninput="filterPatients()">
<div class="notif-bell-wrap" id="notifWrap">
<button type="button" class="notif-bell-btn" onclick="toggleNotifDropdown()">
<i class="fas fa-bell"></i><span class="notif-badge" id="notifBadge" style="display:none">0</span>
</button>
<div class="notif-dropdown" id="notifDropdown">
<div class="notif-dropdown-header"><span><i class="fas fa-bell me-1"></i> Notifications</span><button onclick="markAllSeen()" class="notif-mark-btn">Mark all read</button></div>
<div id="notifList"><div class="notif-empty">No notifications</div></div>
</div>
</div>
</div>
</div>

<div style="margin-bottom:1.75rem">
<h1 style="color:#800000;font-size:1.8rem;font-weight:800;margin:0"><i class="fas fa-user-md me-2"></i>Patient Management</h1>
<p style="color:#6c757d;margin:.25rem 0 0">View patient interview results, payment status, and manage treatment activities.</p>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.75rem">
<?php
$total   = count($patients);
$paid    = count(array_filter($patients, fn($p) => !empty($p['payment_status'])));
$severe  = count(array_filter($patients, fn($p) => $p['score'] >= 8));
$pending = count(array_filter($patients, fn($p) => empty($p['payment_status'])));
?>
<div class="stat-card" style="background:#e8f4fd;border-color:#0d6efd">
<div style="font-size:2rem;font-weight:800;color:#0d6efd"><?= $total ?></div>
<div style="font-size:.85rem;color:#0d6efd;font-weight:600">Total Patients</div>
</div>
<div class="stat-card" style="background:#d1e7dd;border-color:#198754">
<div style="font-size:2rem;font-weight:800;color:#0f5132"><?= $paid ?></div>
<div style="font-size:.85rem;color:#0f5132;font-weight:600">Payment Confirmed</div>
</div>
<div class="stat-card" style="background:#fff3cd;border-color:#ffc107">
<div style="font-size:2rem;font-weight:800;color:#664d03"><?= $pending ?></div>
<div style="font-size:.85rem;color:#664d03;font-weight:600">Awaiting Payment</div>
</div>
<div class="stat-card" style="background:#f8d7da;border-color:#dc3545">
<div style="font-size:2rem;font-weight:800;color:#842029"><?= $severe ?></div>
<div style="font-size:.85rem;color:#842029;font-weight:600">Severe Cases</div>
</div>
</div>

<!-- Patient List -->
<div class="fc-card">
<div class="fc-card-header"><i class="fas fa-users"></i> Patient List</div>
<div class="fc-card-body">
<?php if (empty($patients)): ?>
<div style="text-align:center;padding:3rem;color:#6c757d">
<i class="fas fa-user-slash fa-3x mb-3" style="opacity:.3;display:block"></i>
<h5>No patients yet</h5>
<p style="font-size:.9rem">Patients will appear here once they complete their initial interview with a qualifying score.</p>
</div>
<?php else: ?>
<div id="patientList">
<?php foreach ($patients as $p):
    $sev = severityBadge($p['score']);
    $hasPaid = !empty($p['payment_status']);
?>
<div class="patient-row" data-name="<?= strtolower(htmlspecialchars($p['patient_name'])) ?>">
<div class="info-grid">
<div class="info-box">
<div class="lbl">Patient</div>
<div class="val"><?= htmlspecialchars($p['patient_name']) ?></div>
</div>
<div class="info-box">
<div class="lbl">Email</div>
<div class="val" style="font-size:.78rem"><?= htmlspecialchars($p['patient_email']) ?></div>
</div>
<div class="info-box">
<div class="lbl">Interview Score</div>
<div class="val">
<span style="background:<?= $sev['bg'] ?>;color:<?= $sev['color'] ?>;padding:.2rem .65rem;border-radius:20px;font-size:.78rem;font-weight:700">
<?= $p['score'] ?>/9 – <?= $sev['label'] ?>
</span>
</div>
</div>
<div class="info-box">
<div class="lbl">Diagnosis</div>
<div class="val" style="font-size:.78rem"><?= htmlspecialchars($p['diagnosis'] ?? '—') ?></div>
</div>
<div class="info-box">
<div class="lbl">Payment</div>
<div class="val">
<?php if ($hasPaid): ?>
<span style="background:#d1e7dd;color:#0f5132;padding:.2rem .65rem;border-radius:20px;font-size:.78rem;font-weight:700"><i class="fas fa-check-circle me-1"></i><?= ucfirst($p['payment_status']) ?></span>
<?php else: ?>
<span style="background:#fff3cd;color:#664d03;padding:.2rem .65rem;border-radius:20px;font-size:.78rem;font-weight:700"><i class="fas fa-clock me-1"></i>Pending</span>
<?php endif; ?>
</div>
</div>
<div class="info-box">
<div class="lbl">Activities</div>
<div class="val">
<?= (int)$p['activity_count'] ?> assigned
<?php if ($p['submission_count'] > 0): ?>
<span style="color:#198754;font-size:.78rem;margin-left:.3rem">(<?= (int)$p['submission_count'] ?> submitted)</span>
<?php endif; ?>
</div>
</div>
</div>
<div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
<?php if ($hasPaid): ?>
<a href="/GAMBYTES_Final/app/views/Users/Case manager/patient-activities.php?booking_id=<?= (int)$p['booking_id'] ?>" class="btn-maroon">
<i class="fas fa-tasks"></i> Manage Activities
</a>
<?php if ($p['payment_status'] === 'verified' && !empty($p['receipt_id'])): ?>
<a href="/GAMBYTES_Final/app/views/Users/admin%20department/payment/view-receipt.php?receipt_id=<?= (int)$p['receipt_id'] ?>" class="btn-maroon" style="background:linear-gradient(135deg,#198754,#146c43)" target="_blank">
<i class="fas fa-receipt"></i> View Receipt
</a>
<?php endif; ?>
<?php else: ?>
<span style="background:#f8f9fa;color:#6c757d;border:1.5px solid #dee2e6;border-radius:10px;padding:.5rem 1.1rem;font-size:.85rem;font-weight:600;display:inline-flex;align-items:center;gap:.4rem">
<i class="fas fa-lock"></i> Awaiting Payment
</span>
<?php endif; ?>
<button class="btn-maroon" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)"
    onclick='openInterviewModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)'>
    <i class="fas fa-clipboard-list"></i> View Interview
</button>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>

</div><!-- /main-content -->
</div><!-- /dashboard-container -->

<!-- ── Interview Record Modal ── -->
<div id="interviewModalOverlay" onclick="closeInterviewModalOnOverlay(event)"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:#fff;border-radius:18px;width:100%;max-width:760px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <div style="background:linear-gradient(135deg,#800000,#5c0000);color:#fff;padding:1.25rem 1.5rem;display:flex;justify-content:space-between;align-items:center;border-radius:18px 18px 0 0;position:sticky;top:0;z-index:1;">
            <h2 style="margin:0;font-size:1.1rem;font-weight:700;color:#fff"><i class="fas fa-clipboard-check me-2"></i>Interview Record</h2>
            <button onclick="closeInterviewModal()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:34px;height:34px;border-radius:8px;font-size:1.2rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">&times;</button>
        </div>
        <div id="interviewModalBody" style="padding:1.5rem"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const INTERVIEW_QUESTIONS = {
    1: "Have you often found yourself thinking about gambling (e.g., reliving past gambling experiences, planning the next time you will play or thinking of ways to get money to gamble)?",
    2: "Have you needed to gamble with more and more money to get the amount of excitement you are looking for?",
    3: "Have you become restless or irritable when trying to cut down or stop gambling?",
    4: "Have you gambled to escape from problems or when you are feeling depressed, anxious or bad about yourself?",
    5: "After losing money gambling, have you returned another day in order to get even?",
    6: "Have you lied to your family or others to hide the extent of your gambling?",
    7: "Have you made repeated unsuccessful attempts to control, cut back or stop gambling?",
    8: "Have you risked or lost a significant relationship, job, educational or career opportunity because of gambling?",
    9: "Have you sought help from others to provide the money to relieve a desperate financial situation caused by gambling?",
};

function getSevInfo(score) {
    if (score >= 8) return { label:'Severe',        cls:'background:#f8d7da;color:#842029' };
    if (score >= 6) return { label:'Moderate',      cls:'background:#fff3cd;color:#856404' };
    if (score >= 4) return { label:'Mild',          cls:'background:#d1e7dd;color:#0f5132' };
    if (score > 0)  return { label:'At-Risk',       cls:'background:#cfe2ff;color:#084298' };
    return                 { label:'No Indicators', cls:'background:#e2e3e5;color:#41464b' };
}

function openInterviewModal(p) {
    const score = parseInt(p.score, 10);
    const sev   = getSevInfo(score);

    let sevDesc = '';
    if (score >= 8)      sevDesc = 'Severe: 8–9 criteria met. Immediate intensive treatment is recommended.';
    else if (score >= 6) sevDesc = 'Moderate: 6–7 criteria met. Structured treatment program is recommended.';
    else if (score >= 4) sevDesc = 'Mild: 4–5 criteria met. Counseling and support services are recommended.';
    else if (score > 0)  sevDesc = 'Less than 4 criteria met — potential problem / at-risk indicators.';
    else                 sevDesc = 'No significant gambling disorder indicators found.';

    let dsmRows = '';
    for (let n = 1; n <= 9; n++) {
        const ans = parseInt(p['q' + n], 10);
        dsmRows += `<tr style="border-bottom:1px solid #f0f0f0">
            <td style="padding:.75rem 1rem;font-weight:700;color:#800000;width:32px">${n}.</td>
            <td style="padding:.75rem 1rem;font-size:.86rem;color:#343a40">${escHtml(INTERVIEW_QUESTIONS[n])}</td>
            <td style="padding:.75rem 1rem;text-align:center;width:80px">
                ${ans === 1
                    ? '<span style="background:#d1e7dd;color:#0f5132;padding:.2rem .75rem;border-radius:20px;font-weight:700;font-size:.8rem">Yes</span>'
                    : '<span style="background:#f8d7da;color:#842029;padding:.2rem .75rem;border-radius:20px;font-weight:700;font-size:.8rem">No</span>'}
            </td>
        </tr>`;
    }

    const remarksHtml = p.remarks
        ? `<div style="margin-top:1rem;padding:1rem;background:#fff8e1;border-radius:10px;border-left:4px solid #ffc107">
               <div style="font-size:.72rem;color:#6c757d;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.3rem">
                   <i class="fas fa-sticky-note me-1" style="color:#ffc107"></i> Interviewer's Remarks
               </div>
               <div style="font-size:.9rem;color:#343a40">${escHtml(p.remarks).replace(/\n/g,'<br>')}</div>
           </div>` : '';

    document.getElementById('interviewModalBody').innerHTML = `
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;margin-bottom:1.25rem">
            <div style="padding:.75rem 1rem;background:#f8f9fa;border-radius:10px;border-left:4px solid #800000">
                <div style="font-size:.72rem;color:#6c757d;font-weight:600;text-transform:uppercase">Patient</div>
                <div style="font-weight:700;color:#212529;margin-top:2px">${escHtml(p.patient_name)}</div>
            </div>
            <div style="padding:.75rem 1rem;background:#f8f9fa;border-radius:10px;border-left:4px solid #800000">
                <div style="font-size:.72rem;color:#6c757d;font-weight:600;text-transform:uppercase">Email</div>
                <div style="font-weight:700;color:#212529;margin-top:2px;font-size:.82rem">${escHtml(p.patient_email)}</div>
            </div>
            <div style="padding:.75rem 1rem;background:#f8f9fa;border-radius:10px;border-left:4px solid #800000">
                <div style="font-size:.72rem;color:#6c757d;font-weight:600;text-transform:uppercase">Interview Date</div>
                <div style="font-weight:700;color:#212529;margin-top:2px">${formatDate(p.interview_date)}</div>
            </div>
            <div style="padding:.75rem 1rem;background:#f8f9fa;border-radius:10px;border-left:4px solid #800000">
                <div style="font-size:.72rem;color:#6c757d;font-weight:600;text-transform:uppercase">Interviewer</div>
                <div style="font-weight:700;color:#212529;margin-top:2px">${escHtml(p.interviewer_name || 'N/A')}</div>
            </div>
        </div>
        <div style="overflow-x:auto;margin-bottom:1.25rem">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:linear-gradient(135deg,#800000,#5c0000);color:#fff">
                        <th style="padding:.7rem 1rem;font-weight:600;font-size:.83rem;width:40px">#</th>
                        <th style="padding:.7rem 1rem;font-weight:600;font-size:.83rem">Question (In the Past Year…)</th>
                        <th style="padding:.7rem 1rem;font-weight:600;font-size:.83rem;text-align:center;width:80px">Answer</th>
                    </tr>
                </thead>
                <tbody>${dsmRows}</tbody>
            </table>
        </div>
        <div style="display:flex;align-items:center;gap:1.25rem;padding:1rem 1.25rem;background:#f8f9fa;border-radius:12px;flex-wrap:wrap">
            <div>
                <div style="font-size:.72rem;color:#6c757d;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Score</div>
                <div style="font-size:2.5rem;font-weight:800;color:#800000;line-height:1">${score}<span style="font-size:1.2rem;color:#6c757d">/9</span></div>
            </div>
            <div>
                <div style="font-size:.72rem;color:#6c757d;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.3rem">Severity</div>
                <span style="${sev.cls};padding:.3rem .85rem;border-radius:20px;font-weight:700;font-size:.85rem">${sev.label}</span>
                <div style="font-size:.78rem;color:#6c757d;margin-top:.4rem">${sevDesc}</div>
            </div>
            <div>
                <div style="font-size:.72rem;color:#6c757d;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.3rem">Diagnosis</div>
                <div style="font-size:.88rem;font-weight:600;color:#343a40">${escHtml(p.diagnosis || '—')}</div>
            </div>
        </div>
        ${remarksHtml}
        <div style="margin-top:.75rem;font-size:.75rem;color:#adb5bd;font-style:italic">
            Adapted from the American Psychiatric Association Diagnostic Criteria from the DSM V 2013
        </div>
    `;

    const overlay = document.getElementById('interviewModalOverlay');
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeInterviewModal() {
    document.getElementById('interviewModalOverlay').style.display = 'none';
    document.body.style.overflow = '';
}
function closeInterviewModalOnOverlay(e) {
    if (e.target === document.getElementById('interviewModalOverlay')) closeInterviewModal();
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeInterviewModal(); });

function filterPatients() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.patient-row').forEach(row => {
        row.style.display = row.dataset.name.includes(q) ? '' : 'none';
    });
}
function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' });
}
function toggleNotifDropdown(){const dd=document.getElementById('notifDropdown');dd.classList.toggle('open');if(dd.classList.contains('open'))loadNotifs();}
document.addEventListener('click',e=>{const w=document.getElementById('notifWrap');if(w&&!w.contains(e.target))document.getElementById('notifDropdown').classList.remove('open');});
function goNotif(link){if(link&&link!=='#')window.location.href=link;}
function loadNotifs(){fetch('/GAMBYTES_Final/api/notifications.php?action=list').then(r=>r.json()).then(d=>{const l=document.getElementById('notifList');if(!d.items||!d.items.length){l.innerHTML='<div class="notif-empty">No notifications</div>';return;}l.innerHTML=d.items.map(n=>`<div class="notif-item" onclick="goNotif(${JSON.stringify(n.link||'')})">${n.link?'<i class="fas fa-external-link-alt me-1" style="color:#800000;font-size:.7rem"></i>':''}<strong>${escHtml(n.title)}</strong><span>${escHtml(n.message||'')}</span></div>`).join('');});}
function markAllSeen(){fetch('/GAMBYTES_Final/api/notifications.php?action=mark_seen').then(()=>{document.getElementById('notifBadge').style.display='none';});}
function pollNotif(){fetch('/GAMBYTES_Final/api/notifications.php?action=count').then(r=>r.json()).then(d=>{const b=document.getElementById('notifBadge');if(d.count>0){b.textContent=d.count;b.style.display='flex';}else b.style.display='none';});}
pollNotif(); setInterval(pollNotif,30000);
</script>
</body>
</html>
