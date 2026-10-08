<?php
require_once __DIR__ . '/../init.php';
require_login();
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';

// reuse same logic (like in main page)
ob_start();
include 'leaf_distribution_records_table.php'; // HTML-only version of table
$html = ob_get_clean();

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("Leaf_Distribution_Report.pdf");
?>
