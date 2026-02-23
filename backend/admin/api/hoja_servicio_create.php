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
  $tmp = (realpath(__DIR__ . '/../../..') ?: (__DIR__ . '/../../..'))
     . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'mpdf';

if (!is_dir($tmp) && !mkdir($tmp, 0775, true)) {
  throw new RuntimeException('No se pudo crear tempDir de mPDF');
}
if (!is_writable($tmp)) {
  throw new RuntimeException('tempDir de mPDF no es escribible: ' . $tmp);
}

$mpdf = new Mpdf([
  'mode' => 'utf-8',
  'format' => 'A4',
  'margin_left' => 10,
  'margin_right' => 10,
  'margin_top' => 10,
  'margin_bottom' => 10,
  'tempDir' => $tmp,  // ✅ clave
]);
  $mpdf->WriteHTML($html);
  $mpdf->Output($absPath, Destination::FILE);

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
}