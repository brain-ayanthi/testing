<?php
require_once __DIR__ . '/../init.php';
require_login();

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? 0;
$code = $_POST['code'] ?? '';
$name = $_POST['name'] ?? '';
$amount = $_POST['amount'] ?? 0;

if ($action === 'agree') {
    // Insert or update saving
    $stmt = $pdo->prepare("SELECT id FROM saving WHERE payment_id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() == 0) {
        $pdo->prepare("INSERT INTO saving (payment_id, farmer_code, farmer_name, amount, status, saving_date)
                       VALUES (?, ?, ?, ?, 'agree', NOW())")
            ->execute([$id, $code, $name, $amount]);
    } else {
        $pdo->prepare("UPDATE saving SET amount=?, status='agree', saving_date=NOW() WHERE payment_id=?")
            ->execute([$amount, $id]);
    }
    // Update payments
    $pdo->prepare("UPDATE payments SET saved_amount=? WHERE id=?")->execute([$amount, $id]);
    echo 'OK';
}
elseif ($action === 'not_agree') {
    $pdo->prepare("DELETE FROM saving WHERE payment_id=?")->execute([$id]);
    $pdo->prepare("UPDATE payments SET saved_amount=0 WHERE id=?")->execute([$id]);
    echo 'REMOVED';
}
else {
    echo 'INVALID';
}
?>
