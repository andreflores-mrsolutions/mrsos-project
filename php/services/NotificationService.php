<?php
// php/services/NotificationService.php
require_once __DIR__ . "/FcmService.php";

class NotificationService
{
  private mysqli $conectar;
  private FcmService $fcm;

  public function __construct(mysqli $conectar, FcmService $fcm)
  {
    $this->conectar = $conectar;
    $this->fcm  = $fcm;
  }

  private function getUserTokens(int $usId, ?string $platform = null): array
  {
    if ($platform) {
      $stmt = $this->conectar->prepare("SELECT token FROM user_push_devices WHERE usId=? AND platform=?");
      $stmt->bind_param("is", $usId, $platform);
    } else {
      $stmt = $this->conectar->prepare("SELECT token FROM user_push_devices WHERE usId=?");
      $stmt->bind_param("i", $usId);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $tokens = [];
    while ($row = $res->fetch_assoc()) $tokens[] = $row['token'];
    return $tokens;
  }

  // AJUSTA ESTE método a tu BD real:
  private function getTicketActors(int $tiId): array
  {
    // Debes mapear aquí tus columnas reales:
    // - usIdCliente
    // - usIdIng (ingeniero asignado)
    // - etc.
    $stmt = $this->conectar->prepare("SELECT usId, usIdIng FROM ticket_soporte WHERE tiId=?");
    $stmt->bind_param("i", $tiId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if (!$row) throw new Exception("Ticket no encontrado");

    return [
      'cliente' => intval($row['usId']),
      'ing'     => intval($row['usIdIng']),
    ];
  }

  public function dispatch(string $action, array $ctx, string $folio): array
  {
    // ctx típico: ['tiId'=>123, 'byUsId'=>1, ...]
    switch ($action) {

      case 'solicitar_logs': {
          $tiId = intval($ctx['tiId'] ?? 0);
          if (!$tiId) throw new Exception("tiId requerido");

          $folio = $folio ?? '';
          if (!$folio) throw new Exception("folio requerido");

          $actors = $this->getTicketActors($tiId);
          $usIdCliente = $actors['cliente'];

          $title = "MR SOS • Solicitud de logs";
          $body  = "Por favor sube los logs del ticket $folio para continuar el diagnóstico.";

          $tokens = $this->getUserTokens($usIdCliente);
          return $this->fcm->sendToTokens($tokens, $title, $body, [
            'type' => 'solicitar_logs',
            'data' => [
              'type' => 'solicitar_logs',
              'tiId' => (string)$tiId,
              'folio' => (string)$folio
            ]
          ]);
        }

      case 'cambio_estado': {
          $tiId = intval($ctx['tiId'] ?? 0);
          $estado = trim(strval($ctx['estado'] ?? ''));
          if (!$tiId || $estado === '') throw new Exception("tiId y estado requeridos");

          $folio = $folio ?? '';
          if (!$folio) throw new Exception("folio requerido");

          $actors = $this->getTicketActors($tiId);
          $usIdCliente = $actors['cliente'];

          $title = "MR SOS • Actualización de ticket";
          $body  = "Tu ticket #$folio cambió a: $estado";

          $tokens = $this->getUserTokens($usIdCliente);
          return $this->fcm->sendToTokens($tokens, $title, $body, [
            'type' => 'cambio_estado',
            'data' => [
              'type' => 'cambio_estado',
              'tiId' => (string)$tiId,
              'folio' => (string)$folio,
              'estado' => $estado,
            ]
          ]);
        }

      default:
        throw new Exception("Acción no soportada: $action");
    }
  }
}
