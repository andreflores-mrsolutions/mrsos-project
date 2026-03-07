<?php
declare(strict_types=1);

/**
 * Historial centralizado MRSOS
 * - Inserta registros en tabla historial
 * - Uso: Historial::log($pdo, $usId, 'tabla', 'descripcion', 'Activo');
 *
 * Requisitos esperados en BD:
 * historial(hId AI, hDescripcion TEXT/VARCHAR, usId INT, hFecha_hora DATETIME/TIMESTAMP, hTabla VARCHAR, hEstatus VARCHAR)
 */
final class Historial
{
  private function __construct() {}

  /**
   * Inserta un registro de historial.
   * Nunca debe romper el flujo principal: si falla, regresa false.
   */
  public static function log(PDO $pdo, int $usId, string $hTabla, string $hDescripcion, string $hEstatus = 'Activo'): bool
  {
    $hTabla = trim($hTabla);
    $hDescripcion = trim($hDescripcion);
    $hEstatus = trim($hEstatus) !== '' ? trim($hEstatus) : 'Activo';

    if ($usId <= 0) return false;
    if ($hTabla === '' || $hDescripcion === '') return false;

    // Hard limit para evitar logs gigantes por accidente
    if (mb_strlen($hDescripcion, 'UTF-8') > 5000) {
      $hDescripcion = mb_substr($hDescripcion, 0, 5000, 'UTF-8') . '…';
    }

    try {
      // Si hFecha_hora tiene DEFAULT CURRENT_TIMESTAMP, no lo mandamos.
      // Si NO tiene default, usamos NOW() aquí.
      $st = $pdo->prepare("
        INSERT INTO historial (hDescripcion, usId, hFecha_hora, hTabla, hEstatus)
        VALUES (:d, :usId, NOW(), :t, :e)
      ");
      $st->execute([
        ':d' => $hDescripcion,
        ':usId' => $usId,
        ':t' => $hTabla,
        ':e' => $hEstatus,
      ]);
      return true;
    } catch (Throwable $e) {
      // No romper endpoints por logging
      return false;
    }
  }

  /**
   * Helper opcional: arma descripción consistente con contexto.
   * Ejemplo:
   * Historial::msg('UPDATE', 'polizasequipo', ['peId'=>3], 'Cambio SN ...')
   */
  public static function msg(string $action, string $table, array $ctx = [], string $detail = ''): string
  {
    $action = strtoupper(trim($action));
    $table = trim($table);
    $detail = trim($detail);

    $parts = [];
    $parts[] = $action . ' ' . $table;

    if (!empty($ctx)) {
      // key=value
      $kv = [];
      foreach ($ctx as $k => $v) {
        if ($v === null || $v === '') continue;
        $kv[] = $k . '=' . (is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE));
      }
      if ($kv) $parts[] = '(' . implode(', ', $kv) . ')';
    }

    if ($detail !== '') $parts[] = '- ' . $detail;

    return implode(' ', $parts);
  }
}