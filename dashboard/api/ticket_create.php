<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();
csrf_verify_or_fail();

$pdo = db();

$usId = (int)($_SESSION['usId'] ?? 0);
$clId = (int)($_SESSION['clId'] ?? 0);
$usRol = (string)($_SESSION['usRol'] ?? '');

if (!in_array($usRol, ['CLI', 'MRSA', 'MRA', 'MRV'], true)) {
    json_fail('Sin permisos', 403);
}

$raw = json_decode(file_get_contents('php://input'), true);
if (!is_array($raw)) $raw = $_POST;

$csId = isset($raw['csId']) ? (int)$raw['csId'] : 0;
$eqId = isset($raw['eqId']) ? (int)$raw['eqId'] : 0;
$peId = isset($raw['peId']) ? (int)$raw['peId'] : 0;

$tiTipoTicket = trim((string)($raw['tiTipoTicket'] ?? 'Servicio'));
$tiNivelCriticidad = trim((string)($raw['tiNivelCriticidad'] ?? '2'));
$tiNombreContacto = trim((string)($raw['tiNombreContacto'] ?? ''));
$tiNumeroContacto = trim((string)($raw['tiNumeroContacto'] ?? ''));
$tiCorreoContacto = trim((string)($raw['tiCorreoContacto'] ?? ''));
$tiDescripcion = trim((string)($raw['tiDescripcion'] ?? ''));

if ($usId <= 0 || $clId <= 0) json_fail('Sesión inválida.', 401);
if ($csId <= 0 || $eqId <= 0 || $peId <= 0) json_fail('Faltan sede/equipo.', 400);
if ($tiNombreContacto === '' || $tiNumeroContacto === '' || $tiCorreoContacto === '' || $tiDescripcion === '') {
    json_fail('Completa todos los campos obligatorios.', 400);
}

try {
    $stEq = $pdo->prepare("
        SELECT pe.peId, pe.eqId, pe.csId, pc.clId
        FROM polizasequipo pe
        INNER JOIN polizascliente pc ON pc.pcId = pe.pcId
        WHERE pe.peId = ?
          AND pe.eqId = ?
          AND pe.csId = ?
          AND pc.clId = ?
          AND pe.peEstatus = 'Activo'
        LIMIT 1
    ");
    $stEq->execute([$peId, $eqId, $csId, $clId]);
    if (!$stEq->fetch()) {
        json_fail('El equipo seleccionado no pertenece a tu alcance.', 403);
    }

    $pdo->beginTransaction();

    $ins = $pdo->prepare("
        INSERT INTO ticket_soporte
        (
            clId, csId, usId, eqId, peId,
            tiDescripcion, tiEstatus, tiProceso, tiTipoTicket,
            tiExtra, tiNivelCriticidad, tiFechaCreacion,
            tiNombreContacto, tiNumeroContacto, tiCorreoContacto, estatus
        )
        VALUES
        (
            ?, ?, ?, ?, ?,
            ?, 'Abierto', 'asignacion', ?,
            '--', ?, CURDATE(),
            ?, ?, ?, 'Activo'
        )
    ");
    $ins->execute([
        $clId, $csId, $usId, $eqId, $peId,
        $tiDescripcion, $tiTipoTicket,
        $tiNivelCriticidad,
        $tiNombreContacto, $tiNumeroContacto, $tiCorreoContacto
    ]);

    $tiId = (int)$pdo->lastInsertId();

    $desc = "[TICKET] tiId={$tiId} · Creado desde portal cliente · peId={$peId} · eqId={$eqId}";
    $pdo->prepare("
        INSERT INTO historial (hDescripcion, usId, hTabla, hEstatus)
        VALUES (?, ?, 'ticket_soporte', 'Activo')
    ")->execute([$desc, $usId]);

    $pdo->commit();

    json_ok([
        'tiId' => $tiId,
        'message' => 'Ticket creado correctamente.'
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_fail('Error al crear ticket. ' . $e->getMessage(), 500);
}