<?php
declare(strict_types=1);

require_once __DIR__ . '/json.php';

function ticket_scope_from_session(string $ticketAlias = 'ts', string $sedeAlias = 'cs'): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $usRol  = (string)($_SESSION['usRol'] ?? '');
    $clId   = isset($_SESSION['clId']) ? (int)$_SESSION['clId'] : 0;
    $csId   = isset($_SESSION['csId']) ? (int)$_SESSION['csId'] : 0;
    $czId   = isset($_SESSION['czId']) ? (int)$_SESSION['czId'] : 0;
    $ucrRol = (string)($_SESSION['ucrRol'] ?? '');

    // ===== USUARIOS INTERNOS MR SOLUTIONS =====
    // Por ahora todos los roles internos ven todo.
    // Después, si quieres, refinamos MRSA/MRV.
    if (in_array($usRol, ['MRA', 'MRSA', 'MRV'], true)) {
        return [
            'scope'  => 'mr_global',
            'sql'    => '1=1',
            'params' => [],
        ];
    }

    // ===== CLIENTES =====
    if ($usRol === 'CLI') {
        if ($clId <= 0) {
            json_fail('Sesión cliente inválida: falta clId.', 401);
        }

        // ADMIN_GLOBAL => todo su cliente
        if ($ucrRol === 'ADMIN_GLOBAL') {
            return [
                'scope'  => 'cliente_global',
                'sql'    => "{$ticketAlias}.clId = ?",
                'params' => [$clId],
            ];
        }

        // ADMIN_ZONA => solo su cliente + su zona
        if ($ucrRol === 'ADMIN_ZONA') {
            if ($czId <= 0) {
                json_fail('El usuario cliente no tiene czId asignado.', 403);
            }

            return [
                'scope'  => 'cliente_zona',
                'sql'    => "{$ticketAlias}.clId = ? AND {$sedeAlias}.czId = ?",
                'params' => [$clId, $czId],
            ];
        }

        // ADMIN_SEDE / USUARIO / VISOR => solo su sede
        if (in_array($ucrRol, ['ADMIN_SEDE', 'USUARIO', 'VISOR'], true)) {
            if ($csId <= 0) {
                json_fail('El usuario cliente no tiene csId asignado.', 403);
            }

            return [
                'scope'  => 'cliente_sede',
                'sql'    => "{$ticketAlias}.clId = ? AND {$ticketAlias}.csId = ?",
                'params' => [$clId, $csId],
            ];
        }

        // Fallback de seguridad
        return [
            'scope'  => 'cliente_fallback',
            'sql'    => "{$ticketAlias}.clId = ?",
            'params' => [$clId],
        ];
    }

    json_fail('Rol sin permisos para consultar tickets.', 403);
    return []; // Nunca se alcanza, pero satisface el return type
}