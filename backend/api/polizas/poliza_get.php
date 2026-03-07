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

$pdo = db();
$pcId = (int)($_GET['pcId'] ?? 0);
if ($pcId <= 0) json_fail('pcId requerido');

$st = $pdo->prepare("
  SELECT pc.*, c.clNombre
  FROM polizascliente pc
  JOIN clientes c ON c.clId = pc.clId
  WHERE pc.pcId=?
  LIMIT 1
");
$st->execute([$pcId]);
$pol = $st->fetch(PDO::FETCH_ASSOC);
if (!$pol) json_fail('Póliza no existe');

$clId = (int)$pol['clId'];
$usId = (int)($_SESSION['usId'] ?? 0);
$rol  = current_usRol();
if (!mr_can_access_client($pdo, $usId, $rol, $clId)) json_fail('Sin acceso al cliente');

// vendedor activo
$st = $pdo->prepare("SELECT usId FROM polizavendedor WHERE pcId=? AND pvEstatus='Activo' ORDER BY pvId DESC LIMIT 1");
$st->execute([$pcId]);
$pvUsId = (int)($st->fetchColumn() ?: 0);

// responsable cuenta
$st = $pdo->prepare("SELECT usId FROM cuentas WHERE pcId=? LIMIT 1");
$st->execute([$pcId]);
$cuUsId = (int)($st->fetchColumn() ?: 0);

// lista de usuarios MR (ajusta filtro si tienes una tabla/rol para ventas)
$st = $pdo->prepare("
  SELECT usId,
         CONCAT_WS(' ', usNombre, usAPaterno, usAMaterno) AS usNombre,
         usCorreo
  FROM usuarios
  WHERE usEstatus='Activo' AND usRol IN ('MRSA','MRA','MRV')
  ORDER BY usNombre ASC
  LIMIT 500
");
$st->execute();
$users = $st->fetchAll(PDO::FETCH_ASSOC);

json_ok([
  'poliza' => $pol,
  'pvUsId' => $pvUsId,
  'cuUsId' => $cuUsId,
  'vendedores' => $users,
  'cuentasUsers' => $users
]);