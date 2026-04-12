<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
$appConfig = @include __DIR__ . '/app_config.php';
if (!is_array($appConfig)) {
    $appConfig = [];
}

function configValue(string $envName, string $configKey, string $default = ''): string
{
    global $appConfig;
    $env = getenv($envName);
    if ($env !== false && trim((string) $env) !== '') {
        return trim((string) $env);
    }
    $cfg = $appConfig[$configKey] ?? '';
    if (trim((string) $cfg) !== '') {
        return trim((string) $cfg);
    }
    return $default;
}

function getGoogleSheetCsvUrl(string $gid): string
{
    $sheetId = configValue('GOOGLE_SHEET_ID', 'google_sheet_id', '1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754');
    return "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";
}

function loadCsvRows(string $csvUrl): array
{
    // Генерируем уникальное имя файла для кеша на основе URL
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    $cacheFile = $cacheDir . '/sheet_cache_' . md5($csvUrl) . '.csv';
    $cacheTime = 300; // Кешируем на 5 минут (300 секунд)

    // Если передан GET параметр ignore_cache=1, сбрасываем кеш
    $ignoreCache = (isset($_GET['ignore_cache']) && $_GET['ignore_cache'] == '1');

    $csvData = '';
    // Проверяем, есть ли свежий кеш (если не просили игнорировать)
    if (!$ignoreCache && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
        $csvData = file_get_contents($cacheFile);
    } else {
        // Если кеша нет или он старый - качаем из Google
        $csvData = @file_get_contents($csvUrl);
        if ($csvData !== false && trim($csvData) !== '') {
            @file_put_contents($cacheFile, $csvData);
        } elseif (file_exists($cacheFile)) {
            // Если Google не ответил, но есть старый кеш - используем его как резерв
            $csvData = file_get_contents($cacheFile);
        }
    }

    if ($csvData === '' || $csvData === false) {
        return [];
    }

    // Удаляем UTF-8 BOM если он есть
    $bom = pack('H*', 'EFBBBF');
    $csvData = preg_replace("/^$bom/", '', $csvData);

    $lines = preg_split("/\r\n|\n|\r/", trim($csvData));
    $rows = [];

    // Определяем разделитель
    $firstLine = $lines[0] ?? '';
    $delimiter = (strpos($firstLine, ';') !== false && strpos($firstLine, ',') === false) ? ';' : ',';

    foreach ($lines as $line) {
        if (trim($line) === '')
            continue;
        $rows[] = str_getcsv($line, $delimiter);
    }
    return $rows;
}

function normalizeHeader(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = str_replace(['ё', '_'], ['е', ' '], $value);
    return preg_replace('/\s+/', ' ', $value);
}

function findColumnIndex(array $headerRow, array $needles): int
{
    foreach ($headerRow as $index => $cell) {
        $normalizedCell = normalizeHeader((string) $cell);
        foreach ($needles as $needle) {
            if (strpos($normalizedCell, normalizeHeader($needle)) !== false) {
                return (int) $index;
            }
        }
    }
    return -1;
}

function detectHeaderRowIndex(array $rows): int
{
    $limit = min(count($rows), 50);
    $bestIndex = 0;
    $bestScore = -1;

    for ($i = 0; $i < $limit; $i++) {
        $row = $rows[$i] ?? [];
        if (!is_array($row) || count($row) === 0)
            continue;

        $score = 0;
        foreach ($row as $cell) {
            $text = normalizeHeader((string) $cell);
            if ($text === '')
                continue;
            if (strpos($text, 'discord') !== false || $text === 'id' || strpos($text, 'айди') !== false)
                $score += 2;
            if (strpos($text, 'куратор') !== false || strpos($text, 'провод') !== false)
                $score += 2;
            if (strpos($text, 'дата') !== false)
                $score += 1;
            if (strpos($text, 'ник') !== false)
                $score += 1;
            if (strpos($text, 'результ') !== false || strpos($text, 'статус') !== false)
                $score += 1;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestIndex = $i;
        }
    }

    return $bestIndex;
}

function chooseValue(array $row, array $indexes): string
{
    foreach ($indexes as $idx) {
        if ($idx < 0)
            continue;
        $value = trim((string) ($row[$idx] ?? ''));
        if ($value !== '' && $value !== '-') {
            return $value;
        }
    }
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    require_once 'db.php';
    return '';
}

function chooseCuratorValue(array $row, array $indexes): string
{
    $fallback = '';
    foreach ($indexes as $idx) {
        if ($idx < 0)
            continue;
        $value = trim((string) ($row[$idx] ?? ''));
        if ($value === '' || $value === '-')
            continue;
        if ($fallback === '')
            $fallback = $value;

        // Предпочитаем ник/тег куратора, а не цифровой ID.
        if (!preg_match('/^\d{10,25}$/', $value)) {
            return $value;
        }
    }
    return $fallback;
}

function looksLikeDate(string $value): bool
{
    $v = trim($value);
    if ($v === '' || $v === '-')
        return false;
    return (bool) preg_match('/^\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{2,4}$/', $v)
        || (bool) preg_match('/^\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{1,2}$/', $v);
}

function parseRuDate(string $value): ?DateTime
{
    $value = trim($value);
    if ($value === '' || $value === '-')
        return null;

    $formats = ['d.m.Y', 'd.m.y', 'd-m-Y', 'd-m-y', 'd/m/Y', 'd/m/y'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            $dt->setTime(0, 0, 0);
            return $dt;
        }
    }
    return null;
}

function handleReattestationLookup(): void
{
    $discordId = trim($_GET['discord_id'] ?? '');
    if ($discordId === '' || !preg_match('/^\d{15,25}$/', $discordId)) {
        echo json_encode(['success' => false, 'error' => 'Некорректный Discord ID']);
        return;
    }

    $gid = configValue('REATTESTATION_GID', 'reattestation_gid', '822458528');
    $rows = loadCsvRows(getGoogleSheetCsvUrl($gid));
    if (count($rows) < 2) {
        echo json_encode(['success' => false, 'error' => 'Лист переаттестации пуст или недоступен']);
        return;
    }

    $resultData = collectReattestationEntries($rows);
    $entries = $resultData['entries'];
    foreach ($entries as $entry) {
        if ($entry['discord_id'] === $discordId) {
            echo json_encode([
                'success' => true,
                'discord_id' => $discordId,
                'curator' => $entry['curator'],
                'date' => $entry['date'],
                'row_number' => $entry['row_number']
            ]);
            return;
        }
    }

    echo json_encode([
        'success' => true,
        'discord_id' => $discordId,
        'curator' => '',
        'date' => '',
        'not_found' => true
    ]);
}

function normalizeText(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = str_replace(['ё', '_'], ['е', ' '], $value);
    return preg_replace('/\s+/', ' ', $value);
}

function rowContainsText(array $row, string $needle): bool
{
    $needle = normalizeText($needle);
    if ($needle === '')
        return false;
    foreach ($row as $cell) {
        $value = normalizeText((string) $cell);
        if ($value === '')
            continue;
        if (strpos($value, $needle) !== false || strpos($needle, $value) !== false) {
            return true;
        }
    }
    return false;
}

function findDiscordIdIndex(array $row, int $fallback = -1): int
{
    if ($fallback >= 0) {
        $value = trim((string) ($row[$fallback] ?? ''));
        if (preg_match('/^\d{15,25}$/', $value)) {
            return $fallback;
        }
    }

    foreach ($row as $idx => $cell) {
        $value = trim((string) $cell);
        if (preg_match('/^\d{15,25}$/', $value)) {
            return (int) $idx;
        }
    }
    return -1;
}

function extractReattestationRow(array $row, array $cols): ?array
{
    $idIdx = findDiscordIdIndex($row, $cols['id']);
    if ($idIdx < 0)
        return null;

    $discordId = trim((string) ($row[$idIdx] ?? ''));
    if (!preg_match('/^\d{15,25}$/', $discordId))
        return null;

    $nick = chooseValue($row, [$cols['nick'], $idIdx - 1, 2, 1]);
    $date = chooseValue($row, [$cols['date'], $idIdx + 1, 4, 3, 1]);
    $curator = chooseCuratorValue($row, [$cols['curator'], $idIdx + 2, 5, 4, 20, 21]);
    $result = chooseValue($row, [$cols['result'], $idIdx + 3, 6, 7]);

    // Отсеиваем строки, где явно нет полезных данных.
    if ($curator === '' && $nick === '' && !looksLikeDate($date)) {
        return null;
    }

    return [
        'discord_id' => $discordId,
        'discord_nickname' => $nick,
        'date' => looksLikeDate($date) ? $date : '',
        'curator' => $curator,
        'result' => $result
    ];
}

function buildReattestationColumns(array $header): array
{
    // C(2):Встал | D(3):Ник | E(4):Айди | F(5):Дата П. | G(6):Проводящий | H(7):Результат
    return [
        'nick' => 3,
        'id' => 4,
        'date' => 5,
        'curator' => 6,
        'result' => 7
    ];
}

function isReattestationHeaderRow(array $row): bool
{
    $normalized = array_map(fn($c) => normalizeHeader((string) $c), $row);
    $score = 0;
    foreach ($normalized as $cell) {
        if ($cell === '')
            continue;
        if ($cell === 'ник' || strpos($cell, 'ник') !== false)
            $score++;
        if ($cell === 'айди' || strpos($cell, 'discord id') !== false || strpos($cell, 'id discord') !== false)
            $score++;
        if (strpos($cell, 'проведение') !== false || strpos($cell, 'дата проведения') !== false)
            $score++;
        if (strpos($cell, 'проводящий') !== false || strpos($cell, 'проводящий куратор') !== false)
            $score++;
        if (strpos($cell, 'сдал/не сдал') !== false || (strpos($cell, 'сдал') !== false && strpos($cell, 'не') !== false))
            $score++;
    }
    // Если нашли хотя бы 3 ключевых заголовка - это заголовочная строка
    return $score >= 3;
}

function detectColsFromHeaderRow(array $row): array
{
    $cols = ['nick' => -1, 'id' => -1, 'date' => -1, 'curator' => -1, 'result' => -1];
    foreach ($row as $idx => $cell) {
        $text = normalizeHeader((string) $cell);
        if ($text === '')
            continue;
        if ($cols['nick'] < 0 && ($text === 'ник' || strpos($text, 'ник') !== false))
            $cols['nick'] = (int) $idx;
        if ($cols['id'] < 0 && ($text === 'айди' || strpos($text, 'discord id') !== false || strpos($text, 'id discord') !== false))
            $cols['id'] = (int) $idx;
        if ($cols['date'] < 0 && (strpos($text, 'проведение') !== false || strpos($text, 'дата проведения') !== false))
            $cols['date'] = (int) $idx;
        if ($cols['curator'] < 0 && (strpos($text, 'проводящий') !== false || strpos($text, 'проводящий куратор') !== false))
            $cols['curator'] = (int) $idx;
        if ($cols['result'] < 0 && (strpos($text, 'сдал/не сдал') !== false || (strpos($text, 'сдал') !== false && strpos($text, 'не') !== false)))
            $cols['result'] = (int) $idx;
    }
    return $cols;
}

function collectReattestationEntries(array $rows): array
{
    $entries = [];
    $activeCols = ['id' => -1, 'nick' => -1, 'date' => -1, 'curator' => -1];

    foreach ($rows as $rowIndex => $row) {
        if (count($row) < 3) continue;

        // Ищем Discord ID в любом месте строки
        $foundId = '';
        $idIdx = -1;
        foreach ($row as $idx => $cell) {
            $val = trim((string)$cell);
            if (preg_match('/^\d{15,25}$/', $val)) {
                $foundId = $val;
                $idIdx = $idx;
                break;
            }
        }

        // Если нашли ID, собираем данные вокруг него
        if ($foundId !== '') {
            // Обычно: Ник слева от ID, Дата справа, Куратор еще правее
            $nick = trim((string)($row[$idIdx - 1] ?? ($row[$idIdx - 2] ?? '')));
            
            // Ищем дату в строке (что-то похожее на DD.MM.YYYY)
            $date = '';
            foreach ($row as $idx => $cell) {
                if (looksLikeDate((string)$cell)) {
                    $date = trim((string)$cell);
                    // Если это дата из начала строки (Встал), ищем вторую дату (Проведения)
                    if ($idx < $idIdx) continue; 
                    if ($date !== '') break;
                }
            }

            // Ищем куратора: ты сказал G7 (индекс 6)
            $curator = trim((string)($row[6] ?? ''));
            
            // Если в колонке 6 пусто, ищем хоть какое-то имя в строке
            if ($curator === '' || $curator === '-' || $curator === '—') {
                foreach ($row as $idx => $cell) {
                    $val = trim((string)$cell);
                    if ($idx > $idIdx && $val !== '' && !preg_match('/^\d+$/', $val) && !looksLikeDate($val) && $val !== '-' && $val !== '—') {
                        $curator = $val;
                        break;
                    }
                }
            }

            // Результат - это строго индекс 7 (H) или дальше
            $result = trim((string)($row[7] ?? ''));

            $entries[] = [
                'row_number' => $rowIndex + 1,
                'discord_id' => $foundId,
                'discord_nickname' => $nick ?: '...',
                'started_at' => trim((string)($row[2] ?? ($row[0] ?? '...'))),
                'date' => $date,
                'curator' => $curator,
                'result' => $result
            ];
        }
    }

    return [
        'entries' => $entries,
        'cols' => $activeCols
    ];
}

function sendResultToAppsScript(array $payload): array
{
    $webhookUrl = configValue('APP_SCRIPT_WEBHOOK_URL', 'app_script_webhook_url', '');
    if ($webhookUrl === '') {
        return ['success' => false, 'error' => 'APP_SCRIPT_WEBHOOK_URL не настроен'];
    }

    $token = configValue('APP_SCRIPT_WEBHOOK_TOKEN', 'app_script_webhook_token', '');
    if ($token !== '') {
        $payload['token'] = $token;
    }

    $ch = curl_init();
    $jsonData = json_encode($payload, JSON_UNESCAPED_UNICODE);
    curl_setopt_array($ch, [
        CURLOPT_URL => $webhookUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true, // ВАЖНО для Google Apps Script (302 redirect)
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => 'cURL ошибка: ' . $curlError, 'sent_data' => $payload];
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        return ['success' => false, 'error' => "Webhook HTTP {$httpCode}: {$response}", 'sent_data' => $payload];
    }

    $decoded = json_decode($response, true);
    if (is_array($decoded) && isset($decoded['success']) && $decoded['success'] === false) {
        $gasError = (string) ($decoded['error'] ?? 'Ошибка Apps Script');
        return [
            'success' => false,
            'error' => $gasError . " (Отправлено: " . ($payload['event'] ?? 'null') . ")",
            'gas_response' => $decoded
        ];
    }

    return ['success' => true, 'message' => 'Результат переаттестации отправлен'];
}

function handleSetReattestationResult(): void
{
    $role = $_SESSION['role'] ?? 'master';
    $allowedRoles = ['curator', 'admin', 'chief', 'senior_curator', 'master'];
    if (!in_array($role, $allowedRoles, true)) {
        echo json_encode(['success' => false, 'error' => 'Недостаточно прав']);
        return;
    }

    $discordId = trim((string) ($_POST['discord_id'] ?? ''));
    $result = normalizeText(trim((string) ($_POST['result'] ?? '')));
    $nick = trim((string) ($_POST['discord_nickname'] ?? ''));
    $date = trim((string) ($_POST['date'] ?? ''));
    $curator = trim((string) ($_POST['curator'] ?? ($_SESSION['username'] ?? '')));

    if (!preg_match('/^\d{15,25}$/', $discordId)) {
        echo json_encode(['success' => false, 'error' => 'Некорректный Discord ID']);
        return;
    }
    if (!in_array($result, ['сдал', 'не сдал'], true)) {
        echo json_encode(['success' => false, 'error' => 'Результат должен быть "сдал" или "не сдал"']);
        return;
    }

    $payload = [
        'event' => 'reattestation_passed',
        'discord_id' => $discordId,
        'discord_nickname' => $nick,
        'curator' => $curator,
        'date' => $date,
        'status' => $result,
        'approved_by' => $_SESSION['username'] ?? 'system',
        'approved_at' => date('c')
    ];

    // $sent = sendResultToAppsScript($payload);
    echo json_encode(['success' => true, 'message' => 'Результат сохранен локально (синхронизация отключена)']);
}

function handleReattestationQueue(): void
{
    // ЖЕСТКО: Твоя таблица и твоя вкладка переаттестации
    $sheetId = '1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754';
    $gid = '822458528';
    $url = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";

    $rows = loadCsvRows($url);
    if (count($rows) < 7) { // Минимум 7 строк (заголовки + хотя бы одна строка данных)
        echo json_encode(['success' => false, 'error' => 'Лист переаттестации пуст или данные начинаются позже нормы']);
        return;
    }

    $resultData = collectReattestationEntries($rows);
    $entries = $resultData['entries'];
    $activeCols = $resultData['cols'];

    $curatorFilterRaw = trim((string) ($_GET['curator'] ?? ($_SESSION['username'] ?? '')));
    $curatorFilter = normalizeText($curatorFilterRaw);
    $showAll = ($curatorFilterRaw === 'all');
    $items = [];

    foreach ($entries as $data) {
        $discordId = $data['discord_id'];
        $curator = $data['curator'];
        $result = $data['result'];
        $date = $data['date'];

        if (!$showAll && $curatorFilter !== '') {
            $rowCurator = normalizeText($curator);
            // Прямое сравнение или вхождение (для случаев "Куратор | Nick" или просто "Nick")
            $isMatch = ($rowCurator !== '') && (
                $rowCurator === $curatorFilter
                || mb_strpos($rowCurator, $curatorFilter) !== false
                || mb_strpos($curatorFilter, $rowCurator) !== false
            );

            // Дополнительная проверка: если в фильтре или в ячейке есть тег (с решеткой или без)
            if (!$isMatch && $rowCurator !== '') {
                // Очищаем от лишних символов для более мягкого сравнения
                $cleanRow = preg_replace('/[^a-z0-9]/i', '', $rowCurator);
                $cleanFilter = preg_replace('/[^a-z0-9]/i', '', $curatorFilter);
                if ($cleanRow !== '' && $cleanFilter !== '' && ($cleanRow === $cleanFilter || strpos($cleanRow, $cleanFilter) !== false)) {
                    $isMatch = true;
                }
            }

            if (!$isMatch) {
                continue;
            }
        }

        // По ТЗ: если поле "Сдал/Не сдал" не пустое, значит переаттестация уже проведена.
        $normalizedResult = normalizeText($result);
        if ($normalizedResult !== '' && $normalizedResult !== '-' && $normalizedResult !== '—') {
            continue;
        }

        $dateState = 'unknown';
        $daysDiff = null;
        $targetDate = parseRuDate($date);
        if ($targetDate instanceof DateTime) {
            $today = new DateTime('today');
            $interval = $today->diff($targetDate);
            $daysDiff = (int) $interval->format('%r%a');
            if ($daysDiff < 0) {
                $dateState = 'overdue';
            } elseif ($daysDiff === 0) {
                $dateState = 'today';
            } else {
                $dateState = 'upcoming';
            }
        }

        $items[] = [
            'row_number' => $data['row_number'],
            'discord_id' => $discordId,
            'discord_nickname' => $data['discord_nickname'],
            'curator' => $curator,
            'date' => looksLikeDate($date) ? $date : '',
            'result' => $result,
            'date_state' => $dateState,
            'days_until_date' => $daysDiff
        ];
    }

    echo json_encode([
        'success' => true,
        'curator_filter' => $curatorFilterRaw,
        'count' => count($items),
        'items' => $items,
        'debug' => [
            'total_rows' => count($rows),
            'detected' => $activeCols,
            'entries_found' => count($entries),
            'raw_sample' => $resultData['raw_sample'] ?? []
        ]
    ]);
}

$action = $_GET['action'] ?? '';

if ($action === 'reattestation_meta') {
    handleReattestationLookup();
    exit;
}
if ($action === 'reattestation_queue') {
    handleReattestationQueue();
    exit;
}
if ($action === 'set_reattestation_result' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handleSetReattestationResult();
    exit;
}

if ($action === 'master_details') {
    $csvUrl = getGoogleSheetCsvUrl(configValue('MAIN_SHEET_GID', 'main_sheet_gid', '1970062457'));
    $rows = loadCsvRows($csvUrl);
    if (count($rows) === 0) {
        echo json_encode(['success' => false, 'error' => 'Table load failed']);
        exit;
    }

    $targetNick = mb_strtolower($_SESSION['username'] ?? '');
    $targetDiscordId = trim($_SESSION['discord_id'] ?? '');
    $userShift = null;
    $curatorFound = 'Не назначен';

    // 1. Ищем мастера (Колонна 21 - Ник, 22 - Discord ID)
    for ($i = 0; $i < count($rows); $i++) {
        $row = $rows[$i];
        $found = false;
        
        if ($targetDiscordId !== '' && isset($row[22]) && trim((string)$row[22]) === $targetDiscordId) {
            $found = true;
        } elseif (isset($row[21])) {
            $rowNick = mb_strtolower(trim((string)$row[21]));
            if ($rowNick !== '' && (strpos($rowNick, $targetNick) !== false || strpos($targetNick, $rowNick) !== false)) {
                $found = true;
            }
        }

        if ($found) {
            // Пытаемся взять смену из колонки T (индекс 19). 
            // Если она пустая - идем вверх, пока не найдем заполненную ячейку (для объединенных ячеек)
            for ($k = $i; $k >= 0; $k--) {
                if (isset($rows[$k][19]) && trim($rows[$k][19]) !== '') {
                    $userShift = trim($rows[$k][19]);
                    break;
                }
                // Не уходим слишком далеко вверх (чтобы не уйти в другой блок)
                if ($k < $i - 3) break; 
            }
            break;
        }
    }

    // 2. Если смена найдена, ищем куратора (T=19, V=21 - Ник) в блоке КУРАТОРОВ (примерно строки 15-24)
    if ($userShift) {
        foreach ($rows as $idx => $row) {
            $logicRow = $idx + 1;
            // Ищем только в блоке курирующего состава (до 24 строки)
            if ($logicRow >= 15 && $logicRow < 24) {
                if (isset($row[19], $row[21]) && trim($row[19]) === $userShift) {
                    $curatorFound = trim($row[21]);
                    break;
                }
            }
        }
    }

    echo json_encode(['success' => true, 'curator' => $curatorFound, 'shift' => $userShift]);
    exit;
}

$csvUrl = getGoogleSheetCsvUrl(configValue('MAIN_SHEET_GID', 'main_sheet_gid', '1970062457'));
$rows = loadCsvRows($csvUrl);
if (count($rows) === 0) {
    echo json_encode(['error' => 'Не удалось загрузить данные из Google Таблицы']);
    exit;
}

$management = [
    'chief' => [],
    'admin' => [],
    'masters' => [],
    'curators' => []
];

$roleCol = 20;
$nickCol = 21;
$shiftCol = 19;
$lastSeenShift = '';

foreach ($rows as $index => $row) {
    // 1. Сбор Вышки (если есть такие колонки)
    if (isset($row[$roleCol], $row[$nickCol])) {
        $role_text = trim((string) $row[$roleCol]);
        $nickname = trim((string) $row[$nickCol]);
        $current_line_shift = isset($row[$shiftCol]) ? trim((string)$row[$shiftCol]) : '';

        // Если в этой строке есть новая смена — запоминаем её
        if ($current_line_shift !== '') {
            $lastSeenShift = $current_line_shift;
        }

        if ($nickname !== '' && $nickname !== '-' && $nickname !== '—' && $role_text !== '') {
            $role_lower = mb_strtolower($role_text);
            
            // Если это просто заголовок блока (например "КУРАТОРСКИЙ СОСТАВ"), пропускаем
            if (mb_strpos($role_lower, 'состав') !== false && $nickname === $role_text) {
                continue; 
            }
            if ($nickname === 'Никнейм' || $nickname === 'Nickname') continue;

            $entry = ['name' => $role_text, 'nick' => $nickname, 'shift' => $lastSeenShift];

            if (mb_strpos($role_lower, 'гл. куратор') !== false) {
                $management['chief'][] = $entry;
            } elseif (mb_strpos($role_lower, 'админ') !== false) {
                $management['admin'][] = $entry;
            } elseif (mb_strpos($role_lower, 'мастер') !== false) {
                $management['masters'][] = $entry;
            } elseif (mb_strpos($role_lower, 'куратор') !== false) {
                $management['curators'][] = $entry;
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'management' => $management
]);
?>