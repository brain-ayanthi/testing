<?php
require_once __DIR__ . '/../init.php';
global $pdo;
header('Content-Type: application/json');

$q = $_GET['q'] ?? '';
$stmt = $pdo->prepare("SELECT id, code, name FROM farmers WHERE code LIKE ? OR name LIKE ? ORDER BY code ASC LIMIT 20");
$stmt->execute(["%$q%", "%$q%"]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
