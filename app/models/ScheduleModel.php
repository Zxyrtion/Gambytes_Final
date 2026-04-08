<?php
require_once __DIR__ . '/../core/Database.php';

class ScheduleModel {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->connect();
        
        if (!$this->conn) {
            throw new Exception('Database connection failed: ' . mysqli_connect_error());
        }
    }
    
    /**
     * Create a new booking in the database
     * @param array $bookingData - Booking information including calendly_event_uri, email, name, start_time, end_time
     * @return array - Array with 'success' and 'booking_id' or 'error'
     */
    public function createBooking($bookingData) {
        try {
            // Prepare the insert statement
            $query = "INSERT INTO bookings (calendly_event_uri, email, name, start_time, end_time, status) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $this->conn->error);
            }
            
            // Extract data with defaults
            $calendly_event_uri = $bookingData['calendly_event_uri'] ?? '';
            if (empty($calendly_event_uri)) {
                $calendly_event_uri = 'pending_' . time() . '_' . bin2hex(random_bytes(4));
            }
            $email = $bookingData['email'] ?? '';
            $name = $bookingData['name'] ?? '';
            $start_time = $bookingData['start_time'] ?? date('Y-m-d H:i:s');
            $end_time = $bookingData['end_time'] ?? date('Y-m-d H:i:s', strtotime('+1 hour', strtotime($start_time)));
            $status = $bookingData['status'] ?? 'booked';
            
            // Bind parameters
            $stmt->bind_param(
                'ssssss',
                $calendly_event_uri,
                $email,
                $name,
                $start_time,
                $end_time,
                $status
            );
            
            // Execute the statement
            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            
            $booking_id = $this->conn->insert_id;
            $stmt->close();
            
            return [
                'success' => true,
                'booking_id' => $booking_id,
                'message' => 'Booking saved to database successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get booking by Calendly event URI
     * @param string $calendly_event_uri - The Calendly event URI
     * @return array - Booking data or null if not found
     */
    public function getBookingByEventUri($calendly_event_uri) {
        $query = "SELECT * FROM bookings WHERE calendly_event_uri = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('s', $calendly_event_uri);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $booking = $result->fetch_assoc();
        $stmt->close();
        
        return $booking;
    }
    
    /**
     * Get booking by ID
     * @param int $id - The booking ID
     * @return array - Booking data or null if not found
     */
    public function getBookingById($id) {
        $query = "SELECT * FROM bookings WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $booking = $result->fetch_assoc();
        $stmt->close();
        
        return $booking;
    }
    
    /**
     * Get all bookings for a user email
     * @param string $email - User email
     * @return array - Array of bookings
     */
    public function getBookingsByEmail($email) {
        $query = "SELECT * FROM bookings WHERE email = ? ORDER BY start_time DESC";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $bookings = [];
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }
        
        $stmt->close();
        return $bookings;
    }
    
    /**
     * Update booking status
     * @param int $id - Booking ID
     * @param string $status - New status
     * @return array - Success/error response
     */
    public function updateBookingStatus($id, $status) {
        try {
            $query = "UPDATE bookings SET status = ? WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $this->conn->error);
            }
            
            $stmt->bind_param('si', $status, $id);
            
            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'message' => 'Booking status updated successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cancel a booking
     * @param int $id - Booking ID
     * @return array - Success/error response
     */
    public function cancelBooking($id) {
        return $this->updateBookingStatus($id, 'cancelled');
    }
    
    /**
     * Update the booking URI and times after Calendly response
     */
    public function updateBookingEventUri($id, $calendly_event_uri, $start_time = null, $end_time = null) {
        $query = "UPDATE bookings SET calendly_event_uri = ?, start_time = ?, end_time = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('sssi', $calendly_event_uri, $start_time, $end_time, $id);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $stmt->close();
        return [
            'success' => true,
            'message' => 'Booking updated successfully'
        ];
    }
    
    /**
     * Get all bookings with optional filters
     * @param array $filters - Filters like ['status' => 'booked', 'email' => 'user@example.com']
     * @return array - Array of bookings
     */
    public function getBookings($filters = []) {
        $query = "SELECT * FROM bookings WHERE 1=1";
        
        if (!empty($filters['status'])) {
            $query .= " AND status = '" . $this->conn->real_escape_string($filters['status']) . "'";
        }
        
        if (!empty($filters['email'])) {
            $query .= " AND email = '" . $this->conn->real_escape_string($filters['email']) . "'";
        }
        
        if (!empty($filters['date_from'])) {
            $query .= " AND start_time >= '" . $this->conn->real_escape_string($filters['date_from']) . "'";
        }
        
        if (!empty($filters['date_to'])) {
            $query .= " AND start_time <= '" . $this->conn->real_escape_string($filters['date_to']) . "'";
        }
        
        $query .= " ORDER BY start_time DESC";
        
        $result = $this->conn->query($query);
        
        if (!$result) {
            throw new Exception('Query failed: ' . $this->conn->error);
        }
        
        $bookings = [];
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }
        
        return $bookings;
    }
    
    /**
     * Get all bookings with filters and pagination
     * @param array $filters - Filters like ['status' => 'booked', 'search' => 'john']
     * @param int $limit - Number of records per page
     * @param int $offset - Starting position
     * @return array - Array of bookings
     */
    public function getAllBookings($filters = [], $limit = 50, $offset = 0) {
        $query = "SELECT * FROM bookings WHERE 1=1";
        
        // Apply status filter
        if (!empty($filters['status'])) {
            $query .= " AND status = '" . $this->conn->real_escape_string($filters['status']) . "'";
        }
        
        // Apply search filter (search in name and email)
        if (!empty($filters['search'])) {
            $search = $this->conn->real_escape_string($filters['search']);
            $query .= " AND (name LIKE '%$search%' OR email LIKE '%$search%')";
        }
        
        // Apply date filters
        if (!empty($filters['date_from'])) {
            $query .= " AND start_time >= '" . $this->conn->real_escape_string($filters['date_from']) . " 00:00:00'";
        }
        
        if (!empty($filters['date_to'])) {
            $query .= " AND start_time <= '" . $this->conn->real_escape_string($filters['date_to']) . " 23:59:59'";
        }
        
        // Order and limit
        $query .= " ORDER BY start_time DESC LIMIT $limit OFFSET $offset";
        
        $result = $this->conn->query($query);
        
        if (!$result) {
            throw new Exception('Query failed: ' . $this->conn->error);
        }
        
        $bookings = [];
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }
        
        return $bookings;
    }
    
    /**
     * Get total count of bookings with filters
     * @param array $filters - Filters to apply
     * @return int - Total count
     */
    public function getBookingsCount($filters = []) {
        $query = "SELECT COUNT(*) as count FROM bookings WHERE 1=1";
        
        if (!empty($filters['status'])) {
            $query .= " AND status = '" . $this->conn->real_escape_string($filters['status']) . "'";
        }
        
        if (!empty($filters['search'])) {
            $search = $this->conn->real_escape_string($filters['search']);
            $query .= " AND (name LIKE '%$search%' OR email LIKE '%$search%')";
        }
        
        if (!empty($filters['date_from'])) {
            $query .= " AND start_time >= '" . $this->conn->real_escape_string($filters['date_from']) . " 00:00:00'";
        }
        
        if (!empty($filters['date_to'])) {
            $query .= " AND start_time <= '" . $this->conn->real_escape_string($filters['date_to']) . " 23:59:59'";
        }
        
        $result = $this->conn->query($query);
        
        if (!$result) {
            throw new Exception('Query failed: ' . $this->conn->error);
        }
        
        $row = $result->fetch_assoc();
        return (int)$row['count'];
    }
    
    /**
     * Add notes to a booking
     * @param int $id - Booking ID
     * @param string $notes - Notes to add
     * @return array - Success/error response
     */
    public function addBookingNotes($id, $notes) {
        try {
            $query = "UPDATE bookings SET notes = CONCAT(IFNULL(notes, ''), '\n', ?, ' - ', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s')) WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $this->conn->error);
            }
            
            $stmt->bind_param('si', $notes, $id);
            
            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'message' => 'Notes added successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get database connection
     * @return mysqli - Database connection
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Destructor - close database connection
     */
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>
