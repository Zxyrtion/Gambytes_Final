<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../../includes/CalendlyService.php';

class SchedulingController extends Controller {
    private $calendlyService;
    
    public function __construct() {
        parent::__construct();
        $this->calendlyService = new CalendlyService();
    }
    
    /**
     * Display booking page
     */
    public function index() {
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit();
        }
        
        try {
            // Get available event types
            $eventTypes = $this->calendlyService->getEventTypes();
            
            // Filter for rehabilitation/consultation types
            $eventTypeList = $eventTypes['collection'] ?? [];
            
            $data = [
                'eventTypes' => array_values($eventTypeList),
                'user' => $_SESSION
            ];
            
            $this->view('scheduling/index', $data);
            
        } catch (Exception $e) {
            $this->view('scheduling/error', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Get available time slots via AJAX
     */
    public function getAvailableSlots() {
        header('Content-Type: application/json');
        
        try {
            $eventTypeUri = $_POST['event_type_uri'] ?? '';
            $startDate = $_POST['start_date'] ?? date('Y-m-d');
            $endDate = $_POST['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
            
            if (empty($eventTypeUri)) {
                throw new Exception('Event type URI is required');
            }
            
            $slots = $this->calendlyService->getAvailableSlots(
                $eventTypeUri,
                $this->calendlyService->formatDateForApi($startDate),
                $this->calendlyService->formatDateForApi($endDate)
            );
            
            echo json_encode([
                'success' => true,
                'data' => $slots
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Create a booking
     */
    public function book() {
        header('Content-Type: application/json');
        
        try {
            // Check if user is logged in
            if (!isset($_SESSION['user_id'])) {
                throw new Exception('User not authenticated');
            }
            
            $eventTypeUri = $_POST['event_type_uri'] ?? '';
            $startTime = $_POST['start_time'] ?? '';
            $name = $_POST['name'] ?? $_SESSION['user_name'] ?? '';
            $email = $_POST['email'] ?? $_SESSION['user_email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            
            // Validate required fields
            if (empty($eventTypeUri) || empty($startTime) || empty($name) || empty($email)) {
                throw new Exception('Missing required fields');
            }
            
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }
            
            $booking = $this->calendlyService->createBooking(
                $eventTypeUri,
                $startTime,
                $name,
                $email,
                $phone
            );
            
            // Save booking to local database
            $this->saveBookingToLocal($booking);
            
            echo json_encode([
                'success' => true,
                'data' => $booking,
                'message' => 'Booking created successfully'
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Cancel a booking
     */
    public function cancel() {
        header('Content-Type: application/json');
        
        try {
            // Check if user is logged in
            if (!isset($_SESSION['user_id'])) {
                throw new Exception('User not authenticated');
            }
            
            $eventUri = $_POST['event_uri'] ?? '';
            $reason = $_POST['reason'] ?? 'Cancelled by user';
            
            if (empty($eventUri)) {
                throw new Exception('Event URI is required');
            }
            
            $result = $this->calendlyService->cancelEvent($eventUri, $reason);
            
            // Update local database
            $this->updateBookingStatus($eventUri, 'cancelled');
            
            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => 'Booking cancelled successfully'
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * View booking details
     */
    public function view($eventUri) {
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit();
        }
        
        try {
            $details = $this->calendlyService->getEventDetails($eventUri);
            
            $data = [
                'event' => $details,
                'user' => $_SESSION
            ];
            
            $this->view('scheduling/view', $data);
            
        } catch (Exception $e) {
            $this->view('scheduling/error', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Save booking to local database
     */
    private function saveBookingToLocal($booking, $userId = null) {
        try {
            $db = new Database();
            $conn = $db->connect();

            if ($conn->connect_error) {
                throw new Exception('Database connect error: ' . $conn->connect_error);
            }

            $calendlyEventUri = $booking['uri'] ?? $booking['resource']['uri'] ?? '';
            $startTime = $this->normalizeDateTime($booking['start_time'] ?? $booking['event']['start_time'] ?? '');
            $endTime = $this->normalizeDateTime($booking['end_time'] ?? $booking['event']['end_time'] ?? '');
            $invitee = $booking['invitee'] ?? ($booking['resource']['invitees'][0] ?? []);
            $email = $invitee['email'] ?? $booking['email'] ?? '';
            $name = $invitee['name'] ?? $booking['name'] ?? '';
            $status = 'booked';

            $stmt = $conn->prepare(
                "INSERT IGNORE INTO bookings (calendly_event_uri, email, name, start_time, end_time, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );

            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }

            $stmt->bind_param('ssssss', $calendlyEventUri, $email, $name, $startTime, $endTime, $status);
            $stmt->execute();
            $stmt->close();
            $conn->close();

        } catch (Exception $e) {
            error_log("Failed to save booking to local database: " . $e->getMessage());
        }
    }

    /**
     * Normalize date/time strings for DB storage
     */
    private function normalizeDateTime($dateTime) {
        if (empty($dateTime)) {
            return null;
        }

        $timestamp = strtotime($dateTime);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
    
    /**
     * Update booking status in local database
     */
    private function updateBookingStatus($eventUri, $status) {
        try {
            // Database connection would go here
            // For now, we'll just log it
            error_log("Booking status updated to {$status} for event: {$eventUri}");
            
            // Example database update:
            /*
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
            $stmt = $pdo->prepare("
                UPDATE bookings SET status = ?, updated_at = NOW()
                WHERE calendly_event_uri = ?
            ");
            $stmt->execute([$status, $eventUri]);
            */
            
        } catch (Exception $e) {
            error_log("Failed to update booking status in local database: " . $e->getMessage());
        }
    }
}
?>