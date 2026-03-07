<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/auth_guard.php';

require_login();
require_usRol(['MRSA','MRA','MRV']);

$filename = 'plantilla_import_inventario.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($out, ['part_number','serial_number','ubicacion','estatus']);
fputcsv($out, ['P18420-B21','2106195YSAXEP2000221','A1-R1','Activo']);
fputcsv($out, ['400-ATJM','SN-DEMO-0002','A1-R2','Activo']);

fclose($out);
exit;