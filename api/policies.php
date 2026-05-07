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

// Auto-create table
$conn->query("CREATE TABLE IF NOT EXISTS `policy_files` (
    `id`            INT(11)      NOT NULL AUTO_INCREMENT,
    `doc_title`     VARCHAR(255) NOT NULL,
    `doc_type`      VARCHAR(100) NOT NULL DEFAULT 'Other',
    `doc_category`  VARCHAR(100) NOT NULL DEFAULT 'General',
    `description`   TEXT         NULL,
    `filename`      VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `uploaded_by`   INT(11)      NOT NULL,
    `uploaded_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── GET: list all documents ───────────────────────────────────────────────────
if ($action === 'list') {
    $res = $conn->query(
        "SELECT pf.id, pf.doc_title, pf.doc_type, pf.doc_category, pf.description,
                pf.filename, pf.original_name, pf.uploaded_at,
                CONCAT(u.first_name,' ',u.last_name) AS uploader_name
         FROM policy_files pf
         LEFT JOIN users u ON u.id = pf.uploaded_by
         ORDER BY pf.uploaded_at DESC"
    );
    $docs = [];
    while ($row = $res->fetch_assoc()) {
        $row['url'] = '/GAMBYTES_Final/uploads/policies/' . rawurlencode($row['filename']);
        $docs[] = $row;
    }
    echo json_encode(['success' => true, 'documents' => $docs]);
    exit();
}

// ── POST: upload ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload') {
    // Role check
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $urow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$urow || !in_array($urow['role'], ['supervisor', 'admin'])) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }

    $doc_title    = trim($_POST['doc_title']    ?? '');
    $doc_type     = trim($_POST['doc_type']     ?? '');
    $doc_category = trim($_POST['doc_category'] ?? '');
    $description  = trim($_POST['doc_description'] ?? '');

    if (!$doc_title || !$doc_type || !$doc_category) {
        echo json_encode(['success' => false, 'message' => 'Title, type, and category are required.']);
        exit();
    }

    if (empty($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
        exit();
    }

    $file = $_FILES['doc_file'];
    $mime = mime_content_type($file['tmp_name']);
    $allowed_mimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    if (!in_array($mime, $allowed_mimes)) {
        echo json_encode(['success' => false, 'message' => 'File type not allowed. Use PDF, DOC, DOCX, TXT, PPT, PPTX, XLS, or XLSX.']);
        exit();
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 10 MB.']);
        exit();
    }

    $upload_dir = __DIR__ . '/../uploads/policies/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safe_ext = in_array($ext, ['pdf','doc','docx','txt','ppt','pptx','xls','xlsx']) ? $ext : 'bin';
    $new_name = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safe_ext;

    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
        exit();
    }

    $ins = $conn->prepare(
        "INSERT INTO policy_files (doc_title, doc_type, doc_category, description, filename, original_name, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $ins->bind_param('ssssssi', $doc_title, $doc_type, $doc_category, $description, $new_name, $file['name'], $_SESSION['user_id']);
    $ins->execute();
    $ins->close();

    echo json_encode([
        'success'  => true,
        'message'  => 'Document uploaded successfully.',
        'filename' => $new_name,
        'url'      => '/GAMBYTES_Final/uploads/policies/' . rawurlencode($new_name),
    ]);
    exit();
}

// ── POST: delete ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $urow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$urow || !in_array($urow['role'], ['supervisor', 'admin'])) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }

    $doc_id = (int)($_POST['doc_id'] ?? 0);
    if (!$doc_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid document ID']);
        exit();
    }

    $sel = $conn->prepare("SELECT filename FROM policy_files WHERE id = ?");
    $sel->bind_param('i', $doc_id);
    $sel->execute();
    $frow = $sel->get_result()->fetch_assoc();
    $sel->close();

    if ($frow) {
        $path = __DIR__ . '/../uploads/policies/' . $frow['filename'];
        if (file_exists($path)) unlink($path);
        $del = $conn->prepare("DELETE FROM policy_files WHERE id = ?");
        $del->bind_param('i', $doc_id);
        $del->execute();
        $del->close();
        echo json_encode(['success' => true, 'message' => 'Document deleted.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Document not found.']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
