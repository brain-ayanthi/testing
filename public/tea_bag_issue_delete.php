<?php
require_once __DIR__ . '/../init.php';
header('Content-Type: application/json');
require_login();

try {
    global $pdo;
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => false, 'message' => 'Invalid ID']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM tea_bag_issues WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['status' => true, 'message' => 'Record deleted successfully.']);
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?>
