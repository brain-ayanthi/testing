<?php
require_once __DIR__ . '/../init.php';
require_login();
include('db.php');

header('Content-Type: application/json; charset=utf-8');

$farmer_id = intval($_POST['farmer_id'] ?? 0);
$commi = floatval($_POST['commi'] ?? 0);

if ($farmer_id <= 0) {
    echo json_encode(['status' => false, 'message' => 'Invalid farmer']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE saving SET commi = ? WHERE farmer_id = ? AND paid = 0");
    $stmt->bind_param("di", $commi, $farmer_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => true, 'message' => 'Commission updated']);
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
