<?php
require_once __DIR__ . '/../init.php';
header('Content-Type: application/json');
require_login();

try {
    // Connect to DB (adjust if not using $pdo)
    global $pdo;

    $issue_date = $_POST['issue_date'] ?? date('Y-m-d');
    $user_id = $_SESSION['user']['id'] ?? null;
    $json_data = $_POST['data'] ?? '';

    // Decode rows from AJAX
    $rows = json_decode($json_data, true);

    if (empty($rows) || !is_array($rows)) {
        echo json_encode(['status' => false, 'message' => 'No valid data received.']);
        exit;
    }

    // Prepare SQL insert
    $stmt = $pdo->prepare("
        INSERT INTO tea_bag_issues (issue_date, farmer_id, unit_price, quantity, subtotal, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $inserted = 0;

    foreach ($rows as $r) {
        $farmer_id = intval($r['farmer_id']);
        $unit_price = floatval($r['unit_price']);
        $quantity = floatval($r['quantity']);
        $subtotal = $unit_price * $quantity;

        if ($farmer_id > 0 && $quantity > 0) {
            $stmt->execute([$issue_date, $farmer_id, $unit_price, $quantity, $subtotal, $user_id]);
            $inserted++;
        }
    }

    if ($inserted > 0) {
        echo json_encode(['status' => true, 'message' => "$inserted record(s) saved successfully."]);
    } else {
        echo json_encode(['status' => false, 'message' => 'No valid rows to save.']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
