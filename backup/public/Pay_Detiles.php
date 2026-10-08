<?php
require_once __DIR__ . '/../init.php';
require_login();

$period = $_GET['period'] ?? date('Y-m'); // Default current month

// --- Summary queries ---
$sql = "SELECT 
            SUM(p.paid_amount) AS total_paid,
            SUM(CASE WHEN f.payment_method='cash' THEN p.paid_amount ELSE 0 END) AS cash_total,
            SUM(CASE WHEN f.payment_method='bank' THEN p.paid_amount ELSE 0 END) AS bank_total
        FROM payments p
        INNER JOIN farmers f ON f.id = p.farmer_id
        WHERE p.period = :period";

$stmt = $pdo->prepare($sql);
$stmt->execute([':period' => $period]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

$total_paid  = $summary['total_paid'] ?? 0;
$cash_total  = $summary['cash_total'] ?? 0;
$bank_total  = $summary['bank_total'] ?? 0;

// --- Cash Denomination Calculation (for cash only) ---
$denominations = [5000, 1000, 500, 100, 50, 10];
$remaining = $cash_total;
$breakdown = [];

foreach ($denominations as $denom) {
    $count = floor($remaining / $denom);
    $breakdown[$denom] = $count;
    $remaining -= $count * $denom;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payment Details</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'partials/topnav.php'; ?>
<div class="container mt-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">💰 Payment Details</h4>
    <form method="get" class="d-flex align-items-center">
      <label class="me-2">Select Month:</label>
      <input type="month" name="period" value="<?= htmlspecialchars($period) ?>" class="form-control form-control-sm me-2" style="width:160px">
      <button class="btn btn-primary btn-sm">Filter</button>
    </form>
  </div>

  <!-- Totals Section -->
  <div class="row text-center mb-4">
    <div class="col-md-4">
      <div class="card border-success">
        <div class="card-body">
          <h6 class="text-success">Total Paid Amount</h6>
          <h3><?= number_format($total_paid, 2) ?> Rs</h3>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-primary">
        <div class="card-body">
          <h6 class="text-primary">Bank Payments</h6>
          <h4><?= number_format($bank_total, 2) ?> Rs</h4>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-warning">
        <div class="card-body">
          <h6 class="text-warning">Cash Payments</h6>
          <h4><?= number_format($cash_total, 2) ?> Rs</h4>
        </div>
      </div>
    </div>
  </div>

  <!-- Cash Denomination Breakdown -->
  <div class="card mt-3">
    <div class="card-header bg-light"><strong>💵 Cash Denominations Breakdown</strong></div>
    <div class="card-body">
      <?php if ($cash_total > 0): ?>
        <table class="table table-bordered text-center table-sm">
          <thead class="table-secondary">
            <tr>
              <th>Denomination (Rs)</th>
              <th>Count</th>
              <th>Total Value (Rs)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($breakdown as $denom => $count): ?>
              <?php if ($count > 0): ?>
              <tr>
                <td><?= $denom ?></td>
                <td><?= $count ?></td>
                <td><?= number_format($count * $denom, 2) ?></td>
              </tr>
              <?php endif; ?>
            <?php endforeach; ?>
            <tr class="table-success fw-bold">
              <td colspan="2">Total Cash</td>
              <td><?= number_format($cash_total, 2) ?> Rs</td>
            </tr>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-muted mb-0">No cash payments found for this period.</p>
      <?php endif; ?>
    </div>
  </div>

</div>
</body>
</html>
