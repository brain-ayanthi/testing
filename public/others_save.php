<?php
require_once __DIR__ . '/../init.php';
require_login();
global $pdo;

header('Content-Type: application/json');

try {
  $farmer_id = $_POST['farmer_code'] ?? $_POST['farmer_name'] ?? null;
  $amount = $_POST['amount'] ?? 0;
  $note = $_POST['note'] ?? '';
  $issue_date = $_POST['issue_date'] ?? date('Y-m-d');

  if (empty($farmer_id) || empty($amount)) {
    throw new Exception('Missing required fields.');
  }

  // Default status logic
  $status = 'due';
  $paid = 0;

  $stmt = $pdo->prepare("
  INSERT INTO others (issue_date, farmer_id, amount, note, paid, status)
  VALUES (:issue_date, :farmer_id, :amount, :note, :paid, :status)
");

$stmt->execute([
  ':issue_date' => $issue_date,
  ':farmer_id' => $farmer_id,
  ':amount' => $amount,
  ':note' => $note,
  ':paid' => $paid,
  ':status' => $status
]);

  echo json_encode(['status' => true, 'message' => 'Record saved successfully!']);
} catch (Exception $e) {
  echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
