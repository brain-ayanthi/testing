<?php
require_once __DIR__ . '/../init.php';
header('Content-Type: application/json');

$q = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'code';

$sql = "SELECT id, code, name FROM farmers WHERE code LIKE ? OR name LIKE ? LIMIT 20";
$stmt = $pdo->prepare($sql);
$stmt->execute(["%$q%", "%$q%"]);
$data = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $data[] = [
    'id' => $row['id'],
    'text' => $type == 'code' ? $row['code'] : $row['name'],
    'text_code' => $row['code'],
    'text_name' => $row['name']
  ];
}

echo json_encode($data);
