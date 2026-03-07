<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../php/import_equipos_helper.php';

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
        $headerMap[$col] = eq_import_normalize_header((string)$label);
    }

    $required = eq_import_required_headers();
    $present = array_values($headerMap);

    foreach ($required as $req) {
        if (!in_array($req, $present, true)) {
            json_fail("Falta la columna obligatoria: {$req}", 422);
        }
    }

    $preview = [];
    $errors = [];
    $seen = [];

    $stMarca = $pdo->query("SELECT maId, maNombre FROM marca");
    $marcas = [];
    foreach ($stMarca->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $marcas[mb_strtolower(trim((string)$m['maNombre']), 'UTF-8')] = [
            'maId' => (int)$m['maId'],
            'maNombre' => (string)$m['maNombre'],
        ];
    }

    $stEq = $pdo->query("SELECT eqId, maId, eqModelo, eqVersion FROM equipos");
    $existing = [];
    foreach ($stEq->fetchAll(PDO::FETCH_ASSOC) as $e) {
        $k = (int)$e['maId'] . '|' .
             mb_strtolower(trim((string)$e['eqModelo']), 'UTF-8') . '|' .
             mb_strtolower(trim((string)$e['eqVersion']), 'UTF-8');
        $existing[$k] = (int)$e['eqId'];
    }

    $line = 1;
    foreach ($rows as $excelRow) {
        $line++;

        $rawRow = [];
        foreach ($headerMap as $col => $normKey) {
            $rawRow[$normKey] = trim((string)($excelRow[$col] ?? ''));
        }

        if (eq_import_is_blank_row($rawRow)) {
            continue;
        }

        $row = $rawRow;
        $row['estatus'] = eq_import_normalize_status($row['estatus'] ?? 'Activo');

        $rowErrors = [];

        foreach (['marca','modelo','version','tipo_equipo','tipo','cpu','sockets','max_ram','nic','descripcion'] as $field) {
            if (($row[$field] ?? '') === '') {
                $rowErrors[] = "Campo vacío: {$field}";
            }
        }

        if (!in_array($row['estatus'], ['Activo','Inactivo','Cambios','Error'], true)) {
            $rowErrors[] = "Estatus inválido: {$row['estatus']}";
        }

        if (mb_strlen($row['modelo'] ?? '') > 50) $rowErrors[] = 'Modelo excede 50 caracteres';
        if (mb_strlen($row['version'] ?? '') > 25) $rowErrors[] = 'Versión excede 25 caracteres';
        if (mb_strlen($row['tipo_equipo'] ?? '') > 50) $rowErrors[] = 'Tipo de equipo excede 50 caracteres';
        if (mb_strlen($row['tipo'] ?? '') > 50) $rowErrors[] = 'Tipo excede 50 caracteres';
        if (mb_strlen($row['cpu'] ?? '') > 50) $rowErrors[] = 'CPU excede 50 caracteres';
        if (mb_strlen($row['sockets'] ?? '') > 50) $rowErrors[] = 'Sockets excede 50 caracteres';
        if (mb_strlen($row['max_ram'] ?? '') > 50) $rowErrors[] = 'Max RAM excede 50 caracteres';
        if (mb_strlen($row['nic'] ?? '') > 50) $rowErrors[] = 'NIC excede 50 caracteres';

        $marcaKey = mb_strtolower(trim((string)($row['marca'] ?? '')), 'UTF-8');
        $maId = $marcas[$marcaKey]['maId'] ?? 0;
        if ($maId <= 0) {
            $rowErrors[] = "Marca no encontrada: {$row['marca']}";
        }

        $key = $maId . '|' .
            mb_strtolower(trim((string)($row['modelo'] ?? '')), 'UTF-8') . '|' .
            mb_strtolower(trim((string)($row['version'] ?? '')), 'UTF-8');

        if (isset($seen[$key])) {
            $rowErrors[] = 'Equipo duplicado dentro del archivo (marca + modelo + versión)';
        }
        $seen[$key] = true;

        $exists = isset($existing[$key]);
        $eqId = $exists ? $existing[$key] : null;

        $previewRow = [
            'line' => $line,
            'maId' => $maId,
            'marca' => $row['marca'] ?? '',
            'eqId' => $eqId,
            'eqModelo' => $row['modelo'] ?? '',
            'eqVersion' => $row['version'] ?? '',
            'eqTipoEquipo' => $row['tipo_equipo'] ?? '',
            'eqTipo' => $row['tipo'] ?? '',
            'eqCPU' => $row['cpu'] ?? '',
            'eqSockets' => $row['sockets'] ?? '',
            'eqMaxRAM' => $row['max_ram'] ?? '',
            'eqNIC' => $row['nic'] ?? '',
            'eqDescripcion' => $row['descripcion'] ?? '',
            'eqEstatus' => $row['estatus'] ?? 'Activo',
            'exists' => $exists,
            'errors' => $rowErrors,
        ];

        $preview[] = $previewRow;

        if ($rowErrors) {
            $errors[] = [
                'line' => $line,
                'modelo' => $row['modelo'] ?? '',
                'version' => $row['version'] ?? '',
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