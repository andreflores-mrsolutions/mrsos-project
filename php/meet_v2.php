<?php
// ../php/meet_v2.php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // NO ensuciar JSON
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/conexion.php';

function jexit(array $out, int $code = 200): void {
  http_response_code($code);
  if (ob_get_length()) { @ob_clean(); }
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  exit;
}

function s($v): string { return trim((string)$v); }

function isMr(): bool {
  $rol = strtoupper(trim((string)($_SESSION['usRol'] ?? '')));
  return in_array($rol, ['MR', 'MRA', 'ADMIN_GLOBAL', 'ADMIN_ZONA', 'ADMIN_SEDE'], true);
}

// =====================
// Auth + base
// =====================
if (empty($_SESSION['usId'])) {
  jexit(['success' => false, 'error' => 'No autenticado'], 401);
}

$usId = (int)($_SESSION['usId'] ?? 0);
$clId = (int)($_SESSION['clId'] ?? 0);
$mr   = isMr();
$origen = $mr ? 'ingeniero' : 'cliente';

$accion = strtolower(s($_POST['accion'] ?? ''));
// acciones soportadas:
// - generar_single   (proponer/asignar un meet en ticket_soporte)
// - proponer_ventanas (inserta N opciones en ticket_meet_propuestas)
// - confirmar_propuesta (elige mpId -> actualiza ticket_soporte)
// - reprogramar
// - cancelar

$tiId = (int)($_POST['tiId'] ?? 0);
if ($tiId <= 0 || $accion === '') {
  jexit(['success' => false, 'error' => 'Parámetros inválidos'], 400);
}

// =====================
// Validar autorización al ticket
// =====================
$tk = null;
$sqlTk = "SELECT tiId, clId, tiProceso, tiMeetEstado, tiMeetModo, tiMeetFecha, tiMeetHora, tiMeetPlataforma, tiMeetEnlace
          FROM ticket_soporte WHERE tiId=? LIMIT 1";
$st = $conectar->prepare($sqlTk);
if (!$st) jexit(['success'=>false,'error'=>'DB error (prepare ticket)'], 500);
$st->bind_param("i", $tiId);
$st->execute();
$tk = $st->get_result()->fetch_assoc();
$st->close();

if (!$tk) {
  jexit(['success' => false, 'error' => 'Ticket no encontrado'], 404);
}

if (!$mr) {
  // Cliente solo puede tocar tickets de su clId
  if ($clId <= 0 || (int)$tk['clId'] !== $clId) {
    jexit(['success'=>false,'error'=>'No autorizado'], 403);
  }
}

// =====================
// Helpers para actualizar respuestas/vencimientos
// =====================
function touchRespuesta(mysqli $conectar, int $tiId, string $origen): void {
  if ($origen === 'cliente') {
    $conectar->query("UPDATE ticket_soporte SET tiUltimaRespuestaCliente = NOW() WHERE tiId=".(int)$tiId);
  } else {
    $conectar->query("UPDATE ticket_soporte SET tiUltimaRespuestaIng = NOW() WHERE tiId=".(int)$tiId);
  }
}

function setVenceCliente48h(mysqli $conectar, int $tiId): void {
  $conectar->query("UPDATE ticket_soporte SET tiVenceRespuestaCliente = DATE_ADD(NOW(), INTERVAL 48 HOUR) WHERE tiId=".(int)$tiId);
}

// =====================
// Acción: generar_single
// =====================
if ($accion === 'generar_single') {
  $tipo = strtolower(s($_POST['tipo'] ?? 'proponer')); // proponer | asignar
  if (!in_array($tipo, ['proponer','asignar'], true)) {
    jexit(['success'=>false,'error'=>'tipo inválido'], 400);
  }

  $fecha = s($_POST['fecha'] ?? ''); // YYYY-MM-DD
  $hora  = s($_POST['hora'] ?? '');  // HH:MM o HH:MM:SS
  $plataforma = s($_POST['plataforma'] ?? '');
  $link = s($_POST['link'] ?? '');
  $quienHara = strtolower(s($_POST['quienHara'] ?? 'cliente')); // cliente | ingeniero

  if ($fecha === '' || $hora === '' || $plataforma === '') {
    jexit(['success'=>false,'error'=>'Fecha, hora y plataforma son obligatorias'], 400);
  }

  // Regla UI: si Teams u Otra -> link obligatorio
  $platLower = strtolower($plataforma);
  $isTeamsOrOther = (str_contains($platLower,'teams') || str_contains($platLower,'otra'));
  if ($isTeamsOrOther && $link === '') {
    jexit(['success'=>false,'error'=>'El link es obligatorio para Teams u Otra plataforma'], 400);
  }

  // Modo según origen + tipo
  $modo = null;
  if ($origen === 'cliente' && $tipo === 'proponer') $modo = 'propuesta_cliente';
  if ($origen === 'cliente' && $tipo === 'asignar')  $modo = 'asignado_cliente';
  if ($origen === 'ingeniero' && $tipo === 'proponer') $modo = 'propuesta_ingeniero';
  if ($origen === 'ingeniero' && $tipo === 'asignar')  $modo = 'asignado_ingeniero';

  if (!$modo) jexit(['success'=>false,'error'=>'Modo inválido'], 400);

  // Estado queda pendiente
  $estado = 'pendiente';

  // tiMeetActivo (quién creará/pondrá meet)
  $tiMeetActivo = null;
  if ($quienHara === 'cliente') $tiMeetActivo = ($origen === 'cliente') ? 'meet cliente' : 'meet solicitado cliente';
  if ($quienHara === 'ingeniero') $tiMeetActivo = ($origen === 'ingeniero') ? 'meet ingeniero' : 'meet solicitado ingeniero';
  if (!$tiMeetActivo) $tiMeetActivo = 'meet cliente';

  $autorNombre = s($_SESSION['usNombre'] ?? ($_SESSION['usUsuario'] ?? ($origen === 'cliente' ? 'Cliente' : 'Ingeniero')));

  $sql = "UPDATE ticket_soporte
          SET tiProceso='meet',
              tiMeetFecha=?,
              tiMeetHora=?,
              tiMeetPlataforma=?,
              tiMeetEnlace=?,
              tiMeetModo=?,
              tiMeetEstado=?,
              tiMeetActivo=?,
              tiMeetAutorNombre=?,
              tiMeetCancelBy=NULL,
              tiMeetCancelMotivo=NULL,
              tiMeetCancelFecha=NULL
          WHERE tiId=?";
  $st = $conectar->prepare($sql);
  if (!$st) jexit(['success'=>false,'error'=>'DB error (update meet)'], 500);

  $st->bind_param("ssssssssi", $fecha, $hora, $plataforma, $link, $modo, $estado, $tiMeetActivo, $autorNombre, $tiId);
  $ok = $st->execute();
  $st->close();

  touchRespuesta($conectar, $tiId, $origen);

  if (!$ok) jexit(['success'=>false,'error'=>'No se pudo actualizar meet'], 500);

  jexit([
    'success'=>true,
    'accion'=>'generar_single',
    'tiId'=>$tiId,
    'tiProceso'=>'meet',
    'tiMeetModo'=>$modo,
    'tiMeetEstado'=>$estado,
    'tiMeetFecha'=>$fecha,
    'tiMeetHora'=>$hora,
    'tiMeetPlataforma'=>$plataforma,
    'tiMeetEnlace'=>$link,
    'tiMeetActivo'=>$tiMeetActivo,
  ]);
}

// =====================
// Acción: proponer_ventanas (múltiples)
// POST: propuestas = JSON string
// [
//   {"inicio":"2026-01-22 10:00:00","fin":"2026-01-22 11:00:00"},
//   {"inicio":"2026-01-23 10:00:00","fin":"2026-01-23 11:00:00"}
// ]
// =====================
if ($accion === 'proponer_ventanas') {
  $plataforma = s($_POST['plataforma'] ?? '');
  $link = s($_POST['link'] ?? '');
  $quienHara = strtolower(s($_POST['quienHara'] ?? 'cliente')); // cliente | ingeniero

  $raw = s($_POST['propuestas'] ?? '');
  if ($raw === '') jexit(['success'=>false,'error'=>'propuestas requerido'], 400);

  $arr = json_decode($raw, true);
  if (!is_array($arr) || count($arr) === 0) {
    jexit(['success'=>false,'error'=>'propuestas inválidas'], 400);
  }

  // Máx 5 por sanidad
  if (count($arr) > 5) {
    jexit(['success'=>false,'error'=>'Máximo 5 propuestas'], 400);
  }

  // Estado/Modo en ticket_soporte para activar “acción requerida”
  $modo = ($origen === 'cliente') ? 'propuesta_cliente' : 'propuesta_ingeniero';
  $estado = 'pendiente';

  $autorNombre = s($_SESSION['usNombre'] ?? ($_SESSION['usUsuario'] ?? ($origen === 'cliente' ? 'Cliente' : 'Ingeniero')));

  // Guardar propuestas
  $sqlIns = "INSERT INTO ticket_meet_propuestas
              (tiId, mpAutorTipo, mpModo, mpPlataforma, mpLink, mpInicio, mpFin, mpEstado)
            VALUES (?,?,?,?,?,?,?, 'pendiente')";
  $ins = $conectar->prepare($sqlIns);
  if (!$ins) jexit(['success'=>false,'error'=>'DB error (insert propuestas)'], 500);

  $inserted = [];

  foreach ($arr as $p) {
    $ini = s($p['inicio'] ?? '');
    $fin = s($p['fin'] ?? '');
    if ($ini === '' || $fin === '') continue;

    $mpAutorTipo = ($origen === 'cliente') ? 'cliente' : 'ingeniero';
    $mpModo = 'propuesta';

    $ins->bind_param("issssss", $tiId, $mpAutorTipo, $mpModo, $plataforma, $link, $ini, $fin);
    $ok = $ins->execute();
    if ($ok) {
      $inserted[] = (int)$ins->insert_id;
    }
  }
  $ins->close();

  if (count($inserted) === 0) {
    jexit(['success'=>false,'error'=>'No se insertaron propuestas'], 500);
  }

  // Poner el ticket en meet + pendiente
  $sqlUp = "UPDATE ticket_soporte
            SET tiProceso='meet',
                tiMeetModo=?,
                tiMeetEstado=?,
                tiMeetPlataforma=?,
                tiMeetEnlace=?,
                tiMeetAutorNombre=?
            WHERE tiId=?";
  $st = $conectar->prepare($sqlUp);
  if (!$st) jexit(['success'=>false,'error'=>'DB error (update ticket meet estado)'], 500);
  $st->bind_param("sssssi", $modo, $estado, $plataforma, $link, $autorNombre, $tiId);
  $st->execute();
  $st->close();

  touchRespuesta($conectar, $tiId, $origen);

  jexit([
    'success'=>true,
    'accion'=>'proponer_ventanas',
    'tiId'=>$tiId,
    'tiMeetModo'=>$modo,
    'tiMeetEstado'=>$estado,
    'insertedMpIds'=>$inserted,
  ]);
}

// =====================
// Acción: confirmar_propuesta
// POST: mpId (opción elegida)
// =====================
if ($accion === 'aceptar_actual') {
  // Solo acepta si está pendiente
  $sqlUp = "UPDATE ticket_soporte
            SET tiMeetEstado='confirmado'
            WHERE tiId=? AND LOWER(COALESCE(tiMeetEstado,''))='pendiente'";
  $st = $conectar->prepare($sqlUp);
  if (!$st) jexit(['success'=>false,'error'=>'DB error (aceptar)'], 500);
  $st->bind_param("i", $tiId);
  $st->execute();
  $ok = $st->affected_rows > 0;
  $st->close();

  touchRespuesta($conectar, $tiId, $origen);

  if (!$ok) jexit(['success'=>false,'error'=>'No se pudo aceptar (no estaba pendiente)'], 400);

  jexit([
    'success'=>true,
    'accion'=>'aceptar_actual',
    'tiId'=>$tiId,
    'tiMeetEstado'=>'confirmado'
  ]);
}

if ($accion === 'confirmar_propuesta') {
  $mpId = (int)($_POST['mpId'] ?? 0);
  if ($mpId <= 0) jexit(['success'=>false,'error'=>'mpId inválido'], 400);

  $sql = "SELECT mpId, tiId, mpInicio, mpFin, mpPlataforma, mpLink, mpEstado
          FROM ticket_meet_propuestas
          WHERE mpId=? AND tiId=? LIMIT 1";
  $st = $conectar->prepare($sql);
  if (!$st) jexit(['success'=>false,'error'=>'DB error (select propuesta)'], 500);
  $st->bind_param("ii", $mpId, $tiId);
  $st->execute();
  $p = $st->get_result()->fetch_assoc();
  $st->close();

  if (!$p) jexit(['success'=>false,'error'=>'Propuesta no encontrada'], 404);
  if (($p['mpEstado'] ?? '') !== 'pendiente') jexit(['success'=>false,'error'=>'Propuesta no disponible'], 400);

  // Parse inicio
  $inicio = s($p['mpInicio'] ?? '');
  $plataforma = s($p['mpPlataforma'] ?? '');
  $link = s($p['mpLink'] ?? '');

  $fecha = substr($inicio, 0, 10);
  $hora  = substr($inicio, 11, 8);

  // Confirmar meet en ticket_soporte
  $modo = ($origen === 'cliente') ? 'asignado_cliente' : 'asignado_ingeniero';
  $estado = 'confirmado';
  $autorNombre = s($_SESSION['usNombre'] ?? ($_SESSION['usUsuario'] ?? ($origen === 'cliente' ? 'Cliente' : 'Ingeniero')));

  $sqlUp = "UPDATE ticket_soporte
            SET tiMeetFecha=?,
                tiMeetHora=?,
                tiMeetPlataforma=?,
                tiMeetEnlace=?,
                tiMeetModo=?,
                tiMeetEstado=?,
                tiMeetAutorNombre=?
            WHERE tiId=?";
  $st = $conectar->prepare($sqlUp);
  if (!$st) jexit(['success'=>false,'error'=>'DB error (confirm meet)'], 500);
  $st->bind_param("sssssssi", $fecha, $hora, $plataforma, $link, $modo, $estado, $autorNombre, $tiId);
  $st->execute();
  $st->close();

  // Marcar propuesta aceptada y rechazar otras pendientes
  $conectar->query("UPDATE ticket_meet_propuestas SET mpEstado='aceptada' WHERE mpId=".(int)$mpId." AND tiId=".(int)$tiId);
  $conectar->query("UPDATE ticket_meet_propuestas SET mpEstado='rechazada' WHERE tiId=".(int)$tiId." AND mpId<>".(int)$mpId." AND mpEstado='pendiente'");

  touchRespuesta($conectar, $tiId, $origen);

  jexit([
    'success'=>true,
    'accion'=>'confirmar_propuesta',
    'tiId'=>$tiId,
    'mpId'=>$mpId,
    'tiMeetModo'=>$modo,
    'tiMeetEstado'=>$estado,
    'tiMeetFecha'=>$fecha,
    'tiMeetHora'=>$hora,
    'tiMeetPlataforma'=>$plataforma,
    'tiMeetEnlace'=>$link,
  ]);
}

// =====================
// Acción: reprogramar (single)
// =====================
if ($accion === 'reprogramar') {
  $fecha = s($_POST['fecha'] ?? '');
  $hora  = s($_POST['hora'] ?? '');
  $plataforma = s($_POST['plataforma'] ?? '');
  $link = s($_POST['link'] ?? '');

  if ($fecha === '' || $hora === '' || $plataforma === '') {
    jexit(['success'=>false,'error'=>'Fecha, hora y plataforma son obligatorias'], 400);
  }

  $estado = 'reprogramar';
  $autorNombre = s($_SESSION['usNombre'] ?? ($_SESSION['usUsuario'] ?? ($origen === 'cliente' ? 'Cliente' : 'Ingeniero')));

  $sqlUp = "UPDATE ticket_soporte
            SET tiMeetFecha=?,
                tiMeetHora=?,
                tiMeetPlataforma=?,
                tiMeetEnlace=?,
                tiMeetEstado=?,
                tiMeetAutorNombre=?
            WHERE tiId=?";
  $st = $conectar->prepare($sqlUp);
  if (!$st) jexit(['success'=>false,'error'=>'DB error (reprogramar)'], 500);
  $st->bind_param("ssssssi", $fecha, $hora, $plataforma, $link, $estado, $autorNombre, $tiId);
  $ok = $st->execute();
  $st->close();

  touchRespuesta($conectar, $tiId, $origen);

  if (!$ok) jexit(['success'=>false,'error'=>'No se pudo reprogramar'], 500);

  jexit([
    'success'=>true,
    'accion'=>'reprogramar',
    'tiId'=>$tiId,
    'tiMeetEstado'=>$estado,
    'tiMeetFecha'=>$fecha,
    'tiMeetHora'=>$hora,
    'tiMeetPlataforma'=>$plataforma,
    'tiMeetEnlace'=>$link,
  ]);
}

// =====================
// Acción: cancelar (Eliminar meet)
// - No existe enum "cancelado" en tiMeetEstado,
//   por eso usamos "rechazado" y llenamos campos cancel.
// - Política: si cancela cliente => vence respuesta en 48h
// =====================
if ($accion === 'cancelar') {
  $motivo = s($_POST['motivo'] ?? '');

  $sqlUp = "UPDATE ticket_soporte
            SET tiMeetEstado='rechazado',
                tiMeetCancelBy=?,
                tiMeetCancelMotivo=?,
                tiMeetCancelFecha=NOW()
            WHERE tiId=?";
  $st = $conectar->prepare($sqlUp);
  if (!$st) jexit(['success'=>false,'error'=>'DB error (cancelar)'], 500);

  $st->bind_param("ssi", $origen, $motivo, $tiId);
  $ok = $st->execute();
  $st->close();

  // cancelar propuestas pendientes
  $conectar->query("UPDATE ticket_meet_propuestas SET mpEstado='cancelada' WHERE tiId=".(int)$tiId." AND mpEstado='pendiente'");

  touchRespuesta($conectar, $tiId, $origen);

  if ($origen === 'cliente') {
    setVenceCliente48h($conectar, $tiId);
  }

  if (!$ok) jexit(['success'=>false,'error'=>'No se pudo cancelar'], 500);

  jexit([
    'success'=>true,
    'accion'=>'cancelar',
    'tiId'=>$tiId,
    'tiMeetEstado'=>'rechazado',
    'tiMeetCancelBy'=>$origen
  ]);
}

// =====================
// Si llega aquí, no soportado
// =====================
jexit(['success'=>false,'error'=>'Acción no soportada'], 400);
