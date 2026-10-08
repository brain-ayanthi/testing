<?php
require_once __DIR__ . '/../init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['factory_name']);
  if ($name !== '') {
    $stmt = $pdo->prepare("INSERT INTO factory (factory_name, created_at) VALUES (:n, NOW())");
    $stmt->execute([':n' => $name]);
    header("Location: factory_list.php?added=1");
    exit;
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Add Factory</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'partials/topnav.php'; ?>
<div class="container mt-4">
  <h4>Add Factory</h4>
  <form method="post" class="card p-3 shadow-sm">
    <div class="mb-3">
      <label class="form-label">Factory Name</label>
      <input type="text" name="factory_name" class="form-control" required>
    </div>
    <button class="btn btn-primary">Save</button>
    <a href="factory_list.php" class="btn btn-secondary">Cancel</a>
  </form>
</div>
</body>
</html>
