<?php
/**
 * api/contract_forms.php
 * Handles contract form templates (supervisor upload) and contract submissions.
 */
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

// ── Fetch current user ────────────────────────────────────────────────────────
$rs = $conn->prepare("SELECT role, first_name, last_name FROM users WHERE id = ?");
$rs->bind_param('i', $uid);
$rs->execute();
$me = $rs->get_result()->fetch_assoc();
$rs->close();
if (!$me) { echo json_encode(['success' => false, 'message' => 'User not found']); exit(); }

// ── Auto-create tables ────────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `contract_form_templates` (
    `id`            INT(11)      NOT NULL AUTO_INCREMENT,
    `title`         VARCHAR(255) NOT NULL,
    `description`   TEXT         NULL,
    `filename`      VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `uploaded_by`   INT(11)      NOT NULL,
    `uploaded_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `contract_submissions` (
    `id`                INT(11)      NOT NULL AUTO_INCREMENT,
    `template_id`       INT(11)      NOT NULL,
    `gambler_id`        INT(11)      NOT NULL,
    `family_member_id`  INT(11)      NULL,
    `booking_id`        INT(11)      NULL,
    `gambler_data`      LONGTEXT     NULL COMMENT 'JSON of gambler-filled fields',
    `family_data`       LONGTEXT     NULL COMMENT 'JSON of family-filled fields',
    `gambler_sig`       LONGTEXT     NULL,
    `family_sig`        LONGTEXT     NULL,
    `status`            ENUM('draft','submitted','reviewed','sent_to_parties','completed')
                        NOT NULL DEFAULT 'draft',
    `sent_at`           DATETIME     NULL,
    `submitted_at`      DATETIME     NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_gambler`  (`gambler_id`),
    INDEX `idx_template` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ════════════════════════════════════════════════════════════════════════════
// SUPERVISOR ACTIONS
// ════════════════════════════════════════════════════════════════════════════

// ── Upload contract form template ─────────────────────────────────────────────
if ($action === 'upload_template' && in_array($me['role'], ['supervisor', 'admin'])) {
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!$title) {
        echo json_encode(['success' => false, 'message' => 'Title is required.']);
        exit();
    }
    if (empty($_FILES['template_file']) || $_FILES['template_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
        exit();
    }

    $file = $_FILES['template_file'];
    $mime = mime_content_type($file['tmp_name']);
    $allowed = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    if (!in_array($mime, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Only PDF, DOC, DOCX files are allowed.']);
        exit();
    }
    if ($file['size'] > 15 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large. Max 15 MB.']);
        exit();
    }

    $upload_dir = __DIR__ . '/../uploads/contract_templates/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safe_ext = in_array($ext, ['pdf','doc','docx']) ? $ext : 'bin';
    $new_name = 'ctpl_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safe_ext;

    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
        exit();
    }

    $ins = $conn->prepare(
        "INSERT INTO contract_form_templates (title, description, filename, original_name, uploaded_by)
         VALUES (?, ?, ?, ?, ?)"
    );
    $ins->bind_param('ssssi', $title, $description, $new_name, $file['name'], $uid);
    $ins->execute();
    $ins->close();

    echo json_encode(['success' => true, 'message' => 'Contract form template uploaded successfully.']);
    exit();
}

// ── List templates ────────────────────────────────────────────────────────────
if ($action === 'list_templates') {
    $res = $conn->query(
        "SELECT ct.id, ct.title, ct.description, ct.filename, ct.original_name,
                ct.uploaded_at, ct.is_active,
                CONCAT(u.first_name,' ',u.last_name) AS uploader_name
         FROM contract_form_templates ct
         LEFT JOIN users u ON u.id = ct.uploaded_by
         ORDER BY ct.uploaded_at DESC"
    );
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $r['url'] = '/GAMBYTES_Final/uploads/contract_templates/' . rawurlencode($r['filename']);
        $rows[] = $r;
    }
    echo json_encode(['success' => true, 'templates' => $rows]);
    exit();
}

// ── Toggle template active/inactive ──────────────────────────────────────────
if ($action === 'toggle_template' && in_array($me['role'], ['supervisor', 'admin'])) {
    $tid = (int)($_POST['template_id'] ?? 0);
    if (!$tid) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit(); }
    $conn->query("UPDATE contract_form_templates SET is_active = NOT is_active WHERE id = $tid");
    echo json_encode(['success' => true]);
    exit();
}

// ── Delete template ───────────────────────────────────────────────────────────
if ($action === 'delete_template' && in_array($me['role'], ['supervisor', 'admin'])) {
    $tid = (int)($_POST['template_id'] ?? 0);
    if (!$tid) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit(); }

    $sel = $conn->prepare("SELECT filename FROM contract_form_templates WHERE id = ?");
    $sel->bind_param('i', $tid);
    $sel->execute();
    $frow = $sel->get_result()->fetch_assoc();
    $sel->close();

    if ($frow) {
        $path = __DIR__ . '/../uploads/contract_templates/' . $frow['filename'];
        if (file_exists($path)) unlink($path);
        $conn->query("DELETE FROM contract_form_templates WHERE id = $tid");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Template not found.']);
    }
    exit();
}

// ── List submissions (supervisor review) ─────────────────────────────────────
if ($action === 'list_submissions' && in_array($me['role'], ['supervisor', 'admin'])) {
    $res = $conn->query(
        "SELECT cs.id, cs.status, cs.submitted_at, cs.sent_at,
                cs.gambler_data, cs.family_data,
                ct.title AS template_title, ct.filename AS template_filename,
                CONCAT(gu.first_name,' ',gu.last_name) AS gambler_name, gu.email AS gambler_email,
                CONCAT(fu.first_name,' ',fu.last_name) AS family_name, fu.email AS family_email,
                cs.gambler_id, cs.family_member_id, cs.template_id
         FROM contract_submissions cs
         JOIN contract_form_templates ct ON ct.id = cs.template_id
         JOIN users gu ON gu.id = cs.gambler_id
         LEFT JOIN users fu ON fu.id = cs.family_member_id
         WHERE cs.status IN ('submitted','reviewed','sent_to_parties','completed')
         ORDER BY cs.submitted_at DESC"
    );
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $r['template_url'] = '/GAMBYTES_Final/uploads/contract_templates/' . rawurlencode($r['template_filename']);
        $r['gambler_data'] = json_decode($r['gambler_data'] ?? '{}', true);
        $r['family_data']  = json_decode($r['family_data']  ?? '{}', true);
        $rows[] = $r;
    }
    echo json_encode(['success' => true, 'submissions' => $rows]);
    exit();
}

// ── Send contract to parties (gambler + family) ───────────────────────────────
if ($action === 'send_to_parties' && in_array($me['role'], ['supervisor', 'admin'])) {
    $sub_id = (int)($_POST['submission_id'] ?? 0);

    if (!$sub_id) { echo json_encode(['success' => false, 'message' => 'Invalid submission']); exit(); }

    // Fetch submission
    $s = $conn->prepare(
        "SELECT cs.*, ct.title AS template_title,
                CONCAT(gu.first_name,' ',gu.last_name) AS gambler_name,
                CONCAT(fu.first_name,' ',fu.last_name) AS family_name
         FROM contract_submissions cs
         JOIN contract_form_templates ct ON ct.id = cs.template_id
         JOIN users gu ON gu.id = cs.gambler_id
         LEFT JOIN users fu ON fu.id = cs.family_member_id
         WHERE cs.id = ?"
    );
    $s->bind_param('i', $sub_id);
    $s->execute();
    $sub = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$sub) { echo json_encode(['success' => false, 'message' => 'Submission not found']); exit(); }

    // Update status
    $upd = $conn->prepare(
        "UPDATE contract_submissions SET status='sent_to_parties', sent_at=NOW() WHERE id=?"
    );
    $upd->bind_param('i', $sub_id);
    $upd->execute();
    $upd->close();

    $link_gambler = '/GAMBYTES_Final/app/views/Users/Gamblers/contract/fill-contract.php?submission_id=' . $sub_id;
    $link_family  = '/GAMBYTES_Final/app/views/Users/Family member/fill-contract.php?submission_id=' . $sub_id;
    $sup_name     = $me['first_name'] . ' ' . $me['last_name'];

    // Notify gambler
    $notif_title = 'Contract Form Ready for Review';
    $notif_msg   = 'Your supervisor ' . $sup_name . ' has sent you the contract form "' . $sub['template_title'] . '" to review and fill out.';
    $notif_type  = 'contract';
    $n = $conn->prepare("INSERT INTO notifications (user_id,type,title,message,link,is_read,created_at) VALUES (?,?,?,?,?,0,NOW())");
    $n->bind_param('issss', $sub['gambler_id'], $notif_type, $notif_title, $notif_msg, $link_gambler);
    $n->execute();
    $n->close();

    // Notify family member if linked
    if ($sub['family_member_id']) {
        $fid = (int)$sub['family_member_id'];
        $notif_msg_f = 'Your supervisor ' . $sup_name . ' has sent you the contract form "' . $sub['template_title'] . '" to review and fill out.';
        $n2 = $conn->prepare("INSERT INTO notifications (user_id,type,title,message,link,is_read,created_at) VALUES (?,?,?,?,?,0,NOW())");
        $n2->bind_param('issss', $fid, $notif_type, $notif_title, $notif_msg_f, $link_family);
        $n2->execute();
        $n2->close();
    }

    echo json_encode(['success' => true, 'message' => 'Contract sent to parties successfully.']);
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// GAMBLER / FAMILY ACTIONS
// ════════════════════════════════════════════════════════════════════════════

// ── Get active templates (for gambler to pick when signing) ──────────────────
if ($action === 'active_templates') {
    $res = $conn->query(
        "SELECT id, title, description, filename, original_name, uploaded_at
         FROM contract_form_templates WHERE is_active = 1 ORDER BY uploaded_at DESC"
    );
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $r['url'] = '/GAMBYTES_Final/uploads/contract_templates/' . rawurlencode($r['filename']);
        $rows[] = $r;
    }
    echo json_encode(['success' => true, 'templates' => $rows]);
    exit();
}

// ── Submit filled contract (gambler) ─────────────────────────────────────────
if ($action === 'submit_contract' && $me['role'] === 'gambler') {
    $template_id      = (int)($_POST['template_id']      ?? 0);
    $booking_id       = (int)($_POST['booking_id']       ?? 0);
    $family_member_id = (int)($_POST['family_member_id'] ?? 0) ?: null;
    $gambler_data     = $_POST['gambler_data']     ?? '{}';
    $family_data      = $_POST['family_data']      ?? '{}';
    $gambler_sig      = $_POST['gambler_sig']      ?? '';
    $family_sig       = $_POST['family_sig']       ?? '';

    if (!$template_id) {
        echo json_encode(['success' => false, 'message' => 'No contract template selected.']);
        exit();
    }

    // If family_member_id not provided, look it up from parental_control_requests
    if (!$family_member_id) {
        $famStmt = $conn->prepare("SELECT family_id FROM parental_control_requests WHERE gambler_id = ? AND status = 'accepted' ORDER BY created_at DESC LIMIT 1");
        if ($famStmt) {
            $famStmt->bind_param('i', $uid);
            $famStmt->execute();
            $famRow = $famStmt->get_result()->fetch_assoc();
            $famStmt->close();
            if ($famRow) {
                $family_member_id = (int)$famRow['family_id'];
            }
        }
    }

    // Check if already submitted for this booking+template
    if ($booking_id) {
        $chk = $conn->prepare(
            "SELECT id FROM contract_submissions WHERE gambler_id=? AND booking_id=? AND template_id=? LIMIT 1"
        );
        $chk->bind_param('iii', $uid, $booking_id, $template_id);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            $chk->close();
            echo json_encode(['success' => false, 'message' => 'Already submitted for this booking.']);
            exit();
        }
        $chk->close();
    }

    $status = 'submitted';
    $ins = $conn->prepare(
        "INSERT INTO contract_submissions
         (template_id, gambler_id, family_member_id, booking_id,
          gambler_data, family_data, gambler_sig, family_sig, status, submitted_at)
         VALUES (?,?,?,?,?,?,?,?,?,NOW())"
    );
    $ins->bind_param(
        'iiiiisssss',
        $template_id, $uid, $family_member_id, $booking_id,
        $gambler_data, $family_data, $gambler_sig, $family_sig,
        $status
    );

    if ($ins->execute()) {
        $new_id = $conn->insert_id;
        $ins->close();

        // Notify supervisor
        $sup_res = $conn->query("SELECT id FROM users WHERE role IN ('supervisor','admin') LIMIT 5");
        $gambler_name = $me['first_name'] . ' ' . $me['last_name'];
        $notif_title  = 'New Contract Submission';
        $notif_msg    = $gambler_name . ' has submitted a contract form for your review.';
        $notif_link   = '/GAMBYTES_Final/app/views/Users/Supervisor/contract-review.php';
        $notif_type   = 'contract';
        while ($sup = $sup_res->fetch_assoc()) {
            $sid = (int)$sup['id'];
            $n = $conn->prepare("INSERT INTO notifications (user_id,type,title,message,link,is_read,created_at) VALUES (?,?,?,?,?,0,NOW())");
            $n->bind_param('issss', $sid, $notif_type, $notif_title, $notif_msg, $notif_link);
            $n->execute();
            $n->close();
        }

        echo json_encode(['success' => true, 'submission_id' => $new_id]);
    } else {
        $ins->close();
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
    }
    exit();
}

// ── Get a single submission (for fill/edit page) ──────────────────────────────
if ($action === 'get_submission') {
    $sub_id = (int)($_GET['submission_id'] ?? 0);
    if (!$sub_id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit(); }

    $s = $conn->prepare(
        "SELECT cs.*, ct.title AS template_title, ct.filename AS template_filename,
                ct.description AS template_description,
                CONCAT(gu.first_name,' ',gu.last_name) AS gambler_name, gu.email AS gambler_email,
                CONCAT(fu.first_name,' ',fu.last_name) AS family_name, fu.email AS family_email
         FROM contract_submissions cs
         JOIN contract_form_templates ct ON ct.id = cs.template_id
         JOIN users gu ON gu.id = cs.gambler_id
         LEFT JOIN users fu ON fu.id = cs.family_member_id
         WHERE cs.id = ?"
    );
    $s->bind_param('i', $sub_id);
    $s->execute();
    $sub = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$sub) { echo json_encode(['success' => false, 'message' => 'Submission not found']); exit(); }

    // Access check: only gambler, family member, or supervisor can view
    $allowed = in_array($me['role'], ['supervisor', 'admin'])
        || (int)$sub['gambler_id'] === $uid
        || (int)$sub['family_member_id'] === $uid;

    if (!$allowed) { echo json_encode(['success' => false, 'message' => 'Access denied']); exit(); }

    $sub['template_url'] = '/GAMBYTES_Final/uploads/contract_templates/' . rawurlencode($sub['template_filename']);
    $sub['gambler_data'] = json_decode($sub['gambler_data'] ?? '{}', true);
    $sub['family_data']  = json_decode($sub['family_data']  ?? '{}', true);

    echo json_encode(['success' => true, 'submission' => $sub]);
    exit();
}

// ── Save family member signature ──────────────────────────────────────────────
if ($action === 'save_family_sig' && $me['role'] === 'family') {
    $sub_id = (int)($_POST['submission_id'] ?? 0);
    if (!$sub_id) { echo json_encode(['success' => false, 'message' => 'Invalid submission']); exit(); }

    $s = $conn->prepare("SELECT id, family_member_id, status FROM contract_submissions WHERE id = ?");
    $s->bind_param('i', $sub_id);
    $s->execute();
    $sub = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$sub || (int)$sub['family_member_id'] !== $uid) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    if (in_array($sub['status'], ['completed'])) {
        echo json_encode(['success' => false, 'message' => 'Already completed']);
        exit();
    }

    $sig = $_POST['family_sig'] ?? '';
    $upd = $conn->prepare("UPDATE contract_submissions SET family_sig=?, status='sent_to_parties' WHERE id=?");
    $upd->bind_param('si', $sig, $sub_id);
    $upd->execute();
    $upd->close();

    echo json_encode(['success' => true, 'message' => 'Contract signed successfully.']);
    exit();
}

// ── Submit an existing draft submission (gambler finalises after filling) ────
if ($action === 'submit_existing' && $me['role'] === 'gambler') {
    $sub_id = (int)($_POST['submission_id'] ?? 0);
    if (!$sub_id) { echo json_encode(['success' => false, 'message' => 'Invalid submission']); exit(); }

    $s = $conn->prepare("SELECT id, status FROM contract_submissions WHERE id = ? AND gambler_id = ?");
    $s->bind_param('ii', $sub_id, $uid);
    $s->execute();
    $sub = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$sub) { echo json_encode(['success' => false, 'message' => 'Submission not found']); exit(); }
    if (in_array($sub['status'], ['submitted', 'completed'])) {
        echo json_encode(['success' => false, 'message' => 'Already submitted']);
        exit();
    }

    // Save latest data + mark submitted
    $data = $_POST['gambler_data'] ?? '{}';
    $sig  = $_POST['gambler_sig']  ?? null;

    if ($sig) {
        $upd = $conn->prepare("UPDATE contract_submissions SET gambler_data=?, gambler_sig=?, status='submitted', submitted_at=NOW() WHERE id=?");
        $upd->bind_param('ssi', $data, $sig, $sub_id);
    } else {
        $upd = $conn->prepare("UPDATE contract_submissions SET gambler_data=?, status='submitted', submitted_at=NOW() WHERE id=?");
        $upd->bind_param('si', $data, $sub_id);
    }
    $upd->execute();
    $upd->close();

    // Notify supervisors
    $sup_res = $conn->query("SELECT id FROM users WHERE role IN ('supervisor','admin') LIMIT 5");
    $gambler_name = $me['first_name'] . ' ' . $me['last_name'];
    $notif_title  = 'New Contract Submission';
    $notif_msg    = $gambler_name . ' has submitted a contract form for your review.';
    $notif_link   = '/GAMBYTES_Final/app/views/Users/Supervisor/contract-review.php';
    $notif_type   = 'contract';
    while ($sup = $sup_res->fetch_assoc()) {
        $sid = (int)$sup['id'];
        $n = $conn->prepare("INSERT INTO notifications (user_id,type,title,message,link,is_read,created_at) VALUES (?,?,?,?,?,0,NOW())");
        $n->bind_param('issss', $sid, $notif_type, $notif_title, $notif_msg, $notif_link);
        $n->execute();
        $n->close();
    }

    echo json_encode(['success' => true, 'message' => 'Contract submitted successfully.']);
    exit();
}

// ── Save edits to a submission (gambler or family) ────────────────────────────
if ($action === 'save_edits') {
    $sub_id = (int)($_POST['submission_id'] ?? 0);
    if (!$sub_id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit(); }

    $s = $conn->prepare("SELECT gambler_id, family_member_id, status FROM contract_submissions WHERE id = ?");
    $s->bind_param('i', $sub_id);
    $s->execute();
    $sub = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$sub) { echo json_encode(['success' => false, 'message' => 'Submission not found']); exit(); }

    // Only gambler or family member can edit, and only if not completed
    $is_gambler = (int)$sub['gambler_id'] === $uid && $me['role'] === 'gambler';
    $is_family  = (int)$sub['family_member_id'] === $uid && $me['role'] === 'family';

    if (!$is_gambler && !$is_family) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    if ($sub['status'] === 'completed') {
        echo json_encode(['success' => false, 'message' => 'This contract is already completed and cannot be edited.']);
        exit();
    }

    if ($is_gambler) {
        $data = $_POST['gambler_data'] ?? '{}';
        $sig  = $_POST['gambler_sig']  ?? null;
        if ($sig) {
            $upd = $conn->prepare("UPDATE contract_submissions SET gambler_data=?, gambler_sig=? WHERE id=?");
            $upd->bind_param('ssi', $data, $sig, $sub_id);
        } else {
            $upd = $conn->prepare("UPDATE contract_submissions SET gambler_data=? WHERE id=?");
            $upd->bind_param('si', $data, $sub_id);
        }
    } else {
        $data = $_POST['family_data'] ?? '{}';
        $sig  = $_POST['family_sig']  ?? null;
        if ($sig) {
            $upd = $conn->prepare("UPDATE contract_submissions SET family_data=?, family_sig=? WHERE id=?");
            $upd->bind_param('ssi', $data, $sig, $sub_id);
        } else {
            $upd = $conn->prepare("UPDATE contract_submissions SET family_data=? WHERE id=?");
            $upd->bind_param('si', $data, $sub_id);
        }
    }
    $upd->execute();
    $upd->close();

    echo json_encode(['success' => true, 'message' => 'Changes saved.']);
    exit();
}

// ── List submissions for gambler ──────────────────────────────────────────────
if ($action === 'my_submissions' && $me['role'] === 'gambler') {
    $s = $conn->prepare(
        "SELECT cs.id, cs.status, cs.submitted_at, cs.sent_at,
                ct.title AS template_title, ct.filename AS template_filename
         FROM contract_submissions cs
         JOIN contract_form_templates ct ON ct.id = cs.template_id
         WHERE cs.gambler_id = ?
         ORDER BY cs.submitted_at DESC"
    );
    $s->bind_param('i', $uid);
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    foreach ($rows as &$r) {
        $r['template_url'] = '/GAMBYTES_Final/uploads/contract_templates/' . rawurlencode($r['template_filename']);
    }
    echo json_encode(['success' => true, 'submissions' => $rows]);
    exit();
}

// ── List submissions for family member ────────────────────────────────────────
if ($action === 'my_family_submissions' && $me['role'] === 'family') {
    $s = $conn->prepare(
        "SELECT cs.id, cs.status, cs.submitted_at, cs.sent_at,
                ct.title AS template_title, ct.filename AS template_filename,
                CONCAT(gu.first_name,' ',gu.last_name) AS gambler_name
         FROM contract_submissions cs
         JOIN contract_form_templates ct ON ct.id = cs.template_id
         JOIN users gu ON gu.id = cs.gambler_id
         WHERE cs.family_member_id = ?
         ORDER BY cs.submitted_at DESC"
    );
    $s->bind_param('i', $uid);
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    foreach ($rows as &$r) {
        $r['template_url'] = '/GAMBYTES_Final/uploads/contract_templates/' . rawurlencode($r['template_filename']);
    }
    echo json_encode(['success' => true, 'submissions' => $rows]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action or insufficient permissions']);
