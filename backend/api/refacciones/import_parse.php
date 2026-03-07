<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../php/import_refacciones_helper.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

try {
    if (empty($_FILES['xlsx']['name'])) {
        json_fail('Debes seleccionar un archivo XLSX.', 422);
    }

    if (!isset($_FILES['xlsx']) || $_FILES['xlsx']['error'] !== UPLOAD_ERR_OK) {
        json_fail('Error al subir el archivo.', 422);
    }

    $tmp = $_FILES['xlsx']['tmp_name'];
    $name = (string)($_FILES['xlsx']['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($ext !== 'xlsx') {
        json_fail('Solo se permiten archivos .xlsx', 422);
    }

    $pdo = db();

    $reader = new Xlsx();
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($tmp);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);

    if (count($rows) < 2) {
        json_fail('El archivo no contiene datos para importar.', 422);
    }

    if (count($rows) > 5001) {
        json_fail('El archivo excede el límite permitido de 5000 filas de datos.', 422);
    }

    $headerRow = array_shift($rows);
    $headerMap = [];
    foreach ($headerRow as $col => $label) {
        $headerMap[$col] = ref_import_normalize_header((string)$label);
    }

    $required = ref_import_required_headers();
    $present = array_values($headerMap);

    foreach ($required as $req) {
        if (!in_array($req, $present, true)) {
            json_fail("Falta la columna obligatoria: {$req}", 422);
        }
    }

    $preview = [];
    $errors = [];
    $partNumbersSeen = [];

    $stMarca = $pdo->query("SELECT maId, maNombre FROM marca");
    $marcas = [];
    foreach ($stMarca->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $marcas[mb_strtolower(trim((string)$m['maNombre']), 'UTF-8')] = [
            'maId' => (int)$m['maId'],
            'maNombre' => (string)$m['maNombre'],
        ];
    }

    $stRef = $pdo->query("SELECT refId, refPartNumber FROM refaccion");
    $existingByPn = [];
    foreach ($stRef->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $existingByPn[mb_strtolower(trim((string)$r['refPartNumber']), 'UTF-8')] = (int)$r['refId'];
    }

    $allowedTipos = ref_import_allowed_tipos();

    $line = 1;
    foreach ($rows as $excelRow) {
        $line++;

        $rawRow = [];
        foreach ($headerMap as $col => $normKey) {
            $rawRow[$normKey] = trim((string)($excelRow[$col] ?? ''));
        }

        if (ref_import_is_blank_row($rawRow)) {
            continue;
        }

        $row = $rawRow;
        $row['tipo_refaccion'] = ref_import_normalize_tipo_refaccion($row['tipo_refaccion'] ?? '');
        $row['estatus'] = ref_import_normalize_status($row['estatus'] ?? 'Activo');

        $marcaNombre = $row['marca'] ?? '';
        $partNumber  = $row['part_number'] ?? '';

        $rowErrors = [];

        if ($marcaNombre === '') $rowErrors[] = 'Marca vacía';
        if ($partNumber === '') $rowErrors[] = 'Part Number vacío';
        if (($row['descripcion'] ?? '') === '') $rowErrors[] = 'Descripción vacía';
        if (($row['tipo_refaccion'] ?? '') === '') $rowErrors[] = 'Tipo de refacción vacío';
        if (($row['interfaz'] ?? '') === '') $rowErrors[] = 'Interfaz vacía';
        if (($row['tipo'] ?? '') === '') $rowErrors[] = 'Tipo vacío';

        if (mb_strlen($partNumber) > 50) $rowErrors[] = 'Part Number excede 50 caracteres';
        if (mb_strlen((string)($row['interfaz'] ?? '')) > 25) $rowErrors[] = 'Interfaz excede 25 caracteres';
        if (mb_strlen((string)($row['tipo'] ?? '')) > 15) $rowErrors[] = 'Tipo excede 15 caracteres';
        if (mb_strlen((string)($row['tp_capacidad'] ?? '')) > 15) $rowErrors[] = 'Unidad de capacidad excede 15 caracteres';
        if (mb_strlen((string)($row['tp_velocidad'] ?? '')) > 15) $rowErrors[] = 'Unidad de velocidad excede 15 caracteres';

        if (($row['tipo_refaccion'] ?? '') !== '' && !in_array($row['tipo_refaccion'], $allowedTipos, true)) {
            $rowErrors[] = 'Tipo de refacción no permitido';
        }

        if (!in_array($row['estatus'], ['Activo','Inactivo','Cambios','Error'], true)) {
            $rowErrors[] = "Estatus inválido: {$row['estatus']}";
        }

        $marcaKey = mb_strtolower($marcaNombre, 'UTF-8');
        $maId = $marcas[$marcaKey]['maId'] ?? 0;
        if ($maId <= 0) {
            $rowErrors[] = "Marca no encontrada: {$marcaNombre}";
        }

        $pnKey = mb_strtolower($partNumber, 'UTF-8');
        if (isset($partNumbersSeen[$pnKey])) {
            $rowErrors[] = "Part Number duplicado dentro del archivo: {$partNumber}";
        }
        $partNumbersSeen[$pnKey] = true;

        $exists = isset($existingByPn[$pnKey]);
        $refId = $exists ? $existingByPn[$pnKey] : null;

        $previewRow = [
            'line' => $line,
            'maId' => $maId,
            'marca' => $marcaNombre,
            'refId' => $refId,
            'refPartNumber' => $partNumber,
            'refDescripcion' => $row['descripcion'] ?? '',
            'refTipoRefaccion' => $row['tipo_refaccion'] ?? '',
            'refInterfaz' => $row['interfaz'] ?? '',
            'refTipo' => $row['tipo'] ?? '',
            'refCapacidad' => ref_import_to_decimal($row['capacidad'] ?? ''),
            'refTpCapacidad' => $row['tp_capacidad'] ?? '',
            'refVelocidad' => ref_import_to_decimal($row['velocidad'] ?? ''),
            'refTpVelocidad' => $row['tp_velocidad'] ?? '',
            'refEstatus' => $row['estatus'],
            'exists' => $exists,
            'errors' => $rowErrors,
        ];

        $preview[] = $previewRow;

        if ($rowErrors) {
            $errors[] = [
                'line' => $line,
                'part_number' => $partNumber,
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