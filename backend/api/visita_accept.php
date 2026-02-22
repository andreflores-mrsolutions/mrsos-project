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
$tiId = (int)($in['tiId'] ?? 0);
$opcion = (int)($in['opcion'] ?? 0);
if ($tiId <= 0 || $opcion < 1 || $opcion > 3) json_fail('Datos inválidos');

$pdo = db();
$usId = (int)($_SESSION['usId'] ?? 0);

// obtiene la opción seleccionada (la más reciente)
$st = $pdo->prepare("
  SELECT vpInicio, vpFin
  FROM ticket_visita_propuestas
  WHERE tiId=? AND vpOpcion=?
  ORDER BY vpCreadoEn DESC
  LIMIT 1
");
$st->execute([$tiId, $opcion]);
$row = $st->fetch();
if (!$row) json_fail('No existe esa propuesta', 404);

$pdo->beginTransaction();

// rechaza todas las pendientes
$pdo->prepare("
  UPDATE ticket_visita_propuestas
  SET vpEstado = CASE
    WHEN vpOpcion=? THEN 'aceptada'
    ELSE 'rechazada'
  END
  WHERE tiId=? AND vpEstado='pendiente'
")->execute([$opcion, $tiId]);

// estado visita + lock
$pdo->prepare("
  INSERT INTO ticket_visita_estado (tiId, confirmada_inicio, confirmada_fin, lock_cancel, estado)
  VALUES (?, ?, ?, 1, 'confirmada')
  ON DUPLICATE KEY UPDATE
    confirmada_inicio=VALUES(confirmada_inicio),
    confirmada_fin=VALUES(confirmada_fin),
    lock_cancel=1,
    estado='confirmada'
")->execute([$tiId, $row['vpInicio'], $row['vpFin']]);

// historial
$desc = "[VISITA] tiId={$tiId} · Ventana confirmada (Opción {$opcion}) · lock_cancel=1";
$pdo->prepare("INSERT INTO historial (hDescripcion, usId, hFecha_hora, hTabla) VALUES (?, ?, ?, 'ticket_soporte')")
    ->execute([$desc, $usId, date('Y-m-d H:i:s')]);

$pdo->commit();

json_ok(['opcion'=>$opcion]);