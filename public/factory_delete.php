<?php
require_once __DIR__ . '/../init.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
  $stmt = $pdo->prepare("DELETE FROM factory WHERE id = :id");
  $stmt->execute([':id'=>$id]);
}
header("Location: factory_list.php?deleted=1");
exit;
