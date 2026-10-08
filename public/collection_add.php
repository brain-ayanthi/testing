<?php
require_once __DIR__ . '/../init.php';
require_login();

// load areas and agents
$areas = $pdo->query("SELECT id, name FROM areas ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$agents = $pdo->query("SELECT id, name FROM users WHERE roles LIKE '%field_officer%' OR roles LIKE '%collector%'")->fetchAll(PDO::FETCH_ASSOC);

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $area_id = intval($_POST['area_id']);
    $agent_id = intval($_POST['agent_id']);
    $collection_date = $_POST['collection_date'] ?? date('Y-m-d');
    $year_month = date('Y-m', strtotime($collection_date));

    $farmer_ids = $_POST['farmer_id'] ?? [];
    $weights = $_POST['weight_kg'] ?? [];
    $advances = $_POST['advance_paid'] ?? [];

    try {
        $pdo->beginTransaction();
        for ($i = 0; $i < count($farmer_ids); $i++) {
            $fid = intval($farmer_ids[$i]);
            $weight = floatval($weights[$i] ?? 0);
            $advance = floatval($advances[$i] ?? 0);
            if ($fid <= 0 || $weight <= 0) continue;

            $insert = $pdo->prepare("INSERT INTO collections 
                (farmer_id, collection_date, weight_kg, area_id, agent_id, advance_paid, created_at, status)
                VALUES (:f, :d, :w, :a, :ag, :adv, NOW(), 0)");
            $insert->execute([
                ':f' => $fid,
                ':d' => $collection_date,
                ':w' => $weight,
                ':a' => $area_id,
                ':ag' => $agent_id,
                ':adv' => $advance
            ]);
        }
        $pdo->commit();
        $success = true;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Collection Entry</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
.table th { background:#0d6efd; color:#fff; text-align:center; }
.total-weight-box {
  font-weight: bold;
  font-size: 1.1rem;
  background: #f1f1f1;
  padding: 10px;
  border-radius: 5px;
  text-align: right;
  
  
}


.duplicate-row {
  background-color: #ffb3b3 !important;
  transition: background-color 0.5s ease;
}

</style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>
<div class="container mt-4">
  <h4>Tea Leaf Collection Entry</h4>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php elseif ($success): ?>
    <div class="alert alert-success">Collection saved successfully.</div>
  <?php endif; ?>

  <form method="POST" id="collectionForm">
    <div class="row mb-3">
      <div class="col-md-4">
        <label>Area</label>
        <select name="area_id" id="areaSelect" class="form-select" required>
          <option value="">Select Area</option>
          <?php foreach($areas as $a): ?>
            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label>Agent</label>
        <select name="agent_id" class="form-select" required>
          <option value="">Select Agent</option>
          <?php foreach($agents as $ag): ?>
            <option value="<?= $ag['id'] ?>"><?= htmlspecialchars($ag['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label>Date</label>
        <input type="date" name="collection_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered" id="collectionTable">
        <thead>
          <tr>
            <th>Farmer Code</th>
            <th>Farmer Name</th>
            <th>Weight (kg)</th>
            <th>Advance</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="collectionRows">
          <!-- Template Row -->
          <tr class="template-row">
            <td><select class="form-select farmer-code"></select></td>
            <td><select class="form-select farmer-name"></select></td>
            <td><input type="number" step="0.01" class="form-control weight" placeholder="0.00"></td>
            <td><input type="number" step="0.01" class="form-control advance" value="0"></td>
            <td class="text-center"><button type="button" class="btn btn-success btn-sm btnAdd">+</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="table-responsive mt-3">
      <table class="table table-sm" id="previewTable">
        <thead><tr><th>Code</th><th>Name</th><th>Weight</th><th>Advance</th><th>Action</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>

    <!-- ✅ Total Weight Display -->
    <div class="mt-2 text-end">
      <div class="total-weight-box">
        Total Weight: <span id="totalWeight">0.00</span> kg
      </div>
    </div>

    <div class="text-end mt-3">
      <button type="submit" class="btn btn-primary">Save Collections</button>
    </div>
  </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function(){

  // Initialize Select2 AJAX for a given row
  function initSelect2(row) {
    const area_id = $('#areaSelect').val();

    row.find('.farmer-code').select2({
      placeholder: 'Search Farmer Code or Name...',
      ajax: {
        url: 'search_farmers.php',
        dataType: 'json',
        delay: 300,
        data: params => ({ q: params.term, area_id: area_id }),
        processResults: data => ({
          results: data.map(f => ({ id: f.id, text: f.code, name: f.name }))
        })
      },
      width: 'resolve'
    });

    row.find('.farmer-name').select2({
      placeholder: 'Search Farmer Name or Code...',
      ajax: {
        url: 'search_farmers.php',
        dataType: 'json',
        delay: 300,
        data: params => ({ q: params.term, area_id: area_id }),
        processResults: data => ({
          results: data.map(f => ({ id: f.id, text: f.name, code: f.code }))
        })
      },
      width: 'resolve'
    });

    // two-way sync between code and name
    row.find('.farmer-code').on('select2:select', function(e){
      const d = e.params.data;
      const nameSel = row.find('.farmer-name');
      const opt = new Option(d.name, d.id, true, true);
      nameSel.append(opt).trigger('change');
    });

    row.find('.farmer-name').on('select2:select', function(e){
      const d = e.params.data;
      const codeSel = row.find('.farmer-code');
      const opt = new Option(d.code, d.id, true, true);
      codeSel.append(opt).trigger('change');
    });
  }

  // Initialize for template row
  initSelect2($('.template-row'));

  // ✅ Function to update total weight
  function updateTotalWeight() {
    let total = 0;
    $('#previewTable tbody tr').each(function() {
      const weight = parseFloat($(this).find('td:nth-child(3)').text()) || 0;
      total += weight;
    });
    $('#totalWeight').text(total.toFixed(2));
  }

  // Add new row
  $(document).on('click', '.btnAdd', function(){
    const template = $('.template-row');
    const codeSel = template.find('.farmer-code');
    const nameSel = template.find('.farmer-name');
    const fid = codeSel.val();
    const codeText = codeSel.find('option:selected').text();
    const nameText = nameSel.find('option:selected').text();
    const weight = parseFloat(template.find('.weight').val() || 0);
    const advance = parseFloat(template.find('.advance').val() || 0);

    if(!fid) return alert('Select a farmer.');
    if(weight <= 0) return alert('Enter valid weight.');

    // ✅ Allow duplicates but highlight them red
    const isDuplicate = $(`#previewTable tr[data-fid="${fid}"]`).length > 0;

    const $row = $(`
      <tr data-fid="${fid}" class="${isDuplicate ? 'duplicate-row' : ''}">
        <td>${codeText}</td>
        <td>${nameText}</td>
        <td>${weight.toFixed(2)}</td>
        <td>${advance.toFixed(2)}</td>
        <td><button type="button" class="btn btn-danger btn-sm removeRow">–</button></td>
        <input type="hidden" name="farmer_id[]" value="${fid}">
        <input type="hidden" name="weight_kg[]" value="${weight}">
        <input type="hidden" name="advance_paid[]" value="${advance}">
      </tr>
    `);

    $('#collectionRows').append($row);
    addPreview(fid, codeText, nameText, weight, advance, isDuplicate);

    // reset inputs
    codeSel.val('').trigger('change');
    nameSel.val('').trigger('change');
    template.find('.weight').val('');
    template.find('.advance').val('0');

    // ✅ Update total weight
    updateTotalWeight();

    $('html,body').animate({scrollTop: $(document).height()}, 300);
  });

  // Remove row + preview
  $(document).on('click', '.removeRow, .remove-preview', function(){
    const fid = $(this).closest('tr').data('fid');
    $(`#previewTable tr[data-fid="${fid}"]`).remove();
    $(`#collectionRows tr[data-fid="${fid}"]`).remove();
    updateTotalWeight();
  });

  // Add live preview
  function addPreview(fid, code, name, w, adv, isDuplicate=false){
    const $tr = $(`
      <tr data-fid="${fid}" class="${isDuplicate ? 'duplicate-row' : ''}">
        <td>${code}</td>
        <td>${name}</td>
        <td>${w.toFixed(2)}</td>
        <td>${adv.toFixed(2)}</td>
        <td><button type="button" class="btn btn-outline-danger btn-sm remove-preview">Remove</button></td>
      </tr>`);
    $('#previewTable tbody').append($tr);
  }

});
</script>

<style>
.duplicate-row {
  background-color: #ffb3b3 !important;
}
</style>


</body>
</html>
