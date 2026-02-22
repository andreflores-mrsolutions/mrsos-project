<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA']);
csrf_verify_or_fail();

$tiId = isset($_POST['tiId']) ? (int)$_POST['tiId'] : 0;
if ($tiId <= 0) json_fail('tiId inválido');

if (empty($_FILES['folioFile']) || $_FILES['folioFile']['error'] !== UPLOAD_ERR_OK) {
  json_fail('Archivo inválido');
}

$pdo = db();
$usId = (int)($_SESSION['usId'] ?? 0);

// valida ticket
$st = $pdo->prepare("SELECT tiId FROM ticket_soporte WHERE tiId=? LIMIT 1");
$st->execute([$tiId]);
if (!$st->fetchColumn()) json_fail('Ticket no existe', 404);

$orig = $_FILES['folioFile']['name'] ?? 'folio';
$tmp  = $_FILES['folioFile']['tmp_name'] ?? '';
$mime = $_FILES['folioFile']['type'] ?? '';
$size = (int)($_FILES['folioFile']['size'] ?? 0);

$allowed = ['application/pdf','image/jpeg','image/png'];
if ($mime && !in_array($mime, $allowed, true)) json_fail('Tipo no permitido');

$dir = __DIR__ . "/../../php/uploads/visitas/{$tiId}/";
if (!is_dir($dir)) @mkdir($dir, 0775, true);

$ext = pathinfo($orig, PATHINFO_EXTENSION);
$fname = "folio_{$tiId}_" . date('YmdHis') . "." . ($ext ?: 'bin');
$dest = $dir . $fname;

if (!move_uploaded_file($tmp, $dest)) json_fail('No se pudo guardar el archivo', 500);

// guarda en ticket_folio_entrada
$folioTxt = "FOL-{$tiId}-" . date('His');
$pdo->prepare("
  INSERT INTO ticket_folio_entrada (tiId, folio, archivoRuta, comentario, creadoPor)
  VALUES (?, ?, ?, ?, ?)
")->execute([$tiId, $folioTxt, $fname, "Subido por admin ({$mime}, {$size} bytes)", $usId]);

// historial
$desc = "[VISITA] tiId={$tiId} · Folio cargado: {$folioTxt}";
$pdo->prepare("INSERT INTO historial (hDescripcion, usId, hFecha_hora, hTabla) VALUES (?, ?, ?, 'ticket_soporte')")
    ->execute([$desc, $usId, date('Y-m-d H:i:s')]);

json_ok(['folio'=>$folioTxt]);