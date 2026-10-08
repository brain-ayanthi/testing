<?php
require_once __DIR__ . '/../init.php';
require_login();

header('Content-Type: application/json');

$id = $_POST['id'] ?? 0;

if (!$id) {
  echo json_encode(['status' => false, 'message' => 'Invalid record ID']);
  exit;
}

$stmt = $pdo->prepare("DELETE FROM daily_distributions WHERE id = :id");
$ok = $stmt->execute([':id' => $id]);

if ($ok) {
  echo json_encode(['status' => true, 'message' => 'Record deleted successfully']);
} else {
  echo json_encode(['status' => false, 'message' => 'Delete failed']);
}
