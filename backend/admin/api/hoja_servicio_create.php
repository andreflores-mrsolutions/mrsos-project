<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA', 'MRA', 'MRV']);
csrf_verify_or_fail();

function require_composer_autoload(): void
{
    $candidates = [
        __DIR__ . '/../../../../vendor/autoload.php',
        __DIR__ . '/../../../vendor/autoload.php',
    ];

    foreach ($candidates as $p) {
        if (file_exists($p)) {
            require_once $p;
            return;
        }
    }

    throw new RuntimeException('No se encontró vendor/autoload.php');
}

require_composer_autoload();

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

$pdo = db();
$in = read_json_body();

$tiId = isset($in['tiId']) ? (int)$in['tiId'] : 0;
if ($tiId <= 0) {
    json_fail('tiId inválido', 400);
}

/**
 * 1) Cargar ticket base
 */
$st = $pdo->prepare("
    SELECT
        t.tiId,
        t.clId,
        t.csId,
        t.peId,
        t.eqId,
        t.usIdIng,
        t.tiNombreContacto,
        t.tiNumeroContacto,
        t.tiCorreoContacto,
        t.tiDescripcion,
        t.tiDiagnostico,
        t.tiProceso,
        t.tiVisitaFecha,
        t.tiVisitaHora,
        c.clNombre,
        c.clDireccion,
        c.clTelefono,
        c.clCorreo,
        cs.csNombre,
        cs.csDireccion,
        e.eqModelo,
        e.eqVersion,
        m.maNombre,
        pe.peSN,
        pe.peSO
    FROM ticket_soporte t
    LEFT JOIN clientes c ON c.clId = t.clId
    LEFT JOIN cliente_sede cs ON cs.csId = t.csId
    LEFT JOIN equipos e ON e.eqId = t.eqId
    LEFT JOIN marca m ON m.maId = e.maId
    LEFT JOIN polizasequipo pe ON pe.peId = t.peId
    WHERE t.tiId = ?
    LIMIT 1
");
$st->execute([$tiId]);
$t = $st->fetch();

if (!$t) {
    json_fail('Ticket no existe', 404);
}

/**
 * 2) Folio HS
 */
$ts = (new DateTime('now'))->format('YmdHis');
$hsFolio = 'HS-' . $tiId . '-' . $ts;
$hsPrefix = 'HS';
$hsTipo = 'T';

/**
 * 3) Transacción
 */
$pdo->beginTransaction();

try {
    // Consecutivo
    $stN = $pdo->prepare("
        SELECT COALESCE(MAX(hsNumero), 0)
        FROM hojas_servicio
        WHERE hsPrefix = ? AND hsTipo = ?
    ");
    $stN->execute([$hsPrefix, $hsTipo]);
    $hsNumero = (int)$stN->fetchColumn() + 1;

    // Paths
    $relDir = 'uploads/hojas_servicio/' . $tiId;

    $baseBackend = realpath(__DIR__ . '/../../..') ?: (__DIR__ . '/../../..');
    $absDir = rtrim($baseBackend, '/\\') . DIRECTORY_SEPARATOR . $relDir;

    if (!is_dir($absDir) && !mkdir($absDir, 0775, true)) {
        throw new RuntimeException('No se pudo crear directorio de uploads');
    }

    $fileName = 'hoja_servicio_' . $hsFolio . '.pdf';
    $relPath = $relDir . '/' . $fileName;
    $absPath = $absDir . DIRECTORY_SEPARATOR . $fileName;

    // tempDir seguro
    $tmp = rtrim($baseBackend, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'mpdf';

    if (!is_dir($tmp) && !mkdir($tmp, 0775, true)) {
        throw new RuntimeException('No se pudo crear tempDir: ' . $tmp);
    }

    if (!is_writable($tmp)) {
        throw new RuntimeException('tempDir no escribible: ' . $tmp);
    }

    // 5) Render HTML
    $html = build_hs_html($in, $t, $hsFolio);

    // 6) PDF
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 12,
        'tempDir' => $tmp,
    ]);

    $mpdf->SetTitle('Hoja de Servicio ' . $hsFolio);
    $mpdf->WriteHTML($html);
    $mpdf->Output($absPath, Destination::FILE);

    // 7) Insert BD
    $hsNombreEquipo = trim(($t['eqModelo'] ?? '') . ' ' . ($t['eqVersion'] ?? ''));

    $stI = $pdo->prepare("
        INSERT INTO hojas_servicio
        (
            clId,
            csId,
            tiId,
            peId,
            hsTipo,
            hsNumero,
            hsPrefix,
            hsFolio,
            hsNombreEquipo,
            hsPath,
            hsMime,
            hsActivo
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stI->execute([
        (int)$t['clId'],
        !empty($t['csId']) ? (int)$t['csId'] : null,
        (int)$t['tiId'],
        !empty($t['peId']) ? (int)$t['peId'] : null,
        $hsTipo,
        $hsNumero,
        $hsPrefix,
        $hsFolio,
        $hsNombreEquipo,
        $relPath,
        'application/pdf',
        1
    ]);

    $hsId = (int)$pdo->lastInsertId();
    if ($hsId <= 0) {
        throw new RuntimeException('No se pudo obtener hsId');
    }

    $pdo->commit();

    json_ok([
        'hsId' => $hsId,
        'hsFolio' => $hsFolio,
        'downloadUrl' => 'api/hoja_servicio_download.php?hsId=' . $hsId,
    ]);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_fail('No se pudo generar hoja: ' . $e->getMessage(), 500);
}

/* =========================
   Helpers
========================= */

function esc($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function is_data_png(?string $data): bool
{
    return is_string($data) && str_starts_with($data, 'data:image/png;base64,');
}

function build_hs_html(array $in, array $t, string $hsFolio): string
{
    $e  = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $nl = fn($v) => nl2br($e($v));

    $tipo = fn(string $k): bool => !empty($in['tipo_' . $k]);
    $res  = fn(string $k): bool => !empty($in['res_' . $k]);
    $status = (string)($in['status'] ?? 'cerrado');

    $fecha   = (string)($in['fecha'] ?? '');
    $noCaso  = (string)($in['no_caso'] ?? ('TI-' . (int)$t['tiId']));

    $clienteContacto = (string)($in['cliente_contacto'] ?? ($t['tiNombreContacto'] ?? ''));
    $clientePuesto   = (string)($in['cliente_puesto_area'] ?? '');
    $clienteRazon    = (string)($in['cliente_razon_social'] ?? ($t['clNombre'] ?? ''));
    $clienteDir      = (string)($in['cliente_direccion'] ?? (($t['csDireccion'] ?: ($t['clDireccion'] ?? ''))));
    $clienteCiudad   = (string)($in['cliente_ciudad_estado'] ?? '');
    $clienteTel      = (string)($in['cliente_telefono'] ?? ($t['tiNumeroContacto'] ?? ''));
    $clienteFax      = (string)($in['cliente_fax'] ?? '');
    $clienteEmail    = (string)($in['cliente_email'] ?? ($t['tiCorreoContacto'] ?? ''));

    $visFecha = (string)($in['visita_fecha'] ?? ($t['tiVisitaFecha'] ?? ''));
    $visHora  = (string)($in['visita_hora'] ?? ($t['tiVisitaHora'] ?? ''));

    $eqSO     = (string)($in['equipo_so'] ?? ($t['peSO'] ?? ''));
    $eqSoft   = (string)($in['equipo_software_respaldo'] ?? '');
    $eqSN     = (string)($in['equipo_sn'] ?? ($t['peSN'] ?? ''));
    $eqModelo = trim((string)($in['equipo_modelo_unidad'] ?? (trim(($t['eqModelo'] ?? '') . ' ' . ($t['eqVersion'] ?? '')))));
    $eqMarca  = (string)($in['equipo_marca_unidad'] ?? ($t['maNombre'] ?? ''));

    $problema    = trim((string)($in['problema'] ?? ''));
    $actividades = trim((string)($in['actividades'] ?? ''));
    $comentarios = trim((string)($in['comentarios'] ?? ''));

    if ($problema === '') $problema = ' ';
    if ($actividades === '') $actividades = ' ';
    if ($comentarios === '') $comentarios = ' ';

    $ings = $in['ingenieros'] ?? [];
    if (!is_array($ings)) $ings = [];
    $ingsTxt = !empty($ings) ? implode(', ', array_map('intval', $ings)) : '—';

    $sigIng = is_data_png($in['sig_ing_base64'] ?? null) ? (string)$in['sig_ing_base64'] : '';
    $sigCli = is_data_png($in['sig_cli_base64'] ?? null) ? (string)$in['sig_cli_base64'] : '';

    $sigIngImg = $sigIng
        ? '<img src="' . $e($sigIng) . '" class="sig-img" />'
        : '<div class="sig-placeholder"></div>';

    $sigCliImg = $sigCli
        ? '<img src="' . $e($sigCli) . '" class="sig-img" />'
        : '<div class="sig-placeholder"></div>';

    $chk = function (bool $on): string {
        return $on
            ? '<span class="check on">✓</span>'
            : '<span class="check"></span>';
    };

    return '
    <style>
        @page {
            margin: 10mm 12mm 12mm 12mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #111827;
        }

        .sheet {
            width: 100%;
        }

        .topbar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6mm;
        }

        .topbar td {
            border: 1px solid #111827;
            padding: 6px 8px;
            vertical-align: middle;
        }

        .brand {
            width: 18%;
            text-align: center;
            font-weight: 800;
            font-size: 18pt;
            background: #111827;
            color: #ffffff;
            letter-spacing: .5px;
        }

        .titlebox {
            width: 52%;
            text-align: center;
        }

        .title {
            font-size: 15pt;
            font-weight: 800;
            margin-bottom: 2px;
        }

        .subtitle {
            font-size: 8.8pt;
            color: #4b5563;
        }

        .meta {
            width: 30%;
            border-collapse: collapse;
        }

        .meta td {
            border: 1px solid #111827;
            padding: 4px 6px;
            font-size: 9pt;
        }

        .meta-label {
            font-weight: 700;
            background: #f3f4f6;
            width: 42%;
        }

        .section {
            margin-top: 4mm;
        }

        .section-title {
            background: #111827;
            color: #ffffff;
            padding: 6px 8px;
            font-size: 10pt;
            font-weight: 800;
            border: 1px solid #111827;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
        }

        .grid td {
            border: 1px solid #111827;
            padding: 6px 8px;
            vertical-align: top;
        }

        .label {
            font-size: 8.5pt;
            color: #374151;
            margin-bottom: 2px;
            font-weight: 700;
        }

        .value {
            font-size: 9.8pt;
            font-weight: 700;
            min-height: 14px;
        }

        .value-light {
            font-size: 9.6pt;
            font-weight: 400;
            min-height: 14px;
        }

        .block {
            border: 1px solid #111827;
            padding: 8px 10px;
            min-height: 34mm;
            white-space: pre-wrap;
            line-height: 1.35;
        }

        .block-sm {
            border: 1px solid #111827;
            padding: 8px 10px;
            min-height: 22mm;
            white-space: pre-wrap;
            line-height: 1.35;
        }

        .checks-table {
            width: 100%;
            border-collapse: collapse;
        }

        .checks-table td {
            border: 1px solid #111827;
            padding: 6px 8px;
            font-size: 9.5pt;
        }

        .check {
            display: inline-block;
            width: 12px;
            height: 12px;
            line-height: 11px;
            text-align: center;
            border: 1px solid #111827;
            margin-right: 6px;
            font-size: 9pt;
            font-weight: 800;
            vertical-align: middle;
        }

        .check.on {
            background: #111827;
            color: #ffffff;
        }

        .status-row {
            width: 100%;
            border-collapse: collapse;
        }

        .status-row td {
            border: 1px solid #111827;
            padding: 8px;
            font-size: 9.5pt;
        }

        .sign-table {
            width: 100%;
            border-collapse: collapse;
        }

        .sign-table td {
            border: 1px solid #111827;
            padding: 8px;
            vertical-align: top;
            height: 38mm;
        }

        .sig-box {
            height: 18mm;
            text-align: center;
        }

        .sig-img {
            max-width: 100%;
            max-height: 18mm;
            object-fit: contain;
        }

        .sig-placeholder {
            height: 18mm;
        }

        .sig-line {
            border-top: 1px solid #111827;
            margin-top: 4mm;
            padding-top: 2mm;
            font-size: 8.8pt;
            text-align: center;
            font-weight: 700;
        }

        .footer {
            margin-top: 6mm;
            border-top: 1px solid #111827;
            padding-top: 3mm;
            text-align: center;
            font-size: 8.5pt;
            color: #374151;
        }

        .tiny {
            font-size: 8.2pt;
            color: #4b5563;
        }
    </style>

    <div class="sheet">

        <table class="topbar">
            <tr>
                <td class="brand">MR</td>
                <td class="titlebox">
                    <div class="title">ORDEN DE SERVICIO</div>
                    <div class="subtitle">Formato MR SOS basado en el nuevo diseño operativo</div>
                </td>
                <td style="padding:0;">
                    <table class="meta">
                        <tr>
                            <td class="meta-label">Fecha</td>
                            <td>' . $e($fecha) . '</td>
                        </tr>
                        <tr>
                            <td class="meta-label">No. de Reporte Lab</td>
                            <td>' . $e($noCaso) . '</td>
                        </tr>
                        <tr>
                            <td class="meta-label">HS ID</td>
                            <td>' . $e($hsFolio) . '</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="section">
            <div class="section-title">Información del Cliente</div>
            <table class="grid">
                <tr>
                    <td width="28%">
                        <div class="label">Cliente / At´n</div>
                        <div class="value">' . $e($clienteContacto) . '</div>
                    </td>
                    <td width="22%">
                        <div class="label">Puesto / Area</div>
                        <div class="value">' . $e($clientePuesto) . '</div>
                    </td>
                    <td width="50%">
                        <div class="label">Razón Social</div>
                        <div class="value">' . $e($clienteRazon) . '</div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <div class="label">Dirección</div>
                        <div class="value-light">' . $e($clienteDir) . '</div>
                    </td>
                    <td>
                        <div class="label">Ciudad o Estado</div>
                        <div class="value">' . $e($clienteCiudad) . '</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">Teléfono</div>
                        <div class="value">' . $e($clienteTel) . '</div>
                    </td>
                    <td>
                        <div class="label">Fax</div>
                        <div class="value">' . $e($clienteFax) . '</div>
                    </td>
                    <td>
                        <div class="label">E-mail</div>
                        <div class="value">' . $e($clienteEmail) . '</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Datos del Servicio y del Equipo</div>
            <table class="grid">
                <tr>
                    <td width="22%">
                        <div class="label">Primera Visita</div>
                        <div class="value">' . $e($visFecha) . '</div>
                    </td>
                    <td width="15%">
                        <div class="label">Hora</div>
                        <div class="value">' . $e($visHora) . '</div>
                    </td>
                    <td width="25%">
                        <div class="label">Ingeniero(s)</div>
                        <div class="value-light">' . $e($ingsTxt) . '</div>
                    </td>
                    <td width="18%">
                        <div class="label">Sistema Operativo</div>
                        <div class="value">' . $e($eqSO) . '</div>
                    </td>
                    <td width="20%">
                        <div class="label">Software Respaldo</div>
                        <div class="value">' . $e($eqSoft) . '</div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <div class="label">Número de Serie</div>
                        <div class="value">' . $e($eqSN) . '</div>
                    </td>
                    <td colspan="2">
                        <div class="label">Modelo</div>
                        <div class="value">' . $e($eqModelo) . '</div>
                    </td>
                    <td>
                        <div class="label">Marca</div>
                        <div class="value">' . $e($eqMarca) . '</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Tipo de Servicio</div>
            <table class="checks-table">
                <tr>
                    <td>' . $chk($tipo('mantenimiento')) . 'Mantenimiento</td>
                    <td>' . $chk($tipo('reparacion')) . 'Reparación</td>
                    <td>' . $chk($tipo('garantia')) . 'Garantía</td>
                </tr>
                <tr>
                    <td>' . $chk($tipo('evaluacion')) . 'Evaluación de equipo</td>
                    <td>' . $chk($tipo('proyecto')) . 'Proyecto</td>
                    <td>' . $chk($tipo('software_demo')) . 'Software demo</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Descripción del Problema</div>
            <div class="block">' . $nl($problema) . '</div>
        </div>

        <div class="section">
            <div class="section-title">Actividades Realizadas</div>
            <div class="block">' . $nl($actividades) . '</div>
        </div>

        <div class="section">
            <div class="section-title">Estatus</div>
            <table class="status-row">
                <tr>
                    <td>' . $chk($status === 'cerrado') . 'Reporte Cerrado</td>
                    <td>' . $chk($status === 'pendiente') . 'Reporte Pendiente</td>
                    <td>' . $chk($status === 'cancelado') . 'Reporte Cancelado</td>
                    <td>' . $chk($status === 'reasignado') . 'Reasignado</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Resultado / Acciones</div>
            <table class="checks-table">
                <tr>
                    <td>' . $chk($res('reemplazo_refaccion')) . 'Reemplazo de Refacción</td>
                    <td>' . $chk($res('config_hw')) . 'Configuración de HW</td>
                    <td>' . $chk($res('config_sw')) . 'Configuración de SW</td>
                    <td>' . $chk($res('reinstalacion')) . 'Reinstalación</td>
                </tr>
                <tr>
                    <td>' . $chk($res('reparacion_sitio')) . 'Reparación en sitio</td>
                    <td>' . $chk($res('pendiente_partes')) . 'Pendiente por partes</td>
                    <td>' . $chk($res('software_respaldo')) . 'Software de respaldo</td>
                    <td>' . $chk($res('otros')) . 'Otros</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Nombre y Firma</div>
            <table class="sign-table">
                <tr>
                    <td width="50%">
                        <div class="sig-box">' . $sigIngImg . '</div>
                        <div class="sig-line">Nombre y Firma del Ingeniero</div>
                    </td>
                    <td width="50%">
                        <div class="sig-box">' . $sigCliImg . '</div>
                        <div class="sig-line">Nombre y Firma del Cliente</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Comentarios Adicionales</div>
            <div class="block-sm">' . $nl($comentarios) . '</div>
        </div>

        <div class="footer">
            <div><b>MR Solutions</b> · Orden de Servicio</div>
            <div class="tiny">Alhambra 813 Bis Col. Portales · Tels. 33.30.55.55 - 55.23.20.03 · Fax Ext. 205</div>
        </div>

    </div>
    ';
}
/* 

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

function require_composer_autoload(): void {
  $candidates = [
    __DIR__ . '/../../../../vendor/autoload.php', // vendor en raíz (tu caso)
    __DIR__ . '/../../../vendor/autoload.php',
  ];
  foreach ($candidates as $p) {
    if (file_exists($p)) { require_once $p; return; }
  }
  throw new RuntimeException('No se encontró vendor/autoload.php');
}
require_composer_autoload();

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

$pdo = db();
$in = read_json_body();

$tiId = isset($in['tiId']) ? (int)$in['tiId'] : 0;
if ($tiId <= 0) json_fail('tiId inválido', 400);

// 1) Cargar ticket base
$st = $pdo->prepare("
  SELECT t.tiId,t.clId,t.csId,t.peId,t.eqId,t.usIdIng,
         t.tiNombreContacto,t.tiNumeroContacto,t.tiCorreoContacto,
         t.tiDescripcion,t.tiDiagnostico,t.tiProceso,
         t.tiVisitaFecha,t.tiVisitaHora,
         c.clNombre,c.clDireccion,c.clTelefono,c.clCorreo,
         cs.csNombre,cs.csDireccion,
         e.eqModelo,e.eqVersion,
         m.maNombre,
         pe.peSN,pe.peSO
  FROM ticket_soporte t
  LEFT JOIN clientes c ON c.clId=t.clId
  LEFT JOIN cliente_sede cs ON cs.csId=t.csId
  LEFT JOIN equipos e ON e.eqId=t.eqId
  LEFT JOIN marca m ON m.maId=e.maId
  LEFT JOIN polizasequipo pe ON pe.peId=t.peId
  WHERE t.tiId=? LIMIT 1
");
$st->execute([$tiId]);
$t = $st->fetch();
if (!$t) json_fail('Ticket no existe', 404);

// 2) Folio HS-<tiId>-<timestamp>
$ts = (new DateTime('now'))->format('YmdHis');
$hsFolio = 'HS-' . $tiId . '-' . $ts;
$hsPrefix = 'HS';
$hsTipo = 'T';

// 3) Transacción
$pdo->beginTransaction();
try {
  // consecutivo
  $stN = $pdo->prepare("SELECT COALESCE(MAX(hsNumero),0) FROM hojas_servicio WHERE hsPrefix=? AND hsTipo=?");
  $stN->execute([$hsPrefix, $hsTipo]);
  $hsNumero = (int)$stN->fetchColumn() + 1;

  // 4) Paths
  $relDir = 'uploads/hojas_servicio/' . $tiId;

  // backend root => /backend
  $baseBackend = realpath(__DIR__ . '/../../..') ?: (__DIR__ . '/../../..');
  $absDir = rtrim($baseBackend, '/\\') . DIRECTORY_SEPARATOR . $relDir;

  if (!is_dir($absDir) && !mkdir($absDir, 0775, true)) {
    throw new RuntimeException('No se pudo crear directorio de uploads');
  }

  $fileName = 'hoja_servicio_' . $hsFolio . '.pdf';
  $relPath = $relDir . '/' . $fileName;
  $absPath = $absDir . DIRECTORY_SEPARATOR . $fileName;

  // 5) Render HTML
  $html = build_hs_html($in, $t, $hsFolio);

  // 6) PDF
  $templatePdf = (realpath(__DIR__ . '/../../..') ?: (__DIR__ . '/../../..'))
  . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'HOJA_DE_SERVICIO.pdf';

if (!is_file($templatePdf)) {
  throw new RuntimeException('No se encontró plantilla: ' . $templatePdf);
}

$mpdf = new \Mpdf\Mpdf([
  'mode' => 'utf-8',
  'format' => 'A4',
  'margin_left' => 0,
  'margin_right' => 0,
  'margin_top' => 0,
  'margin_bottom' => 0,
  'tempDir' => $tmp, // el que ya configuraste
]);

$mpdf->SetDisplayMode('fullpage');
$mpdf->showImageErrors = true;

// Esto pone la hoja original como “fondo” en cada página
$mpdf->SetDocTemplate($templatePdf, true);

// Escribimos SOLO overlays (posicionados)
$html = build_hs_overlay_html($in, $t, $hsFolio);
$mpdf->WriteHTML($html);

$mpdf->Output($absPath, \Mpdf\Output\Destination::FILE);

  // 7) Insert BD
  $hsNombreEquipo = trim(($t['eqModelo'] ?? '') . ' ' . ($t['eqVersion'] ?? ''));
  $stI = $pdo->prepare("
    INSERT INTO hojas_servicio
      (clId, csId, tiId, peId, hsTipo, hsNumero, hsPrefix, hsFolio, hsNombreEquipo, hsPath, hsMime, hsActivo)
    VALUES
      (?,   ?,   ?,   ?,   ?,     ?,       ?,       ?,      ?,             ?,      ?,     ?)
  ");
  $stI->execute([
    (int)$t['clId'],
    !empty($t['csId']) ? (int)$t['csId'] : null,
    (int)$t['tiId'],
    !empty($t['peId']) ? (int)$t['peId'] : null,
    $hsTipo,
    $hsNumero,
    $hsPrefix,
    $hsFolio,
    $hsNombreEquipo,
    $relPath,
    'application/pdf',
    1
  ]);

  $hsId = (int)$pdo->lastInsertId();
  if ($hsId <= 0) throw new RuntimeException('No se pudo obtener hsId');

  $pdo->commit();

  json_ok([
    'hsId' => $hsId,
    'hsFolio' => $hsFolio,
    'downloadUrl' => 'api/hoja_servicio_download.php?hsId=' . $hsId,
  ]);

} catch (Throwable $e) {
  $pdo->rollBack();
  json_fail('No se pudo generar hoja: ' . $e->getMessage(), 500);
}

// ---------------- helpers ----------------

function esc($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function is_data_png(?string $data): bool {
  return is_string($data) && str_starts_with($data, 'data:image/png;base64,');
}

function hs_chk(string $label, bool $on): string {
  return '<span class="check '.($on?'on':'').'"></span>'.esc($label).'&nbsp;&nbsp;&nbsp;';
}
function hs_radio(string $label, bool $on): string {
  return '<span class="check '.($on?'on':'').'"></span>'.esc($label).'&nbsp;&nbsp;&nbsp;';
}
function build_hs_overlay_html(array $in, array $t, string $hsFolio): string {

  // helpers
  $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
  $mm = fn($x) => rtrim(rtrim(number_format((float)$x, 2, '.', ''), '0'), '.') . 'mm';

  $posText = function($x,$y,$w,$h,$txt,$size=9,$bold=false) use ($mm,$e) {
    $fw = $bold ? '700' : '400';
    $txt = $e($txt);
    return "<div style=\"position:absolute;left:{$mm($x)};top:{$mm($y)};width:{$mm($w)};height:{$mm($h)};font-size:{$size}pt;font-weight:$fw;line-height:1.1;overflow:hidden;\">$txt</div>";
  };

  $posBox = function($x,$y,$on=false) use ($mm) {
    $bg = $on ? '#111827' : 'transparent';
    return "<div style=\"position:absolute;left:{$mm($x)};top:{$mm($y)};width:3.6mm;height:3.6mm;border:0.35mm solid #111827;background:$bg;\"></div>";
  };

  $posImg = function($x,$y,$w,$h,$src) use ($mm,$e) {
    if (!$src) return '';
    $src = $e($src);
    return "<img src=\"$src\" style=\"position:absolute;left:{$mm($x)};top:{$mm($y)};width:{$mm($w)};height:{$mm($h)};object-fit:contain;\" />";
  };

  $isPng = fn($d) => is_string($d) && str_starts_with($d, 'data:image/png;base64,');

  // Datos (usa tus reglas ya prellenadas)
  $fecha   = $in['fecha'] ?? '';
  $noCaso  = $in['no_caso'] ?? ('TI-'.$t['tiId']);
  $contacto= $in['cliente_contacto'] ?? ($t['tiNombreContacto'] ?? '');
  $razon   = $in['cliente_razon_social'] ?? ($t['clNombre'] ?? '');
  $dir     = $in['cliente_direccion'] ?? ($t['csDireccion'] ?: ($t['clDireccion'] ?? ''));
  $tel     = $in['cliente_telefono'] ?? ($t['tiNumeroContacto'] ?? '');
  $email   = $in['cliente_email'] ?? ($t['tiCorreoContacto'] ?? '');

  $so      = $in['equipo_so'] ?? ($t['peSO'] ?? '');
  $soft    = $in['equipo_software_respaldo'] ?? '';
  $sn      = $in['equipo_sn'] ?? ($t['peSN'] ?? '');
  $modelo  = $in['equipo_modelo_unidad'] ?? trim(($t['eqModelo'] ?? '').' '.($t['eqVersion'] ?? ''));
  $marca   = $in['equipo_marca_unidad'] ?? ($t['maNombre'] ?? '');

  $vFecha  = $in['visita_fecha'] ?? ($t['tiVisitaFecha'] ?? '');
  $vHora   = $in['visita_hora'] ?? ($t['tiVisitaHora'] ?? '');

  $problema = $in['problema'] ?? '';
  $act      = $in['actividades'] ?? '';
  $coment   = $in['comentarios'] ?? '';

  $status   = $in['status'] ?? 'cerrado';

  $tipo = fn($k) => !empty($in['tipo_'.$k]);
  $res  = fn($k) => !empty($in['res_'.$k]);

  $sigIng = $isPng($in['sig_ing_base64'] ?? null) ? $in['sig_ing_base64'] : '';
  $sigCli = $isPng($in['sig_cli_base64'] ?? null) ? $in['sig_cli_base64'] : '';

  // IMPORTANTE:
  // Estas coordenadas SON base. Se afinan con prueba/error 2–3 ajustes y queda 1:1.
  // A4 en mm: ancho 210, alto 297.

  $out = "<div style=\"position:relative;width:210mm;height:297mm;\">";

  // TOP: Fecha / No Caso / HS ID
  $out .= $posText(18, 20, 50, 6, $fecha, 10);
  $out .= $posText(78, 20, 50, 6, $noCaso, 10);
  $out .= $posText(140, 20, 60, 6, $hsFolio, 10, true);

  // Cliente
  $out .= $posText(18, 43, 55, 6, $contacto);
  $out .= $posText(140, 43, 60, 6, $razon);
  $out .= $posText(18, 56, 122, 10, $dir);
  $out .= $posText(18, 72, 55, 6, $tel);
  $out .= $posText(140, 72, 60, 6, $email);

  // Tipo (checkboxes) -> ajusta X/Y según tu plantilla
  $out .= $posBox(20, 90, $tipo('garantia'));
  $out .= $posBox(55, 90, $tipo('evaluacion'));
  $out .= $posBox(95, 90, $tipo('proyecto'));
  $out .= $posBox(130, 90, $tipo('mantenimiento'));
  $out .= $posBox(165, 90, $tipo('reparacion'));
  $out .= $posBox(190, 90, $tipo('software_demo'));

  // Equipo
  $out .= $posText(18, 110, 60, 6, $so);
  $out .= $posText(78, 110, 60, 6, $soft);
  $out .= $posText(140, 110, 60, 6, $sn);
  $out .= $posText(18, 123, 122, 6, $modelo);
  $out .= $posText(140, 123, 60, 6, $marca);

  // Primera visita
  $out .= $posText(18, 142, 60, 6, $vFecha);
  $out .= $posText(110, 142, 40, 6, $vHora);

  // Problema / Actividades / Comentarios (bloques grandes)
  $out .= $posText(18, 160, 182, 22, $problema, 9);
  $out .= $posText(18, 186, 182, 22, $act, 9);

  // Estatus (checkbox style)
  $out .= $posBox(20, 212, $status==='cerrado');
  $out .= $posBox(70, 212, $status==='pendiente');
  $out .= $posBox(125, 212, $status==='cancelado');
  $out .= $posBox(170, 212, $status==='reasignado');

  // Resultado
  $out .= $posBox(20, 230, $res('reemplazo_refaccion'));
  $out .= $posBox(20, 237, $res('config_hw'));
  $out .= $posBox(20, 244, $res('config_sw'));
  $out .= $posBox(20, 251, $res('reinstalacion'));
  $out .= $posBox(110, 230, $res('reparacion_sitio'));
  $out .= $posBox(110, 237, $res('pendiente_partes'));
  $out .= $posBox(110, 244, $res('software_respaldo'));
  $out .= $posBox(110, 251, $res('otros'));

  $out .= $posText(18, 258, 182, 18, $coment, 9);

  // Firmas (imágenes)
  $out .= $posImg(25, 279, 70, 14, $sigIng);
  $out .= $posImg(120, 279, 70, 14, $sigCli);

  $out .= "</div>";
  return $out;
}
function build_hs_html(array $in, array $t, string $hsFolio): string {

  $tipo = fn(string $k): bool => !empty($in['tipo_'.$k]);
  $res  = fn(string $k): bool => !empty($in['res_'.$k]);
  $status = (string)($in['status'] ?? 'cerrado');

  $noCaso = (string)($in['no_caso'] ?? ('TI-'.(int)$t['tiId']));
  $fecha  = (string)($in['fecha'] ?? '');

  $clienteContacto = (string)($in['cliente_contacto'] ?? ($t['tiNombreContacto'] ?? ''));
  $clienteEmail    = (string)($in['cliente_email'] ?? ($t['tiCorreoContacto'] ?? ''));
  $clienteTel      = (string)($in['cliente_telefono'] ?? ($t['tiNumeroContacto'] ?? ''));
  $clienteRazon    = (string)($in['cliente_razon_social'] ?? ($t['clNombre'] ?? ''));

  $direccion = (string)($in['cliente_direccion'] ?? (($t['csDireccion'] ?: ($t['clDireccion'] ?? ''))));
  $ciudad    = (string)($in['cliente_ciudad_estado'] ?? '');

  $eqModelo = trim((string)($in['equipo_modelo_unidad'] ?? (trim(($t['eqModelo'] ?? '').' '.($t['eqVersion'] ?? '')))));
  $eqMarca  = (string)($in['equipo_marca_unidad'] ?? ($t['maNombre'] ?? ''));
  $eqSN     = (string)($in['equipo_sn'] ?? ($t['peSN'] ?? ''));
  $eqSO     = (string)($in['equipo_so'] ?? ($t['peSO'] ?? ''));
  $eqSoft   = (string)($in['equipo_software_respaldo'] ?? '');

  $visFecha = (string)($in['visita_fecha'] ?? ($t['tiVisitaFecha'] ?? ''));
  $visHora  = (string)($in['visita_hora'] ?? ($t['tiVisitaHora'] ?? ''));

  $problema    = (string)($in['problema'] ?? '');
  $actividades = (string)($in['actividades'] ?? '');
  $comentarios = (string)($in['comentarios'] ?? '');

  $ings = $in['ingenieros'] ?? [];
  if (!is_array($ings)) $ings = [];
  $ingsTxt = !empty($ings) ? implode(', ', array_map('intval', $ings)) : '—';

  $sigIng = is_data_png($in['sig_ing_base64'] ?? null) ? (string)$in['sig_ing_base64'] : '';
  $sigCli = is_data_png($in['sig_cli_base64'] ?? null) ? (string)$in['sig_cli_base64'] : '';
  $sigIngImg = $sigIng ? '<img src="'.esc($sigIng).'" style="height:70px;">' : '<div style="height:70px;"></div>';
  $sigCliImg = $sigCli ? '<img src="'.esc($sigCli).'" style="height:70px;">' : '<div style="height:70px;"></div>';

  return '
  <style>
    body { font-family: sans-serif; font-size: 11px; color:#111827; }
    .title { font-size: 16px; font-weight: 800; text-align:center; margin: 4px 0 10px; }
    .box { border:1px solid #111827; padding:8px; border-radius:6px; }
    .grid { width:100%; border-collapse:collapse; }
    .grid td { border:1px solid #111827; padding:5px 7px; vertical-align:top; }
    .check { display:inline-block; border:1px solid #111827; width:10px; height:10px; margin-right:6px; vertical-align:middle; }
    .on { background:#111827; }
    .hr { height:10px; }
    .signline { border-top:1px solid #111827; margin-top:6px; }
  </style>

  <div class="title">Orden de Servicio</div>

  <table class="grid">
    <tr>
      <td width="30%"><b>Fecha</b><br>'.esc($fecha).'</td>
      <td width="30%"><b>No. de Caso</b><br>'.esc($noCaso).'</td>
      <td width="40%"><b>HS ID</b><br>'.esc($hsFolio).'</td>
    </tr>
  </table>

  <div class="hr"></div>

  <div class="box">
    <b>Información del Cliente</b><br><br>
    <table class="grid">
      <tr>
        <td width="33%"><b>Contacto</b><br>'.esc($clienteContacto).'</td>
        <td width="33%"><b>Puesto / Área</b><br>'.esc($in['cliente_puesto_area'] ?? '').'</td>
        <td width="34%"><b>Razón Social</b><br>'.esc($clienteRazon).'</td>
      </tr>
      <tr>
        <td colspan="2"><b>Dirección</b><br>'.esc($direccion).'</td>
        <td><b>Ciudad o Estado</b><br>'.esc($ciudad).'</td>
      </tr>
      <tr>
        <td><b>Teléfono</b><br>'.esc($clienteTel).'</td>
        <td><b>Fax</b><br>'.esc($in['cliente_fax'] ?? '').'</td>
        <td><b>E-mail</b><br>'.esc($clienteEmail).'</td>
      </tr>
    </table>

    <div class="hr"></div>

    <b>Tipo</b><br>
    '.hs_chk('Mantenimiento', $tipo('mantenimiento')).'
    '.hs_chk('Reparación', $tipo('reparacion')).'
    '.hs_chk('Garantía', $tipo('garantia')).'
    '.hs_chk('Evaluación de equipo', $tipo('evaluacion')).'
    '.hs_chk('Proyecto', $tipo('proyecto')).'
    '.hs_chk('Software demo', $tipo('software_demo')).'
  </div>

  <div class="hr"></div>

  <div class="box">
    <b>Datos del equipo</b><br><br>
    <table class="grid">
      <tr>
        <td width="33%"><b>Sistema Operativo</b><br>'.esc($eqSO).'</td>
        <td width="33%"><b>Software Respaldo</b><br>'.esc($eqSoft).'</td>
        <td width="34%"><b>Número Serie</b><br>'.esc($eqSN).'</td>
      </tr>
      <tr>
        <td colspan="2"><b>Modelo Unidad / Librería</b><br>'.esc($eqModelo).'</td>
        <td><b>Marca Unidad / Librería</b><br>'.esc($eqMarca).'</td>
      </tr>
    </table>
  </div>

  <div class="hr"></div>

  <table class="grid">
    <tr>
      <td width="50%"><b>Primera Visita - Fecha</b><br>'.esc($visFecha).'</td>
      <td width="50%"><b>Hora</b><br>'.esc($visHora).'</td>
    </tr>
    <tr>
      <td colspan="2"><b>Ingeniero(s)</b><br>'.esc($ingsTxt).'</td>
    </tr>
  </table>

  <div class="hr"></div>

  <div class="box">
    <b>Descripción del Problema</b><br><br>
    '.nl2br(esc($problema)).'
  </div>

  <div class="hr"></div>

  <div class="box">
    <b>Actividades Realizadas</b><br><br>
    '.nl2br(esc($actividades)).'
  </div>

  <div class="hr"></div>

  <div class="box">
    <b>Estatus</b><br><br>
    '.hs_radio('Reporte Cerrado', $status==='cerrado').'
    '.hs_radio('Reporte Pendiente', $status==='pendiente').'
    '.hs_radio('Reporte Cancelado', $status==='cancelado').'
    '.hs_radio('Reasignado', $status==='reasignado').'
  </div>

  <div class="hr"></div>

  <div class="box">
    <b>Resultado / Acciones</b><br><br>
    '.hs_chk('Reemplazo de Refacción', $res('reemplazo_refaccion')).'
    '.hs_chk('Configuración de HW', $res('config_hw')).'
    '.hs_chk('Configuración de SW', $res('config_sw')).'
    '.hs_chk('Reinstalación', $res('reinstalacion')).'
    '.hs_chk('Reparación en sitio', $res('reparacion_sitio')).'
    '.hs_chk('Pendiente por partes', $res('pendiente_partes')).'
    '.hs_chk('Software de respaldo', $res('software_respaldo')).'
    '.hs_chk('Otros', $res('otros')).'
  </div>

  <div class="hr"></div>

  <div class="box">
    <b>Comentarios Adicionales</b><br><br>
    '.nl2br(esc($comentarios)).'
  </div>

  <div class="hr"></div>

  <table class="grid">
    <tr>
      <td width="50%">
        <b>Firma del Ingeniero</b><br><br>
        '.$sigIngImg.'
        <div class="signline"></div>
      </td>
      <td width="50%">
        <b>Firma del Cliente</b><br><br>
        '.$sigCliImg.'
        <div class="signline"></div>
      </td>
    </tr>
  </table>
  ';
} */