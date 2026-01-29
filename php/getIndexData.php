<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require 'conexion.php'; // $conectar (mysqli)

$usId  = $_SESSION['usId']  ?? null;
$usRol = $_SESSION['usRol'] ?? null;
$clIdSes = $_SESSION['clId'] ?? null;

// Validación mínima de auth
if (!$usId || !$usRol) {
  echo json_encode(['success' => false, 'error' => 'No autenticado']);
  exit;
}

$usId = (int)$usId;

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

if (!$clId) {
  echo json_encode(['success' => false, 'error' => 'Cliente no definido']);
  exit;
}

// Sede opcional (si viene, filtramos)
$csIdParam = isset($_GET['csId']) ? (int)$_GET['csId'] : null;

// ===============================
// Helper: prefijo 3 letras
// ===============================
function clPrefix(string $name): string {
  // quitar acentos/raros de manera simple + solo letras
  $name = strtoupper($name);
  $name = preg_replace('/[^A-Z]/', '', $name ?? '');
  $p = substr($name, 0, 3);
  return $p !== '' ? $p : 'CLI';
}

try {
  // ==========================================
  // 0) Obtener nombre de cliente (para prefijo)
  // ==========================================
  $stmt = $conectar->prepare("SELECT clNombre FROM clientes WHERE clId = ? LIMIT 1");
  $stmt->bind_param("i", $clId);
  $stmt->execute();
  $clRow = $stmt->get_result()->fetch_assoc();
  $clienteNombre = $clRow['clNombre'] ?? '';
  $prefix = clPrefix($clienteNombre);

  // ==========================================
  // 1) Resolver sedes permitidas (si NO es MR)
  // ==========================================
  // - MR: sin restricción, salvo que venga csIdParam
  // - Cliente: se arma allowedCsIds desde usuario_cliente_rol
  $allowedCsIds = null; // null => sin restricción

  if ($isMR) {
    if ($csIdParam) $allowedCsIds = [$csIdParam];
  } else {
    $allowedCsIds = [];

    // Leer roles del usuario
    $stmt = $conectar->prepare("
      SELECT clId, czId, csId, ucrRol
      FROM usuario_cliente_rol
      WHERE usId = ?
        AND ucrEstatus = 'Activo'
    ");
    $stmt->bind_param("i", $usId);
    $stmt->execute();
    $rolesRes = $stmt->get_result();

    if ($rolesRes->num_rows === 0) {
      // sin alcance
      echo json_encode([
        'success' => true,
        'poliza' => 'Sin póliza',
        'ticketsAbiertos' => 0,
        'equipos' => 0,
        'tickets' => [],
      ]);
      exit;
    }

    $adminGlobalClIds = [];
    $adminZonaCzIds   = [];
    $directCsIds      = [];

    while ($r = $rolesRes->fetch_assoc()) {
      $ucrRol = $r['ucrRol'];
      $clIdR  = (int)$r['clId'];
      $czIdR  = $r['czId'] !== null ? (int)$r['czId'] : null;
      $csIdR  = $r['csId'] !== null ? (int)$r['csId'] : null;

      // Solo roles de ESTE cliente
      if ($clIdR !== $clId) continue;

      if ($ucrRol === 'ADMIN_GLOBAL') {
        $adminGlobalClIds[] = $clIdR;
      } elseif ($ucrRol === 'ADMIN_ZONA' && $czIdR) {
        $adminZonaCzIds[] = $czIdR;
      } elseif (in_array($ucrRol, ['ADMIN_SEDE', 'USUARIO', 'VISOR'], true) && $csIdR) {
        $directCsIds[] = $csIdR;
      }
    }

    $csIds = [];

    // ADMIN_GLOBAL -> todas las sedes del cliente
    if (!empty($adminGlobalClIds)) {
      $adminGlobalClIds = array_values(array_unique($adminGlobalClIds));
      $placeholders = implode(',', array_fill(0, count($adminGlobalClIds), '?'));
      $sql = "SELECT csId FROM cliente_sede WHERE clId IN ($placeholders)";
      $stmt = $conectar->prepare($sql);
      $types = str_repeat('i', count($adminGlobalClIds));
      $stmt->bind_param($types, ...$adminGlobalClIds);
      $stmt->execute();
      $tmp = $stmt->get_result();
      while ($row = $tmp->fetch_assoc()) $csIds[] = (int)$row['csId'];
    }

    // ADMIN_ZONA -> sedes de esas zonas
    if (!empty($adminZonaCzIds)) {
      $adminZonaCzIds = array_values(array_unique($adminZonaCzIds));
      $placeholders = implode(',', array_fill(0, count($adminZonaCzIds), '?'));
      $sql = "SELECT csId FROM cliente_sede WHERE czId IN ($placeholders)";
      $stmt = $conectar->prepare($sql);
      $types = str_repeat('i', count($adminZonaCzIds));
      $stmt->bind_param($types, ...$adminZonaCzIds);
      $stmt->execute();
      $tmp = $stmt->get_result();
      while ($row = $tmp->fetch_assoc()) $csIds[] = (int)$row['csId'];
    }

    // Sedes directas
    if (!empty($directCsIds)) {
      foreach ($directCsIds as $id) $csIds[] = (int)$id;
    }

    $csIds = array_values(array_unique($csIds));

    // Si además viene ?csId=, se reduce a esa sede (si está permitida)
    if ($csIdParam) {
      if (!in_array($csIdParam, $csIds, true)) {
        echo json_encode(['success' => false, 'error' => 'Sede no permitida']);
        exit;
      }
      $csIds = [$csIdParam];
    }

    $allowedCsIds = $csIds;

    // Si no hay sedes permitidas => no hay data
    if (empty($allowedCsIds)) {
      echo json_encode([
        'success' => true,
        'poliza' => 'Sin póliza',
        'ticketsAbiertos' => 0,
        'equipos' => 0,
        'tickets' => [],
      ]);
      exit;
    }
  }

  // ==========================
  // 1) Tipo de póliza vigente
  // ==========================
  $stmt = $conectar->prepare("
    SELECT pcTipoPoliza
    FROM polizascliente
    WHERE clId = ?
      AND pcEstatus = 'Activo'
    ORDER BY pcFechaFin DESC
    LIMIT 1
  ");
  $stmt->bind_param("i", $clId);
  $stmt->execute();
  $result = $stmt->get_result();
  $tipoPoliza = $result->num_rows > 0 ? ($result->fetch_assoc()['pcTipoPoliza'] ?? 'Sin póliza') : 'Sin póliza';

  // ==========================
  // 2) Tickets abiertos (con filtro de sede si aplica)
  // ==========================
  $sql = "
    SELECT COUNT(*) AS total
    FROM ticket_soporte t
    WHERE t.clId = ?
      AND t.tiEstatus != 'Cerrado'
      AND t.estatus = 'Activo'
  ";
  $types = "i";
  $params = [$clId];

  if (is_array($allowedCsIds)) {
    $placeholders = implode(',', array_fill(0, count($allowedCsIds), '?'));
    $sql .= " AND t.csId IN ($placeholders)";
    $types .= str_repeat('i', count($allowedCsIds));
    $params = array_merge($params, $allowedCsIds);
  } elseif ($csIdParam) {
    // por si acaso (MR con csId)
    $sql .= " AND t.csId = ?";
    $types .= "i";
    $params[] = $csIdParam;
  }

  $stmt = $conectar->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $ticketsAbiertos = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);

  // ==========================
  // 3) Equipos en póliza
  // ==========================
  $stmt = $conectar->prepare("
    SELECT COUNT(DISTINCT pe.eqId) AS total
    FROM polizasequipo pe
    INNER JOIN polizascliente pc ON pc.pcId = pe.pcId
    WHERE pc.clId = ?
      AND pc.pcEstatus = 'Activo'
      AND pe.peEstatus = 'Activo'
  ");
  $stmt->bind_param("i", $clId);
  $stmt->execute();
  $equipos = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);

  // ==========================
  // 4) Lista de tickets abiertos (con sede si aplica) + folio ENE-17
  // ==========================
  $sql = "
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
  ";
  $types = "i";
  $params = [$clId];

  if (is_array($allowedCsIds)) {
    $placeholders = implode(',', array_fill(0, count($allowedCsIds), '?'));
    $sql .= " AND t.csId IN ($placeholders)";
    $types .= str_repeat('i', count($allowedCsIds));
    $params = array_merge($params, $allowedCsIds);
  } elseif ($csIdParam) {
    $sql .= " AND t.csId = ?";
    $types .= "i";
    $params[] = $csIdParam;
  }

  $sql .= " ORDER BY t.tiFechaCreacion DESC, t.tiId DESC";

  $stmt = $conectar->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

  // Agregar folio prefijado
  foreach ($tickets as &$t) {
    $t['folio'] = $prefix . '-' . $t['tiId']; // ENE-17
  }
  unset($t);

  // ==========================
// 5) Health Checks programados (próximos)
// ==========================
$stmt = $conectar->prepare("
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
    WHERE hc.clId = ?
      AND hc.hcEstatus = 'Programado'
    ORDER BY hc.hcFechaHora ASC
    LIMIT 10
");
$stmt->bind_param("i", $clId);
$stmt->execute();
$healthChecks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$healthChecksCount = count($healthChecks);


  // ==========================
  // Respuesta
  // ==========================
  echo json_encode([
    'success'         => true,
    'poliza'          => $tipoPoliza,
    'ticketsAbiertos' => $ticketsAbiertos,
    'equipos'         => $equipos,
    'clientePrefix'   => $prefix,
    'tickets'         => $tickets,

    'healthChecksCount' => $healthChecksCount,
    'healthChecks'      => $healthChecks
  ]);

} catch (Throwable $e) {
  echo json_encode([
    'success' => false,
    'error'   => 'Error interno',
    'detail'  => $e->getMessage()
  ]);
}
