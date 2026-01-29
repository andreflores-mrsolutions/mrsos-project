<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
  // Incluye tu conexión
  // require_once __DIR__.'/db.php';
  $DB_HOST = 'localhost';
  $DB_NAME = 'mrsos';
  $DB_USER = 'root';
  $DB_PASS = '';
  $DB_CHARSET = 'utf8mb4';
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // Puedes filtrar por experto/tier si lo deseas: ?tier=Tier%201&experto=OS
  $tier    = $_GET['tier']    ?? null;
  $experto = $_GET['experto'] ?? null;

  $where = ['i.ingEstatus = "Activo"', 'u.usEstatus = "Activo"'];
  $params = [];
  if ($tier)    { $where[] = 'i.ingTier = :tier';        $params[':tier'] = $tier; }
  if ($experto) { $where[] = 'i.ingExperto = :experto';  $params[':experto'] = $experto; }
  $whereSql = 'WHERE ' . implode(' AND ', $where);

  $sql = "
    SELECT
      i.ingId, i.usId, i.ingTier, i.ingExperto, i.ingDescripcion,
      u.usNombre, u.usAPaterno, u.usUsername, u.usCorreo, u.usTelefono, u.usId as usIdUsuario
    FROM ingenieros i
    INNER JOIN usuarios u ON u.usId = i.usId
    $whereSql
    ORDER BY 
      CASE i.ingTier WHEN 'Tier 1' THEN 1 WHEN 'Tier 2' THEN 2 ELSE 3 END,
      i.ingExperto, u.usNombre
  ";
  $stmt = $pdo->prepare($sql);
  foreach ($params as $k => $v) $stmt->bindValue($k, $v);
  $stmt->execute();
  $rows = $stmt->fetchAll();

  // Formato amigable para el front
  $ingenieros = array_map(function($r) {
    return [
      'ingId'      => (int)$r['ingId'],
      'usId'       => (int)$r['usIdUsuario'],
      'ingTier'    => $r['ingTier'],
      'ingExperto' => $r['ingExperto'],
      'ingDescripcion' => $r['ingDescripcion'],
      'usNombre'   => $r['usNombre'],
      'usAPaterno' => $r['usAPaterno'],
      'usCorreo'   => $r['usCorreo'],
      'usUsername'   => $r['usUsername'],
      'usTelefono' => $r['usTelefono']

    ];
  }, $rows);

  echo json_encode(['success' => true, 'ingenieros' => $ingenieros], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
