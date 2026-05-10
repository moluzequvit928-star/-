<?php
session_start();
header('Content-Type: application/json');

// Увеличиваем время выполнения до 5 минут
set_time_limit(300);

// Проверка прав (только админы и гл. кураторы могут делать сверку)
$allowed_roles = ['admin', 'chief'];
if (!isset($_SESSION['user_logged_in']) || !in_array($_SESSION['role'], $allowed_roles)) {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещен']);
    exit;
}

// Команда для запуска скрипта (используем cmd /c для обхода ограничений)
$command = 'cmd /c "node check_sync.js"';
$output = [];
$return_var = 0;

exec($command, $output, $return_var);

if ($return_var === 0) {
    // Парсим вывод консоли
    $raw_output = implode("\n", $output);
    
    $results = [
        'success' => true,
        'raw' => $raw_output,
        'sheet_count' => 0,
        'discord_count' => 0,
        'extra' => [],
        'missing' => []
    ];

    // Извлекаем цифры
    if (preg_match('/В таблице:.*?(\d+)/u', $raw_output, $matches)) $results['sheet_count'] = (int)$matches[1];
    if (preg_match('/В Discord:.*?(\d+)/u', $raw_output, $matches)) $results['discord_count'] = (int)$matches[1];

    // Извлекаем списки (ищем блоки после заголовков)
    $sections = preg_split('/[🔴🟡]/u', $raw_output);
    
    // Лишние (Discord)
    if (strpos($raw_output, '🔴') !== false) {
        $extra_block = explode("\n\n", explode('🔴', $raw_output)[1])[0];
        preg_match_all('/ > (.*)/', $extra_block, $matches);
        $results['extra'] = $matches[1] ?? [];
    }

    // Отсутствуют (Таблица)
    if (strpos($raw_output, '🟡') !== false) {
        $missing_block = explode("\n\n", explode('🟡', $raw_output)[1])[0];
        preg_match_all('/ > (.*)/', $missing_block, $matches);
        $results['missing'] = $matches[1] ?? [];
    }

    echo json_encode($results);

    // --- АВТО-ТРЕКИНГ ИЗМЕНЕНИЙ (СНЯТЫ/ДОБАВЛЕНЫ) ---
    try {
        require_once 'db.php';
        
        // 1. Извлекаем список текущих ID из вывода селфбота
        if (preg_match('/---CURRENT_DISCORD_IDS---\n(.*?)\n---END_CURRENT_DISCORD_IDS---/s', $raw_output, $matches)) {
            $current_ids = array_filter(explode(',', trim($matches[1])));
            
            // 2. Получаем список ID, которые были в прошлый раз
            $stmt = $pdo->query("SELECT discord_id FROM supports_current");
            $last_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // 3. Вычисляем разницу
            $added_ids = array_diff($current_ids, $last_ids);   // Есть сейчас, не было раньше
            $removed_ids = array_diff($last_ids, $current_ids); // Были раньше, нет сейчас
            
            $added_count = count($added_ids);
            $removed_count = count($removed_ids);
            
            // 4. Если есть изменения, записываем их в статистику (по дням)
            // Мы записываем общее кол-во изменений за этот запуск
            // В идеале можно группировать по дате, но для графика лучше каждая точка - запуск или день
            $stmt = $pdo->prepare("INSERT INTO sync_stats (added_count, removed_count, sheet_total, discord_total) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $added_count,
                $removed_count,
                $results['sheet_count'],
                $results['discord_count']
            ]);
            
            // 5. Обновляем таблицу текущего состава
            if ($added_count > 0 || $removed_count > 0) {
                // Очищаем и записываем новый состав (или точечно, но проще обновить всё если их немного)
                $pdo->exec("DELETE FROM supports_current");
                $ins = $pdo->prepare("INSERT INTO supports_current (discord_id) VALUES (?)");
                foreach ($current_ids as $cid) {
                    $ins->execute([$cid]);
                }
            }
        }
    } catch (Exception $e) {
        // Ошибки логируем в файл, чтобы не ломать JSON
        file_put_contents('debug_sync_error.txt', $e->getMessage(), FILE_APPEND);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка при запуске скрипта сверки', 'debug' => $output]);
}
