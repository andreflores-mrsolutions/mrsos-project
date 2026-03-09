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
$hcFechaHora = trim((string)($raw['hcFechaHora'] ?? ''));
$hcDuracionMins = isset($raw['hcDuracionMins']) ? (int)$raw['hcDuracionMins'] : 240;
$hcNombreContacto = trim((string)($raw['hcNombreContacto'] ?? ''));
$hcNumeroContacto = trim((string)($raw['hcNumeroContacto'] ?? ''));
$hcCorreoContacto = trim((string)($raw['hcCorreoContacto'] ?? ''));
$items = $raw['items'] ?? [];

if ($usId <= 0 || $clId <= 0) json_fail('Sesión inválida.', 401);
if ($csId <= 0) json_fail('Falta csId.', 400);
if ($hcFechaHora === '' || $hcNombreContacto === '' || $hcNumeroContacto === '' || $hcCorreoContacto === '') {
    json_fail('Completa todos los campos obligatorios.', 400);
}
if (!is_array($items) || count($items) === 0) {
    json_fail('Selecciona al menos un equipo.', 400);
}

try {
    $pdo->beginTransaction();

    $ins = $pdo->prepare("
        INSERT INTO health_check
        (
            clId, csId, usId,
            hcFechaHora, hcDuracionMins,
            hcNombreContacto, hcNumeroContacto, hcCorreoContacto, hcEstatus
        )
        VALUES
        (
            ?, ?, ?,
            ?, ?,
            ?, ?, ?, 'Programado'
        )
    ");
    $ins->execute([
        $clId, $csId, $usId,
        str_replace('T', ' ', $hcFechaHora) . ':00',
        $hcDuracionMins,
        $hcNombreContacto, $hcNumeroContacto, $hcCorreoContacto
    ]);

    $hcId = (int)$pdo->lastInsertId();

    $chkEq = $pdo->prepare("
        SELECT pe.peId, pe.eqId
        FROM polizasequipo pe
        INNER JOIN polizascliente pc ON pc.pcId = pe.pcId
        WHERE pe.peId = ?
          AND pe.eqId = ?
          AND pe.csId = ?
          AND pc.clId = ?
          AND pe.peEstatus = 'Activo'
        LIMIT 1
    ");

    $insItem = $pdo->prepare("
        INSERT INTO health_check_items (hcId, eqId, peId, tiId)
        VALUES (?, ?, ?, NULL)
    ");

    foreach ($items as $it) {
        $peId = (int)($it['peId'] ?? 0);
        $eqId = (int)($it['eqId'] ?? 0);
        if ($peId <= 0 || $eqId <= 0) continue;

        $chkEq->execute([$peId, $eqId, $csId, $clId]);
        if (!$chkEq->fetch()) continue;

        $insItem->execute([$hcId, $eqId, $peId]);
    }

    $desc = "[HEALTH] hcId={$hcId} · Programado desde portal cliente · csId={$csId}";
    $pdo->prepare("
        INSERT INTO historial (hDescripcion, usId, hTabla, hEstatus)
        VALUES (?, ?, 'health_check', 'Activo')
    ")->execute([$desc, $usId]);

    $pdo->commit();

    json_ok([
        'hcId' => $hcId,
        'message' => 'Health Check programado correctamente.'
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_fail('Error al crear health check. ' . $e->getMessage(), 500);
}