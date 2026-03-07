<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/auth_guard.php';

require_login();
require_usRol(['MRSA','MRA','MRV']);

$filename = 'plantilla_import_equipos.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

fputcsv($out, [
    'marca',
    'modelo',
    'version',
    'tipo_equipo',
    'tipo',
    'cpu',
    'sockets',
    'max_ram',
    'nic',
    'descripcion',
    'estatus'
]);

fputcsv($out, [
    'xFusion',
    'FusionServer 2288H',
    'V7',
    'Server',
    'Rack 2U',
    'Intel Xeon Scalable 4th Gen',
    '2',
    '8 TB',
    '2x10GbE',
    'Servidor 2U de alto desempeño',
    'Activo'
]);

fputcsv($out, [
    'HPE',
    'ProLiant DL380',
    'Gen10',
    'Server',
    'Rack 2U',
    'Intel Xeon Scalable',
    '2',
    '6 TB',
    '4x10GbE',
    'Servidor empresarial 2U',
    'Activo'
]);

fclose($out);
exit;