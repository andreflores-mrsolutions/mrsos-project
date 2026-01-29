<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['auth']) || empty($_SESSION['usId'])) {
  http_response_code(401);
  echo json_encode([
    "success" => false,
    "message" => "No autenticado"
  ]);
  exit;
}

echo json_encode([
  "success" => true,
  "usId" => $_SESSION['usId'],
  "userName" => $_SESSION['user'] ?? '',
  "clId" => $_SESSION['clId'] ?? null,
  "rol" => $_SESSION['rol'] ?? null,
  "pcId" => $_SESSION['pcId'] ?? null,
  "usNombre" => $_SESSION['usNombre'] ?? '',
  "usAPaterno" => $_SESSION['usAPaterno'] ?? '',
  "usAMaterno" => $_SESSION['usAMaterno'] ?? '',
  "usCorreo" => $_SESSION['usCorreo'] ?? '',
  "usTelefono" => $_SESSION['usTelefono'] ?? '',
  "usUsername" => $_SESSION['usUsername'] ?? '',
  "usImagen" => $_SESSION['usImagen'] ?? '',
  "usNotificaciones" => $_SESSION['usNotificaciones'] ?? null,
  "usConfirmado" => $_SESSION['usConfirmado'] ?? null,
]);
