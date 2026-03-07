<?php
declare(strict_types=1);

require_once __DIR__ . '/json.php';

function require_cliente_exists(PDO $pdo, int $clId): void {
  $st = $pdo->prepare("SELECT 1 FROM clientes WHERE clId=? LIMIT 1");
  $st->execute([$clId]);
  if (!$st->fetchColumn()) json_fail('Cliente no existe.', 404);
}

function require_zona_of_cliente(PDO $pdo, int $clId, int $czId): void {
  $st = $pdo->prepare("SELECT 1 FROM cliente_zona WHERE czId=? AND clId=? LIMIT 1");
  $st->execute([$czId, $clId]);
  if (!$st->fetchColumn()) json_fail('Zona no pertenece al cliente.', 409);
}

function require_sede_of_cliente(PDO $pdo, int $clId, int $csId): array {
  $st = $pdo->prepare("SELECT csId, czId FROM cliente_sede WHERE csId=? AND clId=? LIMIT 1");
  $st->execute([$csId, $clId]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  if (!$r) json_fail('Sede no pertenece al cliente.', 409);
  return $r;
}

function require_mr_access_client(PDO $pdo, int $clId): void {
  $usId = (int)($_SESSION['usId'] ?? 0);
  $usRol = (string)($_SESSION['usRol'] ?? '');
  if (!mr_can_access_client($pdo, $usId, $usRol, $clId)) {
    json_fail('Sin acceso a este cliente.', 403);
  }
}