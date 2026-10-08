<?php
require_once __DIR__ . '/../init.php';
require_login();

// Filters
$search = $_GET['search'] ?? '';
$start  = $_GET['start'] ?? date('Y-m-01');
$end    = $_GET['end'] ?? date('Y-m-d');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

// --- Get all area names ---
$areas = $pdo->query("SELECT id, name FROM areas ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// --- Get all factories ---
$factories = $pdo->query("SELECT id, factory_name FROM factory ORDER BY factory_name")->fetchAll(PDO::FETCH_ASSOC);

// --- Build filter ---
$where = "WHERE distribution_date BETWEEN :s AND :e";
$params = [':s'=>$start, ':e'=>$end];
if ($search) {
    $where .= " AND (factory_name LIKE :search OR distribution_date LIKE :search)";
    $params[':search'] = "%$search%";
}

// --- Get total date count ---
$totalRows = $pdo->prepare("SELECT COUNT(DISTINCT distribution_date) 
                            FROM daily_distributions d 
                            JOIN factory f ON d.factory_id=f.id $where");
$totalRows->execute($params);
$total_count = $totalRows->fetchColumn();

// --- Get unique distribution dates ---
$q = $pdo->prepare("SELECT DISTINCT distribution_date 
                    FROM daily_distributions d 
                    JOIN factory f ON d.factory_id=f.id 
                    $where 
                    ORDER BY distribution_date DESC 
                    LIMIT $limit OFFSET $offset");
$q->execute($params);
$dates = $q->fetchAll(PDO::FETCH_COLUMN);

// --- Build record data ---
$records = [];
$grand_total_weight = $grand_total_alloc = $grand_total_extra = 0;

foreach ($dates as $d) {
    // Area totals
    $qA = $pdo->prepare("SELECT a.name, SUM(c.weight_kg) AS total
                         FROM collections c
                         JOIN areas a ON c.area_id=a.id
                         WHERE DATE(c.collection_date)=:d
                         GROUP BY a.name");
    $qA->execute([':d'=>$d]);
    $areasData = $qA->fetchAll(PDO::FETCH_KEY_PAIR);

    // Factory allocations
    $qF = $pdo->prepare("SELECT f.factory_name, SUM(d.allocated_weight) AS alloc, SUM(d.extra_weight) AS extra
                         FROM daily_distributions d
                         JOIN factory f ON d.factory_id=f.id
                         WHERE d.distribution_date=:d
                         GROUP BY f.factory_name");
    $qF->execute([':d'=>$d]);
    $factData = $qF->fetchAll(PDO::FETCH_ASSOC);

    $tw = array_sum($areasData);
    $ta = array_sum(array_column($factData, 'alloc'));
    $te = array_sum(array_column($factData, 'extra'));

    $records[] = [
        'date' => $d,
        'areas' => $areasData,
        'factories' => $factData,
        'total_weight' => $tw,
        'total_alloc' => $ta,
        'total_extra' => $te
    ];

    $grand_total_weight += $tw;
    $grand_total_alloc  += $ta;
    $grand_total_extra  += $te;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Leaf Distribution Records</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#f8f9fa; }
  th, td { text-align:center; vertical-align:middle; }
  .table thead th { background:#e9ecef; }
  .totals-row th { background:#d1e7dd; font-weight:bold; }
</style>
</head>
<body >
<?php include 'partials/topnav.php'; ?>
<div class="container mt-3">
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Leaf Distribution List</h4>
  </div>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <form class="d-flex align-items-center" method="get">
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." class="form-control me-2" style="width:200px;">
      <input type="date" name="start" value="<?= htmlspecialchars($start) ?>" class="form-control me-2">
      <input type="date" name="end" value="<?= htmlspecialchars($end) ?>" class="form-control me-2">
      <button class="btn btn-primary">Filter</button>
    </form>
    <div>
      <a href="export_excel.php?start=<?= $start ?>&end=<?= $end ?>&search=<?= urlencode($search) ?>" class="btn btn-success me-2">⬇️ Download Excel</a>
      <a href="export_pdf.php?start=<?= $start ?>&end=<?= $end ?>&search=<?= urlencode($search) ?>" class="btn btn-danger">📄 Download PDF</a>
    </div>
  </div>

  <table class="table table-bordered bg-white">
    <thead>
      <tr>
        <th>Date</th>
        <?php foreach ($areas as $a): ?>
          <th><?= htmlspecialchars($a['name']) ?></th>
        <?php endforeach; ?>
        <th>Total Weight (kg)</th>
        <?php foreach ($factories as $f): ?>
          <th><?= htmlspecialchars($f['factory_name']) ?></th>
        <?php endforeach; ?>
        <th>Allocated (kg)</th>
        <th>Extra (kg)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($records as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['date']) ?></td>
        <?php foreach ($areas as $a): ?>
          <td><?= number_format($r['areas'][$a['name']] ?? 0,2) ?></td>
        <?php endforeach; ?>
        <td><strong><?= number_format($r['total_weight'],2) ?></strong></td>
        <?php foreach ($factories as $f): 
          $ff = array_values(array_filter($r['factories'], fn($x)=>$x['factory_name']==$f['factory_name']));
          $alloc = $ff[0]['alloc'] ?? 0; ?>
          <td><?= number_format($alloc,2) ?></td>
        <?php endforeach; ?>
        <td><strong><?= number_format($r['total_alloc'],2) ?></strong></td>
        <td><strong><?= number_format($r['total_extra'],2) ?></strong></td>
      </tr>
      <?php endforeach; ?>

      <!-- Totals Row -->
      <tr class="totals-row">
        <th colspan="<?= 0 + count($areas) + count($factories) ?>">Grand Totals</th>
        <th><?= number_format($grand_total_weight,2) ?></th>
		<th></th>
        <th><?= number_format($grand_total_alloc,2) ?></th>
        <th><?= number_format($grand_total_extra,2) ?></th>
      </tr>
    </tbody>
  </table>

  <!-- Pagination -->
  <nav>
    <ul class="pagination justify-content-center">
      <?php 
      $pages = ceil($total_count / $limit);
      for($i=1;$i<=$pages;$i++): ?>
        <li class="page-item <?= $i==$page?'active':'' ?>">
          <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&start=<?= $start ?>&end=<?= $end ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>

</div>
</body>
</html>
