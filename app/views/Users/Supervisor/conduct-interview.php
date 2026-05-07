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

$booking_id = (int)($_GET['booking_id'] ?? 0);
if (!$booking_id) {
    header("Location: /GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php");
    exit();
}

// Auto-create Initial_Interview_Record table
$conn->query("CREATE TABLE IF NOT EXISTS `Initial_Interview_Record` (
    `id`           INT(11)  NOT NULL AUTO_INCREMENT,
    `booking_id`   INT(11)  NOT NULL,
    `interviewer_id` INT(11) NOT NULL,
    `q1` TINYINT(1) DEFAULT NULL, `q2` TINYINT(1) DEFAULT NULL,
    `q3` TINYINT(1) DEFAULT NULL, `q4` TINYINT(1) DEFAULT NULL,
    `q5` TINYINT(1) DEFAULT NULL, `q6` TINYINT(1) DEFAULT NULL,
    `q7` TINYINT(1) DEFAULT NULL, `q8` TINYINT(1) DEFAULT NULL,
    `q9` TINYINT(1) DEFAULT NULL,
    `score`        INT(11)  DEFAULT 0,
    `diagnosis`    VARCHAR(100) DEFAULT NULL,
    `remarks`      TEXT     NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_booking` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Fetch booking
$bStmt = $conn->prepare(
    "SELECT * FROM booking_record WHERE id = ? AND status = 'approved' LIMIT 1"
);
if (!$bStmt) {
    die("Query prepare failed: " . $conn->error);
}
$bStmt->bind_param('i', $booking_id);
$bStmt->execute();
$booking = $bStmt->get_result()->fetch_assoc();
$bStmt->close();

if (!$booking) {
    // Also check if already interviewed (allow re-view)
    $bStmt2 = $conn->prepare("SELECT * FROM booking_record WHERE id = ? LIMIT 1");
    $bStmt2->bind_param('i', $booking_id);
    $bStmt2->execute();
    $booking = $bStmt2->get_result()->fetch_assoc();
    $bStmt2->close();
    if (!$booking) {
        header("Location: /GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php");
        exit();
    }
}

// Get gambler's user_id from users table by email
$booking['user_id_fk'] = null;
if (!empty($booking['email'])) {
    $uStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    if ($uStmt) {
        $uStmt->bind_param('s', $booking['email']);
        $uStmt->execute();
        $uRow = $uStmt->get_result()->fetch_assoc();
        $uStmt->close();
        if ($uRow) $booking['user_id_fk'] = $uRow['id'];
    }
}

// Handle form submission
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_interview'])) {
    $answers = [];
    $score   = 0;
    for ($i = 1; $i <= 9; $i++) {
        $val = isset($_POST["q$i"]) ? (int)$_POST["q$i"] : 0;
        $answers[$i] = $val;
        $score += $val;
    }
    $remarks   = trim($_POST['remarks'] ?? '');
    // DSM-V severity scale
    if ($score >= 8)      $diagnosis = 'Gambling Disorder – Severe (8–9 criteria met)';
    elseif ($score >= 6)  $diagnosis = 'Gambling Disorder – Moderate (6–7 criteria met)';
    elseif ($score >= 4)  $diagnosis = 'Gambling Disorder – Mild (4–5 criteria met)';
    elseif ($score > 0)   $diagnosis = 'At-Risk / Potential Problem (less than 4 criteria met)';
    else                  $diagnosis = 'No Significant Indicators';

    $ins = $conn->prepare(
        "INSERT INTO Initial_Interview_Record
         (booking_id, interviewer_id, q1,q2,q3,q4,q5,q6,q7,q8,q9, score, diagnosis, remarks, created_at)
         VALUES (?,?, ?,?,?,?,?,?,?,?,?, ?,?,?, NOW())"
    );

    if (!$ins) {
        $error_msg = "Database error: " . $conn->error;
    } else {
        $ins->bind_param('iiiiiiiiiiisss',
            $booking_id, $user_id,
            $answers[1],$answers[2],$answers[3],$answers[4],$answers[5],
            $answers[6],$answers[7],$answers[8],$answers[9],
            $score, $diagnosis, $remarks
        );

        if ($ins->execute()) {
            $ins->close();

            // Update booking status → interviewed
            $conn->query("UPDATE booking_record SET status='interviewed' WHERE id=$booking_id");

            // Notify gambler
            $notif_title   = "Initial Interview Completed";
            $notif_message = "Your initial interview has been recorded. Score: $score/9 — $diagnosis.";
            $notif_link    = '/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php';
            $notif_type    = 'interview_done';

            if ($booking['user_id_fk']) {
                $gid = (int)$booking['user_id_fk'];
                $nStmt = $conn->prepare(
                    "INSERT INTO notifications (user_id,type,title,message,link,is_read,created_at)
                     VALUES (?,?,?,?,?,0,NOW())"
                );
                if ($nStmt) {
                    $nStmt->bind_param('issss', $gid, $notif_type, $notif_title, $notif_message, $notif_link);
                    $nStmt->execute();
                    $nStmt->close();
                }
            }

            header("Location: /GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php?interview_saved=1");
            exit();
        } else {
            $error_msg = "Failed to save interview: " . $ins->error;
            $ins->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conduct Interview – Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css?v=<?= time() ?>">
    <style>
        .interview-card { background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); overflow:hidden; margin-bottom:1.5rem; }
        .interview-card-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:1.1rem 1.5rem; font-weight:700; font-size:1rem; display:flex; align-items:center; gap:.6rem; }
        .interview-card-body { padding:1.5rem; }
        .patient-info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:.85rem; margin-bottom:1.5rem; }
        .info-box { padding:.85rem 1rem; background:#f8f9fa; border-radius:10px; border-left:4px solid #800000; }
        .info-box .lbl { font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
        .info-box .val { font-weight:700; color:#212529; margin-top:2px; }

        /* DSM-V Table */
        .dsm-table { width:100%; border-collapse:collapse; }
        .dsm-table thead tr { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; }
        .dsm-table thead th { padding:.85rem 1rem; font-weight:600; font-size:.88rem; }
        .dsm-table tbody tr { border-bottom:1px solid #f0f0f0; transition:background .15s; }
        .dsm-table tbody tr:hover { background:#fdf5f5; }
        .dsm-table tbody td { padding:.9rem 1rem; font-size:.9rem; color:#343a40; vertical-align:middle; }
        .dsm-table .q-num { font-weight:700; color:#800000; width:32px; }
        .dsm-table .q-text { line-height:1.5; }
        .dsm-table .q-yn { text-align:center; width:80px; }

        /* Radio toggle buttons */
        .yn-wrap { display:flex; gap:.4rem; justify-content:center; }
        .yn-wrap input[type=radio] { display:none; }
        .yn-wrap label { display:inline-flex; align-items:center; justify-content:center; width:44px; height:32px; border-radius:8px; border:2px solid #dee2e6; font-size:.82rem; font-weight:700; cursor:pointer; transition:all .2s; color:#6c757d; }
        .yn-wrap input[value="1"]:checked + label { background:#198754; border-color:#198754; color:#fff; }
        .yn-wrap input[value="0"]:checked + label { background:#dc3545; border-color:#dc3545; color:#fff; }
        .yn-wrap label:hover { border-color:#800000; color:#800000; }

        /* Score box */
        .score-box { display:inline-flex; align-items:center; gap:1rem; background:#f8f9fa; border-radius:12px; padding:1rem 1.5rem; border:2px solid #dee2e6; }
        .score-num { font-size:2.5rem; font-weight:800; color:#800000; line-height:1; }
        .score-label { font-size:.85rem; color:#6c757d; }
        .diagnosis-badge { display:inline-block; padding:.5rem 1.1rem; border-radius:20px; font-weight:700; font-size:.9rem; margin-top:.5rem; }
        .diag-severe   { background:#f8d7da; color:#842029; border:2px solid #f5c2c7; }
        .diag-moderate { background:#fff3cd; color:#856404; border:2px solid #ffecb5; }
        .diag-mild     { background:#fff9e6; color:#7d6608; border:2px solid #ffe69c; }
        .diag-atrisk   { background:#cfe2ff; color:#084298; border:2px solid #b6d4fe; }
        .diag-none     { background:#d1e7dd; color:#0f5132; border:2px solid #badbcc; }

        .btn-submit { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border:none; border-radius:12px; padding:.85rem 2.5rem; font-weight:700; font-size:1rem; cursor:pointer; transition:all .3s; }
        .btn-submit:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(128,0,0,.35); }
        .btn-back { background:#6c757d; color:#fff; border:none; border-radius:12px; padding:.85rem 1.5rem; font-weight:600; font-size:.95rem; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; }
        .btn-back:hover { background:#5a6268; color:#fff; }
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
                <div class="user-role"><?= ucfirst(str_replace('_', ' ', $role)) ?></div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php"><i class="fas fa-calendar-check"></i> Booking Management</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/interview-records.php"><i class="fas fa-clipboard-list"></i> Interview Records</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/contract-review.php"><i class="fas fa-file-contract"></i> Contract Management</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/policies.php"><i class="fas fa-book"></i> Policies &amp; Guidelines</a></li>
            <li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
            <div class="menu-divider"></div>
            <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">

        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;">
            <a href="/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <div>
                <h1 style="color:#800000; font-size:1.7rem; font-weight:800; margin:0;">
                    <i class="fas fa-clipboard-list me-2"></i>Initial Interview
                </h1>
                <p style="color:#6c757d; margin:0; font-size:.9rem;">DSM-V Pathological Gambling Diagnostic Form</p>
            </div>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <form method="POST" id="interviewForm">
            <input type="hidden" name="submit_interview" value="1">

            <!-- Patient Info -->
            <div class="interview-card">
                <div class="interview-card-header">
                    <i class="fas fa-user-circle"></i> Patient Information
                </div>
                <div class="interview-card-body">
                    <div class="patient-info-grid">
                        <div class="info-box">
                            <div class="lbl">Patient Name</div>
                            <div class="val"><?= htmlspecialchars($booking['name']) ?></div>
                        </div>
                        <div class="info-box">
                            <div class="lbl">Email</div>
                            <div class="val" style="font-size:.88rem;"><?= htmlspecialchars($booking['email']) ?></div>
                        </div>
                        <div class="info-box">
                            <div class="lbl">Session Date</div>
                            <div class="val"><?= date('F j, Y', strtotime($booking['start_time'])) ?></div>
                        </div>
                        <div class="info-box">
                            <div class="lbl">Session Time</div>
                            <div class="val"><?= date('g:i A', strtotime($booking['start_time'])) ?> – <?= date('g:i A', strtotime($booking['end_time'])) ?></div>
                        </div>
                        <div class="info-box">
                            <div class="lbl">Today's Date</div>
                            <div class="val"><?= date('F j, Y') ?></div>
                        </div>
                        <div class="info-box">
                            <div class="lbl">Interviewer</div>
                            <div class="val"><?= htmlspecialchars($full_name) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DSM-V Questions -->
            <div class="interview-card">
                <div class="interview-card-header">
                    <i class="fas fa-clipboard-list"></i> DSM-V Pathological Gambling Diagnostic Criteria
                </div>
                <div class="interview-card-body">
                    <p style="color:#6c757d; font-size:.9rem; margin-bottom:1.25rem;">
                        <i class="fas fa-info-circle me-1" style="color:#800000;"></i>
                        Persistent and recurrent maladaptive gambling behavior as indicated by <strong>four (4) or more</strong> of the following criteria.
                        <strong>IN THE PAST YEAR…</strong>
                    </p>

                    <div style="overflow-x:auto;">
                    <table class="dsm-table">
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Question</th>
                                <th class="q-yn">Yes</th>
                                <th class="q-yn">No</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
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
                            foreach ($questions as $num => $text): ?>
                            <tr>
                                <td class="q-num"><?= $num ?>.</td>
                                <td class="q-text"><?= htmlspecialchars($text) ?></td>
                                <td class="q-yn">
                                    <div class="yn-wrap">
                                        <input type="radio" name="q<?= $num ?>" id="q<?= $num ?>_yes" value="1" required>
                                        <label for="q<?= $num ?>_yes">Yes</label>
                                    </div>
                                </td>
                                <td class="q-yn">
                                    <div class="yn-wrap">
                                        <input type="radio" name="q<?= $num ?>" id="q<?= $num ?>_no" value="0">
                                        <label for="q<?= $num ?>_no">No</label>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <!-- Live Score -->
                    <div style="margin-top:1.5rem; padding:1.25rem; background:#f8f9fa; border-radius:12px;">
                        <div style="display:flex; align-items:flex-start; gap:1.5rem; flex-wrap:wrap; margin-bottom:1.25rem;">
                            <div class="score-box">
                                <div>
                                    <div class="score-num" id="liveScore">0</div>
                                    <div class="score-label">Score / 9</div>
                                </div>
                            </div>
                            <div>
                                <div style="font-size:.8rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px; margin-bottom:.4rem;">Severity Level</div>
                                <div id="liveDiagnosis" class="diagnosis-badge diag-none">No Significant Indicators</div>
                                <div style="font-size:.78rem; color:#6c757d; margin-top:.5rem;" id="diagNote">
                                    Answer the questions above to see the severity level.
                                </div>
                            </div>
                        </div>

                        <!-- Severity Scale Reference Table -->
                        <div style="border-top:1px solid #dee2e6; padding-top:1rem;">
                            <div style="font-size:.78rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px; margin-bottom:.6rem;">DSM-V Severity Scale Reference</div>
                            <table style="width:100%; border-collapse:collapse; font-size:.85rem;">
                                <thead>
                                    <tr style="background:#e9ecef;">
                                        <th style="padding:.5rem .85rem; text-align:left; font-weight:700; border:1px solid #dee2e6; width:120px;">Scale</th>
                                        <th style="padding:.5rem .85rem; text-align:left; font-weight:700; border:1px solid #dee2e6;">Range</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr id="row-mild">
                                        <td style="padding:.5rem .85rem; border:1px solid #dee2e6; font-weight:600;">Mild</td>
                                        <td style="padding:.5rem .85rem; border:1px solid #dee2e6;">4–5 criteria met.</td>
                                    </tr>
                                    <tr id="row-moderate">
                                        <td style="padding:.5rem .85rem; border:1px solid #dee2e6; font-weight:600;">Moderate</td>
                                        <td style="padding:.5rem .85rem; border:1px solid #dee2e6;">6–7 criteria met.</td>
                                    </tr>
                                    <tr id="row-severe">
                                        <td style="padding:.5rem .85rem; border:1px solid #dee2e6; font-weight:600;">Severe</td>
                                        <td style="padding:.5rem .85rem; border:1px solid #dee2e6;">8–9 criteria met.</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div style="font-size:.75rem; color:#6c757d; margin-top:.5rem;">
                                **4 or more "Yes" answers indicates a diagnosis for Gambling Disorder – please see DSM-V for further diagnostic criteria.<br>
                                **Less than 4 indicates a potential problem and/or at risk indicators which may warrant further support, education and treatment services.<br>
                                <em>Adapted from the American Psychiatric Association Diagnostic Criteria from the DSM V 2013</em>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Remarks -->
            <div class="interview-card">
                <div class="interview-card-header">
                    <i class="fas fa-sticky-note"></i> Interviewer's Remarks
                </div>
                <div class="interview-card-body">
                    <textarea name="remarks" class="form-control" rows="4"
                        placeholder="Additional observations, recommendations, or notes from the interviewer..."></textarea>
                    <div style="font-size:.78rem; color:#6c757d; margin-top:.5rem;">
                        <em>Adapted from the American Psychiatric Association Diagnostic Criteria from the DSM V 2013</em>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div style="display:flex; gap:1rem; justify-content:flex-end; margin-bottom:2rem;">
                <a href="/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php" class="btn-back">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save me-2"></i>Save Interview & Submit
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Live score calculation
    function updateScore() {
        let score = 0;
        for (let i = 1; i <= 9; i++) {
            const checked = document.querySelector(`input[name="q${i}"]:checked`);
            if (checked && checked.value === '1') score++;
        }

        document.getElementById('liveScore').textContent = score;

        const diagEl = document.getElementById('liveDiagnosis');
        const noteEl = document.getElementById('diagNote');

        // Reset table row highlights
        ['row-mild','row-moderate','row-severe'].forEach(id => {
            document.getElementById(id).style.background = '';
            document.getElementById(id).style.fontWeight = '';
        });

        diagEl.className = 'diagnosis-badge';

        if (score >= 8) {
            diagEl.classList.add('diag-severe');
            diagEl.textContent = '🔴 Severe – Gambling Disorder (8–9 criteria met)';
            noteEl.textContent = 'Score indicates Severe Gambling Disorder. Immediate intensive treatment is recommended.';
            document.getElementById('row-severe').style.background = '#f8d7da';
            document.getElementById('row-severe').style.fontWeight = '700';
        } else if (score >= 6) {
            diagEl.classList.add('diag-moderate');
            diagEl.textContent = '🟠 Moderate – Gambling Disorder (6–7 criteria met)';
            noteEl.textContent = 'Score indicates Moderate Gambling Disorder. Structured treatment program is recommended.';
            document.getElementById('row-moderate').style.background = '#fff3cd';
            document.getElementById('row-moderate').style.fontWeight = '700';
        } else if (score >= 4) {
            diagEl.classList.add('diag-mild');
            diagEl.textContent = '🟡 Mild – Gambling Disorder (4–5 criteria met)';
            noteEl.textContent = 'Score indicates Mild Gambling Disorder. Counseling and support services are recommended.';
            document.getElementById('row-mild').style.background = '#fff9e6';
            document.getElementById('row-mild').style.fontWeight = '700';
        } else if (score > 0) {
            diagEl.classList.add('diag-atrisk');
            diagEl.textContent = '🔵 At-Risk / Potential Problem (less than 4 criteria met)';
            noteEl.textContent = 'Less than 4 indicates a potential problem and/or at risk indicators which may warrant further support, education and treatment services.';
        } else {
            diagEl.classList.add('diag-none');
            diagEl.textContent = 'No Significant Indicators';
            noteEl.textContent = 'Answer the questions above to see the severity level.';
        }
    }

    document.querySelectorAll('input[type=radio]').forEach(r => r.addEventListener('change', updateScore));
</script>
</body>
</html>
