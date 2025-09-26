<?php
// php/adm_usuarios_create.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'conexion.php';
session_start();

$rol = strtoupper(trim($_SESSION['usRol'] ?? ''));
if (!in_array($rol, ['AC','MRA','MRSA'], true)) {
  echo json_encode(['success'=>false,'error'=>'No autorizado']); exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$usNombre   = trim($in['usNombre']   ?? '');
$usAPaterno = trim($in['usAPaterno'] ?? '');
$usAMaterno = trim($in['usAMaterno'] ?? '');
$usCorreo   = trim($in['usCorreo']   ?? '');
$usTelefono = trim($in['usTelefono'] ?? '');
$usUsername = trim($in['usUsername'] ?? '');
$usRolNew   = strtoupper(trim($in['usRol'] ?? ''));  // AC | UC | EC
$csId       = (int)($in['csId'] ?? 0);
$clId       = (int)($in['clId'] ?? 0);

if ($usNombre==='' || $usAPaterno==='' || $usCorreo==='' || $usUsername==='') {
  echo json_encode(['success'=>false,'error'=>'Faltan campos obligatorios']); exit;
}
if (!in_array($usRolNew, ['AC','UC','EC'], true)) {
  echo json_encode(['success'=>false,'error'=>'Rol inválido']); exit;
}
if ($csId <= 0) {
  echo json_encode(['success'=>false,'error'=>'Sede requerida']); exit;
}

$conectar->begin_transaction();

try {
  // 1) Resolver clId a partir de la sede si no vino
  if ($clId <= 0) {
    $st = $conectar->prepare("SELECT clId FROM cliente_sede WHERE csId=? LIMIT 1");
    $st->bind_param('i', $csId);
    $st->execute(); 
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row || !$row['clId']) throw new Exception('Sede inválida (no ligada a cliente)');
    $clId = (int)$row['clId'];
  }

  // 2) Validar que el cliente existe (evita el FK fail si no existiera)
  $st = $conectar->prepare("SELECT 1 FROM clientes WHERE clId=?");
  $st->bind_param('i', $clId);
  $st->execute();
  if (!$st->get_result()->fetch_row()) { 
    $st->close();
    throw new Exception("Cliente clId={$clId} no existe");
  }
  $st->close();

  // 3) Generar usId único (6 dígitos) en columna usId (UNIQUE)
  function generarNumero6() {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  }
  function getNumeroUnico(mysqli $db) {
    for ($i=0; $i<20; $i++) {
      $num = generarNumero6();
      $st = $db->prepare("SELECT 1 FROM usuarios WHERE usId=? LIMIT 1");
      $st->bind_param('s', $num);
      $st->execute();
      $existe = $st->get_result()->fetch_row();
      $st->close();
      if (!$existe) return $num;
    }
    throw new Exception('No se pudo generar número único');
  }
  $usId = getNumeroUnico($conectar);

  // 4) Generar contraseña temporal + hash
  function generarTempPass() { return bin2hex(random_bytes(5)); } // 10 chars hex
  $tempPass = generarTempPass();
  $pwdHash  = password_hash($tempPass, PASSWORD_BCRYPT);

  // 5) Insertar usuario (IMPORTANTE: incluir clId y NO forzar usId)
  $sqlU = "INSERT INTO usuarios
           (clId, usId, usNombre, usAPaterno, usAMaterno, usCorreo, usTelefono, usUsername, usRol, usPass, usEstatus)
           VALUES (?,?,?,?,?,?,?,?,?,?, 'NewPass')";
  $stU = $conectar->prepare($sqlU);
  $stU->bind_param(
    'isssssssss',
    $clId, $usId, $usNombre, $usAPaterno, $usAMaterno, $usCorreo, $usTelefono, $usUsername, $usRolNew, $pwdHash
  );
  if (!$stU->execute()) throw new Exception('Error al crear usuario: '.$stU->error);
  $usIdInsert = (int)$conectar->insert_id; // AUTO_INCREMENT del PK
  $stU->close();

  // 6) Ligar a sede
  $stS = $conectar->prepare("INSERT INTO sede_usuario (usId, csId) VALUES (?,?)");
  $stS->bind_param('ii', $usIdInsert, $csId);
  if (!$stS->execute()) throw new Exception('Error al ligar sede: '.$stS->error);
  $stS->close();

  // 7) (Opcional) enviar correo
  $okMail = enviarCorreoBienvenida($usCorreo, $usNombre, $usUsername, $tempPass, $usId);

  $conectar->commit();
  echo json_encode([
    'success'=>true, 
    'usId'=>$usIdInsert, 
    'usId'=>$usId, 
    'tempPassEnviada'=>$okMail
  ]);
} catch (Throwable $e) {
  $conectar->rollback();
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}

function enviarCorreoBienvenida($to, $nombre, $username, $tempPass, $usId) {
  // include_once 'mailer_config.php'; // tu bootstrap de PHPMailer
  try {
    // $mail = new PHPMailer(true);
    // ... tu configuración SMTP ...
    // $mail->addAddress($to, $nombre);
    // $mail->Subject = 'Tu acceso a la plataforma';
    // $mail->isHTML(true);
    // $mail->Body = "
    //   Hola {$nombre},<br>
    //   Tu usuario ha sido creado.<br><br>
    //   Usuario: <b>{$username}</b><br>
    //   Número de usuario: <b>{$usId}</b><br>
    //   Contraseña temporal: <b>{$tempPass}</b><br><br>
    //   Al ingresar, se te pedirá cambiar la contraseña.
    // ";
    // $mail->send();
    return true; // simular OK si aún no tienes SMTP
  } catch (\Throwable $e) {
    // error_log('Mailer error: '.$e->getMessage());
    return false;
  }
}
