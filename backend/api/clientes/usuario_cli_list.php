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

  $st = $pdo->prepare("
    SELECT usId, usNombre, usAPaterno, usAMaterno, usCorreo, usTelefono, usUsername, usEstatus
    FROM usuarios
    WHERE clId=? AND usRol='CLI' AND usEstatus<>'Eliminado'
    ORDER BY usNombre, usAPaterno
  ");
  $st->execute([$clId]);

  json_ok(['usuarios'=>$st->fetchAll()]);
} catch (Throwable $e) {
  json_fail('Error al listar usuarios CLI.', 500);
}