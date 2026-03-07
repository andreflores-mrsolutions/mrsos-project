<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../../php/cliente_guard.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

$pdo = db();
$in = read_json_body();
$peId = (int)($in['peId'] ?? 0);
$peEstatus = trim((string)($in['peEstatus'] ?? ''));

if ($peId <= 0) json_fail('peId requerido');

$allowed = ['Activo','Inactivo','Error'];
if (!in_array($peEstatus, $allowed, true)) json_fail('peEstatus inválido');

$st = $pdo->prepare("
  SELECT pe.peId, pe.pcId, pc.clId
  FROM polizasequipo pe
  JOIN polizascliente pc ON pc.pcId = pe.pcId
  WHERE pe.peId=?
  LIMIT 1
");
$st->execute([$peId]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) json_fail('Equipo en póliza no existe');

$clId = (int)$row['clId'];
$usId = (int)($_SESSION['usId'] ?? 0);
$rol  = current_usRol();
if (!mr_can_access_client($pdo, $usId, $rol, $clId)) json_fail('Sin acceso al cliente');

$pdo->prepare("UPDATE polizasequipo SET peEstatus=? WHERE peId=? LIMIT 1")
    ->execute([$peEstatus, $peId]);

json_ok(['peId' => $peId, 'peEstatus' => $peEstatus]);