<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Resource Hub — Student Resource Sharing</title>
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
      --card-radius: 16px;
    }

    html { scroll-behavior: smooth; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* === BACKGROUND GRID === */
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

    /* === GLOW ORBS === */
    .orb {
      position: fixed;
      border-radius: 50%;
      filter: blur(100px);
      pointer-events: none;
      z-index: 0;
    }
    .orb1 { width: 500px; height: 500px; background: rgba(124,111,247,0.12); top: -100px; left: -100px; }
    .orb2 { width: 400px; height: 400px; background: rgba(247,194,111,0.07); bottom: 100px; right: -80px; }

    /* === NAV === */
    nav {
      position: sticky;
      top: 0;
      z-index: 100;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem 2.5rem;
      background: rgba(10,10,15,0.8);
      backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--border);
    }

    .logo {
      font-family: 'Syne', sans-serif;
      font-weight: 800;
      font-size: 1.3rem;
      letter-spacing: -0.5px;
      color: var(--text);
    }
    .logo span { color: var(--accent); }

    .nav-links {
      display: flex;
      gap: 2rem;
      list-style: none;
    }
    .nav-links a {
      text-decoration: none;
      color: var(--muted);
      font-size: 0.9rem;
      font-weight: 500;
      transition: color 0.2s;
    }
    .nav-links a:hover { color: var(--text); }

    .nav-btns { display: flex; gap: 0.75rem; align-items: center; }

    .btn-ghost {
      padding: 0.5rem 1.2rem;
      border: 1px solid var(--border);
      background: transparent;
      color: var(--text);
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.875rem;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-ghost:hover { background: var(--surface); border-color: rgba(255,255,255,0.15); }

    .btn-primary {
      padding: 0.5rem 1.2rem;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-primary:hover { opacity: 0.88; transform: translateY(-1px); }

    /* === HERO === */
    .hero {
      position: relative;
      z-index: 1;
      max-width: 900px;
      margin: 0 auto;
      padding: 7rem 2rem 5rem;
      text-align: center;
    }

    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: rgba(124,111,247,0.12);
      border: 1px solid rgba(124,111,247,0.25);
      color: #a99ff8;
      font-size: 0.8rem;
      font-weight: 500;
      padding: 0.35rem 1rem;
      border-radius: 999px;
      margin-bottom: 2rem;
      letter-spacing: 0.3px;
    }
    .hero-badge::before {
      content: '';
      width: 6px; height: 6px;
      background: var(--accent);
      border-radius: 50%;
      animation: pulse 2s infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.5; transform: scale(1.4); }
    }

    .hero h1 {
      font-family: 'Syne', sans-serif;
      font-size: clamp(1.8rem, 4vw, 2.6rem);
      font-weight: 800;
      line-height: 1.2;
      letter-spacing: -1px;
      margin-bottom: 1.5rem;
      color: var(--text);
    }
    .hero h1 em {
      font-style: normal;
      color: var(--accent);
    }
    .hero h1 .highlight {
      position: relative;
      display: inline-block;
    }
    .hero h1 .highlight::after {
      content: '';
      position: absolute;
      bottom: 4px; left: 0; right: 0;
      height: 3px;
      background: var(--accent2);
      border-radius: 2px;
    }

    .hero p {
      font-size: 0.95rem;
      color: var(--muted);
      line-height: 1.7;
      max-width: 560px;
      margin: 0 auto 2.5rem;
      font-weight: 300;
    }

    .hero-cta {
      display: flex;
      gap: 1rem;
      justify-content: center;
      flex-wrap: wrap;
    }

    .btn-large {
      padding: 0.85rem 2rem;
      font-size: 0.95rem;
      border-radius: 10px;
      font-weight: 500;
    }

    /* === SEARCH BAR === */
    .search-wrap {
      position: relative;
      z-index: 1;
      max-width: 640px;
      margin: 3rem auto 0;
      padding: 0 2rem;
    }
    .search-bar {
      display: flex;
      align-items: center;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 0.75rem 1rem;
      gap: 0.75rem;
      transition: border-color 0.2s;
    }
    .search-bar:focus-within { border-color: rgba(124,111,247,0.5); }
    .search-icon { color: var(--muted); font-size: 1.1rem; }
    .search-bar input {
      flex: 1;
      background: none;
      border: none;
      outline: none;
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.95rem;
    }
    .search-bar input::placeholder { color: var(--muted); }
    .search-btn {
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 0.45rem 1.1rem;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.85rem;
      font-weight: 500;
      cursor: pointer;
      transition: opacity 0.2s;
    }
    .search-btn:hover { opacity: 0.85; }

    /* === STATS === */
    .stats {
      position: relative;
      z-index: 1;
      display: flex;
      justify-content: center;
      gap: 3rem;
      padding: 3rem 2rem;
      flex-wrap: wrap;
    }
    .stat { text-align: center; }
    .stat-num {
      font-family: 'Syne', sans-serif;
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--text);
    }
    .stat-num span { color: var(--accent); }
    .stat-label { font-size: 0.8rem; color: var(--muted); margin-top: 0.2rem; letter-spacing: 0.5px; text-transform: uppercase; }

    /* === CATEGORIES === */
    .section {
      position: relative;
      z-index: 1;
      max-width: 1100px;
      margin: 0 auto;
      padding: 3rem 2rem;
    }

    .section-header {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      margin-bottom: 1.5rem;
    }
    .section-title {
      font-family: 'Syne', sans-serif;
      font-size: 1.15rem;
      font-weight: 700;
      letter-spacing: -0.3px;
    }
    .section-link {
      font-size: 0.85rem;
      color: var(--accent);
      text-decoration: none;
      font-weight: 500;
    }
    .section-link:hover { text-decoration: underline; }

    .categories {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .cat-chip {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.6rem 1.1rem;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 999px;
      font-size: 0.875rem;
      color: var(--muted);
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
    }
    .cat-chip:hover, .cat-chip.active {
      background: rgba(124,111,247,0.12);
      border-color: rgba(124,111,247,0.3);
      color: #a99ff8;
    }
    .cat-icon { font-size: 1rem; }
    .cat-count {
      font-size: 0.75rem;
      background: var(--surface2);
      padding: 1px 7px;
      border-radius: 999px;
    }

    /* === RESOURCE CARDS === */
    .cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1rem;
    }

    .resource-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--card-radius);
      padding: 1.25rem;
      transition: all 0.25s;
      cursor: pointer;
      text-decoration: none;
      display: block;
    }
    .resource-card:hover {
      border-color: rgba(124,111,247,0.3);
      transform: translateY(-2px);
      background: var(--surface2);
    }

    .card-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      margin-bottom: 0.75rem;
    }
    .file-icon {
      width: 40px; height: 40px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
    }
    .fi-pdf { background: rgba(239,68,68,0.15); }
    .fi-doc { background: rgba(59,130,246,0.15); }
    .fi-ppt { background: rgba(247,194,111,0.15); }
    .fi-zip { background: rgba(124,111,247,0.15); }

    .card-badge {
      font-size: 0.7rem;
      padding: 3px 8px;
      border-radius: 4px;
      font-weight: 500;
      background: rgba(124,111,247,0.12);
      color: #a99ff8;
      border: 1px solid rgba(124,111,247,0.2);
    }
    .card-badge.new { background: rgba(34,197,94,0.1); color: #4ade80; border-color: rgba(34,197,94,0.2); }

    .card-title {
      font-family: 'Syne', sans-serif;
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 0.35rem;
      line-height: 1.3;
    }
    .card-sub {
      font-size: 0.8rem;
      color: var(--muted);
      margin-bottom: 1rem;
    }

    .card-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .card-meta {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.78rem;
      color: var(--muted);
    }
    .avatar {
      width: 22px; height: 22px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.6rem;
      font-weight: 700;
      color: #fff;
    }
    .card-stats {
      display: flex;
      gap: 0.75rem;
      font-size: 0.78rem;
      color: var(--muted);
    }
    .card-stat { display: flex; align-items: center; gap: 3px; }

    /* === UPLOAD CTA === */
    .upload-cta {
      position: relative;
      z-index: 1;
      margin: 1rem 2rem 4rem;
      max-width: 1100px;
      margin-left: auto;
      margin-right: auto;
      background: var(--surface);
      border: 1px dashed rgba(124,111,247,0.3);
      border-radius: var(--card-radius);
      padding: 2.5rem;
      text-align: center;
      transition: border-color 0.2s;
      cursor: pointer;
    }
    .upload-cta:hover { border-color: rgba(124,111,247,0.6); background: var(--surface2); }
    .upload-icon {
      width: 56px; height: 56px;
      background: rgba(124,111,247,0.12);
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin: 0 auto 1rem;
    }
    .upload-cta h3 {
      font-family: 'Syne', sans-serif;
      font-size: 1.1rem;
      font-weight: 700;
      margin-bottom: 0.4rem;
    }
    .upload-cta p { font-size: 0.875rem; color: var(--muted); }

    /* === FOOTER === */
    footer {
      position: relative;
      z-index: 1;
      border-top: 1px solid var(--border);
      padding: 2rem;
      text-align: center;
      font-size: 0.8rem;
      color: var(--muted);
    }

    /* === ANIMATIONS === */
    .fade-in {
      opacity: 0;
      transform: translateY(20px);
      animation: fadeUp 0.6s forwards;
    }
    @keyframes fadeUp {
      to { opacity: 1; transform: translateY(0); }
    }
    .fade-in:nth-child(1) { animation-delay: 0.1s; }
    .fade-in:nth-child(2) { animation-delay: 0.2s; }
    .fade-in:nth-child(3) { animation-delay: 0.3s; }
    .fade-in:nth-child(4) { animation-delay: 0.4s; }
    .fade-in:nth-child(5) { animation-delay: 0.5s; }
    .fade-in:nth-child(6) { animation-delay: 0.6s; }
  </style>
</head>
<body>

<div class="orb orb1"></div>
<div class="orb orb2"></div>

<!-- NAV -->
<nav>
  <div class="logo">Resource<span>Hub</span></div>
  <ul class="nav-links">
    <li><a href="#">Browse</a></li>
    <li><a href="#">Subjects</a></li>
    <li><a href="#">Top Rated</a></li>
    <li><a href="#">About</a></li>
  </ul>
  <div class="nav-btns">
    <button class="btn-ghost" onclick="window.location='pages/login.php'">Log in</button>
    <button class="btn-primary" onclick="window.location='pages/register.php'">Sign up</button>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-badge fade-in">University of Colombo — IT Faculty</div>
  <h1 class="fade-in">
    Find notes.<br>
    Share knowledge.<br>
    <span class="highlight">Pass together.</span>
  </h1>
  <p class="fade-in">
    Resource Hub is your student-built library of lecture notes, past papers, and study resources — all in one place, free forever.
  </p>
  <div class="hero-cta fade-in">
    <button class="btn-primary btn-large" onclick="window.location='pages/register.php'">Get started free</button>
    <button class="btn-ghost btn-large" onclick="window.location='pages/browse.php'">Browse resources</button>
  </div>
</section>

<!-- SEARCH -->
<div class="search-wrap">
  <div class="search-bar">
    <span class="search-icon">🔍</span>
    <input type="text" placeholder="Search by subject, year, or keyword…" id="searchInput"/>
    <button class="search-btn" onclick="doSearch()">Search</button>
  </div>
</div>

<!-- STATS -->
<div class="stats">
  <div class="stat fade-in">
    <div class="stat-num">1,<span>240</span></div>
    <div class="stat-label">Resources shared</div>
  </div>
  <div class="stat fade-in">
    <div class="stat-num"><span>380</span></div>
    <div class="stat-label">Active students</div>
  </div>
  <div class="stat fade-in">
    <div class="stat-num"><span>42</span></div>
    <div class="stat-label">Subjects covered</div>
  </div>
  <div class="stat fade-in">
    <div class="stat-num"><span>4.8</span>★</div>
    <div class="stat-label">Avg rating</div>
  </div>
</div>

<!-- CATEGORIES -->
<section class="section">
  <div class="section-header">
    <h2 class="section-title">Browse by subject</h2>
    <a href="browse.php" class="section-link">View all →</a>
  </div>
  <div class="categories">
    <a class="cat-chip active" href="#"><span class="cat-icon">💻</span> Web Technology <span class="cat-count">84</span></a>
    <a class="cat-chip" href="#"><span class="cat-icon">🗄️</span> Databases <span class="cat-count">61</span></a>
    <a class="cat-chip" href="#"><span class="cat-icon">🧮</span> Data Structures <span class="cat-count">55</span></a>
    <a class="cat-chip" href="#"><span class="cat-icon">🔐</span> Cybersecurity <span class="cat-count">40</span></a>
    <a class="cat-chip" href="#"><span class="cat-icon">📊</span> Statistics <span class="cat-count">37</span></a>
    <a class="cat-chip" href="#"><span class="cat-icon">🤖</span> AI & ML <span class="cat-count">29</span></a>
    <a class="cat-chip" href="#"><span class="cat-icon">📐</span> Maths <span class="cat-count">48</span></a>
    <a class="cat-chip" href="#"><span class="cat-icon">🌐</span> Networking <span class="cat-count">33</span></a>
  </div>
</section>

<!-- RECENT RESOURCES -->
<section class="section" style="padding-top:0;">
  <div class="section-header">
    <h2 class="section-title">Recently uploaded</h2>
    <a href="browse.php" class="section-link">See all →</a>
  </div>
  <div class="cards-grid">

    <a class="resource-card fade-in" href="resource.php?id=1">
      <div class="card-top">
        <div class="file-icon fi-pdf">📄</div>
        <span class="card-badge new">New</span>
      </div>
      <div class="card-title">Web Technology — Past Paper 2023</div>
      <div class="card-sub">IT2040 · Year 2 · Semester 1</div>
      <div class="card-footer">
        <div class="card-meta">
          <div class="avatar">AS</div>
          <span>A. Silva</span>
        </div>
        <div class="card-stats">
          <span class="card-stat">⬇️ 142</span>
          <span class="card-stat">⭐ 4.9</span>
        </div>
      </div>
    </a>

    <a class="resource-card fade-in" href="resource.php?id=2">
      <div class="card-top">
        <div class="file-icon fi-doc">📝</div>
        <span class="card-badge">Notes</span>
      </div>
      <div class="card-title">MySQL Complete Lecture Notes — Week 1–8</div>
      <div class="card-sub">IT2031 · Year 2 · Databases</div>
      <div class="card-footer">
        <div class="card-meta">
          <div class="avatar">RP</div>
          <span>R. Perera</span>
        </div>
        <div class="card-stats">
          <span class="card-stat">⬇️ 98</span>
          <span class="card-stat">⭐ 4.7</span>
        </div>
      </div>
    </a>

    <a class="resource-card fade-in" href="resource.php?id=3">
      <div class="card-top">
        <div class="file-icon fi-ppt">📊</div>
        <span class="card-badge">Slides</span>
      </div>
      <div class="card-title">Data Structures — Trees & Graphs Slides</div>
      <div class="card-sub">IT1022 · Year 1 · Semester 2</div>
      <div class="card-footer">
        <div class="card-meta">
          <div class="avatar">KJ</div>
          <span>K. Jay</span>
        </div>
        <div class="card-stats">
          <span class="card-stat">⬇️ 76</span>
          <span class="card-stat">⭐ 4.5</span>
        </div>
      </div>
    </a>

    <a class="resource-card fade-in" href="resource.php?id=4">
      <div class="card-top">
        <div class="file-icon fi-zip">📦</div>
        <span class="card-badge">Bundle</span>
      </div>
      <div class="card-title">Cybersecurity — Full Semester Notes Pack</div>
      <div class="card-sub">IT3015 · Year 3 · Semester 1</div>
      <div class="card-footer">
        <div class="card-meta">
          <div class="avatar">MN</div>
          <span>M. Nonis</span>
        </div>
        <div class="card-stats">
          <span class="card-stat">⬇️ 201</span>
          <span class="card-stat">⭐ 5.0</span>
        </div>
      </div>
    </a>

    <a class="resource-card fade-in" href="resource.php?id=5">
      <div class="card-top">
        <div class="file-icon fi-pdf">📄</div>
        <span class="card-badge new">New</span>
      </div>
      <div class="card-title">AI & Machine Learning — Mid Exam Guide</div>
      <div class="card-sub">IT3040 · Year 3 · Semester 2</div>
      <div class="card-footer">
        <div class="card-meta">
          <div class="avatar">TF</div>
          <span>T. Fernando</span>
        </div>
        <div class="card-stats">
          <span class="card-stat">⬇️ 55</span>
          <span class="card-stat">⭐ 4.6</span>
        </div>
      </div>
    </a>

    <a class="resource-card fade-in" href="resource.php?id=6">
      <div class="card-top">
        <div class="file-icon fi-doc">📝</div>
        <span class="card-badge">Notes</span>
      </div>
      <div class="card-title">Networking Fundamentals — OSI Model Summary</div>
      <div class="card-sub">IT2025 · Year 2 · Networking</div>
      <div class="card-footer">
        <div class="card-meta">
          <div class="avatar">DW</div>
          <span>D. Wijes.</span>
        </div>
        <div class="card-stats">
          <span class="card-stat">⬇️ 88</span>
          <span class="card-stat">⭐ 4.8</span>
        </div>
      </div>
    </a>

  </div>
</section>

<!-- UPLOAD CTA -->
<div class="upload-cta" onclick="window.location='pages/upload.php'">
  <div class="upload-icon">📤</div>
  <h3>Share your notes with 380+ students</h3>
  <p>Upload your lecture notes, past papers or study guides — help your batchmates and earn ratings.</p>
  <br>
  <button class="btn-primary" style="padding: 0.7rem 1.8rem;">Start uploading →</button>
</div>

<!-- FOOTER -->
<footer>
  <p>Resource Hub &copy; 2026 · Built by IT Students, for IT Students · University of Colombo</p>
</footer>

<script>
  function doSearch() {
    const q = document.getElementById('searchInput').value.trim();
    if (q) window.location = 'browse.php?q=' + encodeURIComponent(q);
  }
  document.getElementById('searchInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') doSearch();
  });

  // Category chip toggle
  document.querySelectorAll('.cat-chip').forEach(chip => {
    chip.addEventListener('click', function(e) {
      e.preventDefault();
      document.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
      this.classList.add('active');
    });
  });
</script>

</body>
</html>