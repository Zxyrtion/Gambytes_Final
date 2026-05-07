<?php
require_once __DIR__ . '/../../../../../includes/session_config.php';
require_once __DIR__ . '/../../../../../includes/url_helper.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . url('app/views/auth/login.php'));
    exit();
}
require_once __DIR__ . '/../../../../core/Database.php';
$db   = new Database();
$conn = $db->connect();

$user_id    = $_SESSION['user_id'];
$booking_id = (int)($_GET['booking_id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || $user['role'] !== 'gambler') {
    header("Location: " . url('app/views/auth/dashboard.php'));
    exit();
}
if (!$booking_id) {
    header("Location: /GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php");
    exit();
}

$bStmt = $conn->prepare("SELECT br.*, ii.score, ii.diagnosis FROM booking_record br
    LEFT JOIN Initial_Interview_Record ii ON ii.booking_id = br.id
    WHERE br.id = ? AND LOWER(br.email) = LOWER(?)");
$bStmt->bind_param('is', $booking_id, $user['email']);
$bStmt->execute();
$booking = $bStmt->get_result()->fetch_assoc();
$bStmt->close();

if (!$booking) {
    header("Location: /GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php");
    exit();
}

$cStmt = $conn->prepare("SELECT id FROM contract_documents WHERE user_id = ? AND booking_id = ? LIMIT 1");
$cStmt->bind_param('ii', $user_id, $booking_id);
$cStmt->execute();
$existing = $cStmt->get_result()->fetch_assoc();
$cStmt->close();
if ($existing) {
    header("Location: /GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php");
    exit();
}

$full_name  = $user['first_name'] . ' ' . ($user['middle_name'] ? $user['middle_name'] . ' ' : '') . $user['last_name'];
$today      = date('F j, Y');
$today_iso  = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Application Forms – Gambytes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
<style>
.form-card{background:#fff;border-radius:16px;box-shadow:0 2px 15px rgba(0,0,0,.08);overflow:hidden;margin-bottom:2rem}
.form-card-header{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;padding:1rem 1.5rem;font-weight:700;font-size:1rem;display:flex;align-items:center;gap:.6rem}
.form-card-body{padding:1.75rem}
.step-indicator{display:flex;align-items:center;gap:.5rem;margin-bottom:2rem;flex-wrap:wrap}
.step{display:flex;align-items:center;gap:.4rem;font-size:.85rem;font-weight:600}
.step-num{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700}
.step.active .step-num{background:#800000;color:#fff}
.step.done .step-num{background:#198754;color:#fff}
.step.pending .step-num{background:#dee2e6;color:#6c757d}
.step.active .step-label{color:#800000}
.step.done .step-label{color:#198754}
.step.pending .step-label{color:#6c757d}
.step-sep{flex:1;height:2px;background:#dee2e6;min-width:20px}
.sig-canvas-wrap{border:2px solid #dee2e6;border-radius:10px;background:#f8f9fa;position:relative;overflow:hidden}
.sig-canvas-wrap canvas{display:block;cursor:crosshair;touch-action:none}
.sig-actions{display:flex;gap:.5rem;margin-top:.5rem}
.btn-clear-sig{background:#6c757d;color:#fff;border:none;border-radius:8px;padding:.4rem 1rem;font-size:.82rem;font-weight:600;cursor:pointer}
.btn-clear-sig:hover{background:#5a6268}
.form-section-title{background:#343a40;color:#fff;padding:.4rem .85rem;font-weight:700;font-size:.85rem;margin:.85rem 0 .5rem;border-radius:4px}
.field-row{display:grid;gap:.75rem;margin-bottom:.75rem}
.field-row.cols-2{grid-template-columns:1fr 1fr}
.field-row.cols-3{grid-template-columns:1fr 1fr 1fr}
.field-group label{font-size:.8rem;font-weight:600;color:#495057;margin-bottom:.25rem;display:block}
.field-group input,.field-group select,.field-group textarea{width:100%;border:1.5px solid #dee2e6;border-radius:8px;padding:.45rem .75rem;font-size:.88rem;transition:border-color .2s}
.field-group input:focus,.field-group select:focus,.field-group textarea:focus{outline:none;border-color:#800000;box-shadow:0 0 0 3px rgba(128,0,0,.1)}
.check-group{display:flex;flex-wrap:wrap;gap:.5rem 1.5rem;margin:.4rem 0}
.check-item{display:flex;align-items:center;gap:.4rem;font-size:.88rem;cursor:pointer}
.check-item input[type=checkbox],.check-item input[type=radio]{accent-color:#800000;width:15px;height:15px}
.tab-nav{display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap}
.tab-btn{padding:.55rem 1.25rem;border-radius:10px;border:2px solid #dee2e6;background:#fff;font-weight:600;font-size:.88rem;cursor:pointer;transition:all .2s;color:#343a40}
.tab-btn.active{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;border-color:#800000}
.tab-pane{display:none}
.tab-pane.active{display:block}
.btn-submit-all{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;border:none;border-radius:12px;padding:.8rem 2.5rem;font-weight:700;font-size:1rem;cursor:pointer;display:inline-flex;align-items:center;gap:.6rem;box-shadow:0 4px 14px rgba(128,0,0,.3);transition:all .2s}
.btn-submit-all:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 6px 20px rgba(128,0,0,.4)}
.btn-submit-all:disabled{opacity:.5;cursor:not-allowed}
.progress-tabs{display:flex;gap:0;margin-bottom:1.5rem;border-radius:12px;overflow:hidden;border:2px solid #dee2e6}
.progress-tab{flex:1;padding:.6rem .5rem;text-align:center;font-size:.82rem;font-weight:600;background:#f8f9fa;color:#6c757d;cursor:pointer;border:none;transition:all .2s}
.progress-tab.active{background:linear-gradient(135deg,#800000,#5c0000);color:#fff}
.progress-tab.done{background:#d1e7dd;color:#0f5132}
</style>
</head>
<body>
<div class="dashboard-container">
<div class="sidebar">
<div class="sidebar-header">
<div class="sidebar-logo"><img src="/GAMBYTES_Final/public/images/Logo.png" alt="Logo"><span>Gambytes</span></div>
<div class="sidebar-user">
<div class="user-name"><i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($full_name); ?></div>
<div class="user-role">Gambler</div>
</div>
</div>
<ul class="sidebar-menu">
<li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/BookRehab.php"><i class="fas fa-calendar-plus"></i> Book Rehabilitation</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php"><i class="fas fa-clipboard-list"></i> My Interview</a></li>
<li><a href="#" class="active"><i class="fas fa-file-contract"></i> My Contracts</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-cbt-sessions.php"><i class="fas fa-brain"></i> CBT Sessions</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/my-activities.php"><i class="fas fa-tasks"></i> My Activities</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Gamblers/parental-control-requests.php"><i class="fas fa-shield-alt"></i> Parental Access</a></li>
<li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
<div class="menu-divider"></div>
<li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul>
</div>
<div class="main-content">
<div style="margin-bottom:1.75rem">
<h1 style="color:#800000;font-size:1.8rem;font-weight:800;margin:0"><i class="fas fa-pen-nib me-2"></i>Application Forms</h1>
<p style="color:#6c757d;margin:.25rem 0 0">Complete all three forms and sign each one to submit your rehabilitation application</p>
</div>
<div class="step-indicator">
<div class="step done"><div class="step-num"><i class="fas fa-check" style="font-size:.65rem"></i></div><div class="step-label">Review Policies</div></div>
<div class="step-sep"></div>
<div class="step active"><div class="step-num">2</div><div class="step-label">Complete Forms &amp; Sign</div></div>
<div class="step-sep"></div>
<div class="step pending"><div class="step-num">3</div><div class="step-label">Submitted</div></div>
</div>
<div class="progress-tabs">
<button class="progress-tab active" onclick="showTab(0,this)"><i class="fas fa-ban me-1"></i>Self Exclusion</button>
<button class="progress-tab" onclick="showTab(1,this)"><i class="fas fa-users me-1"></i>Family Exclusion</button>
<button class="progress-tab" onclick="showTab(2,this)"><i class="fas fa-id-card me-1"></i>Player Verification</button>
</div>
<form id="contractForm">
<input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
<div id="tab0" class="tab-section">
<div class="form-card">
<div class="form-card-header"><i class="fas fa-ban"></i> Self Exclusion Application Form</div>
<div class="form-card-body">
<p style="font-size:.85rem;color:#6c757d;margin-bottom:1rem">I voluntarily request to be excluded from all gambling activities. Please fill in all required fields.</p>
<div class="form-section-title">Personal Information</div>
<div class="field-row cols-2">
<div class="field-group"><label>Last Name *</label><input type="text" name="se_last_name" required placeholder="Last name"></div>
<div class="field-group"><label>First Name *</label><input type="text" name="se_first_name" required placeholder="First name"></div>
</div>
<div class="field-row cols-2">
<div class="field-group"><label>Middle Name</label><input type="text" name="se_middle_name" placeholder="Middle name"></div>
<div class="field-group"><label>Date of Birth *</label><input type="date" name="se_dob" required></div>
</div>
<div class="field-row cols-2">
<div class="field-group"><label>Gender *</label><select name="se_gender" required><option value="">Select</option><option>Male</option><option>Female</option><option>Other</option></select></div>
<div class="field-group"><label>Civil Status *</label><select name="se_civil_status" required><option value="">Select</option><option>Single</option><option>Married</option><option>Widowed</option><option>Separated</option></select></div>
</div>
<div class="field-row">
<div class="field-group"><label>Complete Address *</label><input type="text" name="se_address" required placeholder="House No., Street, Barangay, City/Municipality, Province"></div>
</div>
<div class="field-row cols-2">
<div class="field-group"><label>Contact Number *</label><input type="tel" name="se_contact" required placeholder="09XXXXXXXXX"></div>
<div class="field-group"><label>Email Address</label><input type="email" name="se_email" placeholder="email@example.com"></div>
</div>
<div class="form-section-title">Exclusion Details</div>
<div class="field-row">
<div class="field-group"><label>Reason for Self-Exclusion *</label><textarea name="se_reason" rows="3" required placeholder="Briefly describe your reason for requesting self-exclusion..."></textarea></div>
</div>
<div class="field-row cols-2">
<div class="field-group"><label>Exclusion Period *</label><select name="se_period" required><option value="">Select Period</option><option value="6 months">6 Months</option><option value="1 year">1 Year</option><option value="2 years">2 Years</option><option value="5 years">5 Years</option><option value="Lifetime">Lifetime</option></select></div>
<div class="field-group"><label>Date of Application *</label><input type="date" name="se_date" required value="<?php echo $today_iso; ?>"></div>
</div>
<div class="form-section-title">Signature</div>
<p style="font-size:.82rem;color:#6c757d;margin-bottom:.5rem">Draw your signature below using your mouse or touch screen:</p>
<div class="sig-canvas-wrap"><canvas id="sig_self" width="700" height="150"></canvas></div>
<div class="sig-actions">
<button type="button" class="btn-clear-sig" onclick="clearSig('sig_self')"><i class="fas fa-eraser me-1"></i>Clear Signature</button>
<span id="sig_self_status" style="font-size:.8rem;color:#6c757d;align-self:center;margin-left:.5rem"></span>
</div>
<input type="hidden" name="self_exclusion_sig" id="self_exclusion_sig">
</div>
</div>
</div>
<div id="tab1" class="tab-section" style="display:none">
<div class="form-card">
<div class="form-card-header"><i class="fas fa-users"></i> Family Exclusion Application Form</div>
<div class="form-card-body">

<!-- Notice banner -->
<div style="background:#fff3cd;border:1.5px solid #ffc107;border-radius:10px;padding:.85rem 1.1rem;margin-bottom:1.25rem;display:flex;gap:.75rem;align-items:flex-start;">
  <i class="fas fa-info-circle" style="color:#856404;margin-top:.15rem;flex-shrink:0;"></i>
  <div style="font-size:.85rem;color:#664d03;line-height:1.6;">
    <strong>Important:</strong> This form must be filled out and signed by the <strong>family member</strong> who is requesting the exclusion on behalf of the gambler.
    The gambler must also provide a <strong>counter-signature</strong> at the bottom to acknowledge and consent to this request.
    Please have both parties present when completing this form.
  </div>
</div>

<div class="form-section-title">Part 1 – Family Member (Applicant) Information</div>
<div class="field-row cols-2">
<div class="field-group"><label>Last Name *</label><input type="text" name="fe_app_last_name" id="fe_app_last_name" required placeholder="Last name" oninput="lookupFamilyMember()"></div>
<div class="field-group"><label>First Name *</label><input type="text" name="fe_app_first_name" id="fe_app_first_name" required placeholder="First name" oninput="lookupFamilyMember()"></div>
</div>
<div id="fm_lookup_result" style="margin-bottom:.75rem;display:none;"></div>
<div class="field-row cols-2">
<div class="field-group"><label>Relationship to Gambler *</label><select name="fe_relationship" required><option value="">Select</option><option>Spouse</option><option>Parent</option><option>Child</option><option>Sibling</option><option>Guardian</option><option>Other</option></select></div>
<div class="field-group"><label>Contact Number *</label><input type="tel" name="fe_app_contact" required placeholder="09XXXXXXXXX"></div>
</div>
<div class="field-row">
<div class="field-group"><label>Complete Address *</label><input type="text" name="fe_app_address" required placeholder="House No., Street, Barangay, City/Municipality, Province"></div>
</div>

<div class="form-section-title">Part 2 – Gambler (Subject) Information</div>
<div class="field-row cols-2">
<div class="field-group"><label>Last Name *</label><input type="text" name="fe_gam_last_name" required placeholder="Last name"></div>
<div class="field-group"><label>First Name *</label><input type="text" name="fe_gam_first_name" required placeholder="First name"></div>
</div>
<div class="field-row cols-2">
<div class="field-group"><label>Date of Birth *</label><input type="date" name="fe_gam_dob" required></div>
<div class="field-group"><label>Contact Number</label><input type="tel" name="fe_gam_contact" placeholder="09XXXXXXXXX"></div>
</div>
<div class="field-row">
<div class="field-group"><label>Reason for Family Exclusion Request *</label><textarea name="fe_reason" rows="3" required placeholder="Describe the gambling problem and reason for this request..."></textarea></div>
</div>
<div class="field-row cols-2">
<div class="field-group"><label>Date of Application *</label><input type="date" name="fe_date" required value="<?php echo $today_iso; ?>"></div>
</div>

<!-- Family Member Signature -->
<div class="form-section-title">Part 3 – Family Member Signature (Applicant)</div>
<p style="font-size:.82rem;color:#6c757d;margin-bottom:.5rem">
  <i class="fas fa-pen me-1" style="color:#800000;"></i>
  To be signed by the <strong>family member</strong> who is filing this request:
</p>
<div class="sig-canvas-wrap"><canvas id="sig_family" width="700" height="150"></canvas></div>
<div class="sig-actions">
<button type="button" class="btn-clear-sig" onclick="clearSig('sig_family')"><i class="fas fa-eraser me-1"></i>Clear Signature</button>
<span id="sig_family_status" style="font-size:.8rem;color:#6c757d;align-self:center;margin-left:.5rem"></span>
</div>
<input type="hidden" name="family_exclusion_sig" id="family_exclusion_sig">

<!-- Gambler Counter-Signature -->
<div class="form-section-title" style="margin-top:1.25rem;">Part 4 – Gambler Counter-Signature (Consent)</div>
<div style="background:#f8f9fa;border-radius:10px;padding:.85rem 1.1rem;margin-bottom:.75rem;font-size:.85rem;color:#343a40;line-height:1.6;border-left:4px solid #800000;">
  I, the gambler named above, acknowledge and consent to this Family Exclusion request filed on my behalf.
  I understand that this exclusion is intended to support my rehabilitation and recovery.
</div>
<p style="font-size:.82rem;color:#6c757d;margin-bottom:.5rem">
  <i class="fas fa-pen me-1" style="color:#800000;"></i>
  To be signed by the <strong>gambler</strong> as counter-signature / consent:
</p>
<div class="sig-canvas-wrap"><canvas id="sig_family_counter" width="700" height="150"></canvas></div>
<div class="sig-actions">
<button type="button" class="btn-clear-sig" onclick="clearSig('sig_family_counter')"><i class="fas fa-eraser me-1"></i>Clear Signature</button>
<span id="sig_family_counter_status" style="font-size:.8rem;color:#6c757d;align-self:center;margin-left:.5rem"></span>
</div>
<input type="hidden" name="family_exclusion_counter_sig" id="family_exclusion_counter_sig">

</div>
</div>
</div>
<div id="tab2" class="tab-section" style="display:none">
<div class="form-card">
<div class="form-card-header"><i class="fas fa-id-card"></i> Player Verification Form</div>
<div class="form-card-body">
<p style="font-size:.85rem;color:#6c757d;margin-bottom:1rem">This form verifies your identity as part of the rehabilitation enrollment process.</p>
<div class="form-section-title">Personal Details</div>
<div class="field-row cols-2">
<div class="field-group"><label>Last Name *</label><input type="text" name="pv_last_name" required placeholder="Last name"></div>
<div class="field-group"><label>First Name *</label><input type="text" name="pv_first_name" required placeholder="First name"></div>
</div>
<div class="field-row cols-3">
<div class="field-group"><label>Middle Name</label><input type="text" name="pv_middle_name" placeholder="Middle name"></div>
<div class="field-group"><label>Date of Birth *</label><input type="date" name="pv_dob" required></div>
<div class="field-group"><label>Place of Birth *</label><input type="text" name="pv_pob" required placeholder="City/Municipality"></div>
</div>
<div class="field-row cols-2">
<div class="field-group"><label>Nationality *</label><input type="text" name="pv_nationality" required placeholder="e.g. Filipino"></div>
<div class="field-group"><label>Occupation</label><input type="text" name="pv_occupation" placeholder="Current occupation"></div>
</div>
<div class="field-row">
<div class="field-group"><label>Complete Address *</label><input type="text" name="pv_address" required placeholder="House No., Street, Barangay, City/Municipality, Province"></div>
</div>
<div class="field-row cols-2">
<div class="field-group"><label>Contact Number *</label><input type="tel" name="pv_contact" required placeholder="09XXXXXXXXX"></div>
<div class="field-group"><label>Email Address *</label><input type="email" name="pv_email" required placeholder="email@example.com"></div>
</div>
<div class="form-section-title">Government ID</div>
<div class="field-row cols-2">
<div class="field-group"><label>ID Type *</label><select name="pv_id_type" required><option value="">Select ID Type</option><option>Philippine Passport</option><option>SSS ID</option><option>GSIS ID</option><option>PhilHealth ID</option><option>Postal ID</option><option>Voter ID</option><option>Driver License</option><option>PRC ID</option><option>National ID</option></select></div>
<div class="field-group"><label>ID Number *</label><input type="text" name="pv_id_number" required placeholder="ID Number"></div>
</div>
<div class="field-row cols-2">
<div class="field-group"><label>Date Issued</label><input type="date" name="pv_id_issued"></div>
<div class="field-group"><label>Place Issued</label><input type="text" name="pv_id_place" placeholder="Issuing office/city"></div>
</div>
<div class="form-section-title">Declaration</div>
<div style="background:#fff8e1;border-radius:10px;padding:1rem;margin-bottom:1rem;font-size:.85rem;color:#343a40;line-height:1.7">
I hereby certify that all information provided in this form is true and correct to the best of my knowledge. I understand that providing false information may result in the cancellation of my rehabilitation application.
</div>
<div class="field-row cols-2">
<div class="field-group"><label>Date *</label><input type="date" name="pv_date" required value="<?php echo $today_iso; ?>"></div>
</div>
<div class="form-section-title">Signature</div>
<p style="font-size:.82rem;color:#6c757d;margin-bottom:.5rem">Draw your signature below:</p>
<div class="sig-canvas-wrap"><canvas id="sig_player" width="700" height="150"></canvas></div>
<div class="sig-actions">
<button type="button" class="btn-clear-sig" onclick="clearSig('sig_player')"><i class="fas fa-eraser me-1"></i>Clear Signature</button>
<span id="sig_player_status" style="font-size:.8rem;color:#6c757d;align-self:center;margin-left:.5rem"></span>
</div>
<input type="hidden" name="player_verification_sig" id="player_verification_sig">
</div>
</div>
</div>
<div style="background:#fff;border-radius:16px;box-shadow:0 2px 15px rgba(0,0,0,.08);padding:1.5rem 1.75rem;margin-bottom:2rem">
<div id="submit_msg" style="display:none;margin-bottom:1rem;padding:.75rem 1rem;border-radius:8px;font-size:.88rem"></div>
<div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
<a href="/GAMBYTES_Final/app/views/Users/Gamblers/contract/review_terms.php?booking_id=<?php echo $booking_id; ?>" style="background:#6c757d;color:#fff;border:none;border-radius:12px;padding:.75rem 1.5rem;font-weight:600;font-size:.9rem;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem"><i class="fas fa-arrow-left"></i> Back</a>
<button type="button" id="submitBtn" class="btn-submit-all" onclick="submitForms()"><i class="fas fa-paper-plane"></i> Submit All Forms</button>
</div>
</div>
</form>
</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
var tabs=[document.getElementById('tab0'),document.getElementById('tab1'),document.getElementById('tab2')];
var tabBtns=document.querySelectorAll('.progress-tab');
function showTab(i,btn){
  tabs.forEach(function(t,idx){
    t.style.display=(idx===i)?'block':'none';
    // disable required on hidden tabs so browser doesn't block rendering
    t.querySelectorAll('[required]').forEach(function(el){
      if(idx===i){ el.setAttribute('required',''); }
      else { el.removeAttribute('required'); el.setAttribute('data-was-required','1'); }
    });
  });
  tabBtns.forEach(function(b){b.classList.remove('active');});
  btn.classList.add('active');
}
// init: disable required on hidden tabs on load
document.addEventListener('DOMContentLoaded',function(){
  tabs.forEach(function(t,idx){
    if(idx!==0){
      t.querySelectorAll('[required]').forEach(function(el){
        el.removeAttribute('required');
        el.setAttribute('data-was-required','1');
      });
    }
  });
});
var sigPads={};
function initSig(id){var c=document.getElementById(id);if(!c)return;var ctx=c.getContext('2d');var drawing=false;var lastX=0,lastY=0;function getPos(e){var r=c.getBoundingClientRect();if(e.touches){return{x:e.touches[0].clientX-r.left,y:e.touches[0].clientY-r.top};}return{x:e.clientX-r.left,y:e.clientY-r.top};}
c.addEventListener('mousedown',function(e){drawing=true;var p=getPos(e);lastX=p.x;lastY=p.y;});c.addEventListener('mousemove',function(e){if(!drawing)return;var p=getPos(e);ctx.beginPath();ctx.moveTo(lastX,lastY);ctx.lineTo(p.x,p.y);ctx.strokeStyle='#000';ctx.lineWidth=2;ctx.lineCap='round';ctx.stroke();lastX=p.x;lastY=p.y;updateStatus(id);});c.addEventListener('mouseup',function(){drawing=false;});c.addEventListener('mouseleave',function(){drawing=false;});c.addEventListener('touchstart',function(e){e.preventDefault();drawing=true;var p=getPos(e);lastX=p.x;lastY=p.y;},{passive:false});c.addEventListener('touchmove',function(e){e.preventDefault();if(!drawing)return;var p=getPos(e);ctx.beginPath();ctx.moveTo(lastX,lastY);ctx.lineTo(p.x,p.y);ctx.strokeStyle='#000';ctx.lineWidth=2;ctx.lineCap='round';ctx.stroke();lastX=p.x;lastY=p.y;updateStatus(id);},{passive:false});c.addEventListener('touchend',function(){drawing=false;});sigPads[id]=ctx;}
function updateStatus(id){var map={'sig_self':'sig_self_status','sig_family':'sig_family_status','sig_family_counter':'sig_family_counter_status','sig_player':'sig_player_status'};var s=document.getElementById(map[id]);if(s)s.textContent='Signature captured';}
function clearSig(id){var c=document.getElementById(id);if(!c)return;var ctx=c.getContext('2d');ctx.clearRect(0,0,c.width,c.height);var map={'sig_self':'sig_self_status','sig_family':'sig_family_status','sig_family_counter':'sig_family_counter_status','sig_player':'sig_player_status'};var s=document.getElementById(map[id]);if(s)s.textContent='';}
function isSigEmpty(id){var c=document.getElementById(id);if(!c)return true;var ctx=c.getContext('2d');var d=ctx.getImageData(0,0,c.width,c.height).data;for(var i=3;i<d.length;i+=4){if(d[i]>0)return false;}return true;}
window.addEventListener('load',function(){initSig('sig_self');initSig('sig_family');initSig('sig_family_counter');initSig('sig_player');});
var fmLookupTimer=null;
function lookupFamilyMember(){
  clearTimeout(fmLookupTimer);
  fmLookupTimer=setTimeout(function(){
    var fn=document.getElementById('fe_app_first_name').value.trim();
    var ln=document.getElementById('fe_app_last_name').value.trim();
    var box=document.getElementById('fm_lookup_result');
    if(!fn||!ln){box.style.display='none';return;}
    fetch('/GAMBYTES_Final/api/contract.php?action=lookup_family&first='+encodeURIComponent(fn)+'&last='+encodeURIComponent(ln))
      .then(function(r){return r.json();})
      .then(function(d){
        box.style.display='block';
        if(d.found){
          box.innerHTML='<div style="background:#d1e7dd;color:#0f5132;border-radius:8px;padding:.5rem .85rem;font-size:.83rem;"><i class="fas fa-check-circle me-1"></i><strong>'+d.name+'</strong> is registered as a Family Member. Their contract will be linked to their account.</div>';
        } else {
          box.innerHTML='<div style="background:#fff3cd;color:#664d03;border-radius:8px;padding:.5rem .85rem;font-size:.83rem;"><i class="fas fa-exclamation-triangle me-1"></i>No Family Member account found for <strong>'+fn+' '+ln+'</strong>. They need to register first with the Family Member role.</div>';
        }
      }).catch(function(){box.style.display='none';});
  },600);
}
function submitForms(){
var form=document.getElementById('contractForm');
// restore required on all fields before validating
form.querySelectorAll('[data-was-required]').forEach(function(el){
  el.setAttribute('required','');
  el.removeAttribute('data-was-required');
});
if(!form.checkValidity()){form.reportValidity();return;}
if(isSigEmpty('sig_self')){alert('Please sign the Self Exclusion Application Form.');showTab(0,tabBtns[0]);return;}
if(isSigEmpty('sig_family')){alert('Please have the family member sign the Family Exclusion Application Form (Part 3).');showTab(1,tabBtns[1]);return;}
if(isSigEmpty('sig_family_counter')){alert('Please have the gambler provide a counter-signature on the Family Exclusion Form (Part 4).');showTab(1,tabBtns[1]);return;}
if(isSigEmpty('sig_player')){alert('Please sign the Player Verification Form.');showTab(2,tabBtns[2]);return;}
document.getElementById('self_exclusion_sig').value=document.getElementById('sig_self').toDataURL();
document.getElementById('family_exclusion_sig').value=document.getElementById('sig_family').toDataURL();
document.getElementById('family_exclusion_counter_sig').value=document.getElementById('sig_family_counter').toDataURL();
document.getElementById('player_verification_sig').value=document.getElementById('sig_player').toDataURL();
var fd=new FormData(form);
var btn=document.getElementById('submitBtn');
btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i> Submitting...';
var msg=document.getElementById('submit_msg');
fetch('/GAMBYTES_Final/api/contract.php',{method:'POST',body:fd})
.then(function(r){return r.json();})
.then(function(data){
btn.disabled=false;btn.innerHTML='<i class="fas fa-paper-plane"></i> Submit All Forms';
msg.style.display='block';
if(data.success){msg.style.background='#d1e7dd';msg.style.color='#0f5132';msg.innerHTML='<i class="fas fa-check-circle me-1"></i> Application submitted successfully! Redirecting...';setTimeout(function(){window.location.href='/GAMBYTES_Final/app/views/Users/Gamblers/my-interview.php';},2000);}
else{msg.style.background='#f8d7da';msg.style.color='#842029';msg.innerHTML='<i class="fas fa-exclamation-circle me-1"></i>'+(data.message||'Submission failed.');}
}).catch(function(){btn.disabled=false;btn.innerHTML='<i class="fas fa-paper-plane"></i> Submit All Forms';msg.style.display='block';msg.style.background='#f8d7da';msg.style.color='#842029';msg.innerHTML='<i class="fas fa-exclamation-circle me-1"></i> Network error. Please try again.';});}
</script>
</body>
</html>
