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

require_once __DIR__ . '/../includes/CalendlyService.php';
require_once __DIR__ . '/../app/models/ScheduleModel.php';

try {
    $calendlyService = new CalendlyService();
    $method = $_SERVER['REQUEST_METHOD'];
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    $input = is_array($jsonInput) ? $jsonInput : [];
    $input = array_merge($_POST, $input);
    $action = $_GET['action'] ?? $input['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'event-types':
                    // Get available event types
                    try {
                        $eventTypes = $calendlyService->getEventTypes();
                        
                        // Return all available event types so valid Calendly types are available
                        $eventTypeList = $eventTypes['collection'] ?? [];
                        
                        echo json_encode([
                            'success' => true,
                            'data' => array_values($eventTypeList)
                        ]);
                        
                    } catch (Exception $e) {
                        echo json_encode([
                            'success' => false,
                            'error' => $e->getMessage()
                        ]);
                    }
                    break;
                    
                case 'available-slots':
                    // Get available time slots
                    $eventTypeUri = $_GET['event_type_uri'] ?? '';
                    $startDate = $_GET['start_date'] ?? date('Y-m-d');
                    $endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
                    
                    if (empty($eventTypeUri)) {
                        throw new Exception('Event type URI is required');
                    }
                    
                    $slots = $calendlyService->getAvailableSlots(
                        $eventTypeUri,
                        $calendlyService->formatDateForApi($startDate),
                        $calendlyService->formatDateForApi($endDate)
                    );
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $slots
                    ]);
                    break;
                    
                case 'event-details':
                    // Get event details
                    $eventUri = $_GET['event_uri'] ?? '';
                    if (empty($eventUri)) {
                        throw new Exception('Event URI is required');
                    }
                    
                    $details = $calendlyService->getEventDetails($eventUri);
                    echo json_encode([
                        'success' => true,
                        'data' => $details
                    ]);
                    break;

                case 'test-db':
                    $scheduleModel = new ScheduleModel();
                    $bookings = $scheduleModel->getBookings();
                    echo json_encode([
                        'success' => true,
                        'count' => count($bookings),
                        'bookings' => $bookings
                    ]);
                    break;
                    
                case 'my-bookings':
                    // Get bookings from database
                    $email = $_GET['email'] ?? '';
                    if (empty($email)) {
                        throw new Exception('Email is required');
                    }
                    
                    $scheduleModel = new ScheduleModel();
                    $bookings = $scheduleModel->getBookingsByEmail($email);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $bookings,
                        'count' => count($bookings)
                    ]);
                    break;
                    
                case 'booking-details':
                    // Get specific booking details from database
                    $bookingId = $_GET['booking_id'] ?? '';
                    if (empty($bookingId)) {
                        throw new Exception('Booking ID is required');
                    }
                    
                    $scheduleModel = new ScheduleModel();
                    $booking = $scheduleModel->getBookingById($bookingId);
                    
                    if (!$booking) {
                        throw new Exception('Booking not found');
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $booking
                    ]);
                    break;
                    
                default:
                    // Get user info and default event types
                    $userInfo = $calendlyService->getUserInfo();
                    $rehabEventType = $calendlyService->getRehabilitationEventType();
                    
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'user_info' => $userInfo,
                            'rehab_event_type' => $rehabEventType
                        ]
                    ]);
            }
            break;
            
        case 'POST':
            switch ($action) {
                case 'book':
                    // Create a booking
                    $eventTypeUri = $input['event_type_uri'] ?? '';
                    $startTime = $input['start_time'] ?? '';
                    $endTime = $input['end_time'] ?? '';
                    $name = $input['name'] ?? '';
                    $email = $input['email'] ?? '';
                    $phone = $input['phone'] ?? '';
                    
                    // Validate required fields
                    if (empty($eventTypeUri) || empty($startTime) || empty($name) || empty($email)) {
                        throw new Exception('Missing required fields: event_type_uri, start_time, name, email');
                    }
                    
                    // Validate email
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Invalid email format');
                    }
                    
                    $scheduleModel = new ScheduleModel();
                    $dbBookingData = [
                        'calendly_event_uri' => $input['calendly_event_uri'] ?? '',
                        'email' => $email,
                        'name' => $name,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'status' => 'booked'
                    ];
                    
                    $dbResult = $scheduleModel->createBooking($dbBookingData);
                    
                    if (empty($dbResult['success']) || $dbResult['success'] !== true) {
                        throw new Exception('Database save failed: ' . ($dbResult['error'] ?? 'Unknown error'));
                    }
                    
                    $calendlyResponse = null;
                    $calendlyError = null;
                    $calendlyEvent = null;
                    
                    try {
                        $calendlyResponse = $calendlyService->createBooking(
                            $eventTypeUri,
                            $startTime,
                            $name,
                            $email,
                            $phone
                        );
                        
                        if (isset($calendlyResponse['error'])) {
                            throw new Exception($calendlyResponse['error']);
                        }
                        
                        $calendlyEvent = $calendlyResponse['resource'] ?? $calendlyResponse;
                        $eventUri = $calendlyEvent['booking_url'] ?? $calendlyEvent['uri'] ?? $calendlyResponse['booking_url'] ?? $calendlyResponse['uri'] ?? $calendlyEvent['resource']['uri'] ?? '';
                        $calendlyStartTime = $calendlyEvent['start_time'] ?? $startTime;
                        $calendlyEndTime = $calendlyEvent['end_time'] ?? $endTime;
                        
                        if (!empty($eventUri)) {
                            $scheduleModel->updateBookingEventUri(
                                $dbResult['booking_id'],
                                $eventUri,
                                $calendlyStartTime,
                                $calendlyEndTime
                            );
                        }
                    } catch (Exception $e) {
                        $calendlyError = $e->getMessage();
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'name' => $name,
                            'email' => $email,
                            'calendly_booking_url' => $eventUri,
                            'database_booking' => $dbResult,
                            'calendly_booking' => $calendlyEvent,
                            'calendly_error' => $calendlyError
                        ],
                        'message' => 'Booking saved to database' . ($calendlyError ? ' (Calendly: ' . $calendlyError . ')' : ' and Calendly booking attempted successfully'),
                        'booking_id' => $dbResult['booking_id'] ?? null
                    ]);
                    break;
                    
                case 'cancel':
                    // Cancel a booking
                    $eventUri = $input['event_uri'] ?? '';
                    $reason = $input['reason'] ?? 'Cancelled by user';
                    
                    if (empty($eventUri)) {
                        throw new Exception('Event URI is required');
                    }
                    
                    // Cancel booking in Calendly
                    $result = $calendlyService->cancelEvent($eventUri, $reason);
                    
                    // Update booking status in database
                    $scheduleModel = new ScheduleModel();
                    $booking = $scheduleModel->getBookingByEventUri($eventUri);
                    
                    if ($booking) {
                        $dbResult = $scheduleModel->cancelBooking($booking['id']);
                        
                        echo json_encode([
                            'success' => true,
                            'data' => [
                                'calendly_result' => $result,
                                'database_update' => $dbResult
                            ],
                            'message' => 'Booking cancelled successfully in both Calendly and database'
                        ]);
                    } else {
                        echo json_encode([
                            'success' => true,
                            'data' => $result,
                            'message' => 'Booking cancelled in Calendly. Database record not found.'
                        ]);
                    }
                    break;

                case 'available-slots':
                    // Get available time slots via POST request
                    $eventTypeUri = $input['event_type_uri'] ?? '';
                    $startDate = $input['start_date'] ?? date('Y-m-d');
                    $endDate = $input['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
                    
                    if (empty($eventTypeUri)) {
                        throw new Exception('Event type URI is required');
                    }
                    
                    $slots = $calendlyService->getAvailableSlots(
                        $eventTypeUri,
                        $calendlyService->formatDateForApi($startDate),
                        $calendlyService->formatDateForApi($endDate)
                    );
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $slots
                    ]);
                    break;
                    
                default:
                    throw new Exception('Invalid action');
            }
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
