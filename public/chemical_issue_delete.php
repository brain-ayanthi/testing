<?php
require_once __DIR__ . '/../init.php';
require_login();
global $pdo;
header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
  echo json_encode(['status'=>false, 'message'=>'Invalid ID']);
  exit;
}

try {
  $pdo->beginTransaction();

  $pdo->prepare("DELETE FROM chemical_issue_items WHERE issue_id = ?")->execute([$id]);
  $pdo->prepare("DELETE FROM chemical_issue WHERE id = ?")->execute([$id]);

  $pdo->commit();
  echo json_encode(['status'=>true, 'message'=>'Record deleted successfully.']);
} catch (Exception $e) {
  $pdo->rollBack();
  echo json_encode(['status'=>false, 'message'=>'Delete failed: '.$e->getMessage()]);
}
