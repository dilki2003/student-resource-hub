<?php
// ============================================
//  Resource Hub — Database Connection (db.php)
//  Place this file in: C:\xampp\htdocs\resourcehub\
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'resourcehub');
define('DB_USER', 'root');       
define('DB_PASS', 'Cdn2003$');         
define('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Show friendly error instead of raw PHP error
    die('
    <div style="
      font-family: sans-serif;
      background: #0a0a0f;
      color: #f87171;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      gap: 1rem;
      padding: 2rem;
      text-align: center;
    ">
      <h2 style="font-size:1.5rem;">⚠️ Database Connection Failed</h2>
      <p style="color:#7a7a9a; max-width:400px;">
        Make sure XAMPP is running and the database <strong style="color:#f0f0f8;">resourcehub</strong> exists in phpMyAdmin.
      </p>
      <code style="background:#13131a; padding:0.5rem 1rem; border-radius:8px; font-size:0.85rem; color:#a99ff8;">
        ' . htmlspecialchars($e->getMessage()) . '
      </code>
      <a href="http://localhost/phpmyadmin" style="color:#7c6ff7; margin-top:0.5rem;">Open phpMyAdmin →</a>
    </div>
    ');
}
?>