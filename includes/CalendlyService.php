<?php
require_once __DIR__ . '/config.php';

class CalendlyService {
    private $apiKey;
    private $apiUrl;
    
    public function __construct() {
        $this->apiKey = CALENDLY_API_KEY;
        $this->apiUrl = CALENDLY_API_URL;
    }
    
    /**
     * Get user information from Calendly
     */
    public function getUserInfo() {
        $response = $this->makeRequest('GET', '/users/me');
        return $response;
    }
    
    /**
     * Get available event types
     */
    public function getEventTypes($userUri = null) {
        $endpoint = '/event_types';
        $params = [];
        
        if (!$userUri) {
            if (defined('CALENDLY_USER_URI') && CALENDLY_USER_URI) {
                $userUri = CALENDLY_USER_URI;
            } elseif (defined('CALENDLY_ORGANIZATION_URI') && CALENDLY_ORGANIZATION_URI) {
                $params['organization'] = CALENDLY_ORGANIZATION_URI;
            } else {
                $userInfo = $this->getUserInfo();
                $userUri = $userInfo['resource']['uri'] ?? null;
            }
        }
        
        if ($userUri) {
            $params['user'] = $userUri;
        }
        
        // Add pagination parameters using valid Calendly query names
        $params['page_size'] = 20; // Get up to 20 event types
        
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }
        
        $response = $this->makeRequest('GET', $endpoint);
        return $response;
    }
    
    /**
     * Get available time slots for an event type
     */
    public function getAvailableSlots($eventTypeUri, $startDate, $endDate) {
        $endpoint = '/schedule_availability';
        $data = [
            'event_type' => $eventTypeUri,
            'start_time' => $startDate,
            'end_time' => $endDate
        ];
        
        $response = $this->makeRequest('POST', $endpoint, $data);
        return $response;
    }
    
    /**
     * Create a scheduling link for a booking request
     */
    public function createBooking($eventTypeUri, $startTime, $name, $email, $phone = null) {
        $endpoint = '/scheduling_links';
        $data = [
            'event_type' => $eventTypeUri,
            'name' => $name,
            'email' => $email,
            'owner' => $eventTypeUri,
            'owner_type' => 'EventType',
            'max_event_count' => 1
        ];
        
        if (!empty($phone)) {
            $data['phone_number'] = $phone;
        }
        
        $response = $this->makeRequest('POST', $endpoint, $data);
        return $response;
    }
    
    /**
     * Get scheduled event details
     */
    public function getEventDetails($eventUri) {
        $endpoint = str_replace($this->apiUrl, '', $eventUri);
        $response = $this->makeRequest('GET', $endpoint);
        return $response;
    }
    
    /**
     * Cancel a scheduled event
     */
    public function cancelEvent($eventUri, $reason = null) {
        $endpoint = str_replace($this->apiUrl, '', $eventUri) . '/cancellation';
        $data = [
            'reason' => $reason ?: 'Cancelled by user'
        ];
        
        $response = $this->makeRequest('POST', $endpoint, $data);
        return $response;
    }
    
    /**
     * Make HTTP request to Calendly API (public wrapper)
     */
    public function makeRequestPublic($method, $endpoint, $data = null) {
        return $this->makeRequest($method, $endpoint, $data);
    }

    /**
     * Make HTTP request to Calendly API
     */
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->apiUrl . $endpoint;
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $data ? json_encode($data) : null,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: " . $error);
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $message = $decodedResponse['message'] ?? 'API Error';
            throw new Exception("Calendly API Error ({$httpCode}): {$message}");
        }
        
        return $decodedResponse;
    }
    
    /**
     * Format date for Calendly API
     */
    public function formatDateForApi($date) {
        return date('c', strtotime($date));
    }
    
    /**
     * Extract event type URI from event type data
     */
    public function extractEventTypeUri($eventTypeData) {
        return $eventTypeData['uri'] ?? null;
    }
    
    /**
     * Get rehabilitation consultation event type
     */
    public function getRehabilitationEventType() {
        $eventTypes = $this->getEventTypes();
        
        // Look for rehabilitation or consultation related event types
        foreach ($eventTypes['collection'] ?? [] as $eventType) {
            if (stripos($eventType['name'], 'consultation') !== false || 
                stripos($eventType['name'], 'rehab') !== false ||
                stripos($eventType['name'], 'therapy') !== false) {
                return $eventType;
            }
        }
        
        // Return first available event type if no specific match found
        return $eventTypes['collection'][0] ?? null;
    }
    
    /**
     * Get scheduled events (bookings) for the authenticated user
     */
    public function getUserBookings($params = []) {
        try {
            // Get user info first
            $userInfo = $this->getUserInfo();
            $userUri = $userInfo['resource']['uri'] ?? null;
            
            if (!$userUri) {
                throw new Exception('Could not retrieve user URI');
            }
            
            // Set default parameters
            if (empty($params)) {
                $params = [
                    'user' => $userUri,
                    'page_size' => 10,
                    'sort' => 'start_time:desc'
                ];
            } else {
                // Ensure user parameter is set
                $params['user'] = $userUri;
            }
            
            // Add pagination if not present
            if (!isset($params['page_size'])) {
                $params['page_size'] = 10;
            }
            
            $endpoint = '/scheduled_events?' . http_build_query($params);
            $response = $this->makeRequest('GET', $endpoint);
            return $response;
        } catch (Exception $e) {
            error_log("Error fetching user bookings: " . $e->getMessage());
            return ['collection' => [], 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get scheduled events filtered by email
     */
    public function getScheduledEventsByEmail($email, $params = []) {
        try {
            // First, try fetching all scheduled events for the authenticated user
            $userInfo = $this->getUserInfo();
            $userUri = $userInfo['resource']['uri'] ?? null;
            
            if (!$userUri) {
                throw new Exception('Could not retrieve user URI');
            }
            
            // Build parameters for scheduled events
            $queryParams = [
                'user' => $userUri,
                'page_size' => $params['page_size'] ?? 10,
                'sort' => $params['sort'] ?? 'start_time:desc'
            ];
            
            $endpoint = '/scheduled_events?' . http_build_query($queryParams);
            $response = $this->makeRequest('GET', $endpoint);
            
            // Filter results by email if provided
            if (!empty($response['collection']) && !empty($email)) {
                $filtered = array_filter($response['collection'], function($event) use ($email) {
                    $eventEmail = $event['invitee']['email'] ?? '';
                    return strtolower($eventEmail) === strtolower($email);
                });
                $response['collection'] = array_values($filtered);
            }
            
            return $response;
        } catch (Exception $e) {
            error_log("Error fetching scheduled events by email: " . $e->getMessage());
            return ['collection' => [], 'error' => $e->getMessage()];
        }
    }
}
?>
