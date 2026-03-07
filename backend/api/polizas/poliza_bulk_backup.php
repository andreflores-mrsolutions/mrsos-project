<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../../php/cliente_guard.php';
require_once __DIR__ . '/../../../php/backup.php';
require_once __DIR__ . '/../../../php/historial.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

$pdo = db();
$in = read_json_body();

$pcId = (int)($in['pcId'] ?? 0);
if ($pcId <= 0) json_fail('pcId requerido');

$st = $pdo->prepare("SELECT pcId, clId FROM polizascliente WHERE pcId=? LIMIT 1");
$st->execute([$pcId]);
$pc = $st->fetch(PDO::FETCH_ASSOC);
if (!$pc) json_fail('Póliza no existe');

$usId = (int)($_SESSION['usId'] ?? 0);
$rol  = current_usRol();
$clId = (int)$pc['clId'];

if (!mr_can_access_client($pdo, $usId, $rol, $clId)) {
  json_fail('Sin acceso al cliente', 403);
}

try {
  $baseDir = realpath(__DIR__ . '/../../../') . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'polizas';
  $res = Backup::savePolizaBackup($pdo, $pcId, $baseDir);

  Historial::log(
    $pdo,
    $usId,
    'polizasequipo',
    Historial::msg('BACKUP', 'polizasequipo', ['pcId' => $pcId], 'Backup previo a carga masiva: ' . $res['filename']),
    'Activo'
  );

  json_ok($res);
} catch (Throwable $e) {
  json_fail('Error al generar backup: ' . $e->getMessage());
}