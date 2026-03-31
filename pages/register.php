<?php
session_start();
require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $year     = $_POST['year'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $terms    = $_POST['terms'] ?? '';

    if (empty($name) || empty($email) || empty($year) || empty($password) || empty($confirm)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!$terms) {
        $error = 'You must agree to the terms to continue.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, year, password) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $year, $hash]);
            header('Location: login.php?registered=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register — Resource Hub</title>
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
    .orb1 { width: 400px; height: 400px; background: rgba(124,111,247,0.1); top: -80px; right: -80px; }
    .orb2 { width: 300px; height: 300px; background: rgba(247,194,111,0.06); bottom: 50px; left: -60px; }

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
      max-width: 440px;
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
    .card-header p { font-size: 0.875rem; color: var(--muted); }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.75rem;
    }

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
    input[type="password"],
    select {
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
      appearance: none;
    }
    input:focus, select:focus { border-color: var(--border-focus); }
    input::placeholder { color: var(--muted); }
    select option { background: var(--surface2); color: var(--text); }

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

    /* Password strength */
    .pw-strength { margin-top: 0.5rem; }
    .pw-bar-wrap {
      height: 4px;
      background: var(--border);
      border-radius: 2px;
      margin-bottom: 0.3rem;
      overflow: hidden;
    }
    .pw-bar {
      height: 100%;
      border-radius: 2px;
      transition: width 0.3s, background 0.3s;
      width: 0%;
    }
    .pw-label { font-size: 0.75rem; color: var(--muted); }

    .terms {
      display: flex;
      align-items: flex-start;
      gap: 0.6rem;
      margin-bottom: 1.2rem;
      font-size: 0.8rem;
      color: var(--muted);
      line-height: 1.5;
    }
    .terms input[type="checkbox"] {
      width: auto;
      margin-top: 2px;
      accent-color: var(--accent);
      flex-shrink: 0;
    }
    .terms a { color: var(--accent); text-decoration: none; }
    .terms a:hover { text-decoration: underline; }

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

    .alert {
      padding: 0.75rem 1rem;
      border-radius: 8px;
      font-size: 0.85rem;
      margin-bottom: 1.2rem;
    }
    .alert.error { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.2); color: var(--error); }
    .alert.success { background: rgba(74,222,128,0.1); border: 1px solid rgba(74,222,128,0.2); color: var(--success); }

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
  <a href="login.php" class="nav-link">Already have an account? Log in →</a>
</nav>

<main>
  <div class="card">
    <div class="card-header">
      <div class="card-icon">🎓</div>
      <h1>Create your account</h1>
      <p>Join hundreds of students sharing resources</p>
    </div>

    <?php if ($error): ?>
      <div class="alert error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php">

      <div class="form-row">
        <div class="form-group">
          <label for="name">Full name</label>
          <input type="text" id="name" name="name" placeholder="Your name" required
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"/>
        </div>
        <div class="form-group">
          <label for="year">Academic year</label>
          <select id="year" name="year" required>
            <option value="" disabled <?= empty($_POST['year']) ? 'selected' : '' ?>>Select year</option>
            <option value="1" <?= ($_POST['year'] ?? '') === '1' ? 'selected' : '' ?>>Year 1</option>
            <option value="2" <?= ($_POST['year'] ?? '') === '2' ? 'selected' : '' ?>>Year 2</option>
            <option value="3" <?= ($_POST['year'] ?? '') === '3' ? 'selected' : '' ?>>Year 3</option>
            <option value="4" <?= ($_POST['year'] ?? '') === '4' ? 'selected' : '' ?>>Year 4</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label for="email">University email</label>
        <input type="email" id="email" name="email" placeholder="you@university.edu" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-wrap">
          <input type="password" id="password" name="password" placeholder="Min. 8 characters"
                 required oninput="checkStrength(this.value)"/>
          <button type="button" class="toggle-pw" onclick="togglePw('password','btn1')" id="btn1">👁️</button>
        </div>
        <div class="pw-strength">
          <div class="pw-bar-wrap"><div class="pw-bar" id="pwBar"></div></div>
          <span class="pw-label" id="pwLabel">Enter a password</span>
        </div>
      </div>

      <div class="form-group">
        <label for="confirm_password">Confirm password</label>
        <div class="input-wrap">
          <input type="password" id="confirm_password" name="confirm_password"
                 placeholder="Re-enter your password" required/>
          <button type="button" class="toggle-pw" onclick="togglePw('confirm_password','btn2')" id="btn2">👁️</button>
        </div>
      </div>

      <div class="terms">
        <input type="checkbox" id="terms" name="terms" value="1"
               <?= !empty($_POST['terms']) ? 'checked' : '' ?>/>
        <label for="terms" style="margin:0; font-size:0.8rem; color:var(--muted);">
          I agree to the <a href="#">Terms of Use</a> and <a href="#">Privacy Policy</a>
        </label>
      </div>

      <button type="submit" class="btn-submit">Create account</button>
    </form>

    <div class="divider">or</div>
    <div class="switch-link">
      Already have an account? <a href="login.php">Log in</a>
    </div>
  </div>
</main>

<footer>Resource Hub &copy; 2026 · University of Moratuwa</footer>

<script>
  function togglePw(inputId, btnId) {
    const input = document.getElementById(inputId);
    const btn   = document.getElementById(btnId);
    if (input.type === 'password') {
      input.type = 'text';
      btn.textContent = '';
    } else {
      input.type = 'password';
      btn.textContent = '👁️';
    }
  }

  function checkStrength(val) {
    const bar   = document.getElementById('pwBar');
    const label = document.getElementById('pwLabel');
    let score = 0;
    if (val.length >= 8)              score++;
    if (/[A-Z]/.test(val))           score++;
    if (/[0-9]/.test(val))           score++;
    if (/[^A-Za-z0-9]/.test(val))   score++;

    const levels = [
      { w: '0%',   bg: 'transparent', text: 'Enter a password' },
      { w: '25%',  bg: '#f87171',     text: 'Weak' },
      { w: '50%',  bg: '#fb923c',     text: 'Fair' },
      { w: '75%',  bg: '#facc15',     text: 'Good' },
      { w: '100%', bg: '#4ade80',     text: 'Strong ✓' },
    ];
    const l = val.length === 0 ? levels[0] : levels[score];
    bar.style.width      = l.w;
    bar.style.background = l.bg;
    label.textContent    = l.text;
    label.style.color    = l.bg === 'transparent' ? 'var(--muted)' : l.bg;
  }
</script>
</body>
</html>