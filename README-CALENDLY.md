# Calendly Integration for Gambytes Rehabilitation Booking

This document explains the Calendly API integration implemented for the Gambytes gambling recovery system.

## Overview

The Calendly integration allows patients/gamblers to book rehabilitation consultation appointments through the Gambytes system. The integration follows the data flow diagram requirements where:

1. **Patient** provides personal details and selects time/date
2. **Booking Process** creates a schedule record
3. **Clinical Operation Supervisor** can verify the booked schedule

## Files Created/Modified

### 1. Configuration Files
- `includes/config.php` - Contains Calendly API key and configuration
- `includes/CalendlyService.php` - Core service class for Calendly API operations

### 2. API Endpoint
- `api/booking.php` - RESTful API endpoints for booking operations

### 3. Controller
- `app/controllers/SchedulingController.php` - MVC controller handling booking logic

### 4. Views
- `app/views/scheduling/index.php` - Main booking interface
- `app/views/scheduling/error.php` - Error handling page

## API Endpoints

### GET `/api/booking.php`
- Get user info and available event types
- Returns rehabilitation/consultation event types filtered from Calendly

### GET `/api/booking.php?action=event-types`
- Get all available event types
- Filters for rehabilitation-related services

### GET `/api/booking.php?action=available-slots`
- Get available time slots for a specific event type
- Parameters: `event_type_uri`, `start_date`, `end_date`

### POST `/api/booking.php?action=book`
- Create a new booking
- Required fields: `event_type_uri`, `start_time`, `name`, `email`
- Optional: `phone`

### POST `/api/booking.php?action=cancel`
- Cancel an existing booking
- Required fields: `event_uri`, optional: `reason`

## Features Implemented

### 1. Multi-Step Booking Process
- **Step 1**: Select rehabilitation service type
- **Step 2**: Choose available time slot
- **Step 3**: Enter personal details
- **Step 4**: Confirmation

### 2. Real-time Availability
- Fetches real-time availability from Calendly
- Displays available time slots in user-friendly format
- Supports date range filtering

### 3. User Experience
- Responsive design for mobile and desktop
- Progress indicators
- Loading states and error handling
- Form validation

### 4. Integration Features
- Automatic filtering of rehabilitation/consultation services
- Singapore timezone support (as shown in your Calendly image)
- Email confirmation integration
- Local database logging for booking records

## Usage Instructions

### For Patients/Gamblers:
1. Navigate to `/scheduling` in the Gambytes system
2. Select the desired rehabilitation service (e.g., "JJVBMC Free Consultation")
3. Choose an available date and time slot
4. Fill in personal details (name, email, phone)
5. Confirm booking

### For Clinical Operation Supervisor:
1. Access the booking management system
2. View all scheduled appointments
3. Verify and manage booking statuses
4. Access patient details for scheduled consultations

## Configuration

The Calendly API key is configured in `includes/config.php`. The system uses the provided token with the following scopes:
- `availability:read/write`
- `event_types:read/write`
- `scheduled_events:read/write`
- `scheduling_links:write`

## Security Features

- Input validation and sanitization
- Email format validation
- Session-based authentication
- CORS headers for API access
- Error handling without exposing sensitive information

## Database Integration

The system includes prepared database integration for storing booking records locally:
- `bookings` table structure is prepared
- Automatic logging of booking creation and status updates
- User association for tracking patient appointments

## Error Handling

- Comprehensive error handling throughout the booking flow
- User-friendly error messages
- Fallback options for API failures
- Logging for debugging purposes

## Future Enhancements

Potential improvements that could be added:
- SMS reminders integration
- Calendar export functionality
- Multi-language support
- Advanced filtering options
- Integration with telemedicine platforms
- Automated follow-up scheduling

## Testing

To test the integration:
1. Access the booking page at `/scheduling`
2. Select an event type and time slot
3. Fill in test details and submit
4. Verify booking creation in Calendly dashboard
5. Test cancellation functionality

## Support

For issues with the Calendly integration:
1. Check the error logs in the system
2. Verify API key validity in Calendly dashboard
3. Ensure proper timezone configuration
4. Test API connectivity using the provided endpoints
