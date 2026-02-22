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
$ieTipo  = trim((string)($in['ieTipo'] ?? ''));
$ieMarca = trim((string)($in['ieMarca'] ?? ''));
$ieModelo= trim((string)($in['ieModelo'] ?? ''));
$ieSerie = trim((string)($in['ieSerie'] ?? ''));
$ieDesc  = trim((string)($in['ieDescripcion'] ?? ''));

if ($usIdIng <= 0 || $ieTipo === '') json_fail('Datos inválidos (usIdIng/ieTipo).');

$pdo = db();

$st = $pdo->prepare("SELECT usId FROM ingenieros WHERE usId=? LIMIT 1");
$st->execute([$usIdIng]);
if (!$st->fetchColumn()) json_fail('Ingeniero no existe', 404);

$pdo->prepare("
  INSERT INTO ingeniero_equipos (usId, ieTipo, ieMarca, ieModelo, ieSerie, ieDescripcion, ieActivo)
  VALUES (?, ?, ?, ?, ?, ?, 1)
")->execute([$usIdIng, $ieTipo, $ieMarca, $ieModelo, $ieSerie, $ieDesc]);

$ieId = (int)$pdo->lastInsertId();

json_ok([
  'ieId' => $ieId,
  'equipo' => [
    'usIdIng' => $usIdIng,
    'ieId' => $ieId,
    'ieTipo' => $ieTipo,
    'ieMarca' => $ieMarca,
    'ieModelo' => $ieModelo,
    'ieSerie' => $ieSerie,
    'ieDescripcion' => $ieDesc,
    'ieActivo' => 1
  ]
]);