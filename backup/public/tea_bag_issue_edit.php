<?php
require_once __DIR__ . '/../init.php';
require_login();

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: tea_bag_issues_list.php');
    exit;
}

// Fetch record
$stmt = $pdo->prepare("
    SELECT t.*, f.code AS farmer_code, f.name AS farmer_name
    FROM tea_bag_issues t
    LEFT JOIN farmers f ON f.id = t.farmer_id
    WHERE t.id = ?
");
$stmt->execute([$id]);
$issue = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$issue) {
    echo "<div class='alert alert-danger text-center mt-4'>Record not found.</div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Tea Bag Issue</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
  body { background:#f8f9fa; }
  table th, table td { text-align:center; vertical-align:middle; }
  .select2-container { width:100%!important; }
</style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-4">
  <h4 class="fw-bold text-primary mb-4">Edit Tea Bag Issue</h4>

  <form id="editForm">
    <input type="hidden" name="id" value="<?= $issue['id'] ?>">

    <div class="row mb-3">
      <div class="col-md-3">
        <label class="form-label">Date</label>
        <input type="date" name="issue_date" value="<?= htmlspecialchars($issue['issue_date']) ?>" class="form-control" required>
      </div>
    </div>

    <table class="table table-bordered bg-white">
      <thead class="table-light">
        <tr>
          <th>Farmer Code</th>
          <th>Farmer Name</th>
          <th>Unit Price</th>
          <th>Quantity</th>
          <th>Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <select class="form-select farmerCode" name="farmer_id" required>
              <option value="<?= $issue['farmer_id'] ?>" selected><?= htmlspecialchars($issue['farmer_code']) ?></option>
            </select>
          </td>
          <td>
            <select class="form-select farmerName" required>
              <option value="<?= $issue['farmer_id'] ?>" selected><?= htmlspecialchars($issue['farmer_name']) ?></option>
            </select>
          </td>
          <td><input type="number" step="0.01" name="unit_price" class="form-control unitPrice" value="<?= htmlspecialchars($issue['unit_price']) ?>"></td>
          <td>
            <input type="number" step="0.01" name="quantity" class="form-control qty" value="<?= htmlspecialchars($issue['quantity']) ?>">
          </td>
          <td class="subtotal"><?= number_format($issue['unit_price'] * $issue['quantity'], 2) ?></td>
        </tr>
      </tbody>
    </table>

    <div class="text-end">
      <a href="tea_bag_issues_list.php" class="btn btn-secondary">Back</a>
      <button type="submit" class="btn btn-primary">Update</button>
    </div>
  </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function(){

  function initSelect2() {
    $('.farmerCode').select2({
      placeholder: 'Search Farmer Code...',
      ajax: {
        url: 'search_farmer_tea.php',
        dataType: 'json',
        delay: 250,
        data: params => ({ q: params.term }),
        processResults: data => ({ results: data })
      }
    });

    $('.farmerName').select2({
      placeholder: 'Search Farmer Name...',
      ajax: {
        url: 'search_farmer_tea.php',
        dataType: 'json',
        delay: 250,
        data: params => ({ q: params.term }),
        processResults: data => ({ results: data })
      }
    });
  }

  initSelect2();

  // Two-way sync
  $(document).on('select2:select', '.farmerCode', function(e){
    const data = e.params.data;
    $('.farmerName').empty().append(new Option(data.text_name, data.id, true, true)).trigger('change.select2');
  });

  $(document).on('select2:select', '.farmerName', function(e){
    const data = e.params.data;
    $('.farmerCode').empty().append(new Option(data.text_code, data.id, true, true)).trigger('change.select2');
  });

  // Subtotal
  $(document).on('input', '.unitPrice, .qty', function(){
    const unit = parseFloat($('.unitPrice').val()) || 0;
    const qty = parseFloat($('.qty').val()) || 0;
    $('.subtotal').text((unit * qty).toFixed(2));
  });

  // Submit update
  $('#editForm').submit(function(e){
    e.preventDefault();
    $.post('tea_bag_issue_update.php', $(this).serialize(), function(res){
      alert(res.message);
      if(res.status) window.location = 'tea_bag_issues_list.php';
    }, 'json');
  });

});
</script>
</body>
</html>
