<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);

try {
    $pdo = db();

    $q = trim((string)($_GET['q'] ?? ''));
    $maId = (int)($_GET['maId'] ?? 0);
    $estatus = trim((string)($_GET['estatus'] ?? ''));

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['perPage'] ?? 20);
    $allowedPerPage = [10, 20, 50, 100];
    if (!in_array($perPage, $allowedPerPage, true)) {
        $perPage = 20;
    }

    $where = [];
    $params = [];

    if ($q !== '') {
        $where[] = "(
            e.eqModelo LIKE ?
            OR e.eqVersion LIKE ?
            OR e.eqTipoEquipo LIKE ?
            OR e.eqTipo LIKE ?
            OR e.eqCPU LIKE ?
            OR e.eqSockets LIKE ?
            OR e.eqMaxRAM LIKE ?
            OR e.eqNIC LIKE ?
            OR e.eqDescripcion LIKE ?
            OR m.maNombre LIKE ?
        )";
        for ($i = 0; $i < 10; $i++) {
            $params[] = '%' . $q . '%';
        }
    }

    if ($maId > 0) {
        $where[] = "e.maId = ?";
        $params[] = $maId;
    }

    if ($estatus !== '') {
        $where[] = "e.eqEstatus = ?";
        $params[] = $estatus;
    }

    $sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $sqlCount = "
        SELECT COUNT(*)
        FROM equipos e
        INNER JOIN marca m ON m.maId = e.maId
        {$sqlWhere}
    ";
    $stCount = $pdo->prepare($sqlCount);
    $stCount->execute($params);
    $total = (int)$stCount->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $sql = "
        SELECT
            e.eqId,
            e.eqModelo,
            e.eqVersion,
            e.eqImgPath,
            e.eqTipoEquipo,
            e.maId,
            e.eqTipo,
            e.eqCPU,
            e.eqSockets,
            e.eqMaxRAM,
            e.eqNIC,
            e.eqDescripcion,
            e.eqEstatus,
            m.maNombre,
            m.maImgPath
        FROM equipos e
        INNER JOIN marca m ON m.maId = e.maId
        {$sqlWhere}
        ORDER BY m.maNombre ASC, e.eqModelo ASC, e.eqVersion ASC, e.eqId ASC
        LIMIT {$perPage} OFFSET {$offset}
    ";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $stStats = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN eqEstatus='Activo' THEN 1 ELSE 0 END) AS activos,
            SUM(CASE WHEN eqEstatus='Inactivo' THEN 1 ELSE 0 END) AS inactivos
        FROM equipos
    ");
    $stats = $stStats->fetch(PDO::FETCH_ASSOC) ?: [];

    $from = $total > 0 ? ($offset + 1) : 0;
    $to = min($offset + $perPage, $total);

    json_ok([
        'rows' => $rows,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
            'from' => $from,
            'to' => $to,
        ],
        'stats' => [
            'total' => (int)($stats['total'] ?? 0),
            'activos' => (int)($stats['activos'] ?? 0),
            'inactivos' => (int)($stats['inactivos'] ?? 0),
        ]
    ]);
} catch (Throwable $e) {
    json_fail('Error al listar equipos: ' . $e->getMessage(), 500);
}