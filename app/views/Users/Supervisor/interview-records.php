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
$role = $user['role'];

if (!in_array($role, ['supervisor', 'admin'])) {
    header("Location: " . url('app/views/auth/dashboard.php'));
    exit();
}

$full_name = $user['first_name'] . ' ' . $user['last_name'];

$questions = [
    1 => "Have you often found yourself thinking about gambling (e.g., reliving past gambling experiences, planning the next time you will play or thinking of ways to get money to gamble)?",
    2 => "Have you needed to gamble with more and more money to get the amount of excitement you are looking for?",
    3 => "Have you become restless or irritable when trying to cut down or stop gambling?",
    4 => "Have you gambled to escape from problems or when you are feeling depressed, anxious or bad about yourself?",
    5 => "After losing money gambling, have you returned another day in order to get even?",
    6 => "Have you lied to your family or others to hide the extent of your gambling?",
    7 => "Have you made repeated unsuccessful attempts to control, cut back or stop gambling?",
    8 => "Have you risked or lost a significant relationship, job, educational or career opportunity because of gambling?",
    9 => "Have you sought help from others to provide the money to relieve a desperate financial situation caused by gambling?",
];

function getSeverity($score) {
    if ($score >= 8) return ['label' => 'Severe',        'class' => 'diag-severe'];
    if ($score >= 6) return ['label' => 'Moderate',      'class' => 'diag-moderate'];
    if ($score >= 4) return ['label' => 'Mild',          'class' => 'diag-mild'];
    if ($score > 0)  return ['label' => 'At-Risk',       'class' => 'diag-atrisk'];
    return                  ['label' => 'No Indicators', 'class' => 'diag-none'];
}

// Fetch all completed interview records
$records = [];
$rStmt = $conn->prepare(
    "SELECT
        ii.id            AS interview_id,
        ii.booking_id,
        ii.q1, ii.q2, ii.q3, ii.q4, ii.q5,
        ii.q6, ii.q7, ii.q8, ii.q9,
        ii.score,
        ii.diagnosis,
        ii.remarks,
        ii.created_at    AS interview_date,
        bs.start_time    AS session_date,
        bs.email         AS patient_email,
        bs.name          AS patient_name,
        CONCAT(iv.first_name,' ',iv.last_name) AS interviewer_name
     FROM Initial_Interview_Record ii
     JOIN booking_record bs ON bs.id = ii.booking_id
     LEFT JOIN users iv ON iv.id = ii.interviewer_id
     ORDER BY ii.created_at DESC"
);
if ($rStmt) {
    $rStmt->execute();
    $records = $rStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $rStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Records – Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css?v=<?= time() ?>">
    <style>
        .ir-card { background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); overflow:hidden; margin-bottom:1.5rem; }
        .ir-card-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:1.1rem 1.5rem; font-weight:700; font-size:1rem; display:flex; align-items:center; gap:.6rem; }
        .ir-card-body { padding:1.5rem; }
        .ir-table { width:100%; border-collapse:collapse; font-size:.88rem; }
        .ir-table thead tr { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; }
        .ir-table thead th { padding:.85rem 1rem; font-weight:600; white-space:nowrap; }
        .ir-table tbody tr { border-bottom:1px solid #f0f0f0; transition:background .15s; }
        .ir-table tbody tr:hover { background:#fdf5f5; }
        .ir-table tbody td { padding:.85rem 1rem; color:#343a40; vertical-align:middle; }
        .diag-badge { display:inline-block; padding:.3rem .85rem; border-radius:20px; font-weight:700; font-size:.8rem; white-space:nowrap; }
        .diag-severe   { background:#f8d7da; color:#842029; border:2px solid #f5c2c7; }
        .diag-moderate { background:#fff3cd; color:#856404; border:2px solid #ffecb5; }
        .diag-mild     { background:#fff9e6; color:#7d6608; border:2px solid #ffe69c; }
        .diag-atrisk   { background:#cfe2ff; color:#084298; border:2px solid #b6d4fe; }
        .diag-none     { background:#d1e7dd; color:#0f5132; border:2px solid #badbcc; }
        .btn-view-record { background:linear-gradient(135deg,#0d6efd,#0a58ca); color:#fff; border:none; border-radius:8px; padding:.4rem 1rem; font-size:.82rem; font-weight:600; cursor:pointer; transition:all .2s; white-space:nowrap; }
        .btn-view-record:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(13,110,253,.35); }
        .ir-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9999; align-items:center; justify-content:center; padding:1rem; }
        .ir-modal-overlay.open { display:flex; }
        .ir-modal { background:#fff; border-radius:18px; width:100%; max-width:760px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.25); }
        .ir-modal-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:1.25rem 1.5rem; display:flex; justify-content:space-between; align-items:center; border-radius:18px 18px 0 0; position:sticky; top:0; z-index:1; }
        .ir-modal-header h2 { margin:0; font-size:1.1rem; font-weight:700; color:#fff; }
        .ir-modal-close { background:rgba(255,255,255,.2); border:none; color:#fff; width:34px; height:34px; border-radius:8px; font-size:1.2rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:.2s; }
        .ir-modal-close:hover { background:rgba(255,255,255,.35); }
        .ir-modal-body { padding:1.5rem; }
        .info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.75rem; margin-bottom:1.25rem; }
        .info-box { padding:.75rem 1rem; background:#f8f9fa; border-radius:10px; border-left:4px solid #800000; }
        .info-box .lbl { font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
        .info-box .val { font-weight:700; color:#212529; margin-top:2px; font-size:.9rem; }
        .dsm-table { width:100%; border-collapse:collapse; }
        .dsm-table thead tr { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; }
        .dsm-table thead th { padding:.7rem 1rem; font-weight:600; font-size:.83rem; }
        .dsm-table tbody tr { border-bottom:1px solid #f0f0f0; }
        .dsm-table tbody td { padding:.8rem 1rem; font-size:.86rem; color:#343a40; vertical-align:middle; }
        .dsm-table .q-num { font-weight:700; color:#800000; width:32px; }
        .ans-yes { display:inline-block; padding:.2rem .75rem; border-radius:20px; background:#d1e7dd; color:#0f5132; font-weight:700; font-size:.8rem; }
        .ans-no  { display:inline-block; padding:.2rem .75rem; border-radius:20px; background:#f8d7da; color:#842029; font-weight:700; font-size:.8rem; }
        .score-wrap { display:flex; align-items:center; gap:1.25rem; padding:1rem 1.25rem; background:#f8f9fa; border-radius:12px; flex-wrap:wrap; margin-top:1.25rem; }
        .score-num { font-size:2.5rem; font-weight:800; color:#800000; line-height:1; }
        .ir-search { border:2px solid #dee2e6; border-radius:10px; padding:.55rem 1rem; font-size:.9rem; width:100%; max-width:320px; outline:none; transition:border-color .2s; }
        .ir-search:focus { border-color:#800000; }
        .empty-state { text-align:center; padding:3rem 1rem; color:#6c757d; }
        .empty-state i { font-size:3rem; color:#dee2e6; margin-bottom:1rem; display:block; }
    </style>
</head>
<body>
<div class="dashboard-container">
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
            <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php"><i class="fas fa-calendar-check"></i> Booking Management</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/interview-records.php" class="active"><i class="fas fa-clipboard-list"></i> Interview Records</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/contract-review.php"><i class="fas fa-file-contract"></i> Contract Management</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/policies.php"><i class="fas fa-book"></i> Policies &amp; Guidelines</a></li>
            <li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
            <div class="menu-divider"></div>
            <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div style="margin-bottom:1.75rem;">
            <h1 style="color:#800000; font-size:1.8rem; font-weight:800; margin:0;">
                <i class="fas fa-clipboard-list me-2"></i>Interview Records
            </h1>
            <p style="color:#6c757d; margin:0;">All completed initial interview records</p>
        </div>

        <div class="ir-card">
            <div class="ir-card-header">
                <i class="fas fa-list-alt"></i> Completed Interviews
                <span style="margin-left:auto; background:rgba(255,255,255,.2); padding:.2rem .75rem; border-radius:20px; font-size:.82rem;">
                    <?= count($records) ?> record<?= count($records) !== 1 ? 's' : '' ?>
                </span>
            </div>
            <div class="ir-card-body">
                <div style="margin-bottom:1rem;">
                    <input type="text" class="ir-search" id="irSearch" placeholder="Search by name or email…" oninput="filterTable()">
                </div>

                <?php if (empty($records)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard"></i>
                        <p>No interview records found yet.</p>
                    </div>
                <?php else: ?>
                <div style="overflow-x:auto;">
                <table class="ir-table" id="irTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Patient Name</th>
                            <th>Email</th>
                            <th>Session Date</th>
                            <th>Score</th>
                            <th>Severity</th>
                            <th>Interview Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $i => $rec):
                            $sev = getSeverity((int)$rec['score']);
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($rec['patient_name']) ?></td>
                            <td style="color:#6c757d; font-size:.85rem;"><?= htmlspecialchars($rec['patient_email']) ?></td>
                            <td><?= date('M j, Y', strtotime($rec['session_date'])) ?></td>
                            <td style="font-weight:700; color:#800000;"><?= (int)$rec['score'] ?>/9</td>
                            <td><span class="diag-badge <?= $sev['class'] ?>"><?= $sev['label'] ?></span></td>
                            <td><?= date('M j, Y', strtotime($rec['interview_date'])) ?></td>
                            <td>
                                <button class="btn-view-record"
                                    onclick='openRecord(<?= htmlspecialchars(json_encode($rec), ENT_QUOTES) ?>)'>
                                    <i class="fas fa-eye me-1"></i> View Record
                                </button>
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

<!-- Detail Modal -->
<div class="ir-modal-overlay" id="irModalOverlay" onclick="closeModalOnOverlay(event)">
    <div class="ir-modal" id="irModal">
        <div class="ir-modal-header">
            <h2><i class="fas fa-clipboard-check me-2"></i>Interview Record Detail</h2>
            <button class="ir-modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="ir-modal-body" id="irModalBody"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const QUESTIONS = <?= json_encode($questions) ?>;

function getSeverityInfo(score) {
    if (score >= 8) return { label:'Severe',        cls:'diag-severe' };
    if (score >= 6) return { label:'Moderate',      cls:'diag-moderate' };
    if (score >= 4) return { label:'Mild',          cls:'diag-mild' };
    if (score > 0)  return { label:'At-Risk',       cls:'diag-atrisk' };
    return                 { label:'No Indicators', cls:'diag-none' };
}

function openRecord(rec) {
    const score = parseInt(rec.score, 10);
    const sev   = getSeverityInfo(score);

    let dsmRows = '';
    for (let n = 1; n <= 9; n++) {
        const ans = parseInt(rec['q' + n], 10);
        dsmRows += `<tr>
            <td class="q-num">${n}.</td>
            <td>${escHtml(QUESTIONS[n])}</td>
            <td style="text-align:center;">
                ${ans === 1 ? '<span class="ans-yes">Yes</span>' : '<span class="ans-no">No</span>'}
            </td>
        </tr>`;
    }

    let sevDesc = '';
    if (score >= 8)      sevDesc = 'Severe: 8–9 criteria met. Immediate intensive treatment is recommended.';
    else if (score >= 6) sevDesc = 'Moderate: 6–7 criteria met. Structured treatment program is recommended.';
    else if (score >= 4) sevDesc = 'Mild: 4–5 criteria met. Counseling and support services are recommended.';
    else if (score > 0)  sevDesc = 'Less than 4 criteria met — potential problem / at-risk indicators.';
    else                 sevDesc = 'No significant gambling disorder indicators found.';

    const remarksHtml = rec.remarks
        ? `<div style="margin-top:1rem; padding:1rem; background:#fff8e1; border-radius:10px; border-left:4px solid #ffc107;">
               <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px; margin-bottom:.3rem;">
                   <i class="fas fa-sticky-note me-1" style="color:#ffc107;"></i> Interviewer's Remarks
               </div>
               <div style="font-size:.9rem; color:#343a40;">${escHtml(rec.remarks).replace(/\n/g,'<br>')}</div>
           </div>` : '';

    document.getElementById('irModalBody').innerHTML = `
        <div class="info-grid">
            <div class="info-box"><div class="lbl">Patient Name</div><div class="val">${escHtml(rec.patient_name)}</div></div>
            <div class="info-box"><div class="lbl">Email</div><div class="val" style="font-size:.85rem;">${escHtml(rec.patient_email)}</div></div>
            <div class="info-box"><div class="lbl">Session Date</div><div class="val">${formatDate(rec.session_date)}</div></div>
            <div class="info-box"><div class="lbl">Interview Date</div><div class="val">${formatDate(rec.interview_date)}</div></div>
            <div class="info-box"><div class="lbl">Interviewer</div><div class="val">${escHtml(rec.interviewer_name || 'N/A')}</div></div>
        </div>
        <div style="overflow-x:auto; margin-bottom:1.25rem;">
        <table class="dsm-table">
            <thead><tr><th style="width:40px;">#</th><th>Question (In the Past Year…)</th><th style="text-align:center; width:80px;">Answer</th></tr></thead>
            <tbody>${dsmRows}</tbody>
        </table>
        </div>
        <div class="score-wrap">
            <div>
                <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Score</div>
                <div class="score-num">${score}<span style="font-size:1.2rem; color:#6c757d;">/9</span></div>
            </div>
            <div>
                <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px; margin-bottom:.3rem;">Severity</div>
                <span class="diag-badge ${sev.cls}">${sev.label}</span>
                <div style="font-size:.78rem; color:#6c757d; margin-top:.4rem;">${sevDesc}</div>
            </div>
            <div>
                <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px; margin-bottom:.3rem;">Diagnosis</div>
                <div style="font-size:.88rem; font-weight:600; color:#343a40;">${escHtml(rec.diagnosis || '—')}</div>
            </div>
        </div>
        ${remarksHtml}
        <div style="margin-top:.75rem; font-size:.75rem; color:#adb5bd; font-style:italic;">
            Adapted from the American Psychiatric Association Diagnostic Criteria from the DSM V 2013
        </div>
    `;

    document.getElementById('irModalOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('irModalOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
function closeModalOnOverlay(e) {
    if (e.target === document.getElementById('irModalOverlay')) closeModal();
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

function filterTable() {
    const q = document.getElementById('irSearch').value.toLowerCase();
    document.querySelectorAll('#irTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' });
}
</script>
</body>
</html>
