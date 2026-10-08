<?php
require_once __DIR__ . '/../init.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, code, name FROM farmers 
                       WHERE code LIKE :q OR name LIKE :q 
                       ORDER BY name LIMIT 50");
$stmt->execute([':q' => "%$q%"]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
