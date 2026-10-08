<?php
require_once __DIR__ . '/../init.php';
require_login();

// Fetch all areas
$areas = $pdo->query('SELECT * FROM areas ORDER BY name')->fetchAll();
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $area_id = intval($_POST['area_id']);
    $ym = trim($_POST['year_month']);
    $rate = floatval($_POST['rate_per_kg']);

    if ($area_id <= 0 || $ym == '') {
        $err = 'Area and period are required.';
    }

    if (!$err) {
        try {
            // Use backticks to fix SQL syntax issue with `year_month`
            $stmt = $pdo->prepare('INSERT INTO area_rates (area_id, `year_month`, rate_per_kg, created_at) 
                                   VALUES (:a, :ym, :r, NOW())');
            $stmt->execute([':a' => $area_id, ':ym' => $ym, ':r' => $rate]);

            header('Location: rates.php');
            exit;
        } catch (PDOException $e) {
            // If duplicate entry (same area and month)
            if ($e->getCode() == 23000) {
                $err = 'A rate for this area and period already exists.';
            } else {
                $err = 'Database error: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Area Rate | Tea Factory</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
      body { background-color: #f8f9fa; }
      .container { max-width: 720px; }
      .card { border-radius: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
  </style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>
<div class="container mt-4">
  <div class="card p-4">
    <h4 class="mb-3 text-success">Add Area Rate</h4>

    <?php if ($err): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Area</label>
          <select name="area_id" class="form-select" required>
            <option value="">-- Select Area --</option>
            <?php foreach ($areas as $a): ?>
              <option value="<?= htmlspecialchars($a['id']) ?>">
                <?= htmlspecialchars($a['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Period (YYYY-MM)</label>
          <input type="month" name="year_month" class="form-control" required>
        </div>

        <div class="col-md-3">
          <label class="form-label">Rate per kg</label>
          <input type="number" step="0.01" name="rate_per_kg" class="form-control" required value="0.00">
        </div>
      </div>

      <div class="mt-4">
        <button class="btn btn-success px-4" type="submit">Save Rate</button>
        <a href="rates.php" class="btn btn-secondary">Back</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
