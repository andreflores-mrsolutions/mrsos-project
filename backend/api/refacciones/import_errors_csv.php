<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';

require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$errors = $data['errors'] ?? [];

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="errores_import_refacciones.csv"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($out, ['linea', 'part_number', 'errores']);

foreach ($errors as $err) {
    fputcsv($out, [
        $err['line'] ?? '',
        $err['part_number'] ?? '',
        implode(' | ', $err['messages'] ?? [])
    ]);
}

fclose($out);
exit;