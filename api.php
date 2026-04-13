<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
require_once 'db.php';
$appConfig = @include __DIR__ . '/app_config.php';
if (!is_array($appConfig)) { $appConfig = []; }

function configValue(string $envName, string $configKey, string $default = ''): string {
    global $appConfig;
    $env = getenv($envName);
    if ($env !== false && trim((string) $env) !== '') return trim((string) $env);
    $cfg = $appConfig[$configKey] ?? '';
    if (trim((string) $cfg) !== '') return trim((string) $cfg);
    return $default;
}

function getGoogleSheetCsvUrl(string $gid): string {
    $sheetId = configValue('GOOGLE_SHEET_ID', 'google_sheet_id', '1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754');
    return "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";
}

function loadCsvRows(string $csvUrl): array {
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
    $cacheFile = $cacheDir . '/sheet_cache_' . md5($csvUrl) . '.csv';
    $cacheTime = 300; 
    $ignoreCache = (isset($_GET['ignore_cache']) && $_GET['ignore_cache'] == '1');
    if (!$ignoreCache && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
        $handle = fopen($cacheFile, 'r');
    } else {
        $csvData = @file_get_contents($csvUrl);
        if ($csvData === false) return [];
        file_put_contents($cacheFile, $csvData);
        $handle = fopen($cacheFile, 'r');
    }
    $rows = [];
    if ($handle) {
        while (($data = fgetcsv($handle, 4096, ",")) !== FALSE) { $rows[] = $data; }
        fclose($handle);
    }
    return $rows;
}

function normalizeText($text) { return mb_strtolower(trim((string)$text)); }

// ЛОГИКА ОЧЕРЕДИ
if (isset($_GET['action']) && $_GET['action'] === 'reattestation_queue') {
    global $pdo;
    $url = getGoogleSheetCsvUrl('0'); // Главный лист или лист очереди
    $rows = loadCsvRows($url);
    $items = [];
    $curatorFilterRaw = $_GET['curator'] ?? ($_SESSION['username'] ?? '');
    $filter = mb_strtolower(trim($curatorFilterRaw));

    foreach ($rows as $idx => $row) {
        if ($idx < 3) continue;
        $id = trim((string)($row[1] ?? ''));
        if (!preg_match('/^\d+$/', $id) || strlen($id) < 10) continue;

        $nick = trim((string)($row[3] ?? '...'));
        $curator = trim((string)($row[6] ?? ''));
        $result = trim((string)($row[7] ?? ''));

        // Фильтр по базе (попытки)
        $stmt = $pdo->prepare("SELECT result FROM reattestations WHERE discord_id = ? ORDER BY created_at DESC");
        $stmt->execute([$id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $failCount = 0; $alreadyPassed = false;
        foreach ($history as $h) {
            if ($h['result'] === 'сдал') { $alreadyPassed = true; break; }
            if ($h['result'] === 'не сдал') $failCount++;
        }
        if ($alreadyPassed || $failCount >= 3) continue;

        // Фильтр по куратору
        $isMatch = ($filter === '' || $filter === 'all');
        if (!$isMatch) {
            $rowC = mb_strtolower(trim($curator));
            if (strpos($rowC, $filter) !== false || strpos($filter, $rowC) !== false) $isMatch = true;
        }
        if (!$isMatch) continue;

        // Фильтр по результату в таблице
        $normRes = normalizeText($result);
        if ($normRes !== '' && $normRes !== '-' && $normRes !== '—') continue;

        $items[] = [
            'id' => $id,
            'nickname' => $nick,
            'curator' => $curator,
            'attempt_count' => ($failCount + 1) . ' / 3'
        ];
    }
    echo json_encode(['success' => true, 'data' => $items]);
    exit;
}

// ЛОГИКА СОХРАНЕНИЯ
if (isset($_GET['action']) && $_GET['action'] === 'set_reattestation_result') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("INSERT INTO reattestations (discord_id, discord_nickname, curator, result) VALUES (?, ?, ?, ?)");
    $stmt->execute([$data['discord_id'], $data['nickname'], $_SESSION['username'], $data['result']]);
    echo json_encode(['success' => true]);
    exit;
}

// ЛОГИКА ГЛАВНОЙ СТРАНИЦЫ (ВЫШКА)
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

        if ($nickname !== '' && $nickname !== '-' && $nickname !== '—' && $role_text !== '') {
            $role_l = mb_strtolower($role_text);
            if (mb_strpos($role_l, 'состав') !== false && $nickname === $role_text) continue;
            if ($nickname === 'Никнейм' || $nickname === 'Nickname') continue;
            
            $entry = ['name' => $role_text, 'nick' => $nickname, 'shift' => $lastSeenShift];
            if (mb_strpos($role_l, 'гл. куратор') !== false) $management['chief'][] = $entry;
            elseif (mb_strpos($role_l, 'админ') !== false) $management['admin'][] = $entry;
            elseif (mb_strpos($role_l, 'мастер') !== false) $management['masters'][] = $entry;
            elseif (mb_strpos($role_l, 'куратор') !== false) $management['curators'][] = $entry;
        }
    }
}
echo json_encode(['success' => true, 'management' => $management]);
?>