<?php
putenv('OPENSSL_CONF=C:\\xampp\\apache\\bin\\openssl.cnf'); // <- ruta doble backslash
require __DIR__.'/vendor/autoload.php';

use Minishlink\WebPush\VAPID;
$keys = VAPID::createVapidKeys();
print_r($keys);


