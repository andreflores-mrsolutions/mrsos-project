<?php
declare(strict_types=1);

function eq_import_normalize_header(string $value): string {
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = str_replace(
        ['á','é','í','ó','ú','ñ',' '],
        ['a','e','i','o','u','n','_'],
        $value
    );
    $value = preg_replace('/[^a-z0-9_]+/', '', $value);
    return (string)$value;
}

function eq_import_required_headers(): array {
    return [
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
        'estatus',
    ];
}

function eq_import_normalize_status(?string $value): string {
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

function eq_import_is_blank_row(array $row): bool {
    foreach ($row as $value) {
        if (trim((string)$value) !== '') {
            return false;
        }
    }
    return true;
}