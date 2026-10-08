<?php
require_once __DIR__ . '/../init.php';
require_login();

global $pdo;

// Pagination
$limit = 15;
$page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$offset = ($page - 1) * $limit;

// Filters
$search = trim($_GET['search'] ?? '');
$month = $_GET['month'] ?? date('Y-m');

// WHERE
$where = "WHERE DATE_FORMAT(t.issue_date, '%Y-%m') = :month";
$params = ['month' => $month];

if ($search !== '') {
    $where .= " AND (f.name LIKE :search OR f.code LIKE :search)";
    $params['search'] = "%$search%";
}

// Count total for pagination
$count_sql = "
    SELECT COUNT(*) FROM tea_bag_issues t
    LEFT JOIN farmers f ON t.farmer_id = f.id
    $where
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch data
$data_sql = "
    SELECT t.*, f.code AS farmer_code, f.name AS farmer_name
    FROM tea_bag_issues t
    LEFT JOIN farmers f ON t.farmer_id = f.id
    $where
    ORDER BY t.issue_date DESC, t.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($data_sql);
$stmt->execute($params);
$issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🔴 Duplicate highlighting
$dup_stmt = $pdo->prepare("
    SELECT f.id
    FROM tea_bag_issues t
    LEFT JOIN farmers f ON t.farmer_id = f.id
    WHERE DATE_FORMAT(t.issue_date, '%Y-%m') = :month
    GROUP BY f.id
    HAVING COUNT(*) > 1
");
$dup_stmt->execute(['month' => $month]);
$duplicateFarmerIds = $dup_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

// Calculate totals (for the filtered month)
$total_stmt = $pdo->prepare("
    SELECT SUM(t.quantity) AS total_qty, SUM(t.subtotal) AS total_amount
    FROM tea_bag_issues t
    LEFT JOIN farmers f ON t.farmer_id = f.id
    $where
");
$total_stmt->execute($params);
$totals = $total_stmt->fetch(PDO::FETCH_ASSOC);
$total_qty = $totals['total_qty'] ?? 0;
$total_amount = $totals['total_amount'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Tea Bag Issues List</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background: #f8f9fa; }
  .table-danger td { background-color: #f8d7da !important; }
  .page-link { cursor: pointer; }
</style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="text-primary mb-0">Tea Bag Issues</h4>
    <a href="tea_bag_issue.php" class="btn btn-success btn-sm">➕ Add New Issue</a>
  </div>

  <!-- Filters -->
  <form method="get" class="row g-2 mb-4">
    <div class="col-md-3">
      <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" class="form-control">
    </div>
    <div class="col-md-5">
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search by Farmer Name or Code">
    </div>
    <div class="col-md-4">
      <button class="btn btn-primary">Filter</button>
      <a href="tea_bag_issues_list.php" class="btn btn-secondary">Reset</a>
    </div>
  </form>

  <!-- Table -->
  <div class="table-responsive bg-white shadow-sm rounded">
    <table class="table table-bordered table-hover mb-0">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Date</th>
          <th>Farmer Code</th>
          <th>Farmer Name</th>
          <th>Unit Price (Rs)</th>
          <th>Quantity</th>
          <th>Subtotal (Rs)</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($issues) > 0): 
          $sn = $offset + 1;
          foreach ($issues as $row):
            $isDuplicate = in_array($row['farmer_id'], $duplicateFarmerIds);
        ?>
        <tr class="<?= $isDuplicate ? 'table-danger' : '' ?>">
          <td><?= $sn++ ?></td>
          <td><?= htmlspecialchars($row['issue_date']) ?></td>
          <td><?= htmlspecialchars($row['farmer_code']) ?></td>
          <td><?= htmlspecialchars($row['farmer_name']) ?></td>
          <td><?= number_format($row['unit_price'], 2) ?></td>
          <td><?= number_format($row['quantity'], 2) ?></td>
          <td><?= number_format($row['subtotal'], 2) ?></td>
          <td>
            <a href="tea_bag_issue_edit.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
            <button class="btn btn-danger btn-sm deleteBtn" data-id="<?= $row['id'] ?>">Delete</button>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8" class="text-center text-muted py-3">No records found for this month.</td></tr>
        <?php endif; ?>
      </tbody>
      <?php if ($total_rows > 0): ?>
      <tfoot class="table-secondary fw-bold">
        <tr>
          <td colspan="5" class="text-end">Total</td>
          <td><?= number_format($total_qty, 2) ?></td>
          <td><?= number_format($total_amount, 2) ?></td>
          <td></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <nav class="mt-3">
    <ul class="pagination justify-content-center">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <li class="page-item <?= $i == $page ? 'active' : '' ?>">
        <a class="page-link" href="?page=<?= $i ?>&month=<?= urlencode($month) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).on('click', '.deleteBtn', function(){
  if(!confirm("Are you sure you want to delete this record?")) return;
  const id = $(this).data('id');
  $.post('tea_bag_issue_delete.php', { id }, function(res){
    try {
      const data = JSON.parse(res);
      alert(data.message);
      if(data.status) location.reload();
    } catch (e) {
      alert('Delete failed.');
    }
  });
});
</script>
</body>
</html>
