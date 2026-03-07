<?php
// admin/api/health_catalog_sedes.php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','ADMIN']);
csrf_verify_or_fail();

$clId = isset($_GET['clId']) ? (int)$_GET['clId'] : 0;
if ($clId <= 0) json_fail('Falta clId');

$pdo = db();
$usId = (int)($_SESSION['usId'] ?? 0);
$usRol = (string)($_SESSION['usRol'] ?? '');

$rol = $_SESSION['usRol'] ?? ($_SESSION['rol'] ?? '');
if (!in_array($rol, ['MRA','MRSA','ADMIN'], true)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Sin permisos'], JSON_UNESCAPED_UNICODE);
  exit;
}
$st = $pdo->prepare("
  SELECT csId, csNombre
  FROM cliente_sede
  WHERE clId = ? AND csEstatus = 'Activo'
  ORDER BY csEsPrincipal DESC, csNombre ASC
");
$st->execute([$clId]);
$sedes = $st->fetchAll();

json_ok(['sedes' => $sedes]);