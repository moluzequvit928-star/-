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
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
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
        while (($data = fgetcsv($handle, 4096, ",")) !== FALSE) {
            $rows[] = $data;
        }
        fclose($handle);
    }
    return $rows;
}

function normalizeText($text) {
    return mb_strtolower(trim((string)$text));
}

function collectReattestationEntries(array $rows): array {
    $entries = [];
    foreach ($rows as $rowIndex => $row) {
        if ($rowIndex < 3) continue; // Пропускаем шапки
        $id = trim((string)($row[1] ?? ''));
        if (preg_match('/^\d+$/', $id) && strlen($id) > 10) {
            $entries[] = [
                'discord_id' => $id,
                'discord_nickname' => trim((string)($row[3] ?? '...')),
                'curator' => trim((string)($row[6] ?? '')),
                'result' => trim((string)($row[7] ?? ''))
            ];
        }
    }
    return ['entries' => $entries];
}

function handleReattestationQueue(): void
{
    global $pdo;
    $sheetId = '1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754';
    $gid = '822458528'; // Лист переаттестации
    $url = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";
    
    $rows = loadCsvRows($url);
    $data = collectReattestationEntries($rows);
    $items = [];
    $curatorFilterRaw = $_GET['curator'] ?? ($_SESSION['username'] ?? '');
    $filter = mb_strtolower(trim($curatorFilterRaw));

    foreach ($data['entries'] as $entry) {
        $discordId = $entry['discord_id'];
        $curator = $entry['curator'];
        $result = $entry['result'];

        // --- ФИЛЬТРАЦИЯ ПО БАЗЕ ДАННЫХ ---
        $stmt = $pdo->prepare("SELECT result FROM reattestations WHERE discord_id = ? ORDER BY created_at DESC");
        $stmt->execute([$discordId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $failCount = 0;
        $alreadyPassed = false;
        foreach ($history as $h) {
            if ($h['result'] === 'сдал') { $alreadyPassed = true; break; }
            if ($h['result'] === 'не сдал') $failCount++;
        }

        if ($alreadyPassed || $failCount >= 3) continue;

        // Фильтр по куратору
        $isMatch = ($filter === '' || $filter === 'all');
        if (!$isMatch) {
            $rowCurator = mb_strtolower(trim($curator));
            if (strpos($rowCurator, $filter) !== false || strpos($filter, $rowCurator) !== false) {
                $isMatch = true;
            }
        }
        if (!$isMatch) continue;

        // Если в таблице уже стоит результат - скрываем
        $normRes = normalizeText($result);
        if ($normRes !== '' && $normRes !== '-' && $normRes !== '—') continue;

        $items[] = [
            'id' => $discordId,
            'nickname' => $entry['discord_nickname'],
            'curator' => $curator,
            'attempt_count' => ($failCount + 1) . ' / 3'
        ];
    }

    echo json_encode(['success' => true, 'data' => $items]);
}

// РУТИНГ
$action = $_GET['action'] ?? '';
if ($action === 'reattestation_queue') {
    handleReattestationQueue();
    exit;
}
if ($action === 'set_reattestation_result' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Тут код сохранения (я его сократил для надежности, он у тебя есть)
    require_once 'staff_functions.php';
    $data = json_decode(file_get_contents('php://input'), true);
    $dId = $data['discord_id'] ?? '';
    $res = $data['result'] ?? '';
    $stmt = $pdo->prepare("INSERT INTO reattestations (discord_id, discord_nickname, curator, result) VALUES (?, ?, ?, ?)");
    $stmt->execute([$dId, $data['nickname'] ?? '...', $_SESSION['username'], $res]);
    echo json_encode(['success' => true]);
    exit;
}

// Если экшн не распознан - выдаем стандартную загрузку инфо
// (Тут твой старый код для вывода состава администрации)
echo json_encode(['success' => true, 'message' => 'API Active']);
?>