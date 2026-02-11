<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/conexion.php'; // db(): PDO

// ---------- Helpers JSON ----------
function jfail(string $msg, int $code = 400): void {
  http_response_code($code);
  echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}
function jok(array $data): void {
  echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
  exit;
}

// ===============================
// Helper: prefijo 3 letras
// ===============================
function clPrefix(string $name): string {
  $name = strtoupper((string)$name);
  $name = preg_replace('/[^A-Z]/', '', $name);
  $p = substr($name, 0, 3);
  return $p !== '' ? $p : 'CLI';
}

try {
  // --- Auth ---
  $usId  = $_SESSION['usId']  ?? null;
  $usRol = $_SESSION['usRol'] ?? null;
  $clIdSes = $_SESSION['clId'] ?? null;

  if (!$usId || !$usRol) jfail('No autenticado', 401);

  $usId = (int)$usId;
  $usRol = (string)$usRol;

  // Roles MR internos
  $isMR = in_array($usRol, ['MRA', 'MRV', 'MRSA'], true);

  // Cliente objetivo:
  // - MR puede pedir ?clId=
  // - Cliente toma clId de sesión
  if ($isMR) {
    $clId = isset($_GET['clId']) ? (int)$_GET['clId'] : (int)($clIdSes ?? 0);
  } else {
    $clId = (int)($clIdSes ?? 0);
  }
  if (!$clId) jfail('Cliente no definido', 400);

  // Sede opcional (si viene, filtramos)
  $csIdParam = isset($_GET['csId']) ? (int)$_GET['csId'] : null;

  $pdo = db();

  
  // MRA: global
  // MRSA/MRV: solo clientes ligados en cuentas
  if ($isMR && $usRol !== 'MRSA') {
    $stPerm = $pdo->prepare("SELECT 1 FROM cuentas WHERE usId = ? AND clId = ? LIMIT 1");
    $stPerm->execute([$usId, $clId]);
    if (!$stPerm->fetchColumn()) jfail('Sin permisos para este cliente', 403);
  }

  // ==========================================
  // 0.1) Obtener nombre de cliente (prefijo)
  // ==========================================
  $stCl = $pdo->prepare("SELECT clNombre FROM clientes WHERE clId = ? LIMIT 1");
  $stCl->execute([$clId]);
  $clRow = $stCl->fetch(PDO::FETCH_ASSOC);
  $clienteNombre = (string)($clRow['clNombre'] ?? '');
  $prefix = clPrefix($clienteNombre);

  // ==========================================
  // 1) Resolver sedes permitidas
  // ==========================================
  // - MR: sin restricción, salvo que venga csIdParam
  // - Cliente: sedes permitidas desde usuario_cliente_rol
  $allowedCsIds = null; // null => sin restricción

  if ($isMR) {
    if ($csIdParam) $allowedCsIds = [$csIdParam];
  } else {
    // cliente / usuario final
    $stRoles = $pdo->prepare("
      SELECT clId, czId, csId, ucrRol
      FROM usuario_cliente_rol
      WHERE usId = ?
        AND ucrEstatus = 'Activo'
    ");
    $stRoles->execute([$usId]);
    $roles = $stRoles->fetchAll(PDO::FETCH_ASSOC);

    if (!$roles) {
      jok([
        'poliza' => 'Sin póliza',
        'ticketsAbiertos' => 0,
        'equipos' => 0,
        'clientePrefix' => $prefix,
        'tickets' => [],
        'healthChecksCount' => 0,
        'healthChecks' => [],
      ]);
    }

    $adminGlobalForClient = false;
    $zonaIds = [];
    $directCsIds = [];

    foreach ($roles as $r) {
      $clIdR = (int)($r['clId'] ?? 0);
      if ($clIdR !== $clId) continue;

      $ucrRol = (string)($r['ucrRol'] ?? '');
      $czIdR  = isset($r['czId']) ? (int)$r['czId'] : 0;
      $csIdR  = isset($r['csId']) ? (int)$r['csId'] : 0;

      if ($ucrRol === 'ADMIN_GLOBAL') {
        $adminGlobalForClient = true;
      } elseif ($ucrRol === 'ADMIN_ZONA' && $czIdR > 0) {
        $zonaIds[] = $czIdR;
      } elseif (in_array($ucrRol, ['ADMIN_SEDE', 'USUARIO', 'VISOR'], true) && $csIdR > 0) {
        $directCsIds[] = $csIdR;
      }
    }

    $csIds = [];

    // ADMIN_GLOBAL -> todas las sedes del cliente
    if ($adminGlobalForClient) {
      $st = $pdo->prepare("SELECT csId FROM cliente_sede WHERE clId = ? AND csEstatus='Activo'");
      $st->execute([$clId]);
      foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) $csIds[] = (int)$row['csId'];
    }

    // ADMIN_ZONA -> sedes de esas zonas
    $zonaIds = array_values(array_unique(array_filter($zonaIds)));
    if ($zonaIds) {
      $ph = implode(',', array_fill(0, count($zonaIds), '?'));
      $st = $pdo->prepare("SELECT csId FROM cliente_sede WHERE czId IN ($ph) AND csEstatus='Activo'");
      $st->execute($zonaIds);
      foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) $csIds[] = (int)$row['csId'];
    }

    // Sedes directas
    foreach ($directCsIds as $id) $csIds[] = (int)$id;

    $csIds = array_values(array_unique(array_filter($csIds)));

    // Si además viene ?csId=, se reduce a esa sede (si está permitida)
    if ($csIdParam) {
      if (!in_array($csIdParam, $csIds, true)) jfail('Sede no permitida', 403);
      $csIds = [$csIdParam];
    }

    $allowedCsIds = $csIds;

    // Si no hay sedes permitidas => no hay data
    if (!$allowedCsIds) {
      jok([
        'poliza' => 'Sin póliza',
        'ticketsAbiertos' => 0,
        'equipos' => 0,
        'clientePrefix' => $prefix,
        'tickets' => [],
        'healthChecksCount' => 0,
        'healthChecks' => [],
      ]);
    }
  }

  // ==========================
  // 2) Tipo de póliza vigente
  // ==========================
  $stPol = $pdo->prepare("
    SELECT pcTipoPoliza
    FROM polizascliente
    WHERE clId = ?
      AND pcEstatus = 'Activo'
    ORDER BY pcFechaFin DESC
    LIMIT 1
  ");
  $stPol->execute([$clId]);
  $rowPol = $stPol->fetch(PDO::FETCH_ASSOC);
  $tipoPoliza = $rowPol ? (string)($rowPol['pcTipoPoliza'] ?? 'Sin póliza') : 'Sin póliza';

  // Helper para IN csId
  $csFilterSql = '';
  $csParams = [];
  if (is_array($allowedCsIds)) {
    $ph = implode(',', array_fill(0, count($allowedCsIds), '?'));
    $csFilterSql = " AND t.csId IN ($ph) ";
    $csParams = $allowedCsIds;
  } elseif ($csIdParam) {
    $csFilterSql = " AND t.csId = ? ";
    $csParams = [$csIdParam];
  }

  // ==========================
  // 3) Tickets abiertos
  // ==========================
  $sqlOpen = "
    SELECT COUNT(*) AS total
    FROM ticket_soporte t
    WHERE t.clId = ?
      AND t.tiEstatus != 'Cerrado'
      AND t.estatus = 'Activo'
    $csFilterSql
  ";
  $stOpen = $pdo->prepare($sqlOpen);
  $stOpen->execute(array_merge([$clId], $csParams));
  $ticketsAbiertos = (int)($stOpen->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  // ==========================
  // 4) Equipos en póliza
  // ==========================
  $stEq = $pdo->prepare("
    SELECT COUNT(DISTINCT pe.eqId) AS total
    FROM polizasequipo pe
    INNER JOIN polizascliente pc ON pc.pcId = pe.pcId
    WHERE pc.clId = ?
      AND pc.pcEstatus = 'Activo'
      AND pe.peEstatus = 'Activo'
  ");
  $stEq->execute([$clId]);
  $equipos = (int)($stEq->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

  // ==========================
  // 5) Lista tickets abiertos + folio prefijado
  // ==========================
  $sqlTickets = "
    SELECT
      t.tiId,
      t.tiDescripcion,
      t.tiFechaCreacion,
      t.tiNivelCriticidad,
      t.tiEstatus,
      t.tiProceso,
      t.tiTipoTicket,
      cs.csId,
      cs.csNombre,
      pe.peSN
    FROM ticket_soporte t
    JOIN polizasequipo pe ON pe.peId = t.peId
    LEFT JOIN cliente_sede cs ON cs.csId = t.csId
    WHERE t.clId = ?
      AND t.tiEstatus != 'Cerrado'
      AND t.estatus = 'Activo'
    $csFilterSql
    ORDER BY t.tiFechaCreacion DESC, t.tiId DESC
  ";
  $stT = $pdo->prepare($sqlTickets);
  $stT->execute(array_merge([$clId], $csParams));
  $tickets = $stT->fetchAll(PDO::FETCH_ASSOC);

  foreach ($tickets as &$t) {
    $t['folio'] = $prefix . '-' . (int)$t['tiId'];
  }
  unset($t);

  // ==========================
  // 6) Health Checks programados (próximos)
  // ==========================
  // Si tienes allowedCsIds, filtramos por sedes permitidas
  $hcWhere = " WHERE hc.clId = ? AND hc.hcEstatus = 'Programado' ";
  $hcParams = [$clId];

  if (is_array($allowedCsIds)) {
    $ph = implode(',', array_fill(0, count($allowedCsIds), '?'));
    $hcWhere .= " AND hc.csId IN ($ph) ";
    $hcParams = array_merge($hcParams, $allowedCsIds);
  } elseif ($csIdParam) {
    $hcWhere .= " AND hc.csId = ? ";
    $hcParams[] = $csIdParam;
  }

  $stHC = $pdo->prepare("
    SELECT 
        hc.hcId,
        hc.hcFechaHora,
        hc.hcDuracionMins,
        hc.hcEstatus,
        cs.csNombre,
        (
          SELECT COUNT(*) 
          FROM health_check_items hci 
          WHERE hci.hcId = hc.hcId
        ) AS equiposCount
    FROM health_check hc
    INNER JOIN cliente_sede cs ON cs.csId = hc.csId
    $hcWhere
    ORDER BY hc.hcFechaHora ASC
    LIMIT 10
  ");
  $stHC->execute($hcParams);
  $healthChecks = $stHC->fetchAll(PDO::FETCH_ASSOC);
  $healthChecksCount = count($healthChecks);

  // ==========================
  // Respuesta
  // ==========================
  jok([
    'poliza'          => $tipoPoliza,
    'ticketsAbiertos' => $ticketsAbiertos,
    'equipos'         => $equipos,
    'clientePrefix'   => $prefix,
    'tickets'         => $tickets,
    'healthChecksCount' => $healthChecksCount,
    'healthChecks'      => $healthChecks,
  ]);

} catch (Throwable $e) {
  // En producción NO devuelvas detail (evita fuga)
  jfail('Error interno', 500);
}
