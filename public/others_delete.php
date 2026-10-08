<?php
require_once __DIR__ . '/../init.php';
require_login();
global $pdo;

header('Content-Type: application/json');

try {
  $id = $_POST['id'] ?? 0;
  if(!$id) throw new Exception("Invalid ID");

  $stmt = $pdo->prepare("DELETE FROM others WHERE id = ?");
  $stmt->execute([$id]);

  echo json_encode(['status' => true, 'message' => 'Record deleted successfully']);
} catch (Exception $e) {
  echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
