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
$csIdSession = (int)($_SESSION['csId'] ?? 0);
$czIdSession = (int)($_SESSION['czId'] ?? 0);
$ucrRol = (string)($_SESSION['ucrRol'] ?? '');

if (!in_array($usRol, ['CLI', 'MRSA', 'MRA', 'MRV'], true)) {
    json_fail('Sin permisos', 403);
}

if ($clId <= 0) {
    json_fail('Sesión sin cliente.', 400);
}

try {
    if ($usRol === 'CLI') {
        if ($ucrRol === 'ADMIN_GLOBAL') {
            $st = $pdo->prepare("
                SELECT csId, csNombre
                FROM cliente_sede
                WHERE clId = ?
                  AND csEstatus = 'Activo'
                ORDER BY csEsPrincipal DESC, csNombre ASC
            ");
            $st->execute([$clId]);
        } elseif ($ucrRol === 'ADMIN_ZONA') {
            $st = $pdo->prepare("
                SELECT csId, csNombre
                FROM cliente_sede
                WHERE clId = ?
                  AND czId = ?
                  AND csEstatus = 'Activo'
                ORDER BY csEsPrincipal DESC, csNombre ASC
            ");
            $st->execute([$clId, $czIdSession]);
        } else {
            $st = $pdo->prepare("
                SELECT csId, csNombre
                FROM cliente_sede
                WHERE clId = ?
                  AND csId = ?
                  AND csEstatus = 'Activo'
                ORDER BY csEsPrincipal DESC, csNombre ASC
            ");
            $st->execute([$clId, $csIdSession]);
        }
    } else {
        $st = $pdo->prepare("
            SELECT csId, csNombre
            FROM cliente_sede
            WHERE clId = ?
              AND csEstatus = 'Activo'
            ORDER BY csEsPrincipal DESC, csNombre ASC
        ");
        $st->execute([$clId]);
    }

    json_ok(['sedes' => $st->fetchAll()]);
} catch (Throwable $e) {
    json_fail('Error al obtener sedes. ' . $e->getMessage(), 500);
}