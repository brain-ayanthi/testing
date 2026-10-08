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

// ----------------- AJAX actions -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ---------- Generate/Update Payments for month ----------
    if ($action === 'generate_all') {
        $period = $_POST['period'] ?? $period;
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT c.farmer_id, f.area_id,
                       SUM(c.weight_kg) AS total_wt
                FROM collections c
                JOIN farmers f ON c.farmer_id = f.id
                WHERE DATE_FORMAT(c.collection_date, '%Y-%m') = :pm
                  AND c.status = 0
                GROUP BY c.farmer_id, f.area_id
            ");
            $stmt->execute([':pm' => $period]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $checkStmt = $pdo->prepare("SELECT id FROM payments WHERE farmer_id = :f AND period = :p LIMIT 1");
            $insertStmt = $pdo->prepare("
                INSERT INTO payments (farmer_id, period, total_collection, total_payable, saved_amount, paid_amount, created_at, bag_weight)
                VALUES (:f,:p,:tc,:tp,0,0,NOW(),0)
            ");
            $updateStmt = $pdo->prepare("
                UPDATE payments
                SET total_collection = :tc, total_payable = :tp, updated_at = NOW()
                WHERE farmer_id = :f AND period = :p
            ");
            $markCollections = $pdo->prepare("
                UPDATE collections
                SET status = 1
                WHERE farmer_id = :f AND DATE_FORMAT(collection_date, '%Y-%m') = :pm
            ");
            $rateStmt = $pdo->prepare("SELECT `rate_per_kg` FROM `area_rates` WHERE `area_id` = :aid AND `year_month` = :pm LIMIT 1");

            foreach ($rows as $r) {
                $farmerId = (int)$r['farmer_id'];
                $areaId = (int)$r['area_id'];

                $totStmt = $pdo->prepare("
                    SELECT SUM(weight_kg) AS total_wt
                    FROM collections
                    WHERE farmer_id = :f AND DATE_FORMAT(collection_date, '%Y-%m') = :pm
                ");
                $totStmt->execute([':f'=>$farmerId, ':pm'=>$period]);
                $totRow = $totStmt->fetch(PDO::FETCH_ASSOC);
                $totalWeight = floatval($totRow['total_wt'] ?? 0);

                $rateStmt->execute([':aid'=>$areaId, ':pm'=>$period]);
                $rrow = $rateStmt->fetch(PDO::FETCH_ASSOC);
                $rate = $rrow ? floatval($rrow['rate_per_kg']) : 0.0;

                $bag_weight = 0.0;
                $balance_weight = max(0, $totalWeight - $bag_weight);
                $total_pay = round($balance_weight * $rate, 2);

                $checkStmt->execute([':f'=>$farmerId, ':p'=>$period]);
                if ($checkStmt->rowCount() > 0) {
                    $updateStmt->execute([':tc'=>$totalWeight, ':tp'=>$total_pay, ':f'=>$farmerId, ':p'=>$period]);
                } else {
                    $insertStmt->execute([':f'=>$farmerId, ':p'=>$period, ':tc'=>$totalWeight, ':tp'=>$total_pay]);
                }

                $markCollections->execute([':f'=>$farmerId, ':pm'=>$period]);
            }

            $pdo->commit();
            json_exit(['status'=>true, 'message'=>'Payments generated/updated successfully.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            json_exit(['status'=>false, 'message'=>'Error: '.$e->getMessage()]);
        }
    }

    // ---------- Save bag weight ----------
    if ($action === 'save_bag') {
        $payment_id = intval($_POST['payment_id'] ?? 0);
        $bag_weight = floatval($_POST['bag_weight'] ?? 0);
        if ($payment_id <= 0) json_exit(['status'=>false, 'message'=>'Invalid payment id']);

        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE payments SET bag_weight = :bw, updated_at = NOW() WHERE id = :id")
                ->execute([':bw'=>$bag_weight, ':id'=>$payment_id]);
            $pdo->commit();
            json_exit(['status'=>true, 'message'=>'Bag weight saved']);
        } catch (Exception $e) {
            $pdo->rollBack();
            json_exit(['status'=>false, 'message'=>'Error saving bag weight: '.$e->getMessage()]);
        }
    }

    // ---------- Agree (save 10%) ----------
  // ---------- Agree (save 10%) ----------
if ($action === 'agree') {

    $payment_id = intval($_POST['payment_id'] ?? 0);
    $farmer_id  = intval($_POST['farmer_id'] ?? 0);

    if ($payment_id <= 0) json_exit(['status'=>false, 'message'=>'Invalid payment id']);

    try {
        $pdo->beginTransaction();

        // 1️⃣ Get Payment + Farmer info
        $p = $pdo->prepare("SELECT pay.*, f.code AS farmer_code, f.name AS farmer_name, f.area_id 
                            FROM payments pay 
                            JOIN farmers f ON pay.farmer_id = f.id 
                            WHERE pay.id = :id 
                            LIMIT 1");
        $p->execute([':id' => $payment_id]);
        $pay = $p->fetch(PDO::FETCH_ASSOC);
        if (!$pay) throw new Exception('Payment record not found');

        // Always use DB value
        $farmer_id = intval($pay['farmer_id']);
        $period    = $pay['period'] ?? date('Y-m');

        // 2️⃣ Get total collection weight (from payments table)
        $q = $pdo->prepare("
            SELECT COALESCE(total_collection, 0) 
            FROM payments 
            WHERE farmer_id = :fid 
              AND period = :period 
            LIMIT 1
        ");
        $q->execute([':fid' => $farmer_id, ':period' => $period]);
        $total_collected = (float)($q->fetchColumn() ?? 0);

        // 3️⃣ Get area rate
        $q = $pdo->prepare("SELECT rate_per_kg 
                            FROM area_rates 
                            WHERE area_id = :aid 
                              AND `year_month` = :period 
                            LIMIT 1");
        $q->execute([
            ':aid' => $pay['area_id'],
            ':period' => $period
        ]);
        $rate_per_kg = (float)($q->fetchColumn() ?? 0);

        if ($rate_per_kg <= 0) {
            throw new Exception("No rate found for area_id={$pay['area_id']} and period={$period}");
        }

        // 4️⃣ Calculate saving amount
        $bag_weight = (float)$pay['bag_weight'];
        $amount = round((($total_collected - $bag_weight) * $rate_per_kg) * 0.10, 2);

        // 5️⃣ Insert or Update saving table
        $chk = $pdo->prepare("SELECT id FROM saving WHERE payment_id = :pid LIMIT 1");
        $chk->execute([':pid' => $payment_id]);

        if ($chk->rowCount() > 0) {
            // 🟢 Update existing saving
            $pdo->prepare("UPDATE saving 
                           SET amount = :amt,
                               status = 'agree',
                               saving_date = CURDATE(),
                               farmer_code = :fc,
                               farmer_name = :fn,
                               period = :period,
                               farmer_id = :fid
                           WHERE payment_id = :pid")
                ->execute([
                    ':amt'    => $amount,
                    ':fc'     => $pay['farmer_code'],
                    ':fn'     => $pay['farmer_name'],
                    ':period' => $period,
                    ':fid'    => $farmer_id,
                    ':pid'    => $payment_id
                ]);
        } else {
            // 🟢 Insert new saving
            $pdo->prepare("INSERT INTO saving 
                           (payment_id, farmer_code, farmer_name, amount, status, saving_date, period, farmer_id)
                           VALUES (:pid, :fc, :fn, :amt, 'agree', CURDATE(), :period, :fid)")
                ->execute([
                    ':pid'    => $payment_id,
                    ':fc'     => $pay['farmer_code'],
                    ':fn'     => $pay['farmer_name'],
                    ':amt'    => $amount,
                    ':period' => $period,
                    ':fid'    => $farmer_id
                ]);
        }

        // 6️⃣ Update payments table
        $pdo->prepare("UPDATE payments SET saved_amount = :amt WHERE id = :pid")
            ->execute([':amt' => $amount, ':pid' => $payment_id]);

        $pdo->commit();
        json_exit(['status'=>true, 'message'=>'Saved amount (10%) applied', 'amount'=>$amount]);

    } catch (Exception $e) {
        $pdo->rollBack();
        json_exit(['status'=>false, 'message'=>'Error: '.$e->getMessage()]);
    }
}



    // ---------- Not agree ----------
    if ($action === 'not_agree') {
        $payment_id = intval($_POST['payment_id'] ?? 0);
        if ($payment_id <= 0) json_exit(['status'=>false, 'message'=>'Invalid payment id']);
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM saving WHERE payment_id = :pid")->execute([':pid'=>$payment_id]);
            $pdo->prepare("UPDATE payments SET saved_amount = 0 WHERE id = :pid")->execute([':pid'=>$payment_id]);
            $pdo->commit();
            json_exit(['status'=>true, 'message'=>'Saving removed']);
        } catch (Exception $e) {
            $pdo->rollBack();
            json_exit(['status'=>false, 'message'=>'Error: '.$e->getMessage()]);
        }
    }

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
    SELECT pay.*, f.code AS farmer_code, f.name AS farmer_name, f.area_id
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
$savingStmt = $pdo->prepare("SELECT amount, status FROM saving WHERE payment_id = :pid LIMIT 1");

// New statements for chemical/others month & previous balances
$chemMonthStmt = $pdo->prepare("SELECT COALESCE(SUM(subtotal),0) FROM chemical_issue WHERE farmer_id = :f AND DATE_FORMAT(issue_date, '%Y-%m') = :pm");
$chemPaidMonthStmt = $pdo->prepare("SELECT COALESCE(SUM(paid),0) FROM chemical_issue WHERE farmer_id = :f AND DATE_FORMAT(issue_date, '%Y-%m') = :pm");
$chemPrevUnpaidStmt = $pdo->prepare("SELECT COALESCE(SUM(subtotal - paid),0) FROM chemical_issue WHERE farmer_id = :f AND DATE_FORMAT(issue_date, '%Y-%m') < :pm");

$otherMonthStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM others WHERE farmer_id = :f AND DATE_FORMAT(issue_date, '%Y-%m') = :pm");
$otherPaidMonthStmt = $pdo->prepare("SELECT COALESCE(SUM(paid),0) FROM others WHERE farmer_id = :f AND DATE_FORMAT(issue_date, '%Y-%m') = :pm");
$otherPrevUnpaidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount - paid),0) FROM others WHERE farmer_id = :f AND DATE_FORMAT(issue_date, '%Y-%m') < :pm");

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

    // any advances collected in collections (advance_paid)
    $advancePaidStmt->execute([':f'=>$farmer_id, ':pm'=>$period]);
    $advance_paid = floatval($advancePaidStmt->fetchColumn());

    // saving
    $savingStmt->execute([':pid'=>$p['id']]);
    $saveRow = $savingStmt->fetch(PDO::FETCH_ASSOC);
    $saved_amount = $saveRow ? floatval($saveRow['amount']) : floatval($p['saved_amount'] ?? 0);

    // paid amount from payments table
    $paid_amount = floatval($p['paid_amount'] ?? 0);

    // compute final balance for payment screen:
    // Deduct saved_amount + tea_bag_sum + advance_paid + (chemical balance for period?) + (others balance for period?)
    // Here we treat chemical and others amounts that belong to this period + previous unpaid carried amounts as deductions.
    $balance = round($total_pay - ($saved_amount + $tea_bag_sum + $advance_paid + $chem_balance + $other_balance), 2);

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

// 🧮 Step 4: Calculate final balance (only deduct if paid)
$final_balance = $first_balance;

// 🧮 Step 5: Compute unpaid carry-forwards for next month
$chem_balance  = ($chem_prev_unpaid + ($chem_month - $chem_paid_month));
$other_balance = ($other_prev_unpaid + ($other_month - $other_paid_month));

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
.table .dup { background:#ffdddd; } /* duplicate highlight */
.small-input { width:100px; display:inline-block; }

.btn-secondary {
    --bs-btn-bg: #0d6efd!important;
}

</style>
</head>
<body>
<?php include 'partials/topnav.php'; ?>

<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold text-primary">Payments - <?= htmlspecialchars($period) ?></h4>
    <a href="payments.php" class="btn btn-outline-secondary">Reset</a>
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
    <div class="col-md-2">
      <button type="button" id="generateBtn" class="btn btn-lg btn-success w-100" <?= $generateDisabled ? 'disabled' : '' ?>>Generate / Update Payments</button>
    </div>
  </form>

  <div class="mb-2 text-muted">Pending collections for <?= htmlspecialchars($period) ?>: <strong><?= $pendingCollectionsCount ?></strong></div>

  <div class="table-responsive">
    <table id="paymentsTable" class="table table-sm table-bordered table-hover">
  <thead class="table-light text-center">
    <tr>
      <th>Area</th>
      <th>Farmer Code</th>
      <th>Farmer Name</th>
      <th>Total Kg</th>
      <th>Bag Weight (kg)</th>
      <th>Balance Kg</th>
      <th>Rate (Rs/kg)</th>
      <th>Total Pay (Rs)</th>
      <th>Savings Status</th>
      <th>Savings (10%)</th>
      <th>Advance Paid (Rs)</th>
      <th>Tea Bag (Rs)</th>
      <th>F / Advance (Rs)</th>
      <th>Chemical (Rs)</th>
      <th>Others (Rs)</th>

    
      <th>Balance (Rs)</th>
      <th>Paid (Rs)</th>
      <th>Status</th>
      <th>Action</th>
    </tr>
  </thead>

  <tbody>
    <?php foreach ($computed as $row):
        $isDup = isset($dupMap[$row['farmer_id']]);
        $farmer_id = $row['farmer_id'];
        $period = $row['period'] ?? date('Y-m'); // ensure period exists

        // --- Fetch period-based pay totals ---
        $advance_paid_total = 0;
        $chemical_paid_total = 0;
        $others_paid_total = 0;

        $advance_sql = "SELECT COALESCE(SUM(pay_amount),0) AS total FROM f_advance_amount_pay WHERE farmer_id='$farmer_id' AND period='$period'";
        $chemical_sql = "SELECT COALESCE(SUM(pay_amount),0) AS total FROM f_chemical_pay WHERE farmer_id='$farmer_id' AND period='$period'";
        $other_sql = "SELECT COALESCE(SUM(pay_amount),0) AS total FROM f_other_pay WHERE farmer_id='$farmer_id' AND period='$period'";
		
		$tea_bag_issues_sql = "SELECT COALESCE(SUM(subtotal),0) AS total FROM tea_bag_issues WHERE farmer_id='$farmer_id' AND DATE_FORMAT(issue_date, '%Y-%m')='$period'";
		$saving_sql = "SELECT COALESCE(SUM(amount),0) AS total FROM saving WHERE farmer_id='$farmer_id' AND period='$period'";
		$advancePaidT_sql = "SELECT COALESCE(SUM(advance_paid),0) AS total FROM collections WHERE farmer_id='$farmer_id' AND DATE_FORMAT(collection_date, '%Y-%m')='$period'";
		
		$totcolle_sql = "SELECT COALESCE(SUM(weight_kg),0) AS total FROM collections WHERE farmer_id='$farmer_id' AND DATE_FORMAT(collection_date, '%Y-%m')='$period'";
		$bag_weight_sql = "SELECT bag_weight FROM payments WHERE farmer_id = '$farmer_id' AND period = '$period'";

		$area_id_sql = "SELECT area_id FROM farmers WHERE id = '$farmer_id' ";
		if ($res = $conn->query($area_id_sql)) {
            $r = $res->fetch_assoc();
            $area_idT = $r['area_id'];
        }
		
		$rate_per_kgT = 0; // default

		$rate_per_kg_sql = " SELECT rate_per_kg FROM area_rates  WHERE area_id = '$area_idT' AND `year_month` = '$period'";
		
		if ($res = $conn->query($rate_per_kg_sql)) {
		if ($r = $res->fetch_assoc()) {
        $rate_per_kgT = $r['rate_per_kg'];
    }
}
		
		if ($res = $conn->query($bag_weight_sql)) {
    $r = $res->fetch_assoc();
    $bag_weightT = $r['bag_weight']; // ✅ use this
}
		
		if ($res = $conn->query($totcolle_sql)) {
    $r = $res->fetch_assoc();
    $totcolle = $r['total']; // ✅ use this
}
		
		if ($res = $conn->query($advancePaidT_sql)) {
            $r = $res->fetch_assoc();
            $advancePaidT = $r['total'];
        }

		if ($res = $conn->query($saving_sql)) {
            $r = $res->fetch_assoc();
            $savingT = $r['total'];
        }

		if ($res = $conn->query($tea_bag_issues_sql)) {
            $r = $res->fetch_assoc();
            $tea_bag_issuesT = $r['total'];
        }

        if ($res = $conn->query($advance_sql)) {
            $r = $res->fetch_assoc();
            $advance_paid_total = $r['total'];
        }
        if ($res = $conn->query($chemical_sql)) {
            $r = $res->fetch_assoc();
            $chemical_paid_total = $r['total'];
        }
        if ($res = $conn->query($other_sql)) {
            $r = $res->fetch_assoc();
            $others_paid_total = $r['total'];
        }
		
		   
	
		$totcolle = floatval($totcolle ?? 0);
$bag_weightT = floatval($bag_weightT ?? 0);
$rate_per_kgT = floatval($rate_per_kgT ?? 0);
$advance_paid_total = floatval($advance_paid_total ?? 0);
$chemical_paid_total = floatval($chemical_paid_total ?? 0);
$others_paid_total = floatval($others_paid_total ?? 0);
$tea_bag_issuesT = floatval($tea_bag_issuesT ?? 0);
$savingT = floatval($savingT ?? 0);
$advancePaidT = floatval($advancePaidT ?? 0);

$full = (($totcolle - $bag_weightT) * $rate_per_kgT)
       - ($advance_paid_total + $chemical_paid_total + $others_paid_total + $tea_bag_issuesT + $savingT + $advancePaidT);

		
		
		
		
    ?>
    <tr class="<?= $isDup ? 'dup' : '' ?>">
      <td><?= htmlspecialchars($row['area_name'] ?? '-') ?></td>
      <td><?= htmlspecialchars($row['farmer_code']) ?></td>
      <td><?= htmlspecialchars($row['farmer_name']) ?></td>
      <td class="text-end"><?= number_format($row['total_collection'],2) ?></td>

      <td class="text-center">
        <input type="number" step="0.001" class="form-control form-control-sm text-end bag-input"
               data-payment="<?= $row['id'] ?>"
               value="<?= number_format($row['bag_weight'] ?? 0,3) ?>">
        <button class="btn btn-sm btn-outline-primary mt-1 save-bag" data-payment="<?= $row['id'] ?>">Save</button>
      </td>

      <td class="text-end"><?= number_format($row['balance_weight'],3) ?></td>
      <td class="text-end"><?= number_format($row['rate'],2) ?></td>
      <td class="text-end"><?= number_format($row['total_pay'],2) ?></td>

      <td class="text-center">
        <button class="btn btn-sm btn-outline-success agree-btn" data-payment="<?= $row['id'] ?>" <?= ($row['saved_amount']>0)?'disabled':'' ?>>Agree</button>
        <button class="btn btn-sm btn-outline-danger notagree-btn" data-payment="<?= $row['id'] ?>" <?= ($row['saved_amount']>0)?'':'disabled' ?>>NotAgree</button>
      </td>

      <td class="text-end saved-amount"><?= number_format($row['saved_amount'],2) ?></td>
      <td class="text-end"><?= number_format($row['advance_paid'],2) ?></td>
      <td class="text-end"><?= number_format($row['tea_bag_sum'],2) ?></td>

      <!-- Advance -->
      <td class="text-center">
        <input type="number" step="0.01" class="form-control form-control-sm text-end adv-paid-input"
               data-farmer="<?= $row['farmer_id'] ?>"
               value="<?= number_format($row['adv_sum'],2) ?>">
        <small class="text-muted">Bal: <?= number_format($row['adv_balance'],2) ?></small>
        <button class="btn btn-sm btn-outline-primary mt-1 pay-adv-btn" data-farmer="<?= $row['farmer_id'] ?>">Pay</button>
      </td>

      <!-- Chemical -->
      <td class="text-center">
        <input type="number" step="0.01" class="form-control form-control-sm text-end chem-paid-input"
               data-farmer="<?= $row['farmer_id'] ?>"
               value="<?= number_format($row['chem_month'],2) ?>">
        <small class="text-muted"> Bal: <?= number_format($row['chem_balance'],2) ?></small>
        <button class="btn btn-sm btn-outline-warning mt-1 pay-chem-btn" data-farmer="<?= $row['farmer_id'] ?>">Pay</button>
      </td>

      <!-- Others -->
      <td class="text-center">
        <input type="number" step="0.01" class="form-control form-control-sm text-end other-paid-input"
               data-farmer="<?= $row['farmer_id'] ?>"
               value="<?= number_format($row['other_month'],2) ?>">
        <small class="text-muted">  Bal: <?= number_format($row['other_balance'],2) ?></small>
        <button class="btn btn-sm btn-outline-danger mt-1 pay-other-btn" data-farmer="<?= $row['farmer_id'] ?>">Pay</button>
      </td>

      <!-- 🆕 Show This Period Totals -->
      
      <td class="text-end text-success fw-bold"><?= number_format($full, 2) ?></td>
      <td class="text-end"><?= number_format($row['paid_amount'],2) ?></td>
      <td class="text-center"><span class="badge bg-<?= strtolower($row['status_label'])=='paid' ? 'success' : 'warning' ?>"><?= htmlspecialchars($row['status_label']) ?></span></td>
      <td class="text-center">
     <?php if ($row['status'] == 0): ?>
  <a href="payment_view.php?id=<?= $row['id'] ?>&farmer_id=<?= $row['farmer_id'] ?>&period=<?= $period ?>" 
     class="btn btn-sm btn-primary pay-link"
     data-id="<?= $row['id'] ?>"
     data-farmer-id="<?= $row['farmer_id'] ?>"
     data-period="<?= $period ?>"
     data-full="<?= $full ?>">
    Pay
  </a>
<?php else: ?>
  <button class="btn btn-sm btn-secondary" disabled>Pay</button>
<?php endif; ?>

       <a href="payment_view.php?id=<?= $row['id'] ?>&farmer_id=<?= $row['farmer_id'] ?>&period=<?= $period ?>"
   class="btn btn-sm <?= $row['status'] == 1 ? 'btn-secondary' : 'btn-outline-secondary disabled' ?>"
   <?= $row['status'] == 0 ? 'tabindex="-1" aria-disabled="true"' : '' ?>data-id="<?= $row['id'] ?>"
   data-farmer-id="<?= $row['farmer_id'] ?>"
   data-period="<?= $period ?>"
   data-full="<?= $full ?>">
   Slip
</a>
      </td>
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

  $('#generateBtn').on('click', function(){
    if(!confirm('Generate/Update payments for <?= $period ?>? This will mark collections status=1 for processed farmers.')) return;
    const btn = $(this).prop('disabled', true).text('Processing...');
    $.post('', { action: 'generate_all', period: '<?= $period ?>' }, function(res){
      if(res.status){ alert(res.message); location.reload(); }
      else { alert(res.message || 'Failed.'); btn.prop('disabled', false).text('Generate / Update Payments'); }
    }, 'json').fail(()=>{ alert('Server error'); btn.prop('disabled', false).text('Generate / Update Payments'); });
  });

  // Save bag weight
  $(document).on('click', '.save-bag', function(){
    const id = $(this).data('payment');
    const input = $(this).closest('td').find('.bag-input');
    const val = parseFloat(input.val()) || 0;
    const btn = $(this);
    btn.prop('disabled', true).text('Saving...');
    $.post('', { action: 'save_bag', payment_id: id, bag_weight: val }, function(res){
      alert(res.message);
      if(res.status) location.reload();
      else btn.prop('disabled', false).text('Save');
    }, 'json').fail(()=>{ alert('Server error'); btn.prop('disabled', false).text('Save'); });
  });

  // Agree / NotAgree
  $(document).on('click', '.agree-btn', function(){
    const id = $(this).data('payment');
    if(!confirm('Agree saving of 10% for this farmer?')) return;
    const btn = $(this); btn.prop('disabled', true).text('Saving...');
    $.post('', { action: 'agree', payment_id: id }, function(res){
      alert(res.message); if(res.status) location.reload(); else btn.prop('disabled', false).text('Agree');
    }, 'json').fail(()=>{ alert('Server error'); btn.prop('disabled', false).text('Agree'); });
  });
  $(document).on('click', '.notagree-btn', function(){
    const id = $(this).data('payment');
    if(!confirm('Remove saving for this farmer?')) return;
    const btn = $(this); btn.prop('disabled', true).text('Removing...');
    $.post('', { action: 'not_agree', payment_id: id }, function(res){
      alert(res.message); if(res.status) location.reload(); else btn.prop('disabled', false).text('NotAgree');
    }, 'json').fail(()=>{ alert('Server error'); btn.prop('disabled', false).text('NotAgree'); });
  });

  // Pay Advance
  $(document).on('click', '.pay-adv-btn', function(){
    let farmer = $(this).data('farmer');
    let amount = $(this).closest('td').find('.adv-paid-input').val();
    let period = $('#period').val();
    if(!amount || amount <= 0) { alert('Enter valid amount!'); return; }
    $.post('payments.php', { action: 'pay_advance', farmer_id: farmer, pay_amount: amount, period: period }, function(res){
      alert(res.message);
      if(res.status) location.reload();
    }, 'json');
  });

  // Pay Chemical
  $(document).on('click', '.pay-chem-btn', function(){
    let farmer = $(this).data('farmer');
    let amount = $(this).closest('td').find('.chem-paid-input').val();
    let period = $('#period').val();
    if(!amount || amount <= 0) { alert('Enter valid amount!'); return; }
    $.post('payments.php', { action: 'pay_chemical', farmer_id: farmer, pay_amount: amount, period: period }, function(res){
      alert(res.message);
      if(res.status) location.reload();
    }, 'json');
  });

  // Pay Others
  $(document).on('click', '.pay-other-btn', function(){
    let farmer = $(this).data('farmer');
    let amount = $(this).closest('td').find('.other-paid-input').val();
    let period = $('#period').val();
    if(!amount || amount <= 0) { alert('Enter valid amount!'); return; }
    $.post('payments.php', { action: 'pay_others', farmer_id: farmer, pay_amount: amount, period: period }, function(res){
      alert(res.message);
      if(res.status) location.reload();
    }, 'json');
  });

});
</script>

<script>
$(document).on('click', '.pay-link', function(e) {
  e.preventDefault(); // stop normal link behavior

  const link = $(this);
  const id = link.data('id');
  const farmerId = link.data('farmer-id');
  const period = link.data('period');
  const full = link.data('full');
  const href = link.attr('href');

  if (!confirm('Are you sure you want to mark this as paid and print the slip?')) return;

  // open slip in new tab for printing
  window.open(href, '_blank');

  // update payment status in backend
  $.ajax({
    url: 'update_payment_status.php',
    type: 'POST',
    data: { id: id, farmer_id: farmerId, period: period, full: full },
    success: function(res) {
      try {
        const json = JSON.parse(res);
        if (json.success) {
          alert('Payment updated successfully!');
          link.removeClass('btn-primary')
              .addClass('btn-secondary disabled')
              .text('Paid')
              .attr('onclick', 'return false;');
        } else {
          alert('Error: ' + json.message);
        }
      } catch (e) {
        alert('Unexpected response: ' + res);
      }
    },
    error: function() {
      alert('Server error occurred while updating.');
    }
  });
});
</script>



</body>
</html>
