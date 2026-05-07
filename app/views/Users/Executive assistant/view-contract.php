<?php
require_once __DIR__ . '/../../../../includes/session_config.php';
require_once __DIR__ . '/../../../../includes/url_helper.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . url('app/views/auth/login.php'));
    exit();
}

require_once __DIR__ . '/../../../core/Database.php';
$db = new Database();
$conn = $db->connect();

$user_id = $_SESSION['user_id'];
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

$full_name = $user['first_name'] . ' ' . $user['last_name'];

$contract_id = (int)($_GET['id'] ?? 0);

if (!$contract_id) {
    header("Location: /GAMBYTES_Final/app/views/Users/Executive Assistant/dashboard.php");
    exit();
}

// Get gambler contract
$contract = $conn->prepare("
    SELECT cs.*, 
           CONCAT(gu.first_name, ' ', gu.last_name) AS gambler_name,
           gu.email AS gambler_email,
           'Rehabilitation Agreement' AS template_title,
           '' AS template_filename,
           ii.score AS interview_score,
           ii.diagnosis AS diagnosis,
           ii.remarks AS interview_remarks,
           CONCAT(fu.first_name, ' ', fu.last_name) AS family_name
    FROM contract_submissions cs
    JOIN users gu ON gu.id = cs.gambler_id
    LEFT JOIN Initial_Interview_Record ii ON ii.booking_id = cs.booking_id
    LEFT JOIN users fu ON fu.id = cs.family_member_id
    WHERE cs.id = ?
");

if ($contract === false) {
    die("Error preparing contract query: " . $conn->error);
}

$contract->bind_param('i', $contract_id);
$contract->execute();
$contract = $contract->get_result()->fetch_assoc();

if (!$contract) {
    header("Location: /GAMBYTES_Final/app/views/Users/Executive Assistant/dashboard.php");
    exit();
}

// Get gambler signature from signed_contract_documents
$gamblerSignature = $conn->prepare("
    SELECT signature_data, signed_at 
    FROM signed_contract_documents 
    WHERE contract_document_id = ? AND signer_role = 'gambler'
    ORDER BY signed_at DESC LIMIT 1
");
if ($gamblerSignature === false) {
    die("Error preparing gambler signature query: " . $conn->error);
}
$gamblerSignature->bind_param('i', $contract_id);
$gamblerSignature->execute();
$gamblerSignature = $gamblerSignature->get_result()->fetch_assoc();

// Get family member signature from signed_contract_documents
$familySignature = null;
if ($contract['family_member_id']) {
    $familySignature = $conn->prepare("
        SELECT signature_data, signed_at 
        FROM signed_contract_documents 
        WHERE contract_document_id = ? AND signer_role = 'family'
        ORDER BY signed_at DESC LIMIT 1
    ");
    $familySignature->bind_param('i', $contract_id);
    $familySignature->execute();
    $familySignature = $familySignature->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contract Verification – Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
    <style>
        body { background:#f5f5f5; margin:0; padding:0; font-family:'Inter', sans-serif; }
        .dashboard-container { display:flex; min-height:100vh; }
        .main-content { margin-left:260px; flex:1; padding:2rem; }
        .contract-container { max-width:1200px; margin:0 auto; background:#fff; border-radius:20px; box-shadow:0 10px 40px rgba(0,0,0,.1); overflow:hidden; }
        .contract-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:1.5rem; text-align:center; position:relative; }
        .contract-header h1 { font-size:1.5rem; margin:0; }
        .contract-header p { font-size:.9rem; margin:.5rem 0 0 0; opacity:.9; }
        .contract-content { padding:3rem; }
        .contract-section { margin-bottom:3rem; }
        .contract-section h3 { color:#800000; font-weight:700; margin-bottom:1.5rem; border-bottom:2px solid #800000; padding-bottom:.5rem; }
        .info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; margin-bottom:1.5rem; }
        .info-box { background:#f8f9fa; border-radius:8px; padding:1rem; border-left:3px solid #800000; }
        .info-label { font-size:.7rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.3px; margin-bottom:.3rem; }
        .info-value { font-weight:600; color:#212529; font-size:.9rem; }
        .signature-section { background:#f8f9fa; border-radius:12px; padding:2rem; margin:1.5rem 0; }
        .signature-img { max-width:200px; border:2px solid #dee2e6; border-radius:8px; background:#fff; padding:.5rem; }
        .contract-text { font-family:'Times New Roman', serif; font-size:12pt; line-height:1.6; color:#212529; }
        .memo-header { text-align:right; margin-bottom:20pt; }
        .memo-table { width:100%; border-collapse:collapse; margin-bottom:20pt; }
        .memo-table td { padding:10pt 0; vertical-align:top; }
        .memo-table td:first-child { width:150px; font-weight:700; }
    </style>
</head>
<body>
<div class="dashboard-container">

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="/GAMBYTES_Final/public/images/Logo.png" alt="Logo">
                <span>Gambytes</span>
            </div>
            <div class="sidebar-user">
                <div class="user-name"><i class="fas fa-user-tie me-1"></i><?= htmlspecialchars($full_name) ?></div>
                <div class="user-role">Executive Assistant</div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/GAMBYTES_Final/app/views/Users/Executive Assistant/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Executive Assistant/contract-verification.php" class="active"><i class="fas fa-file-contract"></i> Contract Verification</a></li>
            <li><a href="#"><i class="fas fa-chart-line"></i> Reports</a></li>
            <li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
            <div class="menu-divider"></div>
            <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
    <div class="contract-container">
        <div class="contract-header">
            <button onclick="window.location.href='/GAMBYTES_Final/app/views/Users/Executive Assistant/contract-verification.php'" style="position:absolute; left:2rem; top:2rem; background:rgba(255,255,255,.2); border:none; color:#fff; padding:.5rem 1rem; border-radius:8px; cursor:pointer; display:flex; align-items:center; gap:.5rem; font-size:.9rem; transition:all .2s;" onmouseover="this.style.background='rgba(255,255,255,.3)'" onmouseout="this.style.background='rgba(255,255,255,.2)'"><i class="fas fa-arrow-left"></i> Back</button>
            <h1><i class="fas fa-file-contract me-3"></i>Contract Verification</h1>
            <p><?= htmlspecialchars($contract['template_title']) ?></p>
        </div>

        <div class="contract-content">
            <!-- Contract Information -->
            <div class="contract-section">
                <h3><i class="fas fa-info-circle me-2"></i>Contract Information</h3>
                <div class="info-grid">
                    <div class="info-box">
                        <div class="info-label">Contract ID</div>
                        <div class="info-value">#<?= str_pad($contract['id'], 6, '0', STR_PAD_LEFT) ?></div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Gambler</div>
                        <div class="info-value"><?= htmlspecialchars($contract['gambler_name']) ?></div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?= htmlspecialchars($contract['gambler_email']) ?></div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Interview Score</div>
                        <div class="info-value"><?= $contract['interview_score'] ?>/9</div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Diagnosis</div>
                        <div class="info-value"><?= htmlspecialchars($contract['diagnosis']) ?></div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Submitted</div>
                        <div class="info-value"><?= date('F j, Y g:i A', strtotime($contract['created_at'])) ?></div>
                    </div>
                </div>
            </div>

            <!-- Contract Content -->
            <div class="contract-section">
                <h3><i class="fas fa-file-alt me-2"></i>Rehabilitation Agreement</h3>
                <div class="contract-text">
                    <div class="memo-header"><?= date('F j, Y') ?></div>
                    
                    <table class="memo-table">
                        <tr>
                            <td>MEMORANDUM TO:</td>
                            <td>
                                <?= htmlspecialchars($contract['gambler_name']) ?><br>
                                Rehabilitation Participant<br>
                                Gambytes Recovery Program
                            </td>
                        </tr>
                        <tr>
                            <td>FROM:</td>
                            <td>
                                Rehabilitation Services Department<br>
                                Philippine Amusement and Gaming Corporation
                            </td>
                        </tr>
                        <tr>
                            <td>CASE:</td>
                            <td>Rehabilitation Agreement for Gambling Addiction Treatment</td>
                        </tr>
                        <tr>
                            <td>SUBJECT:</td>
                            <td>
                                Terms and Conditions for Participation in the<br>
                                Gambytes Rehabilitation Program
                            </td>
                        </tr>
                    </table>

                    <hr style="border:none; border-top:1px solid #000; margin:20pt 0;">

                    <h3 style="font-weight:700; margin-bottom:15pt;">SUMMARY</h3>
                    <p style="margin-bottom:15pt; text-align:justify;">This memorandum outlines the preliminary results of your rehabilitation assessment, submissions of treatment evaluation data and rebuttal comments, and the Department's reconsideration of your treatment plan valuation, including an error in the assessment of your gambling behavior patterns.</p>

                    <p style="margin-bottom:15pt; text-align:justify;">The rehabilitation program is designed to provide comprehensive treatment for gambling addiction through structured counseling, support groups, and personalized recovery planning. Your participation requires commitment to the treatment schedule and adherence to program guidelines.</p>

                    <h4 style="font-weight:700; margin:20pt 0 10pt;">TREATMENT PROGRAM DETAILS:</h4>
                    <p style="margin-bottom:10pt;"><strong>Duration:</strong> 6 months performing interventions with follow-up sessions</p>
                    <p style="margin-bottom:10pt;"><strong>Frequency:</strong> Per-week (individual and group therapy)</p>
                    <p style="margin-bottom:10pt;"><strong>Location:</strong> Rehabilitation Center</p>
                    <p style="margin-bottom:15pt;"><strong>Cost:</strong> Covered under PAGCOR's Responsible Gaming Program</p>

                    <h4 style="font-weight:700; margin:20pt 0 10pt;">PARTICIPANT RESPONSIBILITIES:</h4>
                    <ul style="margin-bottom:15pt; padding-left:20pt;">
                        <li>Attend all scheduled therapy sessions</li>
                        <li>Complete assigned homework and recovery tasks</li>
                        <li>Maintain abstinence from all gambling activities</li>
                        <li>Participate actively in support group meetings</li>
                        <li>Follow financial management guidelines</li>
                        <li>Submit to regular progress assessments</li>
                    </ul>
                </div>
            </div>

            <!-- Signatures -->
            <div class="contract-section">
                <h3><i class="fas fa-signature me-2"></i>Signatures</h3>
                
                <?php if ($gamblerSignature): ?>
                <div class="signature-section">
                    <h4>Gambler Signature</h4>
                    <div class="d-flex align-items-center gap-3">
                        <img src="<?= htmlspecialchars($gamblerSignature['signature_data']) ?>" class="signature-img" alt="Gambler Signature">
                        <div>
                            <strong><?= htmlspecialchars($contract['gambler_name']) ?></strong><br>
                            <small class="text-muted">Signed on <?= date('F j, Y g:i A', strtotime($gamblerSignature['signed_at'])) ?></small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($familySignature && $contract['family_name']): ?>
                <div class="signature-section">
                    <h4>Family Member Signature</h4>
                    <div class="d-flex align-items-center gap-3">
                        <img src="<?= htmlspecialchars($familySignature['signature_data']) ?>" class="signature-img" alt="Family Member Signature">
                        <div>
                            <strong><?= htmlspecialchars($contract['family_name']) ?></strong><br>
                            <small class="text-muted">Signed on <?= date('F j, Y g:i A', strtotime($familySignature['signed_at'])) ?></small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Interview Remarks -->
            <?php if ($contract['interview_remarks']): ?>
            <div class="contract-section">
                <h3><i class="fas fa-sticky-note me-2"></i>Interview Remarks</h3>
                <div class="info-box">
                    <p><?= nl2br(htmlspecialchars($contract['interview_remarks'])) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    </div>
</div>

</html>
