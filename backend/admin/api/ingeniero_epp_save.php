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
$casco   = (int)($in['casco'] ?? 0) ? 1 : 0;
$chaleco = (int)($in['chaleco'] ?? 0) ? 1 : 0;
$botas   = (int)($in['botas'] ?? 0) ? 1 : 0;
$notas   = trim((string)($in['notas'] ?? ''));

if ($usIdIng <= 0) json_fail('usIdIng inválido');

$pdo = db();

// valida que el ing exista
$st = $pdo->prepare("SELECT usId FROM ingenieros WHERE usId=? LIMIT 1");
$st->execute([$usIdIng]);
if (!$st->fetchColumn()) json_fail('Ingeniero no existe', 404);

// === upsert (MySQL): requiere unique key en ingeniero_epp.usIdIng
// Si tu tabla usa `usId` en vez de `usIdIng`, dime y lo ajusto.
// Asumo tabla: ingeniero_epp(usIdIng, casco, chaleco, botas, notas)
$pdo->prepare("
  INSERT INTO ingeniero_epp (usIdIng, casco, chaleco, botas, notas)
  VALUES (?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    casco=VALUES(casco),
    chaleco=VALUES(chaleco),
    botas=VALUES(botas),
    notas=VALUES(notas)
")->execute([$usIdIng, $casco, $chaleco, $botas, $notas]);

json_ok([
  'usIdIng' => $usIdIng,
  'epp' => [
    'usIdIng' => $usIdIng,
    'casco' => $casco,
    'chaleco' => $chaleco,
    'botas' => $botas,
    'notas' => $notas
  ]
]);