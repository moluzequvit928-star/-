<?php
session_start();
require_once 'app_config.php';
require_once 'api.php'; // Чтобы использовать загрузку CSV

$csvUrl = getGoogleSheetCsvUrl('1970062457');
$rows = loadCsvRows($csvUrl);

echo "<pre>";
echo "Текущий пользователь в сессии: " . ($_SESSION['username'] ?? 'НЕТУ') . "\n";
echo "Discord ID в сессии: " . ($_SESSION['discord_id'] ?? 'НЕТУ') . "\n\n";

if (count($rows) === 0) {
    echo "ОШИБКА: Таблица не загрузилась!";
} else {
    echo "ПЕРВЫЕ 30 СТРОК ТАБЛИЦЫ (ИНДЕКСЫ КОЛОНОК):\n";
    echo "------------------------------------------\n";
    foreach ($rows as $idx => $row) {
        if ($idx > 30) break;
        echo "СТРОКА " . ($idx + 1) . ": ";
        foreach ($row as $colIdx => $val) {
            echo "[$colIdx] => '$val' | ";
        }
        echo "\n\n";
    }
}
echo "</pre>";
