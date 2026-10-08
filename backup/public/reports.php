<?php
require_once __DIR__.'/../init.php'; require_login();
$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-t');
$stmt = $pdo->prepare('SELECT a.name as area_name, SUM(c.weight_kg) as total_wt FROM collections c LEFT JOIN areas a ON c.area_id=a.id WHERE c.collection_date BETWEEN :s AND :e GROUP BY c.area_id');
$stmt->execute([':s'=>$start,':e'=>$end]); $rows = $stmt->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reports</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><?php include 'partials/topnav.php'; ?><div class="container mt-3"><h4>🔍 Collection Report</h4>
<form class="mb-2"><div class="row"><div class="col-md-3"><input type="date" name="start" class="form-control" value="<?php echo htmlspecialchars($start); ?>"></div><div class="col-md-3"><input type="date" name="end" class="form-control" value="<?php echo htmlspecialchars($end); ?>"></div><div class="col-md-2"><button class="btn btn-outline-secondary">Filter</button></div></div></form>
<div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Area</th><th>Total Weight (kg)</th></tr></thead><tbody><?php foreach($rows as $r) echo '<tr><td>'.htmlspecialchars($r['area_name']).'</td><td>'.$r['total_wt'].'</td></tr>'; ?></tbody></table></div>
</div></body></html>
