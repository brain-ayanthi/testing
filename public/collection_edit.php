<?php
require_once __DIR__.'/../init.php'; require_login();
$id = intval($_GET['id']??0); if($id<=0) { header('Location: collections.php'); exit; }
$stmt = $pdo->prepare('SELECT * FROM collections WHERE id=:id'); $stmt->execute([':id'=>$id]); $row = $stmt->fetch();
$areas = $pdo->query('SELECT * FROM areas ORDER BY name')->fetchAll();
$farmers = $pdo->query('SELECT id,code,name FROM farmers ORDER BY name')->fetchAll();
$agents = $pdo->query('SELECT id,name FROM users WHERE roles LIKE "%field_officer%" OR roles LIKE "%collector%"')->fetchAll();
$err=''; if($_SERVER['REQUEST_METHOD']==='POST'){
    $farmer_id = intval($_POST['farmer_id']); $date = $_POST['collection_date']; $weight = floatval($_POST['weight_kg']); $area_id = intval($_POST['area_id']); $agent_id = intval($_POST['agent_id']);
    $bonus = floatval($_POST['bonus'] ?? 0); $deduction = floatval($_POST['deduction'] ?? 0); $advance = floatval($_POST['advance'] ?? 0);
    $ym = date('Y-m', strtotime($date));
    $stmt2 = $pdo->prepare('SELECT rate_per_kg FROM area_rates WHERE area_id=:a AND year_month=:ym LIMIT 1'); $stmt2->execute([':a'=>$area_id,':ym'=>$ym]); $rate = $stmt2->fetchColumn()?:0;
    $payable = ($weight * $rate) + $bonus - $deduction - $advance;
    $stmt = $pdo->prepare('UPDATE collections SET farmer_id=:f,collection_date=:d,weight_kg=:w,area_id=:a,agent_id=:ag,bonus=:b,deduction=:ded,advance_paid=:adv,payable_amount=:pay WHERE id=:id');
    $stmt->execute([':f'=>$farmer_id,':d'=>$date,':w'=>$weight,':a'=>$area_id,':ag'=>$agent_id,':b'=>$bonus,':ded'=>$deduction,':adv'=>$advance,':pay'=>$payable,':id'=>$id]);
    header('Location: collections.php'); exit;
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit Collection</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><?php include 'partials/topnav.php'; ?>
<div class="container mt-3"><h4>Edit Collection</h4><?php if($err) echo '<div class="alert alert-danger">'.htmlspecialchars($err).'</div>'; ?>
<form method="post">
  <div class="row">
    <div class="col-md-4 mb-2"><label>Farmer</label><select name="farmer_id" class="form-control"><?php foreach($farmers as $f) echo '<option value="'.$f['id'].'" '.($row['farmer_id']==$f['id']?'selected':'').'>'.$f['code'].' - '.htmlspecialchars($f['name']).'</option>'; ?></select></div>
    <div class="col-md-3 mb-2"><label>Date</label><input type="date" name="collection_date" class="form-control" value="<?php echo $row['collection_date']; ?>"></div>
    <div class="col-md-3 mb-2"><label>Weight (kg)</label><input type="number" step="0.001" name="weight_kg" class="form-control" value="<?php echo $row['weight_kg']; ?>"></div>
    <div class="col-md-2 mb-2"><label>Area</label><select name="area_id" class="form-control"><?php foreach($areas as $a) echo '<option value="'.$a['id'].'" '.($row['area_id']==$a['id']?'selected':'').'>'.htmlspecialchars($a['name']).'</option>'; ?></select></div>
    <div class="col-md-3 mb-2"><label>Agent</label><select name="agent_id" class="form-control"><option value="0">--none--</option><?php foreach($agents as $ag) echo '<option value="'.$ag['id'].'" '.($row['agent_id']==$ag['id']?'selected':'').'>'.htmlspecialchars($ag['name']).'</option>'; ?></select></div>
    <div class="col-md-3 mb-2"><label>Advance Paid</label><input name="advance" class="form-control" value="<?php echo $row['advance_paid']; ?>"></div>
  </div>
  <button class="btn btn-primary mt-2">Save</button>
</form>
</div></body></html>
