<?php
require_once __DIR__ . '/../init.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM factory WHERE id = :id");
$stmt->execute([':id' => $id]);
$factory = $stmt->fetch();
if (!$factory) { die("Factory not found."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['factory_name']);
  if ($name !== '') {
    $stmt = $pdo->prepare("UPDATE factory SET factory_name = :n WHERE id = :id");
    $stmt->execute([':n'=>$name, ':id'=>$id]);
    header("Location: factory_list.php?updated=1");
    exit;
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Edit Factory</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'partials/topnav.php'; ?>
<div class="container mt-4">
  <h4>Edit Factory</h4>
  <form method="post" class="card p-3 shadow-sm">
    <div class="mb-3">
      <label class="form-label">Factory Name</label>
      <input type="text" name="factory_name" value="<?= htmlspecialchars($factory['factory_name']) ?>" class="form-control" required>
    </div>
    <button class="btn btn-primary">Update</button>
    <a href="factory_list.php" class="btn btn-secondary">Cancel</a>
  </form>
</div>
</body>
</html>
