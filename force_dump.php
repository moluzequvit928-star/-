<?php
session_start();
require_once 'app_config.php';

$sheetId = '1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754';
$gid = '822458528';
$url = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";

$csvData = file_get_contents($url);
$lines = preg_split("/\r\n|\n|\r/", trim($csvData));

echo "<h1>ПОЛНЫЙ ДАМП ТАБЛИЦЫ (БЕЗ ФИЛЬТРОВ)</h1>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse; font-family:sans-serif;'>";
echo "<tr style='background:#ccc;'><td>Строка</td><td>Содержимое (JSON)</td></tr>";

foreach ($lines as $i => $line) {
    if (trim($line) === '') continue;
    $row = str_getcsv($line);
    $bgColor = '';
    if (stripos($line, 'white_powerbank') !== false) {
        $bgColor = 'background:#ffffd0; border: 2px solid red;';
    }
    
    echo "<tr style='$bgColor'>";
    echo "<td>" . ($i + 1) . "</td>";
    echo "<td><pre>" . htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . "</pre></td>";
    echo "</tr>";
}
echo "</table>";
?>
