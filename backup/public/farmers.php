<?php
require_once __DIR__.'/../init.php';
require_login();

$q = trim($_GET['q'] ?? '');

$sql = "SELECT f.*, a.name AS area_name
        FROM farmers f
        LEFT JOIN areas a ON f.area_id = a.id";

$params = [];
if ($q != '') {
    $sql .= " WHERE f.name LIKE :q OR f.nic LIKE :q OR f.code LIKE :q";
    $params[':q'] = "%$q%";
}

$sql .= " ORDER BY f.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Farmers</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-3">
  <div class="d-flex justify-content-between mb-2">
    <h4>Farmers</h4>
    <div>
      <a class="btn btn-success" href="farmer_add.php">Add Farmer</a>
    </div>
  </div>

  <!-- 🔍 Search -->
  <form class="mb-3" method="get">
    <div class="input-group">
      <input type="text" name="q" class="form-control" placeholder="Search name / NIC / code" value="<?= htmlspecialchars($q) ?>">
      <button class="btn btn-outline-secondary">Search</button>
    </div>
  </form>

  <!-- 📋 Farmers Table -->
  <div class="table-responsive">
    <table class="table table-bordered table-striped table-sm align-middle">
      <thead class="table-light">
        <tr class="text-center">
          <th>ID</th>
          <th>Code</th>
          <th>Name</th>
          <th>NIC</th>
          <th>Contact</th>
          <th>Area</th>
          <th>Address</th>
          <th>Payment Method</th>
          <th width="140">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($rows) > 0): ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= $row['id'] ?></td>
              <td><?= htmlspecialchars($row['code']) ?></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= htmlspecialchars($row['nic']) ?></td>
              <td><?= htmlspecialchars($row['contact']) ?></td>
              <td><?= htmlspecialchars($row['area_name']) ?></td>
              <td><?= nl2br(htmlspecialchars($row['address'])) ?></td>
              <td>
                <?php if ($row['payment_method'] == 'bank'): ?>
                  <span class="badge bg-info text-dark">Bank</span>
                <?php else: ?>
                  <span class="badge bg-success">Cash</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <a class="btn btn-sm btn-primary" href="farmer_edit.php?id=<?= $row['id'] ?>">Edit</a>
                <a class="btn btn-sm btn-danger" href="farmer_delete.php?id=<?= $row['id'] ?>" onclick="return confirm('Delete this farmer?')">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="9" class="text-center text-muted">No farmers found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
