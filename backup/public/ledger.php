<?php
require_once __DIR__.'/../init.php'; require_login();
$fid = intval($_GET['farmer_id']??0);
$farmer = null; $entries = [];
if($fid>0){
    $farmer = $pdo->prepare('SELECT * FROM farmers WHERE id=:id'); $farmer->execute([':id'=>$fid]); $farmer = $farmer->fetch();
    $stmt = $pdo->prepare('SELECT * FROM collections WHERE farmer_id=:f ORDER BY collection_date DESC'); $stmt->execute([':f'=>$fid]); $entries = $stmt->fetchAll();
}
$all = $pdo->query('SELECT id,name,code FROM farmers ORDER BY name')->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ledger</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><?php include 'partials/topnav.php'; ?><div class="container mt-3"><h4>Farmer Ledger</h4>
<form method="get" class="mb-2"><div class="row"><div class="col-md-4"><select name="farmer_id" class="form-control"><option value="">--Select Farmer--</option><?php foreach($all as $a) echo '<option value="'.$a['id'].'" '.($fid==$a['id']?'selected':'').'>'.$a['code'].' - '.htmlspecialchars($a['name']).'</option>'; ?></select></div><div class="col-md-2"><button class="btn btn-outline-secondary">View</button></div></div></form>
<?php if($farmer): ?>
<div class="card p-3"><h5><?php echo htmlspecialchars($farmer['name']); ?> (<?php echo htmlspecialchars($farmer['code']); ?>)</h5>
<table class="table table-sm"><thead><tr><th>Date</th><th>Weight</th><th>Payable</th><th>Advance</th></tr></thead><tbody><?php foreach($entries as $e) echo '<tr><td>'.$e['collection_date'].'</td><td>'.$e['weight_kg'].'</td><td>'.$e['payable_amount'].'</td><td>'.$e['advance_paid'].'</td></tr>'; ?></tbody></table>
</div>
<?php endif; ?>
</div></body></html>
