<?php
session_start();
require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];
            if ($user['role'] === 'admin') {
                header('Location: admin.php');
            } else {
                header('Location: ../index.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login — Resource Hub</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg: #0a0a0f;
      --surface: #13131a;
      --surface2: #1c1c27;
      --accent: #7c6ff7;
      --accent2: #f7c26f;
      --text: #f0f0f8;
      --muted: #7a7a9a;
      --border: rgba(255,255,255,0.07);
      --border-focus: rgba(124,111,247,0.5);
      --error: #f87171;
      --success: #4ade80;
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
      position: fixed;
      inset: 0;
      background-image:
        linear-gradient(rgba(124,111,247,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(124,111,247,0.04) 1px, transparent 1px);
      background-size: 60px 60px;
      pointer-events: none;
      z-index: 0;
    }

    .orb {
      position: fixed;
      border-radius: 50%;
      filter: blur(100px);
      pointer-events: none;
      z-index: 0;
    }
    .orb1 { width: 400px; height: 400px; background: rgba(124,111,247,0.1); top: -80px; left: -80px; }
    .orb2 { width: 300px; height: 300px; background: rgba(247,194,111,0.06); bottom: 50px; right: -60px; }

    /* NAV */
    nav {
      position: relative;
      z-index: 10;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem 2.5rem;
      border-bottom: 1px solid var(--border);
    }
    .logo {
      font-family: 'Syne', sans-serif;
      font-weight: 800;
      font-size: 1.3rem;
      color: var(--text);
      text-decoration: none;
    }
    .logo span { color: var(--accent); }
    .nav-link {
      font-size: 0.875rem;
      color: var(--muted);
      text-decoration: none;
      transition: color 0.2s;
    }
    .nav-link:hover { color: var(--text); }

    /* MAIN LAYOUT */
    main {
      position: relative;
      z-index: 1;
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3rem 1.5rem;
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 2.5rem;
      width: 100%;
      max-width: 420px;
      animation: fadeUp 0.5s ease forwards;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .card-header { text-align: center; margin-bottom: 2rem; }
    .card-icon {
      width: 52px; height: 52px;
      background: rgba(124,111,247,0.12);
      border: 1px solid rgba(124,111,247,0.2);
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.4rem;
      margin: 0 auto 1rem;
    }
    .card-header h1 {
      font-family: 'Syne', sans-serif;
      font-size: 1.4rem;
      font-weight: 700;
      margin-bottom: 0.3rem;
    }
    .card-header p {
      font-size: 0.875rem;
      color: var(--muted);
    }

    /* FORM */
    .form-group { margin-bottom: 1.1rem; }
    label {
      display: block;
      font-size: 0.8rem;
      font-weight: 500;
      color: var(--muted);
      margin-bottom: 0.4rem;
      letter-spacing: 0.3px;
    }
    input[type="text"],
    input[type="email"],
    input[type="password"] {
      width: 100%;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 0.7rem 1rem;
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.9rem;
      outline: none;
      transition: border-color 0.2s;
    }
    input:focus { border-color: var(--border-focus); }
    input::placeholder { color: var(--muted); }

    .input-wrap { position: relative; }
    .toggle-pw {
      position: absolute;
      right: 0.9rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--muted);
      cursor: pointer;
      font-size: 1rem;
      padding: 0;
      transition: color 0.2s;
    }
    .toggle-pw:hover { color: var(--text); }

    .forgot {
      display: block;
      text-align: right;
      font-size: 0.78rem;
      color: var(--accent);
      text-decoration: none;
      margin-top: 0.4rem;
    }
    .forgot:hover { text-decoration: underline; }

    .btn-submit {
      width: 100%;
      padding: 0.8rem;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-family: 'Syne', sans-serif;
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
      margin-top: 1.5rem;
      transition: opacity 0.2s, transform 0.2s;
    }
    .btn-submit:hover { opacity: 0.88; transform: translateY(-1px); }
    .btn-submit:active { transform: scale(0.98); }

    .divider {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin: 1.5rem 0;
      color: var(--muted);
      font-size: 0.8rem;
    }
    .divider::before, .divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    .switch-link {
      text-align: center;
      font-size: 0.875rem;
      color: var(--muted);
    }
    .switch-link a {
      color: var(--accent);
      text-decoration: none;
      font-weight: 500;
    }
    .switch-link a:hover { text-decoration: underline; }

    /* ALERT */
    .alert {
      padding: 0.75rem 1rem;
      border-radius: 8px;
      font-size: 0.85rem;
      margin-bottom: 1.2rem;
      display: none;
    }
    .alert.error { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.2); color: var(--error); display: block; }
    .alert.success { background: rgba(74,222,128,0.1); border: 1px solid rgba(74,222,128,0.2); color: var(--success); display: block; }

    footer {
      position: relative;
      z-index: 1;
      text-align: center;
      padding: 1.5rem;
      font-size: 0.78rem;
      color: var(--muted);
      border-top: 1px solid var(--border);
    }
  </style>
</head>
<body>

<div class="orb orb1"></div>
<div class="orb orb2"></div>

<nav>
  <a href='../index.php' class="logo">Resource<span>Hub</span></a>
  <a href="register.php" class="nav-link">Don't have an account? Sign up →</a>
</nav>

<main>
  <div class="card">
    <div class="card-header">
      <div class="card-icon">🔑</div>
      <h1>Welcome back</h1>
      <p>Log in to access your resources</p>
    </div>

    <?php if ($error): ?>
      <div class="alert error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['registered'])): ?>
      <div class="alert success">✅ Account created! Please log in.</div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="form-group">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" placeholder="you@university.edu" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-wrap">
          <input type="password" id="password" name="password" placeholder="Enter your password" required/>
          <button type="button" class="toggle-pw" onclick="togglePw()" id="toggleBtn">👁️</button>
        </div>
        <a href="forgot.php" class="forgot">Forgot password?</a>
      </div>

      <button type="submit" class="btn-submit">Log in</button>
    </form>

    <div class="divider">or</div>
    <div class="switch-link">
      New to Resource Hub? <a href="register.php">Create an account</a>
    </div>
  </div>
</main>

<footer>Resource Hub &copy; 2026 · University of Colombo</footer>

<script>
  function togglePw() {
    const input = document.getElementById('password');
    const btn   = document.getElementById('toggleBtn');
    if (input.type === 'password') {
      input.type = 'text';
      btn.textContent = '🙈';
    } else {
      input.type = 'password';
      btn.textContent = '👁️';
    }
  }
</script>
</body>
</html>