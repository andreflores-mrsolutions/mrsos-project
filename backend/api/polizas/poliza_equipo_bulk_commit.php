<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../../php/cliente_guard.php';
require_once __DIR__ . '/../../../php/historial.php';

no_store();
require_login();
require_usRol(['MRSA', 'MRA', 'MRV']);
csrf_verify_or_fail();

$pdo = db();
$in = read_json_body();

$pcId = (int)($in['pcId'] ?? 0);
$mode = trim((string)($in['mode'] ?? 'insert_only'));
$rows = $in['rows'] ?? [];
$updateFields = $in['updateFields'] ?? [];

if ($pcId <= 0) json_fail('pcId requerido');
if (!is_array($rows) || count($rows) === 0) json_fail('rows requerido');

$validModes = ['insert_only', 'update_only', 'upsert', 'assign_only'];
if (!in_array($mode, $validModes, true)) {
  json_fail('mode inválido');
}

if (!is_array($updateFields)) $updateFields = [];

$usId = (int)($_SESSION['usId'] ?? 0);
$usRol = current_usRol();

/** póliza y permisos */
$st = $pdo->prepare("
  SELECT pcId, clId, pcIdentificador, pcTipoPoliza
  FROM polizascliente
  WHERE pcId = ?
  LIMIT 1
");
$st->execute([$pcId]);
$pc = $st->fetch(PDO::FETCH_ASSOC);
if (!$pc) json_fail('Póliza no existe');

$clId = (int)$pc['clId'];

if (!mr_can_access_client($pdo, $usId, $usRol, $clId)) {
  json_fail('Sin acceso al cliente', 403);
}

/** regla SN */
$allowSNUpdate = ($usRol === 'MRSA') && !empty($updateFields['peSN']);

/** helpers SQL */
$chkSede = $pdo->prepare("
  SELECT 1
  FROM cliente_sede
  WHERE csId = ? AND clId = ?
  LIMIT 1
");

$chkEq = $pdo->prepare("
  SELECT 1
  FROM equipos
  WHERE eqId = ?
  LIMIT 1
");

$findBySN = $pdo->prepare("
  SELECT peId, pcId, eqId, csId, peSN, peSO, peDescripcion, peEstatus
  FROM polizasequipo
  WHERE pcId = ? AND peSN = ?
  LIMIT 1
");

$findByPeId = $pdo->prepare("
  SELECT peId, pcId, eqId, csId, peSN, peSO, peDescripcion, peEstatus
  FROM polizasequipo
  WHERE peId = ? AND pcId = ?
  LIMIT 1
");

$ins = $pdo->prepare("
  INSERT INTO polizasequipo
    (peDescripcion, peSN, pcId, csId, peSO, peEstatus, eqId)
  VALUES
    (?, ?, ?, ?, ?, ?, ?)
");

/**
 * Update dinámico por campos seleccionados.
 * Ojo: peSN solo si MRSA + updateFields['peSN']
 */
function build_update_sql(array $fields): array {
  $set = [];
  $params = [];

  foreach ($fields as $col => $val) {
    $set[] = "$col = ?";
    $params[] = $val;
  }

  return [$set, $params];
}

/** valores permitidos */
$allowedStatus = ['Activo', 'Inactivo', 'Baja', 'Cambios', 'Error'];

/** reporte */
$result = [
  'mode' => $mode,
  'total' => count($rows),
  'insertados' => 0,
  'actualizados' => 0,
  'duplicados' => 0,
  'errores' => 0,
  'rows' => []
];

/** para detectar duplicados dentro del mismo archivo */
$seenSNInBatch = [];

foreach ($rows as $idx => $rowWrap) {
  $line = (int)($rowWrap['line'] ?? ($idx + 2));
  $data = $rowWrap['data'] ?? [];

  $rowResult = [
    'line' => $line,
    'status' => 'ok',
    'message' => '',
    'peSN' => ''
  ];

  try {
    if (!is_array($data)) {
      throw new RuntimeException('Fila inválida');
    }

    $peId = isset($data['peId']) ? (int)$data['peId'] : 0;
    $eqId = (int)($data['eqId'] ?? 0);
    $peSN = trim((string)($data['peSN'] ?? ''));
    $peSO = trim((string)($data['peSO'] ?? ''));
    $peDescripcion = trim((string)($data['peDescripcion'] ?? ''));
    $peEstatus = trim((string)($data['peEstatus'] ?? 'Activo'));
    $csIdResolved = (int)($rowWrap['csIdResolved'] ?? 0);

    $rowResult['peSN'] = $peSN;

    if ($eqId <= 0) throw new RuntimeException('eqId requerido');
    if ($peSN === '') throw new RuntimeException('peSN requerido');
    if ($csIdResolved <= 0) throw new RuntimeException('csId requerido');

    if (!in_array($peEstatus, $allowedStatus, true)) {
      $peEstatus = 'Activo';
    }
    if ($peSO === '') $peSO = 'N/A';
    if ($peDescripcion === '') $peDescripcion = '—';

    // validar equipo catálogo
    $chkEq->execute([$eqId]);
    if (!$chkEq->fetchColumn()) {
      throw new RuntimeException('eqId no existe');
    }

    // validar sede
    $chkSede->execute([$csIdResolved, $clId]);
    if (!$chkSede->fetchColumn()) {
      throw new RuntimeException('La sede no pertenece al cliente');
    }

    // duplicado dentro del mismo archivo (por SN)
    $snKey = mb_strtolower($peSN, 'UTF-8');
    if (isset($seenSNInBatch[$snKey])) {
      // En insert_only y assign_only esto es duplicado directo.
      // En update_only/upsert igual lo marcamos para evitar ambigüedad.
      $result['duplicados']++;
      $rowResult['status'] = 'dup';
      $rowResult['message'] = 'SN duplicado dentro del archivo';
      $result['rows'][] = $rowResult;
      continue;
    }
    $seenSNInBatch[$snKey] = true;

    // buscar existente por SN en la póliza
    $findBySN->execute([$pcId, $peSN]);
    $existing = $findBySN->fetch(PDO::FETCH_ASSOC);

    // assign_only: en este contexto será igual a insert_only
    // porque polizasequipo ES la asignación a póliza.
    $effectiveMode = ($mode === 'assign_only') ? 'insert_only' : $mode;

    if ($effectiveMode === 'insert_only') {
      if ($existing) {
        $result['duplicados']++;
        $rowResult['status'] = 'dup';
        $rowResult['message'] = 'El SN ya existe en la póliza';
        $result['rows'][] = $rowResult;
        continue;
      }

      $ins->execute([
        $peDescripcion,
        $peSN,
        $pcId,
        $csIdResolved,
        $peSO,
        $peEstatus,
        $eqId
      ]);

      $newPeId = (int)$pdo->lastInsertId();

      Historial::log(
        $pdo,
        $usId,
        'polizasequipo',
        Historial::msg(
          'INSERT',
          'polizasequipo',
          ['pcId' => $pcId, 'peId' => $newPeId, 'eqId' => $eqId],
          "Alta masiva: SN '{$peSN}'"
        ),
        'Activo'
      );

      $result['insertados']++;
      $rowResult['status'] = 'inserted';
      $rowResult['message'] = 'Insertado';
      $result['rows'][] = $rowResult;
      continue;
    }

    if ($effectiveMode === 'update_only') {
      if (!$existing) {
        $result['errores']++;
        $rowResult['status'] = 'error';
        $rowResult['message'] = 'No existe en la póliza para actualizar';
        $result['rows'][] = $rowResult;
        continue;
      }

      $updateData = [];

      if (!empty($updateFields['csId'])) $updateData['csId'] = $csIdResolved;
      if (!empty($updateFields['peSO'])) $updateData['peSO'] = $peSO;
      if (!empty($updateFields['peDescripcion'])) $updateData['peDescripcion'] = $peDescripcion;
      if (!empty($updateFields['peEstatus'])) $updateData['peEstatus'] = $peEstatus;
      if (!empty($updateFields['eqId'])) $updateData['eqId'] = $eqId;

      // SN solo MRSA
      if ($allowSNUpdate && !empty($updateFields['peSN'])) {
        $updateData['peSN'] = $peSN;
      }

      if (!$updateData) {
        $result['errores']++;
        $rowResult['status'] = 'error';
        $rowResult['message'] = 'No se seleccionaron columnas para actualizar';
        $result['rows'][] = $rowResult;
        continue;
      }

      [$setParts, $params] = build_update_sql($updateData);
      $params[] = (int)$existing['peId'];

      $sql = "UPDATE polizasequipo SET " . implode(', ', $setParts) . " WHERE peId = ?";
      $stUp = $pdo->prepare($sql);
      $stUp->execute($params);

      Historial::log(
        $pdo,
        $usId,
        'polizasequipo',
        Historial::msg(
          'UPDATE',
          'polizasequipo',
          ['pcId' => $pcId, 'peId' => (int)$existing['peId']],
          "Actualización masiva de SN '{$peSN}'"
        ),
        'Activo'
      );

      $result['actualizados']++;
      $rowResult['status'] = 'updated';
      $rowResult['message'] = 'Actualizado';
      $result['rows'][] = $rowResult;
      continue;
    }

    if ($effectiveMode === 'upsert') {
      if ($existing) {
        $updateData = [];

        if (!empty($updateFields['csId'])) $updateData['csId'] = $csIdResolved;
        if (!empty($updateFields['peSO'])) $updateData['peSO'] = $peSO;
        if (!empty($updateFields['peDescripcion'])) $updateData['peDescripcion'] = $peDescripcion;
        if (!empty($updateFields['peEstatus'])) $updateData['peEstatus'] = $peEstatus;
        if (!empty($updateFields['eqId'])) $updateData['eqId'] = $eqId;
        if ($allowSNUpdate && !empty($updateFields['peSN'])) $updateData['peSN'] = $peSN;

        if ($updateData) {
          [$setParts, $params] = build_update_sql($updateData);
          $params[] = (int)$existing['peId'];

          $sql = "UPDATE polizasequipo SET " . implode(', ', $setParts) . " WHERE peId = ?";
          $stUp = $pdo->prepare($sql);
          $stUp->execute($params);

          Historial::log(
            $pdo,
            $usId,
            'polizasequipo',
            Historial::msg(
              'UPDATE',
              'polizasequipo',
              ['pcId' => $pcId, 'peId' => (int)$existing['peId']],
              "Upsert masivo: actualización de SN '{$peSN}'"
            ),
            'Activo'
          );
        }

        $result['actualizados']++;
        $rowResult['status'] = 'updated';
        $rowResult['message'] = 'Actualizado';
        $result['rows'][] = $rowResult;
        continue;
      }

      // no existe => insertar
      $ins->execute([
        $peDescripcion,
        $peSN,
        $pcId,
        $csIdResolved,
        $peSO,
        $peEstatus,
        $eqId
      ]);

      $newPeId = (int)$pdo->lastInsertId();

      Historial::log(
        $pdo,
        $usId,
        'polizasequipo',
        Historial::msg(
          'INSERT',
          'polizasequipo',
          ['pcId' => $pcId, 'peId' => $newPeId, 'eqId' => $eqId],
          "Upsert masivo: alta de SN '{$peSN}'"
        ),
        'Activo'
      );

      $result['insertados']++;
      $rowResult['status'] = 'inserted';
      $rowResult['message'] = 'Insertado';
      $result['rows'][] = $rowResult;
      continue;
    }

    throw new RuntimeException('Modo no soportado');

  } catch (Throwable $e) {
    $result['errores']++;
    $rowResult['status'] = 'error';
    $rowResult['message'] = $e->getMessage();
    $result['rows'][] = $rowResult;
  }
}

Historial::log(
  $pdo,
  $usId,
  'polizasequipo',
  Historial::msg(
    'BULK_COMMIT',
    'polizasequipo',
    ['pcId' => $pcId, 'mode' => $mode],
    "Resultado masivo: insertados={$result['insertados']}, actualizados={$result['actualizados']}, duplicados={$result['duplicados']}, errores={$result['errores']}"
  ),
  'Activo'
);

json_ok($result);