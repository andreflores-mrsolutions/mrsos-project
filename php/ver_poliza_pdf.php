<?php
// ../php/ver_poliza_pdf.php
declare(strict_types=1);
session_start();

// Opcional: podrías validar sesión aquí
if (empty($_SESSION['usId'])) {
    http_response_code(401);
    echo "Sesión no válida.";
    exit;
}

$pcId = isset($_GET['pcId']) ? (int)$_GET['pcId'] : 0;
if ($pcId <= 0) {
    http_response_code(400);
    echo "Póliza inválida.";
    exit;
}

// Ruta donde guardas los PDFs de pólizas
// Ejemplo: /polizas/poliza_12.pdf, poliza_13.pdf, etc.
$baseDir   = realpath(__DIR__ . '/../polizas');
if (!$baseDir) {
    http_response_code(500);
    echo "Directorio de pólizas no disponible.";
    exit;
}

$filename  = "poliza_{$pcId}.pdf";
$filePath  = $baseDir . DIRECTORY_SEPARATOR . $filename;

// Seguridad: evitar directorio traversal (por si acaso)
if (strpos($filePath, $baseDir) !== 0) {
    http_response_code(400);
    echo "Ruta no válida.";
    exit;
}

if (!is_file($filePath)) {
    http_response_code(404);
    echo "No se encontró el PDF de la póliza.";
    exit;
}

// Enviar PDF
header('Content-Type: application/pdf');
// inline -> abrir en navegador; attachment -> forzar descarga
header('Content-Disposition: inline; filename="' . basename($filename) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($filePath);
exit;
