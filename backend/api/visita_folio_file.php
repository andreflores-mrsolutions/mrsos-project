<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';

no_store();
require_login();
require_usRol(['MRSA','MRA']);

$tiId = isset($_GET['tiId']) ? (int)$_GET['tiId'] : 0;
$mode = ($_GET['mode'] ?? 'view') === 'download' ? 'download' : 'view';
if ($tiId <= 0) { http_response_code(400); exit('Bad request'); }

$pdo = db();
$st = $pdo->prepare("SELECT archivoRuta FROM ticket_folio_entrada WHERE tiId=? ORDER BY creadoEn DESC LIMIT 1");
$st->execute([$tiId]);
$row = $st->fetch();
if (!$row || empty($row['archivoRuta'])) { http_response_code(404); exit('Not found'); }

$fname = basename((string)$row['archivoRuta']);
$path = __DIR__ . "/../uploads/visitas/{$tiId}/{$fname}";
if (!is_file($path)) { http_response_code(404); exit('Not found'); }

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = ($ext === 'pdf') ? 'application/pdf' : (($ext === 'png') ? 'image/png' : 'image/jpeg');

header('Content-Type: '.$mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($mode === 'download' ? 'attachment' : 'inline') . '; filename="'.$fname.'"');
readfile($path);
exit;