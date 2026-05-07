<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../app/core/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db   = new Database();
$conn = $db->connect();
$uid  = (int)$_SESSION['user_id'];

// ── Auto-create tables ────────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `parental_control_requests` (
    `id`              INT(11)      NOT NULL AUTO_INCREMENT,
    `family_id`       INT(11)      NOT NULL COMMENT 'family member user id',
    `gambler_id`      INT(11)      NOT NULL COMMENT 'gambler user id',
    `status`          ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    `requested_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `responded_at`    DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pair` (`family_id`,`gambler_id`),
    INDEX `idx_family`  (`family_id`),
    INDEX `idx_gambler` (`gambler_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Fetch current user role ───────────────────────────────────────────────────
$rs = $conn->prepare("SELECT role, first_name, last_name FROM users WHERE id = ?");
$rs->bind_param('i', $uid);
$rs->execute();
$me = $rs->get_result()->fetch_assoc();
$rs->close();

if (!$me) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ════════════════════════════════════════════════════════════════════════════
// FAMILY MEMBER ACTIONS
// ════════════════════════════════════════════════════════════════════════════

// ── Search gamblers by name ───────────────────────────────────────────────────
if ($action === 'search_gamblers' && $me['role'] === 'family') {
    // Check if this family already has an active (pending/accepted) link
    $activeChk = $conn->prepare(
        "SELECT id FROM parental_control_requests
         WHERE family_id = ? AND status IN ('pending','accepted') LIMIT 1"
    );
    $activeChk->bind_param('i', $uid);
    $activeChk->execute();
    $hasActive = (bool)$activeChk->get_result()->fetch_assoc();
    $activeChk->close();

    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) {
        echo json_encode(['success' => true, 'results' => [], 'has_active_link' => $hasActive]);
        exit();
    }
    $like = '%' . $q . '%';
    $s = $conn->prepare(
        "SELECT u.id, u.first_name, u.last_name, u.email,
                pcr.status AS request_status
         FROM users u
         LEFT JOIN parental_control_requests pcr
               ON pcr.gambler_id = u.id AND pcr.family_id = ?
         WHERE u.role = 'gambler'
           AND u.is_verified = 1
           AND (CONCAT(u.first_name,' ',u.last_name) LIKE ?
                OR u.email LIKE ?)
         LIMIT 10"
    );
    $s->bind_param('iss', $uid, $like, $like);
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    echo json_encode(['success' => true, 'results' => $rows, 'has_active_link' => $hasActive]);
    exit();
}

// ── Send parental control request ─────────────────────────────────────────────
if ($action === 'send_request' && $me['role'] === 'family') {
    $gambler_id = (int)($_POST['gambler_id'] ?? 0);
    if (!$gambler_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid gambler']);
        exit();
    }

    // ── One-gambler-only rule: block if already has a pending or accepted link ──
    $limitChk = $conn->prepare(
        "SELECT pcr.id, CONCAT(u.first_name,' ',u.last_name) AS linked_name
         FROM parental_control_requests pcr
         JOIN users u ON u.id = pcr.gambler_id
         WHERE pcr.family_id = ? AND pcr.status IN ('pending','accepted') LIMIT 1"
    );
    $limitChk->bind_param('i', $uid);
    $limitChk->execute();
    $existing = $limitChk->get_result()->fetch_assoc();
    $limitChk->close();

    if ($existing) {
        echo json_encode([
            'success' => false,
            'message' => 'You already have an active parental control link with ' . htmlspecialchars($existing['linked_name']) . '. You can only monitor one person at a time.'
        ]);
        exit();
    }

    // Verify gambler exists
    $chk = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE id = ? AND role = 'gambler'");
    $chk->bind_param('i', $gambler_id);
    $chk->execute();
    $gambler = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$gambler) {
        echo json_encode(['success' => false, 'message' => 'Gambler not found']);
        exit();
    }

    // Upsert request (reset if previously declined)
    $ins = $conn->prepare(
        "INSERT INTO parental_control_requests (family_id, gambler_id, status, requested_at)
         VALUES (?, ?, 'pending', NOW())
         ON DUPLICATE KEY UPDATE status = 'pending', requested_at = NOW(), responded_at = NULL"
    );
    $ins->bind_param('ii', $uid, $gambler_id);
    if (!$ins->execute()) {
        $ins->close();
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
        exit();
    }
    $ins->close();

    // Notify the gambler
    $family_name = htmlspecialchars($me['first_name'] . ' ' . $me['last_name']);
    $notif_title = 'Parental Control Request';
    $notif_msg   = $family_name . ' is requesting parental access to monitor your rehabilitation activity. Please review and respond.';
    $notif_link  = '/GAMBYTES_Final/app/views/Users/Gamblers/parental-control-requests.php';
    $notif_type  = 'parental_control';

    $n = $conn->prepare(
        "INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
         VALUES (?, ?, ?, ?, ?, 0, NOW())"
    );
    $n->bind_param('issss', $gambler_id, $notif_type, $notif_title, $notif_msg, $notif_link);
    $n->execute();
    $n->close();

    echo json_encode(['success' => true, 'message' => 'Request sent to ' . $gambler['first_name'] . ' ' . $gambler['last_name']]);
    exit();
}

// ── List family's monitored gamblers ─────────────────────────────────────────
if ($action === 'my_gamblers' && $me['role'] === 'family') {
    $s = $conn->prepare(
        "SELECT pcr.id, pcr.status, pcr.requested_at, pcr.responded_at,
                u.id AS gambler_id, u.first_name, u.last_name, u.email
         FROM parental_control_requests pcr
         JOIN users u ON u.id = pcr.gambler_id
         WHERE pcr.family_id = ?
         ORDER BY pcr.requested_at DESC"
    );
    $s->bind_param('i', $uid);
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    echo json_encode(['success' => true, 'gamblers' => $rows]);
    exit();
}

// ── Get gambler activity (bookings, interview, contracts) ─────────────────────
if ($action === 'gambler_activity' && $me['role'] === 'family') {
    $gambler_id = (int)($_GET['gambler_id'] ?? 0);
    if (!$gambler_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid gambler']);
        exit();
    }

    // Verify accepted access
    $chk = $conn->prepare(
        "SELECT id FROM parental_control_requests
         WHERE family_id = ? AND gambler_id = ? AND status = 'accepted'"
    );
    $chk->bind_param('ii', $uid, $gambler_id);
    $chk->execute();
    $access = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$access) {
        echo json_encode(['success' => false, 'message' => 'Access not granted']);
        exit();
    }

    // Gambler info
    $gs = $conn->prepare("SELECT first_name, last_name, email, created_at FROM users WHERE id = ?");
    $gs->bind_param('i', $gambler_id);
    $gs->execute();
    $gambler_info = $gs->get_result()->fetch_assoc();
    $gs->close();

    if (!$gambler_info) {
        echo json_encode(['success' => false, 'message' => 'Gambler not found']);
        exit();
    }

    // Bookings
    $bookings = [];
    $bs = $conn->prepare(
        "SELECT id, start_time, end_time, status, created_at
         FROM booking_record
         WHERE LOWER(email) = LOWER(?)
         ORDER BY created_at DESC
         LIMIT 20"
    );
    if ($bs) {
        $bs->bind_param('s', $gambler_info['email']);
        $bs->execute();
        $bookings = $bs->get_result()->fetch_all(MYSQLI_ASSOC);
        $bs->close();
    }

    // Interview records — table name is lowercase
    $interviews = [];
    $is = $conn->prepare(
        "SELECT ii.score, ii.diagnosis, ii.remarks, ii.created_at,
                CONCAT(u.first_name,' ',u.last_name) AS interviewer
         FROM initial_interview_record ii
         JOIN booking_record br ON br.id = ii.booking_id
         LEFT JOIN users u ON u.id = ii.interviewer_id
         WHERE LOWER(br.email) = LOWER(?)
         ORDER BY ii.created_at DESC
         LIMIT 5"
    );
    if ($is) {
        $is->bind_param('s', $gambler_info['email']);
        $is->execute();
        $interviews = $is->get_result()->fetch_all(MYSQLI_ASSOC);
        $is->close();
    }

    // Contracts — column is gambler_id, not user_id; no submitted_at column
    $contracts = [];
    $cs = $conn->prepare(
        "SELECT id, status, created_at AS submitted_at
         FROM contract_documents
         WHERE gambler_id = ?
         ORDER BY created_at DESC
         LIMIT 10"
    );
    if ($cs) {
        $cs->bind_param('i', $gambler_id);
        $cs->execute();
        $contracts = $cs->get_result()->fetch_all(MYSQLI_ASSOC);
        $cs->close();
    }

    // Also grab contract_submissions (the other contract table)
    $contract_subs = [];
    $css = $conn->prepare(
        "SELECT id, status, ea_verification_status, submitted_at, created_at
         FROM contract_submissions
         WHERE gambler_id = ?
         ORDER BY created_at DESC
         LIMIT 10"
    );
    if ($css) {
        $css->bind_param('i', $gambler_id);
        $css->execute();
        $contract_subs = $css->get_result()->fetch_all(MYSQLI_ASSOC);
        $css->close();
    }

    // Payments — ensure receipts table exists first
    $conn->query("CREATE TABLE IF NOT EXISTS `receipts` (
        `id`             INT(11)      NOT NULL AUTO_INCREMENT,
        `payment_id`     INT(11)      NOT NULL,
        `receipt_number` VARCHAR(100) NULL,
        `verified_by`    INT(11)      NULL,
        `verified_at`    DATETIME     NULL,
        `notes`          TEXT         NULL,
        `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $payments = [];
    $ps = $conn->prepare(
        "SELECT p.id, p.amount, p.currency, p.payment_status, p.paid_at, p.created_at,
                r.receipt_number, r.verified_at,
                CONCAT(vu.first_name,' ',vu.last_name) AS verified_by_name
         FROM payments p
         LEFT JOIN receipts r ON r.payment_id = p.id
         LEFT JOIN users vu ON vu.id = r.verified_by
         WHERE p.user_id = ?
         ORDER BY p.created_at DESC
         LIMIT 20"
    );
    if ($ps) {
        $ps->bind_param('i', $gambler_id);
        $ps->execute();
        $payments = $ps->get_result()->fetch_all(MYSQLI_ASSOC);
        $ps->close();
    }

    // CBT Sessions — ensure table exists first
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

    $cbt_sessions = [];
    $bk = $conn->prepare(
        "SELECT id FROM booking_record WHERE LOWER(email) = LOWER(?) LIMIT 1"
    );
    if ($bk) {
        $bk->bind_param('s', $gambler_info['email']);
        $bk->execute();
        $bk_row = $bk->get_result()->fetch_assoc();
        $bk->close();

        if ($bk_row) {
            $booking_id_cbt = (int)$bk_row['id'];
            $cbt_q = $conn->prepare(
                "SELECT csp.session_number, csp.status, csp.unlocked_at, csp.completed_at, csp.notes,
                        CONCAT(u.first_name,' ',u.last_name) AS unlocked_by_name
                 FROM cbt_session_progress csp
                 LEFT JOIN users u ON u.id = csp.unlocked_by
                 WHERE csp.booking_id = ?
                 ORDER BY csp.session_number ASC"
            );
            if ($cbt_q) {
                $cbt_q->bind_param('i', $booking_id_cbt);
                $cbt_q->execute();
                $cbt_sessions = $cbt_q->get_result()->fetch_all(MYSQLI_ASSOC);
                $cbt_q->close();
            }
        }
    }

    echo json_encode([
        'success'          => true,
        'gambler'          => $gambler_info,
        'bookings'         => $bookings,
        'interviews'       => $interviews,
        'contracts'        => $contracts,
        'contract_subs'    => $contract_subs,
        'payments'         => $payments,
        'cbt_sessions'     => $cbt_sessions,
    ]);
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// GAMBLER ACTIONS
// ════════════════════════════════════════════════════════════════════════════

// ── List pending requests for gambler ────────────────────────────────────────
if ($action === 'my_requests' && $me['role'] === 'gambler') {
    $s = $conn->prepare(
        "SELECT pcr.id, pcr.status, pcr.requested_at,
                u.id AS family_id, u.first_name, u.last_name, u.email
         FROM parental_control_requests pcr
         JOIN users u ON u.id = pcr.family_id
         WHERE pcr.gambler_id = ?
         ORDER BY pcr.requested_at DESC"
    );
    $s->bind_param('i', $uid);
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    echo json_encode(['success' => true, 'requests' => $rows]);
    exit();
}

// ── Respond to request (accept / decline) ────────────────────────────────────
if ($action === 'respond' && $me['role'] === 'gambler') {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $response   = $_POST['response'] ?? '';

    if (!$request_id || !in_array($response, ['accepted', 'declined'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit();
    }

    // Verify this request belongs to this gambler
    $chk = $conn->prepare(
        "SELECT pcr.id, pcr.family_id, u.first_name, u.last_name
         FROM parental_control_requests pcr
         JOIN users u ON u.id = pcr.family_id
         WHERE pcr.id = ? AND pcr.gambler_id = ? AND pcr.status = 'pending'"
    );
    $chk->bind_param('ii', $request_id, $uid);
    $chk->execute();
    $req = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$req) {
        echo json_encode(['success' => false, 'message' => 'Request not found or already responded']);
        exit();
    }

    // Update status
    $upd = $conn->prepare(
        "UPDATE parental_control_requests SET status = ?, responded_at = NOW() WHERE id = ?"
    );
    $upd->bind_param('si', $response, $request_id);
    $upd->execute();
    $upd->close();

    // Notify the family member
    $gambler_name = $me['first_name'] . ' ' . $me['last_name'];
    $family_id    = (int)$req['family_id'];

    if ($response === 'accepted') {
        $notif_title = 'Parental Access Granted';
        $notif_msg   = $gambler_name . ' has accepted your parental control request. You can now monitor their rehabilitation activity.';
        $notif_link  = '/GAMBYTES_Final/app/views/Users/Family member/parental-control.php';
    } else {
        $notif_title = 'Parental Access Declined';
        $notif_msg   = $gambler_name . ' has declined your parental control request.';
        $notif_link  = '/GAMBYTES_Final/app/views/Users/Family member/parental-control.php';
    }

    $notif_type = 'parental_control';
    $n = $conn->prepare(
        "INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
         VALUES (?, ?, ?, ?, ?, 0, NOW())"
    );
    $n->bind_param('issss', $family_id, $notif_type, $notif_title, $notif_msg, $notif_link);
    $n->execute();
    $n->close();

    echo json_encode(['success' => true, 'message' => 'Response recorded: ' . $response]);
    exit();
}

// ── Revoke access (gambler removes family member's access) ───────────────────
if ($action === 'revoke' && $me['role'] === 'gambler') {
    $request_id = (int)($_POST['request_id'] ?? 0);
    if (!$request_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }

    $del = $conn->prepare(
        "DELETE FROM parental_control_requests WHERE id = ? AND gambler_id = ?"
    );
    $del->bind_param('ii', $request_id, $uid);
    $del->execute();
    $del->close();

    echo json_encode(['success' => true, 'message' => 'Access revoked']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action or insufficient permissions']);
