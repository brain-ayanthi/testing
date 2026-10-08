<?php
require_once __DIR__ . '/../init.php';
require_login();

$data = json_decode($_POST['allocations'], true);
$date = $_POST['date'];

if (empty($data)) {
    echo json_encode(['status'=>false,'message'=>'No data to save']);
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("
        INSERT INTO daily_distributions (distribution_date, factory_id, allocated_weight, extra_weight)
        VALUES (:d, :f, :w, :e)
        ON DUPLICATE KEY UPDATE allocated_weight=:w2, extra_weight=:e2
    ");

    foreach ($data as $row) {
        $stmt->execute([
            ':d'=>$date,
            ':f'=>$row['factory'],
            ':w'=>$row['weight'],
            ':e'=>$row['extra'] ?? 0,
            ':w2'=>$row['weight'],
            ':e2'=>$row['extra'] ?? 0
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
