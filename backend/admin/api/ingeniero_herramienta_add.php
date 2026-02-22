<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA']);
csrf_verify_or_fail();

$in = read_json_body();

$usIdIng = (int)($in['usIdIng'] ?? 0);
$nombre  = trim((string)($in['nombre'] ?? ''));
$detalle = trim((string)($in['detalle'] ?? ''));
$activo  = 1;

if ($usIdIng <= 0) json_fail('usIdIng inválido');
if ($nombre === '') json_fail('nombre es obligatorio');

$pdo = db();

// valida ing exista (ajusta si tu tabla/col se llaman diferente)
$st = $pdo->prepare("SELECT usId FROM ingenieros WHERE usId=? LIMIT 1");
$st->execute([$usIdIng]);
if (!$st->fetchColumn()) json_fail('Ingeniero no existe', 404);

// inserta herramienta
$st = $pdo->prepare("
  INSERT INTO ingeniero_herramientas (usIdIng, nombre, detalle, activo, creadoEn)
  VALUES (?, ?, ?, ?, NOW())
");
$st->execute([$usIdIng, $nombre, ($detalle!==''?$detalle:null), $activo]);

$ihtId = (int)$pdo->lastInsertId();

json_ok([
  'ihtId' => $ihtId,
  'tool' => [
    'usIdIng' => $usIdIng,
    'ihtId' => $ihtId,
    'nombre' => $nombre,
    'detalle' => $detalle,
    'activo' => 1
  ]
]);