<?php
require_once __DIR__ . '/../init.php';
require_login();
global $pdo;

header('Content-Type: application/json');

try {
  $id = $_POST['id'] ?? 0;
  $farmer_id = $_POST['farmer_id'] ?? 0;
  $amount = $_POST['amount'] ?? 0;
  $paid = $_POST['paid'] ?? 0;
  $status = $_POST['status'] ?? 'due';
  $note = trim($_POST['note'] ?? '');

  if(!$id || !$farmer_id) throw new Exception("Invalid data");

  $stmt = $pdo->prepare("
    UPDATE others 
    SET farmer_id=?, amount=?, paid=?, status=?, note=? 
    WHERE id=?
  ");
  $stmt->execute([$farmer_id, $amount, $paid, $status, $note, $id]);

  echo json_encode(['status'=>true, 'message'=>'Record updated successfully.']);
} catch (Exception $e) {
  echo json_encode(['status'=>false, 'message'=>$e->getMessage()]);
}
