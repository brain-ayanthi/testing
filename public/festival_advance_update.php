<?php
require_once __DIR__ . '/../init.php';
require_login();
global $pdo;

header('Content-Type: application/json');

$id = $_POST['id'] ?? 0;
$farmer_id = $_POST['farmer_code'] ?? null;
$created_at = $_POST['created_at'] ?? date('Y-m-d');
$amount = $_POST['amount'] ?? 0;
$paid = $_POST['paid'] ?? 0;
$status = $_POST['status'] ?? 'due';
$note = $_POST['note'] ?? '';

if(!$id || !$farmer_id){
  echo json_encode(['status'=>false, 'message'=>'Missing required fields']);
  exit;
}

try {
  $stmt = $pdo->prepare("
    UPDATE advances 
    SET farmer_id = :farmer_id,
        created_at = :created_at,
        amount = :amount,
        paid = :paid,
        status = :status,
        note = :note
    WHERE id = :id
  ");
  $stmt->execute([
    'farmer_id' => $farmer_id,
    'created_at' => $created_at,
    'amount' => $amount,
    'paid' => $paid,
    'status' => $status,
    'note' => $note,
    'id' => $id
  ]);
  echo json_encode(['status'=>true, 'message'=>'Festival advance updated successfully.']);
} catch(Exception $e){
  echo json_encode(['status'=>false, 'message'=>'Error: '.$e->getMessage()]);
}
?>
