<?php
require_once __DIR__.'/../init.php';
require_login();

$areas = $pdo->query('SELECT * FROM areas ORDER BY name')->fetchAll();
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $nic = trim($_POST['nic']);
    $contact = trim($_POST['contact']);
    $area_id = intval($_POST['area_id']);
    $address = trim($_POST['address']);
    $payment_method = $_POST['payment_method'] ?? 'cash';

    if ($name == '') $err = 'Name required';

    if (!$err) {
        $stmt = $pdo->prepare('INSERT INTO farmers 
            (code, name, nic, contact, area_id, address, payment_method, created_at)
            VALUES (:code, :name, :nic, :contact, :area, :address, :payment_method, NOW())');
        $stmt->execute([
            ':code' => $code,
            ':name' => $name,
            ':nic' => $nic,
            ':contact' => $contact,
            ':area' => $area_id,
            ':address' => $address,
            ':payment_method' => $payment_method
        ]);
        header('Location: farmers.php');
        exit;
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Add Farmer</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-3">
  <h4>Add Farmer</h4>
  <?php if($err): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="row">
      <div class="col-md-4 mb-2">
        <label>Code</label>
        <input name="code" class="form-control">
      </div>

      <div class="col-md-4 mb-2">
        <label>Name</label>
        <input name="name" required class="form-control">
      </div>

      <div class="col-md-4 mb-2">
        <label>NIC</label>
        <input name="nic" class="form-control">
      </div>

      <div class="col-md-4 mb-2">
        <label>Contact</label>
        <input name="contact" class="form-control">
      </div>

      <div class="col-md-4 mb-2">
        <label>Area</label>
        <select name="area_id" class="form-control">
          <option value="0">--select--</option>
          <?php foreach($areas as $ar): ?>
            <option value="<?= $ar['id'] ?>"><?= htmlspecialchars($ar['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-8 mb-2">
        <label>Address</label>
        <textarea name="address" class="form-control" rows="2"></textarea>
      </div>

      <div class="col-md-4 mb-2">
        <label>Payment Method</label>
        <select name="payment_method" class="form-select">
          <option value="cash">Cash</option>
          <option value="bank">Bank</option>
        </select>
      </div>
    </div>

    <button class="btn btn-primary mt-3">Save</button>
  </form>
</div>

</body>
</html>
