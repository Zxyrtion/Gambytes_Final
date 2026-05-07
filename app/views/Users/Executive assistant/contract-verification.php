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
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || $user['role'] !== 'executive_assistant') {
    header("Location: " . url('app/views/auth/dashboard.php'));
    exit();
}

$full_name = $user['first_name'] . ' ' . $user['last_name'];

// Ensure required tables and columns exist
$conn->query("CREATE TABLE IF NOT EXISTS `contract_form_templates` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `uploaded_by` INT(11) NOT NULL,
    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `contract_submissions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `template_id` INT(11) NOT NULL DEFAULT 0,
    `gambler_id` INT(11) NOT NULL,
    `family_member_id` INT(11) NULL,
    `booking_id` INT(11) NULL,
    `gambler_data` LONGTEXT NULL,
    `family_data` LONGTEXT NULL,
    `gambler_sig` LONGTEXT NULL,
    `family_sig` LONGTEXT NULL,
    `status` ENUM('draft','submitted','reviewed','sent_to_parties','completed') NOT NULL DEFAULT 'draft',
    `supervisor_notes` TEXT NULL,
    `ea_verification_status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `ea_verified_by` INT(11) NULL,
    `ea_verified_at` DATETIME NULL,
    `ea_notes` TEXT NULL,
    `sent_at` DATETIME NULL,
    `submitted_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add EA columns if they don't exist yet (for existing installs)
$conn->query("ALTER TABLE `contract_submissions` ADD COLUMN IF NOT EXISTS `ea_verification_status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
$conn->query("ALTER TABLE `contract_submissions` ADD COLUMN IF NOT EXISTS `ea_verified_by` INT(11) NULL");
$conn->query("ALTER TABLE `contract_submissions` ADD COLUMN IF NOT EXISTS `ea_verified_at` DATETIME NULL");
$conn->query("ALTER TABLE `contract_submissions` ADD COLUMN IF NOT EXISTS `ea_notes` TEXT NULL");

$conn->query("CREATE TABLE IF NOT EXISTS `contract_verifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `contract_submission_id` INT(11) NOT NULL,
    `executive_assistant_id` INT(11) NOT NULL,
    `verification_status` ENUM('approved', 'rejected') NOT NULL,
    `verification_notes` TEXT NULL,
    `verified_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_contract_id` (`contract_submission_id`),
    KEY `idx_ea_id` (`executive_assistant_id`),
    KEY `idx_status` (`verification_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add columns if they don't exist yet (for existing installs)
$conn->query("ALTER TABLE `contract_verifications` ADD COLUMN IF NOT EXISTS `verification_notes` TEXT NULL");
$conn->query("ALTER TABLE `contract_verifications` ADD COLUMN IF NOT EXISTS `verified_at` DATETIME NULL");

// Get all gamblers who have any contracts
$unifiedContractsStmt = $conn->prepare("
    SELECT DISTINCT
        gu.id AS gambler_id,
        CONCAT(gu.first_name, ' ', gu.last_name) AS gambler_name,
        gu.email AS gambler_email
    FROM users gu
    WHERE gu.id IN (
        SELECT DISTINCT gambler_id FROM contract_submissions WHERE status IN ('submitted', 'sent_to_parties', 'completed')
    )
    ORDER BY gu.first_name, gu.last_name
");

$unifiedContracts = [];
if ($unifiedContractsStmt) {
    $unifiedContractsStmt->execute();
    $gamblers = $unifiedContractsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $unifiedContractsStmt->close();
    
    // For each gambler, get all their contract details
    foreach ($gamblers as $gambler) {
        $gambler_id = $gambler['gambler_id'];
        
        // Get gambler contract submission
        $gamblerContract = $conn->prepare("
            SELECT cs.id, cs.created_at, cs.family_member_id,
                   COALESCE(cv.verification_status, 'pending') AS status,
                   (SELECT COUNT(*) FROM signed_contract_documents WHERE contract_document_id = cs.id AND signer_role = 'gambler') AS has_gambler_sig,
                   (SELECT COUNT(*) FROM signed_contract_documents WHERE contract_document_id = cs.id AND signer_role = 'family') AS has_family_sig
            FROM contract_submissions cs
            LEFT JOIN contract_verifications cv ON cv.contract_submission_id = cs.id
            WHERE cs.gambler_id = ? AND cs.status IN ('submitted', 'sent_to_parties', 'completed')
            ORDER BY cs.created_at DESC LIMIT 1
        ");
        $gamblerContract->bind_param('i', $gambler_id);
        $gamblerContract->execute();
        $contractData = $gamblerContract->get_result()->fetch_assoc();
        $gamblerContract->close();
        
        // Get family member info if exists
        $family_name = null;
        $family_email = null;
        $family_signed_date = null;
        if ($contractData && $contractData['family_member_id']) {
            $familyStmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
            $familyStmt->bind_param('i', $contractData['family_member_id']);
            $familyStmt->execute();
            $familyData = $familyStmt->get_result()->fetch_assoc();
            $familyStmt->close();
            if ($familyData) {
                $family_name = $familyData['first_name'] . ' ' . $familyData['last_name'];
                $family_email = $familyData['email'];
                $family_signed_date = $contractData['created_at']; // Use contract creation date as proxy
            }
        }
        
        // Get latest date for sorting
        $latestDate = $contractData ? $contractData['created_at'] : null;
        
        $unifiedContracts[] = [
            'gambler_id' => $gambler_id,
            'gambler_name' => $gambler['gambler_name'],
            'gambler_email' => $gambler['gambler_email'],
            'gambler_contract_id' => $contractData['id'] ?? null,
            'gambler_contract_date' => $contractData['created_at'] ?? null,
            'gambler_status' => $contractData['status'] ?? null,
            'family_contract_id' => $contractData['id'] ?? null, // Same contract, different view
            'family_name' => $family_name,
            'family_email' => $family_email,
            'family_signed_date' => $family_signed_date,
            'signed_document_id' => null,
            'signed_document_type' => null,
            'signed_document_date' => null,
            'latest_date' => $latestDate
        ];
    }
    
    // Sort by latest date
    usort($unifiedContracts, function($a, $b) {
        return strtotime($b['latest_date']) - strtotime($a['latest_date']);
    });
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
        .main-content { margin-left:260px; flex:1; padding:2rem; }
        .top-navbar { background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.08); padding:1rem 1.5rem; margin-bottom:2rem; display:flex; justify-content:space-between; align-items:center; }
        .top-navbar-title { font-size:1.1rem; font-weight:600; color:#495057; }
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1.5rem; margin-bottom:2rem; }
        .stat-card { background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); padding:1.5rem; border-left:4px solid #800000; text-align:center; }
        .stat-number { font-size:2.5rem; font-weight:800; margin-bottom:.5rem; }
        .stat-number.pending { color:#ffc107; }
        .stat-number.approved { color:#28a745; }
        .stat-number.rejected { color:#dc3545; }
        .stat-label { color:#6c757d; font-size:.9rem; font-weight:600; }
        .contract-table { background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); overflow:hidden; }
        .table { margin:0; }
        .table th { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; font-weight:600; border:none; padding:1rem; }
        .table td { padding:1rem; vertical-align:middle; border-bottom:1px solid #f8f9fa; }
        .table tbody tr:hover { background:#f8f9fa; }
        .badge-pending { background:#fff3cd; color:#856404; padding:.35rem .75rem; border-radius:20px; font-size:.75rem; font-weight:700; }
        .badge-approved { background:#d1e7dd; color:#0f5132; padding:.35rem .75rem; border-radius:20px; font-size:.75rem; font-weight:700; }
        .badge-rejected { background:#f8d7da; color:#842029; padding:.35rem .75rem; border-radius:20px; font-size:.75rem; font-weight:700; }
        .action-buttons { display:flex; gap:.5rem; }
        .btn-sm { padding:.4rem .8rem; font-size:.8rem; border-radius:8px; }
        .btn-view { background:#007bff; color:#fff; border:none; }
        .btn-view:hover { background:#0056b3; }
        .btn-approve { background:#28a745; color:#fff; border:none; }
        .btn-approve:hover { background:#1e7e34; }
        .btn-reject { background:#dc3545; color:#fff; border:none; }
        .btn-reject:hover { background:#c82333; }
        .empty-state { text-align:center; padding:3rem; color:#6c757d; }
        .empty-state i { font-size:3rem; color:#dee2e6; margin-bottom:1rem; }
        .nav-tabs { border-bottom:2px solid #800000; }
        .nav-tabs .nav-link { border:none; color:#6c757d; font-weight:600; padding:1rem 1.5rem; }
        .nav-tabs .nav-link.active { background:#800000; color:#fff; border-radius:10px 10px 0 0; }
        .nav-tabs .nav-link:hover { color:#800000; }
        .tab-content { margin-top:2rem; }
        /* Fix modal z-index stacking issue */
        .modal { z-index: 1055 !important; }
        .modal-backdrop { z-index: 1050 !important; }
        .modal-dialog { z-index: 1060 !important; }
        .modal-content { position:relative; z-index: 1065 !important; pointer-events:all !important; }
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
                <i class="fas fa-file-contract me-2"></i>Contract Verification
            </h1>
            <p style="color:#6c757d; margin:0;">Review and verify rehabilitation contracts</p>
        </div>

        <!-- Unified Contract Table -->
        <div class="contract-table">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="width: 25%;"><i class="fas fa-user me-2"></i> Gambler Name</th>
                        <th style="width: 25%;"><i class="fas fa-users me-2"></i> Family of Gambler</th>
                        <th style="width: 20%;"><i class="fas fa-calendar me-2"></i> Signed Date</th>
                        <th style="width: 30%;"><i class="fas fa-cog me-2"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($unifiedContracts)): ?>
                        <tr>
                            <td colspan="4" class="text-center" style="padding: 3rem; color: #6c757d;">
                                <i class="fas fa-inbox fa-3x mb-3" style="display: block; opacity: 0.3;"></i>
                                <h4 style="color: #495057; margin-bottom: 0.5rem;">No Contracts Found</h4>
                                <p style="font-size: 1rem; margin-bottom: 0;">No contracts have been submitted yet.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($unifiedContracts as $contract): ?>
                        <tr style="border-left: 4px solid #f8f9fa;">
                            <td style="padding: 1.25rem; vertical-align: middle;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div>
                                        <div style="font-weight: 600; color: #212529; font-size: 0.95rem;">
                                            <i class="fas fa-user-circle me-1" style="color: #800000;"></i>
                                            <?= htmlspecialchars($contract['gambler_name']) ?>
                                        </div>
                                        <?php if ($contract['gambler_contract_id']): ?>
                                            <div style="margin-top: 0.5rem;">
                                                <button class="btn btn-sm btn-view" style="background: #007bff; border: none; border-radius: 8px; padding: 0.5rem 1rem; font-size: 0.8rem; cursor: pointer; transition: all 0.2s;" 
                                                        onmouseover="this.style.background='#0056b3'" 
                                                        onmouseout="this.style.background='#007bff'" 
                                                        onclick="viewContract(<?= $contract['gambler_contract_id'] ?>, 'gambler')">
                                                    <i class="fas fa-eye me-1"></i> View Contract
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1.25rem; vertical-align: middle;">
                                <?php if ($contract['family_name']): ?>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div>
                                            <div style="font-weight: 600; color: #212529; font-size: 0.95rem;">
                                                <i class="fas fa-users me-1" style="color: #28a745;"></i>
                                                <?= htmlspecialchars($contract['family_name']) ?>
                                            </div>
                                            <div style="margin-top: 0.5rem;">
                                                <button class="btn btn-sm btn-view" style="background: #6f42c1; border: none; border-radius: 8px; padding: 0.5rem 1rem; font-size: 0.8rem; cursor: pointer; transition: all 0.2s;" 
                                                            onmouseover="this.style.background='#5a3291'" 
                                                            onmouseout="this.style.background='#6f42c1'" 
                                                            onclick="viewContract(<?= $contract['family_contract_id'] ?>, 'family')">
                                                    <i class="fas fa-eye me-1"></i> View Contract
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="text-align: center; color: #6c757d; font-style: italic; padding: 1rem;">
                                        <i class="fas fa-minus-circle me-1"></i>
                                        No Family Member
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1.25rem; vertical-align: middle;">
                                <div style="font-weight: 500; color: #495057; font-size: 0.9rem;">
                                    <?php 
                                    $signedDate = $contract['family_signed_date'] ?? $contract['gambler_contract_date'] ?? $contract['signed_document_date'];
                                    echo $signedDate ? date('M j, Y', strtotime($signedDate)) : '—';
                                    ?>
                                </div>
                            </td>
                            <td style="padding: 1.25rem; vertical-align: middle;">
                                <?php if ($contract['gambler_contract_id']): ?>
                                    <?php if (($contract['gambler_status'] ?? 'pending') === 'pending'): ?>
                                        <button class="btn btn-sm btn-approve" style="background: linear-gradient(135deg, #28a745, #20c997); color: #fff; border: none; border-radius: 8px; padding: 0.6rem 1.2rem; font-weight: 600; font-size: 0.9rem; cursor: pointer; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2); transition: all 0.2s;" 
                                                onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(40, 167, 69, 0.3)'" 
                                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(40, 167, 69, 0.2)'" 
                                                onclick="verifyContract(<?= $contract['gambler_contract_id'] ?>, 'approved')">
                                            <i class="fas fa-check-circle me-1"></i> Verify
                                        </button>
                                    <?php elseif (($contract['gambler_status'] ?? '') === 'approved'): ?>
                                        <span class="badge" style="background: #d1e7dd; color: #0f5132; padding: 0.4rem 0.8rem; border-radius: 20px; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                                            <i class="fas fa-check-circle"></i> Verified
                                        </span>
                                    <?php elseif (($contract['gambler_status'] ?? '') === 'rejected'): ?>
                                        <span class="badge" style="background: #f8d7da; color: #842029; padding: 0.4rem 0.8rem; border-radius: 20px; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                                            <i class="fas fa-times-circle"></i> Rejected
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge" style="background: #d1e7dd; color: #0f5132; padding: 0.4rem 0.8rem; border-radius: 20px; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                                        <i class="fas fa-signature"></i> Signed
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══ APPROVE MODAL ═══ -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content" style="border-radius:20px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">
      <div class="modal-header" style="background:linear-gradient(135deg,#28a745,#1a6b2e);color:#fff;border:none;padding:1rem 1.25rem;">
        <div style="display:flex;align-items:center;gap:.75rem;">
          <div style="width:40px;height:40px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;">
            <i class="fas fa-check-circle"></i>
          </div>
          <div>
            <h5 class="modal-title fw-bold mb-0" style="font-size:1.1rem;">Approve Contract</h5>
            <small style="opacity:.85;font-size:.8rem;">This action will notify the gambler</small>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:1rem;">
        <div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:12px;padding:0.75rem 1rem;margin-bottom:1rem;display:flex;align-items:flex-start;gap:.75rem;">
          <i class="fas fa-info-circle" style="color:#16a34a;margin-top:2px;"></i>
          <span style="font-size:.9rem;color:#166534;">You are about to <strong>approve</strong> this rehabilitation contract. The gambler will receive a notification.</span>
        </div>
        <label class="form-label fw-semibold" style="font-size:.88rem;color:#374151;margin-bottom:0.5rem;">
          Feedback / Notes <span style="color:#9ca3af;font-weight:400;">(optional)</span>
        </label>
        <textarea id="approveFeedback" class="form-control" rows="3"
          placeholder="e.g. All documents are in order. Welcome to the program!"
          style="border-radius:10px;border:1.5px solid #d1d5db;font-size:.88rem;resize:none;"></textarea>
      </div>
      <div class="modal-footer" style="border:none;padding:0.75rem 1.25rem 1rem;gap:.75rem;justify-content:flex-end;">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"
          style="border-radius:10px;padding:.55rem 1.25rem;font-weight:600;border:1.5px solid #d1d5db;">
          Cancel
        </button>
        <button type="button" id="confirmApproveBtn"
          style="background:linear-gradient(135deg,#28a745,#1a6b2e);color:#fff;border:none;border-radius:10px;padding:.55rem 1.5rem;font-weight:700;font-size:.9rem;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;">
          <i class="fas fa-check"></i> Confirm Approve
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ REJECT MODAL ═══ -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content" style="border-radius:20px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">
      <div class="modal-header" style="background:linear-gradient(135deg,#dc3545,#991b1b);color:#fff;border:none;padding:1rem 1.25rem;">
        <div style="display:flex;align-items:center;gap:.75rem;">
          <div style="width:40px;height:40px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;">
            <i class="fas fa-times-circle"></i>
          </div>
          <div>
            <h5 class="modal-title fw-bold mb-0" style="font-size:1.1rem;">Reject Contract</h5>
            <small style="opacity:.85;font-size:.8rem;">The gambler will be notified with your reason</small>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:1rem;">
        <div style="background:#fff5f5;border:1.5px solid #fecaca;border-radius:12px;padding:0.75rem 1rem;margin-bottom:1rem;display:flex;align-items:flex-start;gap:.75rem;">
          <i class="fas fa-exclamation-triangle" style="color:#dc2626;margin-top:2px;"></i>
          <span style="font-size:.9rem;color:#991b1b;">You are about to <strong>reject</strong> this contract. Please provide a clear reason so the gambler knows what to fix.</span>
        </div>
        <label class="form-label fw-semibold" style="font-size:.88rem;color:#374151;margin-bottom:0.5rem;">
          Reason for Rejection <span style="color:#dc3545;">*</span>
        </label>
        <textarea id="rejectFeedback" class="form-control" rows="3"
          placeholder="e.g. Signature is missing. Please re-submit with a complete signature."
          style="border-radius:10px;border:1.5px solid #d1d5db;font-size:.88rem;resize:none;"></textarea>
        <div id="rejectFeedbackError" style="color:#dc3545;font-size:.82rem;margin-top:.4rem;display:none;">
          <i class="fas fa-exclamation-circle me-1"></i>Please provide a reason for rejection.
        </div>
      </div>
      <div class="modal-footer" style="border:none;padding:0.75rem 1.25rem 1rem;gap:.75rem;justify-content:flex-end;">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"
          style="border-radius:10px;padding:.55rem 1.25rem;font-weight:600;border:1.5px solid #d1d5db;">
          Cancel
        </button>
        <button type="button" id="confirmRejectBtn"
          style="background:linear-gradient(135deg,#dc3545,#991b1b);color:#fff;border:none;border-radius:10px;padding:.55rem 1.5rem;font-weight:700;font-size:.9rem;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;">
          <i class="fas fa-times"></i> Confirm Reject
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let _pendingContractId = null;
let _pendingAction     = null;

function viewContract(contractId, contractType) {
    if (contractType === 'gambler') {
        window.location.href = '/GAMBYTES_Final/app/views/Users/Executive Assistant/view-contract.php?id=' + contractId;
    } else if (contractType === 'family') {
        window.location.href = '/GAMBYTES_Final/app/views/Users/Executive Assistant/view-family-contract.php?id=' + contractId;
    } else if (contractType === 'signed_document') {
        window.location.href = '/GAMBYTES_Final/app/views/Users/Executive Assistant/view-signed-document.php?id=' + contractId;
    }
}

function verifyContract(contractId, action) {
    _pendingContractId = contractId;
    _pendingAction     = action;

    if (action === 'approved') {
        document.getElementById('approveFeedback').value = '';
        const approveEl = document.getElementById('approveModal');
        const approveModal = bootstrap.Modal.getInstance(approveEl) || new bootstrap.Modal(approveEl, {backdrop: true, keyboard: true});
        approveModal.show();
    } else {
        document.getElementById('rejectFeedback').value = '';
        document.getElementById('rejectFeedbackError').style.display = 'none';
        const rejectEl = document.getElementById('rejectModal');
        const rejectModal = bootstrap.Modal.getInstance(rejectEl) || new bootstrap.Modal(rejectEl, {backdrop: true, keyboard: true});
        rejectModal.show();
    }
}

document.getElementById('confirmApproveBtn').addEventListener('click', function () {
    const feedback = document.getElementById('approveFeedback').value.trim();
    submitVerification(_pendingContractId, 'approved', feedback, this);
});

document.getElementById('confirmRejectBtn').addEventListener('click', function () {
    const feedback = document.getElementById('rejectFeedback').value.trim();
    if (!feedback) {
        document.getElementById('rejectFeedbackError').style.display = 'block';
        return;
    }
    document.getElementById('rejectFeedbackError').style.display = 'none';
    submitVerification(_pendingContractId, 'rejected', feedback, this);
});

function submitVerification(contractId, action, notes, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';

    fetch('/GAMBYTES_Final/api/verify_contract.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ contract_id: contractId, action: action, notes: notes })
    })
    .then(r => r.json())
    .then(data => {
        // Close whichever modal is open
        const modalEl = document.getElementById(action === 'approved' ? 'approveModal' : 'rejectModal');
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) { modalInstance.hide(); }

        if (data.success) {
            showToast(action === 'approved'
                ? '✅ Contract approved and gambler notified!'
                : '❌ Contract rejected and gambler notified.', action === 'approved' ? 'success' : 'danger');
            setTimeout(() => location.reload(), 1800);
        } else {
            alert('Error: ' + (data.message || 'Failed to verify contract'));
            btn.disabled = false;
            btn.innerHTML = action === 'approved'
                ? '<i class="fas fa-check me-1"></i> Confirm Approve'
                : '<i class="fas fa-times me-1"></i> Confirm Reject';
        }
    })
    .catch(() => {
        alert('Network error. Please try again.');
        btn.disabled = false;
    });
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed;top:1.5rem;right:1.5rem;z-index:9999;background:${type==='success'?'#28a745':'#dc3545'};color:#fff;padding:.85rem 1.5rem;border-radius:12px;font-weight:700;font-size:.95rem;box-shadow:0 4px 20px rgba(0,0,0,.2);transition:opacity .4s`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 400); }, 2500);
}
</script>

</body>
</html>
