<?php
// staff_functions.php - Общие функции для работы с составом

function configValue($envName, $configKey, $default = '')
{
    $appConfig = getAppConfig();
    $env = getenv($envName);
    if ($env !== false && trim((string) $env) !== '')
        return trim((string) $env);
    return trim((string) ($appConfig[$configKey] ?? $default));
}

function getGoogleSheetCsvUrl($gid)
{
    $sheetId = configValue('GOOGLE_SHEET_ID', 'google_sheet_id', '1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754');
    $url = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv";
    if ($gid !== null && $gid !== '') {
        $url .= "&gid={$gid}";
    }
    return $url;
}

function loadCsvRows($url, $customCacheTime = 600) // Увеличили кэш до 10 минут по умолчанию
{
    if (!$url)
        return [];

    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir))
        @mkdir($cacheDir, 0777, true);

    $cacheFile = $cacheDir . '/' . md5($url) . '.csv';
    
    // Если файл существует и он свежий - читаем его
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $customCacheTime)) {
        $csvData = @file_get_contents($cacheFile);
    } else {
        // Иначе качаем заново
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // Быстрее таймаут на коннект
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);       // Быстрее таймаут на загрузку
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $csvData = curl_exec($ch);
        curl_close($ch);

        if ($csvData) {
            file_put_contents($cacheFile, $csvData);
        } elseif (file_exists($cacheFile)) {
            // Если не удалось скачать, но есть старый кэш - используем его
            $csvData = file_get_contents($cacheFile);
        } else {
            return [];
        }
    }

    $rows = [];
    $temp = fopen('php://temp', 'r+');
    fwrite($temp, $csvData);
    rewind($temp);
    while (($row = fgetcsv($temp)) !== false) {
        $rows[] = $row;
    }
    fclose($temp);
    return $rows;
}

function normalizeText($text)
{
    $t = mb_strtolower(trim((string) $text));
    return str_replace('_', '', $t);
}

function getAllDashboardData($pdo)
{
    // Состав администрации лежит на отдельном листе (первая вкладка по умолчанию),
    // НЕ на листе смен (main_sheet_gid), который используется для подсчёта баллов.
    $csvUrl = getGoogleSheetCsvUrl(configValue('COMPOSITION_GID', 'composition_gid', ''));
    $rows = loadCsvRows($csvUrl);

    $management = [
        'admin' => [],
        'chief' => [],
        'curators' => [],
        'masters' => [],
        'helpers' => []
    ];
    $supportCount = 0;
    $totalSalary = 0;
    $lastSeenShift = '';

    // Предзагрузка пользователей
    $userMap = [];
    $stmtUsers = $pdo->query("SELECT username, discord_id, banner_url, appointment_date FROM users");
    while ($u = $stmtUsers->fetch(PDO::FETCH_ASSOC)) {
        $key = str_replace('_', '', mb_strtolower(trim($u['username'])));
        
        $daysOnBranch = null;
        if (!empty($u['appointment_date'])) {
            $diff = time() - strtotime($u['appointment_date']);
            $daysOnBranch = max(0, (int)floor($diff / 86400));
        }

        $userMap[$key] = [
            'username' => $u['username'],
            'id' => $u['discord_id'],
            'banner' => $u['banner_url'],
            'appointment_date' => $u['appointment_date'],
            'days_on_branch' => $daysOnBranch
        ];
    }

    foreach ($rows as $i => $row) {
        if (isset($row[20], $row[21])) {
            $role_text = trim((string) $row[20]);
            $nickname = trim((string) $row[21]);
            $d_id = preg_replace('/[^0-9]/', '', (isset($row[22]) ? (string) $row[22] : ''));

            $userData = $userMap[str_replace('_', '', mb_strtolower($nickname))] ?? null;
            if (empty($d_id)) {
                $d_id = $userData['id'] ?? null;
            }
            $banner_url = $userData['banner'] ?? '';
            $appt_date = $userData['appointment_date'] ?? null;
            $days_on_branch = $userData['days_on_branch'] ?? null;
            $db_username = $userData['username'] ?? null;

            $curr_shift = isset($row[19]) ? trim((string) $row[19]) : '';
            if ($curr_shift !== '')
                $lastSeenShift = $curr_shift;

            if ($nickname !== '' && $role_text !== '' && $nickname !== 'Никнейм') {
                $role_l = mb_strtolower($role_text);
                $entry = [
                    'name' => $role_text, 
                    'nick' => $nickname, 
                    'db_username' => $db_username,
                    'shift' => $lastSeenShift, 
                    'discord_id' => $d_id, 
                    'banner' => $banner_url,
                    'appointment_date' => $appt_date,
                    'days_on_branch' => $days_on_branch
                ];
                if (mb_strpos($role_l, 'гл. куратор') !== false || mb_strpos($role_l, 'глк') !== false) $management['chief'][] = $entry;
                elseif (mb_strpos($role_l, 'админ') !== false) $management['admin'][] = $entry;
                elseif (mb_strpos($role_l, 'куратор') !== false) $management['curators'][] = $entry;
                elseif (mb_strpos($role_l, 'мастер') !== false) $management['masters'][] = $entry;
                elseif (mb_strpos($role_l, 'помощник') !== false) $management['helpers'][] = $entry;
            }
        }

        foreach ($row as $j => $cell) {
            $norm = normalizeText($cell);
            if (mb_strpos($norm, 'сапп') !== false && $supportCount === 0) {
                $vB = trim((string) ($rows[$i + 1][$j] ?? ''));
                $vR = trim((string) ($row[$j + 1] ?? ''));
                $supportCount = is_numeric($vB) ? (int) $vB : (is_numeric($vR) ? (int) $vR : 0);
            }
            if ($norm === 'итог' || $norm === 'итог:') {
                $vB = trim((string) ($rows[$i + 1][$j] ?? ''));
                $vR = trim((string) ($row[$j + 1] ?? ''));
                $fV = is_numeric(preg_replace('/[^0-9]/', '', $vB)) ? $vB : $vR;
                $totalSalary = (int) preg_replace('/[^0-9]/', '', $fV);
            }
        }
    }

    return [
        'management' => $management,
        'stats' => [
            'support_count' => $supportCount,
            'total_salary' => number_format($totalSalary, 0, '.', ' ') . ' $'
        ]
    ];
}

function getReattestationQueue($pdo)
{
    $csvUrl = getGoogleSheetCsvUrl(configValue('REATTESTATION_GID', 'reattestation_gid', '822458528'));
    
    // Для очереди используем кэш 30 секунд
    $rows = loadCsvRows($csvUrl, 30); 
    $queue = [];
    
    // Получаем список тех, кто уже прошел проверку сегодня (из локальной БД)
    $doneToday = [];
    try {
        $stmt = $pdo->query("SELECT discord_id FROM reattestations WHERE DATE(created_at) = CURDATE()");
        while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $doneToday[] = $r['discord_id'];
        }
    } catch (Exception $e) {}

    foreach ($rows as $index => $row) {
        if ($index < 5) continue;
            
        $row = array_map(function($v) { return trim((string)$v); }, $row);
        $nick = $row[3] ?? '';
        $id = $row[4] ?? '';
        if ($nick === '' || $nick === 'Ник') continue;
            
        // Если человек уже в базе как сдавший сегодня — пропускаем
        if (in_array($id, $doneToday)) continue;

        $status = mb_strtolower(trim($row[7] ?? ''));
        $attempt = trim($row[8] ?? '');

        // Прошёл переаттестацию — убираем из очереди.
        // ВАЖНО: "не сдал" содержит подстроку "сдал", поэтому проверяем точное значение / "прошел".
        $passed = ($status === 'сдал' || mb_strpos($status, 'прошел') !== false);
        if ($passed) continue;

        // Исчерпал все попытки (3/3) — тоже убираем из очереди.
        if (mb_strpos($attempt, '3/3') !== false) continue;

        // Остаются: ещё не проверенные ('-' / пусто) и провалившие, у кого есть попытки (1/3, 2/3).
        $queue[] = [
            'nickname' => $nick,
            'id' => $id,
            'date' => $row[5] ?? '',
            'curator' => ($row[6] ?? '') ?: 'Не назначен',
            'attempt_count' => ($attempt !== '' && $attempt !== '-' && $attempt !== '—') ? $attempt : '1/3'
        ];
    }
    return $queue;
}

function getShiftSlots()
{
    $csvUrl = getGoogleSheetCsvUrl(configValue('MAIN_SHEET_GID', 'main_sheet_gid', ''));
    $rows = loadCsvRows($csvUrl);

    $shifts = [];
    $currentShift = null;

    $shiftTimes = [
        '0' => 'Свободный график',
        '1' => '00:00 - 02:00',
        '2' => '02:00 - 04:00',
        '3' => '04:00 - 06:00',
        '4' => '06:00 - 08:00',
        '5' => '08:00 - 10:00',
        '6' => '10:00 - 12:00',
        '7' => '12:00 - 14:00',
        '8' => '14:00 - 16:00',
        '9' => '16:00 - 18:00',
        '10' => '18:00 - 20:00',
        '11' => '20:00 - 22:00',
        '12' => '22:00 - 00:00'
    ];

    foreach ($rows as $i => $row) {
        $cell = trim($row[2] ?? '');
        if (preg_match('/^(\d+)\s+смена/i', $cell, $matches)) {
            $currentShift = $matches[1];
            $shifts[$currentShift] = [
                'id' => $currentShift,
                'label' => $cell,
                'time' => $shiftTimes[$currentShift] ?? '',
                'free_slots' => 0
            ];
            continue;
        }

        if ($currentShift !== null) {
            if (trim($row[1] ?? '') === 'Дата' || trim($row[2] ?? '') === 'Никнейм') continue;
            
            if (count($row) > 5) {
                $nick = trim($row[2] ?? '');
                if (trim($row[4] ?? '') === '-' || trim($row[6] ?? '') === '0/2') {
                     if ($nick === '' || $nick === '-' || $nick === '—') {
                         $shifts[$currentShift]['free_slots']++;
                     }
                }
            }
        }
    }
    ksort($shifts);
    return array_values($shifts);
}

function getAppConfig() {
    $file = __DIR__ . '/app_config.php';
    if (!file_exists($file)) {
        return [];
    }
    $appConfig = include $file;
    return is_array($appConfig) ? $appConfig : [];
}

function getStaffCsvUrl($gid = '2053240546') {
    $config = getAppConfig();
    $sheetId = $config['google_sheet_id'] ?? '1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754';
    return "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";
}

function fetchStaffRows($gid = '2053240546') {
    $url = getStaffCsvUrl($gid);
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
    $cacheFile = $cacheDir . '/sheet_cache_' . md5($url) . '.csv';
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 30)) {
        $csvData = file_get_contents($cacheFile);
    } else {
        $csvData = @file_get_contents($url);
        if ($csvData !== false && trim($csvData) !== '') {
            @file_put_contents($cacheFile, $csvData);
        } elseif (file_exists($cacheFile)) {
            $csvData = file_get_contents($cacheFile);
        }
    }

    if (!$csvData) return [];
    $lines = preg_split("/\r\n|\n|\r/", trim($csvData));
    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $rows[] = str_getcsv($line);
    }
    return $rows;
}

function normalizeStaffNick($text) {
    $t = mb_strtolower(trim((string)$text));
    return preg_replace('/[\W_]/u', '', $t);
}

function normalizeShift($shift) {
    $s = trim((string)$shift);
    return preg_replace('/[\s\-\—\−]/u', '', $s);
}

function getMasterNicksForCurator($curatorNick) {
    if (!$curatorNick) return [];
    $rows = fetchStaffRows();
    if (empty($rows)) return [];

    $curatorNickNorm = normalizeStaffNick($curatorNick);
    $curatorDiscordId = $_SESSION['discord_id'] ?? '';
    $curatorShifts = [];
    
    foreach ($rows as $row) {
        if (isset($row[21], $row[19])) {
            $nickInTableNorm = normalizeStaffNick($row[21]);
            $idInTable = isset($row[22]) ? trim($row[22]) : '';
            $isMe = false;
            if ($nickInTableNorm !== '' && (strpos($nickInTableNorm, $curatorNickNorm) !== false || strpos($curatorNickNorm, $nickInTableNorm) !== false)) $isMe = true;
            if (!$isMe && $curatorDiscordId !== '' && $idInTable === $curatorDiscordId) $isMe = true;
            if ($isMe) {
                $shift = normalizeShift($row[19]);
                if ($shift !== '') $curatorShifts[] = $shift;
            }
        }
    }

    if (empty($curatorShifts)) return [];
    $masterNicks = [];
    $lastSeenShift = '';
    foreach ($rows as $row) {
        $shiftInRow = isset($row[19]) ? normalizeShift($row[19]) : '';
        if ($shiftInRow !== '') $lastSeenShift = $shiftInRow;
        if (isset($row[21])) {
            $role = mb_strtolower(trim($row[20] ?? ''));
            if (strpos($role, 'мастер') !== false || strpos($role, 'саппорт') !== false) {
                if (in_array($lastSeenShift, $curatorShifts, true)) {
                    $masterNick = trim($row[21]);
                    if ($masterNick !== '') $masterNicks[] = $masterNick;
                }
            }
        }
    }
    return array_unique($masterNicks);
}

function getAvatarUrl($discordId, $username = '') {
    return "avatar.php?id=" . urlencode($discordId) . "&seed=" . urlencode($username ?: 'default');
}

function getRoleDisplayName($role) {
    $roles = [
        'admin' => 'Администратор',
        'chief' => 'Гл. Куратор',
        'curator' => 'Куратор',
        'master' => 'Мастер',
        'helper' => 'Помощник'
    ];
    return $roles[$role] ?? $role;
}

