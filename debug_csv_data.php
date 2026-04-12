<?php
session_start();
if (!isset($_SESSION['user_logged_in'])) { die('Unauthorized'); }

$sheetId = '1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754';
$gid = '822458528';
$url = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";

$csvData = @file_get_contents($url);
if ($csvData === false) { die('Failed to fetch CSV from Google'); }

echo "<h2>Raw CSV Data Dump (Top 50 rows)</h2>";
echo "<table border='1' style='border-collapse:collapse; font-family:monospace; font-size:12px;'>";

$lines = preg_split("/\r\n|\n|\r/", trim($csvData));
foreach ($lines as $i => $line) {
    if ($i > 50) break;
    $row = str_getcsv($line);
    echo "<tr>";
    echo "<td style='background:#eee; padding:5px;'><b>Row $i</b></td>";
    foreach ($row as $j => $cell) {
        $color = (preg_match('/^\d{15,25}$/', trim($cell))) ? 'background:#e0ffe0;' : '';
        echo "<td style='padding:5px; $color'>Col $j: " . htmlspecialchars($cell) . "</td>";
    }
    echo "</tr>";
}
echo "</table>";
?>
