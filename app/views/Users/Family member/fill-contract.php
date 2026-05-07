<?php
require_once __DIR__ . '/../../../../includes/session_config.php';
require_once __DIR__ . '/../../../../includes/url_helper.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . url('app/views/auth/login.php')); exit(); }
require_once __DIR__ . '/../../../core/Database.php';
$db = new Database(); $conn = $db->connect();
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$user || $user['role'] !== 'family') { header("Location: " . url('app/views/auth/dashboard.php')); exit(); }
$full_name = $user['first_name'] . ' ' . $user['last_name'];
$submission_id = (int)($_GET['submission_id'] ?? 0);
if (!$submission_id) { header("Location: /GAMBYTES_Final/app/views/Users/Family member/my-contracts.php"); exit(); }

// Verify the family member is linked to this contract submission
$verifyStmt = $conn->prepare("SELECT cs.id, cs.status, cs.gambler_id FROM contract_submissions cs WHERE cs.id = ? AND cs.family_member_id = ? LIMIT 1");
$verifyStmt->bind_param('ii', $submission_id, $user_id);
$verifyStmt->execute();
$contractVerify = $verifyStmt->get_result()->fetch_assoc();
$verifyStmt->close();

if (!$contractVerify) {
    // Family member not linked to this contract - redirect back
    header("Location: /GAMBYTES_Final/app/views/Users/Family member/my-contracts.php");
    exit();
}

// ── Load submission data server-side ─────────────────────────────────────────
$submission_data = null;
$sd = $conn->prepare(
    "SELECT cs.id, cs.status, cs.ea_verification_status, cs.ea_notes,
            cs.family_data,
            'Rehabilitation Agreement' AS template_title,
            '' AS template_filename,
            CONCAT(gu.first_name,' ',gu.last_name) AS gambler_name, gu.email AS gambler_email,
            scd_family.signature_data AS family_sig
     FROM contract_submissions cs
     JOIN users gu ON gu.id = cs.gambler_id
     LEFT JOIN signed_contract_documents scd_family ON scd_family.contract_document_id = cs.id AND scd_family.signer_role = 'family'
     WHERE cs.id = ? AND cs.family_member_id = ?"
);
if ($sd) {
    $sd->bind_param('ii', $submission_id, $user_id);
    $sd->execute();
    $row = $sd->get_result()->fetch_assoc();
    $sd->close();
    if ($row) {
        $row['template_url'] = '';
        $submission_data = $row;
    }
}
if (!$submission_data) {
    header("Location: /GAMBYTES_Final/app/views/Users/Family member/my-contracts.php");
    exit();
}

// ── Load policies ─────────────────────────────────────────────────────────────
$pRes = $conn->query("SELECT * FROM policy_files ORDER BY doc_category, uploaded_at DESC");
$grouped_policies = [];
if ($pRes) {
    while ($pRow = $pRes->fetch_assoc()) {
        $pRow['url'] = '/GAMBYTES_Final/uploads/policies/' . rawurlencode($pRow['filename']);
        $grouped_policies[$pRow['doc_category']][] = $pRow;
    }
}

// Family is locked out only if they've already signed (family_sig is not empty)
$is_locked = !empty($submission_data['family_sig']) || $submission_data['status'] === 'completed';

// Parse existing family_data if any
$family_info = [];
if (!empty($submission_data['family_data'])) {
    $family_info = json_decode($submission_data['family_data'], true) ?? [];
}
$ea_status = $submission_data['ea_verification_status'] ?? 'pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Contract Form – Gambytes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
<style>
.fc-card{background:#fff;border-radius:16px;box-shadow:0 2px 15px rgba(0,0,0,.08);overflow:hidden;margin-bottom:1.5rem}
.fc-card-header{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;padding:1rem 1.5rem;font-weight:700;font-size:1rem;display:flex;align-items:center;gap:.6rem}
.fc-card-body{padding:1.75rem}
.sig-canvas-wrap{border:2px solid #dee2e6;border-radius:10px;background:#f8f9fa;overflow:hidden;max-width:500px;margin:0 auto}
.sig-canvas-wrap canvas{display:block;cursor:crosshair;touch-action:none;width:100%;height:200px}
.btn-maroon{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;border:none;border-radius:10px;padding:.65rem 1.75rem;font-weight:700;font-size:.95rem;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:.5rem;text-decoration:none}
.btn-maroon:hover{opacity:.88;color:#fff}
.btn-clear{background:#6c757d;color:#fff;border:none;border-radius:8px;padding:.4rem 1rem;font-size:.82rem;font-weight:600;cursor:pointer}
.policy-item{border-left:4px solid #800000;padding:.85rem 1.25rem;margin-bottom:.75rem;background:#fafafa;border-radius:0 10px 10px 0;display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap}
.policy-item-title{font-weight:700;font-size:.95rem;color:#212529;margin-bottom:.2rem}
.policy-item-meta{font-size:.8rem;color:#6c757d}
.badge-cat{display:inline-block;padding:.2rem .65rem;border-radius:20px;font-size:.72rem;font-weight:700;background:#e0e7ff;color:#3730a3}
.section-divider{border:none;border-top:3px solid #f0f0f0;margin:2rem 0}
.top-navbar{background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);padding:.85rem 1.5rem;display:flex;align-items:center;justify-content:space-between;margin-bottom:1.75rem}
.top-navbar-title{font-weight:700;font-size:1rem;color:#800000}
.notif-bell-wrap{position:relative}
.notif-bell-btn{background:linear-gradient(135deg,#800000,#5c0000);border:none;color:#fff;width:40px;height:40px;border-radius:10px;cursor:pointer;font-size:1rem;position:relative;transition:.2s;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(128,0,0,.3)}
.notif-badge{position:absolute;top:-6px;right:-6px;background:#ffc107;color:#000;font-size:.65rem;font-weight:800;min-width:18px;height:18px;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px;border:2px solid #fff}
.notif-dropdown{display:none;position:absolute;right:0;top:calc(100% + 8px);width:300px;background:#fff;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.15);z-index:9999;overflow:hidden}
.notif-dropdown.open{display:block}
.notif-dropdown-header{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;padding:.75rem 1rem;display:flex;justify-content:space-between;align-items:center;font-weight:700;font-size:.9rem}
.notif-mark-btn{background:rgba(255,255,255,.2);border:none;color:#fff;font-size:.75rem;padding:.25rem .6rem;border-radius:6px;cursor:pointer}
.notif-item{padding:.75rem 1rem;border-bottom:1px solid #f0f0f0;font-size:.85rem}
.notif-item:last-child{border-bottom:none}
.notif-item strong{display:block;color:#343a40}
.notif-item span{color:#6c757d;font-size:.78rem}
.notif-empty{padding:1.5rem 1rem;text-align:center;color:#6c757d;font-size:.85rem}
</style>
</head>
<body>
<div class="dashboard-container">
<div class="sidebar">
<div class="sidebar-header">
<div class="sidebar-logo"><img src="/GAMBYTES_Final/public/images/Logo.png" alt="Logo"><span>Gambytes</span></div>
<div class="sidebar-user">
<div class="user-name"><i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($full_name) ?></div>
<div class="user-role">Family</div>
</div>
</div>
<ul class="sidebar-menu">
<li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Family member/my-contracts.php" class="active"><i class="fas fa-file-contract"></i> My Contracts</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Family member/parental-control.php"><i class="fas fa-shield-alt"></i> Parental Control</a></li>
<li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
<div class="menu-divider"></div>
<li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul>
</div>
<div class="main-content">
<div class="top-navbar">
<span class="top-navbar-title">Family Portal</span>
<div class="notif-bell-wrap" id="notifWrap">
<button type="button" class="notif-bell-btn" onclick="toggleNotifDropdown()">
<i class="fas fa-bell"></i><span class="notif-badge" id="notifBadge" style="display:none">0</span>
</button>
<div class="notif-dropdown" id="notifDropdown">
<div class="notif-dropdown-header"><span><i class="fas fa-bell me-1"></i> Notifications</span><button onclick="markAllSeen()" class="notif-mark-btn">Mark all read</button></div>
<div id="notifList"><div class="notif-empty">No notifications</div></div>
</div>
</div>
</div>

<div style="margin-bottom:1.75rem">
<h1 style="color:#800000;font-size:1.8rem;font-weight:800;margin:0"><i class="fas fa-file-contract me-2"></i>Rehabilitation Contract</h1>
<p style="color:#6c757d;margin:.25rem 0 0">Review the policies and sign the contract form below.</p>
</div>

<!-- ═══ SECTION 1: POLICIES ═══ -->
<div class="fc-card">
<div class="fc-card-header"><i class="fas fa-book-open"></i> Section 1 – Policies &amp; Guidelines</div>
<div class="fc-card-body">
<?php if (empty($grouped_policies)): ?>
<div style="text-align:center;padding:2rem;color:#6c757d">
<i class="fas fa-folder-open fa-2x mb-2" style="opacity:.4;display:block"></i>
<p style="font-size:.9rem">No policies have been uploaded by the supervisor yet.</p>
</div>
<?php else: ?>
<?php foreach ($grouped_policies as $category => $docs): ?>
<div style="margin-bottom:1.5rem">
<div style="font-weight:700;font-size:.88rem;color:#800000;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.75rem;padding-bottom:.4rem;border-bottom:2px solid #f0f0f0">
<i class="fas fa-folder me-1"></i><?= htmlspecialchars($category) ?>
</div>
<?php foreach ($docs as $doc): ?>
<div class="policy-item">
<div style="flex:1;min-width:0">
<div class="policy-item-title"><?= htmlspecialchars($doc['doc_title']) ?></div>
<?php if ($doc['description']): ?><div class="policy-item-meta"><?= htmlspecialchars($doc['description']) ?></div><?php endif; ?>
<div style="margin-top:.35rem">
<span class="badge-cat"><?= htmlspecialchars($doc['doc_type']) ?></span>
<span style="font-size:.75rem;color:#6c757d;margin-left:.5rem"><?= date('M j, Y', strtotime($doc['uploaded_at'])) ?></span>
</div>
</div>
<div style="display:flex;gap:.5rem;flex-shrink:0">
<a href="<?= htmlspecialchars($doc['url']) ?>" target="_blank" style="background:#fff;border:1.5px solid #0d6efd;color:#0d6efd;padding:.4rem .9rem;border-radius:8px;font-size:.82rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.35rem"><i class="fas fa-eye"></i> View</a>
<a href="<?= htmlspecialchars($doc['url']) ?>" download="<?= htmlspecialchars($doc['original_name']) ?>" style="background:#fff;border:1.5px solid #6c757d;color:#6c757d;padding:.4rem .9rem;border-radius:8px;font-size:.82rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.35rem"><i class="fas fa-download"></i> Download</a>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>

<hr class="section-divider">

<!-- ═══ SECTION 2: CONTRACT ═══ -->
<div style="margin-bottom:1.25rem">
<h2 style="color:#800000;font-size:1.35rem;font-weight:800;margin:0"><i class="fas fa-pen-nib me-2"></i>Section 2 – Contract Form</h2>
<p style="color:#6c757d;margin:.2rem 0 0;font-size:.9rem">Review the contract and sign below as the family member.</p>
</div>

<!-- Gambler info -->
<div class="fc-card">
<div class="fc-card-header"><i class="fas fa-user"></i> Gambler Information</div>
<div class="fc-card-body">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
<div style="background:#f8f9fa;border-radius:8px;padding:.65rem .85rem;border-left:3px solid #800000">
<div style="font-size:.72rem;color:#6c757d;font-weight:600;text-transform:uppercase">Gambler Name</div>
<div style="font-weight:700;color:#212529"><?= htmlspecialchars($submission_data['gambler_name'] ?? '-') ?></div>
</div>
<div style="background:#f8f9fa;border-radius:8px;padding:.65rem .85rem;border-left:3px solid #800000">
<div style="font-size:.72rem;color:#6c757d;font-weight:600;text-transform:uppercase">Email</div>
<div style="font-weight:700;color:#212529"><?= htmlspecialchars($submission_data['gambler_email'] ?? '-') ?></div>
</div>
</div>
</div>
</div>

<?php if (!empty($submission_data['ea_notes'])): ?>
<div style="background:#fff8e1;border-left:4px solid #ffc107;border-radius:10px;padding:.85rem 1.1rem;font-size:.88rem;color:#5a4a00;margin-bottom:1.25rem;display:flex;align-items:flex-start;gap:.75rem">
<i class="fas fa-sticky-note" style="color:#ffc107;margin-top:.1rem;flex-shrink:0"></i>
<div><strong>Notes:</strong> <?= htmlspecialchars($submission_data['ea_notes']) ?></div>
</div>
<?php endif; ?>

<!-- Contract PDF embed -->
<div class="fc-card">
<div class="fc-card-header"><i class="fas fa-file-pdf"></i> <?= htmlspecialchars($submission_data['template_title']) ?></div>
<div class="fc-card-body" style="padding:0">
<?php
if (empty($submission_data['template_filename'])):
    // No template uploaded - show default agreement text
?>
<div style="padding:2rem;background:#fff">
    <h3 style="text-align:center;color:#800000;margin-bottom:1.5rem">REHABILITATION SUPPORT AGREEMENT</h3>
    
    <p style="margin-bottom:1rem;line-height:1.6">This agreement is made between:</p>
    
    <div style="background:#f8f9fa;padding:1rem;border-radius:8px;margin-bottom:1.5rem">
        <p style="margin:0"><strong>Gambler:</strong> <?= htmlspecialchars($submission_data['gambler_name']) ?></p>
        <p style="margin:.5rem 0 0"><strong>Family Member:</strong> <?= htmlspecialchars($full_name) ?></p>
    </div>
    
    <h4 style="color:#800000;margin-top:1.5rem">1. PURPOSE</h4>
    <p style="line-height:1.6">This agreement establishes the terms and conditions for the rehabilitation treatment program for gambling addiction. The family member agrees to support the gambler throughout the rehabilitation process.</p>
    
    <h4 style="color:#800000;margin-top:1.5rem">2. FAMILY MEMBER RESPONSIBILITIES</h4>
    <ul style="line-height:1.8">
        <li>Provide emotional and moral support throughout the treatment</li>
        <li>Attend family counseling sessions as required</li>
        <li>Assist in monitoring the gambler's progress</li>
        <li>Maintain confidentiality of treatment information</li>
        <li>Cooperate with the rehabilitation center staff</li>
    </ul>
    
    <h4 style="color:#800000;margin-top:1.5rem">3. GAMBLER RESPONSIBILITIES</h4>
    <ul style="line-height:1.8">
        <li>Attend all scheduled treatment sessions</li>
        <li>Follow the treatment plan prescribed by the rehabilitation center</li>
        <li>Abstain from gambling activities during treatment</li>
        <li>Participate actively in therapy and counseling</li>
        <li>Complete all assigned activities and homework</li>
    </ul>
    
    <h4 style="color:#800000;margin-top:1.5rem">4. TREATMENT DURATION</h4>
    <p style="line-height:1.6">The treatment program will follow the schedule determined by the rehabilitation center. The duration may be adjusted based on the gambler's progress and the recommendation of the treatment team.</p>
    
    <h4 style="color:#800000;margin-top:1.5rem">5. CONFIDENTIALITY</h4>
    <p style="line-height:1.6">All parties agree to maintain the confidentiality of information shared during the treatment process, except as required by law or for the safety of the gambler or others.</p>
    
    <h4 style="color:#800000;margin-top:1.5rem">6. FINANCIAL OBLIGATIONS</h4>
    <p style="line-height:1.6">The family member acknowledges understanding of the financial obligations associated with the treatment program and agrees to fulfill payment responsibilities as outlined by the rehabilitation center.</p>
    
    <h4 style="color:#800000;margin-top:1.5rem">7. TERMINATION</h4>
    <p style="line-height:1.6">This agreement may be terminated by mutual consent or as determined by the rehabilitation center if the gambler fails to comply with treatment requirements.</p>
    
    <h4 style="color:#800000;margin-top:1.5rem">8. ACKNOWLEDGMENT</h4>
    <p style="line-height:1.6">By signing below, both parties acknowledge that they have read, understood, and agree to the terms of this Rehabilitation Support Agreement.</p>
</div>
<?php
else:
    $ext = strtolower(pathinfo($submission_data['template_filename'], PATHINFO_EXTENSION));
    if ($ext === 'pdf'):
?>
<iframe src="<?= htmlspecialchars($submission_data['template_url']) ?>" style="width:100%;height:700px;border:none;display:block" title="Contract Form"></iframe>
<?php else: ?>
<div style="padding:1.5rem;text-align:center">
<i class="fas fa-file-word fa-3x mb-3" style="color:#0d6efd;display:block"></i>
<p style="font-size:.9rem;color:#6c757d;margin-bottom:1rem">This contract is a Word document. Click below to view or download it.</p>
<a href="<?= htmlspecialchars($submission_data['template_url']) ?>" target="_blank" class="btn-maroon" style="margin-right:.5rem"><i class="fas fa-eye"></i> View Contract</a>
<a href="<?= htmlspecialchars($submission_data['template_url']) ?>" download="<?= htmlspecialchars($submission_data['template_filename']) ?>" class="btn-maroon" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)"><i class="fas fa-download"></i> Download</a>
</div>
<?php 
    endif;
endif;
?>
</div>
</div>

<!-- Signature & Submit -->
<div class="fc-card">
<div class="fc-card-header"><i class="fas fa-signature"></i> Sign &amp; Submit (Family Member)</div>
<div class="fc-card-body">
<?php if ($is_locked): ?>
<div style="background:#d1e7dd;color:#0f5132;border-radius:8px;padding:.85rem 1rem;font-size:.88rem">
<i class="fas fa-check-circle me-1"></i>You have already signed and submitted this contract.
</div>
<?php else: ?>
<p style="font-size:.88rem;color:#6c757d;margin-bottom:1rem">
I, as the family member of the gambler named above, have read and understood the policies and the contract form. I agree to the terms and conditions.
</p>
<div style="margin-bottom:1rem">
<label style="display:block;font-weight:600;font-size:.85rem;color:#343a40;margin-bottom:.35rem">Your Signature <span style="color:#dc3545">*</span></label>
<div class="sig-canvas-wrap"><canvas id="sigCanvas" width="500" height="200" style="background:#fff;"></canvas></div>
<div style="display:flex;gap:.5rem;margin-top:.5rem">
<button type="button" class="btn-clear" onclick="clearSig()"><i class="fas fa-eraser me-1"></i>Clear</button>
<span id="sigStatus" style="font-size:.8rem;color:#6c757d;align-self:center;margin-left:.5rem"></span>
</div>
</div>
<div id="saveMsg" style="display:none;margin-bottom:.85rem;padding:.65rem 1rem;border-radius:8px;font-size:.85rem"></div>
<button id="submitBtn" class="btn-maroon" onclick="submitContract()"><i class="fas fa-paper-plane"></i> Submit &amp; Sign Contract</button>
<?php endif; ?>
</div>
</div>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API = '/GAMBYTES_Final/api/contract_forms.php';
const SUB_ID = <?= (int)$submission_id ?>;

function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// Notifications
function toggleNotifDropdown(){const dd=document.getElementById('notifDropdown');dd.classList.toggle('open');if(dd.classList.contains('open'))loadNotifs();}
document.addEventListener('click',e=>{const w=document.getElementById('notifWrap');if(w&&!w.contains(e.target))document.getElementById('notifDropdown').classList.remove('open');});
function goNotif(link){if(link&&link!=='#')window.location.href=link;}
function loadNotifs(){fetch('/GAMBYTES_Final/api/notifications.php?action=list').then(r=>r.json()).then(d=>{const l=document.getElementById('notifList');if(!d.items||!d.items.length){l.innerHTML='<div class="notif-empty">No notifications</div>';return;}l.innerHTML=d.items.map(n=>`<div class="notif-item" style="cursor:pointer" onclick="goNotif(${JSON.stringify(n.link||'')})">${n.link?'<i class="fas fa-external-link-alt me-1" style="color:#800000;font-size:.7rem"></i>':''}<strong>${escHtml(n.title)}</strong><span>${escHtml(n.message||'')}</span></div>`).join('');});}
function markAllSeen(){fetch('/GAMBYTES_Final/api/notifications.php?action=mark_seen').then(()=>{document.getElementById('notifBadge').style.display='none';});}
function pollNotif(){fetch('/GAMBYTES_Final/api/notifications.php?action=count').then(r=>r.json()).then(d=>{const b=document.getElementById('notifBadge');if(d.count>0){b.textContent=d.count;b.style.display='flex';}else b.style.display='none';});}
pollNotif(); setInterval(pollNotif,30000);

// Signature
const canvas = document.getElementById('sigCanvas');
if(canvas){
  const ctx = canvas.getContext('2d');
  let drawing=false,lastX=0,lastY=0;
  function getPos(e){const r=canvas.getBoundingClientRect();if(e.touches)return{x:e.touches[0].clientX-r.left,y:e.touches[0].clientY-r.top};return{x:e.clientX-r.left,y:e.clientY-r.top};}
  canvas.addEventListener('mousedown',e=>{drawing=true;const p=getPos(e);lastX=p.x;lastY=p.y;});
  canvas.addEventListener('mousemove',e=>{if(!drawing)return;const p=getPos(e);ctx.beginPath();ctx.moveTo(lastX,lastY);ctx.lineTo(p.x,p.y);ctx.strokeStyle='#000';ctx.lineWidth=2;ctx.lineCap='round';ctx.stroke();lastX=p.x;lastY=p.y;document.getElementById('sigStatus').textContent='Signature captured';});
  canvas.addEventListener('mouseup',()=>drawing=false);
  canvas.addEventListener('mouseleave',()=>drawing=false);
  canvas.addEventListener('touchstart',e=>{e.preventDefault();drawing=true;const p=getPos(e);lastX=p.x;lastY=p.y;},{passive:false});
  canvas.addEventListener('touchmove',e=>{e.preventDefault();if(!drawing)return;const p=getPos(e);ctx.beginPath();ctx.moveTo(lastX,lastY);ctx.lineTo(p.x,p.y);ctx.strokeStyle='#000';ctx.lineWidth=2;ctx.lineCap='round';ctx.stroke();lastX=p.x;lastY=p.y;document.getElementById('sigStatus').textContent='Signature captured';},{passive:false});
  canvas.addEventListener('touchend',()=>drawing=false);
  window.clearSig=function(){ctx.clearRect(0,0,canvas.width,canvas.height);document.getElementById('sigStatus').textContent='';};
  window.isSigEmpty=function(){const d=ctx.getImageData(0,0,canvas.width,canvas.height).data;for(let i=3;i<d.length;i+=4)if(d[i]>0)return false;return true;};
  window.getSigData=function(){return canvas.toDataURL();};
} else {
  window.clearSig=function(){};
  window.isSigEmpty=function(){return true;};
  window.getSigData=function(){return '';};
}

function submitContract(){
  if(isSigEmpty()){alert('Please sign the contract before submitting.');return;}
  if(!confirm('Submit this contract? You will not be able to edit it after submission.'))return;
  const msg=document.getElementById('saveMsg');
  const btn=document.getElementById('submitBtn');
  const fd=new FormData();
  fd.append('participant_date', new Date().toISOString().split('T')[0]); // Today's date
  fd.append('signature_data',getSigData());
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Submitting...';
  fetch('/GAMBYTES_Final/api/save_family_contract.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Submit &amp; Sign Contract';
    msg.style.display='block';
    if(d.success){
      msg.style.background='#d1e7dd';msg.style.color='#0f5132';
      msg.innerHTML='<i class="fas fa-check-circle me-1"></i>Contract signed and submitted! Redirecting...';
      setTimeout(()=>window.location.href='/GAMBYTES_Final/app/views/Users/Family member/my-contracts.php',2000);
    } else {
      msg.style.background='#f8d7da';msg.style.color='#842029';
      msg.innerHTML='<i class="fas fa-exclamation-circle me-1"></i>'+(d.message||'Submit failed.');
    }
  }).catch(err=>{
    btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Submit &amp; Sign Contract';
    msg.style.display='block';
    msg.style.background='#f8d7da';msg.style.color='#842029';
    msg.innerHTML='<i class="fas fa-exclamation-circle me-1"></i>Network error. Please try again.';
    console.error('Submit error:', err);
  });
}
</script>
</body>
</html>
