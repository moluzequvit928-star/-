<?php
// staff_functions.php - Общие функции для работы с составом

function getAppConfig() {
    $appConfig = @include __DIR__ . '/app_config.php';
    return is_array($appConfig) ? $appConfig : [];
}

function getStaffCsvUrl($gid = '1970062457') {
    $config = getAppConfig();
    $sheetId = $config['google_sheet_id'] ?? '1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754';
    return "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";
}

function fetchStaffRows($gid = '1970062457') {
    $url = getStaffCsvUrl($gid);
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
    $cacheFile = $cacheDir . '/sheet_cache_' . md5($url) . '.csv';
    
    // Кеш на 30 секунд для оперативного обновления
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

/**
 * Общая нормализация ников (удаление подчеркиваний и пробелов)
 */
function normalizeStaffNick($text) {
    $t = mb_strtolower(trim((string)$text));
    // Удаляем вообще всё, кроме букв и цифр для максимально точного сравнения
    return preg_replace('/[\W_]/u', '', $t);
}

/**
 * Возвращает список никнеймов мастеров, которые закреплены за куратором
 */
function getMasterNicksForCurator($curatorNick) {
    if (!$curatorNick) return [];
    $rows = fetchStaffRows();
    if (empty($rows)) return [];

    $curatorNickNorm = normalizeStaffNick($curatorNick);
    $curatorShifts = [];
    
    // 1. Ищем, какую смены ведет этот куратор (по всей таблице, колонка V=21 - Ник, T=19 - Смена)
    foreach ($rows as $row) {
        if (isset($row[21], $row[19])) {
            $nickInTableNorm = normalizeStaffNick($row[21]);
            if ($nickInTableNorm !== '' && (strpos($nickInTableNorm, $curatorNickNorm) !== false || strpos($curatorNickNorm, $nickInTableNorm) !== false)) {
                $shift = trim($row[19]);
                if ($shift !== '') $curatorShifts[] = $shift;
            }
        }
    }

    if (empty($curatorShifts)) return [];

    // 2. Ищем всех мастеров в этих сменах (колонки T=19, V=21)
    $masterNicks = [];
    $lastSeenShift = '';
    foreach ($rows as $row) {
        // Если в текущей строке указана новая смена - запоминаем её
        $shiftInRow = isset($row[19]) ? trim($row[19]) : '';
        if ($shiftInRow !== '') {
            $lastSeenShift = $shiftInRow;
        }

        if (isset($row[21])) {
            $role = mb_strtolower(trim($row[20] ?? ''));
            // Проверяем роль (мастер или саппорт)
            if (strpos($role, 'мастер') !== false || strpos($role, 'саппорт') !== false) {
                // Если текущая (или унаследованная) смена совпадает со сменой куратора
                if (in_array($lastSeenShift, $curatorShifts, true)) {
                    $masterNick = trim($row[21]);
                    if ($masterNick !== '') $masterNicks[] = $masterNick;
                }
            }
        }
    }

    return array_unique($masterNicks);
}
?>
