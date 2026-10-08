<?php
require_once __DIR__.'/../init.php'; require_login();
$id=intval($_GET['id']??0); if($id<=0){ header('Location: rates.php'); exit; }
$stmt=$pdo->prepare('SELECT * FROM area_rates WHERE id=:id'); $stmt->execute([':id'=>$id]); $row=$stmt->fetch();
$areas = $pdo->query('SELECT * FROM areas ORDER BY name')->fetchAll();
$err=''; if($_SERVER['REQUEST_METHOD']==='POST'){ $area_id=intval($_POST['area_id']); $ym=trim($_POST['year_month']); $rate=floatval($_POST['rate_per_kg']); if($area_id<=0||$ym=='') $err='Area and period required'; if(!$err){ $stmt=$pdo->prepare('UPDATE area_rates SET area_id=:a,year_month=:ym,rate_per_kg=:r WHERE id=:id'); $stmt->execute([':a'=>$area_id,':ym'=>$ym,':r'=>$rate,':id'=>$id]); header('Location: rates.php'); exit; } }
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit Rate</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><?php include 'partials/topnav.php'; ?><div class="container mt-3"><h4>Edit Rate</h4><?php if($err) echo '<div class="alert alert-danger">'.htmlspecialchars($err).'</div>'; ?>
<form method="post"><div class="row"><div class="col-md-6 mb-2"><label>Area</label><select name="area_id" class="form-control"><?php foreach($areas as $a) echo '<option value="'.$a['id'].'" '.($row['area_id']==$a['id']?'selected':'').'>'.htmlspecialchars($a['name']).'</option>'; ?></select></div><div class="col-md-3 mb-2"><label>Period (YYYY-MM)</label><input name="year_month" class="form-control" value="<?php echo htmlspecialchars($row['year_month']); ?>"></div><div class="col-md-3 mb-2"><label>Rate per kg</label><input name="rate_per_kg" class="form-control" value="<?php echo $row['rate_per_kg']; ?>"></div></div><button class="btn btn-primary">Save</button></form></div></body></html>
