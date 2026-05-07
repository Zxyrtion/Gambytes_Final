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
$role = $user['role'];
if (!in_array($role, ['supervisor','admin'])) { header("Location: " . url('app/views/auth/dashboard.php')); exit(); }
$full_name = $user['first_name'] . ' ' . $user['last_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Contract Management - Gambytes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
<style>
.cm-card{background:#fff;border-radius:16px;box-shadow:0 2px 15px rgba(0,0,0,.08);overflow:hidden;margin-bottom:1.75rem}
.cm-card-header{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;padding:1rem 1.5rem;font-weight:700;font-size:1rem;display:flex;align-items:center;gap:.6rem}
.cm-card-body{padding:1.75rem}
.tpl-row{display:flex;align-items:center;gap:1rem;padding:.9rem 1rem;border-radius:10px;background:#f8f9fa;margin-bottom:.6rem;flex-wrap:wrap}
.tpl-row:last-child{margin-bottom:0}
.tpl-icon{width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,#800000,#5c0000);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.tpl-info{flex:1;min-width:0}
.tpl-title{font-weight:700;color:#212529;font-size:.95rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tpl-meta{font-size:.78rem;color:#6c757d}
.pill{display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .75rem;border-radius:20px;font-size:.75rem;font-weight:700}
.pill-active{background:#d1e7dd;color:#0f5132}
.pill-inactive{background:#e2e3e5;color:#41464b}
.pill-submitted{background:#cfe2ff;color:#084298}
.pill-reviewed{background:#fff3cd;color:#664d03}
.pill-sent{background:#d1e7dd;color:#0f5132}
.pill-completed{background:#d1e7dd;color:#0f5132}
.sub-row{border-left:4px solid #800000;padding:1rem 1.25rem;background:#fafafa;border-radius:0 12px 12px 0;margin-bottom:.85rem}
.sub-row:last-child{margin-bottom:0}
.sub-name{font-weight:700;color:#212529;font-size:.95rem}
.sub-meta{font-size:.8rem;color:#6c757d;margin-top:.2rem}
.btn-maroon{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;border:none;border-radius:8px;padding:.42rem 1rem;font-size:.82rem;font-weight:600;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:.35rem}
.btn-maroon:hover{opacity:.88;color:#fff}
.btn-outline{background:transparent;border:1.5px solid #800000;color:#800000;border-radius:8px;padding:.4rem .9rem;font-size:.82rem;font-weight:600;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:.35rem;text-decoration:none}
.btn-outline:hover{background:#800000;color:#fff}
.btn-danger-sm{background:transparent;border:1.5px solid #dc3545;color:#dc3545;border-radius:8px;padding:.4rem .9rem;font-size:.82rem;font-weight:600;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:.35rem}
.btn-danger-sm:hover{background:#dc3545;color:#fff}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:flex-start;justify-content:center;padding:2rem 1rem;overflow-y:auto}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:18px;width:100%;max-width:600px;box-shadow:0 20px 60px rgba(0,0,0,.25);margin:auto}
.modal-header{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;padding:1.1rem 1.5rem;display:flex;justify-content:space-between;align-items:center;border-radius:18px 18px 0 0}
.modal-header h3{margin:0;font-size:1.05rem;font-weight:700;color:#fff}
.modal-close{background:rgba(255,255,255,.2);border:none;color:#fff;width:32px;height:32px;border-radius:8px;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center}
.modal-close:hover{background:rgba(255,255,255,.35)}
.modal-body{padding:1.75rem}
.field-group{margin-bottom:1rem}
.field-group label{display:block;font-weight:600;font-size:.85rem;color:#343a40;margin-bottom:.35rem}
.field-group input,.field-group textarea{width:100%;border:1.5px solid #dee2e6;border-radius:8px;padding:.5rem .85rem;font-size:.88rem;transition:.2s}
.field-group input:focus,.field-group textarea:focus{outline:none;border-color:#800000;box-shadow:0 0 0 3px rgba(128,0,0,.1)}
.empty-state{text-align:center;padding:2.5rem 1rem;color:#adb5bd}
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
<div class="user-role"><?= ucfirst(str_replace('_',' ',$role)) ?></div>
</div>
</div>
<ul class="sidebar-menu">
<li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php"><i class="fas fa-calendar-check"></i> Booking Management</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/interview-records.php"><i class="fas fa-clipboard-list"></i> Interview Records</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/contract-review.php" class="active"><i class="fas fa-file-contract"></i> Contract Management</a></li>
<li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/policies.php"><i class="fas fa-book"></i> Policies &amp; Guidelines</a></li>
<li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
<div class="menu-divider"></div>
<li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul>
</div>
<div class="main-content">
<div class="top-navbar">
<span class="top-navbar-title">Supervisor Portal</span>
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
<h1 style="color:#800000;font-size:1.8rem;font-weight:800;margin:0"><i class="fas fa-file-contract me-2"></i>Contract Management</h1>
<p style="color:#6c757d;margin:.25rem 0 0">Upload contract form templates and review submissions from gamblers</p>
</div>

<!-- UPLOAD TEMPLATE -->
<div class="cm-card">
<div class="cm-card-header"><i class="fas fa-cloud-upload-alt"></i> Upload Contract Form Template</div>
<div class="cm-card-body">
<form id="uploadForm" enctype="multipart/form-data">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1rem">
<div class="field-group" style="margin:0">
<label>Template Title <span style="color:#dc3545">*</span></label>
<input type="text" name="title" placeholder="e.g. Rehabilitation Contract Form" required>
</div>
<div class="field-group" style="margin:0">
<label>File (PDF / DOC / DOCX) <span style="color:#dc3545">*</span></label>
<label for="tpl_file" style="display:flex;align-items:center;gap:.5rem;border:1.5px dashed #dee2e6;border-radius:8px;padding:.5rem .85rem;cursor:pointer;background:#f8f9fa;transition:.2s">
<i class="fas fa-paperclip" style="color:#800000"></i>
<span id="fileLabelText" style="font-size:.88rem;color:#6c757d">Choose file...</span>
<input type="file" id="tpl_file" name="template_file" accept=".pdf,.doc,.docx" style="display:none" required onchange="updateFileLabel(this)">
</label>
</div>
</div>
<div class="field-group">
<label>Description</label>
<textarea name="description" rows="2" placeholder="Brief description of this contract form..."></textarea>
</div>
<div style="display:flex;gap:.75rem;align-items:center">
<button type="submit" class="btn-maroon"><i class="fas fa-upload"></i> Upload Template</button>
<button type="button" onclick="resetForm()" style="background:#6c757d;color:#fff;border:none;border-radius:8px;padding:.42rem 1rem;font-size:.82rem;font-weight:600;cursor:pointer">Clear</button>
</div>
<div id="uploadMsg" style="display:none;margin-top:.85rem;padding:.65rem 1rem;border-radius:8px;font-size:.85rem"></div>
</form>
</div>
</div>

<!-- TEMPLATES LIST -->
<div class="cm-card">
<div class="cm-card-header"><i class="fas fa-folder-open"></i> Contract Form Templates</div>
<div class="cm-card-body" id="templatesList"><div class="empty-state"><i class="fas fa-spinner fa-spin fa-2x mb-2" style="display:block"></i>Loading...</div></div>
</div>

<!-- SUBMISSIONS -->
<div class="cm-card">
<div class="cm-card-header"><i class="fas fa-inbox"></i> Submitted Contracts <span id="subCount" style="background:rgba(255,255,255,.25);padding:.15rem .65rem;border-radius:20px;font-size:.75rem;margin-left:auto"></span></div>
<div class="cm-card-body" id="submissionsList"><div class="empty-state"><i class="fas fa-spinner fa-spin fa-2x mb-2" style="display:block"></i>Loading...</div></div>
</div>

</div>
</div>

<!-- Send Modal -->
<div class="modal-overlay" id="sendModal">
<div class="modal-box">
<div class="modal-header"><h3><i class="fas fa-paper-plane me-2"></i>Send Contract to Parties</h3><button class="modal-close" onclick="closeSendModal()">&times;</button></div>
<div class="modal-body">
<p style="font-size:.88rem;color:#6c757d;margin-bottom:1.25rem">This will notify the gambler and their family member (if linked) to review and fill out the contract form.</p>
<div id="sendMsg" style="display:none;margin-bottom:.85rem;padding:.65rem 1rem;border-radius:8px;font-size:.85rem"></div>
<div style="display:flex;gap:.75rem">
<button class="btn-maroon" onclick="confirmSend()"><i class="fas fa-paper-plane"></i> Send Now</button>
<button onclick="closeSendModal()" style="background:#6c757d;color:#fff;border:none;border-radius:8px;padding:.42rem 1rem;font-size:.82rem;font-weight:600;cursor:pointer">Cancel</button>
</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API = '/GAMBYTES_Final/api/contract_forms.php';
let pendingSendId = null;

function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// Notifications
function toggleNotifDropdown(){const dd=document.getElementById('notifDropdown');dd.classList.toggle('open');if(dd.classList.contains('open'))loadNotifs();}
document.addEventListener('click',e=>{const w=document.getElementById('notifWrap');if(w&&!w.contains(e.target))document.getElementById('notifDropdown').classList.remove('open');});
function goNotif(link){if(link&&link!=='#')window.location.href=link;}
function loadNotifs(){fetch('/GAMBYTES_Final/api/notifications.php?action=list').then(r=>r.json()).then(d=>{const l=document.getElementById('notifList');if(!d.items||!d.items.length){l.innerHTML='<div class="notif-empty">No notifications</div>';return;}l.innerHTML=d.items.map(n=>`<div class="notif-item" style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;padding:.75rem 1rem;border-bottom:1px solid #f0f0f0"><div style="flex:1;cursor:pointer" onclick="goNotif(${JSON.stringify(n.link||'')})">${n.link?'<i class="fas fa-external-link-alt me-1" style="color:#800000;font-size:.7rem"></i>':''}<strong>${escHtml(n.title)}</strong><span style="display:block;color:#6c757d;font-size:.78rem">${escHtml(n.message||'')}</span><span style="font-size:.72rem;color:#adb5bd">${n.created_at}</span></div><button style="flex-shrink:0;background:none;border:none;color:#dc3545;cursor:pointer;font-size:.9rem;padding:.25rem .5rem;border-radius:4px" onclick="deleteNotif(${n.id}, event)" title="Delete"><i class="fas fa-times"></i></button></div>`).join('');});}
function deleteNotif(notifId,event){event.stopPropagation();if(!confirm('Are you sure you want to delete this notification?'))return;fetch('/GAMBYTES_Final/api/notifications.php?action=delete&id='+notifId).then(r=>r.json()).then(d=>{if(d.success){loadNotifs();pollNotif();}else alert('Error deleting notification: '+(d.message||'Unknown error'));});}
function markAllSeen(){fetch('/GAMBYTES_Final/api/notifications.php?action=mark_seen').then(()=>{document.getElementById('notifBadge').style.display='none';document.getElementById('notifList').innerHTML='<div class="notif-empty">No notifications</div>';});}
function pollNotif(){fetch('/GAMBYTES_Final/api/notifications.php?action=count').then(r=>r.json()).then(d=>{const b=document.getElementById('notifBadge');if(d.count>0){b.textContent=d.count;b.style.display='flex';}else b.style.display='none';});}
pollNotif(); setInterval(pollNotif,30000);

// File label
function updateFileLabel(inp){const s=document.getElementById('fileLabelText');s.textContent=inp.files[0]?inp.files[0].name:'Choose file...';s.style.color=inp.files[0]?'#800000':'#6c757d';}
function resetForm(){document.getElementById('uploadForm').reset();document.getElementById('fileLabelText').textContent='Choose file...';document.getElementById('fileLabelText').style.color='#6c757d';document.getElementById('uploadMsg').style.display='none';}

// Upload template
document.getElementById('uploadForm').addEventListener('submit',function(e){
  e.preventDefault();
  const msg=document.getElementById('uploadMsg');
  const btn=this.querySelector('button[type=submit]');
  const fd=new FormData(this);
  fd.append('action','upload_template');
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Uploading...';
  msg.style.display='none';
  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    btn.disabled=false; btn.innerHTML='<i class="fas fa-upload"></i> Upload Template';
    msg.style.display='block';
    if(d.success){msg.style.background='#d1e7dd';msg.style.color='#0f5132';msg.innerHTML='<i class="fas fa-check-circle me-1"></i>'+d.message;resetForm();loadTemplates();}
    else{msg.style.background='#f8d7da';msg.style.color='#842029';msg.innerHTML='<i class="fas fa-exclamation-circle me-1"></i>'+(d.message||'Upload failed.');}
  }).catch(()=>{btn.disabled=false;btn.innerHTML='<i class="fas fa-upload"></i> Upload Template';msg.style.display='block';msg.style.background='#f8d7da';msg.style.color='#842029';msg.innerHTML='Network error.';});
});

// Load templates
function loadTemplates(){
  fetch(API+'?action=list_templates').then(r=>r.json()).then(d=>{
    const c=document.getElementById('templatesList');
    if(!d.success||!d.templates.length){c.innerHTML='<div class="empty-state"><i class="fas fa-folder-open fa-2x mb-2" style="display:block"></i>No templates uploaded yet.</div>';return;}
    c.innerHTML=d.templates.map(t=>`
      <div class="tpl-row">
        <div class="tpl-icon"><i class="fas fa-file-pdf"></i></div>
        <div class="tpl-info">
          <div class="tpl-title">${escHtml(t.title)}</div>
          <div class="tpl-meta">${escHtml(t.description||'-')} &nbsp;&middot;&nbsp; Uploaded by ${escHtml(t.uploader_name)} &nbsp;&middot;&nbsp; ${new Date(t.uploaded_at).toLocaleDateString()}</div>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
          <span class="pill ${t.is_active=='1'?'pill-active':'pill-inactive'}">${t.is_active=='1'?'Active':'Inactive'}</span>
          <a href="${escHtml(t.url)}" target="_blank" class="btn-outline"><i class="fas fa-eye"></i> View</a>
          <button class="btn-outline" onclick="toggleTemplate(${t.id},this)"><i class="fas fa-toggle-${t.is_active=='1'?'on':'off'}"></i> ${t.is_active=='1'?'Deactivate':'Activate'}</button>
          <button class="btn-danger-sm" onclick="deleteTemplate(${t.id},'${escHtml(t.title)}')"><i class="fas fa-trash"></i> Delete</button>
        </div>
      </div>`).join('');
  });
}

function toggleTemplate(id,btn){
  btn.disabled=true;
  const fd=new FormData(); fd.append('action','toggle_template'); fd.append('template_id',id);
  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.success)loadTemplates();else{btn.disabled=false;alert('Failed.');}});
}
function deleteTemplate(id,title){
  if(!confirm('Delete template "'+title+'"? This cannot be undone.'))return;
  const fd=new FormData(); fd.append('action','delete_template'); fd.append('template_id',id);
  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.success)loadTemplates();else alert(d.message||'Delete failed.');});
}

// Load submissions
function statusPill(s){const m={submitted:'pill-submitted',reviewed:'pill-reviewed',sent_to_parties:'pill-sent',completed:'pill-completed'};return`<span class="pill ${m[s]||'pill-submitted'}">${escHtml(s.replace(/_/g,' '))}</span>`;}

function loadSubmissions(){
  fetch(API+'?action=list_submissions').then(r=>r.json()).then(d=>{
    const c=document.getElementById('submissionsList');
    const cnt=document.getElementById('subCount');
    if(!d.success||!d.submissions.length){c.innerHTML='<div class="empty-state"><i class="fas fa-inbox fa-2x mb-2" style="display:block"></i>No submissions yet.</div>';cnt.textContent='';return;}
    cnt.textContent=d.submissions.length+' total';
    c.innerHTML=d.submissions.map(s=>{
      const hasFamily=s.family_name&&s.family_name.trim()!='';
      return`<div class="sub-row">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
          <div>
            <div class="sub-name"><i class="fas fa-user me-1" style="color:#800000"></i>${escHtml(s.gambler_name)} ${hasFamily?'<span style="font-size:.8rem;color:#6c757d">+ '+escHtml(s.family_name)+'</span>':''}</div>
            <div class="sub-meta">
              <i class="fas fa-file-contract me-1"></i>${escHtml(s.template_title)} &nbsp;&middot;&nbsp;
              Submitted: ${s.submitted_at?new Date(s.submitted_at).toLocaleDateString():'-'} &nbsp;&middot;&nbsp;
              ${s.sent_at?'Sent: '+new Date(s.sent_at).toLocaleDateString():'Not sent yet'}
            </div>
          </div>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            ${statusPill(s.status)}
            <a href="${escHtml(s.template_url)}" target="_blank" class="btn-outline"><i class="fas fa-eye"></i> View Form</a>
            ${s.status!=='sent_to_parties'&&s.status!=='completed'?`<button class="btn-maroon" onclick="openSendModal(${s.id})"><i class="fas fa-paper-plane"></i> Send to Parties</button>`:'<span style="font-size:.8rem;color:#198754;font-weight:600"><i class="fas fa-check-circle me-1"></i>Sent</span>'}
          </div>
        </div>
      </div>`;
    }).join('');
  });
}

// Send modal
function openSendModal(id){pendingSendId=id;document.getElementById('sendMsg').style.display='none';document.getElementById('sendModal').classList.add('open');}
function closeSendModal(){document.getElementById('sendModal').classList.remove('open');pendingSendId=null;}
document.getElementById('sendModal').addEventListener('click',e=>{if(e.target===document.getElementById('sendModal'))closeSendModal();});

function confirmSend(){
  if(!pendingSendId)return;
  const msg=document.getElementById('sendMsg');
  const btn=document.querySelector('#sendModal .btn-maroon');
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Sending...';
  const fd=new FormData(); fd.append('action','send_to_parties'); fd.append('submission_id',pendingSendId);
  fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Send Now';
    msg.style.display='block';
    if(d.success){msg.style.background='#d1e7dd';msg.style.color='#0f5132';msg.innerHTML='<i class="fas fa-check-circle me-1"></i>'+d.message;setTimeout(()=>{closeSendModal();loadSubmissions();},1500);}
    else{msg.style.background='#f8d7da';msg.style.color='#842029';msg.innerHTML='<i class="fas fa-exclamation-circle me-1"></i>'+(d.message||'Failed.');}
  });
}

loadTemplates(); loadSubmissions();
</script>
</body>
</html>
