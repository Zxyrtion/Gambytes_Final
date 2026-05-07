<?php
require_once __DIR__ . '/../../../../includes/session_config.php';
require_once __DIR__ . '/../../../../includes/url_helper.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . url('app/views/auth/login.php'));
    exit();
}
require_once __DIR__ . '/../../../core/Database.php';
$db   = new Database();
$conn = $db->connect();
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$role = $user['role'];
if (!in_array($role, ['supervisor', 'admin'])) {
    header("Location: " . url('app/views/auth/dashboard.php'));
    exit();
}
$full_name = $user['first_name'] . ' ' . $user['last_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Policies &amp; Guidelines – Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
    <style>
        .policy-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1.25rem; margin-bottom:2rem; }
        .policy-card { background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); overflow:hidden; display:flex; flex-direction:column; transition:transform .2s,box-shadow .2s; }
        .policy-card:hover { transform:translateY(-3px); box-shadow:0 8px 25px rgba(0,0,0,.12); }
        .policy-card-header { padding:1.25rem 1.5rem 1rem; display:flex; align-items:center; gap:.85rem; }
        .policy-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; }
        .policy-card-title { font-weight:700; font-size:1rem; color:#212529; margin:0; line-height:1.3; }
        .policy-card-sub { font-size:.78rem; color:#6c757d; margin:0; }
        .policy-card-body { padding:0 1.5rem 1rem; flex:1; font-size:.88rem; color:#495057; line-height:1.6; }
        .policy-card-footer { padding:.85rem 1.5rem; border-top:1px solid #f0f0f0; display:flex; flex-direction:column; gap:.6rem; }
        .btn-view-policy { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; border:none; border-radius:10px; padding:.55rem 1.25rem; font-weight:600; font-size:.88rem; cursor:pointer; transition:all .2s; width:100%; display:flex; align-items:center; justify-content:center; gap:.5rem; }
        .btn-view-policy:hover { transform:translateY(-1px); box-shadow:0 4px 14px rgba(128,0,0,.35); }

        /* Upload area */
        .upload-zone { border:2px dashed #d0d0d0; border-radius:10px; padding:.65rem 1rem; text-align:center; cursor:pointer; transition:border-color .2s,background .2s; font-size:.82rem; color:#6c757d; }
        .upload-zone:hover { border-color:#800000; background:#fff5f5; color:#800000; }
        .upload-zone input[type=file] { display:none; }
        .uploaded-file { display:flex; align-items:center; gap:.5rem; background:#f8f9fa; border-radius:10px; padding:.55rem .85rem; font-size:.82rem; }
        .uploaded-file a { color:#800000; font-weight:600; text-decoration:none; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .uploaded-file a:hover { text-decoration:underline; }
        .btn-del-file { background:none; border:none; color:#dc3545; cursor:pointer; font-size:.9rem; padding:0 .2rem; flex-shrink:0; }
        .btn-del-file:hover { color:#a71d2a; }
        .upload-progress { display:none; font-size:.78rem; color:#800000; text-align:center; margin-top:.25rem; }
        .upload-progress.show { display:block; }

        /* Modal */
        .pol-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9999; align-items:flex-start; justify-content:center; padding:2rem 1rem; overflow-y:auto; }
        .pol-overlay.open { display:flex; }
        .pol-modal { background:#fff; border-radius:18px; width:100%; max-width:820px; box-shadow:0 20px 60px rgba(0,0,0,.25); margin:auto; }
        .pol-modal-header { background:linear-gradient(135deg,#800000,#5c0000); color:#fff; padding:1.25rem 1.5rem; display:flex; justify-content:space-between; align-items:center; border-radius:18px 18px 0 0; }
        .pol-modal-header h2 { margin:0; font-size:1.1rem; font-weight:700; color:#fff; }
        .pol-modal-close { background:rgba(255,255,255,.2); border:none; color:#fff; width:34px; height:34px; border-radius:8px; font-size:1.2rem; cursor:pointer; display:flex; align-items:center; justify-content:center; }
        .pol-modal-close:hover { background:rgba(255,255,255,.35); }
        .pol-modal-body { padding:2rem; }

        /* Form styles inside modal */
        .rg-form { font-family:'Times New Roman',serif; }
        .rg-form-title { text-align:center; font-size:1.3rem; font-weight:700; margin-bottom:.25rem; }
        .rg-form-org { text-align:center; font-size:.95rem; font-weight:700; margin-bottom:.1rem; }
        .rg-form-tagline { text-align:center; font-size:.8rem; font-style:italic; color:#555; margin-bottom:1.25rem; }
        .rg-form-note { font-size:.82rem; font-style:italic; margin-bottom:1rem; color:#333; }
        .rg-section { background:#343a40; color:#fff; padding:.4rem .75rem; font-weight:700; font-size:.85rem; margin:.75rem 0 .4rem; }
        .rg-field-row { display:grid; gap:.5rem; margin-bottom:.5rem; }
        .rg-field { border-bottom:1px solid #333; padding:.2rem 0; font-size:.88rem; min-height:1.6rem; }
        .rg-field-label { font-size:.78rem; color:#555; }
        .rg-check-group { display:flex; flex-wrap:wrap; gap:.5rem 1.25rem; font-size:.85rem; margin:.4rem 0; }
        .rg-check-item { display:flex; align-items:center; gap:.35rem; }
        .rg-check-box { width:14px; height:14px; border:1.5px solid #333; display:inline-block; flex-shrink:0; }
        .rg-divider { border-top:2px solid #333; margin:1rem 0; }
        .rg-tc-title { text-align:center; font-weight:700; text-decoration:underline; margin:.75rem 0 .5rem; font-size:.9rem; }
        .rg-tc-text { font-size:.83rem; line-height:1.6; margin-bottom:.5rem; }
        .rg-period-row { display:flex; gap:1.5rem; flex-wrap:wrap; font-size:.85rem; margin:.5rem 0; }
        .rg-sig-row { display:flex; justify-content:space-between; margin-top:2rem; font-size:.82rem; font-style:italic; }
        .rg-sig-line { border-top:1px solid #333; padding-top:.25rem; min-width:200px; text-align:center; }
        .rg-remarks { font-size:.78rem; font-style:italic; color:#555; margin-top:1rem; border-top:1px solid #ccc; padding-top:.5rem; }
        .rg-form-num { text-align:right; font-size:.8rem; font-weight:700; margin-bottom:.5rem; }

        /* Policy text */
        .pol-text h4 { color:#800000; font-size:1rem; font-weight:700; margin:1.25rem 0 .5rem; }
        .pol-text p { font-size:.9rem; line-height:1.7; color:#343a40; margin-bottom:.75rem; }
        .pol-text .whereas { margin-bottom:.75rem; font-size:.9rem; line-height:1.7; }
        .pol-text .whereas strong { color:#800000; }
        .pol-resolution-header { text-align:center; font-weight:700; font-size:1rem; margin:1rem 0; text-decoration:underline; }
        .pol-meta { display:flex; gap:2rem; font-size:.85rem; margin-bottom:1rem; flex-wrap:wrap; }
        .pol-meta-item strong { color:#800000; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="/GAMBYTES_Final/public/images/Logo.png" alt="Logo">
                <span>Gambytes</span>
            </div>
            <div class="sidebar-user">
                <div class="user-name"><i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($full_name) ?></div>
                <div class="user-role"><?= ucfirst(str_replace('_',' ',$role)) ?></div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/booking-management.php"><i class="fas fa-calendar-check"></i> Booking Management</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/interview-records.php"><i class="fas fa-clipboard-list"></i> Interview Records</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/contract-review.php"><i class="fas fa-file-contract"></i> Contract Management</a></li>
            <li><a href="/GAMBYTES_Final/app/views/Users/Supervisor/policies.php" class="active"><i class="fas fa-book"></i> Policies &amp; Guidelines</a></li>
            <li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
            <div class="menu-divider"></div>
            <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div style="margin-bottom:1.75rem;">
            <h1 style="color:#800000; font-size:1.8rem; font-weight:800; margin:0;">
                <i class="fas fa-book me-2"></i>Policies &amp; Guidelines
            </h1>
            <p style="color:#6c757d; margin:.25rem 0 0;">PAGCOR Responsible Gaming Forms &amp; Company Policies</p>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             UPLOAD NEW DOCUMENT SECTION
        ═══════════════════════════════════════════════════════════ -->
        <div style="background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); padding:1.75rem 2rem; margin-bottom:2rem;">
            <h2 style="font-size:1.15rem; font-weight:700; color:#212529; margin:0 0 1.25rem;">
                <i class="fas fa-cloud-upload-alt me-2" style="color:#800000;"></i>Upload New Document
            </h2>
            <form id="uploadForm" enctype="multipart/form-data">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">
                    <div>
                        <label style="display:block; font-weight:600; font-size:.88rem; color:#343a40; margin-bottom:.4rem;">
                            Document Title <span style="color:#dc3545;">*</span>
                        </label>
                        <input type="text" id="doc_title" name="doc_title" class="form-control" placeholder="Enter document title..." required>
                    </div>
                    <div>
                        <label style="display:block; font-weight:600; font-size:.88rem; color:#343a40; margin-bottom:.4rem;">
                            Document Type <span style="color:#dc3545;">*</span>
                        </label>
                        <select id="doc_type" name="doc_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="Policy">Policy</option>
                            <option value="Guideline">Guideline</option>
                            <option value="Staff Training">Staff Training</option>
                            <option value="Form">Form</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">
                    <div>
                        <label style="display:block; font-weight:600; font-size:.88rem; color:#343a40; margin-bottom:.4rem;">
                            Category <span style="color:#dc3545;">*</span>
                        </label>
                        <select id="doc_category" name="doc_category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="Policies & Guidelines">Policies & Guidelines</option>
                            <option value="Rehabilitation">Rehabilitation</option>
                            <option value="Staff Training">Staff Training</option>
                            <option value="General">General</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-weight:600; font-size:.88rem; color:#343a40; margin-bottom:.4rem;">
                            Document File <span style="color:#dc3545;">*</span>
                        </label>
                        <label for="doc_file" style="display:flex; align-items:center; justify-content:center; border:2px dashed #d0d0d0; border-radius:10px; padding:.85rem 1rem; cursor:pointer; transition:all .2s; background:#f8f9fa;">
                            <i class="fas fa-paperclip me-2" style="color:#800000;"></i>
                            <span id="file_label" style="font-size:.88rem; color:#6c757d;">Choose File</span>
                            <input type="file" id="doc_file" name="doc_file" accept=".pdf,.doc,.docx,.txt,.ppt,.pptx,.xls,.xlsx" style="display:none;" required onchange="updateFileLabel(this)">
                        </label>
                        <small style="display:block; margin-top:.35rem; color:#6c757d; font-size:.75rem;">PDF, DOC, DOCX, TXT, PPT, PPTX, XLS, XLSX (Max 10MB)</small>
                    </div>
                </div>
                <div style="margin-bottom:1.25rem;">
                    <label style="display:block; font-weight:600; font-size:.88rem; color:#343a40; margin-bottom:.4rem;">
                        Description
                    </label>
                    <textarea id="doc_description" name="doc_description" class="form-control" rows="3" placeholder="Enter document description..."></textarea>
                </div>
                <div style="display:flex; gap:.75rem;">
                    <button type="submit" style="background:linear-gradient(135deg,#0d6efd,#0a58ca); color:#fff; border:none; padding:.65rem 1.75rem; border-radius:10px; font-weight:600; font-size:.9rem; cursor:pointer; display:flex; align-items:center; gap:.5rem;">
                        <i class="fas fa-upload"></i> Upload Document
                    </button>
                    <button type="button" onclick="resetUploadForm()" style="background:#6c757d; color:#fff; border:none; padding:.65rem 1.5rem; border-radius:10px; font-weight:600; font-size:.9rem; cursor:pointer;">
                        Clear
                    </button>
                </div>
                <div id="upload_message" style="display:none; margin-top:1rem; padding:.75rem 1rem; border-radius:8px; font-size:.88rem;"></div>
            </form>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             DOCUMENTS LIST
        ═══════════════════════════════════════════════════════════ -->
        <div style="background:#fff; border-radius:16px; box-shadow:0 2px 15px rgba(0,0,0,.08); padding:1.75rem 2rem;">
            <h2 style="font-size:1.15rem; font-weight:700; color:#212529; margin:0 0 1.25rem;">
                <i class="fas fa-file-alt me-2" style="color:#800000;"></i>Documents
            </h2>
            <div id="documents_list">
                <div style="text-align:center; padding:2rem; color:#6c757d;">
                    <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;"></i>
                    <p style="margin-top:.75rem;">Loading documents...</p>
                </div>
            </div>
        </div>

    </div><!-- /main-content -->
</div><!-- /dashboard-container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const POLICY_API = '/GAMBYTES_Final/api/policies.php';

    // ── File label update ────────────────────────────────────────────────────
    function updateFileLabel(input) {
        const label = document.getElementById('file_label');
        label.textContent = input.files[0] ? input.files[0].name : 'Choose File';
        label.style.color = input.files[0] ? '#800000' : '#6c757d';
    }

    function resetUploadForm() {
        document.getElementById('uploadForm').reset();
        document.getElementById('file_label').textContent = 'Choose File';
        document.getElementById('file_label').style.color = '#6c757d';
        const msg = document.getElementById('upload_message');
        msg.style.display = 'none';
    }

    // ── Upload form submit ───────────────────────────────────────────────────
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const msg = document.getElementById('upload_message');
        const btn = this.querySelector('button[type=submit]');

        const formData = new FormData(this);
        formData.append('action', 'upload');

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Uploading...';
        msg.style.display = 'none';

        fetch(POLICY_API, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload"></i> Upload Document';
                if (data.success) {
                    msg.style.display = 'block';
                    msg.style.background = '#d1e7dd';
                    msg.style.color = '#0f5132';
                    msg.innerHTML = '<i class="fas fa-check-circle me-1"></i> Document uploaded successfully!';
                    resetUploadForm();
                    loadDocuments();
                } else {
                    msg.style.display = 'block';
                    msg.style.background = '#f8d7da';
                    msg.style.color = '#842029';
                    msg.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> ' + (data.message || 'Upload failed.');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload"></i> Upload Document';
                msg.style.display = 'block';
                msg.style.background = '#f8d7da';
                msg.style.color = '#842029';
                msg.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> Network error. Please try again.';
            });
    });

    // ── Load documents list ──────────────────────────────────────────────────
    function loadDocuments() {
        const container = document.getElementById('documents_list');
        fetch(POLICY_API + '?action=list')
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.documents || data.documents.length === 0) {
                    container.innerHTML = `
                        <div style="text-align:center; padding:2.5rem; color:#6c757d;">
                            <i class="fas fa-folder-open" style="font-size:2.5rem; opacity:.4;"></i>
                            <p style="margin-top:.75rem; font-size:.9rem;">No documents uploaded yet.</p>
                        </div>`;
                    return;
                }

                const typeColors = {
                    'Policy':        { bg:'#cfe2ff', color:'#084298' },
                    'Guideline':     { bg:'#d1e7dd', color:'#0f5132' },
                    'Staff Training':{ bg:'#fff3cd', color:'#664d03' },
                    'Form':          { bg:'#f8d7da', color:'#842029' },
                    'Other':         { bg:'#e2e3e5', color:'#41464b' },
                };

                container.innerHTML = data.documents.map(doc => {
                    const tc = typeColors[doc.doc_type] || typeColors['Other'];
                    const date = new Date(doc.uploaded_at).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
                    return `
                    <div style="border-left:4px solid #800000; padding:1rem 1.25rem; margin-bottom:.85rem; background:#fafafa; border-radius:0 10px 10px 0; display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:700; font-size:1rem; color:#212529; margin-bottom:.2rem;">${escHtml(doc.doc_title)}</div>
                            <div style="font-size:.83rem; color:#6c757d; margin-bottom:.5rem;">${escHtml(doc.description || '—')}</div>
                            <div style="display:flex; flex-wrap:wrap; gap:.4rem; align-items:center;">
                                <span style="background:${tc.bg}; color:${tc.color}; font-size:.72rem; font-weight:700; padding:.2rem .6rem; border-radius:20px;">${escHtml(doc.doc_type)}</span>
                                <span style="background:#e0e7ff; color:#3730a3; font-size:.72rem; font-weight:700; padding:.2rem .6rem; border-radius:20px;">${escHtml(doc.doc_category)}</span>
                                <span style="font-size:.78rem; color:#6c757d;"><i class="fas fa-user me-1"></i>${escHtml(doc.uploader_name)}</span>
                                <span style="font-size:.78rem; color:#6c757d;"><i class="fas fa-calendar me-1"></i>${date}</span>
                            </div>
                        </div>
                        <div style="display:flex; gap:.5rem; flex-shrink:0; align-items:center;">
                            <a href="${escHtml(doc.url)}" target="_blank"
                               style="background:#fff; border:1.5px solid #0d6efd; color:#0d6efd; padding:.4rem .9rem; border-radius:8px; font-size:.82rem; font-weight:600; text-decoration:none; display:flex; align-items:center; gap:.35rem;">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="${escHtml(doc.url)}" download="${escHtml(doc.original_name)}"
                               style="background:#fff; border:1.5px solid #6c757d; color:#6c757d; padding:.4rem .9rem; border-radius:8px; font-size:.82rem; font-weight:600; text-decoration:none; display:flex; align-items:center; gap:.35rem;">
                                <i class="fas fa-download"></i> Download
                            </a>
                            <button onclick="deleteDocument(${doc.id}, '${escHtml(doc.doc_title)}')"
                                    style="background:#fff; border:1.5px solid #dc3545; color:#dc3545; padding:.4rem .9rem; border-radius:8px; font-size:.82rem; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:.35rem;">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>`;
                }).join('');
            })
            .catch(() => {
                container.innerHTML = `<div style="color:#dc3545; padding:1rem;">Failed to load documents.</div>`;
            });
    }

    // ── Delete document ──────────────────────────────────────────────────────
    function deleteDocument(id, title) {
        if (!confirm(`Delete "${title}"? This cannot be undone.`)) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('doc_id', id);
        fetch(POLICY_API, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) loadDocuments();
                else alert(data.message || 'Delete failed.');
            });
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Load on page ready
    document.addEventListener('DOMContentLoaded', loadDocuments);
</script>
</body>
</html>

