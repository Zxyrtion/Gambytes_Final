<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../app/models/ScheduleModel.php';

try {
    $scheduleModel = new ScheduleModel();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'all-bookings':
                    // Get all bookings with optional filters
                    $filters = [];
                    
                    if (!empty($_GET['status'])) {
                        $filters['status'] = $_GET['status'];
                    }
                    
                    if (!empty($_GET['search'])) {
                        $filters['search'] = $_GET['search'];
                    }
                    
                    if (!empty($_GET['date_from'])) {
                        $filters['date_from'] = $_GET['date_from'];
                    }
                    
                    if (!empty($_GET['date_to'])) {
                        $filters['date_to'] = $_GET['date_to'];
                    }
                    
                    $limit = (int)($_GET['limit'] ?? 50);
                    $offset = (int)($_GET['offset'] ?? 0);
                    
                    $bookings = $scheduleModel->getAllBookings($filters, $limit, $offset);
                    $total = $scheduleModel->getBookingsCount($filters);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $bookings,
                        'pagination' => [
                            'total' => $total,
                            'limit' => $limit,
                            'offset' => $offset,
                            'pages' => ceil($total / $limit)
                        ]
                    ]);
                    break;
                    
                case 'booking-detail':
                    // Get specific booking details
                    $bookingId = $_GET['id'] ?? '';
                    
                    if (empty($bookingId)) {
                        throw new Exception('Booking ID is required');
                    }
                    
                    $booking = $scheduleModel->getBookingById($bookingId);
                    
                    if (!$booking) {
                        throw new Exception('Booking not found');
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $booking
                    ]);
                    break;
                    
                case 'stats':
                    // Get booking statistics for dashboard
                    $db = $scheduleModel->getConnection();
                    
                    $totalQuery = "SELECT COUNT(*) as total FROM bookings";
                    $bookedQuery = "SELECT COUNT(*) as count FROM bookings WHERE status='booked'";
                    $cancelledQuery = "SELECT COUNT(*) as count FROM bookings WHERE status='cancelled'";
                    $completedQuery = "SELECT COUNT(*) as count FROM bookings WHERE status='completed'";
                    
                    $totalResult = $db->query($totalQuery)->fetch_assoc();
                    $bookedResult = $db->query($bookedQuery)->fetch_assoc();
                    $cancelledResult = $db->query($cancelledQuery)->fetch_assoc();
                    $completedResult = $db->query($completedQuery)->fetch_assoc();
                    
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'total' => (int)$totalResult['total'],
                            'booked' => (int)$bookedResult['count'],
                            'cancelled' => (int)$cancelledResult['count'],
                            'completed' => (int)$completedResult['count']
                        ]
                    ]);
                    break;
                    
                default:
                    throw new Exception('Invalid action');
            }
            break;
            
        case 'PUT':
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];
            
            switch ($action) {
                case 'update-status':
                    // Update booking status
                    $bookingId = $input['booking_id'] ?? $_GET['id'] ?? '';
                    $newStatus = $input['status'] ?? '';
                    $notes = $input['notes'] ?? '';
                    
                    if (empty($bookingId) || empty($newStatus)) {
                        throw new Exception('Booking ID and status are required');
                    }
                    
                    // Validate status
                    $validStatuses = ['booked', 'cancelled', 'no_show', 'completed'];
                    if (!in_array($newStatus, $validStatuses)) {
                        throw new Exception('Invalid status value');
                    }
                    
                    $result = $scheduleModel->updateBookingStatus($bookingId, $newStatus);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result,
                        'message' => 'Booking status updated successfully'
                    ]);
                    break;
                    
                case 'add-notes':
                    // Add notes to booking
                    $bookingId = $input['booking_id'] ?? $_GET['id'] ?? '';
                    $notes = $input['notes'] ?? '';
                    
                    if (empty($bookingId)) {
                        throw new Exception('Booking ID is required');
                    }
                    
                    if (empty($notes)) {
                        throw new Exception('Notes cannot be empty');
                    }
                    
                    $result = $scheduleModel->addBookingNotes($bookingId, $notes);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result,
                        'message' => 'Notes added successfully'
                    ]);
                    break;
                    
                default:
                    throw new Exception('Invalid action');
            }
            break;
            
        case 'DELETE':
            $bookingId = $_GET['id'] ?? '';
            
            if (empty($bookingId)) {
                throw new Exception('Booking ID is required');
            }
            
            // Only allow soft delete (cancel the booking)
            $result = $scheduleModel->updateBookingStatus($bookingId, 'cancelled');
            
            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => 'Booking cancelled successfully'
            ]);
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
