<?php
require_once __DIR__.'/../init.php'; require_login();
$id = intval($_GET['id']??0);
$stmt = $pdo->prepare('SELECT pay.*, f.name as farmer_name, f.code as farmer_code FROM payments pay JOIN farmers f ON pay.farmer_id=f.id WHERE pay.id=:id');
$stmt->execute([':id'=>$id]); $p = $stmt->fetch();
if(!$p) { echo 'Not found'; exit; }
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment Slip</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><div class="container mt-3"><div class="card p-3"><h4>Payment Slip</h4>
<p><strong>Farmer:</strong> <?php echo htmlspecialchars($p['farmer_name']); ?> (<?php echo htmlspecialchars($p['farmer_code']); ?>)</p>
<p><strong>Period:</strong> <?php echo htmlspecialchars($p['period']); ?></p>
<table class="table"><tr><td>Total Collected (kg)</td><td><?php echo $p['total_collection']; ?></td></tr><tr><td>Total Payable</td><td><?php echo $p['total_payable']; ?></td></tr><tr><td>Saved (10%)</td><td><?php echo $p['saved_amount']; ?></td></tr><tr><td>Paid</td><td><?php echo $p['paid_amount']; ?></td></tr></table>
<button class="btn btn-primary" onclick="window.print()">Print</button>
</div></div></body></html>
