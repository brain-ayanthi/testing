<?php
require_once __DIR__ . '/../init.php';
require_login();

$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');

// Count total
$sqlCount = "SELECT COUNT(*) FROM factory WHERE factory_name LIKE :s";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute([':s' => "%$search%"]);
$total = $stmt->fetchColumn();

// Pagination calc
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

// Get paginated results
$sql = "SELECT * FROM factory WHERE factory_name LIKE :s ORDER BY id DESC LIMIT :off, :pp";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':s', "%$search%", PDO::PARAM_STR);
$stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
$stmt->bindValue(':pp', (int)$perPage, PDO::PARAM_INT);
$stmt->execute();
$factories = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Factory List</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'partials/topnav.php'; ?>
<div class="container mt-4">
  <div class="d-flex justify-content-between mb-3">
    <h4 class="fw-bold text-primary">Factories</h4>
    <a href="factory_add.php" class="btn btn-success">➕ Add Factory</a>
  </div>

  <form class="row mb-3">
    <div class="col-md-4">
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search by name">
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100">Search</button>
    </div>
  </form>

  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Factory Name</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($factories as $f): ?>
          <tr>
            <td><?= $f['id'] ?></td>
            <td><?= htmlspecialchars($f['factory_name']) ?></td>
            <td><?= htmlspecialchars($f['created_at']) ?></td>
            <td>
              <a href="factory_edit.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
              <a href="factory_delete.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this factory?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Pagination -->
      <nav>
        <ul class="pagination justify-content-center">
          <?php for($i=1; $i<=$totalPages; $i++): ?>
            <li class="page-item <?= ($i==$page)?'active':'' ?>">
              <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
  </div>
</div>
</body>
</html>
