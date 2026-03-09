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

$usRol = (string)($_SESSION['usRol'] ?? '');
$clId = (int)($_SESSION['clId'] ?? 0);
$csId = isset($_GET['csId']) ? (int)$_GET['csId'] : 0;

if (!in_array($usRol, ['CLI', 'MRSA', 'MRA', 'MRV'], true)) {
    json_fail('Sin permisos', 403);
}

if ($clId <= 0) json_fail('Sesión sin cliente.', 400);
if ($csId <= 0) json_fail('Falta csId', 400);

try {
    $st = $pdo->prepare("
        SELECT
          u.usId,
          u.usNombre, u.usAPaterno, u.usAMaterno,
          u.usCorreo,
          u.usTelefono,
          u.usUsername,
          ucr.ucrRol
        FROM usuarios u
        INNER JOIN usuario_cliente_rol ucr ON ucr.usId = u.usId
        WHERE
          u.usRol = 'CLI'
          AND u.usEstatus = 'Activo'
          AND ucr.ucrEstatus = 'Activo'
          AND ucr.clId = ?
          AND (ucr.csId = ? OR ucr.csId IS NULL)
        ORDER BY
          FIELD(ucr.ucrRol, 'ADMIN_SEDE','ADMIN_ZONA','ADMIN_GLOBAL','USUARIO','VISOR') ASC,
          u.usNombre ASC, u.usAPaterno ASC
    ");
    $st->execute([$clId, $csId]);
    $rows = $st->fetchAll();

    $clientes = array_map(static function(array $r): array {
        $nombre = trim(($r['usNombre'] ?? '') . ' ' . ($r['usAPaterno'] ?? '') . ' ' . ($r['usAMaterno'] ?? ''));
        return [
            'usId' => (int)$r['usId'],
            'nombre' => $nombre !== '' ? $nombre : (string)($r['usUsername'] ?? ('CLI-' . $r['usId'])),
            'correo' => (string)($r['usCorreo'] ?? ''),
            'telefono' => (string)($r['usTelefono'] ?? ''),
            'ucrRol' => (string)($r['ucrRol'] ?? ''),
        ];
    }, $rows);

    json_ok(['clientes' => $clientes]);
} catch (Throwable $e) {
    json_fail('Error al obtener usuarios cliente. ' . $e->getMessage(), 500);
}