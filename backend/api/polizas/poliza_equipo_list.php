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

$pcId = (int)($_GET['pcId'] ?? 0);
if ($pcId <= 0) json_fail('pcId requerido');

/**
 * Prefijo tipo "ENE" a partir del nombre del cliente.
 * (Enel -> ENE). Si no hay letras/números, cae a "CLI".
 */
function cliente_prefix(string $clNombre): string {
  $s = trim($clNombre);
  if ($s === '') return 'CLI';

  // primera palabra, alfanumérico
  $parts = preg_split('/\s+/', $s) ?: [];
  $first = $parts[0] ?? $s;
  $first = preg_replace('/[^a-zA-Z0-9]/', '', $first) ?? '';

  if ($first === '') {
    $first = preg_replace('/[^a-zA-Z0-9]/', '', $s) ?? '';
  }
  if ($first === '') return 'CLI';

  $pref = strtoupper(substr($first, 0, 3));
  return $pref !== '' ? $pref : 'CLI';
}

// --- póliza + cliente (para clNombre y permisos) ---
$st = $pdo->prepare("
  SELECT pc.pcId, pc.clId, c.clNombre
  FROM polizascliente pc
  JOIN clientes c ON c.clId = pc.clId
  WHERE pc.pcId=?
  LIMIT 1
");
$st->execute([$pcId]);
$pc = $st->fetch(PDO::FETCH_ASSOC);
if (!$pc) json_fail('Póliza no existe');

$clId = (int)$pc['clId'];
$clNombre = (string)($pc['clNombre'] ?? '');
$clPrefix = cliente_prefix($clNombre);

// permisos MR
$usId = (int)($_SESSION['usId'] ?? 0);
$rol  = current_usRol();
if (!mr_can_access_client($pdo, $usId, $rol, $clId)) json_fail('Sin acceso al cliente');

// --- equipos de póliza + tickets activos por peId ---
$st = $pdo->prepare("
  SELECT
    pe.peId, pe.pcId, pe.eqId, pe.csId,
    pe.peSN, pe.peSO, pe.peDescripcion, pe.peEstatus,

    s.csNombre,
    e.eqModelo, e.eqVersion, e.eqTipoEquipo,
    m.maNombre,

    (
      SELECT COUNT(*)
      FROM ticket_soporte t
      WHERE t.peId = pe.peId
        AND t.estatus='Activo'
        AND t.tiEstatus IN ('Abierto','Pospuesto')
    ) AS ticketsActivosCount,

    (
      SELECT GROUP_CONCAT(t.tiId ORDER BY t.tiId SEPARATOR ',')
      FROM ticket_soporte t
      WHERE t.peId = pe.peId
        AND t.estatus='Activo'
        AND t.tiEstatus IN ('Abierto','Pospuesto')
    ) AS ticketsActivosIds

  FROM polizasequipo pe
  JOIN equipos e ON e.eqId = pe.eqId
  JOIN marca  m ON m.maId = e.maId
  JOIN cliente_sede s ON s.csId = pe.csId
  WHERE pe.pcId=?
  ORDER BY pe.peId DESC
");
$st->execute([$pcId]);

$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($rows as &$r) {
  $ids = trim((string)($r['ticketsActivosIds'] ?? ''));
  $arr = [];
  if ($ids !== '') {
    foreach (explode(',', $ids) as $x) {
      $n = (int)trim($x);
      if ($n > 0) $arr[] = $n;
    }
  }
  $r['ticketsActivos'] = $arr;
  $r['ticketsActivosCodigos'] = array_map(fn($tiId) => $clPrefix . '-' . (string)$tiId, $arr);

  // limpieza opcional para no ensuciar el front con string interno
  unset($r['ticketsActivosIds']);
}
unset($r);

json_ok([
  'clId' => $clId,
  'clNombre' => $clNombre,
  'clPrefix' => $clPrefix,
  'equipos' => $rows
]);