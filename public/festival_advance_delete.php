<?php
require_once __DIR__ . '/../init.php';
require_login();

global $pdo;
header('Content-Type: application/json');

$id = $_POST['id'] ?? 0;
if(!$id){
  echo json_encode(['status'=>false, 'message'=>'Invalid ID']);
  exit;
}

try {
  $stmt = $pdo->prepare("DELETE FROM advances WHERE id = :id");
  $stmt->execute(['id'=>$id]);
  echo json_encode(['status'=>true, 'message'=>'Deleted successfully']);
} catch(Exception $e){
  echo json_encode(['status'=>false, 'message'=>$e->getMessage()]);
}
?>
