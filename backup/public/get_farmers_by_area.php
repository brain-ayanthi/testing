<?php
require_once __DIR__ . '/../init.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$area_id = intval($_GET['area_id'] ?? 0);
if ($area_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, code, name FROM farmers WHERE area_id = :a ORDER BY name");
$stmt->execute([':a' => $area_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
