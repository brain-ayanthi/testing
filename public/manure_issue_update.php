<?php
require_once __DIR__ . '/../init.php';
require_login();
header('Content-Type: application/json');

try {
    $id = intval($_POST['id'] ?? 0);
    $farmer_id = intval($_POST['farmer_id'] ?? 0);
    $issue_date = $_POST['issue_date'] ?? '';
    $unit_price = floatval($_POST['unit_price'] ?? 0);
    $quantity = floatval($_POST['quantity'] ?? 0);
    $subtotal = $unit_price * $quantity;

    if ($id <= 0 || $farmer_id <= 0 || !$issue_date) {
        throw new Exception("Invalid data.");
    }

    $stmt = $pdo->prepare("
        UPDATE manure 
        SET farmer_id = :f, issue_date = :d, unit_price = :u, quantity = :q, subtotal = :s
        WHERE id = :id
    ");
    $stmt->execute([
        ':f' => $farmer_id,
        ':d' => $issue_date,
        ':u' => $unit_price,
        ':q' => $quantity,
        ':s' => $subtotal,
        ':id' => $id
    ]);

    echo json_encode(['status' => true, 'message' => 'Manure issue updated successfully.']);
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?>
