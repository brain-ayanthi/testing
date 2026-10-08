<?php
require_once __DIR__ . '/../init.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Advance Issue</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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
  <h4 class="fw-bold text-primary mb-3">Advance Issue Add</h4>

  <!-- Date -->
  <div class="row mb-3">
    <div class="col-md-3">
      <label class="form-label">Date</label>
      <input type="date" id="advance_date" value="<?= date('Y-m-d') ?>" class="form-control">
    </div>
  </div>

  <!-- Entry Table -->
  <table class="table table-bordered bg-white" id="advanceTable">
    <thead class="table-light">
      <tr>
        <th>Farmer Code</th>
        <th>Farmer Name</th>
        <th>Amount (Rs)</th>
        <th>Note</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody id="advanceBody">
      <tr class="advanceRow">
        <td><select class="form-select farmerCode"></select></td>
        <td><select class="form-select farmerName"></select></td>
        <td><input type="number" step="0.01" class="form-control amount" value="0.00"></td>
        <td><input type="text" class="form-control note" placeholder="Enter note"></td>
        <td><button class="btn btn-success addRow">+</button></td>
      </tr>
    </tbody>
  </table>

  <div class="text-end">
    <button class="btn btn-primary mt-2" id="saveBtn">💾 Save</button>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(function(){

  // === Initialize Select2 ===
  function initSelect2(row){
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

  initSelect2($('.advanceRow'));

  // === Two-way sync ===
  $(document).on('select2:select', '.farmerCode', function(e){
    const data = e.params.data;
    const row = $(this).closest('tr');
    row.find('.farmerName').html(`<option value="${data.id}" selected>${data.text_name}</option>`).trigger('change.select2');
  });

  $(document).on('select2:select', '.farmerName', function(e){
    const data = e.params.data;
    const row = $(this).closest('tr');
    row.find('.farmerCode').html(`<option value="${data.id}" selected>${data.text_code}</option>`).trigger('change.select2');
  });

  // === Add Row ===
  $(document).on('click', '.addRow', function(){
    const newRow = $('#advanceBody tr:first').clone();
    newRow.find('input').val('');
    newRow.find('.amount').val('0.00');
    newRow.find('.farmerCode, .farmerName').empty();
    newRow.find('.addRow')
      .removeClass('btn-success addRow')
      .addClass('btn-danger removeRow')
      .text('−');
    $('#advanceBody').append(newRow);
    initSelect2(newRow);
  });

  // === Remove Row ===
  $(document).on('click', '.removeRow', function(){
    $(this).closest('tr').remove();
  });

  // === Save Button ===
  $('#saveBtn').click(function(){
    const date = $('#advance_date').val();
    let rows = [];

    $('#advanceBody tr').each(function(){
      const farmer_id = $(this).find('.farmerCode').val();
      const amount = parseFloat($(this).find('.amount').val()) || 0;
      const note = $(this).find('.note').val().trim();
      if(farmer_id && amount > 0){
        rows.push({ farmer_id, amount, note });
      }
    });

    if(rows.length === 0){ alert('Please add at least one valid entry.'); return; }

    $.ajax({
      url: 'advance_issue_save.php',
      type: 'POST',
      dataType: 'json', // IMPORTANT FIX ✅
      data: { date: date, data: JSON.stringify(rows) },
      success: function(res){
        if(res.status){
          alert(res.message);
          location.reload();
        } else {
          alert('❌ ' + res.message);
        }
      },
      error: function(xhr){
        alert('❌ Server error. ' + xhr.responseText);
      }
    });
  });

});
</script>
</body>
</html>
