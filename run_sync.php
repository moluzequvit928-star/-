<?php
session_start();
header('Content-Type: application/json');

// Увеличиваем время выполнения до 10 минут
set_time_limit(600);

// Проверка прав (админ, гл. куратор, куратор)
$allowed_roles = ['admin', 'chief', 'curator'];
if (!isset($_SESSION['user_logged_in']) || !in_array($_SESSION['role'], $allowed_roles)) {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещен']);
    exit;
}

// Команда для запуска скрипта (авто-определение ОС)
if (PHP_OS_FAMILY === 'Windows') {
    $command = 'cmd /c "node check_sync.js"';
} else {
    $command = 'node check_sync.js 2>&1'; // 2>&1 перенаправляет ошибки в основной поток для отладки
}

$output = [];
$return_var = 0;

exec($command, $output, $return_var);

// Логируем в системный журнал Railway для отладки
error_log("Sync command: " . $command);
error_log("Return var: " . $return_var);
error_log("Output: " . implode("\n", $output));

    if ($return_var === 0) {
        // Парсим вывод консоли
        $raw_output = implode("\n", $output);
        
        $results = [
            'success' => true,
            'raw' => $raw_output,
            'sheet_count' => 0,
            'discord_count' => 0,
            'extra' => [],
            'missing' => [],
            'duplicates' => []
        ];

        // Извлекаем цифры
        if (preg_match('/В таблице:.*?(\d+)/u', $raw_output, $matches)) $results['sheet_count'] = (int)$matches[1];
        if (preg_match('/В Discord:.*?(\d+)/u', $raw_output, $matches)) $results['discord_count'] = (int)$matches[1];

        // Извлекаем списки (ищем блоки после заголовков)
        // Лишние (Discord)
        if (strpos($raw_output, '🔴') !== false) {
            $parts = explode('🔴', $raw_output);
            if (isset($parts[1])) {
                $extra_block = explode("\n\n", $parts[1])[0];
                preg_match_all('/ > (.*)/', $extra_block, $matches);
                $results['extra'] = $matches[1] ?? [];
            }
        }

        // Отсутствуют (Таблица)
        if (strpos($raw_output, '🟡') !== false) {
            $parts = explode('🟡', $raw_output);
            if (isset($parts[1])) {
                $missing_block = explode("\n\n", $parts[1])[0];
                preg_match_all('/ > (.*)/', $missing_block, $matches);
                $results['missing'] = $matches[1] ?? [];
            }
        }

        // Дубликаты (Таблица)
        if (strpos($raw_output, '🟠') !== false) {
            $parts = explode('🟠', $raw_output);
            if (isset($parts[1])) {
                $duplicates_block = explode("\n\n", $parts[1])[0];
                preg_match_all('/ > (.*)/', $duplicates_block, $matches);
                $results['duplicates'] = $matches[1] ?? [];
            }
        }

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
                $is_first_run = (count($last_ids) === 0);
                
                $stmt = $pdo->prepare("INSERT INTO sync_stats (added_count, removed_count, sheet_total, discord_total) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $is_first_run ? 0 : $added_count, 
                    $is_first_run ? 0 : $removed_count,
                    $results['sheet_count'],
                    $results['discord_count']
                ]);

                // Обновляем список ID
                $pdo->exec("DELETE FROM supports_current");
                $ins = $pdo->prepare("INSERT INTO supports_current (discord_id) VALUES (?)");
                foreach ($current_ids as $cid) {
                    $ins->execute([$cid]);
                }
            }
        } catch (Exception $e) {
            file_put_contents('debug_sync_error.txt', $e->getMessage(), FILE_APPEND);
        }

        echo json_encode($results);
        exit;
    } else {
        $debug_info = implode("\n", $output);
        echo json_encode(['success' => false, 'error' => 'Ошибка при запуске скрипта сверки', 'debug' => $debug_info]);
        exit;
    }
