<?php
/**
 * Скрипт для автоматического фонового отслеживания состава саппортов.
 * Можно запускать через планировщик задач Windows или cron.
 */

require_once __DIR__ . '/db.php';

// Увеличиваем время выполнения
set_time_limit(300);

echo "[" . date('Y-m-d H:i:s') . "] Запуск авто-синхронизации...\n";

// Команда для запуска селфбота
$command = 'cmd /c "node ' . __DIR__ . '/check_sync.js"';
$output = [];
$return_var = 0;

exec($command, $output, $return_var);

if ($return_var === 0) {
    $raw_output = implode("\n", $output);
    
    // Парсим текущие ID
    if (preg_match('/---CURRENT_DISCORD_IDS---\n(.*?)\n---END_CURRENT_DISCORD_IDS---/s', $raw_output, $matches)) {
        $current_ids = array_filter(explode(',', trim($matches[1])));
        
        // Получаем старый состав
        $stmt = $pdo->query("SELECT discord_id FROM supports_current");
        $last_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Считаем разницу
        $added_ids = array_diff($current_ids, $last_ids);
        $removed_ids = array_diff($last_ids, $current_ids);
        
        $added_count = count($added_ids);
        $removed_count = count($removed_ids);
        
        // Считаем общее кол-во для статистики
        $sheet_total = 0;
        $discord_total = 0;
        if (preg_match('/В таблице:.*?(\d+)/u', $raw_output, $m)) $sheet_total = (int)$m[1];
        if (preg_match('/В Discord:.*?(\d+)/u', $raw_output, $m)) $discord_total = (int)$m[1];

        // Записываем в статистику (только если есть изменения или раз в день для точки на графике)
        // Но лучше записывать каждый запуск для точности графика, если он не слишком частый
        $stmt = $pdo->prepare("INSERT INTO sync_stats (added_count, removed_count, sheet_total, discord_total) VALUES (?, ?, ?, ?)");
        $stmt->execute([$added_count, $removed_count, $sheet_total, $discord_total]);
        
        // Обновляем текущий состав
        $pdo->exec("DELETE FROM supports_current");
        $ins = $pdo->prepare("INSERT INTO supports_current (discord_id) VALUES (?)");
        foreach ($current_ids as $cid) {
            $ins->execute([$cid]);
        }
        
        echo "✅ Успешно. Добавлено: $added_count, Снято: $removed_count, Всего: $discord_total\n";
    } else {
        echo "❌ Ошибка: Не удалось распарсить ID из вывода селфбота.\n";
    }
} else {
    echo "❌ Ошибка при выполнении ноды.\n";
    print_r($output);
}
