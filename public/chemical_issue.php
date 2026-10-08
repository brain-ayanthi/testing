<?php
require_once __DIR__ . '/../init.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Chemical Issue</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
  body { background:#f8f9fa; }
  .select2-container { width:100%!important; }
  table th, table td { text-align:center; vertical-align:middle; }
</style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold text-primary">Chemical Issue</h4>
    
  </div>

  <!-- Date + Farmer -->
  <div class="row mb-3">
    <div class="col-md-3">
      <label class="form-label">Date</label>
      <input type="date" id="issue_date" value="<?= date('Y-m-d') ?>" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">Farmer Code</label>
      <select id="farmerCode" class="form-select"></select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Farmer Name</label>
      <select id="farmerName" class="form-select"></select>
    </div>
  </div>

  <!-- Chemical Table -->
  <table class="table table-bordered bg-white" id="chemicalTable">
    <thead class="table-light">
      <tr>
        <th>Chemical Name</th>
        <th>Price (Rs)</th>
        <th>Quantity</th>
        <th>Total (Rs)</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody id="chemBody">
      <tr class="chemRow">
        <td><input type="text" class="form-control chemName" placeholder="Enter chemical name"></td>
        <td><input type="number" step="0.01" class="form-control price" value="0.00"></td>
        <td><input type="number" step="0.01" class="form-control qty" value="0.00"></td>
        <td><input type="text" class="form-control total" value="0.00" readonly></td>
        <td><button type="button" class="btn btn-success addRow">+</button></td>
      </tr>
    </tbody>
  </table>

  <div class="row mb-3">
    <div class="col-md-6">
      <label class="form-label">Note</label>
      <input type="text" id="note" class="form-control" placeholder="Enter note (optional)">
    </div>
    <div class="col-md-3 offset-md-3 text-end">
      <h5>Subtotal (Rs): <span id="subtotal" class="text-success fw-bold">0.00</span></h5>
    </div>
  </div>

  <div class="text-end">
    <button type="button" class="btn btn-primary" id="saveBtn">💾 Save</button>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function(){

  // --- Initialize Select2 ---
  function initSelects(){
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
  }
  initSelects();

  // --- Two-way sync between code and name ---
  $('#farmerCode').on('select2:select', function(e){
    const d = e.params.data;
    $('#farmerName').html(`<option value="${d.id}" selected>${d.text_name}</option>`).trigger('change');
  });
  $('#farmerName').on('select2:select', function(e){
    const d = e.params.data;
    $('#farmerCode').html(`<option value="${d.id}" selected>${d.text_code}</option>`).trigger('change');
  });

  // --- Add new chemical row ---
  $(document).on('click', '.addRow', function(){
    const row = $('#chemBody tr:first').clone();
    row.find('input').val('0.00');
    row.find('.chemName').val('');
    row.find('.addRow')
        .removeClass('btn-success addRow')
        .addClass('btn-danger removeRow')
        .text('−');
    $('#chemBody').append(row);
  });

  // --- Remove chemical row ---
  $(document).on('click', '.removeRow', function(){
    $(this).closest('tr').remove();
    updateSubtotal();
  });

  // --- Auto calculate totals ---
  $(document).on('input', '.price, .qty', function(){
    const row = $(this).closest('tr');
    const price = parseFloat(row.find('.price').val()) || 0;
    const qty = parseFloat(row.find('.qty').val()) || 0;
    const total = (price * qty).toFixed(2);
    row.find('.total').val(total);
    updateSubtotal();
  });

  function updateSubtotal(){
    let subtotal = 0;
    $('.total').each(function(){
      subtotal += parseFloat($(this).val()) || 0;
    });
    $('#subtotal').text(subtotal.toFixed(2));
  }

  // --- Save to database ---
  // Save
$('#saveBtn').click(function(){
  const farmer_id = $('#farmerCode').val();
  const date = $('#issue_date').val();
  const note = $('#note').val();
  let items = [];

  $('#chemBody tr').each(function(){
    const chem = $(this).find('.chemName').val().trim();
    const price = parseFloat($(this).find('.price').val()) || 0;
    const qty = parseFloat($(this).find('.qty').val()) || 0;
    const total = parseFloat($(this).find('.total').val()) || 0;
    if(chem && qty > 0){
      items.push({ name: chem, price, qty, total });
    }
  });

  if(!farmer_id){ alert('Please select a farmer.'); return; }
  if(items.length === 0){ alert('Add at least one chemical.'); return; }

  $.ajax({
    url: 'chemical_issue_save.php',
    type: 'POST',
    data: {
      farmer_id, date, note,
      subtotal: $('#subtotal').text(),
      data: JSON.stringify(items)
    },
    dataType: 'json',
    success: function(res){
      if(res.status){
        alert(res.message);
        location.reload();
      } else {
        alert(res.message);
      }
    },
    error: function(xhr){
      console.error(xhr.responseText);
      alert('❌ Server error — invalid response.');
    }
  });
});


});
</script>
</body>
</html>
