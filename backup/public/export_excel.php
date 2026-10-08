<?php
require_once __DIR__ . '/../init.php';
require_login();

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Leaf_Distribution_Report.xls");

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';

// reuse same query logic
include 'leaf_distribution_records_query.php'; // optional if you extract logic
echo "<table border='1'><tr><th>Date</th>";

foreach ($areas as $a) echo "<th>{$a['name']}</th>";
echo "<th>Total Weight (kg)</th>";
foreach ($factories as $f) echo "<th>{$f['factory_name']}</th>";
echo "<th>Allocated (kg)</th><th>Extra (kg)</th></tr>";

foreach ($records as $r) {
    echo "<tr><td>{$r['date']}</td>";
    foreach ($areas as $a)
        echo "<td>".number_format($r['areas'][$a['name']] ?? 0,2)."</td>";
    echo "<td>".number_format($r['total_weight'],2)."</td>";
    foreach ($factories as $f) {
        $ff = array_values(array_filter($r['factories'], fn($x)=>$x['factory_name']==$f['factory_name']));
        echo "<td>".number_format($ff[0]['alloc'] ?? 0,2)."</td>";
    }
    echo "<td>".number_format($r['total_alloc'],2)."</td><td>".number_format($r['total_extra'],2)."</td></tr>";
}
echo "</table>";
?>
