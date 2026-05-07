<?php
require_once __DIR__ . '/../../../includes/session_config.php';
require_once __DIR__ . '/../../../includes/url_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . url('app/views/auth/login.php'));
    exit();
}

// Get user information from database
require_once "../../core/Database.php";
$db = new Database();
$conn = $db->connect();

$user_id = $_SESSION['user_id'];

// SECURE: Using prepared statement to prevent SQL injection
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
if (!$stmt) {
    die("Database error occurred");
}

$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$user = $result->fetch_assoc();
$role = $user['role'];
$full_name = $user['first_name'] . ' ' . $user['last_name'];
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Section 2 - Automated Rehab Form - Gambytes</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css?v=<?= time() ?>">
    <style>
        /* ── Top Navbar ── */
        .top-navbar { background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:.85rem 1.5rem; display:flex; align-items:center; justify-content:space-between; margin-bottom:1.75rem; }
        .top-navbar-left .top-navbar-title { font-weight:700; font-size:1rem; color:#800000; }
        .top-navbar-right { display:flex; align-items:center; gap:1rem; }
        .top-navbar-user { display:flex; align-items:center; gap:.5rem; font-size:.9rem; font-weight:600; color:#343a40; }
        .top-navbar-user i { font-size:1.3rem; color:#800000; }

        /* ── Form Styles ── */
        .form-section { background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.07); padding:2rem; margin-bottom:1.5rem; }
        .form-header { border-bottom:2px solid #800000; padding-bottom:1rem; margin-bottom:2rem; }
        .form-header h2 { color:#800000; font-weight:700; margin:0; }
        .form-header p { color:#6c757d; margin:0.5rem 0 0 0; }
        
        .form-group { margin-bottom:1.5rem; }
        .form-label { font-weight:600; color:#343a40; margin-bottom:0.5rem; }
        .form-control, .form-select { border:2px solid #e9ecef; border-radius:8px; padding:0.75rem; transition:all 0.3s; }
        .form-control:focus, .form-select:focus { border-color:#800000; box-shadow:0 0 0 0.2rem rgba(128,0,0,0.25); }
        
        .btn-primary { background:linear-gradient(135deg,#800000,#5c0000); border:none; border-radius:8px; padding:0.75rem 2rem; font-weight:600; transition:all 0.3s; }
        .btn-primary:hover { background:linear-gradient(135deg,#5c0000,#800000); transform:translateY(-1px); box-shadow:0 4px 12px rgba(128,0,0,0.3); }
        
        .progress-step { display:flex; align-items:center; margin-bottom:2rem; }
        .step-number { width:40px; height:40px; border-radius:50%; background:#800000; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; margin-right:1rem; }
        .step-content { flex:1; }
        .step-title { font-weight:700; color:#343a40; margin-bottom:0.25rem; }
        .step-description { color:#6c757d; font-size:0.9rem; }
        
        .assessment-card { background:#f8f9fa; border-radius:8px; padding:1.5rem; margin-bottom:1rem; border-left:4px solid #800000; }
        .assessment-title { font-weight:700; color:#800000; margin-bottom:1rem; }
        
        .radio-group, .checkbox-group { display:flex; flex-wrap:wrap; gap:1rem; }
        .form-check-input:checked { background-color:#800000; border-color:#800000; }
        
        .alert-custom { border-radius:8px; border-left:4px solid #800000; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Modern Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="/GAMBYTES_Final/public/images/Logo.png" alt="Logo">
                    <span>Gambytes</span>
                </div>
                <div class="sidebar-user">
                    <div class="user-name">
                        <i class="fas fa-user-circle me-1"></i>
                        <?= htmlspecialchars($full_name) ?>
                    </div>
                    <div class="user-role"><?= ucfirst(str_replace('_', ' ', $role)) ?></div>
                </div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php">
                    <i class="fas fa-home"></i> Overview
                </a></li>
                
                <?php if ($role === 'supervisor' || $role === 'admin'): ?>
                <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php">
                    <i class="fas fa-calendar-check"></i> Booking Management
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/interview-records.php">
                    <i class="fas fa-clipboard-list"></i> Interview Records
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/contract-review.php">
                    <i class="fas fa-file-contract"></i> Contract Management
                </a></li>
                <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/policies.php">
                    <i class="fas fa-book"></i> Policies &amp; Guidelines
                </a></li>
                <?php endif; ?>
                
                <li><a href="#">
                    <i class="fas fa-user"></i> Profile
                </a></li>
                <div class="menu-divider"></div>
                <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a></li>
            </ul>
        </div>
        
        <!-- Modern Main Content Area -->
        <div class="main-content">

            <!-- Top Navbar -->
            <div class="top-navbar">
                <div class="top-navbar-left">
                    <span class="top-navbar-title">
                        <i class="fas fa-robot me-2"></i>Section 2 - Automated Rehab Form
                    </span>
                </div>
                <div class="top-navbar-right">
                    <div class="top-navbar-user">
                        <i class="fas fa-user-circle"></i>
                        <?= htmlspecialchars($full_name) ?>
                    </div>
                </div>
            </div>

            <!-- Page Header -->
            <div class="page-header fade-in-up">
                <h1><i class="fas fa-robot me-2"></i>Automated Rehabilitation Assessment</h1>
                <p>Complete this comprehensive automated form to assess rehabilitation needs and create personalized treatment plans.</p>
            </div>

            <!-- Automated Rehab Form -->
            <div class="form-section">
                <div class="form-header">
                    <h2>Patient Information</h2>
                    <p>Basic demographic and contact information</p>
                </div>

                <form id="automatedRehabForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="patientName" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="patientName" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="patientAge" class="form-label">Age *</label>
                                <input type="number" class="form-control" id="patientAge" min="18" max="100" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="patientGender" class="form-label">Gender *</label>
                                <select class="form-select" id="patientGender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="patientContact" class="form-label">Contact Number *</label>
                                <input type="tel" class="form-control" id="patientContact" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="patientEmail" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="patientEmail">
                    </div>
                </div>

                <!-- Gambling Assessment Section -->
                <div class="form-section">
                    <div class="form-header">
                        <h2>Gambling Behavior Assessment</h2>
                        <p>Automated evaluation of gambling patterns and severity</p>
                    </div>

                    <div class="progress-step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Frequency Assessment</div>
                            <div class="step-description">How often do you engage in gambling activities?</div>
                        </div>
                    </div>

                    <div class="assessment-card">
                        <div class="assessment-title">Gambling Frequency</div>
                        <div class="radio-group">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="frequency" id="freq1" value="daily">
                                <label class="form-check-label" for="freq1">Daily</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="frequency" id="freq2" value="weekly">
                                <label class="form-check-label" for="freq2">Weekly</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="frequency" id="freq3" value="monthly">
                                <label class="form-check-label" for="freq3">Monthly</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="frequency" id="freq4" value="rarely">
                                <label class="form-check-label" for="freq4">Rarely</label>
                            </div>
                        </div>
                    </div>

                    <div class="progress-step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Financial Impact</div>
                            <div class="step-description">Assessment of financial consequences</div>
                        </div>
                    </div>

                    <div class="assessment-card">
                        <div class="assessment-title">Financial Losses (Past 6 Months)</div>
                        <div class="form-group">
                            <select class="form-select" name="financialLoss">
                                <option value="">Select Range</option>
                                <option value="0-1000">Less than ₱1,000</option>
                                <option value="1000-5000">₱1,000 - ₱5,000</option>
                                <option value="5000-10000">₱5,000 - ₱10,000</option>
                                <option value="10000-50000">₱10,000 - ₱50,000</option>
                                <option value="50000+">More than ₱50,000</option>
                            </select>
                        </div>
                    </div>

                    <div class="progress-step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">Behavioral Indicators</div>
                            <div class="step-description">Identify compulsive gambling behaviors</div>
                        </div>
                    </div>

                    <div class="assessment-card">
                        <div class="assessment-title">Check all that apply:</div>
                        <div class="checkbox-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="behaviors" id="beh1" value="chasing">
                                <label class="form-check-label" for="beh1">Chasing losses</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="behaviors" id="beh2" value="lying">
                                <label class="form-check-label" for="beh2">Lying about gambling</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="behaviors" id="beh3" value="borrowing">
                                <label class="form-check-label" for="beh3">Borrowing money to gamble</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="behaviors" id="beh4" value="neglecting">
                                <label class="form-check-label" for="beh4">Neglecting responsibilities</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="behaviors" id="beh5" value="relationship">
                                <label class="form-check-label" for="beh5">Relationship problems</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Treatment Plan Section -->
                <div class="form-section">
                    <div class="form-header">
                        <h2>Automated Treatment Recommendations</h2>
                        <p>AI-generated personalized rehabilitation plan</p>
                    </div>

                    <div class="alert alert-info alert-custom">
                        <i class="fas fa-robot me-2"></i>
                        <strong>Automated Assessment:</strong> Based on your responses, our system will generate a personalized treatment plan.
                    </div>

                    <div id="treatmentRecommendations" style="display: none;">
                        <!-- Recommendations will be populated by JavaScript -->
                    </div>

                    <div class="form-group">
                        <label for="additionalNotes" class="form-label">Additional Notes or Concerns</label>
                        <textarea class="form-control" id="additionalNotes" rows="4" placeholder="Any additional information you'd like to share..."></textarea>
                    </div>
                </div>

                <!-- Submit Section -->
                <div class="form-section">
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-robot me-2"></i>Generate Automated Assessment
                        </button>
                        <button type="button" class="btn btn-secondary btn-lg" onclick="window.location.href='/GAMBYTES_Final/app/views/auth/dashboard.php'">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </button>
                    </div>
                </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script>
        document.getElementById('automatedRehabForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Collect form data
            const formData = new FormData(this);
            const data = {};
            
            // Basic info
            data.patientName = document.getElementById('patientName').value;
            data.patientAge = document.getElementById('patientAge').value;
            data.patientGender = document.getElementById('patientGender').value;
            data.patientContact = document.getElementById('patientContact').value;
            data.patientEmail = document.getElementById('patientEmail').value;
            
            // Assessment data
            data.frequency = formData.get('frequency');
            data.financialLoss = formData.get('financialLoss');
            data.behaviors = formData.getAll('behaviors');
            data.additionalNotes = document.getElementById('additionalNotes').value;
            
            // Generate automated recommendations
            generateRecommendations(data);
        });

        function generateRecommendations(data) {
            const recommendationsDiv = document.getElementById('treatmentRecommendations');
            
            // Simple scoring algorithm (can be enhanced)
            let riskScore = 0;
            let recommendations = [];
            
            // Frequency scoring
            if (data.frequency === 'daily') riskScore += 4;
            else if (data.frequency === 'weekly') riskScore += 3;
            else if (data.frequency === 'monthly') riskScore += 2;
            else if (data.frequency === 'rarely') riskScore += 1;
            
            // Financial impact scoring
            if (data.financialLoss === '50000+') riskScore += 4;
            else if (data.financialLoss === '10000-50000') riskScore += 3;
            else if (data.financialLoss === '5000-10000') riskScore += 2;
            else if (data.financialLoss === '1000-5000') riskScore += 1;
            
            // Behavioral indicators scoring
            riskScore += data.behaviors.length;
            
            // Generate recommendations based on risk score
            if (riskScore >= 8) {
                recommendations = [
                    {
                        level: 'High',
                        color: 'danger',
                        title: 'Intensive Treatment Program',
                        description: 'Immediate professional intervention required',
                        actions: [
                            'Individual counseling (3x per week)',
                            'Group therapy sessions',
                            'Financial management counseling',
                            'Family therapy involvement',
                            'Regular psychiatric monitoring'
                        ]
                    }
                ];
            } else if (riskScore >= 5) {
                recommendations = [
                    {
                        level: 'Moderate',
                        color: 'warning',
                        title: 'Structured Rehabilitation Program',
                        description: 'Comprehensive treatment plan recommended',
                        actions: [
                            'Weekly counseling sessions',
                            'Support group participation',
                            'Cognitive behavioral therapy',
                            'Relapse prevention planning',
                            'Monthly progress reviews'
                        ]
                    }
                ];
            } else {
                recommendations = [
                    {
                        level: 'Low',
                        color: 'success',
                        title: 'Preventive Care Program',
                        description: 'Early intervention and education',
                        actions: [
                            'Monthly check-ins',
                            'Educational workshops',
                            'Self-help resources',
                            'Peer support groups',
                            'Quarterly assessments'
                        ]
                    }
                ];
            }
            
            // Display recommendations
            let html = '';
            recommendations.forEach(rec => {
                html += `
                    <div class="alert alert-${rec.color} alert-custom">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Risk Level: ${rec.level}</h5>
                        <h6>${rec.title}</h6>
                        <p>${rec.description}</p>
                        <hr>
                        <h6>Recommended Actions:</h6>
                        <ul class="mb-0">
                `;
                rec.actions.forEach(action => {
                    html += `<li>${action}</li>`;
                });
                html += `
                        </ul>
                    </div>
                `;
            });
            
            recommendationsDiv.innerHTML = html;
            recommendationsDiv.style.display = 'block';
            
            // Scroll to recommendations
            recommendationsDiv.scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>
