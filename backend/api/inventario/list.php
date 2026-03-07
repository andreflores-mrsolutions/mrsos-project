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
    $tipo = trim((string)($_GET['tipo'] ?? ''));
    $estatus = trim((string)($_GET['estatus'] ?? ''));

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['perPage'] ?? 20);
    $allowed = [10,20,50,100];
    if (!in_array($perPage, $allowed, true)) $perPage = 20;

    $where = [];
    $params = [];

    if ($q !== '') {
        $where[] = "(
            CAST(i.invSerialNumber AS CHAR) LIKE ?
            OR r.refPartNumber LIKE ?
            OR r.refDescripcion LIKE ?
            OR i.invUbicacion LIKE ?
            OR m.maNombre LIKE ?
        )";
        for ($i=0;$i<5;$i++) $params[] = '%' . $q . '%';
    }

    if ($maId > 0) {
        $where[] = "m.maId = ?";
        $params[] = $maId;
    }

    if ($tipo !== '') {
        $where[] = "r.refTipoRefaccion = ?";
        $params[] = $tipo;
    }

    if ($estatus !== '') {
        $where[] = "i.invEstatus = ?";
        $params[] = $estatus;
    }

    $sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $sqlCount = "
        SELECT COUNT(*)
        FROM inventario i
        INNER JOIN refaccion r ON r.refId = i.refId
        INNER JOIN marca m ON m.maId = r.maId
        {$sqlWhere}
    ";
    $stCount = $pdo->prepare($sqlCount);
    $stCount->execute($params);
    $total = (int)$stCount->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;

    $sql = "
        SELECT
            i.invId, i.invSerialNumber, i.refId, i.invUbicacion, i.invEstatus,
            r.refPartNumber, r.refDescripcion, r.refTipoRefaccion,
            m.maId, m.maNombre
        FROM inventario i
        INNER JOIN refaccion r ON r.refId = i.refId
        INNER JOIN marca m ON m.maId = r.maId
        {$sqlWhere}
        ORDER BY i.invId DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $stats = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN invEstatus='Activo' THEN 1 ELSE 0 END) AS activos,
            SUM(CASE WHEN invEstatus='Inactivo' THEN 1 ELSE 0 END) AS inactivos
        FROM inventario
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

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
    json_fail('Error listando inventario: ' . $e->getMessage(), 500);
}