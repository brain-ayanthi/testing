<?php
require_once __DIR__ . '/../init.php';
require_login();
include('db.php'); 
/**
 * payments.php - final version
 * - Fixed undefined variable warnings
 * - Chemical & Others carry-forward logic implemented
 */

// --- helpers ---
function json_exit($arr){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

// get inputs
$period = $_REQUEST['period'] ?? date('Y-m');            // YYYY-MM
$area_id = $_REQUEST['area_id'] ?? '';
$search = trim($_REQUEST['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

// Try to add bag_weight column to payments if missing (safe)
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM payments LIKE 'bag_weight'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN bag_weight DECIMAL(12,3) NOT NULL DEFAULT 0.000");
    }
} catch (Exception $e) {
    // ignore - user may not have alter privileges
}

// Try to add l_m_balance column if missing (safe)
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM payments LIKE 'l_m_balance'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN l_m_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00");
    }
} catch (Exception $e) {
    // ignore if no permission
}
// ----------------- AJAX actions -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ---------- Generate/Update Payments for month ----------
  


	
    // ---------- Advance Payment ----------
    if ($action === 'pay_advance') {
        $period = $_POST['period'] ?? date('Y-m');
        $farmer_id = intval($_POST['farmer_id'] ?? 0);
        $pay_amount = floatval($_POST['pay_amount'] ?? 0);

        if ($farmer_id <= 0 || $pay_amount <= 0) {
            json_exit(['status' => false, 'message' => 'Invalid input']);
        }

        try {
            $pdo->beginTransaction();

            $advances = $pdo->prepare("
                SELECT * FROM advances
                WHERE farmer_id = :f 
                  AND status IN ('due','partial')
                ORDER BY created_at ASC
            ");
            $advances->execute([':f' => $farmer_id]);
            $rows = $advances->fetchAll(PDO::FETCH_ASSOC);

            $remain = $pay_amount;
            foreach ($rows as $adv) {
                if ($remain <= 0) break;
                $due = $adv['amount'] - $adv['paid'];
                if ($due <= 0) continue;
                $toPay = min($remain, $due);
                $newPaid = $adv['paid'] + $toPay;
                $newStatus = ($newPaid >= $adv['amount']) ? 'paid' : 'partial';

                $pdo->prepare("UPDATE advances SET paid = :p, status = :s WHERE id = :id")
                    ->execute([':p'=>$newPaid, ':s'=>$newStatus, ':id'=>$adv['id']]);

                $remain -= $toPay;
            }

            // insert history
            $ins = $pdo->prepare("
                INSERT INTO f_advance_amount_pay 
                (farmer_id, pay_amount, previous_balance, new_balance, pay_date, period)
                VALUES (:farmer_id, :pay_amount, :prev, :newb, NOW(), :period)
            ");
            // previous and new balance totals
            $stmtPrev = $pdo->prepare("SELECT COALESCE(SUM(amount - paid), 0) FROM advances WHERE farmer_id = :f");
            $stmtPrev->execute([':f'=>$farmer_id]);
            $prevBal = floatval($stmtPrev->fetchColumn());
            $ins->execute([':farmer_id'=>$farmer_id, ':pay_amount'=>$pay_amount, ':prev'=>$prevBal, ':newb'=>$prevBal, ':period'=>$period]);

            $pdo->commit();
            json_exit(['status' => true, 'message' => 'Advance payment updated successfully.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            json_exit(['status' => false, 'message' => 'Error updating advance: ' . $e->getMessage()]);
        }
    }

    // ---------- Chemical Payment ----------
    if ($action === 'pay_chemical') {
        $farmer_id = intval($_POST['farmer_id'] ?? 0);
        $pay_amount = floatval($_POST['pay_amount'] ?? 0);
        $period = $_POST['period'] ?? date('Y-m');

        if ($farmer_id <= 0 || $pay_amount <= 0) {
            json_exit(['status' => false, 'message' => 'Invalid input']);
        }

        try {
            $pdo->beginTransaction();

            // get unpaid chemical_issue rows (oldest first)
            $chems = $pdo->prepare("
                SELECT * FROM chemical_issue
                WHERE farmer_id = :f
                  AND status IN ('due','partial')
                ORDER BY issue_date ASC, id ASC
            ");
            $chems->execute([':f' => $farmer_id]);
            $rows = $chems->fetchAll(PDO::FETCH_ASSOC);

            $remain = $pay_amount;
            foreach ($rows as $chem) {
                if ($remain <= 0) break;
                $due = floatval($chem['subtotal']) - floatval($chem['paid']);
                if ($due <= 0) continue;
                $toPay = min($remain, $due);
                $newPaid = floatval($chem['paid']) + $toPay;
                $newStatus = ($newPaid >= floatval($chem['subtotal'])) ? 'paid' : 'partial';

                $pdo->prepare("UPDATE chemical_issue SET paid = :p, status = :s WHERE id = :id")
                    ->execute([':p' => $newPaid, ':s' => $newStatus, ':id' => $chem['id']]);

                $remain -= $toPay;
            }

            // compute prev/new balances for history
            $stmtPrev = $pdo->prepare("SELECT COALESCE(SUM(subtotal - paid), 0) FROM chemical_issue WHERE farmer_id = :f");
            $stmtPrev->execute([':f'=>$farmer_id]);
            $prevBal = floatval($stmtPrev->fetchColumn());

            $ins = $pdo->prepare("
                INSERT INTO f_chemical_pay
                (farmer_id, pay_amount, previous_balance, new_balance, pay_date, period)
                VALUES (:farmer_id, :pay_amount, :prev, :newb, NOW(), :period)
            ");
            $ins->execute([':farmer_id'=>$farmer_id, ':pay_amount'=>$pay_amount, ':prev'=>$prevBal, ':newb'=>$prevBal, ':period'=>$period]);

            $pdo->commit();
            json_exit(['status' => true, 'message' => 'Chemical payment recorded successfully.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            json_exit(['status' => false, 'message' => 'Error (chemical): ' . $e->getMessage()]);
        }
    }

    // ---------- Others Payment ----------
    if ($action === 'pay_others') {
        $farmer_id = intval($_POST['farmer_id'] ?? 0);
        $pay_amount = floatval($_POST['pay_amount'] ?? 0);
        $period = $_POST['period'] ?? date('Y-m');

        if ($farmer_id <= 0 || $pay_amount <= 0) {
            json_exit(['status' => false, 'message' => 'Invalid input']);
        }

        try {
            $pdo->beginTransaction();

            // get unpaid others rows (oldest first)
            $others = $pdo->prepare("
                SELECT * FROM others
                WHERE farmer_id = :f
                  AND status IN ('due','partial')
                ORDER BY issue_date ASC, id ASC
            ");
            $others->execute([':f' => $farmer_id]);
            $rows = $others->fetchAll(PDO::FETCH_ASSOC);

            $remain = $pay_amount;
            foreach ($rows as $oth) {
                if ($remain <= 0) break;
                $due = floatval($oth['amount']) - floatval($oth['paid']);
                if ($due <= 0) continue;
                $toPay = min($remain, $due);
                $newPaid = floatval($oth['paid']) + $toPay;
                $newStatus = ($newPaid >= floatval($oth['amount'])) ? 'paid' : 'partial';

                $pdo->prepare("UPDATE others SET paid = :p, status = :s WHERE id = :id")
                    ->execute([':p'=>$newPaid, ':s'=>$newStatus, ':id'=>$oth['id']]);

                $remain -= $toPay;
            }

            $stmtPrev = $pdo->prepare("SELECT COALESCE(SUM(amount - paid), 0) FROM others WHERE farmer_id = :f");
            $stmtPrev->execute([':f'=>$farmer_id]);
            $prevBal = floatval($stmtPrev->fetchColumn());

            $pdo->prepare("
                INSERT INTO f_other_pay 
                (farmer_id, pay_amount, previous_balance, new_balance, pay_date, period)
                VALUES (:farmer_id, :pay_amount, :prev, :newb, NOW(), :period)
            ")->execute([':farmer_id'=>$farmer_id, ':pay_amount'=>$pay_amount, ':prev'=>$prevBal, ':newb'=>$prevBal, ':period'=>$period]);

            $pdo->commit();
            json_exit(['status' => true, 'message' => 'Other payment recorded successfully.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            json_exit(['status' => false, 'message' => 'Error (others): ' . $e->getMessage()]);
        }
    }


// ---------- Manure Payment ----------
if ($action === 'pay_manure') {
    $farmer_id = intval($_POST['farmer_id'] ?? 0);
    $pay_amount = floatval($_POST['pay_amount'] ?? 0);
    $period = $_POST['period'] ?? date('Y-m');

    if ($farmer_id <= 0 || $pay_amount <= 0) {
        json_exit(['status' => false, 'message' => 'Invalid input']);
    }

    try {
        $pdo->beginTransaction();

        $manure = $pdo->prepare("
            SELECT * FROM manure
            WHERE farmer_id = :f
              AND status IN ('due','partial')
            ORDER BY issue_date ASC, id ASC
        ");
        $manure->execute([':f' => $farmer_id]);
        $rows = $manure->fetchAll(PDO::FETCH_ASSOC);

        $remain = $pay_amount;
        foreach ($rows as $mn) {
            if ($remain <= 0) break;
            $due = floatval($mn['subtotal']) - floatval($mn['paid']);
            if ($due <= 0) continue;
            $toPay = min($remain, $due);
            $newPaid = floatval($mn['paid']) + $toPay;
            $newStatus = ($newPaid >= floatval($mn['subtotal'])) ? 'paid' : 'partial';

            $pdo->prepare("UPDATE manure SET paid = :p, status = :s WHERE id = :id")
                ->execute([':p'=>$newPaid, ':s'=>$newStatus, ':id'=>$mn['id']]);

            $remain -= $toPay;
        }

        $stmtPrev = $pdo->prepare("SELECT COALESCE(SUM(subtotal - paid), 0) FROM manure WHERE farmer_id = :f");
        $stmtPrev->execute([':f'=>$farmer_id]);
        $prevBal = floatval($stmtPrev->fetchColumn());

        $pdo->prepare("
            INSERT INTO f_manure_pay 
            (farmer_id, pay_amount, previous_balance, new_balance, pay_date, period)
            VALUES (:farmer_id, :pay_amount, :prev, :newb, NOW(), :period)
        ")->execute([':farmer_id'=>$farmer_id, ':pay_amount'=>$pay_amount, ':prev'=>$prevBal, ':newb'=>$prevBal, ':period'=>$period]);

        $pdo->commit();
        json_exit(['status' => true, 'message' => 'Manure payment recorded successfully.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_exit(['status' => false, 'message' => 'Error (manure): ' . $e->getMessage()]);
    }
}



    // unknown action
    json_exit(['status'=>false, 'message'=>'Unknown action']);
}

// ----------------- End AJAX actions -----------------

// --- UI: Filtering + listing payments with computed columns ---

// Fetch areas for filter
$areas = $pdo->query("SELECT id, name FROM areas ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Check pending collections count for current period (status=0)
$chkCollectionsStmt = $pdo->prepare("
    SELECT COUNT(*) FROM collections
    WHERE DATE_FORMAT(collection_date, '%Y-%m') = :pm AND status = 0
");
$chkCollectionsStmt->execute([':pm' => $period]);
$pendingCollectionsCount = (int)$chkCollectionsStmt->fetchColumn();
$generateDisabled = ($pendingCollectionsCount === 0);

// Build WHERE for payments listing (filter by period they are for)
$where = "WHERE pay.period = :period";
$params = [':period'=>$period];

if (!empty($area_id)) {
    $where .= " AND f.area_id = :area_id";
    $params[':area_id'] = $area_id;
}
if (!empty($search)) {
    $where .= " AND (f.name LIKE :s OR f.code LIKE :s)";
    $params[':s'] = "%$search%";
}

// Count total payments for pagination
$countSql = "SELECT COUNT(*) FROM payments pay JOIN farmers f ON pay.farmer_id = f.id $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

// Fetch paginated payments
$sql = "
    SELECT pay.*, 
           f.code AS farmer_code, 
           f.name AS farmer_name, 
           f.area_id, 
           f.saving 
    FROM payments pay
    JOIN farmers f ON pay.farmer_id = f.id
    $where
    ORDER BY f.name ASC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare statements for sums and balances
$teaBagStmt = $pdo->prepare("SELECT COALESCE(SUM(subtotal),0) AS s FROM tea_bag_issues WHERE farmer_id = :f AND DATE_FORMAT(issue_date, '%Y-%m') = :pm");

$advStmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(amount),0) AS total_adv,
        COALESCE(SUM(paid),0) AS total_paid
    FROM advances
    WHERE farmer_id = :f 
      AND (DATE_FORMAT(created_at, '%Y-%m') <= :pm) 
      AND status IN ('due','partial')
");

$rateStmt = $pdo->prepare("SELECT `rate_per_kg` FROM `area_rates` WHERE `area_id` = :aid AND `year_month` = :pm LIMIT 1");
$advancePaidStmt = $pdo->prepare("SELECT COALESCE(SUM(advance_paid),0) AS s FROM collections WHERE farmer_id = :f AND DATE_FORMAT(collection_date, '%Y-%m') = :pm");

$advanceIssueStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) AS s
    FROM advances_issues
    WHERE farmer_id = :f 
      AND DATE_FORMAT(created_at, '%Y-%m') = :pm
");
$savingStmt = $pdo->prepare("SELECT amount, status FROM saving WHERE payment_id = :pid LIMIT 1");

// New statements for chemical/others month & previous balances
$chemMonthStmt = $pdo->prepare("SELECT COALESCE(SUM(subtotal),0) FROM chemical_issue WHERE farmer_id = :f AND DATE_FORMAT(issue_date, '%Y-%m') = :pm");
$chemPaidMonthStmt = $pdo->prepare("SELECT COALESCE(SUM(paid),0) FROM chemical_issue WHERE farmer_id = :f AND DATE_FORMAT(issue_date, '%Y-%m') = :pm");
$chemPrevUnpaidStmt = $pdo->prepare("SELECT COALESCE(SUM(subtotal - paid),0) FROM chemical_issue WHERE farmer_id = :f AND DATE_FORMAT(issue_date, '%Y-%m') < :pm");

$otherMonthStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM others WHERE farmer_id = :f AND DATE_FORMAT(issue_date, '%Y-%m') = :pm");
$otherPaidMonthStmt = $pdo->prepare("SELECT COALESCE(SUM(paid),0) FROM others WHERE farmer_id = :f AND DATE_FORMAT(issue_date, '%Y-%m') = :pm");
$otherPrevUnpaidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount - paid),0) FROM others WHERE farmer_id = :f AND DATE_FORMAT(issue_date, '%Y-%m') < :pm");


// 🧩 Manure (new)
$manureMonthStmt = $pdo->prepare("SELECT COALESCE(SUM(subtotal),0) FROM manure WHERE farmer_id = :f AND DATE_FORMAT(issue_date, '%Y-%m') = :pm");
$manurePaidMonthStmt = $pdo->prepare("SELECT COALESCE(SUM(paid),0) FROM manure WHERE farmer_id = :f AND DATE_FORMAT(issue_date, '%Y-%m') = :pm");
$manurePrevUnpaidStmt = $pdo->prepare("SELECT COALESCE(SUM(subtotal - paid),0) FROM manure WHERE farmer_id = :f AND DATE_FORMAT(issue_date, '%Y-%m') < :pm");



// Collect rows with computed fields
$computed = [];
foreach ($payments as $p) {
    $farmer_id = (int)$p['farmer_id'];
    $areaId = (int)$p['area_id'];
    $total_collection = floatval($p['total_collection']);
    $bag_weight = floatval($p['bag_weight'] ?? 0.0);
    $balance_weight = max(0, $total_collection - $bag_weight);

    // area rate & total_pay
    $rateStmt->execute([':aid'=>$areaId, ':pm'=>$period]);
    $rrow = $rateStmt->fetch(PDO::FETCH_ASSOC);
    $rate = $rrow ? floatval($rrow['rate_per_kg']) : 0.0;
    $total_pay = round($balance_weight * $rate, 2);

    // tea bag
    $teaBagStmt->execute([':f'=>$farmer_id, ':pm'=>$period]);
    $tea_bag_sum = floatval($teaBagStmt->fetchColumn());

    // advance
    $advStmt->execute([':f'=>$farmer_id, ':pm'=>$period]);
    $advRow = $advStmt->fetch(PDO::FETCH_ASSOC);
    $adv_amount = floatval($advRow['total_adv']);
    $adv_paid = floatval($advRow['total_paid']);
    $adv_balance = max(0, $adv_amount - $adv_paid);

    // chemical: month, paid month, previous unpaid, total balance (carry forward)
    $chemMonthStmt->execute([':f'=>$farmer_id, ':pm'=>$period]);
    $chem_month = floatval($chemMonthStmt->fetchColumn());

    $chemPaidMonthStmt->execute([':f'=>$farmer_id, ':pm'=>$period]);
    $chem_paid_month = floatval($chemPaidMonthStmt->fetchColumn());

    $chemPrevUnpaidStmt->execute([':f'=>$farmer_id, ':pm'=>$period]);
    $chem_prev_unpaid = floatval($chemPrevUnpaidStmt->fetchColumn());

    // total chemical balance to show for this period:
    // previous unpaid + (month issued - month paid)
    $chem_balance = $chem_prev_unpaid + ($chem_month - $chem_paid_month);
    if ($chem_balance < 0) $chem_balance = 0.0;

    // others: same concept
    $otherMonthStmt->execute([':f'=>$farmer_id, ':pm'=>$period]);
    $other_month = floatval($otherMonthStmt->fetchColumn());

    $otherPaidMonthStmt->execute([':f'=>$farmer_id, ':pm'=>$period]);
    $other_paid_month = floatval($otherPaidMonthStmt->fetchColumn());

    $otherPrevUnpaidStmt->execute([':f'=>$farmer_id, ':pm'=>$period]);
    $other_prev_unpaid = floatval($otherPrevUnpaidStmt->fetchColumn());

    $other_balance = $other_prev_unpaid + ($other_month - $other_paid_month);
    if ($other_balance < 0) $other_balance = 0.0;
	
	
	
	// 🧮 Manure calculations
$manureMonthStmt->execute([':f'=>$farmer_id, ':pm'=>$period]);
$manure_month = floatval($manureMonthStmt->fetchColumn());

$manurePaidMonthStmt->execute([':f'=>$farmer_id, ':pm'=>$period]);
$manure_paid_month = floatval($manurePaidMonthStmt->fetchColumn());

$manurePrevUnpaidStmt->execute([':f'=>$farmer_id, ':pm'=>$period]);
$manure_prev_unpaid = floatval($manurePrevUnpaidStmt->fetchColumn());

$manure_balance = $manure_prev_unpaid + ($manure_month - $manure_paid_month);
if ($manure_balance < 0) $manure_balance = 0.0;

	
	
	

    // any advances collected in collections (advance_paid)
    $advancePaidStmt->execute([':f'=>$farmer_id, ':pm'=>$period]);
    $advance_paid = floatval($advancePaidStmt->fetchColumn());
	// advances_issues monthly total
$advanceIssueStmt->execute([':f'=>$farmer_id, ':pm'=>$period]);
$advance_issues_total = floatval($advanceIssueStmt->fetchColumn() ?? 0);

    // saving
    $savingStmt->execute([':pid'=>$p['id']]);
    $saveRow = $savingStmt->fetch(PDO::FETCH_ASSOC);
    $saved_amount = $saveRow ? floatval($saveRow['amount']) : floatval($p['saved_amount'] ?? 0);


// 🔹 LMBalance previous months total
$lmbPrevStmt = $pdo->prepare("
     SELECT COALESCE(l_m_balance, 0)
    FROM payments
    WHERE farmer_id = :f
      AND period < :pm
      AND status = 3
    ORDER BY period DESC
    LIMIT 1
");
$lmbPrevStmt->execute([':f' => $farmer_id, ':pm' => $period]);
$lmbalance_prev_total = floatval($lmbPrevStmt->fetchColumn());
    // paid amount from payments table
    $paid_amount = floatval($p['paid_amount'] ?? 0);

    // compute final balance for payment screen:
    // Deduct saved_amount + tea_bag_sum + advance_paid + (chemical balance for period?) + (others balance for period?)
    // Here we treat chemical and others amounts that belong to this period + previous unpaid carried amounts as deductions.
    $balance = round($total_pay - ($saved_amount + $tea_bag_sum + $advance_paid + $chem_balance + $other_balance + $manure_balance), 2);

    // status: determine label
    $status = ($paid_amount >= $total_pay && $total_pay > 0) ? 'Paid' : 'Unpaid';
    if (isset($p['status']) && intval($p['status']) === 1) $status = 'Paid';

    // Build computed row (ensure every variable exists)
   // 🧮 Step 1: Base totals
$total_pay       = $balance_weight * $rate;

// 🧮 Step 2: Main fixed deductions
$first_balance = $total_pay - ($saved_amount + $adv_paid + $tea_bag_sum);

// 🧮 Step 3: Variable payments (only if actually paid this month)
$advance_paid_total = $advance_paid ?? 0;     // From f_advance_amount_pay
$chemical_paid_total = $chem_paid_month ?? 0; // This month’s paid chem
$others_paid_total = $other_paid_month ?? 0;  // This month’s paid others
$manure_paid_total = $manure_paid_month ?? 0;  // This month’s paid others

// 🧮 Step 4: Calculate final balance (only deduct if paid)
$final_balance = $first_balance;

// 🧮 Step 5: Compute unpaid carry-forwards for next month
$chem_balance  = ($chem_prev_unpaid + ($chem_month - $chem_paid_month));
$other_balance = ($other_prev_unpaid + ($other_month - $other_paid_month));
$manure_balance = ($manure_prev_unpaid + ($manure_month - $manure_paid_month));



// 🧩 Step 6: Merge into computed array
$computed[] = array_merge($p, [
    'rate'               => $rate,
    'balance_weight'     => $balance_weight,
    'total_pay'          => $total_pay,
    'tea_bag_sum'        => $tea_bag_sum,

    // Advance
    'adv_sum'            => $adv_amount,
    'adv_paid'           => $adv_paid,
    'adv_balance'        => $adv_balance,
	'advance_issues_total' => $advance_issues_total,

    // Chemical (month & carry-forward)
    'chem_month'         => $chem_month,
    'chem_paid_month'    => $chem_paid_month,
    'chem_prev_unpaid'   => $chem_prev_unpaid,
    'chem_sum'           => $chem_month,        // issued this month
    'chem_paid'          => $chem_paid_month,   // paid against this month's chemical issues
    'chem_balance'       => $chem_balance,      // total remaining (prev + this month unpaid)

    // Others (month & carry-forward)
    'other_month'        => $other_month,
    'other_paid_month'   => $other_paid_month,
    'other_prev_unpaid'  => $other_prev_unpaid,
    'other_sum'          => $other_month,
    'other_paid'         => $other_paid_month,
    'other_balance'      => $other_balance,
	
	'manure_month'       => $manure_month,
'manure_paid_month'  => $manure_paid_month,
'manure_prev_unpaid' => $manure_prev_unpaid,
'manure_balance'     => $manure_balance,

 'lmbalance_prev'     => $lmbalance_prev_total,	
	

    // other fields
    'advance_paid'       => $advance_paid_total,
    'saved_amount'       => $saved_amount,
    'balance'            => $final_balance,
    'paid_amount'        => $paid_amount,
    'status_label'       => $status
	
	
	
	
]);

}

// duplicates highlight map
$dupStmt = $pdo->prepare("SELECT farmer_id, COUNT(*) AS cnt FROM payments WHERE period = :pm GROUP BY farmer_id HAVING cnt > 1");
$dupStmt->execute([':pm'=>$period]);
$dups = $dupStmt->fetchAll(PDO::FETCH_ASSOC);
$dupMap = [];
foreach ($dups as $d) $dupMap[$d['farmer_id']] = $d['cnt'];

// --- RENDER HTML (kept same structure as before) ---
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Payments</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
<style>
/* ✅ force highlight visibility on Bootstrap tables */
tr[row-paid="true"] td {
  background-color: #d4edda !important; /* light green */
}

/* optional duplicate highlight */
tr.dup td {
  background-color: #ffdddd !important; /* light red for duplicates */
}
</style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold text-primary">Payments - <?= htmlspecialchars($period) ?></h4>
   
  </div>

  <!-- Filters -->
  <form class="row g-2 mb-3" method="get">
    <div class="col-md-2">
      <input id="period" name="period" class="form-control" value="<?= htmlspecialchars($period) ?>" placeholder="YYYY-MM">
    </div>
    <div class="col-md-3">
      <select name="area_id" class="form-select">
        <option value="">All Areas</option>
        <?php foreach ($areas as $ar): ?>
          <option value="<?= $ar['id'] ?>" <?= $area_id == $ar['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ar['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <input name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Farmer name or code">
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100">Filter</button>
    </div>
   

	
	
	
  </form>

  <div class="table-responsive">
 <table id="paymentsTable" class="table table-sm table-bordered table-hover">
  <thead class="table-light text-center">
    <tr>
      <th>Area</th>
      <th>Farmer Code</th>
      <th>Farmer Name</th>
      <th>Total Pay (Rs)</th>
      <th>F / Adv Balance (Rs)</th>
      <th>F / Advance (Rs)</th>
      <th>Chemical Balance (Rs)</th>
      <th>Chemical (Rs)</th>
      <th>Others Balance (Rs)</th>
      <th>Others (Rs)</th>
	  
	  <th>Manure Balance (Rs)</th>
<th>Manure (Rs)</th>
	  
      <th>Balance (Rs)</th>
      <th>Save</th>
    </tr>
  </thead>

<tbody>
<?php 
$currentMonth = date('Y-m'); // e.g., 2025-11 (today’s month)

foreach ($computed as $row):
    $isDup = isset($dupMap[$row['farmer_id']]);
    $farmer_id = $row['farmer_id'];
    $period = $row['period'] ?? date('Y-m');

    // Skip if all balances are 0
    $adv_balance  = floatval($row['adv_balance'] ?? 0);
    $chem_balance = floatval($row['chem_balance'] ?? 0);
    $other_balance= floatval($row['other_balance'] ?? 0);
	$manure_balance= floatval($row['manure_balance'] ?? 0);
	
	
    if ($adv_balance <= 0 && $chem_balance <= 0 && $other_balance <= 0 && $manure_balance <= 0) continue;

    // ---------------------------------------------------
    // ✅ Highlight check for current month payments
    // ---------------------------------------------------
    $has_advance = $has_chemical = $has_other = $has_manure = false;

    // Check advance payments
    $sql_adv = "SELECT COUNT(*) AS c FROM f_advance_amount_pay 
                WHERE farmer_id='$farmer_id' AND LEFT(pay_date,7)='$currentMonth'";
    if ($res = $conn->query($sql_adv)) $has_advance = ($res->fetch_assoc()['c'] > 0);

    // Check chemical payments
    $sql_chem = "SELECT COUNT(*) AS c FROM f_chemical_pay 
                 WHERE farmer_id='$farmer_id' AND LEFT(pay_date,7)='$currentMonth'";
    if ($res = $conn->query($sql_chem)) $has_chemical = ($res->fetch_assoc()['c'] > 0);

    // Check other payments
    $sql_other = "SELECT COUNT(*) AS c FROM f_other_pay 
                  WHERE farmer_id='$farmer_id' AND LEFT(pay_date,7)='$currentMonth'";
    if ($res = $conn->query($sql_other)) $has_other = ($res->fetch_assoc()['c'] > 0);
	
	 // Check other payments
    $sql_manure = "SELECT COUNT(*) AS c FROM f_manure_pay 
                  WHERE farmer_id='$farmer_id' AND LEFT(pay_date,7)='$currentMonth'";
    if ($res = $conn->query($sql_manure)) $has_manure = ($res->fetch_assoc()['c'] > 0);

    // ✅ Highlight if any payment exists this month
    $already_paid = ($has_advance || $has_chemical || $has_other || $has_manure);
    $highlight_style = $already_paid ? 'background-color:#d4edda !important;' : ''; // light green background
    // ---------------------------------------------------

    // --- Your existing total and balance calculations ---
    $advance_sql = "SELECT COALESCE(SUM(pay_amount),0) AS total FROM f_advance_amount_pay 
                    WHERE farmer_id='$farmer_id' AND period='$period'";
    $chemical_sql = "SELECT COALESCE(SUM(pay_amount),0) AS total FROM f_chemical_pay 
                     WHERE farmer_id='$farmer_id' AND period='$period'";
    $other_sql    = "SELECT COALESCE(SUM(pay_amount),0) AS total FROM f_other_pay 
                     WHERE farmer_id='$farmer_id' AND period='$period'";
					 
	 $manure_sql    = "SELECT COALESCE(SUM(pay_amount),0) AS total FROM f_manure_pay 
                     WHERE farmer_id='$farmer_id' AND period='$period'";

    $tea_bag_issues_sql = "SELECT COALESCE(SUM(subtotal),0) AS total FROM tea_bag_issues 
                           WHERE farmer_id='$farmer_id' AND DATE_FORMAT(issue_date, '%Y-%m')='$period'";
    $saving_sql         = "SELECT COALESCE(SUM(amount),0) AS total FROM saving 
                           WHERE farmer_id='$farmer_id' AND period='$period'";
    $advancePaidT_sql   = "SELECT COALESCE(SUM(advance_paid),0) AS total FROM collections 
                           WHERE farmer_id='$farmer_id' AND DATE_FORMAT(collection_date, '%Y-%m')='$period'";
    $totcolle_sql       = "SELECT COALESCE(SUM(weight_kg),0) AS total FROM collections 
                           WHERE farmer_id='$farmer_id' AND DATE_FORMAT(collection_date, '%Y-%m')='$period'";
    $bag_weight_sql     = "SELECT bag_weight FROM payments WHERE farmer_id='$farmer_id' AND period='$period'";
    $area_id_sql        = "SELECT area_id FROM farmers WHERE id='$farmer_id'";

    if ($res = $conn->query($area_id_sql)) $area_idT = $res->fetch_assoc()['area_id'] ?? 0;

    $rate_per_kgT = 0;
    $rate_per_kg_sql = "SELECT rate_per_kg FROM area_rates WHERE area_id='$area_idT' AND `year_month`='$period'";
    if ($res = $conn->query($rate_per_kg_sql)) $rate_per_kgT = $res->fetch_assoc()['rate_per_kg'] ?? 0;

    $bag_weightT        = ($conn->query($bag_weight_sql)->fetch_assoc()['bag_weight'] ?? 0);
    $totcolle           = ($conn->query($totcolle_sql)->fetch_assoc()['total'] ?? 0);
    $advancePaidT       = ($conn->query($advancePaidT_sql)->fetch_assoc()['total'] ?? 0);
    $savingT            = ($conn->query($saving_sql)->fetch_assoc()['total'] ?? 0);
    $tea_bag_issuesT    = ($conn->query($tea_bag_issues_sql)->fetch_assoc()['total'] ?? 0);
    $advance_paid_total = ($conn->query($advance_sql)->fetch_assoc()['total'] ?? 0);
    $chemical_paid_total= ($conn->query($chemical_sql)->fetch_assoc()['total'] ?? 0);
    $others_paid_total  = ($conn->query($other_sql)->fetch_assoc()['total'] ?? 0);
	$manure_paid_total  = ($conn->query($manure_sql)->fetch_assoc()['total'] ?? 0);
// 🔹 Get current month's l_m_double_balance (status=1)
$lmb_double_sql = "
SELECT COALESCE(l_m_double_balance, 0) AS double_balance
FROM payments
WHERE farmer_id = '$farmer_id'
  AND period = '$period'
  AND status IN (1, 3)
LIMIT 1
";
$lmb_double_balance = 0;
if ($res = $conn->query($lmb_double_sql)) {
    $r = $res->fetch_assoc();
    if ($r && isset($r['double_balance'])) {
        $lmb_double_balance = floatval($r['double_balance']);
    }
}



		
	$lmbalance_prev_total = 0;
$lmb_sql = "SELECT COALESCE(SUM(l_m_balance),0) AS total
FROM payments
WHERE farmer_id = '$farmer_id'
  AND period < '$period'
  AND status = 3";
if ($res = $conn->query($lmb_sql)) {
    $r = $res->fetch_assoc();
    $lmbalance_prev_total = $r['total'];
}	   
	
$lmb_sql = "
SELECT COALESCE(SUM(l_m_balance), 0) AS total
FROM payments
WHERE farmer_id = '$farmer_id'
  AND period < '$period'
  AND status = 3
";
$res = $conn->query($lmb_sql);
$lmbalance_total = 0;
if ($res) {
    $r = $res->fetch_assoc();
    $lmbalance_total = $r['total'] ;
}
	
$lmbalance_total = $lmbalance_total + $lmb_double_balance;	
	
	
	
	
	$totcolle = floatval($totcolle ?? 0);
$bag_weightT = floatval($bag_weightT ?? 0);
$rate_per_kgT = floatval($rate_per_kgT ?? 0);
$advance_paid_total = floatval($advance_paid_total ?? 0);
$chemical_paid_total = floatval($chemical_paid_total ?? 0);
$others_paid_total = floatval($others_paid_total ?? 0);
$tea_bag_issuesT = floatval($tea_bag_issuesT ?? 0);
$savingT = floatval($savingT ?? 0);
$advancePaidT = floatval($advancePaidT ?? 0);
$advance_issues_total = floatval($row['advance_issues_total'] ?? 0);
$full = (($totcolle - $bag_weightT) * $rate_per_kgT)
       - ($advance_paid_total + $advance_issues_total + $lmbalance_prev_total + $lmb_double_balance + $chemical_paid_total + $others_paid_total + $manure_paid_total + $tea_bag_issuesT + $savingT + $advancePaidT);
$full1 = (($totcolle - $bag_weightT) * $rate_per_kgT)
            - ($tea_bag_issuesT + $advance_issues_total + $savingT + $advancePaidT + $lmbalance_prev_total + $lmb_double_balance);
?>
<tr class="<?= ($isDup ? 'dup' : '') ?>" <?= $already_paid ? 'row-paid="true"' : '' ?>>
  <td><?= htmlspecialchars($row['area_name'] ?? '-') ?></td>
  <td><?= htmlspecialchars($row['farmer_code']) ?></td>
  <td><?= htmlspecialchars($row['farmer_name']) ?></td>
  <td class="text-end"><?= number_format($full1, 2) ?></td>

  <td class="text-end"><?= number_format($row['adv_balance'],2) ?></td>
  <td class="text-center">
    <input type="number" step="0.01" class="form-control form-control-sm text-end adv-paid-input"
           data-farmer="<?= $row['farmer_id'] ?>"
           value="<?= number_format($row['adv_sum'],2) ?>">
  </td>

  <td class="text-end"><?= number_format($row['chem_balance'],2) ?></td>
  <td class="text-center">
    <input type="number" step="0.01" class="form-control form-control-sm text-end chem-paid-input"
           data-farmer="<?= $row['farmer_id'] ?>"
           value="">
  </td>

  <td class="text-end"><?= number_format($row['other_balance'],2) ?></td>
  <td class="text-center">
    <input type="number" step="0.01" class="form-control form-control-sm text-end other-paid-input"
           data-farmer="<?= $row['farmer_id'] ?>"
           value="">
  </td>
  
  
  <td class="text-end"><?= number_format($row['manure_balance'],2) ?></td>
<td class="text-center">
  <input type="number" step="0.01" class="form-control form-control-sm text-end manure-paid-input"
         data-farmer="<?= $row['farmer_id'] ?>"
         value="">
</td>

  
  

  <td class="text-end text-success fw-bold"><?= number_format($full, 2) ?></td>
  <td class="text-center"><button class="btn btn-sm btn-primary save-btn">Save</button></td>
</tr>
<?php endforeach; ?>
</tbody>

</table>

  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <nav>
    <ul class="pagination justify-content-center mt-3">
      <?php for ($i=1;$i<=$totalPages;$i++): ?>
        <li class="page-item <?= $i==$page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&period=<?= urlencode($period) ?>&area_id=<?= urlencode($area_id) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>

</div>

<!-- JS libs -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

<script>
$(function(){
  $('#paymentsTable').DataTable({
    paging: false,
    searching: false,
    info: false,
    dom: 'Bfrtip',
    buttons: [
      { extend: 'excelHtml5', className: 'btn btn-success btn-sm', text: 'Export Excel' },
      { extend: 'pdfHtml5', className: 'btn btn-danger btn-sm', text: 'Export PDF' }
    ]
  });

  // ✅ Save button click handler
  $(document).on('click', '.save-btn', function(){
    const row = $(this).closest('tr');
    const farmer = row.find('.adv-paid-input').data('farmer');
    const advAmount = parseFloat(row.find('.adv-paid-input').val()) || 0;
    const chemAmount = parseFloat(row.find('.chem-paid-input').val()) || 0;
    const otherAmount = parseFloat(row.find('.other-paid-input').val()) || 0;
	const manureAmount = parseFloat(row.find('.manure-paid-input').val()) || 0;
    const period = $('#period').val();

    
	if (advAmount <= 0 && chemAmount <= 0 && otherAmount <= 0 && manureAmount <= 0) {
  alert('Enter at least one valid amount!');
  return;
}

	
	
	
    const btn = $(this);
    btn.prop('disabled', true).text('Saving...');

    const promises = [];

   if (advAmount > 0) {
  promises.push($.post('pay.php', { action: 'pay_advance', farmer_id: farmer, pay_amount: advAmount, period }));
}
if (chemAmount > 0) {
  promises.push($.post('pay.php', { action: 'pay_chemical', farmer_id: farmer, pay_amount: chemAmount, period }));
}
if (otherAmount > 0) {
  promises.push($.post('pay.php', { action: 'pay_others', farmer_id: farmer, pay_amount: otherAmount, period }));
}
if (manureAmount > 0) {
  promises.push($.post('pay.php', { action: 'pay_manure', farmer_id: farmer, pay_amount: manureAmount, period }));
}


	
    Promise.all(promises).then(results => {
		
		$.get('get_payment_row.php', { farmer_id: farmer, period }, function (html) {
  const newRow = $(html).hide();
  row.replaceWith(newRow);
  newRow.fadeIn().css('background-color', '#d4edda');
  setTimeout(() => newRow.css('background-color', ''), 2000);
});

		
		
    }).catch(() => {
      row.css('background-color', '#f8d7da');
      btn.text('Error ⚠').prop('disabled', false);
    });
  });
});


$.get('get_payment_row.php', { farmer_id: farmer, period }, function (html) {
  const newRow = $(html).hide();
  row.replaceWith(newRow);
  newRow.fadeIn().css('background-color', '#d4edda');
  setTimeout(() => newRow.css('background-color', ''), 2000);
});
</script>
</body>
</html>
