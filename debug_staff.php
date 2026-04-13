<?php
require_once 'staff_functions.php';
session_start();

echo "<h2>Отладка состава</h2>";
echo "Текущий пользователь в сессии: <b>" . ($_SESSION['username'] ?? 'Гость') . "</b><br>";

$rows = fetchStaffRows();
if (empty($rows)) {
    echo "<p style='color:red;'>Ошибка: Не удалось загрузить CSV данные из Google Таблицы!</p>";
} else {
    echo "<p style='color:green;'>Данные успешно загружены. Всего строк: " . count($rows) . "</p>";
    
    echo "<table border='1' style='border-collapse: collapse; font-size: 11px;'>";
    foreach (array_slice($rows, 0, 40) as $idx => $row) {
        echo "<tr>";
        echo "<td style='background:#eee;'>#$idx</td>";
        foreach ($row as $colIdx => $val) {
            $style = '';
            // Подсветка ника или смены для наглядности
            if (strpos(mb_strtolower($val), 'nevermore') !== false) $style = 'background: #fbbf24;';
            if (strpos($val, '7-9') !== false) $style = 'background: #bae6fd;';
            
            echo "<td style='padding:4px; $style'>col $colIdx: <b>$val</b></td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}
?>
