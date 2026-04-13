<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Не даем лишним ошибкам ломать JSON
error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'db.php';
$appConfig = @include __DIR__ . '/app_config.php';
if (!is_array($appConfig)) $appConfig = [];

function configValue($envName, $configKey, $default = '') {
    global $appConfig;
    $env = getenv($envName);
    if ($env !== false && trim((string)$env) !== '') return trim((string)$env);
    return trim((string)($appConfig[$configKey] ?? $default));
}

function getGoogleSheetCsvUrl($gid) {
    $sheetId = configValue('GOOGLE_SHEET_ID', 'google_sheet_id', '1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754');
    return "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";
}

function loadCsvRows($url) {
    if (!$url) return [];
    $csvData = @file_get_contents($url);
    if ($csvData === false) return [];
    $lines = explode("\n", $csvData);
    $rows = [];
    foreach ($lines as $line) {
        $rows[] = str_getcsv($line);
    }
    return $rows;
}

function normalizeText($text) { return mb_strtolower(trim((string)$text)); }

$action = $_GET['action'] ?? '';

// 1. ЛОГИКА ОЧЕРЕДИ
if ($action === 'reattestation_queue') {
    global $pdo;
    $url = getGoogleSheetCsvUrl('822458528'); 
    $rows = loadCsvRows($url);
    $items = [];
    $curatorFilterRaw = $_GET['curator'] ?? ($_SESSION['username'] ?? '');
    $filter = normalizeText($curatorFilterRaw);

    foreach ($rows as $idx => $row) {
        if ($idx < 6) continue; // Пропускаем заголовок "Переаттестации"
        
        $id = trim((string)($row[4] ?? '')); // Колонка E (индекс 4)
        if (!preg_match('/^\d+$/', $id) || strlen($id) < 10) continue;

        $nick = trim((string)($row[3] ?? '...')); // Колонка D (3)
        $curator = trim((string)($row[6] ?? '')); // Колонка G (6)
        $result = trim((string)($row[7] ?? ''));  // Колонка H (7)

        // Проверка по базе
        $stmt = $pdo->prepare("SELECT result FROM reattestations WHERE discord_id = ? ORDER BY created_at DESC");
        $stmt->execute([$id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $failCount = 0; $alreadyPassed = false;
        foreach ($history as $h) {
            if ($h['result'] === 'сдал') { $alreadyPassed = true; break; }
            if ($h['result'] === 'не сдал') $failCount++;
        }
        if ($alreadyPassed || $failCount >= 3) continue;

        // Фильтр куратора
        $isMatch = ($filter === '' || $filter === 'all');
        if (!$isMatch) {
            $rowC = normalizeText($curator);
            if (strpos($rowC, $filter) !== false || strpos($filter, $rowC) !== false) $isMatch = true;
        }
        if (!$isMatch) continue;

        // Если в таблице уже есть результат - скрываем (кроме пустых и тире)
        $normRes = normalizeText($result);
        if ($normRes !== '' && $normRes !== '-' && $normRes !== '—' && $normRes !== 'не сдал') continue;

        $items[] = [
            'id' => $id,
            'nickname' => $nick,
            'curator' => $curator,
            'date' => trim((string)($row[5] ?? '')), // Колонка F (дата)
            'attempt_count' => ($failCount + 1) . ' / 3'
        ];
    }
    echo json_encode(['success' => true, 'data' => $items]);
    exit;
}

// 2. СОХРАНЕНИЕ
if ($action === 'set_reattestation_result') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("INSERT INTO reattestations (discord_id, discord_nickname, curator, result) VALUES (?, ?, ?, ?)");
    $stmt->execute([$data['discord_id'], $data['nickname'], $_SESSION['username'], $data['result']]);
    echo json_encode(['success' => true]);
    exit;
}

// 3. ГЛАВНАЯ СТРАНИЦА (ВЫШКА)
$csvUrl = getGoogleSheetCsvUrl(configValue('MAIN_SHEET_GID', 'main_sheet_gid', '1970062457'));
$rows = loadCsvRows($csvUrl);
$management = ['chief' => [], 'admin' => [], 'masters' => [], 'curators' => []];
$lastSeenShift = '';

foreach ($rows as $row) {
    if (isset($row[20], $row[21])) {
        $role_text = trim((string)$row[20]);
        $nickname = trim((string)$row[21]);
        $curr_shift = isset($row[19]) ? trim((string)$row[19]) : '';
        if ($curr_shift !== '') $lastSeenShift = $curr_shift;

        if ($nickname !== '' && $role_text !== '' && $nickname !== 'Никнейм') {
            $role_l = mb_strtolower($role_text);
            if (mb_strpos($role_l, 'состав') !== false && $nickname === $role_text) continue;
            
            $entry = ['name' => $role_text, 'nick' => $nickname, 'shift' => $lastSeenShift];
            if (mb_strpos($role_l, 'гл. куратор') !== false) $management['chief'][] = $entry;
            elseif (mb_strpos($role_l, 'админ') !== false) $management['admin'][] = $entry;
            elseif (mb_strpos($role_l, 'мастер') !== false) $management['masters'][] = $entry;
            elseif (mb_strpos($role_l, 'куратор') !== false) $management['curators'][] = $entry;
        }
    }
}
// 4. ДЕТАЛИ МАСТЕРА (КУРАТОР)
if ($action === 'master_details') {
    $csvUrl = getGoogleSheetCsvUrl(configValue('MAIN_SHEET_GID', 'main_sheet_gid', '1970062457'));
    $rows = loadCsvRows($csvUrl);
    $myNick = normalizeText($_SESSION['username']);
    $myShift = '';
    $curator = 'Не назначен';
    $lastSeen = '';

    // 1. Ищем смену мастера (идем по порядку и запоминаем последнюю смену)
    foreach ($rows as $row) {
        $row_shift = trim((string)($row[19] ?? ''));
        if ($row_shift !== '') $lastSeen = $row_shift;

        if (isset($row[21]) && normalizeText($row[21]) === $myNick) {
            $myShift = $lastSeen;
            break;
        }
    }

    // 2. Если смена найдена, ищем куратора этой смены (в блоке кураторов 15-25 строчки)
    if ($myShift !== '') {
        for ($i = 0; $i < count($rows); $i++) {
            $logicRow = $i + 1;
            if ($logicRow >= 15 && $logicRow <= 26) {
                $row = $rows[$i];
                $shift = trim((string)($row[19] ?? ''));
                if ($shift === $myShift && isset($row[21])) {
                    $curator = trim($row[21]);
                    break;
                }
            }
        }
    }

    echo json_encode(['success' => true, 'curator' => $curator, 'shift' => $myShift]);
    exit;
}

echo json_encode(['success' => true, 'management' => $management]);
?>