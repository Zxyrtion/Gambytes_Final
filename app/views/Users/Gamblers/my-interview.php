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

if ($user['role'] !== 'gambler') {
    header("Location: " . url('app/views/auth/dashboard.php'));
    exit();
}

$full_name = $user['first_name'] . ' ' . $user['last_name'];
$email     = $user['email'];

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
    $bStmt->bind_param('s', $user['email']);
    $bStmt->execute();
    $bRow = $bStmt->get_result()->fetch_assoc();
    $bStmt->close();
    if ($bRow) {
        $gamblerContractUrl = '/GAMBYTES_Final/app/views/Users/Gamblers/contract/fill-contract.php?booking_id=' . $bRow['id'];
    }
}

// Auto-create table if missing
$conn->query("CREATE TABLE IF NOT EXISTS `Initial_Interview_Record` (
    `id`             INT(11)  NOT NULL AUTO_INCREMENT,
    `booking_id`     INT(11)  NOT NULL,
    `interviewer_id` INT(11)  NOT NULL,
    `q1` TINYINT(1) DEFAULT NULL, `q2` TINYINT(1) DEFAULT NULL,
    `q3` TINYINT(1) DEFAULT NULL, `q4` TINYINT(1) DEFAULT NULL,
    `q5` TINYINT(1) DEFAULT NULL, `q6` TINYINT(1) DEFAULT NULL,
    `q7` TINYINT(1) DEFAULT NULL, `q8` TINYINT(1) DEFAULT NULL,
    `q9` TINYINT(1) DEFAULT NULL,
    `score`      INT(11)      DEFAULT 0,
    `diagnosis`  VARCHAR(100) DEFAULT NULL,
    `remarks`    TEXT         DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_booking` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auto-create contract_documents table if missing
$conn->query("CREATE TABLE IF NOT EXISTS `contract_documents` (
    `id`                     INT(11)      NOT NULL AUTO_INCREMENT,
    `user_id`                INT(11)      NOT NULL,
    `booking_id`             INT(11)      NOT NULL,
    `self_exclusion_data`    LONGTEXT     DEFAULT NULL,
    `family_exclusion_data`  LONGTEXT     DEFAULT NULL,
    `player_verification_data` LONGTEXT   DEFAULT NULL,
    `self_exclusion_sig`     LONGTEXT     DEFAULT NULL COMMENT 'base64 canvas signature',
    `family_exclusion_sig`   LONGTEXT     DEFAULT NULL COMMENT 'base64 canvas signature',
    `player_verification_sig` LONGTEXT    DEFAULT NULL COMMENT 'base64 canvas signature',
    `status`                 VARCHAR(50)  NOT NULL DEFAULT 'submitted',
    `submitted_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_booking` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Strategy: fetch interview records that belong to this user's bookings,
// PLUS any approved/booked bookings that don't have an interview yet.
// Use LOWER() for case-insensitive email matching.
$bStmt = $conn->prepare(
    "SELECT
        bs.id, bs.start_time, bs.end_time, bs.status,
        ii.id          AS interview_id,
        ii.q1, ii.q2, ii.q3, ii.q4, ii.q5,
        ii.q6, ii.q7, ii.q8, ii.q9,
        ii.score, ii.diagnosis, ii.remarks,
        ii.created_at  AS interview_date,
        CONCAT(u.first_name,' ',u.last_name) AS interviewer_name
     FROM booking_record bs
     LEFT JOIN Initial_Interview_Record ii ON ii.booking_id = bs.id
     LEFT JOIN users u ON u.id = ii.interviewer_id
     WHERE LOWER(bs.email) = LOWER(?)
       AND (
           ii.id IS NOT NULL
           OR bs.status IN ('approved','interviewed','completed','booked')
       )
     ORDER BY bs.start_time DESC"
);
$bookings = [];
if ($bStmt) {
    $bStmt->bind_param('s', $email);
    $bStmt->execute();
    $bookings = $bStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $bStmt->close();
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Interview – Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css?v=<?= time() ?>">
    <style>
        .iv-card { background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); overflow:hidden; margin-bottom:1.5rem; }
        .iv-card-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:1.1rem 1.5rem; font-weight:700; font-size:1rem; display:flex; align-items:center; gap:.6rem; }
        .iv-card-body { padding:1.5rem; }
        .info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.75rem; margin-bottom:1.25rem; }
        .info-box { padding:.75rem 1rem; background:#f8f9fa; border-radius:10px; border-left:4px solid #800000; }
        .info-box .lbl { font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
        .info-box .val { font-weight:700; color:#212529; margin-top:2px; font-size:.92rem; }

        .dsm-table { width:100%; border-collapse:collapse; }
        .dsm-table thead tr { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; }
        .dsm-table thead th { padding:.75rem 1rem; font-weight:600; font-size:.85rem; }
        .dsm-table tbody tr { border-bottom:1px solid #f0f0f0; }
        .dsm-table tbody td { padding:.85rem 1rem; font-size:.88rem; color:#343a40; vertical-align:middle; }
        .dsm-table .q-num { font-weight:700; color:#800000; width:32px; }

        .ans-yes { display:inline-block; padding:.2rem .75rem; border-radius:20px; background:#d1e7dd; color:#0f5132; font-weight:700; font-size:.8rem; }
        .ans-no  { display:inline-block; padding:.2rem .75rem; border-radius:20px; background:#f8d7da; color:#842029; font-weight:700; font-size:.8rem; }

        .score-wrap { display:flex; align-items:center; gap:1.25rem; padding:1rem 1.25rem; background:#f8f9fa; border-radius:12px; flex-wrap:wrap; }
        .score-num { font-size:2.5rem; font-weight:800; color:#800000; line-height:1; }
        .diag-badge { display:inline-block; padding:.4rem 1rem; border-radius:20px; font-weight:700; font-size:.88rem; }
        .diag-severe   { background:#f8d7da; color:#842029; border:2px solid #f5c2c7; }
        .diag-moderate { background:#fff3cd; color:#856404; border:2px solid #ffecb5; }
        .diag-mild     { background:#fff9e6; color:#7d6608; border:2px solid #ffe69c; }
        .diag-atrisk   { background:#cfe2ff; color:#084298; border:2px solid #b6d4fe; }
        .diag-none     { background:#d1e7dd; color:#0f5132; border:2px solid #badbcc; }

        .pending-badge { display:inline-flex; align-items:center; gap:.4rem; padding:.5rem 1rem; background:#fff3cd; color:#856404; border-radius:10px; font-weight:600; font-size:.88rem; }
        .booking-tab { padding:.6rem 1.25rem; border-radius:10px; border:2px solid #dee2e6; background:#fff; font-weight:600; font-size:.88rem; cursor:pointer; transition:all .2s; color:#343a40; }
        .booking-tab.active { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border-color:#800000; }
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
                <div class="user-role">Gambler</div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/BookRehab.php"><i class="fas fa-calendar-plus"></i> Book Rehabilitation</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php" class="active"><i class="fas fa-clipboard-list"></i> My Interview</a></li>
            <li><a href="<?= htmlspecialchars($gamblerContractUrl) ?>"><i class="fas fa-file-contract"></i> My Contracts</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-cbt-sessions.php"><i class="fas fa-brain"></i> CBT Sessions</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-activities.php"><i class="fas fa-tasks"></i> My Activities</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/parental-control-requests.php"><i class="fas fa-shield-alt"></i> Parental Access</a></li>
            <li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
            <div class="menu-divider"></div>
            <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">

        <div style="margin-bottom:1.75rem;">
            <h1 style="color:#800000; font-size:1.8rem; font-weight:800; margin:0;">
                <i class="fas fa-clipboard-list me-2"></i>My Interview
            </h1>
            <p style="color:#6c757d; margin:0;">DSM-V Pathological Gambling Diagnostic Results</p>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="iv-card">
                <div class="iv-card-body" style="text-align:center; padding:3rem 1rem; color:#6c757d;">
                    <i class="fas fa-clipboard fa-3x mb-3" style="color:#dee2e6;"></i>
                    <p style="font-size:1rem;">No approved bookings or interview results yet.</p>
                    <a href="/GAMBYTES_Final/app/views/Users/Gamblers/BookRehab.php"
                       style="background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border:none; border-radius:10px; padding:.6rem 1.5rem; font-weight:600; text-decoration:none; display:inline-block;">
                        Book a Session
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($bookings as $idx => $bk): ?>
            <div class="iv-card">
                <div class="iv-card-header">
                    <i class="fas fa-calendar-check"></i>
                    Session: <?= date('F j, Y', strtotime($bk['start_time'])) ?>
                    &nbsp;|&nbsp; <?= date('g:i A', strtotime($bk['start_time'])) ?> – <?= date('g:i A', strtotime($bk['end_time'])) ?>
                    &nbsp;
                    <?php if ($bk['status'] === 'interviewed'): ?>
                        <span style="background:rgba(255,255,255,.25); padding:.2rem .75rem; border-radius:20px; font-size:.78rem;">Interviewed</span>
                    <?php else: ?>
                        <span style="background:rgba(255,255,255,.25); padding:.2rem .75rem; border-radius:20px; font-size:.78rem;">Approved – Pending Interview</span>
                    <?php endif; ?>
                </div>
                <div class="iv-card-body">

                    <?php if (!$bk['interview_id']): ?>
                        <!-- No interview yet -->
                        <div class="pending-badge">
                            <i class="fas fa-hourglass-half"></i>
                            Your initial interview has not been conducted yet. Please wait for the supervisor to conduct your interview.
                        </div>

                    <?php else: ?>
                        <!-- Interview results -->
                        <div class="info-grid">
                            <div class="info-box">
                                <div class="lbl">Interview Date</div>
                                <div class="val"><?= date('F j, Y', strtotime($bk['interview_date'])) ?></div>
                            </div>
                            <div class="info-box">
                                <div class="lbl">Interviewer</div>
                                <div class="val"><?= htmlspecialchars($bk['interviewer_name'] ?? 'N/A') ?></div>
                            </div>
                            <div class="info-box">
                                <div class="lbl">Score</div>
                                <div class="val" style="color:#800000; font-size:1.2rem;"><?= $bk['score'] ?> / 9</div>
                            </div>
                        </div>

                        <!-- DSM-V Answers Table -->
                        <div style="overflow-x:auto; margin-bottom:1.25rem;">
                        <table class="dsm-table">
                            <thead>
                                <tr>
                                    <th style="width:40px;">#</th>
                                    <th>Question (In the Past Year…)</th>
                                    <th style="text-align:center; width:80px;">Answer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($questions as $num => $text): ?>
                                <tr>
                                    <td class="q-num"><?= $num ?>.</td>
                                    <td><?= htmlspecialchars($text) ?></td>
                                    <td style="text-align:center;">
                                        <?php $ans = $bk["q$num"]; ?>
                                        <?php if ($ans == 1): ?>
                                            <span class="ans-yes">Yes</span>
                                        <?php else: ?>
                                            <span class="ans-no">No</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>

                        <!-- Score & Diagnosis -->
                        <div class="score-wrap">
                            <div>
                                <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Score</div>
                                <div class="score-num"><?= $bk['score'] ?><span style="font-size:1.2rem; color:#6c757d;">/9</span></div>
                            </div>
                            <div>
                                <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px; margin-bottom:.3rem;">Diagnosis Indicator</div>
                                <?php
                                $score = (int)$bk['score'];
                                if ($score >= 8)     $diagClass = 'diag-severe';
                                elseif ($score >= 6) $diagClass = 'diag-moderate';
                                elseif ($score >= 4) $diagClass = 'diag-mild';
                                elseif ($score > 0)  $diagClass = 'diag-atrisk';
                                else                 $diagClass = 'diag-none';
                                ?>
                                <span class="diag-badge <?= $diagClass ?>"><?= htmlspecialchars($bk['diagnosis']) ?></span>
                                <div style="font-size:.78rem; color:#6c757d; margin-top:.4rem;">
                                    <?php if ($score >= 8): ?>
                                        Severe: 8–9 criteria met. Immediate intensive treatment is recommended.
                                    <?php elseif ($score >= 6): ?>
                                        Moderate: 6–7 criteria met. Structured treatment program is recommended.
                                    <?php elseif ($score >= 4): ?>
                                        Mild: 4–5 criteria met. Counseling and support services are recommended.
                                    <?php elseif ($score > 0): ?>
                                        **Less than 4 indicates a potential problem and/or at risk indicators which may warrant further support, education and treatment services.
                                    <?php else: ?>
                                        No significant gambling disorder indicators found.
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($bk['remarks']): ?>
                        <div style="margin-top:1rem; padding:1rem; background:#fff8e1; border-radius:10px; border-left:4px solid #ffc107;">
                            <div style="font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px; margin-bottom:.3rem;">
                                <i class="fas fa-sticky-note me-1" style="color:#ffc107;"></i> Interviewer's Remarks
                            </div>
                            <div style="font-size:.9rem; color:#343a40;"><?= nl2br(htmlspecialchars($bk['remarks'])) ?></div>
                        </div>
                        <?php endif; ?>

                        <div style="margin-top:.75rem; font-size:.75rem; color:#adb5bd; font-style:italic;">
                            Adapted from the American Psychiatric Association Diagnostic Criteria from the DSM V 2013
                        </div>

                        <?php
                        // Show "Apply for Treatment Rehabilitation" button for high-risk (score >= 4)
                        $score = (int)$bk['score'];
                        if ($score >= 4):
                            // Check if contract already submitted
                            $cStmt = $conn->prepare("SELECT id, status FROM contract_documents WHERE user_id = ? AND booking_id = ? LIMIT 1");
                            $contractExists = false;
                            $contractStatus = '';
                            if ($cStmt) {
                                $cStmt->bind_param('ii', $user_id, $bk['id']);
                                $cStmt->execute();
                                $cRow = $cStmt->get_result()->fetch_assoc();
                                $cStmt->close();
                                if ($cRow) { $contractExists = true; $contractStatus = $cRow['status']; }
                            }
                        ?>
                        <div style="margin-top:1.25rem; padding:1.25rem; background:linear-gradient(135deg,#fff5f5,#ffe8e8); border-radius:12px; border:2px solid #f5c2c7;">
                            <div style="display:flex; align-items:center; gap:.75rem; flex-wrap:wrap;">
                                <div style="flex:1; min-width:200px;">
                                    <div style="font-weight:700; color:#800000; font-size:.95rem; margin-bottom:.2rem;">
                                        <i class="fas fa-heartbeat me-1"></i> Treatment Rehabilitation Required
                                    </div>
                                    <div style="font-size:.83rem; color:#6c757d;">
                                        Based on your assessment score, you are recommended for a structured rehabilitation program.
                                    </div>
                                </div>
                                <?php if ($contractExists): ?>
                                    <?php if ($contractStatus === 'submitted'): ?>
                                        <span style="background:#d1e7dd; color:#0f5132; padding:.5rem 1.25rem; border-radius:10px; font-weight:700; font-size:.88rem;">
                                            <i class="fas fa-check-circle me-1"></i> Application Submitted
                                        </span>
                                    <?php else: ?>
                                        <span style="background:#fff3cd; color:#856404; padding:.5rem 1.25rem; border-radius:10px; font-weight:700; font-size:.88rem;">
                                            <i class="fas fa-clock me-1"></i> Status: <?= htmlspecialchars(ucfirst($contractStatus)) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="/GAMBYTES_Final/app/views/Users/Gamblers/contract/review_terms.php?booking_id=<?= $bk['id'] ?>"
                                       style="background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border:none; border-radius:10px; padding:.65rem 1.5rem; font-weight:700; font-size:.9rem; text-decoration:none; display:inline-flex; align-items:center; gap:.5rem; white-space:nowrap; box-shadow:0 4px 14px rgba(128,0,0,.3);">
                                        <i class="fas fa-file-medical"></i> Apply for Treatment Rehabilitation
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                    <?php endif; ?>

                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
