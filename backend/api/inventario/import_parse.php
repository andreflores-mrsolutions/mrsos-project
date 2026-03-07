<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../php/import_inventario_helper.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

try {
    if (empty($_FILES['xlsx']['name'])) json_fail('Debes seleccionar un archivo XLSX.', 422);
    if (!isset($_FILES['xlsx']) || $_FILES['xlsx']['error'] !== UPLOAD_ERR_OK) json_fail('Error al subir el archivo.', 422);

    $tmp = $_FILES['xlsx']['tmp_name'];
    $name = (string)($_FILES['xlsx']['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext !== 'xlsx') json_fail('Solo se permiten archivos .xlsx', 422);

    $pdo = db();

    $reader = new Xlsx();
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($tmp);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);

    if (count($rows) < 2) json_fail('El archivo no contiene datos para importar.', 422);
    if (count($rows) > 5001) json_fail('El archivo excede el límite permitido de 5000 filas de datos.', 422);

    $headerRow = array_shift($rows);
    $headerMap = [];
    foreach ($headerRow as $col => $label) {
        $headerMap[$col] = inv_import_normalize_header((string)$label);
    }

    foreach (inv_import_required_headers() as $req) {
        if (!in_array($req, array_values($headerMap), true)) json_fail("Falta la columna obligatoria: {$req}", 422);
    }

    $preview = [];
    $errors = [];
    $seenSerial = [];

    $stRef = $pdo->query("SELECT refId, refPartNumber FROM refaccion");
    $refsByPn = [];
    foreach ($stRef->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $refsByPn[mb_strtolower(trim((string)$r['refPartNumber']), 'UTF-8')] = [
            'refId' => (int)$r['refId'],
            'refPartNumber' => (string)$r['refPartNumber'],
        ];
    }

    $stInv = $pdo->query("SELECT invId, invSerialNumber FROM inventario");
    $existingBySn = [];
    foreach ($stInv->fetchAll(PDO::FETCH_ASSOC) as $i) {
        $existingBySn[(string)$i['invSerialNumber']] = (int)$i['invId'];
    }

    $line = 1;
    foreach ($rows as $excelRow) {
        $line++;

        $rawRow = [];
        foreach ($headerMap as $col => $normKey) {
            $rawRow[$normKey] = trim((string)($excelRow[$col] ?? ''));
        }

        if (inv_import_is_blank_row($rawRow)) continue;

        $row = $rawRow;
        $row['estatus'] = inv_import_normalize_status($row['estatus'] ?? 'Activo');

        $rowErrors = [];
        $partNumber = $row['part_number'] ?? '';
        $serial = $row['serial_number'] ?? '';
        $ubicacion = $row['ubicacion'] ?? '';

        if ($partNumber === '') $rowErrors[] = 'Part Number vacío';
        if ($serial === '') $rowErrors[] = 'Serial Number vacío';
        if ($ubicacion === '') $rowErrors[] = 'Ubicación vacía';
        if (strlen($serial) > 30) $rowErrors[] = 'Serial Number excede 30 caracteres';
        if (mb_strlen($ubicacion) > 15) $rowErrors[] = 'Ubicación excede 15 caracteres';

        if (!in_array($row['estatus'], ['Activo','Inactivo','Cambios','Error'], true)) {
            $rowErrors[] = "Estatus inválido: {$row['estatus']}";
        }

        $pnKey = mb_strtolower($partNumber, 'UTF-8');
        $refId = $refsByPn[$pnKey]['refId'] ?? 0;
        if ($refId <= 0) $rowErrors[] = "Part Number no encontrado: {$partNumber}";

        if (isset($seenSerial[$serial])) $rowErrors[] = "Serial duplicado dentro del archivo: {$serial}";
        $seenSerial[$serial] = true;

        $exists = isset($existingBySn[$serial]);
        $invId = $exists ? $existingBySn[$serial] : null;

        $previewRow = [
            'line' => $line,
            'refId' => $refId,
            'refPartNumber' => $partNumber,
            'invId' => $invId,
            'invSerialNumber' => $serial,
            'invUbicacion' => $ubicacion,
            'invEstatus' => $row['estatus'],
            'exists' => $exists,
            'errors' => $rowErrors,
        ];

        $preview[] = $previewRow;

        if ($rowErrors) {
            $errors[] = [
                'line' => $line,
                'part_number' => $partNumber,
                'serial_number' => $serial,
                'messages' => $rowErrors,
            ];
        }
    }

    json_ok([
        'rows' => $preview,
        'summary' => [
            'total' => count($preview),
            'with_errors' => count($errors),
            'insertables' => count(array_filter($preview, fn($r) => !$r['exists'] && !$r['errors'])),
            'updatables' => count(array_filter($preview, fn($r) => $r['exists'] && !$r['errors'])),
        ],
        'errors' => $errors,
    ]);
} catch (Throwable $e) {
    json_fail('Error al procesar el XLSX: ' . $e->getMessage(), 500);
}