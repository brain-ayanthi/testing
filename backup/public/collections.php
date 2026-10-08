<?php
require_once __DIR__.'/../init.php';
require_login();

// --- Load areas for dropdown ---
$areas = $pdo->query("SELECT id, name FROM areas ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// --- Filters ---
$q = trim($_GET['q'] ?? '');
$date_filter = $_GET['date'] ?? date('Y-m-d');
$area_filter = $_GET['area_id'] ?? '';

// --- SQL Query ---
$sql = "SELECT 
            c.*, 
            f.code AS farmer_code, 
            f.name AS farmer_name,
            a.name AS area_name, 
            ag.name AS agent_name
        FROM collections c
        LEFT JOIN farmers f ON c.farmer_id = f.id
        LEFT JOIN areas a ON c.area_id = a.id
        LEFT JOIN users ag ON c.agent_id = ag.id
        WHERE 1";

$params = [];

// Date filter (defaults to today)
if (!empty($date_filter)) {
    $sql .= " AND DATE(c.collection_date) = :date";
    $params[':date'] = $date_filter;
}

// Area filter
if (!empty($area_filter)) {
    $sql .= " AND c.area_id = :area_id";
    $params[':area_id'] = $area_filter;
}

// Search
if (!empty($q)) {
    $sql .= " AND (
        f.code LIKE :q OR
        f.name LIKE :q OR
        a.name LIKE :q OR
        ag.name LIKE :q
    )";
    $params[':q'] = "%$q%";
}

$sql .= " ORDER BY c.collection_date DESC, f.code ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Totals ---
$total_weight = 0;
$total_advance = 0;
foreach ($rows as $r) {
    $total_weight += $r['weight_kg'];
    $total_advance += $r['advance_paid'] ?? 0;
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Collections</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
<style>
  body { background-color:#f9f9f9; }
  .today-row { background-color: #f0fff0 !important; }
  .summary-box {
    background:#f8f9fa;
    padding:12px 15px;
    border:1px solid #ddd;
    border-radius:8px;
    font-weight:500;
  }
  .summary-box span { font-weight:700; color:#007bff; }
</style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold text-primary">Collections</h4>
    <a href="collection_add.php" class="btn btn-success">➕ Add New</a>
  </div>

  <!-- Filter Form -->
  <form class="row g-2 mb-3">
    <div class="col-md-3">
      <input name="q" class="form-control" placeholder="Search Farmer, Code, Area, Agent..." 
             value="<?= htmlspecialchars($q); ?>">
    </div>
    <div class="col-md-3">
      <select name="area_id" class="form-select">
        <option value="">All Areas</option>
        <?php foreach($areas as $a): ?>
          <option value="<?= $a['id'] ?>" <?= ($a['id']==$area_filter)?'selected':'' ?>>
            <?= htmlspecialchars($a['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter); ?>">
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100">Filter</button>
    </div>
    <div class="col-md-2">
      <a href="collections.php" class="btn btn-outline-secondary w-100">Today</a>
    </div>
  </form>

  <!-- Summary -->
  <div class="summary-box mb-3">
    Total Weight: <span><?= number_format($total_weight, 2) ?> kg</span> |
    Total Advance: <span>Rs <?= number_format($total_advance, 2) ?></span>
  </div>

  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table id="collectionsTable" class="table table-bordered table-striped table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Farmer Code</th>
            <th>Farmer Name</th>
            <th>Date</th>
            <th>Weight (kg)</th>
            <th>Advance (Rs)</th>
            <th>Area</th>
            <th>Agent</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): 
            $isToday = (date('Y-m-d', strtotime($r['collection_date'])) == date('Y-m-d'));
        ?>
          <tr class="<?= $isToday ? 'today-row' : ''; ?>">
            <td><?= $r['id']; ?></td>
            <td><?= htmlspecialchars($r['farmer_code']); ?></td>
            <td><?= htmlspecialchars($r['farmer_name']); ?></td>
            <td><?= htmlspecialchars(date('Y-m-d', strtotime($r['collection_date']))); ?></td>
            <td><?= number_format($r['weight_kg'], 2); ?></td>

            <td><?= number_format($r['advance_paid'] ?? 0, 2); ?></td>
            <td><?= htmlspecialchars($r['area_name']); ?></td>
            <td><?= htmlspecialchars($r['agent_name'] ?? '-'); ?></td>
            <td>
              <a href="collection_edit.php?id=<?= $r['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
              <a href="collection_delete.php?id=<?= $r['id']; ?>" 
                 onclick="return confirm('Delete this record?')" 
                 class="btn btn-sm btn-danger">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
          <tr>
            <th colspan="4" class="text-end">Totals:</th>
            <th><?= number_format($total_weight, 2); ?></th>
            <th><?= number_format($total_advance, 2); ?></th>
            <th colspan="3"></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
$(document).ready(function(){
  // Initialize DataTable
  $('#collectionsTable').DataTable({
    order: [[0, 'desc']],
    pageLength: 25,
    fixedHeader: true,
    dom: 'Bfrtip',
    buttons: [
      { extend: 'excelHtml5', className: 'btn btn-success btn-sm', title: 'Collections_Report' },
      { extend: 'pdfHtml5', className: 'btn btn-danger btn-sm', title: 'Collections_Report' },
      { extend: 'print', className: 'btn btn-secondary btn-sm' }
    ],
    language: { search: "_INPUT_", searchPlaceholder: "Quick search..." }
  });
});
</script>
</body>
</html>
