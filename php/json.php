<?php
declare(strict_types=1);

function json_ok(array $data = []): void {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
  exit;
}

function json_fail(string $error, int $code = 400, array $extra = []): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(array_merge(['success' => false, 'error' => $error], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

function read_json_body(): array {
  $raw = file_get_contents('php://input');
  $json = $raw ? json_decode($raw, true) : null;
  return is_array($json) ? $json : [];
}
function mr_can_access_client(PDO $pdo, int $usId, string $usRol, int $clId): bool
{
    // 1. Roles con acceso global
    $rolesGlobal = ['MRSA']; // super admin

    if (in_array($usRol, $rolesGlobal, true)) {
        return true;
    }

    // 2. Si es cliente, solo puede ver su propio cliente
    if ($usRol === 'CLI') {

        $st = $pdo->prepare("
            SELECT clId
            FROM usuarios_clientes
            WHERE usId = ?
            LIMIT 1
        ");
        $st->execute([$usId]);

        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) return false;

        return (int)$row['clId'] === $clId;
    }

    // 3. Roles MR con acceso por asignación
    $rolesMR = ['MRA','MRV','MR'];

    if (in_array($usRol, $rolesMR, true)) {

        $st = $pdo->prepare("
            SELECT 1
            FROM usuarios_clientes
            WHERE usId = ?
            AND clId = ?
            LIMIT 1
        ");

        $st->execute([$usId, $clId]);

        return (bool)$st->fetchColumn();
    }

    return false;
}