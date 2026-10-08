<?php
require_once __DIR__ . '/../init.php';
require_login();
include('db.php');

/**
 * saving_list.php
 * Shows each farmer’s total savings across all periods (paid/unpaid),
 * with paid rows highlighted in green.
 */

$search = trim($_GET['search'] ?? '');
$area_id = $_GET['area_id'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

// --- Base WHERE (no need for "or 1") ---
$where = "WHERE 1=1";
$params = [];

if ($area_id !== '') {
    $where .= " AND f.area_id = ?";
    $params[] = $area_id;
}

if ($search !== '') {
    $where .= " AND (f.name LIKE ? OR f.code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// --- Count distinct farmers ---
$count_sql = "
  SELECT COUNT(*) AS cnt
  FROM (
    SELECT s.farmer_id
    FROM saving s
    JOIN farmers f ON s.farmer_id = f.id
    $where
    GROUP BY s.farmer_id
  ) x
";
$count_stmt = $conn->prepare($count_sql);
if ($params) {
    $types = str_repeat('s', count($params));
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$totalRows = $count_result->fetch_assoc()['cnt'] ?? 0;
$totalPages = max(1, ceil($totalRows / $limit));

// --- Fetch grouped data (by farmer) ---
$sql = "
  SELECT 
    f.id AS farmer_id,
    f.code AS farmer_code,
    f.name AS farmer_name,
    a.name AS area_name,
    SUM(s.amount) AS total_saving,
    AVG(s.commi) AS avg_commi,
    MAX(s.paid) AS paid_status -- if any record is paid=1, show as paid
  FROM saving s
  JOIN farmers f ON s.farmer_id = f.id
  LEFT JOIN areas a ON f.area_id = a.id
  $where
  GROUP BY s.farmer_id
  ORDER BY f.name ASC
  LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$types = str_repeat('s', count($params) - 2) . 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$savings = [];
$total_saving = 0;
while ($row = $result->fetch_assoc()) {
    $row['subtotal'] = $row['total_saving'] + ($row['total_saving'] * $row['avg_commi'] / 100);
    $savings[] = $row;
    $total_saving += $row['total_saving'];
}

// --- Fetch areas for filter ---
$areas = $conn->query("SELECT id, name FROM areas ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Saving List</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#f8f9fa; }
  .table td, .table th { vertical-align: middle; text-align: center; }
  .total-row { background: #eaf7ea; font-weight: bold; }
  .form-control-sm { width: 80px; margin: auto; text-align: right; }
  .paid-row { background-color: #d4edda !important; } /* 🟢 light green for paid */
  .unpaid-row { background-color: #fff !important; } /* default white */
</style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-4">
  <h4 class="fw-bold text-primary mb-3">Saving List</h4>

  <!-- 🔍 Filters -->
  <form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
      <select name="area_id" class="form-select">
        <option value="">All Areas</option>
        <?php foreach ($areas as $ar): ?>
          <option value="<?= $ar['id'] ?>" <?= ($area_id == $ar['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($ar['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <input type="text" name="search" class="form-control" placeholder="Search farmer name or code" value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-primary w-100">Filter</button>
    </div>
    <div class="col-md-2">
      <a href="saving_list.php" class="btn btn-outline-secondary w-100">Reset</a>
    </div>
  </form>

  <!-- 💰 Table -->
  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover">
          <thead class="table-light text-center">
            <tr>
              <th>#</th>
              <th>Area</th>
              <th>Farmer Code</th>
              <th>Farmer Name</th>
              <th>Total Saving (Rs)</th>
              <th>Com %</th>
              <th>Subtotal (Rs)</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if (count($savings) > 0): ?>
            <?php $i = $offset + 1; foreach ($savings as $row): ?>
              <?php
                $rowClass = ($row['paid_status'] == 1) ? 'paid-row' : 'unpaid-row';
                $statusLabel = ($row['paid_status'] == 1)
                  ? '<span class="badge bg-success">Paid</span>'
                  : '<span class="badge bg-warning text-dark">Unpaid</span>';
              ?>
              <tr class="<?= $rowClass ?>" data-id="<?= $row['farmer_id'] ?>">
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['area_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['farmer_code']) ?></td>
                <td><?= htmlspecialchars($row['farmer_name']) ?></td>
                <td class="text-end"><?= number_format($row['total_saving'], 2) ?></td>
                <td class="text-center">
                  <div class="d-flex align-items-center justify-content-center gap-1">
                    <input type="number" step="0.01" class="form-control form-control-sm com-input text-end" 
                           style="width: 80px;" value="<?= number_format($row['avg_commi'], 2) ?>">
                    <button class="btn btn-sm btn-primary save-comm-btn">Save</button>
                  </div>
                </td>
                <td class="text-end subtotal-cell"><?= number_format($row['subtotal'], 2) ?></td>
                <td><?= $statusLabel ?></td>
                <td>
                  <a href="saving_pay.php?farmer_id=<?= $row['farmer_id'] ?>" class="btn btn-sm btn-success">Pay</a>
                  <a href="saving_view.php?farmer_id=<?= $row['farmer_id'] ?>" class="btn btn-sm btn-secondary">View</a>
                </td>
              </tr>
            <?php endforeach; ?>
            <tr class="total-row">
              <td colspan="4" class="text-end">Total Saving:</td>
              <td class="text-end"><?= number_format($total_saving, 2) ?></td>
              <td colspan="4"></td>
            </tr>
          <?php else: ?>
            <tr><td colspan="9" class="text-center text-muted">No records found.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <nav>
          <ul class="pagination justify-content-center mt-3">
            <?php for ($i=1; $i<=$totalPages; $i++): ?>
              <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&area_id=<?= urlencode($area_id) ?>&search=<?= urlencode($search) ?>">
                  <?= $i ?>
                </a>
              </li>
            <?php endfor; ?>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
// 💾 Save Commission Percentage (update all unpaid records for this farmer)
$(document).on('click', '.save-comm-btn', function(){
  const tr = $(this).closest('tr');
  const farmer_id = tr.data('id');
  const commi = parseFloat(tr.find('.com-input').val()) || 0;
  const amount = parseFloat(tr.find('td:nth-child(5)').text().replace(/,/g,'')) || 0;
  const subtotal = amount + (amount * commi / 100);
  tr.find('.subtotal-cell').text(subtotal.toFixed(2));

  $.post('save_commission.php', { farmer_id: farmer_id, commi: commi }, function(res){
    if(res.status){
      alert('Commission updated successfully!');
    } else {
      alert(res.message || 'Failed to save commission');
    }
  }, 'json').fail(()=> alert('Server error'));
});
</script>
</body>
</html>
