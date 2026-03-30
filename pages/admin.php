<?php
session_start();
require_once '../config/db.php';

// Admin only
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$me = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$me->execute([$_SESSION['user_id']]);
$admin = $me->fetch();
if (!$admin || $admin['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Handle actions
$msg = '';

// Delete resource
if (isset($_GET['delete_resource'])) {
    $rid = intval($_GET['delete_resource']);
    $f   = $pdo->prepare("SELECT file_name FROM resources WHERE id = ?");
    $f->execute([$rid]);
    $row = $f->fetch();
    if ($row) {
        $fp = __DIR__ . '/uploads/' . $row['file_name'];
        if (file_exists($fp)) unlink($fp);
        $pdo->prepare("DELETE FROM resources WHERE id = ?")->execute([$rid]);
        $msg = 'Resource deleted.';
    }
}

// Delete user
if (isset($_GET['delete_user'])) {
    $uid = intval($_GET['delete_user']);
    if ($uid !== $_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
        $msg = 'User deleted.';
    }
}

// Add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_cat'])) {
    $cname = trim($_POST['cat_name'] ?? '');
    $cicon = trim($_POST['cat_icon'] ?? '📁');
    if ($cname !== '') {
        $pdo->prepare("INSERT INTO categories (name, icon) VALUES (?, ?)")->execute([$cname, $cicon]);
        $msg = 'Category added.';
    }
}

// Delete category
if (isset($_GET['delete_cat'])) {
    $cid = intval($_GET['delete_cat']);
    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$cid]);
    $msg = 'Category deleted.';
}

// Active tab
$tab = $_GET['tab'] ?? 'dashboard';

// ── Stats ──
$total_resources = $pdo->query("SELECT COUNT(*) FROM resources WHERE status='active'")->fetchColumn();
$total_users     = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_downloads = $pdo->query("SELECT SUM(downloads) FROM resources")->fetchColumn() ?: 0;
$total_cats      = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// ── Data for tabs ──
$resources = $pdo->query("
    SELECT r.*, u.name AS uploader, c.name AS cat_name
    FROM resources r
    JOIN users u ON r.user_id = u.id
    JOIN categories c ON r.category_id = c.id
    WHERE r.status = 'active'
    ORDER BY r.created_at DESC
")->fetchAll();

$users = $pdo->query("
    SELECT u.*, COUNT(r.id) AS uploads
    FROM users u
    LEFT JOIN resources r ON u.id = r.user_id AND r.status='active'
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();

$cats = $pdo->query("
    SELECT c.*, COUNT(r.id) AS resource_count
    FROM categories c
    LEFT JOIN resources r ON c.id = r.category_id AND r.status='active'
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Panel — Resource Hub</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:       #0a0a0f;
      --surface:  #13131a;
      --surface2: #1c1c27;
      --accent:   #7c6ff7;
      --accent2:  #f7c26f;
      --text:     #f0f0f8;
      --muted:    #7a7a9a;
      --border:   rgba(255,255,255,0.07);
      --error:    #f87171;
      --success:  #4ade80;
      --warn:     #fb923c;
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
      background-size: 60px 60px; pointer-events: none; z-index: 0;
    }

    /* NAV */
    nav {
      position: sticky; top: 0; z-index: 100;
      display: flex; align-items: center; justify-content: space-between;
      padding: 0.9rem 2rem;
      background: rgba(10,10,15,0.9); backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--border);
    }
    .nav-left { display: flex; align-items: center; gap: 1rem; }
    .logo {
      font-family: 'Syne', sans-serif; font-weight: 800;
      font-size: 1.2rem; color: var(--text); text-decoration: none;
    }
    .logo span { color: var(--accent); }
    .admin-badge {
      font-size: 0.7rem; font-weight: 700; letter-spacing: 0.5px;
      background: rgba(124,111,247,0.15); color: #a99ff8;
      border: 1px solid rgba(124,111,247,0.25);
      padding: 2px 8px; border-radius: 4px; text-transform: uppercase;
    }
    .nav-right { display: flex; gap: 0.75rem; align-items: center; }
    .btn-ghost {
      padding: 0.4rem 0.9rem; border: 1px solid var(--border);
      background: transparent; color: var(--muted); border-radius: 8px;
      font-size: 0.82rem; text-decoration: none; transition: all 0.2s;
    }
    .btn-ghost:hover { color: var(--text); background: var(--surface); }

    /* LAYOUT */
    .page {
      position: relative; z-index: 1;
      max-width: 1200px; margin: 0 auto;
      padding: 2rem; flex: 1;
      display: grid;
      grid-template-columns: 200px 1fr;
      gap: 2rem;
    }

    /* SIDEBAR NAV */
    .side-nav {
      display: flex; flex-direction: column; gap: 0.3rem;
      align-self: start; position: sticky; top: 5rem;
    }
    .side-nav a {
      display: flex; align-items: center; gap: 0.6rem;
      padding: 0.6rem 0.9rem; border-radius: 10px;
      text-decoration: none; font-size: 0.875rem; color: var(--muted);
      transition: all 0.15s; border: 1px solid transparent;
    }
    .side-nav a:hover { background: var(--surface); color: var(--text); }
    .side-nav a.active {
      background: rgba(124,111,247,0.12);
      border-color: rgba(124,111,247,0.2); color: #a99ff8;
    }

    /* MAIN */
    .main { min-width: 0; }

    /* MSG */
    .msg {
      padding: 0.7rem 1rem; border-radius: 8px; font-size: 0.875rem;
      margin-bottom: 1.5rem;
      background: rgba(74,222,128,0.1); border: 1px solid rgba(74,222,128,0.2);
      color: var(--success); display: flex; align-items: center; gap: 0.5rem;
    }

    /* PAGE TITLE */
    .page-title {
      font-family: 'Syne', sans-serif;
      font-size: 1.4rem; font-weight: 800;
      letter-spacing: -0.3px; margin-bottom: 1.5rem;
    }

    /* STAT CARDS */
    .stats-grid {
      display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 14px; padding: 1.25rem;
      animation: fadeUp 0.4s ease forwards; opacity: 0;
    }
    @keyframes fadeUp {
      to { opacity: 1; transform: translateY(0); }
      from { opacity: 0; transform: translateY(10px); }
    }
    .stat-card:nth-child(1) { animation-delay: 0.05s; }
    .stat-card:nth-child(2) { animation-delay: 0.1s;  }
    .stat-card:nth-child(3) { animation-delay: 0.15s; }
    .stat-card:nth-child(4) { animation-delay: 0.2s;  }

    .stat-icon {
      font-size: 1.4rem; margin-bottom: 0.6rem; display: block;
    }
    .stat-num {
      font-family: 'Syne', sans-serif;
      font-size: 1.8rem; font-weight: 800; color: var(--text);
    }
    .stat-label { font-size: 0.78rem; color: var(--muted); margin-top: 0.2rem; }

    /* TABLE CARD */
    .table-card {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 16px; overflow: hidden; margin-bottom: 1.5rem;
    }
    .table-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 1.1rem 1.25rem; border-bottom: 1px solid var(--border);
    }
    .table-title {
      font-family: 'Syne', sans-serif; font-size: 0.95rem; font-weight: 700;
    }
    .table-count {
      font-size: 0.78rem; color: var(--muted);
      background: var(--surface2); padding: 2px 8px; border-radius: 999px;
    }

    table { width: 100%; border-collapse: collapse; }
    th {
      padding: 0.65rem 1.25rem; text-align: left;
      font-size: 0.72rem; font-weight: 700; letter-spacing: 0.6px;
      text-transform: uppercase; color: var(--muted);
      border-bottom: 1px solid var(--border);
      background: var(--surface2);
    }
    td {
      padding: 0.75rem 1.25rem; font-size: 0.85rem;
      border-bottom: 1px solid var(--border); color: var(--text);
      vertical-align: middle;
    }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(255,255,255,0.015); }

    .td-title { font-weight: 500; max-width: 220px; }
    .td-title a { color: var(--text); text-decoration: none; }
    .td-title a:hover { color: var(--accent); }
    .td-muted { color: var(--muted); }

    .badge {
      display: inline-block; font-size: 0.7rem; font-weight: 600;
      padding: 2px 7px; border-radius: 4px; text-transform: uppercase;
    }
    .badge-admin   { background: rgba(124,111,247,0.15); color: #a99ff8; }
    .badge-student { background: rgba(100,100,100,0.15); color: var(--muted); }

    .action-link {
      font-size: 0.78rem; text-decoration: none; padding: 3px 8px;
      border-radius: 5px; transition: all 0.15s;
    }
    .action-view   { color: var(--accent); }
    .action-view:hover { background: rgba(124,111,247,0.1); }
    .action-delete { color: var(--error); }
    .action-delete:hover { background: rgba(248,113,113,0.1); }

    /* ADD CATEGORY FORM */
    .form-card {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem;
    }
    .form-card-title {
      font-family: 'Syne', sans-serif; font-size: 0.9rem; font-weight: 700;
      margin-bottom: 1rem;
    }
    .inline-form { display: flex; gap: 0.75rem; flex-wrap: wrap; }
    .inline-form input {
      background: var(--surface2); border: 1px solid var(--border);
      border-radius: 8px; padding: 0.55rem 0.9rem;
      color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 0.875rem;
      outline: none; transition: border-color 0.2s; flex: 1; min-width: 140px;
    }
    .inline-form input:focus { border-color: rgba(124,111,247,0.4); }
    .inline-form input::placeholder { color: var(--muted); }
    .btn-add {
      padding: 0.55rem 1.2rem; background: var(--accent); color: #fff;
      border: none; border-radius: 8px; font-family: 'Syne', sans-serif;
      font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: opacity 0.2s;
      white-space: nowrap;
    }
    .btn-add:hover { opacity: 0.85; }

    .empty-row td {
      text-align: center; color: var(--muted); padding: 2rem; font-size: 0.875rem;
    }

    footer {
      position: relative; z-index: 1;
      border-top: 1px solid var(--border);
      padding: 1.2rem; text-align: center;
      font-size: 0.78rem; color: var(--muted);
    }
  </style>
</head>
<body>

<!-- NAV -->
<nav>
  <div class="nav-left">
    <a href='../index.php' class="logo">Resource<span>Hub</span></a>
    <span class="admin-badge">Admin</span>
  </div>
  <div class="nav-right">
    <a href="browse.php" class="btn-ghost">← Browse site</a>
    <a href="logout.php" class="btn-ghost">Log out</a>
  </div>
</nav>

<!-- PAGE -->
<div class="page">

  <!-- SIDE NAV -->
  <nav class="side-nav">
    <a href="admin.php?tab=dashboard" class="<?= $tab==='dashboard' ? 'active':'' ?>">📊 Dashboard</a>
    <a href="admin.php?tab=resources" class="<?= $tab==='resources' ? 'active':'' ?>">📁 Resources</a>
    <a href="admin.php?tab=users"     class="<?= $tab==='users'     ? 'active':'' ?>">👥 Users</a>
    <a href="admin.php?tab=categories"class="<?= $tab==='categories'? 'active':'' ?>">🏷️ Categories</a>
  </nav>

  <!-- MAIN -->
  <main class="main">

    <?php if ($msg): ?>
      <div class="msg">✅ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- ═══════════════════════════════ DASHBOARD ═══════════════════════════ -->
    <?php if ($tab === 'dashboard'): ?>
      <div class="page-title">📊 Dashboard</div>

      <div class="stats-grid">
        <div class="stat-card">
          <span class="stat-icon">📁</span>
          <div class="stat-num"><?= number_format($total_resources) ?></div>
          <div class="stat-label">Total resources</div>
        </div>
        <div class="stat-card">
          <span class="stat-icon">👥</span>
          <div class="stat-num"><?= number_format($total_users) ?></div>
          <div class="stat-label">Registered users</div>
        </div>
        <div class="stat-card">
          <span class="stat-icon">⬇️</span>
          <div class="stat-num"><?= number_format($total_downloads) ?></div>
          <div class="stat-label">Total downloads</div>
        </div>
        <div class="stat-card">
          <span class="stat-icon">🏷️</span>
          <div class="stat-num"><?= number_format($total_cats) ?></div>
          <div class="stat-label">Categories</div>
        </div>
      </div>

      <!-- Recent uploads -->
      <div class="table-card">
        <div class="table-header">
          <span class="table-title">Recent uploads</span>
          <a href="admin.php?tab=resources" style="font-size:0.8rem; color:var(--accent); text-decoration:none;">View all →</a>
        </div>
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Category</th>
              <th>Uploader</th>
              <th>Downloads</th>
              <th>Date</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php $recent = array_slice($resources, 0, 8); ?>
            <?php if (empty($recent)): ?>
              <tr class="empty-row"><td colspan="6">No resources yet.</td></tr>
            <?php else: foreach ($recent as $res): ?>
            <tr>
              <td class="td-title">
                <a href="resource.php?id=<?= $res['id'] ?>"><?= htmlspecialchars(mb_strimwidth($res['title'],0,45,'…')) ?></a>
              </td>
              <td class="td-muted"><?= htmlspecialchars($res['cat_name']) ?></td>
              <td class="td-muted"><?= htmlspecialchars($res['uploader']) ?></td>
              <td><?= number_format($res['downloads']) ?></td>
              <td class="td-muted"><?= date('M j, Y', strtotime($res['created_at'])) ?></td>
              <td>
                <a href="admin.php?delete_resource=<?= $res['id'] ?>&tab=dashboard"
                   class="action-link action-delete"
                   onclick="return confirm('Delete this resource?')">Delete</a>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

    <!-- ═══════════════════════════════ RESOURCES ═══════════════════════════ -->
    <?php elseif ($tab === 'resources'): ?>
      <div class="page-title">📁 All Resources</div>
      <div class="table-card">
        <div class="table-header">
          <span class="table-title">Resources</span>
          <span class="table-count"><?= count($resources) ?> total</span>
        </div>
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Type</th>
              <th>Category</th>
              <th>Uploader</th>
              <th>Downloads</th>
              <th>Date</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($resources)): ?>
              <tr class="empty-row"><td colspan="7">No resources found.</td></tr>
            <?php else: foreach ($resources as $res): ?>
            <tr>
              <td class="td-title">
                <a href="resource.php?id=<?= $res['id'] ?>"><?= htmlspecialchars(mb_strimwidth($res['title'],0,40,'…')) ?></a>
              </td>
              <td><span class="badge badge-student"><?= strtoupper($res['file_type']) ?></span></td>
              <td class="td-muted"><?= htmlspecialchars($res['cat_name']) ?></td>
              <td class="td-muted"><?= htmlspecialchars($res['uploader']) ?></td>
              <td><?= number_format($res['downloads']) ?></td>
              <td class="td-muted"><?= date('M j, Y', strtotime($res['created_at'])) ?></td>
              <td style="display:flex; gap:0.5rem;">
                <a href="resource.php?id=<?= $res['id'] ?>" class="action-link action-view">View</a>
                <a href="admin.php?delete_resource=<?= $res['id'] ?>&tab=resources"
                   class="action-link action-delete"
                   onclick="return confirm('Delete this resource?')">Delete</a>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

    <!-- ═══════════════════════════════ USERS ═══════════════════════════════ -->
    <?php elseif ($tab === 'users'): ?>
      <div class="page-title">👥 All Users</div>
      <div class="table-card">
        <div class="table-header">
          <span class="table-title">Users</span>
          <span class="table-count"><?= count($users) ?> total</span>
        </div>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Year</th>
              <th>Role</th>
              <th>Uploads</th>
              <th>Joined</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr class="empty-row"><td colspan="7">No users found.</td></tr>
            <?php else: foreach ($users as $u): ?>
            <tr>
              <td style="font-weight:500;"><?= htmlspecialchars($u['name']) ?></td>
              <td class="td-muted"><?= htmlspecialchars($u['email']) ?></td>
              <td class="td-muted">Year <?= $u['year'] ?></td>
              <td>
                <span class="badge <?= $u['role']==='admin' ? 'badge-admin' : 'badge-student' ?>">
                  <?= $u['role'] ?>
                </span>
              </td>
              <td><?= $u['uploads'] ?></td>
              <td class="td-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
              <td>
                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                  <a href="admin.php?delete_user=<?= $u['id'] ?>&tab=users"
                     class="action-link action-delete"
                     onclick="return confirm('Delete user <?= htmlspecialchars($u['name']) ?>?')">Delete</a>
                <?php else: ?>
                  <span style="font-size:0.75rem; color:var(--muted);">You</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

    <!-- ═══════════════════════════════ CATEGORIES ══════════════════════════ -->
    <?php elseif ($tab === 'categories'): ?>
      <div class="page-title">🏷️ Categories</div>

      <!-- Add category form -->
      <div class="form-card">
        <div class="form-card-title">Add new category</div>
        <form method="POST" action="admin.php?tab=categories">
          <input type="hidden" name="new_cat" value="1"/>
          <div class="inline-form">
            <input type="text" name="cat_icon" placeholder="Icon e.g. 📘" maxlength="5" style="max-width:90px;"/>
            <input type="text" name="cat_name" placeholder="Category name e.g. Operating Systems" required/>
            <button type="submit" class="btn-add">+ Add category</button>
          </div>
        </form>
      </div>

      <div class="table-card">
        <div class="table-header">
          <span class="table-title">All categories</span>
          <span class="table-count"><?= count($cats) ?> total</span>
        </div>
        <table>
          <thead>
            <tr>
              <th>Icon</th>
              <th>Name</th>
              <th>Resources</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($cats)): ?>
              <tr class="empty-row"><td colspan="4">No categories yet.</td></tr>
            <?php else: foreach ($cats as $cat): ?>
            <tr>
              <td style="font-size:1.3rem;"><?= $cat['icon'] ?></td>
              <td style="font-weight:500;"><?= htmlspecialchars($cat['name']) ?></td>
              <td class="td-muted"><?= $cat['resource_count'] ?> resource<?= $cat['resource_count']!=1?'s':'' ?></td>
              <td>
                <a href="admin.php?delete_cat=<?= $cat['id'] ?>&tab=categories"
                   class="action-link action-delete"
                   onclick="return confirm('Delete category <?= htmlspecialchars($cat['name']) ?>? Resources in it may be affected.')">Delete</a>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

    <?php endif; ?>
  </main>
</div>

<footer>Resource Hub &copy; 2026 · University of Colombo · Admin Panel</footer>

</body>
</html>