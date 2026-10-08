<?php
require_once __DIR__ . '/../init.php';
require_login();
global $pdo;

// Get issue ID
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
  die("Invalid chemical issue ID.");
}

// Fetch main record
$stmt = $pdo->prepare("
  SELECT c.*, f.name AS farmer_name, f.code AS farmer_code
  FROM chemical_issue c
  LEFT JOIN farmers f ON c.farmer_id = f.id
  WHERE c.id = ?
");
$stmt->execute([$id]);
$issue = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$issue) {
  die("Record not found.");
}

// Fetch item rows
$item_stmt = $pdo->prepare("SELECT * FROM chemical_issue_items WHERE issue_id = ?");
$item_stmt->execute([$id]);
$items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Chemical Issue</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
  body { background:#f8f9fa; }
  .table td input { width:100%; }
</style>
</head>
<body>

<?php include 'partials/topnav.php'; ?>

<div class="container mt-3">
  <h4 class="fw-bold text-primary mb-4"> Edit Chemical Issue</h4>

  <form id="issueForm">
    <input type="hidden" name="id" value="<?= htmlspecialchars($issue['id']) ?>">

    <div class="row mb-3">
      <div class="col-md-3">
        <label class="form-label">Farmer Code</label>
        <select name="farmer_id" id="farmer_code" class="form-select select2"></select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Farmer Name</label>
        <select id="farmer_name" class="form-select select2"></select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Date</label>
        <input type="date" name="issue_date" value="<?= htmlspecialchars($issue['issue_date']) ?>" class="form-control" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Note</label>
        <input type="text" name="note" value="<?= htmlspecialchars($issue['note']) ?>" class="form-control">
      </div>
    </div>

    <table class="table table-bordered" id="chemTable">
      <thead class="table-light">
        <tr>
          <th>Chemical Name</th>
          <th>Price (Rs)</th>
          <th>Quantity</th>
          <th>Total (Rs)</th>
          <th width="60">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $i => $row): ?>
          <tr>
            <td><input type="text" name="chemical_name[]" value="<?= htmlspecialchars($row['chemical_name']) ?>" class="form-control" required></td>
            <td><input type="number" name="price[]" value="<?= htmlspecialchars($row['price']) ?>" step="0.01" class="form-control price" required></td>
            <td><input type="number" name="quantity[]" value="<?= htmlspecialchars($row['quantity']) ?>" step="0.01" class="form-control qty" required></td>
            <td><input type="number" name="total[]" value="<?= htmlspecialchars($row['total']) ?>" step="0.01" class="form-control total" readonly></td>
            <td><button type="button" class="btn btn-danger btn-sm removeRow">×</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4" class="text-end fw-bold">Subtotal (Rs):</td>
          <td><input type="number" name="subtotal" id="subtotal" value="<?= htmlspecialchars($issue['subtotal']) ?>" class="form-control" readonly></td>
        </tr>
      </tfoot>
    </table>

    <button type="button" class="btn btn-secondary mb-3" id="addRow">➕ Add Chemical</button>

    <div class="d-flex justify-content-between">
      <a href="chemical_issue_list.php" class="btn btn-outline-secondary">← Back</a>
      <button type="submit" class="btn btn-primary">💾 Update</button>
    </div>
  </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function(){

  // Initialize Select2 for farmers
  $('#farmer_code, #farmer_name').select2({
    ajax: {
      url: 'get_farmers.php',
      dataType: 'json',
      delay: 200,
      data: function(params){ return { q: params.term }; },
      processResults: function(data){
        return { results: data.map(f => ({ id: f.id, text: f.code + ' - ' + f.name })) };
      }
    },
    placeholder: 'Select Farmer',
    width: '100%'
  });

  // Preselect farmer on load
  const preselected = {id: "<?= $issue['farmer_id'] ?>", text: "<?= $issue['farmer_code'].' - '.$issue['farmer_name'] ?>"};
  const opt = new Option(preselected.text, preselected.id, true, true);
  $('#farmer_code').append(opt).trigger('change');
  $('#farmer_name').append(opt).trigger('change');

  // Two-way sync
  $('#farmer_code').on('change', function(){
    $('#farmer_name').val($(this).val()).trigger('change');
  });
  $('#farmer_name').on('change', function(){
    $('#farmer_code').val($(this).val()).trigger('change');
  });

  // Add new row
  $('#addRow').on('click', function(){
    $('#chemTable tbody').append(`
      <tr>
        <td><input type="text" name="chemical_name[]" class="form-control" required></td>
        <td><input type="number" name="price[]" step="0.01" class="form-control price" required></td>
        <td><input type="number" name="quantity[]" step="0.01" class="form-control qty" required></td>
        <td><input type="number" name="total[]" step="0.01" class="form-control total" readonly></td>
        <td><button type="button" class="btn btn-danger btn-sm removeRow">×</button></td>
      </tr>
    `);
  });

  // Remove row
  $(document).on('click', '.removeRow', function(){
    $(this).closest('tr').remove();
    updateSubtotal();
  });

  // Calculate totals dynamically
  $(document).on('input', '.price, .qty', function(){
    const row = $(this).closest('tr');
    const price = parseFloat(row.find('.price').val()) || 0;
    const qty = parseFloat(row.find('.qty').val()) || 0;
    const total = price * qty;
    row.find('.total').val(total.toFixed(2));
    updateSubtotal();
  });

  function updateSubtotal(){
    let subtotal = 0;
    $('.total').each(function(){ subtotal += parseFloat($(this).val()) || 0; });
    $('#subtotal').val(subtotal.toFixed(2));
  }

  // Save (update)
$('#issueForm').on('submit', function(e){
  e.preventDefault();
  $.ajax({
    url: 'chemical_issue_update.php',
    type: 'POST',
    data: $(this).serialize(),
    dataType: 'json',
    success: function(res){
      if(res.status){
        alert('✅ ' + res.message);
        window.location.href = 'chemical_issue_list.php';
      } else {
        alert('❌ ' + res.message);
      }
    },
    error: function(xhr){
      alert('Server error:\n' + xhr.responseText);
    }
  });
});

});
</script>
</body>
</html>
