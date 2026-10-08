<?php
require_once __DIR__ . '/../init.php';
require_login();

/* ---------------------------------------------------------
   🔹 SECTION 1: All-Time Balances (No Month Filter)
----------------------------------------------------------*/
function get_balance_summary($pdo, $table, $amount_col, $paid_col) {
    $sql = "SELECT 
                COALESCE(SUM($amount_col),0) AS total_amount, 
                COALESCE(SUM($paid_col),0) AS total_paid, 
                COALESCE(SUM($amount_col - $paid_col),0) AS balance
            FROM $table";
    return $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
}

$advances  = get_balance_summary($pdo, 'advances', 'amount', 'paid');
$chemical  = get_balance_summary($pdo, 'chemical_issue', 'subtotal', 'paid');
$others    = get_balance_summary($pdo, 'others', 'amount', 'paid');

/* ---------------------------------------------------------
   📅 SECTION 2: Month-wise Paid Summary
----------------------------------------------------------*/

$month = $_GET['month'] ?? date('Y-m');

// ✅ Collect all unique available months across all payment tables
$months = $pdo->query("
    SELECT DISTINCT period FROM (
        SELECT period FROM f_advance_amount_pay
        UNION
        SELECT period FROM f_chemical_pay
        UNION
        SELECT period FROM f_other_pay
    ) AS m
    WHERE period IS NOT NULL
    ORDER BY period DESC
")->fetchAll(PDO::FETCH_COLUMN);

// ✅ Get each table’s total for selected month
$stmt1 = $pdo->prepare("SELECT COALESCE(SUM(pay_amount),0) FROM f_advance_amount_pay WHERE period = :p");
$stmt1->execute([':p'=>$month]);
$adv_paid = floatval($stmt1->fetchColumn());

$stmt2 = $pdo->prepare("SELECT COALESCE(SUM(pay_amount),0) FROM f_chemical_pay WHERE period = :p");
$stmt2->execute([':p'=>$month]);
$chem_paid = floatval($stmt2->fetchColumn());

$stmt3 = $pdo->prepare("SELECT COALESCE(SUM(pay_amount),0) FROM f_other_pay WHERE period = :p");
$stmt3->execute([':p'=>$month]);
$oth_paid = floatval($stmt3->fetchColumn());

$total_paid = $adv_paid + $chem_paid + $oth_paid;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Deduction Summary</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-4 mb-5">

  <h4 class="mb-4 fw-bold">💰 Deduction Summary</h4>

  <!-- 🟩 SECTION 1: All-Time Balances -->
  <div class="card mb-4 border-success shadow-sm">
    <div class="card-header bg-success text-white fw-bold">All-Time Balances</div>
    <div class="card-body p-0">
      <table class="table table-bordered table-sm text-center mb-0">
        <thead class="table-light">
          <tr>
            <th>Type</th>
            <th>Total Amount (Rs)</th>
            <th>Total Paid (Rs)</th>
            <th>Balance (Rs)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Advances</td>
            <td><?= number_format($advances['total_amount'],2) ?></td>
            <td><?= number_format($advances['total_paid'],2) ?></td>
            <td class="text-danger fw-bold"><?= number_format($advances['balance'],2) ?></td>
          </tr>
          <tr>
            <td>Chemical Issue</td>
            <td><?= number_format($chemical['total_amount'],2) ?></td>
            <td><?= number_format($chemical['total_paid'],2) ?></td>
            <td class="text-danger fw-bold"><?= number_format($chemical['balance'],2) ?></td>
          </tr>
          <tr>
            <td>Others</td>
            <td><?= number_format($others['total_amount'],2) ?></td>
            <td><?= number_format($others['total_paid'],2) ?></td>
            <td class="text-danger fw-bold"><?= number_format($others['balance'],2) ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 🟦 SECTION 2: Month-wise Paid Summary -->
  <div class="card border-primary shadow-sm">
    <div class="card-header bg-primary text-white fw-bold">Month-wise Paid Summary</div>
    <div class="card-body">

      <!-- 🔹 Month Selector -->
      <form method="get" class="mb-3">
        <div class="row g-2 align-items-end">
          <div class="col-auto">
            <label class="form-label fw-bold">Select Month:</label>
            <select name="month" class="form-select" onchange="this.form.submit()">
              <?php foreach ($months as $m): ?>
                <option value="<?= $m ?>" <?= ($m==$month?'selected':'') ?>><?= $m ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-auto">
            <button class="btn btn-outline-secondary">Filter</button>
          </div>
        </div>
      </form>

      <!-- 🔹 Monthly Paid Table -->
      <table class="table table-bordered table-sm text-center">
        <thead class="table-light">
          <tr>
            <th>Month</th>
            <th>Advance Paid (Rs)</th>
            <th>Chemical Paid (Rs)</th>
            <th>Others Paid (Rs)</th>
            <th>Total Paid (Rs)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= htmlspecialchars($month) ?></td>
            <td><?= number_format($adv_paid,2) ?></td>
            <td><?= number_format($chem_paid,2) ?></td>
            <td><?= number_format($oth_paid,2) ?></td>
            <td class="fw-bold text-success"><?= number_format($total_paid,2) ?></td>
          </tr>
        </tbody>
      </table>

    </div>
  </div>

</div>
</body>
</html>
