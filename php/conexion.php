<?php
declare(strict_types=1);

date_default_timezone_set('America/Mexico_City');

// === CONFIG ===
$cfgFile = __DIR__ . '/config.alocal.php';
if (file_exists($cfgFile)) {
  require_once $cfgFile;
} else {
  define('DB_HOST', 'localhost');
  define('DB_NAME', 'mrsos');
  define('DB_USER', 'root');
  define('DB_PASS', '');
  define('DB_CHARSET', 'utf8mb4');
}

/* ============================
   1) mysqli LEGACY (NO TOCAR)
   ============================ */
$conectar = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conectar->connect_error) {
  die('DB error');
}
$conectar->set_charset(DB_CHARSET);

/* ============================
   2) PDO MODERNO (NUEVO)
   ============================ */
function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  return $pdo;
}
