<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../app/core/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'count' => 0]);
    exit();
}

$db      = new Database();
$conn    = $db->connect();
$user_id = (int)$_SESSION['user_id'];
$action  = $_GET['action'] ?? 'count';

if ($action === 'count') {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo json_encode(['success' => true, 'count' => (int)$row['cnt']]);

} elseif ($action === 'list') {
    $stmt = $conn->prepare(
        "SELECT id, type, title, message, link, is_read, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 15"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $items  = [];
    while ($r = $result->fetch_assoc()) {
        // Ensure spaces in paths are properly encoded as %20 for valid URLs
        if (!empty($r['link'])) {
            $r['link'] = str_replace(' ', '%20', $r['link']);
        }
        $items[] = $r;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'items' => $items]);

} elseif ($action === 'mark_seen') {
    $stmt = $conn->prepare(
        "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);

} elseif ($action === 'delete') {
    // Delete a specific notification
    $notif_id = (int)($_GET['id'] ?? 0);
    if (!$notif_id) {
        echo json_encode(['success' => false, 'message' => 'Notification ID required']);
        exit();
    }
    
    // Verify the notification belongs to the user
    $verify = $conn->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ?");
    $verify->bind_param('ii', $notif_id, $user_id);
    $verify->execute();
    $exists = $verify->get_result()->fetch_assoc();
    $verify->close();
    
    if (!$exists) {
        echo json_encode(['success' => false, 'message' => 'Notification not found']);
        exit();
    }
    
    // Delete the notification
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $notif_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Notification deleted']);

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
