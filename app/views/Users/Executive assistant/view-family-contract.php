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
$contract_id = (int)($_GET['id'] ?? 0);

if (!$contract_id) {
    header("Location: /GAMBYTES_Final/app/views/Users/Executive Assistant/contract-verification.php");
    exit();
}

// Get user data for sidebar
$user = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user->bind_param('i', $user_id);
$user->execute();
$user = $user->get_result()->fetch_assoc();

// Get contract submission with family info
$contract = $conn->prepare("
    SELECT cs.*, 
           CONCAT(fu.first_name, ' ', fu.last_name) AS family_name,
           fu.email AS family_email,
           COALESCE(CONCAT(gu.first_name, ' ', gu.last_name), 'Unknown') AS gambler_name,
           gu.email AS gambler_email
    FROM contract_submissions cs
    JOIN users gu ON gu.id = cs.gambler_id
    LEFT JOIN users fu ON fu.id = cs.family_member_id
    WHERE cs.id = ?
");

if ($contract === false) {
    die("Error preparing family contract query: " . $conn->error);
}

$contract->bind_param('i', $contract_id);
$contract->execute();
$contract = $contract->get_result()->fetch_assoc();

if (!$contract || !$contract['family_member_id']) {
    header("Location: /GAMBYTES_Final/app/views/Users/Executive Assistant/contract-verification.php");
    exit();
}

// Get family signature from signed_contract_documents
$familySignature = $conn->prepare("
    SELECT signature_data, signed_at 
    FROM signed_contract_documents 
    WHERE contract_document_id = ? AND signer_role = 'family'
    ORDER BY signed_at DESC LIMIT 1
");
$familySignature->bind_param('i', $contract_id);
$familySignature->execute();
$familySignature = $familySignature->get_result()->fetch_assoc();

$full_name = $user['first_name'] . ' ' . $user['last_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Family Contract View – Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
    <style>
        .main-content { margin-left:260px; flex:1; padding:2rem; }
        .top-navbar { background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.08); padding:1rem 1.5rem; margin-bottom:2rem; display:flex; justify-content:space-between; align-items:center; }
        .top-navbar-title { font-size:1.1rem; font-weight:600; color:#495057; }
        .contract-card { background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); overflow:hidden; margin-bottom:1.5rem; }
        .contract-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:1.1rem 1.5rem; font-weight:700; font-size:1rem; display:flex; align-items:center; gap:.6rem; }
        .contract-body { padding:1.5rem; }
        .info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:.75rem; margin-bottom:1.25rem; }
        .info-box { padding:.75rem 1rem; background:#f8f9fa; border-radius:10px; border-left:4px solid #800000; }
        .info-box .lbl { font-size:.72rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
        .info-box .val { font-weight:700; color:#212529; margin-top:2px; font-size:.92rem; }
        .signature-section { margin-top:2rem; padding:1.5rem; background:#f8f9fa; border-radius:12px; border:2px dashed #dee2e6; }
        .signature-img { max-width:200px; border:1px solid #ddd; border-radius:8px; margin-top:1rem; }
        .agreement-section { margin-top:2rem; padding:2rem; background:#fff; border-radius:12px; border:1px solid #dee2e6; }
        .agreement-section h3 { color:#800000; font-weight:700; margin-bottom:1.5rem; border-bottom:2px solid #800000; padding-bottom:.5rem; }
        .agreement-text { font-family:'Times New Roman', serif; font-size:12pt; line-height:1.6; color:#212529; }
        .btn-back { background:#6c757d; color:#fff; border:none; border-radius:10px; padding:.6rem 1.5rem; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:.5rem; }
        .btn-back:hover { background:#5a6268; }
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
        <div style="margin-bottom:1.75rem;">
            <h1 style="color:#800000; font-size:1.8rem; font-weight:800; margin:0;">
                <i class="fas fa-users me-2"></i>Family Contract View
            </h1>
            <p style="color:#6c757d; margin:0;">View family member support agreement</p>
        </div>

        <!-- Contract Details -->
        <div class="contract-card">
            <div class="contract-header">
                <i class="fas fa-file-contract"></i>
                Family Support Agreement
            </div>
            <div class="contract-body">
                <div class="info-grid">
                    <div class="info-box">
                        <div class="lbl">Family Member</div>
                        <div class="val"><?= htmlspecialchars($contract['family_name']) ?></div>
                    </div>
                    <div class="info-box">
                        <div class="lbl">Email</div>
                        <div class="val"><?= htmlspecialchars($contract['family_email']) ?></div>
                    </div>
                    <div class="info-box">
                        <div class="lbl">Linked Gambler</div>
                        <div class="val"><?= htmlspecialchars($contract['gambler_name']) ?></div>
                    </div>
                    <div class="info-box">
                        <div class="lbl">Signed Date</div>
                        <div class="val"><?= $familySignature ? date('F j, Y', strtotime($familySignature['signed_at'])) : 'Not signed yet' ?></div>
                    </div>
                </div>

                <?php if ($familySignature): ?>
                <div class="signature-section">
                    <h5 style="margin-bottom:1rem; color:#495057;">
                        <i class="fas fa-signature me-2"></i>Digital Signature
                    </h5>
                    <img src="<?= htmlspecialchars($familySignature['signature_data']) ?>"
                         alt="Family Member Signature"
                         class="signature-img">
                </div>
                <?php endif; ?>

                <!-- Agreement Document -->
                <div class="agreement-section">
                    <h3><i class="fas fa-file-alt me-2"></i>Family Support Agreement</h3>
                    <div class="agreement-text">
                        <div style="text-align:right; margin-bottom:20pt;"><?= date('F j, Y') ?></div>

                        <table style="width:100%; border-collapse:collapse; margin-bottom:20pt;">
                            <tr>
                                <td style="padding:10pt 0; vertical-align:top; width:150px; font-weight:700;">MEMORANDUM TO:</td>
                                <td style="padding:10pt 0; vertical-align:top;">
                                    <?= htmlspecialchars($contract['family_name']) ?><br>
                                    Family Support Member<br>
                                    Gambytes Recovery Program
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:10pt 0; vertical-align:top; font-weight:700;">FROM:</td>
                                <td style="padding:10pt 0; vertical-align:top;">
                                    Rehabilitation Services Department<br>
                                    Philippine Amusement and Gaming Corporation
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:10pt 0; vertical-align:top; font-weight:700;">CASE:</td>
                                <td style="padding:10pt 0; vertical-align:top;">Family Support Agreement for Gambling Addiction Treatment</td>
                            </tr>
                            <tr>
                                <td style="padding:10pt 0; vertical-align:top; font-weight:700;">SUBJECT:</td>
                                <td style="padding:10pt 0; vertical-align:top;">
                                    Terms and Conditions for Family Support<br>
                                    in the Gambytes Rehabilitation Program
                                </td>
                            </tr>
                        </table>

                        <hr style="border:none; border-top:1px solid #000; margin:20pt 0;">

                        <h3 style="font-weight:700; margin-bottom:15pt;">SUMMARY</h3>
                        <p style="margin-bottom:15pt; text-align:justify;">This memorandum outlines the family support agreement for participation in the gambling addiction rehabilitation program. As a family member, your role is crucial in supporting the recovery process of <?= htmlspecialchars($contract['gambler_name']) ?> through emotional support, accountability, and active participation in the treatment program.</p>

                        <p style="margin-bottom:15pt; text-align:justify;">The family support program is designed to provide comprehensive support for both the individual recovering from gambling addiction and their family members. Your participation requires commitment to attending support sessions and following program guidelines to create a supportive environment for recovery.</p>

                        <h4 style="font-weight:700; margin:20pt 0 10pt;">SUPPORT PROGRAM DETAILS:</h4>
                        <p style="margin-bottom:10pt;"><strong>Duration:</strong> 6 months concurrent with the participant's rehabilitation program</p>
                        <p style="margin-bottom:10pt;"><strong>Frequency:</strong> Monthly family counseling sessions</p>
                        <p style="margin-bottom:10pt;"><strong>Location:</strong> Rehabilitation Center</p>
                        <p style="margin-bottom:15pt;"><strong>Cost:</strong> Covered under PAGCOR's Responsible Gaming Program</p>

                        <h4 style="font-weight:700; margin:20pt 0 10pt;">FAMILY MEMBER RESPONSIBILITIES:</h4>
                        <ul style="margin-bottom:15pt; padding-left:20pt;">
                            <li>Attend scheduled family counseling sessions</li>
                            <li>Provide emotional support and encouragement to the recovering family member</li>
                            <li>Learn about gambling addiction and recovery strategies</li>
                            <li>Participate in family support group meetings</li>
                            <li>Follow financial management guidelines to support recovery</li>
                            <li>Maintain regular communication with rehabilitation staff</li>
                            <li>Create a supportive home environment free from gambling triggers</li>
                        </ul>

                        <h4 style="font-weight:700; margin:20pt 0 10pt;">CONFIDENTIALITY:</h4>
                        <p style="margin-bottom:15pt; text-align:justify;">All information shared during counseling sessions and program activities is confidential and protected by privacy laws. Family members are expected to maintain confidentiality regarding the participant's treatment and recovery journey.</p>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:2rem;">
            <a href="/GAMBYTES_Final/app/views/Users/Executive Assistant/contract-verification.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Contracts
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
