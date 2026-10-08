<?php
require_once __DIR__ . '/../init.php';
require_login();

// ✅ Handle success message from previous save
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

// ✅ Date picker default
$date = $_GET['date'] ?? date('Y-m-d');

// --- Fetch Areas ---
$sql = "SELECT a.id, a.name, SUM(c.weight_kg) AS total_weight
        FROM collections c
        JOIN areas a ON c.area_id = a.id
        WHERE DATE(c.collection_date) = :date
        GROUP BY a.id, a.name
        ORDER BY a.name";
$stmt = $pdo->prepare($sql);
$stmt->execute([':date' => $date]);
$areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Factories ---
$factories = $pdo->query("SELECT id, factory_name FROM factory ORDER BY factory_name")->fetchAll(PDO::FETCH_ASSOC);

// --- Saved allocations ---
$qAlloc = $pdo->prepare("
    SELECT d.*, f.factory_name 
    FROM daily_distributions d
    JOIN factory f ON d.factory_id = f.id
    WHERE d.distribution_date = :d
");
$qAlloc->execute([':d' => $date]);
$saved = $qAlloc->fetchAll(PDO::FETCH_ASSOC);

// --- Totals ---
$totalWeight = array_sum(array_column($areas, 'total_weight'));
$total_alloc = array_sum(array_column($saved, 'allocated_weight'));
$total_extra = array_sum(array_column($saved, 'extra_weight'));
$total_bag = array_sum(array_column($saved, 'bag'));

// ✅ Auto info message when loading saved date
if (!$success_message && !empty($saved)) {
    $success_message = "Distribution already saved for this date.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Daily Leaf Distribution</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#f8f9fa; }
  table th, table td { text-align:center; vertical-align:middle; }
  .extra-weight { background-color:#fff3cd; }
  #successAlert { transition: opacity 1s ease-in-out; }
</style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>
<div class="container mt-3">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold text-primary">Daily Leaf Distribution — <?= htmlspecialchars($date) ?></h4>
    <form method="get" class="d-flex">
      <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" class="form-control me-2" style="width:180px">
      <button class="btn btn-primary">Load</button>
    </form>
  </div>

  <!-- ✅ Success or info message -->
  <?php if ($success_message): ?>
  <div id="successAlert" class="alert 
      <?= (str_contains($success_message, 'already')) ? 'alert-info' : 'alert-success' ?> 
      text-center">
    <?= htmlspecialchars($success_message) ?>
  </div>
  <?php endif; ?>

  <!-- Areas Summary -->
  <table class="table table-bordered bg-white">
    <thead class="table-light">
      <tr>
        <?php foreach ($areas as $a): ?>
          <th><?= htmlspecialchars($a['name']) ?></th>
        <?php endforeach; ?>
        <th>Total Weight (kg)</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <?php foreach ($areas as $a): ?>
          <td><?= number_format($a['total_weight'],2) ?> kg</td>
        <?php endforeach; ?>
        <td><strong><?= number_format($totalWeight,2) ?> kg</strong></td>
      </tr>
    </tbody>
  </table>

  <!-- Allocations Form -->
  <div class="card mt-4 shadow-sm">
    <div class="card-header fw-semibold">Allocations (Factory → Weight)</div>
    <div class="card-body">
      <table class="table table-bordered" id="allocTable">
        <thead class="table-light">
          <tr>
            <th>Factory</th>
            <th>Allocated Weight (kg)</th>
            <th>Bag</th>
            <th>Extra Weight (kg)</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="allocBody">
          <tr class="factory-row">
            <td>
              <select name="factory_id[]" class="form-select">
                <option value="">Select factory</option>
                <?php foreach ($factories as $f): ?>
                  <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['factory_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="number" step="0.01" name="allocated_weight[]" class="form-control text-end allocInput" placeholder="e.g. 2000Kg"></td>
            <td><input type="number" step="0.01" name="bag[]" class="form-control text-end bagInput" placeholder="Bag"></td>
            <td><input type="number" step="0.01" name="extra_weight[]" class="form-control text-end extraInput" readonly></td>
            <td><button type="button" class="btn btn-success addRow">+</button></td>
          </tr>
        </tbody>
      </table>

      <div class="mt-3">
        <strong>Total Weight (kg):</strong> <?= number_format($totalWeight,2) ?> |
        <strong>Allocated Sum:</strong> <span id="allocSum">0</span> kg |
        <strong>Extra Total:</strong> <span id="extraTotal">0</span> kg
      </div>

      <button class="btn btn-primary mt-3" id="saveBtn">💾 Save Distribution</button>
    </div>
  </div>

  <!-- Saved Data -->
  <?php if (!empty($saved)): ?>
  <div class="card mt-4 shadow-sm">
    <div class="card-header bg-light fw-semibold">Saved Distributions for <?= htmlspecialchars($date) ?></div>
    <div class="card-body table-responsive">
      <table class="table table-bordered">
        <thead class="table-light">
          <tr>
            <th>Factory</th>
            <th>Allocated (kg)</th>
			  <th>Bag</th>
            <th>Total (kg)</th>
			 <th>Extra</th>
			  <th>Action</th>
          </tr>
        </thead>
      <tbody>
  <?php foreach ($saved as $s): ?>
  <tr data-id="<?= htmlspecialchars($s['id']) ?>">
    <td><?= htmlspecialchars($s['factory_name']) ?></td>
    <td><?= number_format($s['allocated_weight'],2) ?></td>
    <td><?= number_format($s['bag'],2) ?></td>
    <td><strong><?= number_format($s['allocated_weight'] + $s['extra_weight'],2) ?></strong></td>
    <td><?= number_format($s['extra_weight'],2) ?></td>
    <td>
      <button type="button" class="btn btn-sm btn-danger deleteRow">🗑 Delete</button>
    </td>
  </tr>
  <?php endforeach; ?>
</tbody>

        <tfoot class="table-light">
          <tr>
            <th>Total</th>
            <th><?= number_format($total_alloc,2) ?></th>
			<th><?= number_format($total_bag,2) ?></th>
            <th><?= number_format($total_alloc + $total_extra,2) ?></th>
			 <th><?= number_format($total_extra,2) ?></th>
			 <th></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(function(){
  const total = <?= $totalWeight ?>;

  // ✅ Recalculate totals and extra automatically
function recalc(){
  let sum = 0;
  $('.allocInput').each(function(){
    sum += parseFloat($(this).val()) || 0;
  });

  $('#allocSum').text(sum.toFixed(2));
  $('#remaining').text((total - sum).toFixed(2));

  // 🧮 Calculate extra (positive or negative)
  let extraTotal = sum - total;
  $('#extraTotal').text(extraTotal.toFixed(2));

  // Distribute extra equally among all rows (can be + or -)
  let rows = $('.allocInput').length;
  let perRow = extraTotal / rows;
  $('.extraInput').val(perRow.toFixed(2));
}


  $(document).on('input','.allocInput',recalc);

  $(document).on('click','.addRow',function(){
    let row = $(this).closest('tr').clone();
    row.find('input').val('');
    row.find('select').val('');
    row.find('.addRow')
      .removeClass('btn-success addRow')
      .addClass('btn-danger removeRow')
      .text('-');
    $('#allocBody').append(row);
  });

  $(document).on('click','.removeRow',function(){
    $(this).closest('tr').remove();
    recalc();
  });

  // ✅ Save distribution (fixed with bag)
  $('#saveBtn').click(function(){
    let data = [];
    $('#allocBody tr').each(function(){
      let f = $(this).find('select').val();
      let w = $(this).find('.allocInput').val();
      let b = $(this).find('.bagInput').val();
      let e = $(this).find('.extraInput').val();
      if(f && w) data.push({factory:f, weight:w, bag:b, extra:e});
    });

    if(data.length == 0) { alert('Enter at least one allocation'); return; }

    $.post('daily_distribution_save.php',{
      date:'<?= $date ?>',
      allocations: JSON.stringify(data)
    },function(res){
      if(res.status){
        sessionStorage.setItem('successMsg', 'Distribution saved successfully');
        location.reload();
      } else {
        alert(res.message || 'Error saving data');
      }
    },'json');
  });

  // ✅ Show saved message even after reload
  const msg = sessionStorage.getItem('successMsg');
  if(msg){
    $('<div class="alert alert-success text-center">'+msg+'</div>')
      .prependTo('.container');
    sessionStorage.removeItem('successMsg');
  }

  setTimeout(()=>$('#successAlert').fadeOut(), 4000);
});

$(document).on('click', '.deleteRow', function(){
  const row = $(this).closest('tr');
  const id = row.data('id');
  console.log('Deleting ID:', id); // 🔍 check in browser console

  if(!confirm('Are you sure you want to delete this record?')) return;

  $.post('daily_distribution_delete.php', { id: id }, function(res){
    if(res.status){
      row.fadeOut(300, function(){ $(this).remove(); });
    } else {
      alert(res.message || 'Error deleting record');
    }
  }, 'json');
});




</script>
</body>
</html>
