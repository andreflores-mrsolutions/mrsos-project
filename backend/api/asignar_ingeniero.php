<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
  }

  $tiId       = isset($_POST['tiId']) ? (int)$_POST['tiId'] : 0;
  $usIdIng    = isset($_POST['usIdIng']) ? (int)$_POST['usIdIng'] : 0;
  $nextProceso= $_POST['nextProceso'] ?? 'revision inicial';

  if ($tiId <= 0 || $usIdIng <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
  }

  // require_once __DIR__.'/db.php';
  // $DB_HOST = 'localhost';
  // $DB_NAME = 'u140302554_mrsos';
  // $DB_USER = 'u140302554_mrsos';
  // $DB_PASS = 'MRsolutions552312#$';
  $DB_HOST = 'localhost';
  $DB_NAME = 'mrsos';
  $DB_USER = 'root';
  $DB_PASS = '';
  $DB_CHARSET = 'utf8mb4';
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // Verifica que el ingeniero exista/activo
  $check = $pdo->prepare("SELECT u.usId FROM ingenieros i INNER JOIN usuarios u ON u.usId=i.usId WHERE i.ingEstatus='Activo' AND u.usEstatus='Activo' AND u.usId = :u");
  $check->execute([':u' => $usIdIng]);
  if (!$check->fetch()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ingeniero no válido']);
    exit;
  }

  // Actualiza ticket: asignar e ir al siguiente proceso
  $upd = $pdo->prepare("UPDATE ticket_soporte SET usIdIng = :ing, tiProceso = :proc WHERE tiId = :id");
  $upd->execute([':ing' => $usIdIng, ':proc' => $nextProceso, ':id' => $tiId]);

  // (Opcional) Inserta a historial
  // $hist = $pdo->prepare("INSERT INTO historial (hDescripcion, usId, hFecha_hora, hTabla, hEstatus) VALUES (:d, :u, NOW(), 'ticket_soporte', 'Activo')");
  // $hist->execute([':d' => "Asignado ingeniero $usIdIng y proceso → $nextProceso", ':u' => $usIdIng]);

  echo json_encode(['success' => true]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
