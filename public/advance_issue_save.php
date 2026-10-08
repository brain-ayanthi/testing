<?php
require_once __DIR__ . '/../init.php';
require_login();
global $pdo;

header('Content-Type: application/json');

$date = $_POST['date'] ?? date('Y-m-d');
$data = json_decode($_POST['data'] ?? '[]', true);

if (empty($data)) {
    echo json_encode(['status' => false, 'message' => 'No data received']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO advances_issues (farmer_id, amount, note, created_at)
        VALUES (:farmer_id, :amount, :note, :created_at)
    ");

    foreach ($data as $row) {
        $stmt->execute([
            ':farmer_id' => $row['farmer_id'],
            ':amount' => $row['amount'],
            ':note' => $row['note'] ?? '',
            ':created_at' => $date
        ]);
    }

    $pdo->commit();
    echo json_encode(['status' => true, 'message' => '✅ Festival advance saved successfully.']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
