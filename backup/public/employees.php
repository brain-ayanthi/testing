<?php
require_once __DIR__.'/../init.php'; require_login();
$rows = $pdo->query('SELECT * FROM users ORDER BY name')->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Employees</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><?php include 'partials/topnav.php'; ?>
<div class="container mt-3">
  <div class="d-flex justify-content-between"><h4>Employees</h4><a class="btn btn-success" href="employee_add.php">Add</a></div>
  <div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Roles</th><th>Actions</th></tr></thead><tbody>
  <?php foreach($rows as $r): ?>
    <tr><td><?php echo $r['id']; ?></td><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo htmlspecialchars($r['email']); ?></td><td><?php echo htmlspecialchars($r['roles']); ?></td>
    <td><a class="btn btn-sm btn-primary" href="employee_edit.php?id=<?php echo $r['id']; ?>">Edit</a> <a class="btn btn-sm btn-danger" href="employee_delete.php?id=<?php echo $r['id']; ?>" onclick="return confirm('Delete?')">Delete</a></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div></body></html>
