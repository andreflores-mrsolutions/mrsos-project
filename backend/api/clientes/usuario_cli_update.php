<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../../php/cliente_guard.php';

no_store();
require_login();
require_usRol(['MRSA','MRA']);
csrf_verify_or_fail();

$pdo = db();
$in  = read_json_body();

$clId = (int)($in['clId'] ?? 0);
$usId = (int)($in['usId'] ?? 0);

$usNombre   = trim((string)($in['usNombre'] ?? ''));
$usAPaterno = trim((string)($in['usAPaterno'] ?? ''));
$usAMaterno = trim((string)($in['usAMaterno'] ?? '-'));
$usCorreo   = trim((string)($in['usCorreo'] ?? ''));
$usTelefono = (string)($in['usTelefono'] ?? '');
$usUsername = trim((string)($in['usUsername'] ?? ''));

$setPass = isset($in['usPass']) ? (string)$in['usPass'] : ''; // opcional: reset manual

if ($clId<=0) json_fail('clId requerido.');
if ($usId<=0) json_fail('usId requerido.');
if ($usNombre==='') json_fail('usNombre requerido.');
if ($usAPaterno==='') json_fail('usAPaterno requerido.');
if ($usCorreo==='') json_fail('usCorreo requerido.');
if ($usUsername==='') json_fail('usUsername requerido.');
if ($usTelefono==='' || !ctype_digit((string)$usTelefono)) json_fail('usTelefono inválido.');

try {
  require_cliente_exists($pdo, $clId);
  require_mr_access_client($pdo, $clId);

  // Validar que el usuario pertenezca a ese cliente y sea CLI
  $st = $pdo->prepare("SELECT usId FROM usuarios WHERE usId=? AND clId=? AND usRol='CLI' AND usEstatus<>'Eliminado' LIMIT 1");
  $st->execute([$usId, $clId]);
  if (!$st->fetchColumn()) json_fail('Usuario no pertenece al cliente o no es CLI.', 409);

  // Duplicados contra otros
  $st = $pdo->prepare("SELECT 1 FROM usuarios WHERE usCorreo=? AND usId<>? AND usEstatus<>'Eliminado' LIMIT 1");
  $st->execute([$usCorreo, $usId]);
  if ($st->fetchColumn()) json_fail('Ya existe otro usuario con ese correo.', 409);

  $st = $pdo->prepare("SELECT 1 FROM usuarios WHERE usUsername=? AND usId<>? AND usEstatus<>'Eliminado' LIMIT 1");
  $st->execute([$usUsername, $usId]);
  if ($st->fetchColumn()) json_fail('Ya existe otro usuario con ese username.', 409);

  $sql = "
    UPDATE usuarios
    SET usNombre=?, usAPaterno=?, usAMaterno=?, usCorreo=?, usTelefono=?, usUsername=?
  ";
  $args = [$usNombre, $usAPaterno, $usAMaterno, $usCorreo, (int)$usTelefono, $usUsername];

  if ($setPass !== '') {
    $sql .= ", usPass=?, usEstatus='NewPass'";
    $args[] = password_hash($setPass, PASSWORD_BCRYPT);
  }

  $sql .= " WHERE usId=? AND clId=? AND usRol='CLI'";
  $args[] = $usId;
  $args[] = $clId;

  $st = $pdo->prepare($sql);
  $st->execute($args);

  json_ok(['updated'=>$st->rowCount()]);
} catch (Throwable $e) {
  json_fail('Error al actualizar usuario CLI.', 500);
}