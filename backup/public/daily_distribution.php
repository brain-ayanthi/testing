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
            <td><input type="number" step="0.01" name="allocated_weight[]" class="form-control text-end allocInput" placeholder="e.g. 2000"></td>
            <td><input type="number" step="0.01" name="extra_weight[]" class="form-control text-end extraInput" placeholder="e.g. 100"></td>
            <td><button type="button" class="btn btn-success addRow">+</button></td>
          </tr>
        </tbody>
      </table>

      <div class="mt-3">
        <strong>Allocated Sum:</strong> <span id="allocSum">0</span> kg |
        <strong>Remaining:</strong> <span id="remaining"><?= number_format($totalWeight,2) ?></span> kg
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
            <th>Extra (kg)</th>
            <th>Total (kg)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($saved as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['factory_name']) ?></td>
            <td><?= number_format($s['allocated_weight'],2) ?></td>
            <td class="extra-weight"><?= number_format($s['extra_weight'],2) ?></td>
            <td><strong><?= number_format($s['allocated_weight'] + $s['extra_weight'],2) ?></strong></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
          <tr>
            <th>Total</th>
            <th><?= number_format($total_alloc,2) ?></th>
            <th><?= number_format($total_extra,2) ?></th>
            <th><?= number_format($total_alloc + $total_extra,2) ?></th>
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
  function recalc(){
    let sum = 0;
    $('.allocInput').each(function(){
      sum += parseFloat($(this).val()) || 0;
    });
    $('#allocSum').text(sum.toFixed(2));
    $('#remaining').text((total - sum).toFixed(2));
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

  // ✅ Save distribution
  $('#saveBtn').click(function(){
    let data = [];
    $('#allocBody tr').each(function(){
      let f = $(this).find('select').val();
      let w = $(this).find('.allocInput').val();
      let e = $(this).find('.extraInput').val();
      if(f && w) data.push({factory:f, weight:w, extra:e});
    });
    if(data.length == 0) { alert('Enter at least one allocation'); return; }

    $.post('daily_distribution_save.php',{
      date:'<?= $date ?>',
      allocations: JSON.stringify(data)
    },function(res){
      if(res.status){
        // ✅ store message to show after reload
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

  // fade alert
  setTimeout(()=>$('#successAlert').fadeOut(), 4000);
});
</script>
</body>
</html>
