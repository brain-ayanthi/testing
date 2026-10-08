<?php
require_once __DIR__ . '/../init.php';
require_login();
include('db.php');

header('Content-Type: application/json; charset=utf-8');

$id        = intval($_POST['id'] ?? 0);
$farmer_id = intval($_POST['farmer_id'] ?? 0);
$period    = $_POST['period'] ?? '';
$full      = floatval($_POST['full'] ?? 0);   // for LMB deduction
$full1     = floatval($_POST['full1'] ?? 0);  // for paid_amount + l_m_com_balance
$lmbalance_total = floatval($_POST['lmbalance_total'] ?? 0);


if (!$id || !$farmer_id || !$period) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    $conn->begin_transaction();

    // 🔹 Step 1: Deduct previous LMB balances (status=3 or 0)
    $stmtPrev = $conn->prepare("
        SELECT id, l_m_balance, status 
        FROM payments 
        WHERE farmer_id = ? AND period < ? AND (status = 3 OR status = 0)
        ORDER BY period ASC
    ");
    $stmtPrev->bind_param("is", $farmer_id, $period);
    $stmtPrev->execute();
    $resultPrev = $stmtPrev->get_result();

    $remainingBalance = $full;
    $deductedTotal = 0;

    while ($row = $resultPrev->fetch_assoc()) {
        $lmbId = intval($row['id']);
        $lmbValue = floatval($row['l_m_balance']);
        $currStat = intval($row['status']);
        if ($lmbValue <= 0) continue;

        // ✅ Apply your custom status rules
        $newStat = $currStat;
        if ($full < 0) {
            if ($currStat == 3) $newStat = 1; // 3 → 1
            elseif ($currStat == 0) $newStat = 3; // 0 → 3
        } elseif ($full > 0) {
            if ($currStat == 0) $newStat = 1; // 0 → 1
        }

        // Update this row’s status if changed
        if ($newStat != $currStat) {
            $upd = $conn->prepare("UPDATE payments SET status = ?, updated_at = NOW() WHERE id = ?");
            $upd->bind_param("ii", $newStat, $lmbId);
            $upd->execute();
        }

        // 🔹 FIFO LMB clearing only for positive payments
        if ($full > 0 && $currStat == 3) {
            if ($remainingBalance <= 0) break;

            if ($remainingBalance >= $lmbValue) {
                // Full clear
                $remainingBalance -= $lmbValue;
                $deductedTotal += $lmbValue;
                $upd = $conn->prepare("UPDATE payments SET status = 1 WHERE id = ?");
                $upd->bind_param("i", $lmbId);
                $upd->execute();
            } else {
                // Partial clear
                $deduct = $remainingBalance;
                $remainingBalance = 0;
                $deductedTotal += $deduct;
                $newBalance = $lmbValue - $deduct;
                $upd = $conn->prepare("UPDATE payments SET l_m_balance = ?, updated_at = NOW() WHERE id = ?");
                $upd->bind_param("di", $newBalance, $lmbId);
                $upd->execute();
            }
        }
    } // <-- ✅ closes while loop correctly

    // 🔹 Step 2: Determine leftover and new status for current payment
    $leftover = $remainingBalance;
    $newStatus = ($leftover > 0) ? 1 : 0; 
	$newStatus1 = ($leftover < 0) ? 3 : 1;
	
	
    // 🔹 Step 3: Update current period payment
    if ($full < 0) {
		$full = abs($full);
        // Negative payment → full update
        $stmt = $conn->prepare("
            UPDATE payments 
            SET status = ?, 
                paid_amount = ?, 
				l_m_double_balance = ?,
                l_m_com_balance = ?, 
                l_m_balance = ?, 
                updated_at = NOW()
            WHERE id = ? AND farmer_id = ? AND period = ?
        ");
        $stmt->bind_param("iddddiis", $newStatus1, $full1, $lmbalance_total, $full1, $full, $id, $farmer_id, $period);
    } elseif ($full > 0) {
        // Positive payment → only paid_amount
        $stmt = $conn->prepare("
            UPDATE payments 
            SET status = ?, 
                paid_amount = ?, 
				l_m_double_balance = ?,
                updated_at = NOW()
            WHERE id = ? AND farmer_id = ? AND period = ?
        ");
        $stmt->bind_param("iddiis", $newStatus, $full, $lmbalance_total, $id, $farmer_id, $period);
    } else {
        throw new Exception("Payment value cannot be zero");
    }

    $stmt->execute();

    // 🔹 Step 4: Update collections & tea_bag_issues
    $stmt2 = $conn->prepare("
        UPDATE collections 
        SET paid = 1 
        WHERE farmer_id = ? 
          AND DATE_FORMAT(collection_date, '%Y-%m') = ?
    ");
    $stmt2->bind_param("is", $farmer_id, $period);
    $stmt2->execute();

    $stmt3 = $conn->prepare("
        UPDATE tea_bag_issues 
        SET status = 1 
        WHERE farmer_id = ? 
          AND DATE_FORMAT(issue_date, '%Y-%m') = ?
    ");
    $stmt3->bind_param("is", $farmer_id, $period);
    $stmt3->execute();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => ($full < 0)
            ? "Negative payment Rs. {$full1} processed. Status rules applied: (3→1, 0→3)."
            : "Positive payment Rs. {$full1} processed. Status rules applied: (0→1). LMB deducted Rs. {$deductedTotal}, leftover Rs. {$leftover}."
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
