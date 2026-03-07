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

$pdo = db();

$peId = (int)($_GET['peId'] ?? 0);
if ($peId <= 0) json_fail('peId requerido');

$st = $pdo->prepare("
  SELECT
    pe.peId, pe.pcId, pe.eqId, pe.csId,
    pe.peSN, pe.peSO, pe.peDescripcion, pe.peEstatus,
    pc.clId, c.clNombre,
    s.csNombre,
    e.eqModelo, e.eqTipoEquipo,
    m.maNombre
  FROM polizasequipo pe
  JOIN polizascliente pc ON pc.pcId = pe.pcId
  JOIN clientes c ON c.clId = pc.clId
  LEFT JOIN cliente_sede s ON s.csId = pe.csId
  JOIN equipos e ON e.eqId = pe.eqId
  JOIN marca m ON m.maId = e.maId
  WHERE pe.peId = ?
  LIMIT 1
");
$st->execute([$peId]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) json_fail('Equipo de póliza no existe', 404);

$clId = (int)$row['clId'];
$usId = (int)($_SESSION['usId'] ?? 0);
$rol  = current_usRol();

if (!mr_can_access_client($pdo, $usId, $rol, $clId)) {
  json_fail('Sin acceso al cliente', 403);
}

/** sedes del cliente (para selector) */
$st = $pdo->prepare("
  SELECT csId, csNombre
  FROM cliente_sede
  WHERE clId = ? AND csEstatus='Activo'
  ORDER BY csEsPrincipal DESC, csNombre ASC
");
$st->execute([$clId]);
$sedes = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

json_ok([
  'equipo' => $row,
  'sedes'  => $sedes,
  'role'   => $rol,
  'isMrsa' => ($rol === 'MRSA')
]);