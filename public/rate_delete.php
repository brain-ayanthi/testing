<?php
require_once __DIR__.'/../init.php'; require_login();
$id=intval($_GET['id']??0); if($id>0){ $stmt=$pdo->prepare('DELETE FROM area_rates WHERE id=:id'); $stmt->execute([':id'=>$id]); } header('Location: rates.php'); exit;
