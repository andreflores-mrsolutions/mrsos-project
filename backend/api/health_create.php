<?php
// admin/api/health_create.php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','ADMIN']);
csrf_verify_or_fail();

$pdo = db();
$creatorUsId = (int)($_SESSION['usId'] ?? 0);
$creatorRol  = (string)($_SESSION['usRol'] ?? '');

$body = read_json_body();

$clId = (int)($body['clId'] ?? 0);
$csId = (int)($body['csId'] ?? 0);
$usIdCliente = (int)($body['usIdCliente'] ?? 0);

$hcFechaHoraRaw = trim((string)($body['hcFechaHora'] ?? ''));
$hcDuracionMins = (int)($body['hcDuracionMins'] ?? 240);

$hcNombreContacto = trim((string)($body['hcNombreContacto'] ?? ''));
$hcNumeroContacto = trim((string)($body['hcNumeroContacto'] ?? ''));
$hcCorreoContacto = trim((string)($body['hcCorreoContacto'] ?? ''));

$items = $body['items'] ?? null;

if ($clId <= 0) json_fail('Falta clId');
if ($csId <= 0) json_fail('Falta csId');
if ($usIdCliente <= 0) json_fail('Selecciona el cliente responsable.');
if ($hcFechaHoraRaw === '') json_fail('Falta hcFechaHora');
if ($hcDuracionMins <= 0) $hcDuracionMins = 240;

if ($hcNombreContacto === '') json_fail('Falta nombre de contacto');
if ($hcNumeroContacto === '') json_fail('Falta teléfono de contacto');
if ($hcCorreoContacto === '' || !filter_var($hcCorreoContacto, FILTER_VALIDATE_EMAIL)) json_fail('Correo inválido');

if (!is_array($items) || count($items) < 1) json_fail('Selecciona al menos 1 equipo.');

$rol = $_SESSION['usRol'] ?? ($_SESSION['rol'] ?? '');
if (!in_array($rol, ['MRA','MRSA','ADMIN'], true)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Sin permisos'], JSON_UNESCAPED_UNICODE);
  exit;
}

// Validar cliente responsable (CLI) válido para clId + csId
$stCli = $pdo->prepare("
  SELECT u.usId
  FROM usuarios u
  INNER JOIN usuario_cliente_rol ucr ON ucr.usId = u.usId
  WHERE
    u.usId = ?
    AND u.usRol = 'CLI'
    AND u.usEstatus = 'Activo'
    AND ucr.ucrEstatus = 'Activo'
    AND ucr.clId = ?
    AND (ucr.csId = ? OR ucr.csId IS NULL)
  LIMIT 1
");
$stCli->execute([$usIdCliente, $clId, $csId]);
if (!$stCli->fetch()) {
  json_fail('El cliente responsable no es válido para esta sede.');
}

// Parse datetime-local
$hcFechaHoraRaw = str_replace('T', ' ', $hcFechaHoraRaw);
if (!preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}/', $hcFechaHoraRaw)) {
  json_fail('Formato de fecha/hora inválido.');
}
$hcFechaHora = $hcFechaHoraRaw . ':00';

// Limpiar items
$clean = [];
foreach ($items as $it) {
  if (!is_array($it)) continue;
  $peId = (int)($it['peId'] ?? 0);
  $eqId = (int)($it['eqId'] ?? 0);
  if ($peId > 0 && $eqId > 0) $clean[] = ['peId' => $peId, 'eqId' => $eqId];
}
if (!$clean) json_fail('Items inválidos.');

// Validar que items pertenezcan a cliente/sede
$peIds = array_values(array_unique(array_map(fn($x) => $x['peId'], $clean)));
$in = implode(',', array_fill(0, count($peIds), '?'));

$stVal = $pdo->prepare("
  SELECT pe.peId, pe.eqId
  FROM polizasequipo pe
  INNER JOIN polizascliente pc ON pc.pcId = pe.pcId
  WHERE pc.clId = ? AND pe.csId = ? AND pe.peEstatus='Activo' AND pe.peId IN ($in)
");
$params = array_merge([$clId, $csId], $peIds);
$stVal->execute($params);

$validMap = [];
while ($r = $stVal->fetch()) {
  $validMap[(int)$r['peId']] = (int)$r['eqId'];
}

foreach ($clean as $it) {
  if (!isset($validMap[$it['peId']]) || $validMap[$it['peId']] !== $it['eqId']) {
    json_fail('Uno o más equipos no pertenecen al cliente/sede o están inactivos.');
  }
}

try {
  $pdo->beginTransaction();

  // 👇 usId = cliente responsable
  $st = $pdo->prepare("
    INSERT INTO health_check (
      clId, csId, usId,
      hcFechaHora, hcDuracionMins,
      hcNombreContacto, hcNumeroContacto, hcCorreoContacto,
      hcEstatus
    ) VALUES (
      :clId, :csId, :usIdCliente,
      :hcFechaHora, :hcDuracionMins,
      :hcNombreContacto, :hcNumeroContacto, :hcCorreoContacto,
      'Programado'
    )
  ");
  $st->execute([
    ':clId' => $clId,
    ':csId' => $csId,
    ':usIdCliente' => $usIdCliente,
    ':hcFechaHora' => $hcFechaHora,
    ':hcDuracionMins' => $hcDuracionMins,
    ':hcNombreContacto' => $hcNombreContacto,
    ':hcNumeroContacto' => $hcNumeroContacto,
    ':hcCorreoContacto' => $hcCorreoContacto,
  ]);

  $hcId = (int)$pdo->lastInsertId();

  $stI = $pdo->prepare("
    INSERT INTO health_check_items (hcId, eqId, peId, tiId)
    VALUES (:hcId, :eqId, :peId, NULL)
  ");
  foreach ($clean as $it) {
    $stI->execute([
      ':hcId' => $hcId,
      ':eqId' => $it['eqId'],
      ':peId' => $it['peId'],
    ]);
  }

  $msg = sprintf(
    '[HEALTH] hcId=%d · Creado por usId=%d · ResponsableCliente usId=%d · clId=%d csId=%d · Equipos=%d · Fecha=%s Dur=%d',
    $hcId, $creatorUsId, $usIdCliente, $clId, $csId, count($clean), $hcFechaHora, $hcDuracionMins
  );
  $stH = $pdo->prepare("
    INSERT INTO historial (hDescripcion, usId, hFecha_hora, hTabla, hEstatus)
    VALUES (?, ?, ?, 'health_check', 'Activo')
  ");
  $stH->execute([
    $msg,
    $creatorUsId,
    (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s')
  ]);

  $pdo->commit();
  json_ok(['hcId' => $hcId]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_fail('Error al crear Health Check.', 500, ['detail' => $e->getMessage()]);
}