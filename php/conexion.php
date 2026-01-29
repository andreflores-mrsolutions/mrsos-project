<?php
// Create connection
$conectar = mysqli_connect("localhost", "u140302554_mrsos", "MRsolutions552312#$", "u140302554_mrsos");
// Check connection
if (!$conectar) {
    die("Connection failed: " . mysqli_connect_error());
}

/**
 * Configuración global de MR SOS Web Push
 * ------------------------------------------------------
 * Guarda tus claves VAPID y la configuración de conexión
 * a la base de datos.
 */

date_default_timezone_set('America/Mexico_City');
// require_once __DIR__.'/db.php';
  // $DB_HOST = 'localhost';
  // $DB_NAME = 'u140302554_mrsos';
  // $DB_USER = 'u140302554_mrsos';
  // $DB_PASS = 'MRsolutions552312#$';
/* === CONEXIÓN A BD === */
define('DB_HOST', 'localhost');
define('DB_NAME', 'u140302554_mrsos');
define('DB_USER', 'u140302554_mrsos');
define('DB_PASS', 'MRsolutions552312#$');
define('DB_CHARSET', 'utf8mb4');

/* === VAPID KEYS === */
define('VAPID_PUBLIC',  'BA9W1ScrtfSHTf4mxHTgjg2w6Lx1Y4gKdzmxIJTGvxsT5bYg-gRovppNegyBt7LMMV8LzWM8KI3Y7OA-Y5bYpRM');
define('VAPID_PRIVATE', 'xeMyAp0nFOwBJPlG6Uq433XdkBI-35zTL1IJDUn3400');
define('VAPID_SUBJECT', 'mailto:soporte@mrsolution.com.mx');

/**
 * Devuelve una conexión PDO lista para usar
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

/**
 * Helper rápido para enviar notificaciones Web Push
 */
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Envía una notificación a un usuario (por su usId)
 */
function send_webpush_to_user(int $usId, string $title, string $body, string $url = '/admin/'): array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT endpoint, p256dh, auth FROM webpush_subscriptions WHERE usId = :u");
    $stmt->execute([':u' => $usId]);
    $subs = $stmt->fetchAll();

    if (!$subs) {
        return ['success' => false, 'message' => 'Sin suscripciones registradas'];
    }

    $webPush = new WebPush([
        'VAPID' => [
            'subject'    => VAPID_SUBJECT,
            'publicKey'  => VAPID_PUBLIC,
            'privateKey' => VAPID_PRIVATE,
        ],
    ]);

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $url,
    ]);

    $ok = 0; $fail = 0;
    foreach ($subs as $row) {
        $subscription = Subscription::create([
            'endpoint' => $row['endpoint'],
            'keys'     => ['p256dh' => $row['p256dh'], 'auth' => $row['auth']],
        ]);
        $result = $webPush->sendOneNotification($subscription, $payload);
        if ($result->isSuccess()) $ok++; else $fail++;
    }

    return ['success' => true, 'sent' => $ok, 'failed' => $fail];
}
