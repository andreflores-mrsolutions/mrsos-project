<?php
// helpers/sedes_permitidas.php
function getAllowedSedes(mysqli $db, int $clId, int $usId): array {
  // Ajusta los nombres exactos si tu tabla/columnas difieren
  $sql = "SELECT ucrRol, czId, csId
          FROM usuario_cliente_rol
          WHERE clId = ? AND usId = ?";

  $stmt = $db->prepare($sql);
  $stmt->bind_param("ii", $clId, $usId);
  $stmt->execute();
  $res = $stmt->get_result();

  $roles = [];
  $zona = null;
  $csId = null;

  while ($row = $res->fetch_assoc()) {
    $roles[] = $row['ucrRol'] ?? '';
    if (!empty($row['czId'])) $zona = $row['czId'];
    if (!empty($row['csId'])) $csId = (int)$row['csId'];
  }

  // ADMIN GLOBAL
  if (in_array('ADMIN_GLOBAL', $roles, true) || in_array('adminGlobal', $roles, true)) {
    $stmt2 = $db->prepare("SELECT csId FROM cliente_sede WHERE clId = ? AND csEstatus = 'Activo'");
    $stmt2->bind_param("i", $clId);
    $stmt2->execute();
    $r2 = $stmt2->get_result();
    $ids = [];
    while ($x = $r2->fetch_assoc()) $ids[] = (int)$x['csId'];
    return $ids;
  }

  // ADMIN ZONA
  if (in_array('ADMIN_ZONA', $roles, true) || in_array('adminZona', $roles, true)) {
    $stmt2 = $db->prepare("SELECT csId FROM cliente_sede WHERE clId = ? AND czId = ? AND csEstatus = 'Activo'");
    $stmt2->bind_param("is", $clId, $zona);
    $stmt2->execute();
    $r2 = $stmt2->get_result();
    $ids = [];
    while ($x = $r2->fetch_assoc()) $ids[] = (int)$x['csId'];
    return $ids;
  }

  // USUARIO NORMAL: solo su sede
  return $csId ? [$csId] : [];
}
