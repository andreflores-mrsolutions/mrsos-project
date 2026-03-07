<?php
declare(strict_types=1);

function cli_role_get(PDO $pdo, int $usId, int $clId): ?array {
  $st = $pdo->prepare("
    SELECT ucrId, ucrRol, czId, csId, ucrEstatus
    FROM usuario_cliente_rol
    WHERE usId=? AND clId=? AND ucrEstatus='Activo'
    LIMIT 1
  ");
  $st->execute([$usId, $clId]);
  $r = $st->fetch();
  return $r ?: null;
}

function cli_can_access_cs(PDO $pdo, int $usId, int $clId, int $csId): bool {
  $ucr = cli_role_get($pdo, $usId, $clId);
  if (!$ucr) return false;

  // validar que la sede pertenezca a cliente
  $st = $pdo->prepare("SELECT czId FROM cliente_sede WHERE csId=? AND clId=? AND csEstatus='Activo' LIMIT 1");
  $st->execute([$csId, $clId]);
  $czIdSede = $st->fetchColumn();
  if ($czIdSede === false) return false;
  $czIdSede = $czIdSede !== null ? (int)$czIdSede : null;

  switch ($ucr['ucrRol']) {
    case 'ADMIN_GLOBAL':
      return true;

    case 'ADMIN_ZONA':
      if (empty($ucr['czId'])) return false;
      return $czIdSede !== null && (int)$ucr['czId'] === (int)$czIdSede;

    case 'ADMIN_SEDE':
    case 'USUARIO':
    case 'VISOR':
      if (empty($ucr['csId'])) return false;
      return (int)$ucr['csId'] === $csId;

    default:
      return false;
  }
}