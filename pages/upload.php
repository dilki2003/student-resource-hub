<?php
session_start();
require_once '../config/db.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error   = '';
$success = '';

// Load categories for dropdown
$cats = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = intval($_POST['category']  ?? 0);
    $subject_code= trim($_POST['subject_code']?? '');
    $year_level  = intval($_POST['year_level'] ?? 0);
    $semester    = intval($_POST['semester']   ?? 0);

    // Validate fields
    if (empty($title) || $category_id === 0) {
        $error = 'Title and category are required.';
    } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Please select a file to upload.';
    } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload failed. Please try again.';
    } else {
        $file      = $_FILES['file'];
        $max_size  = 20 * 1024 * 1024; // 20MB
        $allowed   = ['pdf','doc','docx','ppt','pptx','zip','rar','txt','png','jpg','jpeg'];
        $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['size'] > $max_size) {
            $error = 'File is too large. Maximum size is 20MB.';
        } elseif (!in_array($ext, $allowed)) {
            $error = 'File type not allowed. Allowed: PDF, DOC, DOCX, PPT, PPTX, ZIP, RAR, TXT, PNG, JPG.';
        } else {
            // Save to ROOT uploads folder — always use absolute path
            // __FILE__ = pages/upload.php
            // dirname(dirname(__FILE__)) = project root
            $upload_dir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            // Generate unique filename to avoid conflicts
            $stored_name = uniqid('rh_', true) . '.' . $ext;
            $dest        = $upload_dir . $stored_name;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $stmt = $pdo->prepare("
                    INSERT INTO resources
                        (user_id, category_id, title, description, file_name, file_original, file_type, file_size, subject_code, year_level, semester)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $category_id,
                    $title,
                    $description,
                    $stored_name,
                    $file['name'],
                    $ext,
                    $file['size'],
                    $subject_code ?: null,
                    $year_level   ?: null,
                    $semester     ?: null,
                ]);
                $new_id  = $pdo->lastInsertId();
                header('Location: resource.php?id=' . $new_id . '&uploaded=1');
                exit;
            } else {
                $error = 'Could not save the file. Check folder permissions.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Upload Resource — Resource Hub</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:          #0a0a0f;
      --surface:     #13131a;
      --surface2:    #1c1c27;
      --accent:      #7c6ff7;
      --accent2:     #f7c26f;
      --text:        #f0f0f8;
      --muted:       #7a7a9a;
      --border:      rgba(255,255,255,0.07);
      --focus:       rgba(124,111,247,0.5);
      --error:       #f87171;
      --success:     #4ade80;
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    body::before {
      content: '';
      position: fixed; inset: 0;
      background-image:
        linear-gradient(rgba(124,111,247,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(124,111,247,0.03) 1px, transparent 1px);
      background-size: 60px 60px;
      pointer-events: none; z-index: 0;
    }

    .orb {
      position: fixed; border-radius: 50%;
      filter: blur(110px); pointer-events: none; z-index: 0;
    }
    .orb1 { width: 450px; height: 450px; background: rgba(124,111,247,0.09); top: -100px; right: -80px; }
    .orb2 { width: 300px; height: 300px; background: rgba(247,194,111,0.05); bottom: 0; left: -60px; }

    /* NAV */
    nav {
      position: sticky; top: 0; z-index: 100;
      display: flex; align-items: center; justify-content: space-between;
      padding: 0.9rem 2rem;
      background: rgba(10,10,15,0.85);
      backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--border);
    }
    .logo {
      font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.2rem;
      color: var(--text); text-decoration: none;
    }
    .logo span { color: var(--accent); }
    .nav-right { display: flex; gap: 0.75rem; align-items: center; }
    .btn-ghost {
      padding: 0.45rem 1rem; border: 1px solid var(--border);
      background: transparent; color: var(--muted); border-radius: 8px;
      font-family: 'DM Sans', sans-serif; font-size: 0.85rem;
      cursor: pointer; text-decoration: none; transition: all 0.2s;
    }
    .btn-ghost:hover { color: var(--text); background: var(--surface); }

    /* MAIN */
    main {
      position: relative; z-index: 1;
      flex: 1;
      max-width: 780px;
      width: 100%;
      margin: 2.5rem auto;
      padding: 0 1.5rem;
    }

    /* PAGE HEADER */
    .page-header { margin-bottom: 2rem; }
    .page-header h1 {
      font-family: 'Syne', sans-serif;
      font-size: 1.6rem; font-weight: 800;
      letter-spacing: -0.5px; margin-bottom: 0.3rem;
    }
    .page-header p { font-size: 0.875rem; color: var(--muted); }

    /* ALERT */
    .alert {
      padding: 0.8rem 1rem; border-radius: 10px;
      font-size: 0.875rem; margin-bottom: 1.5rem;
      display: flex; align-items: center; gap: 0.5rem;
    }
    .alert.error   { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.2); color: var(--error); }
    .alert.success { background: rgba(74,222,128,0.1);  border: 1px solid rgba(74,222,128,0.2);  color: var(--success); }

    /* FORM CARD */
    .form-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 18px;
      padding: 2rem;
      animation: fadeUp 0.45s ease forwards;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(14px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .section-label {
      font-size: 0.72rem; font-weight: 700; letter-spacing: 0.8px;
      text-transform: uppercase; color: var(--muted);
      margin-bottom: 1rem; padding-bottom: 0.5rem;
      border-bottom: 1px solid var(--border);
    }

    .form-group { margin-bottom: 1.2rem; }
    .form-row   { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

    label {
      display: block; font-size: 0.8rem; font-weight: 500;
      color: var(--muted); margin-bottom: 0.4rem; letter-spacing: 0.2px;
    }
    label .req { color: var(--accent); margin-left: 2px; }

    input[type="text"],
    textarea,
    select {
      width: 100%;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 0.65rem 1rem;
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.9rem;
      outline: none;
      transition: border-color 0.2s;
      appearance: none;
    }
    input:focus, textarea:focus, select:focus { border-color: var(--focus); }
    input::placeholder, textarea::placeholder { color: var(--muted); }
    select option { background: var(--surface2); }

    textarea { resize: vertical; min-height: 90px; line-height: 1.6; }

    /* DROP ZONE */
    .drop-zone {
      border: 2px dashed rgba(124,111,247,0.25);
      border-radius: 14px;
      padding: 2.5rem 1.5rem;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s;
      position: relative;
      background: rgba(124,111,247,0.03);
    }
    .drop-zone:hover, .drop-zone.dragover {
      border-color: rgba(124,111,247,0.6);
      background: rgba(124,111,247,0.07);
    }
    .drop-zone input[type="file"] {
      position: absolute; inset: 0;
      opacity: 0; cursor: pointer; width: 100%; height: 100%;
    }
    .drop-icon {
      font-size: 2.2rem; margin-bottom: 0.75rem; display: block;
    }
    .drop-title {
      font-family: 'Syne', sans-serif;
      font-size: 0.95rem; font-weight: 700;
      margin-bottom: 0.3rem;
    }
    .drop-sub { font-size: 0.8rem; color: var(--muted); }
    .drop-types {
      margin-top: 0.75rem;
      font-size: 0.72rem; color: var(--muted);
      background: var(--surface2); display: inline-block;
      padding: 3px 10px; border-radius: 999px;
    }

    /* FILE PREVIEW */
    .file-preview {
      display: none;
      align-items: center; gap: 0.75rem;
      background: var(--surface2);
      border: 1px solid rgba(124,111,247,0.2);
      border-radius: 10px; padding: 0.75rem 1rem;
      margin-top: 0.75rem;
    }
    .file-preview.show { display: flex; }
    .preview-icon { font-size: 1.4rem; }
    .preview-info { flex: 1; min-width: 0; }
    .preview-name {
      font-size: 0.875rem; font-weight: 500;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .preview-size { font-size: 0.75rem; color: var(--muted); }
    .preview-remove {
      background: none; border: none; color: var(--muted);
      cursor: pointer; font-size: 1rem; transition: color 0.2s; padding: 0;
    }
    .preview-remove:hover { color: var(--error); }

    /* DIVIDER */
    .form-divider { margin: 1.5rem 0; }

    /* SUBMIT */
    .form-footer {
      display: flex; align-items: center; justify-content: space-between;
      gap: 1rem; margin-top: 1.8rem; flex-wrap: wrap;
    }
    .btn-submit {
      padding: 0.8rem 2rem;
      background: var(--accent); color: #fff;
      border: none; border-radius: 10px;
      font-family: 'Syne', sans-serif;
      font-size: 0.95rem; font-weight: 700;
      cursor: pointer; transition: all 0.2s;
    }
    .btn-submit:hover { opacity: 0.88; transform: translateY(-1px); }
    .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
    .btn-cancel {
      color: var(--muted); font-size: 0.875rem;
      text-decoration: none; transition: color 0.2s;
    }
    .btn-cancel:hover { color: var(--text); }

    /* TIPS */
    .tips {
      background: rgba(124,111,247,0.06);
      border: 1px solid rgba(124,111,247,0.15);
      border-radius: 12px; padding: 1rem 1.25rem;
      margin-top: 1.5rem;
    }
    .tips-title {
      font-size: 0.8rem; font-weight: 600;
      color: #a99ff8; margin-bottom: 0.5rem;
    }
    .tips ul {
      list-style: none; display: flex; flex-direction: column; gap: 0.3rem;
    }
    .tips ul li {
      font-size: 0.8rem; color: var(--muted);
      display: flex; align-items: flex-start; gap: 0.4rem;
    }
    .tips ul li::before { content: '→'; color: var(--accent); flex-shrink: 0; }

    footer {
      position: relative; z-index: 1;
      border-top: 1px solid var(--border);
      padding: 1.5rem; text-align: center;
      font-size: 0.78rem; color: var(--muted);
    }
  </style>
</head>
<body>

<div class="orb orb1"></div>
<div class="orb orb2"></div>

<!-- NAV -->
<nav>
  <a href='../index.php' class="logo">Resource<span>Hub</span></a>
  <div class="nav-right">
    <span style="font-size:0.85rem; color:var(--muted);">👋 <?= htmlspecialchars($_SESSION['user_name']) ?></span>
    <a href="browse.php" class="btn-ghost">Browse</a>
    <a href="logout.php" class="btn-ghost">Log out</a>
  </div>
</nav>

<!-- MAIN -->
<main>
  <div class="page-header">
    <h1>📤 Upload a resource</h1>
    <p>Share your notes, past papers or slides with fellow students.</p>
  </div>

  <?php if ($error): ?>
    <div class="alert error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action='upload.php' enctype="multipart/form-data" id="uploadForm">
    <div class="form-card">

      <!-- RESOURCE INFO -->
      <div class="section-label">Resource details</div>

      <div class="form-group">
        <label for="title">Title <span class="req">*</span></label>
        <input type="text" id="title" name="title" placeholder="e.g. Web Technology Past Paper 2023"
               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required/>
      </div>

      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description"
                  placeholder="Briefly describe what's in this resource…"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="category">Subject category <span class="req">*</span></label>
          <select id="category" name="category" required>
            <option value="" disabled <?= empty($_POST['category']) ? 'selected' : '' ?>>Select category</option>
            <?php foreach ($cats as $cat): ?>
              <option value="<?= $cat['id'] ?>"
                <?= ($_POST['category'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="subject_code">Subject code</label>
          <input type="text" id="subject_code" name="subject_code"
                 placeholder="e.g. IT2040"
                 value="<?= htmlspecialchars($_POST['subject_code'] ?? '') ?>"/>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="year_level">Academic year</label>
          <select id="year_level" name="year_level">
            <option value="0">Not specified</option>
            <?php for ($y = 1; $y <= 4; $y++): ?>
              <option value="<?= $y ?>" <?= ($_POST['year_level'] ?? '') == $y ? 'selected' : '' ?>>
                Year <?= $y ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="semester">Semester</label>
          <select id="semester" name="semester">
            <option value="0">Not specified</option>
            <option value="1" <?= ($_POST['semester'] ?? '') == 1 ? 'selected' : '' ?>>Semester 1</option>
            <option value="2" <?= ($_POST['semester'] ?? '') == 2 ? 'selected' : '' ?>>Semester 2</option>
          </select>
        </div>
      </div>

      <!-- FILE UPLOAD -->
      <div class="form-divider">
        <div class="section-label" style="margin-bottom:1rem;">File upload</div>

        <div class="drop-zone" id="dropZone">
          <input type="file" name="file" id="fileInput" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.rar,.txt,.png,.jpg,.jpeg" required/>
          <span class="drop-icon">☁️</span>
          <div class="drop-title">Drag & drop your file here</div>
          <div class="drop-sub">or click to browse from your computer</div>
          <div class="drop-types">PDF · DOC · DOCX · PPT · PPTX · ZIP · RAR · TXT · PNG · JPG — Max 20MB</div>
        </div>

        <div class="file-preview" id="filePreview">
          <span class="preview-icon" id="previewIcon">📄</span>
          <div class="preview-info">
            <div class="preview-name" id="previewName">—</div>
            <div class="preview-size" id="previewSize">—</div>
          </div>
          <button type="button" class="preview-remove" onclick="clearFile()" title="Remove file">✕</button>
        </div>
      </div>

      <!-- TIPS -->
      <div class="tips">
        <div class="tips-title">💡 Tips for a great upload</div>
        <ul>
          <li>Use a clear, descriptive title so others can find it easily</li>
          <li>Add the subject code (e.g. IT2040) for better search results</li>
          <li>PDF format is recommended for notes and past papers</li>
          <li>Make sure your file is your own work or properly sourced</li>
        </ul>
      </div>

      <!-- FOOTER -->
      <div class="form-footer">
        <a href="browse.php" class="btn-cancel">← Cancel</a>
        <button type="submit" class="btn-submit" id="submitBtn">Upload resource →</button>
      </div>

    </div>
  </form>
</main>

<footer>Resource Hub &copy; 2026 · University of Colombo</footer>

<script>
  const dropZone   = document.getElementById('dropZone');
  const fileInput  = document.getElementById('fileInput');
  const preview    = document.getElementById('filePreview');
  const previewName= document.getElementById('previewName');
  const previewSize= document.getElementById('previewSize');
  const previewIcon= document.getElementById('previewIcon');
  const submitBtn  = document.getElementById('submitBtn');

  const iconMap = {
    pdf: '📄', doc: '📝', docx: '📝',
    ppt: '📊', pptx: '📊', zip: '📦',
    rar: '📦', txt: '📃', png: '🖼️',
    jpg: '🖼️', jpeg: '🖼️'
  };

  function formatSize(bytes) {
    if (bytes < 1024)       return bytes + ' B';
    if (bytes < 1048576)    return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  }

  function showPreview(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    previewIcon.textContent = iconMap[ext] || '📁';
    previewName.textContent = file.name;
    previewSize.textContent = formatSize(file.size);
    preview.classList.add('show');
    submitBtn.disabled = false;
  }

  function clearFile() {
    fileInput.value = '';
    preview.classList.remove('show');
  }

  fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) showPreview(fileInput.files[0]);
  });

  // Drag and drop
  dropZone.addEventListener('dragover', e => {
    e.preventDefault();
    dropZone.classList.add('dragover');
  });
  dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('dragover');
  });
  dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file) {
      const dt = new DataTransfer();
      dt.items.add(file);
      fileInput.files = dt.files;
      showPreview(file);
    }
  });

  // Show uploading state on submit
  document.getElementById('uploadForm').addEventListener('submit', () => {
    submitBtn.textContent = 'Uploading…';
    submitBtn.disabled = true;
  });
</script>

</body>
</html>