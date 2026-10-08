<?php
require_once __DIR__ . '/../init.php';
require_login();
global $pdo;

// Fetch all farmers for dropdowns
$farmers = $pdo->query("SELECT id, code, name FROM farmers ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Other Charges / Payments</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
body { background:#f8f9fa; }
.card { max-width:700px; margin:auto; }
</style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0">➕ Add Other Charges / Payments</h5>
    </div>
    <div class="card-body">
      <form id="othersForm">
	  
	  
	  <div class="mb-3 row">
          <label class="col-sm-4 col-form-label">Date</label>
          <div class="col-sm-8">
            <input type="date" name="issue_date" id="issue_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
          </div>
        </div>
	  
	  
        <div class="mb-3 row">
          <label class="col-sm-4 col-form-label">Farmer Code</label>
          <div class="col-sm-8">
            <select id="farmer_code" name="farmer_code" class="form-select select2">
              <option value="">Select Code...</option>
              <?php foreach ($farmers as $f): ?>
                <option value="<?= $f['id'] ?>" data-name="<?= htmlspecialchars($f['name']) ?>">
                  <?= htmlspecialchars($f['code']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="mb-3 row">
          <label class="col-sm-4 col-form-label">Farmer Name</label>
          <div class="col-sm-8">
            <select id="farmer_name" name="farmer_name" class="form-select select2">
              <option value="">Select Name...</option>
              <?php foreach ($farmers as $f): ?>
                <option value="<?= $f['id'] ?>" data-code="<?= htmlspecialchars($f['code']) ?>">
                  <?= htmlspecialchars($f['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="mb-3 row">
          <label class="col-sm-4 col-form-label">Amount (Rs)</label>
          <div class="col-sm-8">
            <input type="number" step="0.01" name="amount" id="amount" class="form-control" required>
          </div>
        </div>

        <div class="mb-3 row">
          <label class="col-sm-4 col-form-label">Note</label>
          <div class="col-sm-8">
            <textarea name="note" id="note" class="form-control" rows="2" placeholder="Enter details..."></textarea>
          </div>
        </div>

        <div class="text-end">
          <button type="submit" class="btn btn-success">💾 Save</button>
          <a href="others_list.php" class="btn btn-secondary">View All</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function(){
  $('.select2').select2();

  // Two-way sync between farmer code and name
  $('#farmer_code').on('change', function(){
    let id = $(this).val();
    $('#farmer_name').val(id).trigger('change');
  });
  $('#farmer_name').on('change', function(){
    let id = $(this).val();
    $('#farmer_code').val(id).trigger('change');
  });

  // Save form
  $('#othersForm').on('submit', function(e){
    e.preventDefault();
    $.ajax({
      url: 'others_save.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function(res){
        if(res.status){
          alert(res.message);
          window.location.href = 'others_list.php';
        } else {
          alert('Save failed: ' + res.message);
        }
      },
      error: function(){
        alert('Server error.');
      }
    });
  });
});
</script>

</body>
</html>
