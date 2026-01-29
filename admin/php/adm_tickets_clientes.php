<?php
// ../php/adm_tickets_clientes.php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/conexion.php';

function json_response(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1) Validar sesión & rol
$usId  = isset($_SESSION['usId']) ? (int)$_SESSION['usId'] : 0;
$usRol = $_SESSION['usRol'] ?? null; // CLI | MRA | MRV | MRSA

if ($usId <= 0 || !$usRol) {
    json_response([
        'success' => false,
        'error'   => 'Sesión no válida.'
    ]);
}

// Sólo “lado administrador MR”
$isMR = in_array($usRol, ['MRA','MRV','MRSA'], true);
if (!$isMR) {
    json_response([
        'success' => false,
        'error'   => 'No tienes permisos para ver el administrador de tickets.'
    ]);
}

// 2) Traer clientes con conteo de tickets
$sql = "
    SELECT 
        c.clId,
        c.clNombre,
        -- Tickets abiertos / pospuestos
        SUM(
            CASE 
              WHEN t.estatus = 'Activo' 
               AND t.tiEstatus IN ('Abierto','Pospuesto')
              THEN 1 ELSE 0 
            END
        ) AS abiertos,
        -- Tickets cerrados / cancelados
        SUM(
            CASE 
              WHEN t.estatus = 'Activo'
               AND t.tiEstatus IN ('Cerrado','Cancelado')
              THEN 1 ELSE 0
            END
        ) AS cerrados,
        COUNT(t.tiId) AS totalTickets
    FROM clientes c
    LEFT JOIN ticket_soporte t
        ON t.clId = c.clId
    GROUP BY c.clId, c.clNombre
    HAVING totalTickets > 0
    ORDER BY c.clNombre ASC
";

$result = $conectar->query($sql);
if (!$result) {
    $errorMsg = ($conectar instanceof mysqli) ? $conectar->error : 'Unknown error';
    json_response([
        'success' => false,
        'error'   => 'Error al consultar clientes: ' . $errorMsg
    ]);
}

$clientes = [];
while ($row = $result->fetch_assoc()) {
    $clientes[] = [
        'clId'          => (int)$row['clId'],
        'clNombre'      => $row['clNombre'],
        'abiertos'      => (int)$row['abiertos'],
        'cerrados'      => (int)$row['cerrados'],
        'totalTickets'  => (int)$row['totalTickets'],
    ];
}

json_response([
    'success'  => true,
    'clientes' => $clientes
]);
