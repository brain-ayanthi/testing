<?php
require_once __DIR__ . '/../init.php';
require_login();

// Database connection
include('db.php');

// ---- Get filters ----
$month = $_GET['month'] ?? date('Y-m');
$factory_id = $_GET['factory_id'] ?? '';

// ---- Prepare date range for selected month ----
$start_date = date('Y-m-01', strtotime($month));
$end_date   = date('Y-m-t', strtotime($month));

// ---- Fetch factory list ----
$factories = $pdo->query("SELECT id, factory_name FROM factory ORDER BY factory_name")->fetchAll(PDO::FETCH_ASSOC);

// ---- Fetch daily weight data ----
$sql = "SELECT distribution_date,
               SUM(allocated_weight) AS allocated_weight,
               SUM(extra_weight) AS extra_weight
        FROM daily_distributions
        WHERE distribution_date BETWEEN :start_date AND :end_date";

$params = [
    ':start_date' => $start_date,
    ':end_date'   => $end_date
];

if (!empty($factory_id)) {
    $sql .= " AND factory_id = :factory_id";
    $params[':factory_id'] = $factory_id;
}

$sql .= " GROUP BY distribution_date ORDER BY distribution_date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Convert to date-indexed array ----
$daily = [];
foreach ($data as $row) {
    $daily[$row['distribution_date']] = $row;
}

// ---- Month Summary (allocated + extra) ----
$sum_sql = "SELECT 
                SUM(allocated_weight) AS total_allocated,
                SUM(extra_weight) AS total_extra
            FROM daily_distributions
            WHERE distribution_date BETWEEN :start_date AND :end_date";
if (!empty($factory_id)) {
    $sum_sql .= " AND factory_id = :factory_id";
}

$sum_stmt = $pdo->prepare($sum_sql);
$sum_stmt->execute($params);
$summary = $sum_stmt->fetch(PDO::FETCH_ASSOC);
$total_allocated = $summary['total_allocated'] ?? 0;
$total_extra = $summary['total_extra'] ?? 0;
$abc= $total_allocated + $total_extra;
// ---- Total bag weight from payments table ----
$bag_stmt = $pdo->prepare("
    SELECT SUM(bag_weight) AS total_bag_weight
    FROM payments
    WHERE period = :period
");
$bag_stmt->execute([':period' => $month]);
$bag_result = $bag_stmt->fetch(PDO::FETCH_ASSOC);
$total_bag_weight = $bag_result['total_bag_weight'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Factory Weight Calendar</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .calendar {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 10px;
    }
    .day {
      border: 1px solid #ddd;
      padding: 5px;
      border-radius: 5px;
      text-align: center;
      min-height: 40px;
      background: #f9f9f9;
      box-shadow: 0 0 5px rgba(0,0,0,0.1);
    }
    .today {
      background: #d1e7dd;
      border: 2px solid #0f5132;
    }
    .day strong {
      display: block;
      font-size: 15px;
      margin-bottom: 5px;
    }
    .weight {
      font-size: 13px;
    }
  </style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-4">
  <h3 class="mb-4">📅 Weight Report</h3>

  <!-- 🔍 Filter Form -->
  <form method="get" class="row g-3 mb-4">
    <div class="col-md-3">
      <label class="form-label">Select Month</label>
      <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($month) ?>">
    </div>

    <div class="col-md-3">
      <label class="form-label">Select Factory</label>
      <select name="factory_id" class="form-select">
        <option value="">-- All Factories --</option>
        <?php foreach ($factories as $f): ?>
          <option value="<?= $f['id'] ?>" <?= ($factory_id == $f['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($f['factory_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-2 align-self-end">
      <button type="submit" class="btn btn-primary w-100">Filter</button>
    </div>
  </form>

  <!-- 🗓️ Calendar -->
  <div class="calendar mb-4">
    <?php
    $days_in_month = date('t', strtotime($month));
    for ($d = 1; $d <= $days_in_month; $d++):
        $date = date('Y-m-d', strtotime("$month-$d"));
        $today_class = ($date == date('Y-m-d')) ? 'today' : '';
        $allocated = $daily[$date]['allocated_weight'] ?? 0;
		 $extra = $daily[$date]['extra_weight'] ?? 0;
		$tw = $allocated + $extra;
		
       
    ?>
      <div class="day <?= $today_class ?>">
        <strong><?= $d ?></strong>
        <div class="weight text-success">T/Weight: <b><?= number_format($tw, 2) ?></b></div>
        <div class="weight text-info">Extra:<b> <?= number_format($extra, 2) ?></b></div>
      </div>
    <?php endfor; ?>
  </div>

  <!-- 📊 Month Summary -->
  <div class="card">
    <div class="card-body">
      <h5 class="card-title">📈 Month Summary (<?= date('F Y', strtotime($month)) ?>)</h5>
      <table class="table table-bordered text-center mt-3">
        <thead class="table-light">
          <tr>
            <th>Total Allocated Weight (Kg)</th>
            <th>Total Extra Weight (Kg)</th>
            <th>Total Bag Weight (Kg)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong><?= number_format($abc, 2) ?></strong></td>
            <td><strong><?= number_format($total_extra, 2) ?></strong></td>
            <td><strong><?= number_format($total_bag_weight, 3) ?></strong></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</body>
</html>
