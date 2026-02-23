<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';

no_store();
require_login();

$hsId = isset($_GET['hsId']) ? (int)$_GET['hsId'] : 0;
if ($hsId <= 0) { http_response_code(400); echo "hsId inválido"; exit; }

$pdo = db();

$st = $pdo->prepare("SELECT hsId, clId, hsPath, hsMime, hsFolio, hsActivo FROM hojas_servicio WHERE hsId=? LIMIT 1");
$st->execute([$hsId]);
$hs = $st->fetch();

if (!$hs || (int)$hs['hsActivo'] !== 1) { http_response_code(404); echo "No encontrado"; exit; }

// Permisos (MR: ok; Cliente: solo los suyos)
$clId = (int)$hs['clId'];
if (!is_mr()) {
  $clIdSes = (int)($_SESSION['clId'] ?? 0);
  if ($clIdSes <= 0 || $clIdSes !== $clId) { http_response_code(403); echo "Sin permisos"; exit; }
}

// Ruta absoluta (hsPath relativo a /backend)
$baseBackend = realpath(__DIR__ . '/../../..') ?: (__DIR__ . '/../../..');
$abs = rtrim($baseBackend, '/\\') . DIRECTORY_SEPARATOR . ltrim((string)$hs['hsPath'], '/\\');

if (!is_file($abs)) { http_response_code(404); echo "Archivo no encontrado"; exit; }

$mime = (string)($hs['hsMime'] ?? 'application/pdf');
$folio = preg_replace('/[^A-Za-z0-9\-_]/', '_', (string)($hs['hsFolio'] ?? ('HS-'.$hsId)));
$downloadName = $folio . '.pdf';

header('Content-Type: '.$mime);
header('Content-Disposition: attachment; filename="'.$downloadName.'"');
header('Content-Length: '.filesize($abs));
readfile($abs);
exit;