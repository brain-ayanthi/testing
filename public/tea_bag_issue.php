<?php
require_once __DIR__ . '/../init.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Tea Bag Issue</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
  body { background:#f8f9fa; }
  table th, table td { vertical-align:middle; text-align:center; }
  .select2-container { width:100%!important; }
</style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-3">
  <h4 class="fw-bold text-primary mb-3">Tea Bag Issue</h4>

  <div class="row mb-3">
    <div class="col-md-3">
      <label class="form-label">Date</label>
      <input type="date" id="issue_date" value="<?= date('Y-m-d') ?>" class="form-control">
    </div>
  </div>

  <!-- Entry Table -->
  <table class="table table-bordered bg-white" id="issueTable">
    <thead class="table-light">
      <tr>
        <th>Farmer Code</th>
        <th>Farmer Name</th>
        <th>Unit Price</th>
        <th>Quantity</th>
        <th>Subtotal</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody id="issueBody">
      <tr class="issueRow">
        <td><select class="form-select farmerCode"></select></td>
        <td><select class="form-select farmerName"></select></td>
        <td><input type="number" step="0.01" class="form-control unitPrice" value="0.00"></td>
        <td class="d-flex justify-content-center align-items-center">
          <button class="btn btn-sm btn-outline-danger decQty">−</button>
          <input type="number" step="1" class="form-control mx-2 text-center qty" style="width:80px" value="1.00">
          <button class="btn btn-sm btn-outline-success incQty">+</button>
        </td>
        <td class="subtotal">0.00</td>
        <td><button class="btn btn-success addRow">+</button></td>
      </tr>
    </tbody>
  </table>

  <div class="text-end">
    <button class="btn btn-primary mt-2" id="saveBtn">Save</button>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function(){

  function initSelect2(row) {
    // Remove previous select2 if cloned
    row.find('.select2-container').remove();

    // Farmer Code
    row.find('.farmerCode').select2({
      placeholder: 'Search Farmer Code...',
      ajax: {
        url: 'search_farmer_tea.php',
        dataType: 'json',
        delay: 250,
        data: params => ({ q: params.term, type: 'code' }),
        processResults: data => ({ results: data })
      }
    });

    // Farmer Name
    row.find('.farmerName').select2({
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

  // Initialize first row
  initSelect2($('.issueRow'));

  // Two-way sync
  $(document).on('select2:select', '.farmerCode', function(e){
    const data = e.params.data;
    const row = $(this).closest('tr');
    row.find('.farmerName').empty().append(new Option(data.text_name, data.id, true, true)).trigger('change.select2');
  });

  $(document).on('select2:select', '.farmerName', function(e){
    const data = e.params.data;
    const row = $(this).closest('tr');
    row.find('.farmerCode').empty().append(new Option(data.text_code, data.id, true, true)).trigger('change.select2');
  });

  // Add row
  $(document).on('click', '.addRow', function(){
    const newRow = $('#issueBody tr:first').clone(false);
    newRow.find('input').val('0.00');
    newRow.find('.qty').val('1.00');
    newRow.find('.subtotal').text('0.00');
    newRow.find('.farmerCode, .farmerName').empty(); // Clear selects

    newRow.find('.addRow')
      .removeClass('btn-success addRow')
      .addClass('btn-danger removeRow')
      .text('−');

    $('#issueBody').append(newRow);
    initSelect2(newRow);
  });

  // Remove row
  $(document).on('click', '.removeRow', function(){
    $(this).closest('tr').remove();
  });

  // Quantity increment/decrement
  $(document).on('click', '.incQty', function(){
    const input = $(this).siblings('.qty');
    input.val((parseFloat(input.val()) + 1).toFixed(2)).trigger('input');
  });

  $(document).on('click', '.decQty', function(){
    const input = $(this).siblings('.qty');
    const val = parseFloat(input.val());
    if(val > 1) input.val((val - 1).toFixed(2)).trigger('input');
  });

  // Subtotal update
  $(document).on('input', '.unitPrice, .qty', function(){
    const row = $(this).closest('tr');
    const unit = parseFloat(row.find('.unitPrice').val()) || 0;
    const qty = parseFloat(row.find('.qty').val()) || 0;
    const sub = unit * qty;
    row.find('.subtotal').text(sub.toFixed(2));
  });

  // Save
  $('#saveBtn').click(function(){
    const issue_date = $('#issue_date').val();
    let rows = [];
    $('#issueBody tr').each(function(){
      const f = $(this).find('.farmerCode').val();
      const u = $(this).find('.unitPrice').val();
      const q = $(this).find('.qty').val();
      if(f && u > 0 && q > 0){
        rows.push({farmer_id:f, unit_price:u, quantity:q});
      }
    });
    if(rows.length === 0){ alert('No data to save'); return; }

    $.post('tea_bag_issue_save.php', {
      issue_date: issue_date,
      data: JSON.stringify(rows)
    }, res=>{
      alert(res.message);
      if(res.status) location.reload();
    }, 'json');
  });
});
</script>
</body>
</html>
