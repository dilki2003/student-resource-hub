<?php
session_start();
require_once '../config/db.php';

// Get resource ID
$id = intval($_GET['id'] ?? 0);
if ($id === 0) {
    header('Location: browse.php');
    exit;
}

// Fetch resource details
$stmt = $pdo->prepare("
    SELECT r.*,
           u.name AS uploader_name,
           u.id   AS uploader_id,
           c.name AS category_name,
           c.icon AS category_icon,
           ROUND(COALESCE(AVG(rt.stars), 0), 1) AS avg_rating,
           COUNT(rt.id) AS rating_count
    FROM resources r
    JOIN users      u  ON r.user_id     = u.id
    JOIN categories c  ON r.category_id = c.id
    LEFT JOIN ratings rt ON r.id        = rt.resource_id
    WHERE r.id = ? AND r.status = 'active'
    GROUP BY r.id
");
$stmt->execute([$id]);
$r = $stmt->fetch();

if (!$r) {
    header('Location: browse.php');
    exit;
}

// Current user's rating (if logged in)
$my_rating = 0;
if (isset($_SESSION['user_id'])) {
    $rs = $pdo->prepare("SELECT stars FROM ratings WHERE resource_id = ? AND user_id = ?");
    $rs->execute([$id, $_SESSION['user_id']]);
    $my_rating = (int)($rs->fetchColumn() ?: 0);
}

// Handle rating submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stars'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    $stars = intval($_POST['stars']);
    if ($stars >= 1 && $stars <= 5) {
        $pdo->prepare("
            INSERT INTO ratings (resource_id, user_id, stars)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE stars = VALUES(stars)
        ")->execute([$id, $_SESSION['user_id'], $stars]);
    }
    header("Location: resource.php?id=$id#ratings");
    exit;
}

// Handle download
if (isset($_GET['download'])) {
    $uploads_dir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    $file_path   = $uploads_dir . $r['file_name'];

    if (file_exists($file_path)) {
        if (isset($_SESSION['user_id'])) {
            $pdo->prepare("INSERT INTO downloads (resource_id, user_id) VALUES (?, ?)")
                ->execute([$id, $_SESSION['user_id']]);
        }
        $pdo->prepare("UPDATE resources SET downloads = downloads + 1 WHERE id = ?")
            ->execute([$id]);

        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($r['file_original']) . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($file_path);
        exit;
    } else {
        die('File not found. Expected at: ' . htmlspecialchars($file_path));
    }
}

// Related resources (same category)
$related = $pdo->prepare("
    SELECT r.*, u.name AS uploader_name,
           ROUND(COALESCE(AVG(rt.stars),0),1) AS avg_rating
    FROM resources r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN ratings rt ON r.id = rt.resource_id
    WHERE r.category_id = ? AND r.id != ? AND r.status = 'active'
    GROUP BY r.id
    ORDER BY r.downloads DESC
    LIMIT 4
");
$related->execute([$r['category_id'], $id]);
$related_items = $related->fetchAll();

// File info
$ext        = strtolower(pathinfo($r['file_original'], PATHINFO_EXTENSION));
$file_path  = __DIR__ . '/../uploads/' . $r['file_name'];
$file_exists= file_exists($file_path);

$icon_emoji = match($ext) {
    'pdf'         => '📄',
    'doc','docx'  => '📝',
    'ppt','pptx'  => '📊',
    'zip','rar'   => '📦',
    default       => '📁',
};
$icon_bg = match($ext) {
    'pdf'         => 'rgba(239,68,68,0.15)',
    'doc','docx'  => 'rgba(59,130,246,0.15)',
    'ppt','pptx'  => 'rgba(247,194,111,0.15)',
    'zip','rar'   => 'rgba(124,111,247,0.15)',
    default       => 'rgba(100,100,100,0.15)',
};

function formatSize($bytes) {
    if ($bytes < 1024)     return $bytes . ' B';
    if ($bytes < 1048576)  return round($bytes/1024, 1) . ' KB';
    return round($bytes/1048576, 1) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($r['title']) ?> — Resource Hub</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:         #0a0a0f;
      --surface:    #13131a;
      --surface2:   #1c1c27;
      --accent:     #7c6ff7;
      --accent2:    #f7c26f;
      --text:       #f0f0f8;
      --muted:      #7a7a9a;
      --border:     rgba(255,255,255,0.07);
      --success:    #4ade80;
      --error:      #f87171;
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg); color: var(--text);
      min-height: 100vh; display: flex; flex-direction: column;
    }
    body::before {
      content: ''; position: fixed; inset: 0;
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
    .orb1 { width: 400px; height: 400px; background: rgba(124,111,247,0.09); top:-80px; left:-80px; }
    .orb2 { width: 300px; height: 300px; background: rgba(247,194,111,0.05); bottom:0; right:-60px; }

    /* NAV */
    nav {
      position: sticky; top: 0; z-index: 100;
      display: flex; align-items: center; justify-content: space-between;
      padding: 0.9rem 2rem;
      background: rgba(10,10,15,0.85); backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--border);
    }
    .logo {
      font-family: 'Syne', sans-serif; font-weight: 800;
      font-size: 1.2rem; color: var(--text); text-decoration: none;
    }
    .logo span { color: var(--accent); }
    .nav-right { display: flex; gap: 0.75rem; align-items: center; }
    .btn-ghost {
      padding: 0.45rem 1rem; border: 1px solid var(--border);
      background: transparent; color: var(--muted); border-radius: 8px;
      font-size: 0.85rem; cursor: pointer; text-decoration: none; transition: all 0.2s;
    }
    .btn-ghost:hover { color: var(--text); background: var(--surface); }
    .btn-accent {
      padding: 0.45rem 1rem; background: var(--accent);
      color: #fff; border: none; border-radius: 8px;
      font-size: 0.85rem; font-weight: 500; text-decoration: none; transition: opacity 0.2s;
    }
    .btn-accent:hover { opacity: 0.85; }

    /* BREADCRUMB */
    .breadcrumb {
      position: relative; z-index: 1;
      max-width: 1100px; margin: 1.5rem auto 0; padding: 0 2rem;
      display: flex; align-items: center; gap: 0.5rem;
      font-size: 0.8rem; color: var(--muted);
    }
    .breadcrumb a { color: var(--muted); text-decoration: none; transition: color 0.15s; }
    .breadcrumb a:hover { color: var(--text); }
    .breadcrumb span { color: var(--text); }

    /* PAGE LAYOUT */
    .page {
      position: relative; z-index: 1;
      max-width: 1100px; margin: 1.5rem auto 3rem;
      padding: 0 2rem;
      display: grid;
      grid-template-columns: 1fr 300px;
      gap: 2rem;
    }

    /* MAIN COLUMN */
    .main { min-width: 0; }

    /* RESOURCE HEADER */
    .resource-header {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 18px; padding: 1.75rem;
      margin-bottom: 1.5rem;
      animation: fadeUp 0.4s ease forwards;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(12px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .resource-top {
      display: flex; align-items: flex-start; gap: 1rem;
      margin-bottom: 1.25rem;
    }
    .file-icon-lg {
      width: 56px; height: 56px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem; flex-shrink: 0;
    }
    .resource-meta { flex: 1; min-width: 0; }
    .cat-badge {
      display: inline-flex; align-items: center; gap: 4px;
      font-size: 0.72rem; color: #a99ff8;
      background: rgba(124,111,247,0.1);
      border: 1px solid rgba(124,111,247,0.2);
      padding: 2px 8px; border-radius: 4px; margin-bottom: 0.5rem;
    }
    .resource-title {
      font-family: 'Syne', sans-serif;
      font-size: 1.3rem; font-weight: 800;
      letter-spacing: -0.3px; line-height: 1.3;
      margin-bottom: 0.5rem;
    }
    .resource-sub {
      font-size: 0.82rem; color: var(--muted);
      display: flex; flex-wrap: wrap; gap: 0.75rem;
    }
    .resource-sub span { display: flex; align-items: center; gap: 3px; }

    .resource-desc {
      font-size: 0.9rem; color: var(--muted);
      line-height: 1.7; padding-top: 1rem;
      border-top: 1px solid var(--border);
    }

    /* RATING DISPLAY */
    .rating-display {
      display: flex; align-items: center; gap: 0.75rem;
      padding: 1rem 0; border-top: 1px solid var(--border);
      margin-top: 1rem;
    }
    .stars-big { font-size: 1.3rem; color: var(--accent2); letter-spacing: 2px; }
    .rating-num {
      font-family: 'Syne', sans-serif;
      font-size: 1.4rem; font-weight: 700;
    }
    .rating-count { font-size: 0.8rem; color: var(--muted); }

    /* SUCCESS ALERT */
    .alert-success {
      background: rgba(74,222,128,0.1);
      border: 1px solid rgba(74,222,128,0.2);
      color: var(--success); border-radius: 10px;
      padding: 0.75rem 1rem; font-size: 0.875rem;
      margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;
    }

    /* RATE SECTION */
    .section-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 16px; padding: 1.5rem;
      margin-bottom: 1.5rem;
    }
    .section-title {
      font-family: 'Syne', sans-serif;
      font-size: 0.95rem; font-weight: 700;
      margin-bottom: 1rem;
    }

    /* STAR PICKER */
    .star-picker {
      display: flex; flex-direction: row-reverse;
      justify-content: flex-end; gap: 0.3rem;
      margin-bottom: 1rem;
    }
    .star-picker input { display: none; }
    .star-picker label {
      font-size: 1.8rem; cursor: pointer; color: var(--surface2);
      transition: color 0.15s; margin: 0;
    }
    .star-picker input:checked ~ label,
    .star-picker label:hover,
    .star-picker label:hover ~ label { color: var(--accent2); }

    .btn-rate {
      padding: 0.55rem 1.4rem;
      background: var(--accent); color: #fff;
      border: none; border-radius: 8px;
      font-family: 'Syne', sans-serif;
      font-size: 0.875rem; font-weight: 600;
      cursor: pointer; transition: opacity 0.2s;
    }
    .btn-rate:hover { opacity: 0.85; }

    .login-prompt {
      font-size: 0.875rem; color: var(--muted); text-align: center; padding: 0.5rem 0;
    }
    .login-prompt a { color: var(--accent); text-decoration: none; }
    .login-prompt a:hover { text-decoration: underline; }

    /* RELATED */
    .related-grid {
      display: flex; flex-direction: column; gap: 0.75rem;
    }
    .related-card {
      display: flex; align-items: center; gap: 0.75rem;
      background: var(--surface2); border: 1px solid var(--border);
      border-radius: 10px; padding: 0.75rem;
      text-decoration: none; transition: all 0.2s;
    }
    .related-card:hover {
      border-color: rgba(124,111,247,0.3);
      transform: translateX(3px);
    }
    .related-icon {
      width: 32px; height: 32px; border-radius: 8px;
      display: flex; align-items: center; justify-content: center; font-size: 1rem;
      flex-shrink: 0; background: rgba(124,111,247,0.1);
    }
    .related-info { flex: 1; min-width: 0; }
    .related-title {
      font-size: 0.82rem; font-weight: 500; color: var(--text);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .related-meta { font-size: 0.72rem; color: var(--muted); }

    /* SIDEBAR */
    .sidebar { display: flex; flex-direction: column; gap: 1.25rem; }

    .side-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 16px; padding: 1.25rem;
    }

    /* DOWNLOAD BTN */
    .btn-download {
      display: flex; align-items: center; justify-content: center; gap: 0.5rem;
      width: 100%; padding: 0.85rem;
      background: var(--accent); color: #fff;
      border: none; border-radius: 12px;
      font-family: 'Syne', sans-serif;
      font-size: 1rem; font-weight: 700;
      text-decoration: none; cursor: pointer;
      transition: all 0.2s;
    }
    .btn-download:hover { opacity: 0.88; transform: translateY(-1px); }

    .file-details { margin-top: 1rem; display: flex; flex-direction: column; gap: 0.5rem; }
    .file-detail-row {
      display: flex; justify-content: space-between;
      font-size: 0.8rem;
    }
    .file-detail-row .label { color: var(--muted); }
    .file-detail-row .value { color: var(--text); font-weight: 500; }

    /* UPLOADER CARD */
    .uploader-row {
      display: flex; align-items: center; gap: 0.75rem;
    }
    .avatar-lg {
      width: 40px; height: 40px; border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      display: flex; align-items: center; justify-content: center;
      font-size: 0.85rem; font-weight: 700; color: #fff; flex-shrink: 0;
    }
    .uploader-name {
      font-weight: 500; font-size: 0.9rem; color: var(--text);
    }
    .uploader-sub { font-size: 0.75rem; color: var(--muted); }

    footer {
      position: relative; z-index: 1;
      border-top: 1px solid var(--border);
      padding: 1.5rem; text-align: center;
      font-size: 0.78rem; color: var(--muted);
      margin-top: auto;
    }

    @media (max-width: 768px) {
      .page { grid-template-columns: 1fr; }
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
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="upload.php" class="btn-accent">+ Upload</a>
      <a href="logout.php" class="btn-ghost">Log out</a>
    <?php else: ?>
      <a href="login.php" class="btn-ghost">Log in</a>
      <a href="register.php" class="btn-accent">Sign up</a>
    <?php endif; ?>
  </div>
</nav>

<!-- BREADCRUMB -->
<div class="breadcrumb">
  <a href='../index.php'>Home</a> › 
  <a href="browse.php">Browse</a> › 
  <a href="browse.php?category=<?= $r['category_id'] ?>"><?= $r['category_icon'] ?> <?= htmlspecialchars($r['category_name']) ?></a> ›
  <span><?= htmlspecialchars(mb_strimwidth($r['title'], 0, 40, '…')) ?></span>
</div>

<!-- PAGE -->
<div class="page">

  <!-- MAIN -->
  <div class="main">

    <?php if (isset($_GET['uploaded'])): ?>
      <div class="alert-success">✅ Your resource was uploaded successfully! Students can now find and download it.</div>
    <?php endif; ?>

    <!-- RESOURCE HEADER -->
    <div class="resource-header">
      <div class="resource-top">
        <div class="file-icon-lg" style="background: <?= $icon_bg ?>">
          <?= $icon_emoji ?>
        </div>
        <div class="resource-meta">
          <div class="cat-badge"><?= $r['category_icon'] ?> <?= htmlspecialchars($r['category_name']) ?></div>
          <h1 class="resource-title"><?= htmlspecialchars($r['title']) ?></h1>
          <div class="resource-sub">
            <?php if ($r['subject_code']): ?>
              <span>📌 <?= htmlspecialchars($r['subject_code']) ?></span>
            <?php endif; ?>
            <?php if ($r['year_level']): ?>
              <span>🎓 Year <?= $r['year_level'] ?></span>
            <?php endif; ?>
            <?php if ($r['semester']): ?>
              <span>📅 Semester <?= $r['semester'] ?></span>
            <?php endif; ?>
            <span>⬇️ <?= number_format($r['downloads']) ?> downloads</span>
            <span>🕒 <?= date('M j, Y', strtotime($r['created_at'])) ?></span>
          </div>
        </div>
      </div>

      <?php if ($r['description']): ?>
        <div class="resource-desc"><?= nl2br(htmlspecialchars($r['description'])) ?></div>
      <?php endif; ?>

      <div class="rating-display">
        <div class="stars-big">
          <?php
            $full  = floor($r['avg_rating']);
            $empty = 5 - $full;
            echo str_repeat('★', $full) . str_repeat('☆', $empty);
          ?>
        </div>
        <div class="rating-num"><?= $r['avg_rating'] ?></div>
        <div class="rating-count">(<?= $r['rating_count'] ?> rating<?= $r['rating_count'] != 1 ? 's' : '' ?>)</div>
      </div>
    </div>

    <!-- RATING FORM -->
    <div class="section-card" id="ratings">
      <div class="section-title">⭐ Rate this resource</div>
      <?php if (isset($_SESSION['user_id'])): ?>
        <form method="POST" action="resource.php?id=<?= $id ?>#ratings">
          <div class="star-picker">
            <?php for ($s = 5; $s >= 1; $s--): ?>
              <input type="radio" name="stars" id="s<?= $s ?>" value="<?= $s ?>"
                     <?= $my_rating === $s ? 'checked' : '' ?>>
              <label for="s<?= $s ?>">★</label>
            <?php endfor; ?>
          </div>
          <button type="submit" class="btn-rate">
            <?= $my_rating > 0 ? 'Update rating' : 'Submit rating' ?>
          </button>
          <?php if ($my_rating > 0): ?>
            <span style="font-size:0.8rem; color:var(--muted); margin-left:0.75rem;">
              Your current rating: <?= $my_rating ?>★
            </span>
          <?php endif; ?>
        </form>
      <?php else: ?>
        <div class="login-prompt">
          <a href="login.php">Log in</a> to rate this resource
        </div>
      <?php endif; ?>
    </div>

    <!-- RELATED RESOURCES -->
    <?php if (!empty($related_items)): ?>
    <div class="section-card">
      <div class="section-title">📚 More from <?= htmlspecialchars($r['category_name']) ?></div>
      <div class="related-grid">
        <?php foreach ($related_items as $rel):
          $rel_ext = strtolower(pathinfo($rel['file_original'], PATHINFO_EXTENSION));
          $rel_icon = match($rel_ext) {
            'pdf'        => '📄',
            'doc','docx' => '📝',
            'ppt','pptx' => '📊',
            'zip','rar'  => '📦',
            default      => '📁',
          };
        ?>
          <a href="resource.php?id=<?= $rel['id'] ?>" class="related-card">
            <div class="related-icon"><?= $rel_icon ?></div>
            <div class="related-info">
              <div class="related-title"><?= htmlspecialchars($rel['title']) ?></div>
              <div class="related-meta">⬇️ <?= $rel['downloads'] ?> · ⭐ <?= $rel['avg_rating'] ?> · <?= $rel['uploader_name'] ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- SIDEBAR -->
  <aside class="sidebar">

    <!-- DOWNLOAD -->
    <div class="side-card">
      <?php if ($file_exists): ?>
        <a href="resource.php?id=<?= $id ?>&download=1" class="btn-download">
          ⬇️ Download file
        </a>
      <?php else: ?>
        <div style="text-align:center; color:var(--error); font-size:0.875rem; padding:0.5rem 0;">
          ⚠️ File not available
        </div>
      <?php endif; ?>

      <div class="file-details">
        <div class="file-detail-row">
          <span class="label">File type</span>
          <span class="value"><?= strtoupper($ext) ?></span>
        </div>
        <div class="file-detail-row">
          <span class="label">File size</span>
          <span class="value"><?= formatSize($r['file_size']) ?></span>
        </div>
        <div class="file-detail-row">
          <span class="label">Downloads</span>
          <span class="value"><?= number_format($r['downloads']) ?></span>
        </div>
        <div class="file-detail-row">
          <span class="label">Uploaded</span>
          <span class="value"><?= date('M j, Y', strtotime($r['created_at'])) ?></span>
        </div>
      </div>
    </div>

    <!-- UPLOADER INFO -->
    <div class="side-card">
      <div class="section-title" style="margin-bottom:0.75rem;">👤 Uploaded by</div>
      <div class="uploader-row">
        <div class="avatar-lg"><?= strtoupper(substr($r['uploader_name'], 0, 2)) ?></div>
        <div>
          <div class="uploader-name"><?= htmlspecialchars($r['uploader_name']) ?></div>
          <div class="uploader-sub">Student contributor</div>
        </div>
      </div>
    </div>

    <!-- BACK LINK -->
    <a href="browse.php?category=<?= $r['category_id'] ?>" class="btn-ghost"
       style="display:block; text-align:center; padding:0.65rem;">
      ← Back to <?= htmlspecialchars($r['category_name']) ?>
    </a>

    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $r['uploader_id']): ?>
    <a href="delete.php?id=<?= $id ?>" class="btn-ghost"
       style="display:block; text-align:center; padding:0.65rem; color:var(--error); border-color:rgba(248,113,113,0.2);"
       onclick="return confirm('Are you sure you want to delete this resource?')">
      🗑 Delete resource
    </a>
    <?php endif; ?>

  </aside>

</div>

<footer>Resource Hub &copy; 2026 · University of Colombo</footer>

</body>
</html>