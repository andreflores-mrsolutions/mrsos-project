<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usId']) || empty($_SESSION['usRol'])) {
    echo json_encode([
        'success' => false,
        'error'   => 'No autenticado'
    ]);
    exit;
}

require 'conexion.php'; // $conectar (mysqli)

$usId = (int)$_SESSION['usId'];
$rol  = $_SESSION['usRol'];

// Roles internos MR
$isMR = in_array($rol, ['MRA', 'MRV', 'MRSA'], true);

try {

    // ==============================
    // 1) Caso MR: ve todos los tickets (con filtros opcionales)
    // ==============================
    if ($isMR) {
        $sql = "
            SELECT 
                t.tiId,
                t.tiEstatus,
                t.tiProceso,
                t.tiTipoTicket,
                t.tiExtra,
                t.tiVisita,
                t.tiVisitaFecha,
                t.tiVisitaHora,
                t.tiVisitaEstado,
                t.tiVisitaDuracionMins,
                t.tiVisitaModo,
                t.tiVisitaAutorNombre,
                t.tiAccesoRequiereDatos,
                t.tiAccesoExtraTexto,
                t.tiAccesoFolio,
                t.tiAccesoSoportePath,
                t.tiMeetFecha,
                t.tiMeetHora,
                t.tiMeetPlataforma,
                t.tiMeetEnlace,
                t.tiMeetModo,
                t.tiMeetEstado,
                t.tiMeetAutorNombre,
                t.tiNivelCriticidad,
                t.tiFechaCreacion,
                t.tiFolioEntrada,
                t.tiFolioArchivo,
                t.tiFolioCreadoEn,
                t.tiFolioCreadoPor,
                cs.csId,
                cs.csNombre,
                c.clNombre,
                e.eqModelo,
                e.eqVersion,
                m.maNombre,
                pe.peSN
            FROM ticket_soporte t
            JOIN cliente_sede cs ON cs.csId = t.csId
            JOIN clientes c      ON c.clId  = t.clId
            JOIN polizasequipo pe ON pe.peId = t.peId
            JOIN equipos e       ON e.eqId  = t.eqId
            JOIN marca m         ON m.maId  = e.maId
            WHERE t.estatus = 'Activo'
        ";

        $types  = '';
        $params = [];

        if (!empty($_GET['clId'])) {
            $sql     .= " AND t.clId = ?";
            $types   .= "i";
            $params[] = (int)$_GET['clId'];
        }
        if (!empty($_GET['csId'])) {
            $sql     .= " AND t.csId = ?";
            $types   .= "i";
            $params[] = (int)$_GET['csId'];
        }

        $sql .= " ORDER BY c.clNombre, cs.csNombre, t.tiId DESC";

        $stmt = $conectar->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $res = $stmt->get_result();
    } else {

        // ==========================================
        // 2) Caso CLIENTE: usar usuario_cliente_rol
        // ==========================================

        // 2.1 Leer roles del usuario
        $stmt = $conectar->prepare("
            SELECT ucrId, clId, czId, csId, ucrRol
            FROM usuario_cliente_rol
            WHERE usId = ?
              AND ucrEstatus = 'Activo'
        ");
        $stmt->bind_param('i', $usId);
        $stmt->execute();
        $rolesRes = $stmt->get_result();

        if ($rolesRes->num_rows === 0) {
            echo json_encode([
                'success' => true,
                'sedes'   => [] // no tiene alcance
            ]);
            exit;
        }

        $adminGlobalClIds = [];
        $adminZonaCzIds   = [];
        $directCsIds      = [];

        while ($r = $rolesRes->fetch_assoc()) {
            $ucrRol = $r['ucrRol'];
            $clId   = (int)$r['clId'];
            $czId   = $r['czId'] !== null ? (int)$r['czId'] : null;
            $csId   = $r['csId'] !== null ? (int)$r['csId'] : null;

            if ($ucrRol === 'ADMIN_GLOBAL') {
                $adminGlobalClIds[] = $clId;
            } elseif ($ucrRol === 'ADMIN_ZONA' && $czId !== null) {
                $adminZonaCzIds[] = $czId;
            } elseif (in_array($ucrRol, ['ADMIN_SEDE', 'USUARIO', 'VISOR'], true) && $csId !== null) {
                $directCsIds[] = $csId;
            }
        }

        // 2.2 Obtener csId reales a partir de esos roles
        $csIds = [];

        // ADMIN_GLOBAL → todas las sedes de esos clientes
        if (!empty($adminGlobalClIds)) {
            $adminGlobalClIds = array_values(array_unique($adminGlobalClIds));
            $placeholders = implode(',', array_fill(0, count($adminGlobalClIds), '?'));
            $sql = "SELECT csId FROM cliente_sede WHERE clId IN ($placeholders)";
            $stmt = $conectar->prepare($sql);
            $types = str_repeat('i', count($adminGlobalClIds));
            $stmt->bind_param($types, ...$adminGlobalClIds);
            $stmt->execute();
            $tmp = $stmt->get_result();
            while ($row = $tmp->fetch_assoc()) {
                $csIds[] = (int)$row['csId'];
            }
        }

        // ADMIN_ZONA → todas las sedes de esas zonas
        if (!empty($adminZonaCzIds)) {
            $adminZonaCzIds = array_values(array_unique($adminZonaCzIds));
            $placeholders = implode(',', array_fill(0, count($adminZonaCzIds), '?'));
            $sql = "SELECT csId FROM cliente_sede WHERE czId IN ($placeholders)";
            $stmt = $conectar->prepare($sql);
            $types = str_repeat('i', count($adminZonaCzIds));
            $stmt->bind_param($types, ...$adminZonaCzIds);
            $stmt->execute();
            $tmp = $stmt->get_result();
            while ($row = $tmp->fetch_assoc()) {
                $csIds[] = (int)$row['csId'];
            }
        }

        // ADMIN_SEDE / USUARIO / VISOR → sedes directas
        if (!empty($directCsIds)) {
            foreach ($directCsIds as $id) {
                $csIds[] = (int)$id;
            }
        }

        $csIds = array_values(array_unique($csIds));

        if (empty($csIds)) {
            echo json_encode([
                'success' => true,
                'sedes'   => []
            ]);
            exit;
        }

        // 2.3 Traer tickets SOLO de esas sedes
        $placeholders = implode(',', array_fill(0, count($csIds), '?'));

        $sql = "
            SELECT 
                t.tiId,
                t.tiEstatus,
                t.tiProceso,
                t.tiTipoTicket,
                t.tiExtra,
                t.tiVisita,
                t.tiVisitaFecha,
                t.tiVisitaHora,
                t.tiVisitaEstado,
                t.tiVisitaDuracionMins,
                t.tiVisitaModo,
                t.tiVisitaAutorNombre,
                t.tiAccesoRequiereDatos,
                t.tiAccesoExtraTexto,
                t.tiAccesoFolio,
                t.tiAccesoSoportePath,
                t.tiMeetFecha,
                t.tiMeetHora,
                t.tiMeetPlataforma,
                t.tiMeetEnlace,
                t.tiMeetModo,
                t.tiMeetEstado,
                t.tiMeetAutorNombre,
                t.tiNivelCriticidad,
                t.tiFechaCreacion,
                t.tiFolioEntrada,
                t.tiFolioArchivo,
                t.tiFolioCreadoEn,
                t.tiFolioCreadoPor,
                cs.csId,
                cs.csNombre,
                c.clNombre,
                e.eqModelo,
                e.eqVersion,
                m.maNombre,
                pe.peSN
            FROM ticket_soporte t
            JOIN cliente_sede cs ON cs.csId = t.csId
            JOIN clientes c      ON c.clId  = t.clId
            JOIN polizasequipo pe ON pe.peId = t.peId
            JOIN equipos e       ON e.eqId  = t.eqId
            JOIN marca m         ON m.maId  = e.maId
            WHERE t.estatus = 'Activo' AND t.tiEstatus != 'Cerrado'
              AND cs.csId IN ($placeholders)
            ORDER BY c.clNombre, cs.csNombre, t.tiId DESC
        ";

        $stmt = $conectar->prepare($sql);
        $types = str_repeat('i', count($csIds));
        $stmt->bind_param($types, ...$csIds);
        $stmt->execute();
        $res = $stmt->get_result();
    }
    // Helper prefijo 3 letras
    function clPrefix(string $name): string
    {
        $name = strtoupper($name);
        $name = preg_replace('/[^A-Z]/', '', $name ?? '');
        $p = substr($name, 0, 3);
        return $p !== '' ? $p : 'CLI';
    }

    // ============================
    // 3) Armar respuesta agrupada por sede
    // ============================
    $sedes = [];

    while ($row = $res->fetch_assoc()) {
        $csId = (int)$row['csId'];
        if (!isset($sedes[$csId])) {
            $sedes[$csId] = [
                'csId'     => $csId,
                'csNombre' => $row['csNombre'],
                'clNombre' => $row['clNombre'],
                'tickets'  => []
            ];
        }
        $row['folio'] = clPrefix((string)($row['clNombre'] ?? '')) . '-' . (string)($row['tiId'] ?? '');


        $sedes[$csId]['tickets'][] = [
            'tiId'                => (int)$row['tiId'],
            'tiEstatus'           => $row['tiEstatus'],
            'tiProceso'           => $row['tiProceso'],
            'tiTipoTicket'        => $row['tiTipoTicket'],
            'tiExtra'             => $row['tiExtra'],
            'tiNivelCriticidad'   => $row['tiNivelCriticidad'],
            'tiFechaCreacion'     => $row['tiFechaCreacion'],

            'folio'               => $row['folio'],

            // VISITA
            'tiVisita'            => $row['tiVisita'],
            'tiVisitaFecha'       => $row['tiVisitaFecha'],
            'tiVisitaHora'        => $row['tiVisitaHora'],
            'tiVisitaEstado'      => $row['tiVisitaEstado'],
            'tiVisitaDuracionMins' => $row['tiVisitaDuracionMins'],
            'tiVisitaModo'        => $row['tiVisitaModo'],
            'tiVisitaAutorNombre' => $row['tiVisitaAutorNombre'],
            'tiAccesoRequiereDatos' => (int)$row['tiAccesoRequiereDatos'],
            'tiAccesoExtraTexto'    => $row['tiAccesoExtraTexto'],
            'tiAccesoFolio'         => $row['tiAccesoFolio'],
            'tiAccesoSoportePath'   => $row['tiAccesoSoportePath'],

            // FOLIO ENTRADA
            'tiFolioEntrada'      => $row['tiFolioEntrada'],
            'tiFolioArchivo'      => $row['tiFolioArchivo'],
            'tiFolioCreadoEn'     => $row['tiFolioCreadoEn'],
            'tiFolioCreadoPor'    => $row['tiFolioCreadoPor'],

            // MEET
            'tiMeetFecha'        => $row['tiMeetFecha'],
            'tiMeetHora'         => $row['tiMeetHora'],
            'tiMeetPlataforma'   => $row['tiMeetPlataforma'],
            'tiMeetEnlace'       => $row['tiMeetEnlace'],
            'tiMeetModo'         => $row['tiMeetModo'],
            'tiMeetEstado'       => $row['tiMeetEstado'],
            'tiMeetAutorNombre'  => $row['tiMeetAutorNombre'],

            //Equipo
            'eqModelo'     => $row['eqModelo'],
            'eqVersion'    => $row['eqVersion'],
            'maNombre'     => $row['maNombre'],
            'peSN'         => $row['peSN'],
        ];
    }

    echo json_encode([
        'success' => true,
        'sedes'   => array_values($sedes),
    ]);
    exit;
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Error al obtener los tickets por sede',
        'detail'  => $e->getMessage(),
    ]);
    exit;
}
