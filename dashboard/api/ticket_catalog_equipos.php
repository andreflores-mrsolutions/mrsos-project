<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();
csrf_verify_or_fail();

$pdo = db();

$usRol = (string)($_SESSION['usRol'] ?? '');
$clId = (int)($_SESSION['clId'] ?? 0);
$csId = isset($_GET['csId']) ? (int)$_GET['csId'] : 0;

if (!in_array($usRol, ['CLI', 'MRSA', 'MRA', 'MRV'], true)) {
    json_fail('Sin permisos', 403);
}

if ($clId <= 0) json_fail('Sesión sin cliente.', 400);
if ($csId <= 0) json_fail('Falta csId', 400);

function slug_folder(string $s): string {
    $s = trim(mb_strtolower($s));
    $s = str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $s);
    $s = preg_replace('/[^a-z0-9]+/u', '', $s) ?? '';
    return $s ?: 'default';
}

try {
    $st = $pdo->prepare("
        SELECT
          pe.peId,
          pe.eqId,
          pe.peSN AS sn,
          e.eqModelo AS modelo,
          e.eqImgPath AS imgPath,
          e.eqTipoEquipo AS tipoEquipo,
          m.maNombre AS marca,
          pc.pcTipoPoliza AS polizaTipo
        FROM polizasequipo pe
        INNER JOIN polizascliente pc ON pc.pcId = pe.pcId
        INNER JOIN equipos e ON e.eqId = pe.eqId
        INNER JOIN marca m ON m.maId = e.maId
        WHERE pc.clId = ?
          AND pe.csId = ?
          AND pe.peEstatus = 'Activo'
        ORDER BY e.eqModelo ASC, pe.peId DESC
    ");
    $st->execute([$clId, $csId]);
    $rows = $st->fetchAll();

    if (!$rows) {
        json_ok(['equipos' => []]);
    }

    $peIds = array_map(fn($r) => (int)$r['peId'], $rows);
    $in = implode(',', array_fill(0, count($peIds), '?'));

    $st2 = $pdo->prepare("
        SELECT peId, COUNT(*) cnt
        FROM ticket_soporte
        WHERE estatus='Activo'
          AND tiEstatus IN ('Abierto','Pospuesto')
          AND peId IN ($in)
        GROUP BY peId
    ");
    $st2->execute($peIds);
    $mapCnt = [];
    while ($r = $st2->fetch()) {
        $mapCnt[(int)$r['peId']] = (int)$r['cnt'];
    }

    $st3 = $pdo->prepare("
        SELECT ti.peId, ti.tiId, cl.clNombre
        FROM ticket_soporte ti
        INNER JOIN clientes cl ON cl.clId = ti.clId
        WHERE ti.estatus='Activo'
          AND ti.tiEstatus IN ('Abierto','Pospuesto')
          AND ti.peId IN ($in)
        ORDER BY ti.tiId DESC
    ");
    $st3->execute($peIds);
    $mapList = [];
    while ($r = $st3->fetch()) {
        $pid = (int)$r['peId'];
        $clid = strtoupper(substr((string)trim($r['clNombre']), 0, 3));
        if (!isset($mapList[$pid])) $mapList[$pid] = [];
        if (count($mapList[$pid]) < 3) $mapList[$pid][] = $clid . '-' . (int)$r['tiId'];
    }

    $equipos = [];
    foreach ($rows as $r) {
        $marcaFolder = slug_folder((string)$r['marca']);
        $modelo = (string)$r['modelo'];
        $img = $r['imgPath'] ? : "../img/Equipos/{$marcaFolder}/{$modelo}.png";

        $peId = (int)$r['peId'];
        $equipos[] = [
            'peId' => $peId,
            'eqId' => (int)$r['eqId'],
            'sn' => (string)$r['sn'],
            'modelo' => $modelo,
            'tipoEquipo' => (string)$r['tipoEquipo'],
            'marca' => (string)$r['marca'],
            'polizaTipo' => (string)($r['polizaTipo'] ?? ''),
            'img' => $img,
            'ticketsActivos' => $mapCnt[$peId] ?? 0,
            'ticketsList' => $mapList[$peId] ?? [],
        ];
    }

    json_ok(['equipos' => $equipos]);
} catch (Throwable $e) {
    json_fail('Error al obtener equipos. ' . $e->getMessage(), 500);
}