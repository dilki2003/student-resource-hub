<?php
session_start();
require_once 'db.php';

// Admin only — redirect if not logged in or not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

global $pdo;
$success = '';
$error = '';

// --- Handle POST actions ---

// Delete a resource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Delete resource
    if ($action === 'delete_resource' && isset($_POST['resource_id'])) {
        $rid = (int)$_POST['resource_id'];
        // Get file path first
        $stmt = $pdo->prepare("SELECT file_path FROM resources WHERE id = ?");
        $stmt->execute([$rid]);
        $row = $stmt->fetch();
        if ($row && file_exists($row['file_path'])) {
            unlink($row['file_path']);
        }
        $pdo->prepare("DELETE FROM ratings WHERE resource_id = ?")->execute([$rid]);
        $pdo->prepare("DELETE FROM downloads WHERE resource_id = ?")->execute([$rid]);
        $pdo->prepare("DELETE FROM resources WHERE id = ?")->execute([$rid]);
        $success = 'Resource deleted successfully.';
    }

    // Delete user
    if ($action === 'delete_user' && isset($_POST['user_id'])) {
        $uid = (int)$_POST['user_id'];
        if ($uid !== $_SESSION['user_id']) {
            $pdo->prepare("DELETE FROM ratings WHERE user_id = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM downloads WHERE user_id = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM resources WHERE user_id = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $success = 'User deleted successfully.';
        } else {
            $error = 'You cannot delete your own account.';
        }
    }

    // Toggle user ban
    if ($action === 'toggle_ban' && isset($_POST['user_id'])) {
        $uid = (int)$_POST['user_id'];
        $stmt = $pdo->prepare("SELECT is_banned FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        if ($user) {
            $newStatus = $user['is_banned'] ? 0 : 1;
            $pdo->prepare("UPDATE users SET is_banned = ? WHERE id = ?")->execute([$newStatus, $uid]);
            $success = $newStatus ? 'User banned.' : 'User unbanned.';
        }
    }

    // Add category
    if ($action === 'add_category' && !empty($_POST['cat_name'])) {
        $catName = trim($_POST['cat_name']);
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->execute([$catName]);
        if ($stmt->fetch()) {
            $error = 'Category already exists.';
        } else {
            $pdo->prepare("INSERT INTO categories (name) VALUES (?)")->execute([$catName]);
            $success = 'Category added.';
        }
    }

    // Delete category
    if ($action === 'delete_category' && isset($_POST['cat_id'])) {
        $cid = (int)$_POST['cat_id'];
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$cid]);
        $success = 'Category deleted.';
    }
}

// --- Fetch data ---
$activeTab = $_GET['tab'] ?? 'dashboard';

// Dashboard stats
$totalResources  = $pdo->query("SELECT COUNT(*) FROM resources")->fetchColumn();
$totalUsers      = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$totalDownloads  = $pdo->query("SELECT COALESCE(SUM(downloads),0) FROM resources")->fetchColumn();
$totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// Recent resources
$recentResources = $pdo->query("
    SELECT r.*, u.name AS uploader, c.name AS category_name
    FROM resources r
    JOIN users u ON r.user_id = u.id
    JOIN categories c ON r.category_id = c.id
    ORDER BY r.created_at DESC
    LIMIT 5
")->fetchAll();

// All resources
$allResources = $pdo->query("
    SELECT r.*, u.name AS uploader, c.name AS category_name
    FROM resources r
    JOIN users u ON r.user_id = u.id
    JOIN categories c ON r.category_id = c.id
    ORDER BY r.created_at DESC
")->fetchAll();

// All users
$allUsers = $pdo->query("
    SELECT u.*, COUNT(r.id) AS resource_count
    FROM users u
    LEFT JOIN resources r ON u.id = r.user_id
    WHERE u.role = 'student'
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();

// Categories
$categories = $pdo->query("
    SELECT c.*, COUNT(r.id) AS resource_count
    FROM categories c
    LEFT JOIN resources r ON c.id = r.category_id
    GROUP BY c.id
    ORDER BY c.name ASC
")->fetchAll();

// Monthly upload stats (last 6 months)
$monthlyStats = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%b') AS month, COUNT(*) AS count
    FROM resources
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b')
    ORDER BY MIN(created_at) ASC
")->fetchAll();

// Top downloads
$topResources = $pdo->query("
    SELECT r.title, r.downloads, c.name AS category_name
    FROM resources r
    JOIN categories c ON r.category_id = c.id
    ORDER BY r.downloads DESC
    LIMIT 5
")->fetchAll();

$adminName = $_SESSION['name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel — Resource Hub</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap');

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:       #0d0f14;
    --surface:  #13161e;
    --card:     #191d28;
    --border:   #252a38;
    --accent:   #5b6af5;
    --accent2:  #38d9a9;
    --danger:   #f05050;
    --warning:  #f5a623;
    --text:     #e8eaf0;
    --muted:    #6b7280;
    --sidebar-w: 240px;
  }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
  }

  /* ── Sidebar ── */
  .sidebar {
    width: var(--sidebar-w);
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 100;
  }

  .sidebar-logo {
    padding: 24px 20px 20px;
    border-bottom: 1px solid var(--border);
  }
  .sidebar-logo a { text-decoration: none; }
  .sidebar-logo .brand {
    font-family: 'Syne', sans-serif;
    font-weight: 800;
    font-size: 1.15rem;
    color: var(--text);
    letter-spacing: -0.3px;
  }
  .sidebar-logo .brand span { color: var(--accent); }
  .admin-badge {
    display: inline-block;
    margin-top: 4px;
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--accent2);
    background: rgba(56,217,169,0.1);
    padding: 2px 8px;
    border-radius: 20px;
  }

  .sidebar-nav {
    flex: 1;
    padding: 16px 12px;
    overflow-y: auto;
  }

  .nav-label {
    font-size: 0.65rem;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--muted);
    padding: 12px 8px 6px;
    font-weight: 600;
  }

  .nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    border-radius: 8px;
    text-decoration: none;
    color: var(--muted);
    font-size: 0.88rem;
    font-weight: 500;
    transition: all 0.15s;
    margin-bottom: 2px;
  }
  .nav-item:hover { background: var(--card); color: var(--text); }
  .nav-item.active { background: rgba(91,106,245,0.15); color: var(--accent); }
  .nav-item .icon { font-size: 1rem; width: 20px; text-align: center; }

  .sidebar-footer {
    padding: 16px 12px;
    border-top: 1px solid var(--border);
  }
  .admin-info {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border-radius: 8px;
    background: var(--card);
  }
  .admin-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.8rem; color: #fff;
    flex-shrink: 0;
  }
  .admin-name { font-size: 0.82rem; font-weight: 600; color: var(--text); }
  .admin-role { font-size: 0.7rem; color: var(--muted); }
  .logout-btn {
    display: block;
    margin-top: 8px;
    text-align: center;
    padding: 7px;
    border-radius: 8px;
    background: rgba(240,80,80,0.1);
    color: var(--danger);
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 600;
    transition: background 0.15s;
  }
  .logout-btn:hover { background: rgba(240,80,80,0.2); }

  /* ── Main ── */
  .main {
    margin-left: var(--sidebar-w);
    flex: 1;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }

  .topbar {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 16px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky; top: 0; z-index: 50;
  }
  .topbar-title {
    font-family: 'Syne', sans-serif;
    font-weight: 700;
    font-size: 1.1rem;
  }
  .topbar-right { display: flex; gap: 10px; align-items: center; }
  .view-site-btn {
    padding: 7px 14px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    text-decoration: none;
    font-size: 0.82rem;
    font-weight: 500;
    transition: border-color 0.15s;
  }
  .view-site-btn:hover { border-color: var(--accent); color: var(--accent); }

  .content {
    padding: 32px;
    flex: 1;
  }

  /* ── Alerts ── */
  .alert {
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 24px;
    font-size: 0.88rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .alert-success { background: rgba(56,217,169,0.1); border: 1px solid rgba(56,217,169,0.3); color: var(--accent2); }
  .alert-error   { background: rgba(240,80,80,0.1);  border: 1px solid rgba(240,80,80,0.3);  color: var(--danger); }

  /* ── Stat Cards ── */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 28px;
  }
  .stat-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px;
    position: relative;
    overflow: hidden;
  }
  .stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
  }
  .stat-card.blue::before  { background: var(--accent); }
  .stat-card.green::before { background: var(--accent2); }
  .stat-card.orange::before{ background: var(--warning); }
  .stat-card.red::before   { background: var(--danger); }

  .stat-icon {
    font-size: 1.5rem;
    margin-bottom: 12px;
    display: block;
  }
  .stat-value {
    font-family: 'Syne', sans-serif;
    font-size: 2rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 4px;
  }
  .stat-label { font-size: 0.8rem; color: var(--muted); font-weight: 500; }

  /* ── Tables ── */
  .section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
  }
  .section-title {
    font-family: 'Syne', sans-serif;
    font-size: 1rem;
    font-weight: 700;
  }

  .table-wrap {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 28px;
  }

  table { width: 100%; border-collapse: collapse; }
  thead tr { background: var(--surface); }
  th {
    padding: 12px 16px;
    text-align: left;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--muted);
    border-bottom: 1px solid var(--border);
  }
  td {
    padding: 13px 16px;
    font-size: 0.85rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
  }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: rgba(255,255,255,0.02); }

  .file-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 5px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .badge-pdf   { background: rgba(240,80,80,0.15);  color: #f05050; }
  .badge-docx  { background: rgba(91,106,245,0.15); color: #5b6af5; }
  .badge-pptx  { background: rgba(245,166,35,0.15); color: #f5a623; }
  .badge-other { background: rgba(107,114,128,0.15);color: #9ca3af; }

  .year-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 5px;
    font-size: 0.72rem;
    font-weight: 600;
    background: rgba(56,217,169,0.1);
    color: var(--accent2);
  }

  .ban-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 5px;
    font-size: 0.72rem;
    font-weight: 600;
    background: rgba(240,80,80,0.15);
    color: var(--danger);
  }

  /* ── Buttons ── */
  .btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 7px;
    border: none;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.15s;
    text-decoration: none;
    font-family: 'DM Sans', sans-serif;
  }
  .btn:hover { opacity: 0.8; }
  .btn-danger  { background: rgba(240,80,80,0.15);  color: var(--danger); }
  .btn-warning { background: rgba(245,166,35,0.15); color: var(--warning); }
  .btn-primary { background: rgba(91,106,245,0.15); color: var(--accent); }
  .btn-success { background: rgba(56,217,169,0.15); color: var(--accent2); }
  .btn-sm { padding: 5px 10px; font-size: 0.74rem; }

  /* ── Search bar ── */
  .search-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
  }
  .search-bar input {
    flex: 1;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 9px;
    padding: 9px 14px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    outline: none;
  }
  .search-bar input:focus { border-color: var(--accent); }

  /* ── Dashboard charts ── */
  .dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 28px;
  }
  .chart-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px;
  }
  .chart-title {
    font-family: 'Syne', sans-serif;
    font-size: 0.9rem;
    font-weight: 700;
    margin-bottom: 16px;
    color: var(--text);
  }

  /* Bar chart */
  .bar-chart { display: flex; align-items: flex-end; gap: 8px; height: 120px; }
  .bar-group { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; height: 100%; justify-content: flex-end; }
  .bar {
    width: 100%;
    background: linear-gradient(180deg, var(--accent), rgba(91,106,245,0.4));
    border-radius: 5px 5px 0 0;
    min-height: 4px;
    transition: opacity 0.2s;
  }
  .bar:hover { opacity: 0.75; }
  .bar-label { font-size: 0.68rem; color: var(--muted); }
  .bar-count { font-size: 0.68rem; color: var(--text); font-weight: 600; }

  /* Top resources list */
  .top-list { display: flex; flex-direction: column; gap: 10px; }
  .top-item {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .top-rank {
    width: 24px; height: 24px;
    border-radius: 50%;
    background: var(--surface);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--muted);
    flex-shrink: 0;
  }
  .top-info { flex: 1; min-width: 0; }
  .top-name { font-size: 0.82rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .top-cat  { font-size: 0.7rem; color: var(--muted); }
  .top-dl   { font-size: 0.78rem; font-weight: 700; color: var(--accent2); flex-shrink: 0; }

  /* ── Category form ── */
  .cat-form {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
  }
  .cat-form input {
    flex: 1;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 9px;
    padding: 9px 14px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    outline: none;
  }
  .cat-form input:focus { border-color: var(--accent2); }
  .cat-form button {
    padding: 9px 18px;
    background: var(--accent2);
    color: #0d0f14;
    border: none;
    border-radius: 9px;
    font-family: 'DM Sans', sans-serif;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
    transition: opacity 0.15s;
  }
  .cat-form button:hover { opacity: 0.85; }

  /* ── Empty state ── */
  .empty-state {
    text-align: center;
    padding: 40px;
    color: var(--muted);
    font-size: 0.88rem;
  }

  /* ── Confirm modal ── */
  .modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    z-index: 999;
    align-items: center;
    justify-content: center;
  }
  .modal-overlay.open { display: flex; }
  .modal {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 28px;
    max-width: 380px;
    width: 90%;
    text-align: center;
  }
  .modal-icon { font-size: 2.5rem; margin-bottom: 12px; }
  .modal-title { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 700; margin-bottom: 8px; }
  .modal-msg { font-size: 0.85rem; color: var(--muted); margin-bottom: 20px; }
  .modal-actions { display: flex; gap: 10px; justify-content: center; }
  .modal-cancel {
    padding: 9px 20px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 9px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
  }
  .modal-confirm {
    padding: 9px 20px;
    background: var(--danger);
    border: none;
    border-radius: 9px;
    color: #fff;
    font-family: 'DM Sans', sans-serif;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
  }
  .modal-confirm:hover { opacity: 0.85; }

  /* Tab content */
  .tab-panel { display: none; }
  .tab-panel.active { display: block; }

</style>
</head>
<body>

<!-- Confirm Modal -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal">
    <div class="modal-icon">⚠️</div>
    <div class="modal-title" id="modalTitle">Are you sure?</div>
    <div class="modal-msg" id="modalMsg">This action cannot be undone.</div>
    <div class="modal-actions">
      <button class="modal-cancel" onclick="closeModal()">Cancel</button>
      <button class="modal-confirm" id="modalConfirmBtn">Delete</button>
    </div>
  </div>
</div>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <a href="index.php">
      <div class="brand">Resource <span>Hub</span></div>
    </a>
    <span class="admin-badge">Admin Panel</span>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="admin.php?tab=dashboard" class="nav-item <?= $activeTab === 'dashboard' ? 'active' : '' ?>">
      <span class="icon">📊</span> Dashboard
    </a>
    <a href="admin.php?tab=resources" class="nav-item <?= $activeTab === 'resources' ? 'active' : '' ?>">
      <span class="icon">📁</span> Resources
    </a>
    <a href="admin.php?tab=users" class="nav-item <?= $activeTab === 'users' ? 'active' : '' ?>">
      <span class="icon">👥</span> Users
    </a>
    <a href="admin.php?tab=categories" class="nav-item <?= $activeTab === 'categories' ? 'active' : '' ?>">
      <span class="icon">🏷️</span> Categories
    </a>

    <div class="nav-label" style="margin-top:8px;">Site</div>
    <a href="index.php" class="nav-item">
      <span class="icon">🏠</span> Homepage
    </a>
    <a href="browse.php" class="nav-item">
      <span class="icon">🔍</span> Browse
    </a>
    <a href="upload.php" class="nav-item">
      <span class="icon">⬆️</span> Upload
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="admin-info">
      <div class="admin-avatar"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
      <div>
        <div class="admin-name"><?= htmlspecialchars($adminName) ?></div>
        <div class="admin-role">Administrator</div>
      </div>
    </div>
    <a href="logout.php" class="logout-btn">🚪 Sign Out</a>
  </div>
</aside>

<!-- Main Content -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title">
      <?php
        $titles = [
          'dashboard'  => '📊 Dashboard',
          'resources'  => '📁 Manage Resources',
          'users'      => '👥 Manage Users',
          'categories' => '🏷️ Manage Categories',
        ];
        echo $titles[$activeTab] ?? 'Dashboard';
      ?>
    </div>
    <div class="topbar-right">
      <a href="index.php" class="view-site-btn">↗ View Site</a>
    </div>
  </div>

  <div class="content">

    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ════════════════ DASHBOARD ════════════════ -->
    <?php if ($activeTab === 'dashboard'): ?>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card blue">
        <span class="stat-icon">📁</span>
        <div class="stat-value"><?= number_format($totalResources) ?></div>
        <div class="stat-label">Total Resources</div>
      </div>
      <div class="stat-card green">
        <span class="stat-icon">👥</span>
        <div class="stat-value"><?= number_format($totalUsers) ?></div>
        <div class="stat-label">Registered Students</div>
      </div>
      <div class="stat-card orange">
        <span class="stat-icon">⬇️</span>
        <div class="stat-value"><?= number_format($totalDownloads) ?></div>
        <div class="stat-label">Total Downloads</div>
      </div>
      <div class="stat-card red">
        <span class="stat-icon">🏷️</span>
        <div class="stat-value"><?= number_format($totalCategories) ?></div>
        <div class="stat-label">Categories</div>
      </div>
    </div>

    <!-- Charts row -->
    <div class="dashboard-grid">
      <!-- Bar chart: uploads per month -->
      <div class="chart-card">
        <div class="chart-title">📈 Uploads — Last 6 Months</div>
        <?php
          $maxCount = max(array_column($monthlyStats, 'count') ?: [1]);
        ?>
        <div class="bar-chart">
          <?php foreach ($monthlyStats as $m): ?>
            <?php $h = max(6, round(($m['count'] / $maxCount) * 110)); ?>
            <div class="bar-group">
              <div class="bar-count"><?= $m['count'] ?></div>
              <div class="bar" style="height:<?= $h ?>px;"></div>
              <div class="bar-label"><?= $m['month'] ?></div>
            </div>
          <?php endforeach; ?>
          <?php if (empty($monthlyStats)): ?>
            <div style="color:var(--muted);font-size:0.82rem;margin:auto;">No data yet</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Top downloads -->
      <div class="chart-card">
        <div class="chart-title">🏆 Most Downloaded</div>
        <div class="top-list">
          <?php foreach ($topResources as $i => $r): ?>
          <div class="top-item">
            <div class="top-rank"><?= $i + 1 ?></div>
            <div class="top-info">
              <div class="top-name"><?= htmlspecialchars($r['title']) ?></div>
              <div class="top-cat"><?= htmlspecialchars($r['category_name']) ?></div>
            </div>
            <div class="top-dl">⬇ <?= $r["downloads"] ?></div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($topResources)): ?>
            <div class="empty-state">No downloads yet</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Recent uploads table -->
    <div class="section-header">
      <div class="section-title">🕒 Recent Uploads</div>
      <a href="admin.php?tab=resources" class="btn btn-primary">View All →</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Title</th>
            <th>Category</th>
            <th>Uploader</th>
            <th>Year</th>
            <th>Downloads</th>
            <th>Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentResources as $r): ?>
          <tr>
            <td>
              <?php
                $ext = strtolower(pathinfo($r['file_name'] ?? '', PATHINFO_EXTENSION));
                $badgeClass = match($ext) {
                  'pdf'  => 'badge-pdf',
                  'docx','doc' => 'badge-docx',
                  'pptx','ppt' => 'badge-pptx',
                  default => 'badge-other'
                };
              ?>
              <span class="file-badge <?= $badgeClass ?>"><?= strtoupper($ext ?: 'file') ?></span>
              <?= htmlspecialchars($r['title']) ?>
            </td>
            <td><?= htmlspecialchars($r['category_name']) ?></td>
            <td><?= htmlspecialchars($r['uploader']) ?></td>
            <td><span class="year-badge">Y<?= $r['year_level'] ?></span></td>
            <td><?= $r["downloads"] ?></td>
            <td style="color:var(--muted)"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
            <td>
              <button class="btn btn-danger btn-sm"
                onclick="confirmDelete('resource', <?= $r['id'] ?>, '<?= addslashes($r['title']) ?>')">
                🗑 Delete
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php endif; ?>

    <!-- ════════════════ RESOURCES ════════════════ -->
    <?php if ($activeTab === 'resources'): ?>

    <div class="search-bar">
      <input type="text" id="resourceSearch" placeholder="🔍  Search by title, category or uploader…" oninput="filterTable('resourceTable', this.value)">
    </div>

    <div class="table-wrap">
      <table id="resourceTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Title</th>
            <th>Category</th>
            <th>Subject</th>
            <th>Uploader</th>
            <th>Year</th>
            <th>Downloads</th>
            <th>Rating</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allResources as $i => $r): ?>
          <?php
            $ext = strtolower(pathinfo($r['file_name'] ?? '', PATHINFO_EXTENSION));
            $badgeClass = match($ext) {
              'pdf'  => 'badge-pdf',
              'docx','doc' => 'badge-docx',
              'pptx','ppt' => 'badge-pptx',
              default => 'badge-other'
            };
            $avg = round((float)($r['average_rating'] ?? 0), 1);
          ?>
          <tr>
            <td style="color:var(--muted)"><?= $i + 1 ?></td>
            <td>
              <span class="file-badge <?= $badgeClass ?>"><?= strtoupper($ext ?: 'file') ?></span>
              <a href="resource.php?id=<?= $r['id'] ?>" style="color:var(--text);text-decoration:none;"
                onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text)'">
                <?= htmlspecialchars($r['title']) ?>
              </a>
            </td>
            <td><?= htmlspecialchars($r['category_name']) ?></td>
            <td style="color:var(--muted)"><?= htmlspecialchars($r['subject_code'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['uploader']) ?></td>
            <td><span class="year-badge">Y<?= $r['year_level'] ?></span></td>
            <td><?= $r["downloads"] ?></td>
            <td><?= $avg > 0 ? '⭐ ' . $avg : '—' ?></td>
            <td style="color:var(--muted)"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
            <td>
              <button class="btn btn-danger btn-sm"
                onclick="confirmDelete('resource', <?= $r['id'] ?>, '<?= addslashes($r['title']) ?>')">
                🗑
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($allResources)): ?>
          <tr><td colspan="10"><div class="empty-state">No resources uploaded yet.</div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php endif; ?>

    <!-- ════════════════ USERS ════════════════ -->
    <?php if ($activeTab === 'users'): ?>

    <div class="search-bar">
      <input type="text" id="userSearch" placeholder="🔍  Search by name or email…" oninput="filterTable('userTable', this.value)">
    </div>

    <div class="table-wrap">
      <table id="userTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Email</th>
            <th>Year</th>
            <th>Resources</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allUsers as $i => $u): ?>
          <tr>
            <td style="color:var(--muted)"><?= $i + 1 ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <div class="admin-avatar" style="width:28px;height:28px;font-size:0.7rem;">
                  <?= strtoupper(substr($u['name'], 0, 1)) ?>
                </div>
                <?= htmlspecialchars($u['name']) ?>
              </div>
            </td>
            <td style="color:var(--muted)"><?= htmlspecialchars($u['email']) ?></td>
            <td><span class="year-badge">Y<?= $u['year'] ?? '—' ?></span></td>
            <td><?= $u['resource_count'] ?></td>
            <td>
              <?php if ($u['is_banned'] ?? false): ?>
                <span class="ban-badge">🚫 Banned</span>
              <?php else: ?>
                <span style="color:var(--accent2);font-size:0.78rem;font-weight:600;">✅ Active</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--muted)"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            <td>
              <div style="display:flex;gap:6px;">
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="toggle_ban">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-warning btn-sm">
                    <?= ($u['is_banned'] ?? false) ? '✅ Unban' : '🚫 Ban' ?>
                  </button>
                </form>
                <button class="btn btn-danger btn-sm"
                  onclick="confirmDelete('user', <?= $u['id'] ?>, '<?= addslashes($u['name']) ?>')">
                  🗑
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($allUsers)): ?>
          <tr><td colspan="8"><div class="empty-state">No students registered yet.</div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php endif; ?>

    <!-- ════════════════ CATEGORIES ════════════════ -->
    <?php if ($activeTab === 'categories'): ?>

    <form method="POST" class="cat-form">
      <input type="hidden" name="action" value="add_category">
      <input type="text" name="cat_name" placeholder="New category name (e.g. Computer Science)…" required>
      <button type="submit">+ Add Category</button>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Category Name</th>
            <th>Resources</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $i => $c): ?>
          <tr>
            <td style="color:var(--muted)"><?= $i + 1 ?></td>
            <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
            <td><?= $c['resource_count'] ?> resource<?= $c['resource_count'] !== 1 ? 's' : '' ?></td>
            <td>
              <?php if ($c['resource_count'] == 0): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"
                  onclick="return confirm('Delete category \'<?= addslashes($c['name']) ?>\'?')">
                  🗑 Delete
                </button>
              </form>
              <?php else: ?>
                <span style="color:var(--muted);font-size:0.78rem;">Cannot delete — has resources</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($categories)): ?>
          <tr><td colspan="4"><div class="empty-state">No categories yet. Add one above!</div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php endif; ?>

  </div><!-- /content -->
</div><!-- /main -->

<!-- Hidden delete forms (submitted via JS) -->
<form id="deleteResourceForm" method="POST" style="display:none;">
  <input type="hidden" name="action" value="delete_resource">
  <input type="hidden" name="resource_id" id="deleteResourceId">
</form>
<form id="deleteUserForm" method="POST" style="display:none;">
  <input type="hidden" name="action" value="delete_user">
  <input type="hidden" name="user_id" id="deleteUserId">
</form>

<script>
// Confirm delete modal
let pendingType = null, pendingId = null;

function confirmDelete(type, id, name) {
  pendingType = type;
  pendingId   = id;
  document.getElementById('modalTitle').textContent = 'Delete ' + (type === 'resource' ? 'Resource' : 'User') + '?';
  document.getElementById('modalMsg').textContent   = 'Are you sure you want to permanently delete "' + name + '"? This cannot be undone.';
  document.getElementById('confirmModal').classList.add('open');
}

document.getElementById('modalConfirmBtn').addEventListener('click', function () {
  if (pendingType === 'resource') {
    document.getElementById('deleteResourceId').value = pendingId;
    document.getElementById('deleteResourceForm').submit();
  } else if (pendingType === 'user') {
    document.getElementById('deleteUserId').value = pendingId;
    document.getElementById('deleteUserForm').submit();
  }
});

function closeModal() {
  document.getElementById('confirmModal').classList.remove('open');
  pendingType = null; pendingId = null;
}
document.getElementById('confirmModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// Live search filter for tables
function filterTable(tableId, query) {
  const q = query.toLowerCase();
  const rows = document.getElementById(tableId).querySelectorAll('tbody tr');
  rows.forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
</script>
</body>
</html>