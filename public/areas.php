<?php
require_once __DIR__ . '/../init.php';
require_login();

// --- CONFIG ---
$perPage = 10; // results per page

// --- SEARCH HANDLING ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- PAGINATION HANDLING ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// --- COUNT TOTAL ---
if ($search) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM areas WHERE name LIKE :search OR code LIKE :search");
    $stmt->execute(['search' => "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT COUNT(*) FROM areas");
}
$totalRows = $stmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// --- FETCH PAGINATED RESULTS ---
if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM areas WHERE name LIKE :search OR code LIKE :search ORDER BY name LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $areas = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT * FROM areas ORDER BY name LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $areas = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Areas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Areas</h4>
    <a class="btn btn-success" href="area_add.php">Add Area</a>
  </div>

  <!-- Search Form -->
  <form method="get" class="row g-2 mb-3">
    <div class="col-sm-4">
      <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Search by name or code">
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary">Search</button>
      <?php if ($search): ?>
        <a href="areas.php" class="btn btn-secondary">Reset</a>
      <?php endif; ?>
    </div>
  </form>

  <!-- Table -->
  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Code</th>
          <th width="150">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($areas): ?>
        <?php foreach ($areas as $a): ?>
          <tr>
            <td><?php echo $a['id']; ?></td>
            <td><?php echo htmlspecialchars($a['name']); ?></td>
            <td><?php echo htmlspecialchars($a['code']); ?></td>
            <td>
              <a class="btn btn-sm btn-primary" href="area_edit.php?id=<?php echo $a['id']; ?>">Edit</a>
              <a class="btn btn-sm btn-danger" href="area_delete.php?id=<?php echo $a['id']; ?>" onclick="return confirm('Delete?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="4" class="text-center text-muted">No records found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <nav>
    <ul class="pagination justify-content-center">
      <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
      </li>
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
          <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
        </li>
      <?php endfor; ?>
      <li class="page-item <?php if ($page >= $totalPages) echo 'disabled'; ?>">
        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
      </li>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
