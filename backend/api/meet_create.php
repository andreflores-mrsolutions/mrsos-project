<?php
// admin/api/meet_create.php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/json.php';
require_once __DIR__ . '/../../php/csrf.php';

require_login();
csrf_verify_or_fail();

$pdo   = db();
$usId  = (int)($_SESSION['usId'] ?? 0);
$usRol = current_usRol();

$body = read_json_body();

$tiId = isset($body['tiId']) ? (int)$body['tiId'] : 0;
if ($tiId <= 0) json_fail('Falta tiId', 400);

$opciones = $body['opciones'] ?? null; // array de 3 datetimes
if (!is_array($opciones) || count($opciones) !== 3) {
    json_fail('Debes enviar 3 opciones de fecha/hora.', 400);
}

$plataforma = isset($body['plataforma']) ? trim((string)$body['plataforma']) : null;
$link       = isset($body['link']) ? trim((string)$body['link']) : null;
$motivo     = isset($body['motivo']) ? trim((string)$body['motivo']) : null;

if ($plataforma === '') $plataforma = null;
if ($link === '') $link = null;
if ($motivo === '') $motivo = null;

// autor tipo: cliente o ingeniero (según rol)
$autorTipo = is_cli() ? 'cliente' : 'ingeniero';

// 1) Validar acceso al ticket (MR puede ver cualquier ticket del cliente permitido; CLI solo su clId)
try {
    $st = $pdo->prepare("SELECT tiId, clId, tiProceso, estatus FROM ticket_soporte WHERE tiId=? LIMIT 1");
    $st->execute([$tiId]);
    $t = $st->fetch();
    if (!$t) json_fail('Ticket no encontrado.', 404);
    if (($t['estatus'] ?? '') !== 'Activo') json_fail('Ticket no activo.', 409);

    $clIdTicket = (int)$t['clId'];

    if (is_cli()) {
        $clIdSes = (int)($_SESSION['clId'] ?? 0);
        if ($clIdSes <= 0 || $clIdSes !== $clIdTicket) {
            json_fail('Sin permisos para este ticket.', 403);
        }
    } else {
        $ROL = $_SESSION['usRol'] ?? null;

        $ROLES_PERMITIDOS = ['CLI', 'MRV', 'MRA', 'MRSA'];

        if (!$ROL || !in_array($ROL, $ROLES_PERMITIDOS, true)) {
            http_response_code(403);
            exit('Acceso no autorizado');
        }
    }

    // 2) Normalizar 3 datetimes y construir fin por default (30 mins)
    $starts = [];
    foreach ($opciones as $i => $raw) {
        $raw = trim((string)$raw);
        if ($raw === '') json_fail('Opción inválida.', 400);

        // Acepta "YYYY-MM-DD HH:MM:SS" o "YYYY-MM-DDTHH:MM" (datetime-local)
        $raw = str_replace('T', ' ', $raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $raw)) $raw .= ':00';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $raw)) {
            json_fail('Formato de fecha inválido en una opción.', 400);
        }

        $starts[] = $raw;
    }

    // 3) Insertar 3 propuestas como un “batch” usando el mismo mpCreadoEn
    $createdAt = date('Y-m-d H:i:s'); // mismo timestamp para agrupar
    $pdo->beginTransaction();

    $ins = $pdo->prepare("
    INSERT INTO ticket_meet_propuestas
      (tiId, mpAutorTipo, mpModo, mpPlataforma, mpLink, mpInicio, mpFin, mpEstado, mpCreadoEn)
    VALUES
      (?, ?, 'propuesta', ?, ?, ?, ?, 'pendiente', ?)
  ");

    foreach ($starts as $s) {
        $startDT = new DateTime($s);
        $endDT = clone $startDT;
        $endDT->modify('+30 minutes');

        $ins->execute([
            $tiId,
            $autorTipo,
            $plataforma,
            $link,
            $startDT->format('Y-m-d H:i:s'),
            $endDT->format('Y-m-d H:i:s'),
            $createdAt
        ]);
    }

    // Cache mínimo opcional en ticket_soporte (para UI rápida)
    // (No rompe nada aunque luego tomemos la verdad desde propuestas)
    $upd = $pdo->prepare("
    UPDATE ticket_soporte
    SET tiMeetEstado='pendiente',
        tiMeetModo=?,
        tiMeetPlataforma=?,
        tiMeetEnlace=?,
        tiMeetActivo=?
    WHERE tiId=?
    LIMIT 1
  ");
    $modo = ($autorTipo === 'cliente') ? 'propuesta_cliente' : 'propuesta_ingeniero';
    $activo = ($autorTipo === 'cliente') ? 'meet solicitado cliente' : 'meet solicitado ingeniero';
    $upd->execute([$modo, $plataforma, $link, $activo, $tiId]);

    $pdo->commit();

    json_ok([
        'tiId' => $tiId,
        'autorTipo' => $autorTipo,
        'createdAt' => $createdAt
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_fail('Error al crear meet.', 500);
}
