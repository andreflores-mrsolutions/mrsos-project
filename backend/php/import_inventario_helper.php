<?php
declare(strict_types=1);

function inv_import_normalize_header(string $value): string {
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = str_replace(['á','é','í','ó','ú','ñ',' '], ['a','e','i','o','u','n','_'], $value);
    $value = preg_replace('/[^a-z0-9_]+/', '', $value);
    return (string)$value;
}

function inv_import_required_headers(): array {
    return ['part_number','serial_number','ubicacion','estatus'];
}

function inv_import_normalize_status(?string $value): string {
    $v = trim((string)$value);
    if ($v === '') return 'Activo';

    $map = [
        'activo' => 'Activo',
        'inactivo' => 'Inactivo',
        'cambios' => 'Cambios',
        'error' => 'Error',
    ];

    $k = mb_strtolower($v, 'UTF-8');
    return $map[$k] ?? $v;
}

function inv_import_is_blank_row(array $row): bool {
    foreach ($row as $value) {
        if (trim((string)$value) !== '') return false;
    }
    return true;
}