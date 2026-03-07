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

$usNombre   = trim((string)($in['usNombre'] ?? ''));
$usAPaterno = trim((string)($in['usAPaterno'] ?? ''));
$usAMaterno = trim((string)($in['usAMaterno'] ?? '-'));
$usCorreo   = trim((string)($in['usCorreo'] ?? ''));
$usTelefono = (string)($in['usTelefono'] ?? '');
$usUsername = trim((string)($in['usUsername'] ?? ''));

$plainPass = isset($in['usPass']) ? (string)$in['usPass'] : ''; // opcional

if ($clId<=0) json_fail('clId requerido.');
if ($usNombre==='') json_fail('usNombre requerido.');
if ($usAPaterno==='') json_fail('usAPaterno requerido.');
if ($usCorreo==='') json_fail('usCorreo requerido.');
if ($usUsername==='') json_fail('usUsername requerido.');
if ($usTelefono==='' || !ctype_digit((string)$usTelefono)) json_fail('usTelefono inválido.');

try {
  require_cliente_exists($pdo, $clId);
  require_mr_access_client($pdo, $clId);

  // Duplicados globales (tu BD no tiene UNIQUE pero es regla de negocio)
  $st = $pdo->prepare("SELECT 1 FROM usuarios WHERE usCorreo=? AND usEstatus<>'Eliminado' LIMIT 1");
  $st->execute([$usCorreo]);
  if ($st->fetchColumn()) json_fail('Ya existe un usuario con ese correo.', 409);

  $st = $pdo->prepare("SELECT 1 FROM usuarios WHERE usUsername=? AND usEstatus<>'Eliminado' LIMIT 1");
  $st->execute([$usUsername]);
  if ($st->fetchColumn()) json_fail('Ya existe un usuario con ese username.', 409);

  // Password
  $generated = false;
  if ($plainPass === '') {
    $generated = true;
    $plainPass = bin2hex(random_bytes(4)); // 8 chars hex
  }
  $hash = password_hash($plainPass, PASSWORD_BCRYPT);

  // usResetToken/usResetTokenExpira son NOT NULL en tu BD
  $resetToken = 0;
  $resetExp = '1970-01-01 00:00:00';

  // usConfirmado NOT NULL en tu BD
  $usConfirmado = 'Si';

  $usEstatus = $generated ? 'NewPass' : 'Activo';

  // Generar usId (tu tabla no está auto_increment en dump)
  $st = $pdo->query("SELECT IFNULL(MAX(usId),0)+1 AS nextId FROM usuarios");
  $usId = (int)$st->fetchColumn();

  $st = $pdo->prepare("
    INSERT INTO usuarios (
      usId, usNombre, usAPaterno, usAMaterno, usRol, usCorreo, usPass,
      usResetToken, usResetTokenExpira, usTelefono, usTokenTelefono, usImagen,
      usNotificaciones, usTheme, usNotifInApp, usNotifMail, usNotifTicketCambio,
      usNotifMeet, usNotifVisita, usNotifFolio, clId, usConfirmado, usEstatus, usUsername
    ) VALUES (
      ?, ?, ?, ?, 'CLI', ?, ?,
      ?, ?, ?, 'N/A', 'avatar_default.png',
      'N/A', 'light', 1, 1, 1,
      1, 1, 1, ?, ?, ?, ?
    )
  ");
  $st->execute([
    $usId, $usNombre, $usAPaterno, $usAMaterno, $usCorreo, $hash,
    $resetToken, $resetExp, (int)$usTelefono,
    $clId, $usConfirmado, $usEstatus, $usUsername
  ]);

  json_ok([
    'usId'=>$usId,
    'generated_password' => $generated ? $plainPass : null,
    'usEstatus'=>$usEstatus
  ]);
} catch (Throwable $e) {
  json_fail('Error al crear usuario CLI.', 500);
}