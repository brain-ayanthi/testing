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
$month = $_GET['month'] ?? '';

$where = "WHERE 1";
$params = [];

if (!empty($search)) {
    $where .= " AND (f.name LIKE :search OR f.code LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($status)) {
    $where .= " AND a.status = :status";
    $params['status'] = $status;
}

if (!empty($month)) {
    $where .= " AND DATE_FORMAT(a.created_at, '%Y-%m') = :month";
    $params['month'] = $month;
}

// Count total
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM advances a
    LEFT JOIN farmers f ON a.farmer_id = f.id
    $where
");
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch data
$sql = "
    SELECT a.*, f.code AS farmer_code, f.name AS farmer_name,
           (a.amount - a.paid) AS balance
    FROM advances a
    LEFT JOIN farmers f ON a.farmer_id = f.id
    $where
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$advances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_amount = 0;
$total_balance = 0;
$total_paid = 0;
foreach ($advances as $a) {
    $total_amount += $a['amount'];
    $total_balance += $a['balance'];
	$total_paid += $a['paid'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Festival Advance List</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="text-primary mb-4"> Festival Advance List</h4>
    <a href="festival_advance.php" class="btn btn-success">➕ Add New</a>
  </div>

  <!-- Filters -->
  <form class="row g-3 mb-3" method="GET">
    <div class="col-md-3">
      <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" class="form-control">
    </div>
    <div class="col-md-3">
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search by Farmer Name or Code">
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
      <a href="festival_advance_list.php" class="btn btn-secondary">Reset</a>
    </div>
  </form>

  <!-- Data Table -->
  <div class="table-responsive">
    <table class="table table-bordered table-hover bg-white align-middle">
      <thead class="table-light text-center">
        <tr>
          <th>#</th>
          <th>Date</th>
          <th>Farmer Code</th>
          <th>Farmer Name</th>
          <th>Amount (Rs)</th>
          <th>Paid (Rs)</th>
          <th>Balance (Rs)</th>
          <th>Note</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($advances): 
          $sn = $offset + 1;
          foreach ($advances as $row): ?>
          <tr >
            <td><?= $sn++ ?></td>
            <td><?= htmlspecialchars($row['created_at']) ?></td>
            <td><?= htmlspecialchars($row['farmer_code']) ?></td>
            <td><?= htmlspecialchars($row['farmer_name']) ?></td>
            <td class="text-end"><?= number_format($row['amount'], 2) ?></td>
            <td class="text-end"><?= number_format($row['paid'], 2) ?></td>
            <td class="text-end fw-bold"><?= number_format($row['balance'], 2) ?></td>
            <td><?= htmlspecialchars($row['note']) ?></td>
            <td class="text-center">
              <?php if ($row['status'] == 'paid'): ?>
                <span class="badge bg-success">Paid</span>
              <?php elseif ($row['status'] == 'partial'): ?>
                <span class="badge bg-warning text-dark">Partial</span>
              <?php else: ?>
                <span class="badge bg-danger">Due</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <a href="festival_advance_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
              <button class="btn btn-sm btn-danger deleteBtn" data-id="<?= $row['id'] ?>">Delete</button>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="10" class="text-center text-muted">No records found.</td></tr>
        <?php endif; ?>
      </tbody>

      <!-- Totals -->
      <?php if ($advances): ?>
      <tfoot class="table-secondary fw-bold">
        <tr>
          <td colspan="4" class="text-end">Total:</td>
          <td class="text-end"><?= number_format($total_amount, 2) ?></td>
          <td class="text-end"><?= number_format($total_paid, 2) ?></td>
          <td class="text-end"><?= number_format($total_balance, 2) ?></td>
          <td colspan="3"></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <nav>
    <ul class="pagination justify-content-center">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>&month=<?= urlencode($month) ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"><?= $i ?></a>
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
  $.post('festival_advance_delete.php', {id:id}, res=>{
    if(res.status){
      alert('Record deleted successfully.');
      location.reload();
    } else {
      alert('Delete failed: ' + res.message);
    }
  }, 'json');
});
</script>
</body>
</html>
