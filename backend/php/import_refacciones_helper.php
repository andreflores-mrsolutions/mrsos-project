<?php
declare(strict_types=1);

function ref_import_normalize_header(string $value): string {
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = str_replace(
        ['á','é','í','ó','ú','ñ',' '],
        ['a','e','i','o','u','n','_'],
        $value
    );
    $value = preg_replace('/[^a-z0-9_]+/', '', $value);
    return (string)$value;
}

function ref_import_normalize_status(?string $value): string {
    $v = trim((string)$value);
    if ($v === '') return 'Activo';

    $map = [
        'activo'   => 'Activo',
        'inactivo' => 'Inactivo',
        'cambios'  => 'Cambios',
        'error'    => 'Error',
    ];

    $k = mb_strtolower($v, 'UTF-8');
    return $map[$k] ?? $v;
}

function ref_import_to_decimal($value): float {
    if ($value === null || $value === '') return 0.0;
    $value = str_replace(',', '.', trim((string)$value));
    return is_numeric($value) ? (float)$value : 0.0;
}

function ref_import_required_headers(): array {
    return [
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
        'estatus',
    ];
}

function ref_import_allowed_tipos(): array {
    return [
        'Network Card',
        'Video Card',
        'RAID Card',
        'PCIE Card',
        'Motherboard',
        'Hard Disk',
        'DIMM',
        'Processador',
        'Fan Module',
        'Gbics',
        'Power Supply',
        'Cinta LTO',
        'Backplain',
        'Nodo',
        'Flash Card',
        'Disipador de Calor',
        'Manage Card',
        'Diagnostic Card',
        'Caddy',
        'Sistema Operativo',
        'Swicth Module',
    ];
}

function ref_import_normalize_tipo_refaccion(string $value): string {
    $value = trim($value);
    if ($value === '') return '';

    $allowed = ref_import_allowed_tipos();

    foreach ($allowed as $item) {
        if (mb_strtolower($item, 'UTF-8') === mb_strtolower($value, 'UTF-8')) {
            return $item;
        }
    }

    return $value;
}

function ref_import_is_blank_row(array $row): bool {
    foreach ($row as $value) {
        if (trim((string)$value) !== '') {
            return false;
        }
    }
    return true;
}