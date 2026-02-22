<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA']);
csrf_verify_or_fail();

$in = read_json_body();

$usIdIng  = (int)($in['usIdIng'] ?? 0);
$pantalon = trim((string)($in['pantalon'] ?? 'pantalón de mezclilla'));
$camisa   = trim((string)($in['camisa'] ?? 'camisa/polo'));
$calzado  = trim((string)($in['calzado'] ?? 'botas/zapatos'));
$notas    = trim((string)($in['notas'] ?? ''));

if ($usIdIng <= 0) json_fail('usIdIng inválido.');

$pdo = db();
$st = $pdo->prepare("SELECT usId FROM ingenieros WHERE usId=? LIMIT 1");
$st->execute([$usIdIng]);
if (!$st->fetchColumn()) json_fail('Ingeniero no existe', 404);

$pdo->prepare("
  INSERT INTO ingeniero_vestimenta (usIdIng, pantalon, camisa, calzado, notas)
  VALUES (?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    pantalon=VALUES(pantalon),
    camisa=VALUES(camisa),
    calzado=VALUES(calzado),
    notas=VALUES(notas)
")->execute([$usIdIng, $pantalon, $camisa, $calzado, $notas]);

json_ok([
  'vestimenta' => [
    'usIdIng' => $usIdIng,
    'pantalon' => $pantalon,
    'camisa' => $camisa,
    'calzado' => $calzado,
    'notas' => $notas
  ]
]);