<?php
require_once __DIR__ . '/../init.php';
require_login();
global $pdo;

// Pagination setup
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$month = $_GET['month'] ?? date('Y-m');

// WHERE conditions
$where = "WHERE DATE_FORMAT(o.created_at, '%Y-%m') = :month";
$params = ['month' => $month];

if (!empty($search)) {
  $where .= " AND (f.name LIKE :search OR f.code LIKE :search)";
  $params['search'] = "%$search%";
}
if (!empty($status)) {
  $where .= " AND o.status = :status";
  $params['status'] = $status;
}

// Count total
$count_stmt = $pdo->prepare("
  SELECT COUNT(*) 
  FROM others o
  LEFT JOIN farmers f ON o.farmer_id = f.id
  $where
");
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch data
$sql = "
  SELECT o.*, f.name AS farmer_name, f.code AS farmer_code,
         (o.amount - o.paid) AS balance
  FROM others o
  LEFT JOIN farmers f ON o.farmer_id = f.id
  $where
  ORDER BY o.created_at DESC, o.id DESC
  LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals for visible page
$total_amount = 0;
$total_paid = 0;
$total_balance = 0;
foreach ($records as $r) {
  $total_amount += $r['amount'];
  $total_paid += $r['paid'];
  $total_balance += ($r['amount'] - $r['paid']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Other Charges / Payments List</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#f8f9fa; }
  table th, table td { text-align:center; vertical-align:middle; }
  .table tfoot { font-weight:bold; background:#eef; }
</style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold text-primary">Other Charges / Payments</h4>
    <a href="others.php" class="btn btn-success">➕ New Entry</a>
  </div>

  <!-- Filters -->
  <form class="row g-3 mb-3" method="GET">
    <div class="col-md-3">
      <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" class="form-control">
    </div>
    <div class="col-md-3">
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search Farmer or Code...">
    </div>
    <div class="col-md-3">
      <select name="status" class="form-select">
        <option value="">All Status</option>
        <option value="due" <?= $status=='due'?'selected':'' ?>>Due</option>
        <option value="partial" <?= $status=='partial'?'selected':'' ?>>Partial</option>
        <option value="paid" <?= $status=='paid'?'selected':'' ?>>Paid</option>
      </select>
    </div>
    <div class="col-md-3">
      <button class="btn btn-primary">Filter</button>
      <a href="others_list.php" class="btn btn-secondary">Reset</a>
    </div>
  </form>

  <!-- Table -->
  <div class="table-responsive">
    <table class="table table-bordered table-hover bg-white">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Date</th>
          <th>Farmer Code</th>
          <th>Farmer Name</th>
          <th>Amount (Rs)</th>
          <th>Paid (Rs)</th>
          <th>Balance (Rs)</th>
          <th>Status</th>
          <th>Note</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($records) > 0): 
          $sn = $offset + 1;
          foreach ($records as $r): ?>
          <tr>
            <td><?= $sn++ ?></td>
            <td><?= htmlspecialchars(date('Y-m-d', strtotime($r['created_at']))) ?></td>
            <td><?= htmlspecialchars($r['farmer_code']) ?></td>
            <td><?= htmlspecialchars($r['farmer_name']) ?></td>
            <td><?= number_format($r['amount'], 2) ?></td>
            <td><?= number_format($r['paid'], 2) ?></td>
            <td><?= number_format($r['balance'], 2) ?></td>
            <td class="text-capitalize"><?= htmlspecialchars($r['status']) ?></td>
            <td><?= htmlspecialchars($r['note']) ?></td>
            <td>
              <a href="others_edit.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
              <button class="btn btn-sm btn-danger deleteBtn" data-id="<?= $r['id'] ?>">Delete</button>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="10" class="text-center text-muted">No records found</td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4" class="text-end">Total (This Page):</td>
          <td><?= number_format($total_amount, 2) ?></td>
          <td><?= number_format($total_paid, 2) ?></td>
          <td><?= number_format($total_balance, 2) ?></td>
          <td colspan="3"></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <nav>
    <ul class="pagination justify-content-center">
      <?php for ($i=1; $i<=$total_pages; $i++): ?>
        <li class="page-item <?= $i==$page?'active':'' ?>">
          <a class="page-link" href="?page=<?= $i ?>&month=<?= $month ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).on('click', '.deleteBtn', function(){
  if(!confirm('Are you sure you want to delete this record?')) return;
  const id = $(this).data('id');
  $.post('others_delete.php', {id:id}, function(res){
    if(res.status){
      alert(res.message);
      location.reload();
    } else {
      alert(res.message);
    }
  }, 'json').fail(() => alert('Server error.'));
});
</script>
</body>
</html>
