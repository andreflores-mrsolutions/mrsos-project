<?php
declare(strict_types=1);

final class Backup
{
  private function __construct() {}

  public static function ensureDir(string $dir): bool
  {
    return is_dir($dir) || mkdir($dir, 0775, true);
  }

  public static function dumpTableRows(
    PDO $pdo,
    string $table,
    string $where,
    array $params = []
  ): string {
    $sql = "SELECT * FROM `$table`" . ($where ? " WHERE $where" : "");
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) return "-- $table: sin filas\n\n";

    $out = "-- Backup tabla: $table\n";
    foreach ($rows as $row) {
      $cols = array_map(fn($c) => "`$c`", array_keys($row));
      $vals = [];
      foreach ($row as $v) {
        if ($v === null) $vals[] = "NULL";
        else $vals[] = $pdo->quote((string)$v);
      }
      $out .= "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
    }
    $out .= "\n";
    return $out;
  }

  public static function savePolizaBackup(PDO $pdo, int $pcId, string $baseDir): array
  {
    $ts = date('Ymd_His');
    $dir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . "pc_$pcId";
    if (!self::ensureDir($dir)) {
      throw new RuntimeException('No se pudo crear carpeta de backup');
    }

    $file = $dir . DIRECTORY_SEPARATOR . "{$ts}.sql";

    $sql = "-- Backup póliza pcId=$pcId\n";
    $sql .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n\n";

    $sql .= self::dumpTableRows($pdo, 'polizascliente', 'pcId = ?', [$pcId]);
    $sql .= self::dumpTableRows($pdo, 'polizasequipo', 'pcId = ?', [$pcId]);

    file_put_contents($file, $sql);

    return [
      'ok' => true,
      'file' => $file,
      'filename' => basename($file),
    ];
  }
}