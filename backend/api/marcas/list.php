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
        $where[] = 'maNombre LIKE ?';
        $params[] = '%' . $q . '%';
    }

    if ($estatus !== '') {
        $where[] = 'maEstatus = ?';
        $params[] = $estatus;
    }

    $sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $sqlCount = "SELECT COUNT(*) FROM marca {$sqlWhere}";
    $stCount = $pdo->prepare($sqlCount);
    $stCount->execute($params);
    $total = (int)$stCount->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;

    $sql = "
        SELECT maId, maNombre, maImgPath, maEstatus
        FROM marca
        {$sqlWhere}
        ORDER BY maNombre ASC, maId ASC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $stStats = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN maEstatus='Activo' THEN 1 ELSE 0 END) AS activas,
            SUM(CASE WHEN maEstatus='Inactivo' THEN 1 ELSE 0 END) AS inactivas
        FROM marca
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
            'activas' => (int)($stats['activas'] ?? 0),
            'inactivas' => (int)($stats['inactivas'] ?? 0),
        ]
    ]);
} catch (Throwable $e) {
    json_fail('Error al listar marcas: ' . $e->getMessage(), 500);
}