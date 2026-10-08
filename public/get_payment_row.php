<?php
require_once __DIR__ . '/../init.php';
require_login();
include('db.php');

$farmer_id = intval($_GET['farmer_id'] ?? 0);
$period = $_GET['period'] ?? date('Y-m');
$currentMonth = date('Y-m');

if ($farmer_id <= 0) exit('');

// --- Fetch farmer info ---
$fSql = "SELECT f.*, a.name AS area_name 
         FROM farmers f 
         LEFT JOIN areas a ON f.area_id = a.id
         WHERE f.id = ?";
$fStmt = $pdo->prepare($fSql);
$fStmt->execute([$farmer_id]);
$f = $fStmt->fetch(PDO::FETCH_ASSOC);
if (!$f) exit('');

// --- Fetch required financial data ---
function getValue($pdo, $sql, $params) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return floatval($st->fetchColumn());
}

// Get rate per kg
$rate_per_kg = getValue($pdo, "SELECT rate_per_kg FROM area_rates WHERE area_id = ? AND `year_month` = ? LIMIT 1", [$f['area_id'], $period]);

// Bag weight & collection
$bag_weight = getValue($pdo, "SELECT bag_weight FROM payments WHERE farmer_id = ? AND period = ?", [$farmer_id, $period]);
$totcolle = getValue($pdo, "SELECT COALESCE(SUM(weight_kg),0) FROM collections WHERE farmer_id = ? AND DATE_FORMAT(collection_date, '%Y-%m') = ?", [$farmer_id, $period]);

// Advance, chemical, others, saving, teabag
$adv_balance = getValue($pdo, "SELECT COALESCE(SUM(amount - paid),0) FROM advances WHERE farmer_id = ? AND DATE_FORMAT(created_at,'%Y-%m') = ?", [$farmer_id, $period]);
$chem_balance = getValue($pdo, "SELECT COALESCE(SUM(subtotal - paid),0) FROM chemical_issue WHERE farmer_id = ? AND DATE_FORMAT(issue_date,'%Y-%m') = ?", [$farmer_id, $period]);
$other_balance = getValue($pdo, "SELECT COALESCE(SUM(amount - paid),0) FROM others WHERE farmer_id = ? AND DATE_FORMAT(issue_date,'%Y-%m') = ?", [$farmer_id, $period]);
$manure_balance = getValue($pdo, "SELECT COALESCE(SUM(subtotal - paid),0) FROM manure WHERE farmer_id = ? AND DATE_FORMAT(issue_date,'%Y-%m') = ?", [$farmer_id, $period]);

$tea_bag_issues = getValue($pdo, "SELECT COALESCE(SUM(subtotal),0) FROM tea_bag_issues WHERE farmer_id = ? AND DATE_FORMAT(issue_date, '%Y-%m') = ?", [$farmer_id, $period]);
$saving = getValue($pdo, "SELECT COALESCE(SUM(amount),0) FROM saving WHERE farmer_id = ? AND period = ?", [$farmer_id, $period]);
$advancePaid = getValue($pdo, "SELECT COALESCE(SUM(advance_paid),0) FROM collections WHERE farmer_id = ? AND DATE_FORMAT(collection_date, '%Y-%m') = ?", [$farmer_id, $period]);

// Paid amounts (for current period)
$adv_paid_total = getValue($pdo, "SELECT COALESCE(SUM(pay_amount),0) FROM f_advance_amount_pay WHERE farmer_id = ? AND period = ?", [$farmer_id, $period]);
$chem_paid_total = getValue($pdo, "SELECT COALESCE(SUM(pay_amount),0) FROM f_chemical_pay WHERE farmer_id = ? AND period = ?", [$farmer_id, $period]);
$other_paid_total = getValue($pdo, "SELECT COALESCE(SUM(pay_amount),0) FROM f_other_pay WHERE farmer_id = ? AND period = ?", [$farmer_id, $period]);
$manure_paid_total = getValue($pdo, "SELECT COALESCE(SUM(pay_amount),0) FROM f_manure_pay WHERE farmer_id = ? AND period = ?", [$farmer_id, $period]);


// ✅ Highlight if any payment in current month
$has_advance = getValue($pdo, "SELECT COUNT(*) FROM f_advance_amount_pay WHERE farmer_id = ? AND LEFT(pay_date,7) = ?", [$farmer_id, $currentMonth]) > 0;
$has_chemical = getValue($pdo, "SELECT COUNT(*) FROM f_chemical_pay WHERE farmer_id = ? AND LEFT(pay_date,7) = ?", [$farmer_id, $currentMonth]) > 0;
$has_other = getValue($pdo, "SELECT COUNT(*) FROM f_other_pay WHERE farmer_id = ? AND LEFT(pay_date,7) = ?", [$farmer_id, $currentMonth]) > 0;
$has_manure = getValue($pdo, "SELECT COUNT(*) FROM f_manure_pay WHERE farmer_id = ? AND LEFT(pay_date,7) = ?", [$farmer_id, $currentMonth]) > 0;

$already_paid = ($has_advance || $has_chemical || $has_other || $has_manure);

// Final calculations
// ✅ Only this period’s payments are deducted (not total carry-forwards)
$full = (($totcolle - $bag_weight) * $rate_per_kg)
      - ($adv_paid_total + $chem_paid_total + $other_paid_total + $manure_paid_total + $tea_bag_issues + $saving + $advancePaid);

// Show period-only balance (exclude previous unpaid amounts)
$full1 = (($totcolle - $bag_weight) * $rate_per_kg)
      - ($tea_bag_issues + $saving + $advancePaid);

$highlight = $already_paid ? 'row-paid="true"' : '';

?>
<tr <?= $highlight ?>>
  <td><?= htmlspecialchars($f['area_name'] ?? '-') ?></td>
  <td><?= htmlspecialchars($f['code'] ?? '') ?></td>
  <td><?= htmlspecialchars($f['name'] ?? '') ?></td>
  <td class="text-end"><?= number_format($full1, 2) ?></td>

  <td class="text-end"><small class="text-muted">Bal: <?= number_format($adv_balance,2) ?></small></td>
  <td class="text-center">
    <input type="number" step="0.01" class="form-control form-control-sm text-end adv-paid-input"
           data-farmer="<?= $farmer_id ?>"
           value="<?= number_format($adv_paid_total,2) ?>">
  </td>

  <td class="text-end"><small class="text-muted">Bal: <?= number_format($chem_balance,2) ?></small></td>
  <td class="text-center">
    <input type="number" step="0.01" class="form-control form-control-sm text-end chem-paid-input"
           data-farmer="<?= $farmer_id ?>"
           value="<?= number_format($chem_paid_total,2) ?>">
  </td>

  <td class="text-end"><small class="text-muted">Bal: <?= number_format($other_balance,2) ?></small></td>
  <td class="text-center">
    <input type="number" step="0.01" class="form-control form-control-sm text-end other-paid-input"
           data-farmer="<?= $farmer_id ?>"
           value="<?= number_format($other_paid_total,2) ?>">
  </td>
  
   <td class="text-end"><small class="text-muted">Bal: <?= number_format($manure_balance,2) ?></small></td>
  <td class="text-center">
    <input type="number" step="0.01" class="form-control form-control-sm text-end manure-paid-input"
           data-farmer="<?= $farmer_id ?>"
           value="<?= number_format($manure_paid_total,2) ?>">
  </td>

  <td class="text-end text-success fw-bold"><?= number_format($full, 2) ?></td>
  <td class="text-center"><button class="btn btn-sm btn-primary save-btn">Save</button></td>
</tr>
