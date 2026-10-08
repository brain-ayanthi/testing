<?php
require_once __DIR__ . '/../init.php';
require_login();

// --- CONFIG ---
$perPage = 15; // results per page

// --- FILTERS ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$period = isset($_GET['period']) ? trim($_GET['period']) : ''; // e.g. "2025-10"

// --- PAGINATION ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// --- BUILD WHERE CLAUSE ---
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(a.name LIKE :search OR a.code LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($period !== '') {
    $where[] = "ar.year_month = :period";
    $params[':period'] = $period;
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// --- COUNT TOTAL ---
$sqlCount = "
    SELECT COUNT(*) 
    FROM area_rates ar 
    LEFT JOIN areas a ON ar.area_id = a.id
    $whereSql
";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($params);
$totalRows = (int) $stmt->fetchColumn();
$totalPages = max(ceil($totalRows / $perPage), 1);

// --- FETCH DATA ---
$sql = "
    SELECT ar.*, a.name AS area_name, a.code AS area_code
    FROM area_rates ar 
    LEFT JOIN areas a ON ar.area_id = a.id
    $whereSql
    ORDER BY ar.year_month DESC
    LIMIT $perPage OFFSET $offset
"; 
// 👆 Directly insert LIMIT/OFFSET instead of PDO bindValue — fixes pagination issue

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- DISTINCT PERIODS for dropdown ---
$periods = $pdo->query("SELECT DISTINCT `year_month` FROM area_rates ORDER BY `year_month` DESC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Area Rates</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Area Rates</h4>
    <a class="btn btn-success" href="rate_add.php">Add Rate</a>
  </div>

  <!-- Search & Filter Form -->
  <form method="get" class="row g-2 mb-3">
    <div class="col-sm-4">
      <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Search by area name or code">
    </div>
    <div class="col-sm-3">
      <select name="period" class="form-select">
        <option value="">All Periods</option>
        <?php foreach ($periods as $p): ?>
          <option value="<?php echo htmlspecialchars($p); ?>" <?php if ($p == $period) echo 'selected'; ?>>
            <?php echo htmlspecialchars($p); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary">Filter</button>
      <?php if ($search || $period): ?>
        <a href="rates.php" class="btn btn-secondary">Reset</a>
      <?php endif; ?>
    </div>
  </form>

  <!-- Table -->
  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>Area</th>
          <th>Code</th>
          <th>Period</th>
          <th>Rate/kg</th>
          <th width="150">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($rows): ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo $r['id']; ?></td>
            <td><?php echo htmlspecialchars($r['area_name']); ?></td>
            <td><?php echo htmlspecialchars($r['area_code']); ?></td>
            <td><?php echo htmlspecialchars($r['year_month']); ?></td>
            <td><?php echo htmlspecialchars($r['rate_per_kg']); ?></td>
            <td>
              <a class="btn btn-sm btn-primary" href="rate_edit.php?id=<?php echo $r['id']; ?>">Edit</a>
              <a class="btn btn-sm btn-danger" href="rate_delete.php?id=<?php echo $r['id']; ?>" onclick="return confirm('Delete this rate?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="6" class="text-center text-muted">No records found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <?php $queryParams = $_GET; ?>
  <nav>
    <ul class="pagination justify-content-center">
      <?php
        // Previous
        $queryParams['page'] = max($page - 1, 1);
        $prevUrl = '?' . http_build_query($queryParams);
      ?>
      <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
        <a class="page-link" href="<?php echo $prevUrl; ?>">Previous</a>
      </li>

      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php
          $queryParams['page'] = $i;
          $url = '?' . http_build_query($queryParams);
        ?>
        <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
          <a class="page-link" href="<?php echo $url; ?>"><?php echo $i; ?></a>
        </li>
      <?php endfor; ?>

      <?php
        // Next
        $queryParams['page'] = min($page + 1, $totalPages);
        $nextUrl = '?' . http_build_query($queryParams);
      ?>
      <li class="page-item <?php if ($page >= $totalPages) echo 'disabled'; ?>">
        <a class="page-link" href="<?php echo $nextUrl; ?>">Next</a>
      </li>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
