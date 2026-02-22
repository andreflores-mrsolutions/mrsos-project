<?php

declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA', 'MRA']);
csrf_verify_or_fail();

$in = read_json_body();

$tiId = (int)($in['tiId'] ?? 0);
if ($tiId <= 0) json_fail('tiId inválido');

$ingenieros = $in['ingenieros'] ?? [];
$docs       = $in['docs'] ?? [];
$vehiculos  = $in['vehiculos'] ?? [];
$equipos    = $in['equipos'] ?? [];
$herrs      = $in['herramientas'] ?? [];
$piezas     = $in['piezas'] ?? [];
$notasAcc   = trim((string)($in['notas_acceso'] ?? ''));

if (!is_array($ingenieros) || count($ingenieros) < 1) {
  json_fail('Debes seleccionar al menos 1 ingeniero.');
}

$hasCred = false;
if (is_array($docs)) {
  foreach ($docs as $d) {
    if (($d['tipo'] ?? '') === 'credencial_trabajo') {
      $hasCred = true;
      break;
    }
  }
}
if (!$hasCred) json_fail('Debes incluir al menos una credencial de trabajo (PDF).');

$pdo = db();
$usIdAdmin = (int)($_SESSION['usId'] ?? 0);

// Valida ticket existe
$st = $pdo->prepare("SELECT tiId FROM ticket_soporte WHERE tiId=? LIMIT 1");
$st->execute([$tiId]);
if (!$st->fetchColumn()) json_fail('Ticket no existe', 404);

$pdo->beginTransaction();

try {
  /* ============ BORRAR SNAPSHOT PREVIO ============ */
  $pdo->prepare("DELETE FROM ticket_visita_ingenieros WHERE tiId=?")->execute([$tiId]);
  $pdo->prepare("DELETE FROM ticket_visita_docs WHERE tiId=?")->execute([$tiId]);
  $pdo->prepare("DELETE FROM ticket_visita_vehiculos WHERE tiId=?")->execute([$tiId]);
  $pdo->prepare("DELETE FROM ticket_visita_equipos_sel WHERE tiId=?")->execute([$tiId]);
  $pdo->prepare("DELETE FROM ticket_visita_herramientas_sel WHERE tiId=?")->execute([$tiId]);
  $pdo->prepare("DELETE FROM ticket_visita_piezas WHERE tiId=?")->execute([$tiId]);

  /* ============ INGENIEROS (multi) ============ */
  $insIng = $pdo->prepare("
    INSERT INTO ticket_visita_ingenieros (tiId, usIdIng, rol)
    VALUES (?, ?, ?)
  ");
  $seen = [];
  foreach ($ingenieros as $x) {
    $usIdIng = (int)($x['usIdIng'] ?? 0);
    $rol = ($x['rol'] ?? 'principal');
    if ($usIdIng <= 0) continue;
    if (isset($seen[$usIdIng])) continue;
    $seen[$usIdIng] = true;
    $rol = ($rol === 'apoyo') ? 'apoyo' : 'principal';
    $insIng->execute([$tiId, $usIdIng, $rol]);
  }
  if (count($seen) < 1) json_fail('Ingenieros inválidos');

  /* ============ DOCS ============ */
  if (is_array($docs) && count($docs)) {
    $insDoc = $pdo->prepare("
      INSERT INTO ticket_visita_docs (tiId, usIdIng, idocId, tipo, label, archivo_snapshot)
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    foreach ($docs as $d) {
      $usIdIng = (int)($d['usIdIng'] ?? 0);
      if ($usIdIng <= 0) continue;
      $idocId = isset($d['idocId']) ? (int)$d['idocId'] : null;
      $tipo = (string)($d['tipo'] ?? '');
      if (!in_array($tipo, ['credencial_trabajo', 'INE', 'NSS', 'OTRO'], true)) continue;
      $label = trim((string)($d['label'] ?? ''));
      $archivoSnap = isset($d['archivo_snapshot']) ? basename((string)$d['archivo_snapshot']) : null;
      $insDoc->execute([$tiId, $usIdIng, $idocId ?: null, $tipo, $label ?: null, $archivoSnap ?: null]);
    }
  }

  /* ============ VEHICULOS (opcional) ============ */
  if (is_array($vehiculos) && count($vehiculos)) {

    $insVeh = $pdo->prepare("
      INSERT INTO ticket_visita_vehiculos (tiId, usIdIng, viId, placas, marca, modelo, color)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($vehiculos as $v) {
      $st = $pdo->prepare("
      SELECT *
      FROM vehiculos_ingenieros
      WHERE viId=? AND usIdIng=?
    ");
      $st->execute([$v['viId'], $v['usIdIng']]);
      $vI = $st->fetchAll();
      $usIdIng = (int)($v['usIdIng'] ?? 0);
      if ($usIdIng <= 0) continue;
      $viId = isset($v['viId']) ? (int)$v['viId'] : null;

      // si viene viId, puedes dejar lo demás null y que el GET lo consolide desde catálogo,
      // o snapshotear aquí. Yo snapshot -> si vienen campos:
      $placas = isset($vI[0]['placas']) ? trim((string)$vI[0]['placas']) : null;
      $marca  = isset($vI[0]['marca'])  ? trim((string)$vI[0]['marca'])  : null;
      $modelo = isset($vI[0]['modelo']) ? trim((string)$vI[0]['modelo']) : null;
      $color  = isset($vI[0]['color'])  ? trim((string)$vI[0]['color'])  : null;

      $insVeh->execute([$tiId, $usIdIng, $viId ?: null, $placas ?: null, $marca ?: null, $modelo ?: null, $color ?: null]);
    }
  }

  /* ============ EQUIPOS SELECCIONADOS (laptop/celular) ============ */
  if (is_array($equipos) && count($equipos)) {
    $insEq = $pdo->prepare("
      INSERT INTO ticket_visita_equipos_sel (tiId, usIdIng, ieId, cantidad)
      VALUES (?, ?, ?, ?)
    ");
    foreach ($equipos as $e) {
      $usIdIng = (int)($e['usIdIng'] ?? 0);
      $ieId = (int)($e['ieId'] ?? 0);
      $cant = (int)($e['cantidad'] ?? 1);
      if ($usIdIng <= 0 || $ieId <= 0) continue;
      if ($cant < 1) $cant = 1;
      if ($cant > 20) $cant = 20;
      $insEq->execute([$tiId, $usIdIng, $ieId, $cant]);
    }
  }

  /* ============ HERRAMIENTAS (opcional) ============ */
  if (is_array($herrs) && count($herrs)) {
    $insH = $pdo->prepare("
      INSERT INTO ticket_visita_herramientas_sel (tiId, usIdIng, ihtId, nombre)
      VALUES (?, ?, ?, ?)
    ");
    foreach ($herrs as $h) {
      $usIdIng = (int)($h['usIdIng'] ?? 0);
      if ($usIdIng <= 0) continue;
      $ihtId = isset($h['ihtId']) ? (int)$h['ihtId'] : null;
      $nombre = trim((string)($h['nombre'] ?? ''));
      $insH->execute([$tiId, $usIdIng, $ihtId ?: null, $nombre ?: null]);
    }
  }

  /* ============ PIEZAS (inventario o nota) ============ */
  if (is_array($piezas) && count($piezas)) {
    $insP = $pdo->prepare("
      INSERT INTO ticket_visita_piezas (tiId, tipo_pieza, partNumber, serialNumber, invId, notas)
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    foreach ($piezas as $p) {
      $tipo = trim((string)($p['tipo_pieza'] ?? ''));
      $pn   = trim((string)($p['partNumber'] ?? ''));
      $sn   = trim((string)($p['serialNumber'] ?? ''));
      $inv  = isset($p['invId']) ? (int)$p['invId'] : null;
      $nt   = trim((string)($p['notas'] ?? ''));
      if ($tipo === '' && $pn === '' && $sn === '' && $nt === '') continue;
      $insP->execute([$tiId, $tipo ?: null, $pn ?: null, $sn ?: null, $inv ?: null, $nt ?: null]);
    }
  }

  /* ============ MARCAR ESTADO READY / NOTAS ============ */
  // si ya agregaste campos a ticket_acceso_ingeniero o a otra tabla, aquí setéalos.
  // Opción minimal: guardar notas en ticket_visita_estado (si tienes campo) o crear tabla ticket_visita_notas.
  // Si NO tienes tabla, no falla.
  // Ejemplo si agregaste notas_acceso en ticket_visita_estado:
  // $pdo->prepare("UPDATE ticket_visita_estado SET notas_acceso=? WHERE tiId=?")->execute([$notasAcc ?: null, $tiId]);

  /* ============ HISTORIAL ============ */
  $desc = "[VISITA-PAQUETE] tiId={$tiId} · Paquete de acceso guardado. Ingenieros=" . count($seen) . " · Credencial=OK";
  $pdo->prepare("INSERT INTO historial (hDescripcion, usId, hFecha_hora, hTabla) VALUES (?, ?, ?, 'ticket_soporte')")
    ->execute([$desc, $usIdAdmin, date('Y-m-d H:i:s')]);

  $pdo->commit();

  json_ok([
    'tiId' => $tiId,
    'ingenieros_count' => count($seen),
    'has_credencial' => 1,
    'notas_acceso' => $notasAcc
  ]);
} catch (Throwable $e) {
  $pdo->rollBack();
  json_fail('Error al guardar paquete: ' . $e->getMessage(), 500);
}
