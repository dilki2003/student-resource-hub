<?php
session_start();
require_once '../config/db.php';

// --- Filters from GET ---
$search   = trim($_GET['q']          ?? '');
$cat_id   = intval($_GET['category'] ?? 0);
$year_lvl = intval($_GET['year']     ?? 0);
$sort     = $_GET['sort']            ?? 'newest';

// --- Build query ---
$where  = ["r.status = 'active'"];
$params = [];

if ($search !== '') {
    $where[]  = "(r.title LIKE ? OR r.subject_code LIKE ? OR COALESCE(r.description,'') LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($cat_id > 0) {
    $where[]  = "r.category_id = ?";
    $params[] = $cat_id;
}
if ($year_lvl > 0) {
    $where[]  = "r.year_level = ?";
    $params[] = $year_lvl;
}

$order = match($sort) {
    'popular'  => 'downloads DESC',
    'rating'   => 'avg_rating DESC',
    default    => 'created_at DESC',
};

$sql = "
    SELECT * FROM (
        SELECT r.*,
               u.name AS uploader_name,
               c.name AS category_name,
               c.icon AS category_icon,
               ROUND(COALESCE(AVG(rt.stars), 0), 1) AS avg_rating,
               COUNT(rt.id) AS rating_count
        FROM resources r
        JOIN users      u  ON r.user_id     = u.id
        JOIN categories c  ON r.category_id = c.id
        LEFT JOIN ratings rt ON r.id        = rt.resource_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY r.id
    ) AS sub
    ORDER BY $order
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resources = $stmt->fetchAll();

// --- All categories for sidebar ---
$cats = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// --- Total count ---
$total = count($resources);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Browse Resources — Resource Hub</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:           #0a0a0f;
      --surface:      #13131a;
      --surface2:     #1c1c27;
      --accent:       #7c6ff7;
      --accent2:      #f7c26f;
      --text:         #f0f0f8;
      --muted:        #7a7a9a;
      --border:       rgba(255,255,255,0.07);
      --card-radius:  14px;
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
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
      background: transparent; color: var(--text); border-radius: 8px;
      font-family: 'DM Sans', sans-serif; font-size: 0.85rem; cursor: pointer;
      text-decoration: none; transition: all 0.2s;
    }
    .btn-ghost:hover { background: var(--surface); }
    .btn-accent {
      padding: 0.45rem 1rem; background: var(--accent);
      color: #fff; border: none; border-radius: 8px;
      font-family: 'DM Sans', sans-serif; font-size: 0.85rem;
      font-weight: 500; cursor: pointer; text-decoration: none;
      transition: opacity 0.2s;
    }
    .btn-accent:hover { opacity: 0.85; }

    /* LAYOUT */
    .page {
      position: relative; z-index: 1;
      max-width: 1200px; margin: 0 auto;
      padding: 2rem;
      display: grid;
      grid-template-columns: 240px 1fr;
      gap: 2rem;
    }

    /* SIDEBAR */
    .sidebar { display: flex; flex-direction: column; gap: 1.5rem; }

    .sidebar-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--card-radius);
      padding: 1.25rem;
    }
    .sidebar-title {
      font-family: 'Syne', sans-serif;
      font-size: 0.8rem; font-weight: 700;
      color: var(--muted); letter-spacing: 0.8px;
      text-transform: uppercase; margin-bottom: 1rem;
    }

    .cat-list { display: flex; flex-direction: column; gap: 0.3rem; }
    .cat-item {
      display: flex; align-items: center; justify-content: space-between;
      padding: 0.5rem 0.75rem; border-radius: 8px;
      text-decoration: none; color: var(--muted);
      font-size: 0.875rem; transition: all 0.15s;
      border: 1px solid transparent;
    }
    .cat-item:hover { background: var(--surface2); color: var(--text); }
    .cat-item.active {
      background: rgba(124,111,247,0.12);
      border-color: rgba(124,111,247,0.25);
      color: #a99ff8;
    }
    .cat-left { display: flex; align-items: center; gap: 0.5rem; }
    .cat-count {
      font-size: 0.72rem; background: var(--surface2);
      padding: 1px 6px; border-radius: 999px; color: var(--muted);
    }

    .year-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; }
    .year-btn {
      padding: 0.5rem; text-align: center;
      background: var(--surface2); border: 1px solid var(--border);
      border-radius: 8px; color: var(--muted); font-size: 0.82rem;
      text-decoration: none; transition: all 0.15s;
    }
    .year-btn:hover { border-color: rgba(124,111,247,0.3); color: var(--text); }
    .year-btn.active {
      background: rgba(124,111,247,0.12);
      border-color: rgba(124,111,247,0.3); color: #a99ff8;
    }

    /* MAIN */
    .main { min-width: 0; }

    /* SEARCH + SORT BAR */
    .top-bar {
      display: flex; gap: 0.75rem; align-items: center;
      margin-bottom: 1.5rem; flex-wrap: wrap;
    }
    .search-wrap {
      flex: 1; min-width: 200px;
      display: flex; align-items: center;
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 10px; padding: 0.6rem 1rem; gap: 0.6rem;
      transition: border-color 0.2s;
    }
    .search-wrap:focus-within { border-color: rgba(124,111,247,0.4); }
    .search-wrap input {
      flex: 1; background: none; border: none; outline: none;
      color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 0.9rem;
    }
    .search-wrap input::placeholder { color: var(--muted); }
    .search-btn {
      background: var(--accent); color: #fff; border: none;
      border-radius: 6px; padding: 0.35rem 0.9rem;
      font-size: 0.82rem; font-weight: 500; cursor: pointer;
    }

    .sort-select {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 10px; padding: 0.6rem 1rem;
      color: var(--text); font-family: 'DM Sans', sans-serif;
      font-size: 0.875rem; outline: none; cursor: pointer;
    }
    .sort-select option { background: var(--surface2); }

    /* RESULTS HEADER */
    .results-header {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 1rem;
    }
    .results-count {
      font-size: 0.85rem; color: var(--muted);
    }
    .results-count strong { color: var(--text); }

    /* CARDS GRID */
    .cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1rem;
    }

    .resource-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--card-radius);
      padding: 1.1rem; cursor: pointer;
      text-decoration: none; display: block;
      transition: all 0.22s;
      animation: fadeUp 0.4s ease forwards;
      opacity: 0;
    }
    @keyframes fadeUp {
      to { opacity: 1; transform: translateY(0); }
      from { opacity: 0; transform: translateY(10px); }
    }
    .resource-card:hover {
      border-color: rgba(124,111,247,0.35);
      transform: translateY(-2px);
      background: var(--surface2);
    }

    .card-top {
      display: flex; align-items: flex-start;
      justify-content: space-between; margin-bottom: 0.75rem;
    }
    .file-icon {
      width: 38px; height: 38px; border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem;
    }
    .fi-pdf  { background: rgba(239,68,68,0.15); }
    .fi-doc  { background: rgba(59,130,246,0.15); }
    .fi-ppt  { background: rgba(247,194,111,0.15); }
    .fi-zip  { background: rgba(124,111,247,0.15); }
    .fi-other{ background: rgba(100,100,100,0.15); }

    .type-badge {
      font-size: 0.68rem; padding: 2px 7px; border-radius: 4px;
      font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px;
      background: var(--surface2); color: var(--muted);
      border: 1px solid var(--border);
    }

    .card-title {
      font-family: 'Syne', sans-serif;
      font-size: 0.9rem; font-weight: 600;
      color: var(--text); margin-bottom: 0.25rem; line-height: 1.35;
    }
    .card-meta-line {
      font-size: 0.78rem; color: var(--muted); margin-bottom: 0.9rem;
    }
    .card-cat {
      display: inline-flex; align-items: center; gap: 3px;
      font-size: 0.72rem; color: #a99ff8;
      background: rgba(124,111,247,0.1);
      border: 1px solid rgba(124,111,247,0.15);
      padding: 2px 7px; border-radius: 4px; margin-bottom: 0.6rem;
    }

    .card-footer {
      display: flex; align-items: center;
      justify-content: space-between; padding-top: 0.75rem;
      border-top: 1px solid var(--border);
    }
    .uploader {
      display: flex; align-items: center; gap: 0.4rem;
      font-size: 0.78rem; color: var(--muted);
    }
    .avatar {
      width: 20px; height: 20px; border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      display: flex; align-items: center; justify-content: center;
      font-size: 0.58rem; font-weight: 700; color: #fff;
    }
    .card-stats {
      display: flex; gap: 0.6rem;
      font-size: 0.75rem; color: var(--muted);
    }

    /* STARS */
    .stars { color: var(--accent2); font-size: 0.78rem; }

    /* EMPTY STATE */
    .empty {
      grid-column: 1 / -1;
      text-align: center; padding: 4rem 2rem;
    }
    .empty-icon {
      font-size: 3rem; margin-bottom: 1rem; opacity: 0.4;
    }
    .empty h3 {
      font-family: 'Syne', sans-serif;
      font-size: 1.1rem; margin-bottom: 0.5rem;
    }
    .empty p { font-size: 0.875rem; color: var(--muted); }

    /* ACTIVE FILTERS */
    .active-filters {
      display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem;
    }
    .filter-tag {
      display: inline-flex; align-items: center; gap: 0.4rem;
      background: rgba(124,111,247,0.1); border: 1px solid rgba(124,111,247,0.2);
      color: #a99ff8; font-size: 0.78rem; padding: 3px 10px;
      border-radius: 999px; text-decoration: none;
    }
    .filter-tag:hover { background: rgba(124,111,247,0.2); }

    footer {
      position: relative; z-index: 1;
      border-top: 1px solid var(--border);
      padding: 1.5rem; text-align: center;
      font-size: 0.78rem; color: var(--muted);
    }

    @media (max-width: 768px) {
      .page { grid-template-columns: 1fr; }
      .sidebar { display: none; }
    }
  </style>
</head>
<body>

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

<!-- PAGE -->
<div class="page">

  <!-- SIDEBAR -->
  <aside class="sidebar">

    <!-- Categories -->
    <div class="sidebar-card">
      <div class="sidebar-title">Subjects</div>
      <div class="cat-list">
        <a href="browse.php?<?= http_build_query(array_merge($_GET, ['category' => 0])) ?>"
           class="cat-item <?= $cat_id === 0 ? 'active' : '' ?>">
          <span class="cat-left">📚 All Subjects</span>
        </a>
        <?php foreach ($cats as $cat):
          $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM resources WHERE category_id = ? AND status = 'active'");
          $count_stmt->execute([$cat['id']]);
          $count = $count_stmt->fetchColumn();
        ?>
        <a href="browse.php?<?= http_build_query(array_merge($_GET, ['category' => $cat['id']])) ?>"
           class="cat-item <?= $cat_id === (int)$cat['id'] ? 'active' : '' ?>">
          <span class="cat-left"><?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?></span>
          <span class="cat-count"><?= $count ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Year filter -->
    <div class="sidebar-card">
      <div class="sidebar-title">Academic Year</div>
      <div class="year-btns">
        <a href="browse.php?<?= http_build_query(array_merge($_GET, ['year' => 0])) ?>"
           class="year-btn <?= $year_lvl === 0 ? 'active' : '' ?>">All</a>
        <?php for ($y = 1; $y <= 4; $y++): ?>
        <a href="browse.php?<?= http_build_query(array_merge($_GET, ['year' => $y])) ?>"
           class="year-btn <?= $year_lvl === $y ? 'active' : '' ?>">Year <?= $y ?></a>
        <?php endfor; ?>
      </div>
    </div>

  </aside>

  <!-- MAIN CONTENT -->
  <main class="main">

    <!-- Search + Sort -->
    <form method="GET" action="browse.php" style="display:contents;">
      <?php if ($cat_id > 0): ?>
        <input type="hidden" name="category" value="<?= $cat_id ?>"/>
      <?php endif; ?>
      <?php if ($year_lvl > 0): ?>
        <input type="hidden" name="year" value="<?= $year_lvl ?>"/>
      <?php endif; ?>
      <div class="top-bar">
        <div class="search-wrap">
          <span style="color:var(--muted);">🔍</span>
          <input type="text" name="q" placeholder="Search notes, past papers, subjects…"
                 value="<?= htmlspecialchars($search) ?>"/>
          <button type="submit" class="search-btn">Search</button>
        </div>
        <select name="sort" class="sort-select" onchange="this.form.submit()">
          <option value="newest"  <?= $sort === 'newest'  ? 'selected' : '' ?>>Newest first</option>
          <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most downloaded</option>
          <option value="rating"  <?= $sort === 'rating'  ? 'selected' : '' ?>>Top rated</option>
        </select>
      </div>
    </form>

    <!-- Active filters -->
    <?php if ($search !== '' || $cat_id > 0 || $year_lvl > 0): ?>
    <div class="active-filters">
      <?php if ($search !== ''): ?>
        <a href="browse.php?<?= http_build_query(array_merge($_GET, ['q' => ''])) ?>" class="filter-tag">
          🔍 "<?= htmlspecialchars($search) ?>" ✕
        </a>
      <?php endif; ?>
      <?php if ($cat_id > 0):
        $catName = '';
        foreach ($cats as $c) if ((int)$c['id'] === $cat_id) $catName = $c['name'];
      ?>
        <a href="browse.php?<?= http_build_query(array_merge($_GET, ['category' => 0])) ?>" class="filter-tag">
          📁 <?= htmlspecialchars($catName) ?> ✕
        </a>
      <?php endif; ?>
      <?php if ($year_lvl > 0): ?>
        <a href="browse.php?<?= http_build_query(array_merge($_GET, ['year' => 0])) ?>" class="filter-tag">
          🎓 Year <?= $year_lvl ?> ✕
        </a>
      <?php endif; ?>
      <a href="browse.php" class="filter-tag" style="color:var(--muted); border-color:var(--border); background:transparent;">
        Clear all
      </a>
    </div>
    <?php endif; ?>

    <!-- Results count -->
    <div class="results-header">
      <span class="results-count">
        <strong><?= $total ?></strong> resource<?= $total !== 1 ? 's' : '' ?> found
        <?= $search !== '' ? ' for "' . htmlspecialchars($search) . '"' : '' ?>
      </span>
    </div>

    <!-- Cards -->
    <div class="cards-grid">
      <?php if (empty($resources)): ?>
        <div class="empty">
          <div class="empty-icon">📭</div>
          <h3>No resources found</h3>
          <p>Try a different search or be the first to upload!</p>
        </div>

      <?php else: foreach ($resources as $i => $r):
        $ext = strtolower(pathinfo($r['file_original'], PATHINFO_EXTENSION));
        $icon_class = match($ext) {
          'pdf'           => 'fi-pdf',
          'doc', 'docx'   => 'fi-doc',
          'ppt', 'pptx'   => 'fi-ppt',
          'zip', 'rar'    => 'fi-zip',
          default         => 'fi-other',
        };
        $icon_emoji = match($ext) {
          'pdf'           => '📄',
          'doc', 'docx'   => '📝',
          'ppt', 'pptx'   => '📊',
          'zip', 'rar'    => '📦',
          default         => '📁',
        };
        $initials = strtoupper(substr($r['uploader_name'], 0, 2));
        $stars_full  = floor($r['avg_rating']);
        $stars_str   = str_repeat('★', $stars_full) . str_repeat('☆', 5 - $stars_full);
        $delay = ($i % 6) * 60;
      ?>
        <a class="resource-card" href="resource.php?id=<?= $r['id'] ?>"
           style="animation-delay: <?= $delay ?>ms;">
          <div class="card-top">
            <div class="file-icon <?= $icon_class ?>"><?= $icon_emoji ?></div>
            <span class="type-badge"><?= strtoupper($ext) ?></span>
          </div>
          <div class="card-cat">
            <?= $r['category_icon'] ?> <?= htmlspecialchars($r['category_name']) ?>
          </div>
          <div class="card-title"><?= htmlspecialchars($r['title']) ?></div>
          <div class="card-meta-line">
            <?= $r['subject_code'] ? htmlspecialchars($r['subject_code']) . ' · ' : '' ?>
            <?= $r['year_level'] ? 'Year ' . $r['year_level'] : '' ?>
            <?= $r['semester'] ? ' · Sem ' . $r['semester'] : '' ?>
          </div>
          <div class="card-footer">
            <div class="uploader">
              <div class="avatar"><?= $initials ?></div>
              <?= htmlspecialchars($r['uploader_name']) ?>
            </div>
            <div class="card-stats">
              <span class="stars"><?= $stars_str ?></span>
              <span>⬇️ <?= number_format($r['downloads']) ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; endif; ?>
    </div>

  </main>
</div>

<footer>Resource Hub &copy; 2026 · University of Moratuwa</footer>

</body>
</html>