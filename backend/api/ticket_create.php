<?php
// admin/api/ticket_create.php
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
$peId = (int)($body['peId'] ?? 0);
$eqId = (int)($body['eqId'] ?? 0);

$usIdCliente = (int)($body['usIdCliente'] ?? 0);

$tiTipoTicket = (string)($body['tiTipoTicket'] ?? 'Servicio');
$tiNivelCriticidad = (string)($body['tiNivelCriticidad'] ?? '1');

$tiNombreContacto = trim((string)($body['tiNombreContacto'] ?? ''));
$tiNumeroContacto = trim((string)($body['tiNumeroContacto'] ?? ''));
$tiCorreoContacto = trim((string)($body['tiCorreoContacto'] ?? ''));
$tiDescripcion = trim((string)($body['tiDescripcion'] ?? ''));

if ($clId <= 0) json_fail('Falta clId');
if ($csId <= 0) json_fail('Falta csId');
if ($peId <= 0) json_fail('Falta peId');
if ($eqId <= 0) json_fail('Falta eqId');
if ($usIdCliente <= 0) json_fail('Selecciona el cliente responsable.');

if ($tiDescripcion === '') json_fail('Falta descripción');
if ($tiNombreContacto === '') json_fail('Falta nombre de contacto');
if ($tiNumeroContacto === '') json_fail('Falta teléfono de contacto');
if ($tiCorreoContacto === '' || !filter_var($tiCorreoContacto, FILTER_VALIDATE_EMAIL)) json_fail('Correo inválido');

$allowedTipo = ['Servicio','Preventivo','Extra'];
if (!in_array($tiTipoTicket, $allowedTipo, true)) $tiTipoTicket = 'Servicio';
if (!preg_match('/^[1-4]$/', $tiNivelCriticidad)) $tiNivelCriticidad = '1';

$rol = $_SESSION['usRol'] ?? ($_SESSION['rol'] ?? '');
if (!in_array($rol, ['MRA','MRSA','ADMIN'], true)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Sin permisos'], JSON_UNESCAPED_UNICODE);
  exit;
}

/** 1) Validar equipo pertenezca a clId+csId */
$stValEq = $pdo->prepare("
  SELECT pe.peId, pe.eqId
  FROM polizasequipo pe
  INNER JOIN polizascliente pc ON pc.pcId = pe.pcId
  WHERE pc.clId = ? AND pe.csId = ? AND pe.peId = ? AND pe.eqId = ? AND pe.peEstatus='Activo'
  LIMIT 1
");
$stValEq->execute([$clId, $csId, $peId, $eqId]);
if (!$stValEq->fetch()) {
  json_fail('El equipo no pertenece al cliente/sede o está inactivo.');
}

/** 2) Validar usIdCliente sea CLI y pertenezca al cliente y aplique a esa sede */
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

/** folio: prefijo simple (3 letras del cliente) + tiId */
function cliente_prefix(string $clNombre): string {
  $s = mb_strtoupper($clNombre);
  $s = preg_replace('/[^A-Z0-9]+/u', '', $s) ?? '';
  if ($s === '') return 'MRS';
  return mb_substr($s, 0, 3);
}

$stCl = $pdo->prepare("SELECT clNombre FROM clientes WHERE clId=? LIMIT 1");
$stCl->execute([$clId]);
$clNombre = (string)(($stCl->fetch()['clNombre'] ?? ''));

$hoy = (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d');
$visitaDummy = '0000-00-00 00:00:00';

// asignación inicial (tu BD usa default 1002)
$usIdIng = 1002;

try {
  $pdo->beginTransaction();

  $st = $pdo->prepare("
    INSERT INTO ticket_soporte (
      clId, csId, usId, eqId, peId,
      tiDescripcion, tiEstatus, tiProceso, tiTipoTicket, tiExtra,
      tiNivelCriticidad, tiFechaCreacion, tiVisita,
      tiNombreContacto, tiNumeroContacto, tiCorreoContacto,
      usIdIng, estatus
    ) VALUES (
      :clId, :csId, :usIdCliente, :eqId, :peId,
      :tiDescripcion, 'Abierto', 'asignacion', :tiTipoTicket, '--',
      :tiNivelCriticidad, :tiFechaCreacion, :tiVisita,
      :tiNombreContacto, :tiNumeroContacto, :tiCorreoContacto,
      :usIdIng, 'Activo'
    )
  ");

  $st->execute([
    ':clId' => $clId,
    ':csId' => $csId,
    ':usIdCliente' => $usIdCliente,
    ':eqId' => $eqId,
    ':peId' => $peId,
    ':tiDescripcion' => $tiDescripcion,
    ':tiTipoTicket' => $tiTipoTicket,
    ':tiNivelCriticidad' => $tiNivelCriticidad,
    ':tiFechaCreacion' => $hoy,
    ':tiVisita' => $visitaDummy,
    ':tiNombreContacto' => $tiNombreContacto,
    ':tiNumeroContacto' => $tiNumeroContacto,
    ':tiCorreoContacto' => $tiCorreoContacto,
    ':usIdIng' => $usIdIng,
  ]);

  $tiId = (int)$pdo->lastInsertId();
  $pref = cliente_prefix($clNombre);
  $folio = $pref . '-' . $tiId;

  // historial: quién lo creó (ingeniero/admin)
  $msg = sprintf(
    '[TICKET] tiId=%d folio=%s · Creado por usId=%d · ResponsableCliente usId=%d · clId=%d csId=%d peId=%d eqId=%d',
    $tiId, $folio, $creatorUsId, $usIdCliente, $clId, $csId, $peId, $eqId
  );
  $stH = $pdo->prepare("
    INSERT INTO historial (hDescripcion, usId, hFecha_hora, hTabla, hEstatus)
    VALUES (?, ?, ?, 'ticket_soporte', 'Activo')
  ");
  $stH->execute([
    $msg,
    $creatorUsId,
    (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s')
  ]);

  $pdo->commit();

  json_ok([
    'tiId' => $tiId,
    'folio' => $folio,
    'ticket' => [
      'tiId' => $tiId,
      'tiProceso' => 'asignacion',
      'tiEstatus' => 'Abierto',
      'folio' => $folio,
      'usId' => $usIdCliente
    ]
  ]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_fail('Error al crear ticket.', 500, ['detail' => $e->getMessage()]);
}