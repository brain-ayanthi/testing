<?php
require_once __DIR__ . '/../init.php';
require_login();

header('Content-Type: application/json; charset=utf-8');
error_reporting(0); // prevent PHP warnings from corrupting JSON output

$response = ['status' => false, 'message' => 'Unknown error'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    global $pdo;

    $farmer_id = intval($_POST['farmer_id'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    $note = trim($_POST['note'] ?? '');
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $items_json = $_POST['data'] ?? '';

    if (!$farmer_id) throw new Exception("Farmer not selected");
    if (empty($items_json)) throw new Exception("No chemical items provided");

    $items = json_decode($items_json, true);
    if (!is_array($items) || count($items) === 0) {
        throw new Exception("Invalid items JSON");
    }

    $pdo->beginTransaction();

    // Insert into chemical_issue (main record)
    $stmt = $pdo->prepare("INSERT INTO chemical_issue (farmer_id, issue_date, note, subtotal, paid, status)
                           VALUES (:farmer_id, :issue_date, :note, :subtotal, 0, 'due' )");
    $stmt->execute([
        ':farmer_id' => $farmer_id,
        ':issue_date' => $date,
        ':note' => $note,
        ':subtotal' => $subtotal
    ]);
    $issue_id = $pdo->lastInsertId();

    // Insert child rows
    $stmt_item = $pdo->prepare("INSERT INTO chemical_issue_items
        (issue_id, chemical_name, price, quantity, total)
        VALUES (:issue_id, :chemical_name, :price, :quantity, :total)");

    foreach ($items as $i) {
        if (empty($i['name'])) continue;
        $stmt_item->execute([
            ':issue_id' => $issue_id,
            ':chemical_name' => $i['name'],
            ':price' => $i['price'] ?? 0,
            ':quantity' => $i['qty'] ?? 0,
            ':total' => $i['total'] ?? 0
        ]);
    }

    $pdo->commit();

    $response = [
        'status' => true,
        'message' => '✅ Chemical issue saved successfully.'
    ];

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $response = ['status' => false, 'message' => '❌ Save failed: ' . $e->getMessage()];
}

echo json_encode($response);
exit;
