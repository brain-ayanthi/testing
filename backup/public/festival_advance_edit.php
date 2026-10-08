<?php
require_once __DIR__ . '/../init.php';
require_login();
global $pdo;

$id = $_GET['id'] ?? 0;
if(!$id){
  die("Invalid record ID");
}

// Fetch advance record
$stmt = $pdo->prepare("
  SELECT a.*, f.code AS farmer_code, f.name AS farmer_name 
  FROM advances a
  LEFT JOIN farmers f ON a.farmer_id = f.id
  WHERE a.id = :id
");
$stmt->execute(['id'=>$id]);
$advance = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$advance){
  die("Record not found");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Festival Advance</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
  body { background:#f8f9fa; }
  .select2-container { width:100%!important; }
</style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold text-primary">Edit Festival Advance</h4>
    <a href="festival_advance_list.php" class="btn btn-secondary">⬅ Back</a>
  </div>

  <form id="editForm">
    <input type="hidden" name="id" value="<?= htmlspecialchars($advance['id']) ?>">

    <div class="row mb-3">
      <div class="col-md-3">
        <label class="form-label">Date</label>
        <input type="date" name="created_at" value="<?= htmlspecialchars($advance['created_at']) ?>" class="form-control" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Farmer Code</label>
        <select name="farmer_code" id="farmerCode" class="form-select"></select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Farmer Name</label>
        <select name="farmer_name" id="farmerName" class="form-select"></select>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-3">
        <label class="form-label">Amount (Rs)</label>
        <input type="number" name="amount" step="0.01" value="<?= htmlspecialchars($advance['amount']) ?>" class="form-control" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Paid (Rs)</label>
        <input type="number" name="paid" step="0.01" value="<?= htmlspecialchars($advance['paid']) ?>" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select" required>
          <option value="due" <?= $advance['status']=='due'?'selected':'' ?>>Due</option>
          <option value="partial" <?= $advance['status']=='partial'?'selected':'' ?>>Partial</option>
          <option value="paid" <?= $advance['status']=='paid'?'selected':'' ?>>Paid</option>
        </select>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Note</label>
      <input type="text" name="note" value="<?= htmlspecialchars($advance['note']) ?>" class="form-control" placeholder="Enter note (optional)">
    </div>

    <div class="text-end">
      <button type="submit" class="btn btn-primary">💾 Update</button>
    </div>
  </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function(){
  // Initialize Select2 for Code
  $('#farmerCode').select2({
    placeholder: 'Search Farmer Code...',
    ajax: {
      url: 'search_farmer_tea.php',
      dataType: 'json',
      delay: 250,
      data: params => ({ q: params.term, type: 'code' }),
      processResults: data => ({ results: data })
    }
  });

  // Initialize Select2 for Name
  $('#farmerName').select2({
    placeholder: 'Search Farmer Name...',
    ajax: {
      url: 'search_farmer_tea.php',
      dataType: 'json',
      delay: 250,
      data: params => ({ q: params.term, type: 'name' }),
      processResults: data => ({ results: data })
    }
  });

  // Set initial values
  const farmerId = <?= (int)$advance['farmer_id'] ?>;
  const farmerCode = <?= json_encode($advance['farmer_code']) ?>;
  const farmerName = <?= json_encode($advance['farmer_name']) ?>;

  if(farmerId){
    const optionCode = new Option(farmerCode, farmerId, true, true);
    const optionName = new Option(farmerName, farmerId, true, true);
    $('#farmerCode').append(optionCode).trigger('change');
    $('#farmerName').append(optionName).trigger('change');
  }

  // Two-way sync
  $('#farmerCode').on('select2:select', function(e){
    const data = e.params.data;
    $('#farmerName').html(`<option value="${data.id}" selected>${data.text_name}</option>`).trigger('change');
  });

  $('#farmerName').on('select2:select', function(e){
    const data = e.params.data;
    $('#farmerCode').html(`<option value="${data.id}" selected>${data.text_code}</option>`).trigger('change');
  });

  // Submit form
  $('#editForm').submit(function(e){
    e.preventDefault();
    $.post('festival_advance_update.php', $(this).serialize(), function(res){
      try {
        const data = JSON.parse(res);
        alert(data.message);
        if(data.status) location.href = 'festival_advance_list.php';
      } catch(err){
        alert('Update failed.');
      }
    });
  });
});
</script>
</body>
</html>
