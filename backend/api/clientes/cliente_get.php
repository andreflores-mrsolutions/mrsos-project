<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../../php/cliente_guard.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

$pdo = db();
$clId = (int)($_GET['clId'] ?? 0);
if ($clId<=0) json_fail('clId requerido.');

try {
  require_cliente_exists($pdo, $clId);
  require_mr_access_client($pdo, $clId);

  $st = $pdo->prepare("SELECT clId, clNombre, clDireccion, clTelefono, clCorreo, clEstatus, clImagen FROM clientes WHERE clId=? LIMIT 1");
  $st->execute([$clId]);
  $cliente = $st->fetch();
  if (!$cliente) json_fail('Cliente no existe.', 404);

  $st = $pdo->prepare("SELECT czId, clId, czNombre, czCodigo, czDescripcion, czEstatus FROM cliente_zona WHERE clId=? ORDER BY czNombre");
  $st->execute([$clId]);
  $zonas = $st->fetchAll();

  $st = $pdo->prepare("SELECT csId, clId, czId, csNombre, csCodigo, csDireccion, csEstatus, csEsPrincipal FROM cliente_sede WHERE clId=? ORDER BY csEsPrincipal DESC, csNombre");
  $st->execute([$clId]);
  $sedes = $st->fetchAll();

  $st = $pdo->prepare("
    SELECT usId, clId, usRol, usEstatus, usNombre, usAPaterno, usAMaterno, usCorreo, usTelefono, usUsername
    FROM usuarios
    WHERE clId=? AND usRol='CLI' AND usEstatus<>'Eliminado'
    ORDER BY usNombre, usAPaterno
  ");
  $st->execute([$clId]);
  $usuarios = $st->fetchAll();

  $st = $pdo->prepare("
    SELECT ucrId, usId, clId, czId, csId, ucrRol, ucrEstatus
    FROM usuario_cliente_rol
    WHERE clId=? AND ucrEstatus='Activo'
  ");
  $st->execute([$clId]);
  $roles = $st->fetchAll();

  json_ok(compact('cliente','zonas','sedes','usuarios','roles'));
} catch (Throwable $e) {
  json_fail('Error al obtener cliente.', 500);
}