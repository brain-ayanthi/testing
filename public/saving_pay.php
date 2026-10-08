<?php
require_once __DIR__ . '/../init.php';
require_login();
include('db.php');

/**
 * saving_pay.php
 * Marks all unpaid savings (paid=0) for a farmer as paid,
 * and prints a payment receipt in A5 format.
 */

$farmer_id = intval($_GET['farmer_id'] ?? 0);
if ($farmer_id <= 0) {
    die("Invalid farmer ID.");
}

// --- Fetch farmer details ---
$fstmt = $conn->prepare("SELECT f.name, f.code, a.name AS area_name
                         FROM farmers f
                         LEFT JOIN areas a ON f.area_id = a.id
                         WHERE f.id = ?");
$fstmt->bind_param("i", $farmer_id);
$fstmt->execute();
$fresult = $fstmt->get_result();
$farmer = $fresult->fetch_assoc();
$fstmt->close();

if (!$farmer) {
    die("Farmer not found.");
}

// --- Update unpaid savings to paid ---
$update = $conn->prepare("UPDATE saving SET paid = 1 WHERE farmer_id = ? AND paid = 0");
$update->bind_param("i", $farmer_id);
$update->execute();
$update->close();

// --- Fetch newly paid savings (today’s payment batch) ---
$stmt = $conn->prepare("
    SELECT id, amount, commi, period,
           (amount + (amount * commi / 100)) AS subtotal
    FROM saving
    WHERE farmer_id = ? AND paid = 1
    ORDER BY period DESC, id ASC
");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$res = $stmt->get_result();
$savings = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Totals ---
$total_amount = 0;
$total_comm = 0;
$total_subtotal = 0;
foreach ($savings as $s) {
    $total_amount += $s['amount'];
    $total_subtotal += $s['subtotal'];
    $total_comm += ($s['subtotal'] - $s['amount']);
}

// --- Company details (you can move these to settings table later) ---
$company_name  = "Ceylon Tea Factory";
$company_phone = "+94 77 123 4567";
$today = date("Y-m-d");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Saving Payment Receipt - <?= htmlspecialchars($farmer['name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  @page {
    size: A5 portrait;
    margin: 10mm;
  }
  body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: #fff;
    color: #000;
    font-size: 13px;
  }
  .header {
    text-align: center;
    border-bottom: 2px solid #000;
    padding-bottom: 5px;
    margin-bottom: 10px;
  }
  .header h4 {
    font-weight: bold;
    margin: 0;
  }
  .header small {
    display: block;
  }
  .table th, .table td {
    vertical-align: middle;
    text-align: center;
    font-size: 12px;
    padding: 4px;
  }
  tfoot td {
    font-weight: bold;
    background: #f8f9fa;
  }
  .no-print {
    margin: 10px 0;
  }
</style>
</head>
<body>

<!-- Non-print buttons -->
<div class="no-print text-end">
  <a href="saving_list.php" class="btn btn-secondary btn-sm">← Back</a>
  <button class="btn btn-primary btn-sm" onclick="window.print()">🖨 Print</button>
</div>

<!-- Header -->
<div class="header">
  <h4>R.THIBAGAR - GM10731</h4>
   <small> NO 2/19TH LANE JAYAMALAPURA GAMPOLA</small>
  <small>Date: <?= htmlspecialchars($today) ?></small>
  <h6 class="mt-2 text-decoration-underline">Saving Payment Receipt</h6>
</div>

<!-- Farmer Details -->
<div class="mb-2">
  <table class="table table-borderless table-sm w-100 mb-0">
    <tr>
      <td><strong>Farmer Name:</strong> <?= htmlspecialchars($farmer['name']) ?></td>
      <td><strong>Farmer Code:</strong> <?= htmlspecialchars($farmer['code']) ?></td>
    </tr>
    <tr>
      <td><strong>Area:</strong> <?= htmlspecialchars($farmer['area_name'] ?? '-') ?></td>
      <td><strong>Receipt Date:</strong> <?= htmlspecialchars($today) ?></td>
    </tr>
  </table>
</div>

<!-- Savings Table -->
<div class="container-fluid px-2">
  <table class="table table-bordered table-sm">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Period</th>
        <th>Saving Amount (Rs)</th>
        <th>Subtotal (Rs)</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($savings) > 0): ?>
        <?php $i = 1; foreach ($savings as $s): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($s['period']) ?></td>
            <td class="text-end"><?= number_format($s['amount'], 2) ?></td>
            <td class="text-end"><?= number_format($s['subtotal'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="4" class="text-center text-muted">No saving records found.</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="1" class="text-end">Total Saving</td>
        <td class="text-end"><?= number_format($total_amount, 2) ?></td>
        <td>—</td>
        <td class="text-end"><?= number_format($total_subtotal, 2) ?></td>
      </tr>
    </tfoot>
  </table>
</div>

<!-- Signature -->
<div class="mt-4">
  <div class="d-flex justify-content-between">
    <div><strong>Farmer Signature: ____________________</strong></div>
    <div><strong>Authorized Officer: ____________________</strong></div>
  </div>
</div>

<script>
// Auto print after short delay
window.onload = function() {
  setTimeout(() => window.print(), 800);
};
</script>

</body>
</html>
