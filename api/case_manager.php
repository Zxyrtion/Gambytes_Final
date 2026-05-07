<?php
/**
 * api/case_manager.php
 * Handles case manager activity management and gambler submissions.
 */
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../app/core/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit();
}

$db   = new Database();
$conn = $db->connect();

$user_id = (int)$_SESSION['user_id'];
$uStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$uStmt->bind_param('i', $user_id); $uStmt->execute();
$me = $uStmt->get_result()->fetch_assoc(); $uStmt->close();

// ── Ensure tables exist ───────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `interventions_assessments` (
    `id`            INT(11)      NOT NULL AUTO_INCREMENT,
    `booking_id`    INT(11)      NOT NULL,
    `gambler_id`    INT(11)      NOT NULL,
    `created_by`    INT(11)      NOT NULL COMMENT 'Case manager who created this',
    `title`         VARCHAR(255) NOT NULL,
    `description`   TEXT         NULL,
    `document_path` VARCHAR(500) NULL,
    `document_name` VARCHAR(255) NULL,
    `open_date`     DATE         NOT NULL,
    `close_date`    DATE         NOT NULL,
    `status`        VARCHAR(20)  NOT NULL DEFAULT 'pending' COMMENT 'pending, opened',
    `opened_at`     DATETIME     NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_booking` (`booking_id`),
    INDEX `idx_gambler` (`gambler_id`),
    INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add status/opened_at columns if they don't exist yet (for existing tables)
$conn->query("ALTER TABLE `interventions_assessments` ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER `close_date`");
$conn->query("ALTER TABLE `interventions_assessments` ADD COLUMN IF NOT EXISTS `opened_at` DATETIME NULL AFTER `status`");

$conn->query("CREATE TABLE IF NOT EXISTS `activity_submissions` (
    `id`           INT(11)      NOT NULL AUTO_INCREMENT,
    `activity_id`  INT(11)      NOT NULL COMMENT 'FK to interventions_assessments.id',
    `gambler_id`   INT(11)      NOT NULL,
    `file_path`    VARCHAR(500) NULL,
    `file_name`    VARCHAR(255) NULL,
    `notes`        TEXT         NULL,
    `submitted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_activity` (`activity_id`),
    INDEX `idx_gambler`  (`gambler_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ── CASE MANAGER ACTIONS ──────────────────────────────────────────────────────
if ($me['role'] === 'case_manager') {

    if ($action === 'create_activity') {
        // Log the request
        error_log("case_manager.php: create_activity called");
        error_log("POST data: " . print_r($_POST, true));
        
        $booking_id  = (int)($_POST['booking_id']  ?? 0);
        $gambler_id  = (int)($_POST['gambler_id']  ?? 0);
        $title       = trim($_POST['title']        ?? '');
        $description = trim($_POST['description']  ?? '');
        $open_date   = trim($_POST['open_date']    ?? '');
        $close_date  = trim($_POST['close_date']   ?? '');

        error_log("Parsed values - booking_id: $booking_id, gambler_id: $gambler_id, title: $title");

        if (!$booking_id || !$gambler_id || !$title || !$open_date || !$close_date) {
            error_log("Missing required fields");
            echo json_encode(['success' => false, 'message' => 'Missing required fields']); exit();
        }

        $doc_path = null; $doc_name = null;
        if (!empty($_FILES['document']['name'])) {
            $upload_dir = __DIR__ . '/../uploads/activities/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext     = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf','doc','docx','jpg','jpeg','png'];
            if (!in_array($ext, $allowed)) {
                echo json_encode(['success' => false, 'message' => 'File type not allowed (pdf, doc, docx, jpg, png)']); exit();
            }
            $safe_name = 'act_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['document']['tmp_name'], $upload_dir . $safe_name)) {
                echo json_encode(['success' => false, 'message' => 'File upload failed']); exit();
            }
            $doc_path = '/GAMBYTES_Final/uploads/activities/' . $safe_name;
            $doc_name = $_FILES['document']['name'];
        }

        $ins = $conn->prepare(
            "INSERT INTO interventions_assessments
             (booking_id, gambler_id, created_by, title, description, document_path, document_name, open_date, close_date)
             VALUES (?,?,?,?,?,?,?,?,?)"
        );
        
        if (!$ins) {
            error_log("case_manager.php: Prepare failed - " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]); exit();
        }
        
        $ins->bind_param('iiissssss', $booking_id, $gambler_id, $user_id, $title, $description, $doc_path, $doc_name, $open_date, $close_date);
        
        if (!$ins->execute()) {
            error_log("case_manager.php: Execute failed - " . $ins->error);
            echo json_encode(['success' => false, 'message' => 'Failed to save activity: ' . $ins->error]); exit();
        }
        
        $new_id = (int)$conn->insert_id;
        $ins->close();
        
        error_log("case_manager.php: Activity created successfully with ID: $new_id");

        // Notify gambler
        $notifLink  = '/GAMBYTES_Final/app/views/Users/Gamblers/my-activities.php';
        $notifTitle = 'New Activity Assigned: ' . $title;
        $notifMsg   = 'Your case manager has assigned a new activity. Open: '
                    . date('M j, Y', strtotime($open_date))
                    . ' – Close: ' . date('M j, Y', strtotime($close_date));
        $n = $conn->prepare("INSERT INTO notifications (user_id,type,title,message,link,is_read,created_at) VALUES (?,?,?,?,?,0,NOW())");
        if ($n) {
            $notif_type = 'activity_assigned';
            $n->bind_param('issss', $gambler_id, $notif_type, $notifTitle, $notifMsg, $notifLink);
            $n->execute(); $n->close();
        }

        echo json_encode(['success' => true, 'activity_id' => $new_id, 'message' => 'Activity created successfully']); exit();
    }

    if ($action === 'delete_activity') {
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $act_id = (int)($input['activity_id'] ?? 0);
        $d = $conn->prepare("DELETE FROM interventions_assessments WHERE id = ? AND created_by = ?");
        $d->bind_param('ii', $act_id, $user_id); $d->execute(); $d->close();
        echo json_encode(['success' => true]); exit();
    }

    if ($action === 'open_activity') {
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $act_id = (int)($input['activity_id'] ?? 0);
        if (!$act_id) { echo json_encode(['success' => false, 'message' => 'Invalid activity ID']); exit(); }

        // Verify this activity belongs to this case manager
        $chk = $conn->prepare("SELECT id, gambler_id, title, booking_id FROM interventions_assessments WHERE id = ? AND created_by = ? LIMIT 1");
        $chk->bind_param('ii', $act_id, $user_id); $chk->execute();
        $act = $chk->get_result()->fetch_assoc(); $chk->close();
        if (!$act) { echo json_encode(['success' => false, 'message' => 'Activity not found']); exit(); }

        $upd = $conn->prepare("UPDATE interventions_assessments SET status = 'opened', opened_at = NOW() WHERE id = ?");
        $upd->bind_param('i', $act_id); $upd->execute(); $upd->close();

        // Notify gambler
        $notifLink  = '/GAMBYTES_Final/app/views/Users/Gamblers/view-activity.php?activity_id=' . $act_id;
        $notifTitle = 'Activity Opened: ' . $act['title'];
        $notifMsg   = 'Your case manager has opened an activity for you. Click to view and submit.';
        $n = $conn->prepare("INSERT INTO notifications (user_id,type,title,message,link,is_read,created_at) VALUES (?,?,?,?,?,0,NOW())");
        if ($n) {
            $ntype = 'activity_opened';
            $n->bind_param('issss', $act['gambler_id'], $ntype, $notifTitle, $notifMsg, $notifLink);
            $n->execute(); $n->close();
        }

        echo json_encode(['success' => true, 'gambler_id' => $act['gambler_id']]); exit();
    }

    if ($action === 'get_activities') {
        $booking_id = (int)($_GET['booking_id'] ?? 0);
        $stmt = $conn->prepare(
            "SELECT ia.*,
                    (SELECT COUNT(*) FROM activity_submissions WHERE activity_id = ia.id) AS submission_count
             FROM interventions_assessments ia
             WHERE ia.booking_id = ?
             ORDER BY ia.created_at DESC"
        );
        $stmt->bind_param('i', $booking_id); $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        echo json_encode(['success' => true, 'activities' => $rows]); exit();
    }

    if ($action === 'get_submissions') {
        $act_id = (int)($_GET['activity_id'] ?? 0);
        $stmt = $conn->prepare(
            "SELECT s.*, CONCAT(u.first_name,' ',u.last_name) AS gambler_name
             FROM activity_submissions s
             JOIN users u ON u.id = s.gambler_id
             WHERE s.activity_id = ?
             ORDER BY s.submitted_at DESC"
        );
        $stmt->bind_param('i', $act_id); $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        echo json_encode(['success' => true, 'submissions' => $rows]); exit();
    }

    // ── CBT Session Management ────────────────────────────────────────────────
    if ($action === 'create_cbt_activity') {
        $booking_id  = (int)($_POST['booking_id']  ?? 0);
        $gambler_id  = (int)($_POST['gambler_id']  ?? 0);
        $title       = trim($_POST['title']        ?? '');
        $description = trim($_POST['description']  ?? '');
        $open_date   = trim($_POST['open_date']    ?? '');
        $close_date  = trim($_POST['close_date']   ?? '');

        if (!$booking_id || !$gambler_id || !$title || !$open_date || !$close_date) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']); exit();
        }

        $doc_path = null; $doc_name = null;
        if (!empty($_FILES['document']['name'])) {
            $upload_dir = __DIR__ . '/../app/views/Users/Case manager/Session_activity/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext     = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf','doc','docx','jpg','jpeg','png'];
            if (!in_array($ext, $allowed)) {
                echo json_encode(['success' => false, 'message' => 'File type not allowed']); exit();
            }
            $safe_name = 'session_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['document']['tmp_name'], $upload_dir . $safe_name)) {
                echo json_encode(['success' => false, 'message' => 'File upload failed']); exit();
            }
            $doc_path = '/GAMBYTES_Final/app/views/Users/Case manager/Session_activity/' . $safe_name;
            $doc_name = $_FILES['document']['name'];
        }

        $ins = $conn->prepare(
            "INSERT INTO interventions_assessments
             (booking_id, gambler_id, created_by, title, description, document_path, document_name, open_date, close_date)
             VALUES (?,?,?,?,?,?,?,?,?)"
        );
        if (!$ins) { echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]); exit(); }
        $ins->bind_param('iiissssss', $booking_id, $gambler_id, $user_id, $title, $description, $doc_path, $doc_name, $open_date, $close_date);
        if (!$ins->execute()) { echo json_encode(['success' => false, 'message' => 'Failed to save: ' . $ins->error]); exit(); }
        $new_id = (int)$conn->insert_id;
        $ins->close();

        // Notify gambler
        $notifLink  = '/GAMBYTES_Final/app/views/Users/Gamblers/my-activities.php';
        $notifTitle = 'New CBT Activity: ' . $title;
        $notifMsg   = 'Your case manager has assigned a CBT session activity. Open: ' . date('M j, Y', strtotime($open_date)) . ' – Due: ' . date('M j, Y', strtotime($close_date));
        $n = $conn->prepare("INSERT INTO notifications (user_id,type,title,message,link,is_read,created_at) VALUES (?,?,?,?,?,0,NOW())");
        if ($n) {
            $ntype = 'activity_assigned';
            $n->bind_param('issss', $gambler_id, $ntype, $notifTitle, $notifMsg, $notifLink);
            $n->execute(); $n->close();
        }
        echo json_encode(['success' => true, 'activity_id' => $new_id]); exit();
    }

    if ($action === 'unlock_cbt_session') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $booking_id     = (int)($input['booking_id']     ?? 0);
        $session_number = (int)($input['session_number'] ?? 0);

        if (!$booking_id || $session_number < 1 || $session_number > 6) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit();
        }

        // Ensure previous session is completed (except session 1)
        if ($session_number > 1) {
            $prevStmt = $conn->prepare("SELECT status FROM cbt_session_progress WHERE booking_id = ? AND session_number = ?");
            $prevNum  = $session_number - 1;
            $prevStmt->bind_param('ii', $booking_id, $prevNum);
            $prevStmt->execute();
            $prev = $prevStmt->get_result()->fetch_assoc();
            $prevStmt->close();
            if (!$prev || $prev['status'] !== 'completed') {
                echo json_encode(['success' => false, 'message' => 'Previous session must be completed first']); exit();
            }
        }

        $stmt = $conn->prepare(
            "UPDATE cbt_session_progress SET status = 'unlocked', unlocked_at = NOW(), unlocked_by = ?
             WHERE booking_id = ? AND session_number = ? AND status = 'locked'"
        );
        $stmt->bind_param('iii', $user_id, $booking_id, $session_number);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        echo json_encode(['success' => $affected > 0, 'message' => $affected > 0 ? 'Session unlocked' : 'Session could not be unlocked']); exit();
    }

    if ($action === 'complete_cbt_session') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $booking_id    = (int)($input['booking_id']    ?? 0);
        $session_number = (int)($input['session_number'] ?? 0);

        if (!$booking_id || $session_number < 1 || $session_number > 6) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit();
        }

        $stmt = $conn->prepare(
            "UPDATE cbt_session_progress SET status = 'completed', completed_at = NOW()
             WHERE booking_id = ? AND session_number = ? AND status = 'unlocked'"
        );
        $stmt->bind_param('ii', $booking_id, $session_number);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            // Auto-unlock next session if it exists and is still locked
            if ($session_number < 6) {
                $nextNum  = $session_number + 1;
                $nextStmt = $conn->prepare(
                    "UPDATE cbt_session_progress SET status = 'unlocked', unlocked_at = NOW(), unlocked_by = ?
                     WHERE booking_id = ? AND session_number = ? AND status = 'locked'"
                );
                $nextStmt->bind_param('iii', $user_id, $booking_id, $nextNum);
                $nextStmt->execute();
                $nextStmt->close();
            }
            echo json_encode(['success' => true, 'message' => 'Session marked as completed']); exit();
        }

        echo json_encode(['success' => false, 'message' => 'Session not found or not in unlocked state']); exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']); exit();
}

// ── GAMBLER ACTIONS ───────────────────────────────────────────────────────────
if ($me['role'] === 'gambler') {

    if ($action === 'get_my_activities') {
        $stmt = $conn->prepare(
            "SELECT ia.*,
                    CONCAT(u.first_name,' ',u.last_name) AS case_manager_name,
                    (SELECT id           FROM activity_submissions WHERE activity_id = ia.id AND gambler_id = ? LIMIT 1) AS my_submission_id,
                    (SELECT submitted_at FROM activity_submissions WHERE activity_id = ia.id AND gambler_id = ? LIMIT 1) AS my_submitted_at,
                    (SELECT file_name    FROM activity_submissions WHERE activity_id = ia.id AND gambler_id = ? LIMIT 1) AS my_file_name
             FROM interventions_assessments ia
             JOIN users u ON u.id = ia.created_by
             WHERE ia.gambler_id = ?
             ORDER BY ia.created_at DESC"
        );
        $stmt->bind_param('iiii', $user_id, $user_id, $user_id, $user_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        echo json_encode(['success' => true, 'activities' => $rows]); exit();
    }

    if ($action === 'submit_activity') {
        $act_id = (int)($_POST['activity_id'] ?? 0);
        $notes  = trim($_POST['notes'] ?? '');

        $chk = $conn->prepare("SELECT * FROM interventions_assessments WHERE id = ? AND gambler_id = ? LIMIT 1");
        $chk->bind_param('ii', $act_id, $user_id); $chk->execute();
        $act = $chk->get_result()->fetch_assoc(); $chk->close();

        if (!$act) { echo json_encode(['success' => false, 'message' => 'Activity not found']); exit(); }

        // Allow submission if case manager has opened it (status = 'opened')
        if (($act['status'] ?? 'pending') !== 'opened') {
            echo json_encode(['success' => false, 'message' => 'This activity has not been opened by your case manager yet']); exit();
        }

        // Also check close date
        $today = date('Y-m-d');
        if ($today > $act['close_date']) { echo json_encode(['success' => false, 'message' => 'Submission deadline has passed']); exit(); }

        $dup = $conn->prepare("SELECT id FROM activity_submissions WHERE activity_id = ? AND gambler_id = ? LIMIT 1");
        $dup->bind_param('ii', $act_id, $user_id); $dup->execute();
        if ($dup->get_result()->fetch_assoc()) { $dup->close(); echo json_encode(['success' => false, 'message' => 'You have already submitted this activity']); exit(); }
        $dup->close();

        $file_path = null; $file_name = null;
        if (!empty($_FILES['file']['name'])) {
            $upload_dir = __DIR__ . '/../uploads/activity_submissions/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext     = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf','doc','docx','jpg','jpeg','png'];
            if (!in_array($ext, $allowed)) { echo json_encode(['success' => false, 'message' => 'File type not allowed']); exit(); }
            $safe_name = 'sub_' . $user_id . '_' . time() . '.' . $ext;
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $upload_dir . $safe_name)) {
                echo json_encode(['success' => false, 'message' => 'Upload failed']); exit();
            }
            $file_path = '/GAMBYTES_Final/uploads/activity_submissions/' . $safe_name;
            $file_name = $_FILES['file']['name'];
        }

        $ins = $conn->prepare("INSERT INTO activity_submissions (activity_id, gambler_id, file_path, file_name, notes) VALUES (?,?,?,?,?)");
        $ins->bind_param('iisss', $act_id, $user_id, $file_path, $file_name, $notes);
        $ins->execute(); $ins->close();

        // Notify case manager
        $cmLink  = '/GAMBYTES_Final/app/views/Users/Case manager/patient-activities.php?booking_id=' . $act['booking_id'];
        $cmTitle = 'Activity Submitted: ' . $act['title'];
        $cmMsg   = 'A gambler has submitted their activity response. Click to review.';
        $n = $conn->prepare("INSERT INTO notifications (user_id,type,title,message,link,is_read,created_at) VALUES (?,?,?,?,?,0,NOW())");
        $n->bind_param('issss', $act['created_by'], 'activity_submitted', $cmTitle, $cmMsg, $cmLink);
        $n->execute(); $n->close();

        echo json_encode(['success' => true]); exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']); exit();
}

echo json_encode(['success' => false, 'message' => 'Access denied']);
