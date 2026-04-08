<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Rehabilitation Schedule - Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container booking-container">
        <div class="rehab-header text-center">
            <h1><i class="fas fa-calendar-check"></i> Rehabilitation Schedule Booking</h1>
            <p class="mb-0">Book your consultation appointment for gambling recovery support</p>
        </div>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step active" id="step1">
                <i class="fas fa-list"></i> Select Service
            </div>
            <div class="step" id="step2">
                <i class="fas fa-clock"></i> Choose Time
            </div>
            <div class="step" id="step3">
                <i class="fas fa-user"></i> Personal Details
            </div>
            <div class="step" id="step4">
                <i class="fas fa-check"></i> Confirmation
            </div>
        </div>

        <!-- Step 1: Select Event Type -->
        <div class="form-section active" id="section1">
            <h3><i class="fas fa-hand-holding-medical"></i> Select Rehabilitation Service</h3>
            <div id="eventTypes">
                <?php if (empty($eventTypes)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> No rehabilitation services available at the moment. Please try again later.
                    </div>
                <?php else: ?>
                    <?php foreach ($eventTypes as $eventType): ?>
                        <div class="event-type-card" data-event-type-uri="<?php echo htmlspecialchars($eventType['uri']); ?>">
                            <h5><?php echo htmlspecialchars($eventType['name']); ?></h5>
                            <p class="mb-1"><i class="fas fa-clock"></i> Duration: <?php echo htmlspecialchars($eventType['duration_minutes'] ?? 'N/A'); ?> minutes</p>
                            <p class="mb-0"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($eventType['description'] ?? 'Click to select this service'); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button class="btn btn-primary mt-3" id="nextToTime" disabled>Next: Choose Time <i class="fas fa-arrow-right"></i></button>
        </div>

        <!-- Step 2: Select Time Slot -->
        <div class="form-section" id="section2">
            <h3><i class="fas fa-calendar-alt"></i> Available Time Slots</h3>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="startDate" class="form-label">From Date:</label>
                    <input type="date" class="form-control" id="startDate" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-6">
                    <label for="endDate" class="form-label">To Date:</label>
                    <input type="date" class="form-control" id="endDate" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                </div>
            </div>
            <button class="btn btn-info mb-3" id="loadSlots"><i class="fas fa-sync"></i> Load Available Slots</button>
            
            <div class="loading" id="loadingSlots">
                <i class="fas fa-spinner fa-spin"></i> Loading available time slots...
            </div>
            
            <div id="timeSlots">
                <p class="text-muted">Please select a date range and click "Load Available Slots" to see available times.</p>
            </div>
            
            <div class="mt-3">
                <button class="btn btn-secondary" id="backToService"><i class="fas fa-arrow-left"></i> Back</button>
                <button class="btn btn-primary" id="nextToDetails" disabled>Next: Personal Details <i class="fas fa-arrow-right"></i></button>
            </div>
        </div>

        <!-- Step 3: Personal Details -->
        <div class="form-section" id="section3">
            <h3><i class="fas fa-user-edit"></i> Personal Details</h3>
            <form id="bookingForm">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="+1234567890">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="reason" class="form-label">Reason for Visit</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="Brief description of why you're seeking rehabilitation support..."></textarea>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong>Important:</strong> Your information is confidential and will only be used for scheduling your rehabilitation consultation.
                </div>
                
                <div class="mt-3">
                    <button class="btn btn-secondary" id="backToTime"><i class="fas fa-arrow-left"></i> Back</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Confirm Booking</button>
                </div>
            </form>
        </div>

        <!-- Step 4: Confirmation -->
        <div class="form-section" id="section4">
            <div class="text-center">
                <div class="mb-4">
                    <i class="fas fa-check-circle" style="font-size: 4rem; color: #28a745;"></i>
                </div>
                <h3>Booking Confirmed!</h3>
                <p class="text-muted">Your rehabilitation consultation has been successfully scheduled.</p>
                
                <div id="confirmationDetails" class="text-start mt-4">
                    <!-- Booking details will be populated here -->
                </div>
                
                <div class="mt-4">
                    <button class="btn btn-primary" id="viewBooking"><i class="fas fa-eye"></i> View Booking Details</button>
                    <button class="btn btn-secondary" id="newBooking"><i class="fas fa-plus"></i> Book Another Appointment</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedEventType = null;
        let selectedTimeSlot = null;
        let currentStep = 1;

        // Event type selection
        document.querySelectorAll('.event-type-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.event-type-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                selectedEventType = {
                    uri: this.dataset.eventTypeUri,
                    name: this.querySelector('h5').textContent
                };
                document.getElementById('nextToTime').disabled = false;
            });
        });

        // Navigation buttons
        document.getElementById('nextToTime').addEventListener('click', () => goToStep(2));
        document.getElementById('backToService').addEventListener('click', () => goToStep(1));
        document.getElementById('nextToDetails').addEventListener('click', () => goToStep(3));
        document.getElementById('backToTime').addEventListener('click', () => goToStep(2));

        function goToStep(step) {
            document.querySelectorAll('.form-section').forEach(section => section.classList.remove('active'));
            document.querySelectorAll('.step').forEach((stepEl, index) => {
                stepEl.classList.remove('active', 'completed');
                if (index < step - 1) {
                    stepEl.classList.add('completed');
                } else if (index === step - 1) {
                    stepEl.classList.add('active');
                }
            });
            document.getElementById(`section${step}`).classList.add('active');
            currentStep = step;
        }

        const apiBookingUrl = (() => {
            const pathSegments = window.location.pathname.split('/');
            const appBase = pathSegments[1] === 'GAMBYTES_Final' ? '/GAMBYTES_Final' : '';
            return window.location.origin + appBase + '/api/booking.php';
        })();

        // Load available slots
        document.getElementById('loadSlots').addEventListener('click', loadTimeSlots);

        function loadTimeSlots() {
            if (!selectedEventType) return;

            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;

            document.getElementById('loadingSlots').style.display = 'block';
            document.getElementById('timeSlots').innerHTML = '';

            fetch(apiBookingUrl + '?action=available-slots', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    event_type_uri: selectedEventType.uri,
                    start_date: startDate,
                    end_date: endDate
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingSlots').style.display = 'none';
                
                if (data.success && data.data && data.data.collection && data.data.collection.length > 0) {
                    displayTimeSlots(data.data.collection);
                } else {
                    document.getElementById('timeSlots').innerHTML = 
                        '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> No available time slots found for the selected dates. Please try a different date range.</div>';
                }
            })
            .catch(error => {
                document.getElementById('loadingSlots').style.display = 'none';
                document.getElementById('timeSlots').innerHTML = 
                    '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Error loading time slots. Please try again.</div>';
            });
        }

        function displayTimeSlots(slots) {
            const container = document.getElementById('timeSlots');
            let html = '';

            slots.forEach(slot => {
                const startTime = new Date(slot.start_time);
                const endTime = new Date(slot.end_time);
                
                html += `
                    <div class="time-slot" data-start-time="${slot.start_time}" data-end-time="${slot.end_time}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${startTime.toLocaleDateString()}</strong><br>
                                <i class="fas fa-clock"></i> ${startTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} - ${endTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                            </div>
                            <div>
                                <span class="badge bg-success">Available</span>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;

            // Add click handlers to time slots
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.addEventListener('click', function() {
                    document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedTimeSlot = {
                        start_time: this.dataset.startTime,
                        end_time: this.dataset.endTime
                    };
                    document.getElementById('nextToDetails').disabled = false;
                });
            });
        }

        // Booking form submission
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!selectedEventType || !selectedTimeSlot) {
                alert('Please select both a service and a time slot.');
                return;
            }

            const formData = new FormData(this);
            const bookingData = {
                event_type_uri: selectedEventType.uri,
                start_time: selectedTimeSlot.start_time,
                end_time: selectedTimeSlot.end_time,
                name: formData.get('name'),
                email: formData.get('email'),
                phone: formData.get('phone')
            };

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;

            fetch(apiBookingUrl + '?action=book', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(bookingData)
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;

                if (data.success) {
                    displayConfirmation(data.data);
                    goToStep(4);
                } else {
                    alert('Booking failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                alert('Booking failed: ' + error.message);
            });
        });

        function displayConfirmation(bookingData) {
            const details = document.getElementById('confirmationDetails');
            const startTime = new Date(bookingData.start_time);
            const endTime = new Date(bookingData.end_time);
            const bookingLinkHtml = bookingData.calendly_booking_url ? `
                <div class="alert alert-primary mt-3">
                    <strong>Calendly link:</strong>
                    <p><a href="${bookingData.calendly_booking_url}" target="_blank">Open Calendly booking page</a></p>
                </div>
            ` : '';

            details.innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-calendar-check"></i> Booking Details</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Service:</strong> ${selectedEventType.name}</p>
                                <p><strong>Date:</strong> ${startTime.toLocaleDateString()}</p>
                                <p><strong>Time:</strong> ${startTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} - ${endTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Name:</strong> ${bookingData.name || document.getElementById('name').value}</p>
                                <p><strong>Email:</strong> ${bookingData.email || document.getElementById('email').value}</p>
                                <p><strong>Status:</strong> <span class="badge bg-success">Confirmed</span></p>
                            </div>
                        </div>
                        ${bookingLinkHtml}
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i> A confirmation email has been sent to your registered email address.
                        </div>
                    </div>
                </div>
            `;
        }

        // Reset for new booking
        document.getElementById('newBooking').addEventListener('click', function() {
            location.reload();
        });

        // View booking details (placeholder)
        document.getElementById('viewBooking').addEventListener('click', function() {
            // This would typically navigate to a booking details page
            alert('Booking details view would be implemented here.');
        });
    </script>
</body>
</html>
