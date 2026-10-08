<?php
require_once __DIR__ . '/../init.php';
require_login();

$data = json_decode($_POST['allocations'] ?? '[]', true);
$date = $_POST['date'] ?? '';

if (empty($data)) {
    echo json_encode(['status'=>false,'message'=>'No data to save']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO daily_distributions (distribution_date, factory_id, allocated_weight, bag, extra_weight)
        VALUES (:d, :f, :w, :b, :e)
        ON DUPLICATE KEY UPDATE
            allocated_weight = VALUES(allocated_weight),
            bag = VALUES(bag),
            extra_weight = VALUES(extra_weight)
    ");

    foreach ($data as $row) {
        $stmt->execute([
            ':d' => $date,
            ':f' => $row['factory'],
            ':w' => $row['weight'],
            ':b' => $row['bag'] ?? 0,   // ✅ this must match JS key
            ':e' => $row['extra'] ?? 0,
        ]);
    }

    $pdo->commit();
    $_SESSION['success_message'] = 'Distribution saved successfully';
    echo json_encode(['status'=>true,'message'=>'Distribution saved successfully']);
} catch(Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status'=>false,'message'=>$e->getMessage()]);
}
?>
