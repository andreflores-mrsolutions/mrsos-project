<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/auth_guard.php';

require_login();
require_usRol(['MRSA','MRA','MRV']);

$filename = 'plantilla_import_refacciones.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

fputcsv($out, [
    'marca',
    'part_number',
    'descripcion',
    'tipo_refaccion',
    'interfaz',
    'tipo',
    'capacidad',
    'tp_capacidad',
    'velocidad',
    'tp_velocidad',
    'estatus'
]);

fputcsv($out, [
    'HPE',
    'P18420-B21',
    'SSD 960GB SATA MU SFF',
    'SSD',
    'SATA',
    'SFF',
    '960',
    'GB',
    '6',
    'Gbps',
    'Activo'
]);

fputcsv($out, [
    'Dell',
    '400-ATJM',
    'HDD 1.2TB 10K SAS',
    'Disco Duro',
    'SAS',
    'SFF',
    '1.2',
    'TB',
    '10',
    'K RPM',
    'Activo'
]);

fclose($out);
exit;