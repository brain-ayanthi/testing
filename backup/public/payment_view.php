<?php
require_once __DIR__ . '/../init.php';
require_login();
include('db.php'); 
$farmer_id = $_GET['farmer_id'] ?? 0;
$period = $_GET['period'] ?? date('Y-m');

// Get farmer details
$farmer_sql = "SELECT f.name AS farmer_name, f.code AS farmer_code, a.name 
               FROM farmers f 
               LEFT JOIN areas a ON a.id = f.area_id 
               WHERE f.id = '$farmer_id'";
$res = $conn->query($farmer_sql);
$farmer = $res->fetch_assoc();
$supplier_name = $farmer['farmer_name'] ?? 'Unknown';
$supplier_no   = $farmer['farmer_code'] ?? '-';
$area_name     = $farmer['area_name'] ?? '-';

// Get bag weight from payments
$bag_weight_sql = "SELECT bag_weight FROM payments WHERE farmer_id='$farmer_id' AND period='$period'";
$res = $conn->query($bag_weight_sql);
$bag_weight = ($res && $r=$res->fetch_assoc()) ? $r['bag_weight'] : 0;
    


// Get total collected weight
$totcolle_sql = "SELECT COALESCE(SUM(weight_kg),0) AS total FROM collections 
                 WHERE farmer_id='$farmer_id' AND DATE_FORMAT(collection_date, '%Y-%m')='$period'";
$res = $conn->query($totcolle_sql);
$total_kg = ($res && $r=$res->fetch_assoc()) ? $r['total'] : 0;


$to1 = $total_kg - $bag_weight;

// Get rate per kg
$area_id_sql = "SELECT area_id FROM farmers WHERE id='$farmer_id'";
$res = $conn->query($area_id_sql);
$area_id = ($res && $r=$res->fetch_assoc()) ? $r['area_id'] : 0;


$rate_sql = "SELECT rate_per_kg FROM area_rates WHERE area_id='$area_id' AND `year_month`='$period'";
$res = $conn->query($rate_sql);
$rate_per_kg = ($res && $r=$res->fetch_assoc()) ? $r['rate_per_kg'] : 0;




// === Get daily collections ===
$coll_sql = "
    SELECT DAY(collection_date) AS day, SUM(weight_kg) AS total_kg
    FROM collections 
    WHERE farmer_id = '$farmer_id' 
      AND DATE_FORMAT(collection_date, '%Y-%m') = '$period'
    GROUP BY DAY(collection_date)
    ORDER BY collection_date";
$coll_result = $conn->query($coll_sql);
$collections = [];
while ($row = $coll_result->fetch_assoc()) {
    $collections[(int)$row['day']] = (float)$row['total_kg'];
}


// Calculate total value
$total_value = ($total_kg - $bag_weight) * $rate_per_kg;

// Fetch all deductions
function get_total($conn, $table, $column, $farmer_id, $period, $date_col='period') {
    $sql = "SELECT COALESCE(SUM($column),0) AS total FROM $table WHERE farmer_id='$farmer_id' AND ";
    $sql .= ($date_col == 'period') ? "period='$period'" : "DATE_FORMAT($date_col, '%Y-%m')='$period'";
    $res = $conn->query($sql);
    $r = $res ? $res->fetch_assoc() : ['total'=>0];
    return $r['total'] ?? 0;
}

$advance_paid_total = get_total($conn, 'f_advance_amount_pay', 'pay_amount', $farmer_id, $period);
$chemical_paid_total = get_total($conn, 'f_chemical_pay', 'pay_amount', $farmer_id, $period);
$others_paid_total = get_total($conn, 'f_other_pay', 'pay_amount', $farmer_id, $period);
$tea_bag_issuesT = get_total($conn, 'tea_bag_issues', 'subtotal', $farmer_id, $period, 'issue_date');
$savingT = get_total($conn, 'saving', 'amount', $farmer_id, $period);
$advancePaidT = get_total($conn, 'collections', 'advance_paid', $farmer_id, $period, 'collection_date');

$total_deduction = $advance_paid_total + $chemical_paid_total + $others_paid_total + 
                   $tea_bag_issuesT + $savingT + $advancePaidT;
$balance = $total_value - $total_deduction;

// Deduction breakdown for display
$deductions = [
    "Advance" => $advance_paid_total,
    "TEA" => $tea_bag_issuesT,
    "MANURE" => $chemical_paid_total,
    "SAVING" => $savingT,
    "L/M/B" => 0,
    "CAMICLE" => 0,
    "F/ADV" => $advancePaidT,
    "Other" => $others_paid_total
];


function get_balance_summary($pdo, $table, $amount_col, $paid_col, $farmer_id) {
    $sql = "SELECT 
                COALESCE(SUM($amount_col),0) AS total_amount, 
                COALESCE(SUM($paid_col),0) AS total_paid, 
                COALESCE(SUM($amount_col - $paid_col),0) AS balance
            FROM $table
            WHERE farmer_id = :farmer_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['farmer_id' => $farmer_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$advancesA = get_balance_summary($pdo, 'advances', 'amount', 'paid', $farmer_id);
$chemicalA = get_balance_summary($pdo, 'chemical_issue', 'subtotal', 'paid', $farmer_id);
$othersA   = get_balance_summary($pdo, 'others', 'amount', 'paid', $farmer_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment Slip - <?= htmlspecialchars($supplier_name) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
 @page {
      size: A4;
      margin: 0;
    }
	html, body {
      margin: 0;
      padding: 0;
      font-size: 11pt;
      font-family: Arial, sans-serif;
      height: 100%;
	  color:#000;
    }
	

.table-bordered td, .table-bordered th { border:1px solid #000 !important; }
.table-sm td, .table-sm th { padding:3px 4px !important; }
.text-green { color:green; font-weight:bold; }
.text-blue { color:blue; font-weight:800; font-size:26px; text-align:center; }
.highlight { background:yellow; font-weight:bold; }
.fw-700 { font-weight:700; }
h6.title { color:green; font-weight:600; }
table { border-collapse: collapse !important; }
thead tr th { background: #f4f4f4; }
@media print {
  @page { margin:5mm; }
  .no-print { display:none; }
}
</style>
</head>
<body onload="window.print()">

<div class="container mt-3">
    
    <!-- Main info -->
    <table class="table table-bordered table-sm mb-2">
       
	   <tr>
            <td colspan="5"><strong><h6 class="title mb-0">R.THIBAGAR - GM10731 - NO 2/19TH LANE JAYAMALAPURA GAMPOLA<?= strtoupper($area_name) ?></h6></strong></td>
		</tr>
	   <tr>
            <td width="17%"><strong><strong><em>Supplier Name :-</em></strong></strong> </td>
			<td colspan="3"><strong><?= htmlspecialchars($supplier_name) ?></strong></td>
			<td style="text-align: center;"><span class="text-blue" ><?= substr($period, 0, 4) ?></span></td>
		</tr>

	   <tr>
            <td><strong>No :-</strong> <?= $supplier_no ?></td>
            <td><strong>Month</strong></td>
            <td class="highlight text-center" style="font-size:15px;"><?= strtoupper(date('M', strtotime($period.'-01'))) ?></td>
            <td><strong>Amount</strong></td>
            <td class="text-end"><?= number_format($total_value,2) ?></td>
        </tr>
        <tr>
            <th>Qty</th>
            <th>Rate</th>
            <th>Value</th>
            <th>Incentive</th>
            <th style="text-align: right;">-</th>
        </tr>
        <tr>
            <td class="highlight"><?= number_format($to1) ?></td>
            <td class="highlight"><?= number_format($rate_per_kg,2) ?></td>
            <td class="highlight"><?= number_format($total_value,2) ?></td>
            <td class="highlight">Transport Incentive</td>
            <td style="text-align: right;">-</td>
        </tr>
        <tr class="fw-bold">
            <td colspan="4" class="text-end">Total</td>
            <td class="text-end"><?= number_format($total_value,2) ?></td>
        </tr>
    </table>

    <!-- Qty Green Leaf + Deductions -->
    <table class="table table-bordered table-sm text-center align-middle" style="margin-top:-7px;">
        <thead>
            
			
			  <thead>
            <tr><th colspan="8">Qty Green Leaf</th><th colspan="2">Deductions</th></tr>
            <tr>
                <th>Date</th><th>Kg</th><th>Date</th><th>Kg</th>
                <th>Date</th><th>Kg</th><th>Date</th><th>Kg</th>
                <th>Type</th><th>Amount</th>
            </tr>
        </thead>
			
			
		<tbody>
<?php 
$dedKeys = array_keys($deductions);
$dedVals = array_values($deductions);

// There are 31 days total → make 8 rows (4 days per row, last row has only 3)
$day = 1;
for ($row = 0; $row < 8; $row++): ?>
<tr>
    <?php for ($col = 0; $col < 4; $col++): ?>
        <?php if ($day <= 31): ?>
            <td><?= $day ?></td>
            <td><?= isset($collections[$day]) ? number_format($collections[$day]) : '-' ?></td>
            <?php $day++; ?>
        <?php else: ?>
            <td>-</td><td>-</td>
        <?php endif; ?>
    <?php endfor; ?>

    <td><?= $dedKeys[$row] ?? '' ?></td>
    <td class="text-end">
        <?= isset($dedVals[$row]) && $dedVals[$row] > 0 ? number_format($dedVals[$row],2) : '-' ?>
    </td>
</tr>
<?php endfor; ?>
</tbody>
			
			
			
			
            <tr class="fw-bold text-danger">
				<td colspan="1" class="text-end">TOTAL</td>
                <td><?= number_format($total_kg) ?></td>
				<td colspan="" class="text-end">B/WEIGHT</td>
                <td><?= number_format($bag_weight) ?></td>
				
                <td colspan="3" class="text-end">TOTAL</td>
                <td><?= number_format($to1) ?></td>
                <td>Total Deduction</td>
                <td class="text-end"><?= number_format($total_deduction,2) ?></td>
            </tr>
            <tr class="fw-bold">
                <td colspan="9" class="text-end">Balance</td>
                <td class="text-end"><?= number_format($balance,2) ?></td>
            </tr>
			 
			<tr>
    <td class="text-end">Note :-</td>

    <td class="text-end">CAM/ B/L</td>
    <td class="text-end"><?= number_format($chemicalA['balance'],2) ?></td>

    <td class="text-end">F/ADV B/L</td>
    <td class="text-end"><?= number_format($advancesA['balance'],2) ?></td>

    <td class="text-end">OTH/ B/L</td>
    <td class="text-end"><?= number_format($othersA['balance'],2) ?></td>

    <td colspan="3" class="text-end" style="text-align: center;"><strong>THANK YOU</strong></td>
</tr>
        </tbody>
    </table>

    <div class="text-center mt-3 no-print">
        <button class="btn btn-primary" onclick="window.print()">🖨 Print</button>
    </div>
</div>
</body>
</html>
