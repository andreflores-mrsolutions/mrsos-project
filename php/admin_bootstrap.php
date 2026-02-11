<?php
declare(strict_types=1);

/**
 * Admin Bootstrap (MR)
 * - Exige login
 * - Exige rol MR
 * - Headers no-store
 * - CSRF disponible para JS
 */

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/csrf.php';

no_store();
require_login();

/**
 * Roles MR permitidos.
 * Si quieres restringir en una página específica, antes de incluir este archivo
 * define $MR_ALLOWED_ROLES como array.
 *
 * Ejemplo:
 *   <?php $MR_ALLOWED_ROLES = ['MRSA']; require_once __DIR__.'/../php/admin_bootstrap.php'; ?>
 */
$MR_ALLOWED_ROLES = $MR_ALLOWED_ROLES ?? ['MRSA','MRA','MRV', 'CLI'];
require_usRol($MR_ALLOWED_ROLES);

$csrf = csrf_token();

/** Imprime JS de bootstrap (CSRF + session) */
function admin_print_js_bootstrap(): void {
  $csrf = csrf_token();
  $session = [
    'usId' => (int)($_SESSION['usId'] ?? 0),
    'usRol' => (string)($_SESSION['usRol'] ?? ''),
    'clId' => isset($_SESSION['clId']) ? (int)$_SESSION['clId'] : null,
    'usUsername' => (string)($_SESSION['usUsername'] ?? ''),
  ];

  echo "<script>\n";
  echo "window.MRS_CSRF = " . json_encode($csrf) . ";\n";
  echo "window.SESSION  = " . json_encode($session) . ";\n";
  echo "</script>\n";
}
