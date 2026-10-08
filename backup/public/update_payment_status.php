<?php
require_once __DIR__ . '/../init.php';
require_login();
include('db.php');

$id = $_POST['id'] ?? 0;
$farmer_id = $_POST['farmer_id'] ?? 0;
$period = $_POST['period'] ?? '';
$full = $_POST['full'] ?? 0;

if (!$id || !$farmer_id || !$period) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // --- Update payments table ---
    $stmt = $conn->prepare("
        UPDATE payments 
        SET status = 1, paid_amount = ? 
        WHERE id = ? AND farmer_id = ? AND period = ?
    ");
    $stmt->bind_param("diis", $full, $id, $farmer_id, $period);
    $stmt->execute();

    // --- Update collections table ---
    $stmt2 = $conn->prepare("
        UPDATE collections 
        SET paid = 1 
        WHERE farmer_id = ? 
        AND DATE_FORMAT(collection_date, '%Y-%m') = ?
    ");
    $stmt2->bind_param("is", $farmer_id, $period);
    $stmt2->execute();

    // --- Update tea bag issues table ---
    $stmt3 = $conn->prepare("
        UPDATE tea_bag_issues 
        SET status = 1 
        WHERE farmer_id = ? 
        AND DATE_FORMAT(issue_date, '%Y-%m') = ?
    ");
    $stmt3->bind_param("is", $farmer_id, $period);
    $stmt3->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
