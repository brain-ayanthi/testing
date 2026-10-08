<?php
require_once __DIR__.'/../init.php';
require_login();
require_once __DIR__.'/../auth/auth.php';

$user = $_SESSION['user'];

// 1. Total Farmers
$totalFarmers = $pdo->query("SELECT COUNT(*) FROM farmers")->fetchColumn();

// 2. Total Collections
$totalCollections = $pdo->query("SELECT COUNT(*) FROM collections")->fetchColumn();

// 3. Today's Weight
$q = $pdo->prepare("SELECT SUM(weight_kg) FROM collections WHERE collection_date = CURDATE()");
$q->execute();
$todayWeight = $q->fetchColumn();

// 4. Month's Weight
$q = $pdo->prepare("SELECT SUM(weight_kg) 
                    FROM collections 
                    WHERE DATE_FORMAT(collection_date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')");
$q->execute();
$monthWeight = $q->fetchColumn();

// 5. Month’s Collection Farmers Count
$q = $pdo->prepare("SELECT COUNT(DISTINCT farmer_id) 
                    FROM collections 
                    WHERE DATE_FORMAT(collection_date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')");
$q->execute();
$monthFarmerCount = $q->fetchColumn();

// 6. Total Saving Amount
$totalSaving = $pdo->query("SELECT SUM(amount) FROM saving WHERE status='agree'")->fetchColumn();
if (!$totalSaving) $totalSaving = 0;

// 7. Total Saving Farmers
$totalSavingFarmers = $pdo->query("SELECT COUNT(DISTINCT farmer_id) FROM saving WHERE amount > 0 AND status='agree'")->fetchColumn();

// 8. Total Areas
$totalAreas = $pdo->query("SELECT COUNT(*) FROM areas")->fetchColumn();
?>

<!doctype html>
<html lang="en">
<head>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard - TeaFactory</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<style>
.card-box {
    color: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.card-icon {
    font-size: 35px;
    opacity: 0.8;
}
.card-value {
    font-size: 30px;
    font-weight: bold;
    margin-top: 10px;
}
</style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>
<div class="container-fluid mt-3">
<div class="row">

    <div class="col-md-3 mb-3">
        <div class="card-box" style="background: linear-gradient(45deg, #36D1DC, #5B86E5);">
            <div class="d-flex justify-content-between">
                <h6>Total Farmers</h6>
                <i class="card-icon bi bi-people"></i>
            </div>
            <div class="card-value"><?= $totalFarmers ?></div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card-box" style="background: linear-gradient(45deg, #FF512F, #DD2476);">
            <div class="d-flex justify-content-between">
                <h6>Today's Weight (kg)</h6>
                <i class="card-icon bi bi-speedometer2"></i>
            </div>
            <div class="card-value"><?= $todayWeight ?: 0 ?></div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card-box" style="background: linear-gradient(45deg, #1D976C, #93F9B9);">
            <div class="d-flex justify-content-between">
                <h6>This Month Weight (kg)</h6>
                <i class="card-icon bi bi-bar-chart"></i>
            </div>
            <div class="card-value"><?= $monthWeight ?: 0 ?></div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card-box" style="background: linear-gradient(45deg, #7F00FF, #E100FF);">
            <div class="d-flex justify-content-between">
                <h6>Month's Collection Farmers</h6>
                <i class="card-icon bi bi-people-fill"></i>
            </div>
            <div class="card-value"><?= $monthFarmerCount ?></div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card-box" style="background: linear-gradient(45deg, #00B4DB, #0083B0);">
            <div class="d-flex justify-content-between">
                <h6>Total Collections</h6>
                <i class="card-icon bi bi-basket"></i>
            </div>
            <div class="card-value"><?= $totalCollections ?></div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card-box" style="background: linear-gradient(45deg, #f7971e, #ffd200);">
            <div class="d-flex justify-content-between">
                <h6>Total Saving (Rs)</h6>
                <i class="card-icon bi bi-piggy-bank"></i>
            </div>
            <div class="card-value"><?= number_format($totalSaving,2) ?></div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card-box" style="background: linear-gradient(45deg, #00c6ff, #0072ff);">
            <div class="d-flex justify-content-between">
                <h6>Total Saving Farmers</h6>
                <i class="card-icon bi bi-person-check"></i>
            </div>
            <div class="card-value"><?= $totalSavingFarmers ?></div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card-box" style="background: linear-gradient(45deg, #ff9966, #ff5e62);">
            <div class="d-flex justify-content-between">
                <h6>Total Areas</h6>
                <i class="card-icon bi bi-map"></i>
            </div>
            <div class="card-value"><?= $totalAreas ?></div>
        </div>
    </div>

</div>


  <div class="row mt-3">
    <div class="col-lg-8">
      <div class="card p-3">
        <h5>Recent Collections</h5>
        <div class="table-responsive">
          <table class="table table-striped">
            <thead><tr><th>#</th><th>Farmer</th><th>Date</th><th>Weight</th><th>Area</th><th>Agent</th></tr></thead>
            <tbody>
            <?php $stmt = $pdo->query('SELECT c.*,f.name as farmer_name,a.name as area_name FROM collections c LEFT JOIN farmers f ON c.farmer_id=f.id LEFT JOIN areas a ON c.area_id=a.id ORDER BY c.collection_date DESC LIMIT 10');
            while($r=$stmt->fetch()): ?>
              <tr>
                <td><?php echo $r['id']; ?></td>
                <td><?php echo htmlspecialchars($r['farmer_name']); ?></td>
                <td><?php echo $r['collection_date']; ?></td>
                <td><?php echo $r['weight_kg']; ?></td>
                <td><?php echo htmlspecialchars($r['area_name']); ?></td>
                <td><?php echo htmlspecialchars($r['agent_name'] ?? ''); ?></td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card p-3">
        <h5>Quick Actions</h5>
        <a class="btn btn-primary mb-2 w-100" href="collections.php">New Collection</a>
        <a class="btn btn-outline-secondary mb-2 w-100" href="farmers.php">Manage Farmers</a>
        <a class="btn btn-outline-secondary mb-2 w-100" href="rates.php">Area Rates</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>




