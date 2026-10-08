<?php
require_once __DIR__ . '/../init.php';
require_login();
global $pdo;

header('Content-Type: application/json');

// Debug mode during testing — remove later
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    if (empty($_POST['id']) || empty($_POST['farmer_id']) || empty($_POST['issue_date'])) {
        throw new Exception('Missing required fields.');
    }

    $id = (int)$_POST['id'];
    $farmer_id = (int)$_POST['farmer_id'];
    $issue_date = $_POST['issue_date'];
    $note = trim($_POST['note'] ?? '');
    $subtotal = (float)($_POST['subtotal'] ?? 0);

    $chemical_names = $_POST['chemical_name'] ?? [];
    $prices = $_POST['price'] ?? [];
    $qtys = $_POST['quantity'] ?? [];
    $totals = $_POST['total'] ?? [];

    if (count($chemical_names) === 0) {
        throw new Exception('No chemical items provided.');
    }

    $pdo->beginTransaction();

    // Update main record
    $update = $pdo->prepare("
        UPDATE chemical_issue
        SET farmer_id = ?, issue_date = ?, note = ?, subtotal = ?
        WHERE id = ?
    ");
    $update->execute([$farmer_id, $issue_date, $note, $subtotal, $id]);

    // Delete old items
    $pdo->prepare("DELETE FROM chemical_issue_items WHERE issue_id = ?")->execute([$id]);

    // Insert new items
    $insertItem = $pdo->prepare("
        INSERT INTO chemical_issue_items (issue_id, chemical_name, price, quantity, total)
        VALUES (?, ?, ?, ?, ?)
    ");

    for ($i = 0; $i < count($chemical_names); $i++) {
        $name = trim($chemical_names[$i]);
        if ($name === '') continue;
        $price = (float)$prices[$i];
        $qty = (float)$qtys[$i];
        $total = (float)$totals[$i];
        $insertItem->execute([$id, $name, $price, $qty, $total]);
    }

    $pdo->commit();

    echo json_encode(['status' => true, 'message' => 'Updated successfully.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
