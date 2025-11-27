<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
  /* =========================
   * 1) CONEXIÓN (ajusta a tu entorno)
   * ========================= */
  $DB_HOST = '127.0.0.1';
  $DB_NAME = 'mrsos';
  $DB_USER = 'root';
  $DB_PASS = '';
  $DB_CHARSET = 'utf8mb4';

  $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  /* =========================
   * 2) FILTROS OPCIONALES (GET)
   * =========================
   * estado:      tiEstatus           (Abierto|Pospuesto|Cerrado)
   * proceso:     tiProceso
   * tipo:        tiTipoTicket        (Servicio|Preventivo|Extra)
   * cliente:     clId
   * sede:        csId
   * q:           búsqueda texto      (tiDescripcion, peSN, eqModelo, eqVersion)
   * date_from:   tiFechaCreacion >=  YYYY-MM-DD
   * date_to:     tiFechaCreacion <=  YYYY-MM-DD
   * limit/offset: paginación opcional
   */
  $estado   = $_GET['estado']   ?? null;
  $proceso  = $_GET['proceso']  ?? null;
  $tipo     = $_GET['tipo']     ?? null;
  $cliente  = isset($_GET['cliente']) ? (int)$_GET['cliente'] : null;
  $sede     = isset($_GET['sede'])    ? (int)$_GET['sede']    : null;
  $q        = $_GET['q']        ?? null;
  $dateFrom = $_GET['date_from'] ?? null;
  $dateTo   = $_GET['date_to']   ?? null;
  $limit    = isset($_GET['limit'])  ? max(1, (int)$_GET['limit'])  : 0;      // 0 = sin límite
  $offset   = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

  $where = [];
  $params = [];

  if ($estado)  { $where[] = 't.tiEstatus = :estado';         $params[':estado']  = $estado; }
  if ($proceso) { $where[] = 't.tiProceso = :proceso';         $params[':proceso'] = $proceso; }
  if ($tipo)    { $where[] = 't.tiTipoTicket = :tipo';         $params[':tipo']    = $tipo; }
  if ($cliente) { $where[] = 't.clId = :cliente';              $params[':cliente'] = $cliente; }
  if ($sede)    { $where[] = 't.csId = :sede';                 $params[':sede']    = $sede; }

  if ($dateFrom) { $where[] = 't.tiFechaCreacion >= :df';      $params[':df']      = $dateFrom; }
  if ($dateTo)   { $where[] = 't.tiFechaCreacion <= :dt';      $params[':dt']      = $dateTo; }

  if ($q) {
    // Búsqueda sencilla en descripción, SN y modelo/version
    $where[] = '(t.tiDescripcion LIKE :q OR pe.peSN LIKE :q OR e.eqModelo LIKE :q OR e.eqVersion LIKE :q)';
    $params[':q'] = '%'.$q.'%';
  }

  $whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

  /* =========================
   * 3) CONSULTA
   * ========================= */
  $sql = "
    SELECT
      c.clId, c.clNombre,
      cs.csId, cs.csNombre,
      t.tiId, t.tiEstatus, t.tiProceso, t.tiTipoTicket, t.tiExtra,
      t.tiVisita, t.tiFechaCreacion,
      t.tiNombreContacto, t.tiNumeroContacto, t.tiCorreoContacto,
      e.eqModelo, e.eqVersion,
      m.maNombre,
      pe.peSN
    FROM ticket_soporte t
      INNER JOIN clientes c     ON c.clId = t.clId
      LEFT  JOIN cliente_sede cs ON cs.csId = t.csId
      LEFT  JOIN polizasequipo pe ON pe.peId = t.peId
      LEFT  JOIN equipos e       ON e.eqId = t.eqId
      LEFT  JOIN marca m         ON m.maId = e.maId
    {$whereSql}
    ORDER BY c.clNombre ASC, cs.csNombre ASC, t.tiFechaCreacion DESC, t.tiId DESC
  ";

  // Paginación server-side (opcional)
  if ($limit > 0) {
    $sql .= " LIMIT :limit OFFSET :offset";
  }

  $stmt = $pdo->prepare($sql);
  foreach ($params as $k => $v) {
    $type = PDO::PARAM_STR;
    if (is_int($v)) $type = PDO::PARAM_INT;
    $stmt->bindValue($k, $v, $type);
  }
  if ($limit > 0) {
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  }
  $stmt->execute();

  $rows = $stmt->fetchAll();

  /* =========================
   * 4) AGRUPACIÓN Cliente → Sedes
   * ========================= */
  $clientesMap = []; // clId => ['clId','clNombre','sedes' => [ csId => {...} ]]
  foreach ($rows as $r) {
    $clId = (int)$r['clId'];
    $csId = $r['csId'] !== null ? (int)$r['csId'] : 0; // 0 para "Sin sede"

    if (!isset($clientesMap[$clId])) {
      $clientesMap[$clId] = [
        'clId'     => $clId,
        'clNombre' => $r['clNombre'] ?? 'Sin cliente',
        'sedes'    => []
      ];
    }

    if (!isset($clientesMap[$clId]['sedes'][$csId])) {
      $clientesMap[$clId]['sedes'][$csId] = [
        'csId'     => $csId,
        'csNombre' => $r['csNombre'] ?? 'Sin sede',
        'tickets'  => []
      ];
    }

    // Normaliza fechas (evita "0000-00-00 00:00:00")
    $tiVisita = $r['tiVisita'];
    if ($tiVisita === '0000-00-00 00:00:00' || $tiVisita === '0000-00-00') {
      $tiVisita = null;
    }

    $clientesMap[$clId]['sedes'][$csId]['tickets'][] = [
      'tiId'         => (int)$r['tiId'],
      'tiEstatus'    => $r['tiEstatus'],
      'tiProceso'    => $r['tiProceso'],
      'tiTipoTicket' => $r['tiTipoTicket'],
      'tiExtra'      => $r['tiExtra'],
      'tiVisita'     => $tiVisita, // tu front ya lo pasa por fmtFechaHoraLocal()
      'eqModelo'     => $r['eqModelo'],
      'eqVersion'    => $r['eqVersion'],
      'maNombre'     => $r['maNombre'],
      'peSN'         => $r['peSN'],
      'clNombre'         => $r['clNombre'],
      'csNombre'         => $r['csNombre'],
      // Datos de contacto (si quieres mostrarlos en cards)
      'persona'      => $r['tiNombreContacto'],
      'contacto'     => $r['tiNumeroContacto'],
      'correo'       => $r['tiCorreoContacto'],
      // Puedes enviar también 'tiFechaCreacion' si la usas
      'tiFechaCreacion' => $r['tiFechaCreacion'],
      // Info útil para enlaces rápidos
      'clId'         => $clId,
      'csId'         => $csId
    ];
  }

  // Pasa sedes de map a array indexado para el JSON final
  $clientes = [];
  foreach ($clientesMap as $cl) {
    $sedesArr = array_values($cl['sedes']); // descarta las keys de mapa
    $cl['sedes'] = $sedesArr;
    $clientes[] = $cl;
  }

  echo json_encode([
    'success'  => true,
    'clientes' => $clientes,
    'count'    => count($rows)
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error'   => 'Error interno: '.$e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}
