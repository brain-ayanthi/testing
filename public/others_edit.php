<?php
require_once __DIR__ . '/../init.php';
require_login();
global $pdo;

$id = $_GET['id'] ?? 0;
if (!$id) {
  die("Invalid request");
}

// Fetch existing record
$stmt = $pdo->prepare("
  SELECT o.*, f.name AS farmer_name, f.code AS farmer_code 
  FROM others o 
  LEFT JOIN farmers f ON o.farmer_id = f.id
  WHERE o.id = ?
");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$data) die("Record not found");

// Get farmers list
$farmers = $pdo->query("SELECT id, code, name FROM farmers ORDER BY code ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Other Payment / Charge</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#f8f9fa; }
  label { font-weight:500; }
</style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Edit Other Payment / Charge</h5>
    </div>
    <div class="card-body">
      <form id="editForm">
        <input type="hidden" name="id" value="<?= htmlspecialchars($data['id']) ?>">

        <div class="row mb-3">
          <div class="col-md-6">
            <label>Farmer Code</label>
            <select name="farmer_id" id="farmer_id" class="form-select" required>
              <option value="">-- Select Farmer --</option>
              <?php foreach ($farmers as $f): ?>
                <option value="<?= $f['id'] ?>" 
                  data-code="<?= htmlspecialchars($f['code']) ?>"
                  data-name="<?= htmlspecialchars($f['name']) ?>"
                  <?= $f['id'] == $data['farmer_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($f['code']) ?> - <?= htmlspecialchars($f['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label>Farmer Name</label>
            <input type="text" id="farmer_name" class="form-control" readonly
                   value="<?= htmlspecialchars($data['farmer_name']) ?>">
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-4">
            <label>Amount (Rs)</label>
            <input type="number" step="0.01" name="amount" class="form-control" required
                   value="<?= htmlspecialchars($data['amount']) ?>">
          </div>
          <div class="col-md-4">
            <label>Paid (Rs)</label>
            <input type="number" step="0.01" name="paid" class="form-control"
                   value="<?= htmlspecialchars($data['paid']) ?>">
          </div>
          <div class="col-md-4">
            <label>Status</label>
            <select name="status" class="form-select" required>
              <option value="due" <?= $data['status']=='due'?'selected':'' ?>>Due</option>
              <option value="partial" <?= $data['status']=='partial'?'selected':'' ?>>Partial</option>
              <option value="paid" <?= $data['status']=='paid'?'selected':'' ?>>Paid</option>
            </select>
          </div>
        </div>

        <div class="mb-3">
          <label>Note</label>
          <textarea name="note" class="form-control" rows="3"><?= htmlspecialchars($data['note']) ?></textarea>
        </div>

        <div class="text-end">
          <button type="submit" class="btn btn-primary px-4">💾 Update</button>
          <a href="others_list.php" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$('#farmer_id').on('change', function(){
  const name = $(this).find('option:selected').data('name') || '';
  $('#farmer_name').val(name);
});

$('#editForm').on('submit', function(e){
  e.preventDefault();
  $.ajax({
    url: 'others_update.php',
    type: 'POST',
    data: $(this).serialize(),
    dataType: 'json',
    success: function(res){
      if(res.status){
        alert(res.message);
        window.location.href = 'others_list.php';
      } else {
        alert('❌ ' + res.message);
      }
    },
    error: function(){
      alert('Server error.');
    }
  });
});
</script>
</body>
</html>
