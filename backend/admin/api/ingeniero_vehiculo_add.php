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
$placas  = strtoupper(trim((string)($in['placas'] ?? '')));
$marca   = trim((string)($in['marca'] ?? ''));
$modelo  = trim((string)($in['modelo'] ?? ''));
$color   = trim((string)($in['color'] ?? ''));
$anioRaw = trim((string)($in['anio'] ?? ''));
$anio    = ($anioRaw === '') ? null : (int)$anioRaw;

if ($usIdIng <= 0) json_fail('usIdIng inválido.');
if ($placas === '') json_fail('Placas son obligatorias.');

$pdo = db();

// valida ingeniero
$st = $pdo->prepare("SELECT usId FROM ingenieros WHERE usId=? LIMIT 1");
$st->execute([$usIdIng]);
if (!$st->fetchColumn()) json_fail('Ingeniero no existe', 404);

$pdo->prepare("
  INSERT INTO vehiculos_ingenieros (usIdIng, placas, marca, modelo, color, anio, activo)
  VALUES (?, ?, ?, ?, ?, ?, 1)
")->execute([$usIdIng, $placas, $marca, $modelo, $color, $anio]);

$viId = (int)$pdo->lastInsertId();

json_ok([
  'viId' => $viId,
  'vehiculo' => [
    'usIdIng' => $usIdIng,
    'viId' => $viId,
    'placas' => $placas,
    'marca' => $marca,
    'modelo' => $modelo,
    'color' => $color,
    'anio' => $anio,
    'activo' => 1
  ]
]);