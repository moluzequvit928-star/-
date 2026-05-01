<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', 0);

$appConfig = @include __DIR__ . '/app_config.php';
if (!is_array($appConfig))
    $appConfig = [];

$apiToken = $appConfig['bot_api_token'] ?? 'futika_bot_secret_2026';
$providedToken = $_GET['token'] ?? $_POST['token'] ?? '';

$isAuthorized = (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true);
if (!$isAuthorized && $providedToken !== $apiToken) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'db.php';

// АВТО-ОБНОВЛЕНИЕ БАЗЫ ДАННЫХ (Статистика)
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS added_supports_count INT DEFAULT 0");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reattestations_count INT DEFAULT 0");
} catch (Exception $e) {
}

function configValue($envName, $configKey, $default = '')
{
    global $appConfig;
    $env = getenv($envName);
    if ($env !== false && trim((string) $env) !== '')
        return trim((string) $env);
    return trim((string) ($appConfig[$configKey] ?? $default));
}

function getGoogleSheetCsvUrl($gid)
{
    $sheetId = configValue('GOOGLE_SHEET_ID', 'google_sheet_id', '1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754');
    return "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";
}

// УЛУЧШЕННАЯ ФУНКЦИЯ ЗАГРУЗКИ С КЭШЕМ И ТАЙМАУТОМ
function loadCsvRows($url)
{
    if (!$url)
        return [];

    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir))
        @mkdir($cacheDir, 0777, true);

    $cacheFile = $cacheDir . '/' . md5($url) . '.csv';
    $cacheTime = 300; // 5 минут (300 секунд)

    // Если кэш есть и он свежий - читаем его
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
        $csvData = file_get_contents($cacheFile);
    } else {
        // Пробуем скачать с таймаутом 3 секунды через cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $csvData = curl_exec($ch);
        curl_close($ch);

        if ($csvData) {
            file_put_contents($cacheFile, $csvData);
        } elseif (file_exists($cacheFile)) {
            // Если Google упал, но есть хоть какой-то кэш - берем его
            $csvData = file_get_contents($cacheFile);
        } else {
            return [];
        }
    }

    $lines = explode("\n", $csvData);
    $rows = [];
    foreach ($lines as $line) {
        $rows[] = str_getcsv($line);
    }
    return $rows;
}

function normalizeText($text)
{
    $t = mb_strtolower(trim((string) $text));
    return str_replace('_', '', $t);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ФУНКЦИЯ ПОЛУЧЕНИЯ СОСТАВА И СТАТИСТИКИ ОДНИМ ЗАХОДОМ
function getAllDashboardData($pdo)
{
    $csvUrl = getGoogleSheetCsvUrl(configValue('MAIN_SHEET_GID', 'main_sheet_gid', '1970062457'));
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
    $stmtUsers = $pdo->query("SELECT username, discord_id FROM users");
    while ($u = $stmtUsers->fetch(PDO::FETCH_ASSOC)) {
        $userMap[str_replace('_', '', mb_strtolower(trim($u['username'])))] = $u['discord_id'];
    }

    foreach ($rows as $i => $row) {
        // 1. Парсим состав
        if (isset($row[20], $row[21])) {
            $role_text = trim((string) $row[20]);
            $nickname = trim((string) $row[21]);
            $d_id = preg_replace('/[^0-9]/', '', (isset($row[22]) ? (string) $row[22] : ''));

            if (empty($d_id)) {
                $d_id = $userMap[str_replace('_', '', mb_strtolower($nickname))] ?? null;
            }

            $curr_shift = isset($row[19]) ? trim((string) $row[19]) : '';
            if ($curr_shift !== '')
                $lastSeenShift = $curr_shift;

            if ($nickname !== '' && $role_text !== '' && $nickname !== 'Никнейм') {
                $role_l = mb_strtolower($role_text);
                if (mb_strpos($role_l, 'состав') !== false && $nickname === $role_text)
                    continue;

                $entry = ['name' => $role_text, 'nick' => $nickname, 'shift' => $lastSeenShift, 'discord_id' => $d_id];
                if (mb_strpos($role_l, 'гл. куратор') !== false)
                    $management['chief'][] = $entry;
                elseif (mb_strpos($role_l, 'админ') !== false)
                    $management['admin'][] = $entry;
                elseif (mb_strpos($role_l, 'мастер') !== false)
                    $management['masters'][] = $entry;
                elseif (mb_strpos($role_l, 'помощник') !== false)
                    $management['helpers'][] = $entry;
                elseif (mb_strpos($role_l, 'куратор') !== false)
                    $management['curators'][] = $entry;
            }
        }

        // 2. Парсим статы (Кол-во и ЗП)
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

// Очередь переаттестации
function getReattestationQueue()
{
    $csvUrl = getGoogleSheetCsvUrl(configValue('REATTESTATION_GID', 'reattestation_gid', '822458528'));
    $rows = loadCsvRows($csvUrl);
    $queue = [];
    foreach ($rows as $index => $row) {
        if ($index < 5)
            continue;
        $nick = trim((string) ($row[3] ?? ''));
        if ($nick === '' || $nick === 'Ник')
            continue;
        $status = mb_strtolower(trim((string) ($row[7] ?? '')));
        // Если в статусе есть "сдал", пропускаем этого человека
        if (mb_strpos($status, 'сдал') !== false) continue;

        if ($status === '' || $status === '-' || $status === '—') {
            $queue[] = [
                'nickname' => $nick,
                'id' => trim((string) ($row[4] ?? '')),
                'date' => trim((string) ($row[5] ?? '')),
                'curator' => trim((string) ($row[6] ?? '')) ?: 'Не назначен',
                'attempt_count' => trim((string) ($row[8] ?? '')) ?: '1'
            ];
        }
    }
    return $queue;
}

// РОУТИНГ
if (empty($action)) {
    $data = getAllDashboardData($pdo);
    echo json_encode([
        'success' => true,
        'management' => $data['management'],
        'stats' => $data['stats']
    ]);
    exit;
}

if ($action === 'reattestation_queue') {
    echo json_encode(['success' => true, 'data' => getReattestationQueue()]);
    exit;
}

if ($action === 'set_reattestation_result') {
    $discordId = $_POST['discord_id'] ?? '';
    $nickname = $_POST['discord_nickname'] ?? '';
    $curator = $_POST['curator'] ?? ($_SESSION['username'] ?? 'system');
    $result = $_POST['result'] ?? '';
    if (!$discordId || !$result) {
        echo json_encode(['success' => false]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO reattestations (discord_id, discord_nickname, curator, result) VALUES (?, ?, ?, ?)");
        $stmt->execute([$discordId, $nickname, $curator, $result]);

        $webhook = $appConfig['app_script_webhook_url'] ?? '';
        if ($webhook) {
            $payload = ['token' => $appConfig['app_script_webhook_token'] ?? '', 'action' => 'update_reattestation', 'discord_id' => $discordId, 'result' => $result, 'curator' => $curator];
            $webhookUrl = $webhook . (strpos($webhook, '?') === false ? '?' : '&') . 'token=' . ($appConfig['app_script_webhook_token'] ?? '') . '&action=' . $action;
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            $response = curl_exec($ch);
            curl_close($ch);

            $resultData = json_decode($response, true);
            if (!$resultData || !isset($resultData['ok']) || $resultData['ok'] !== true) {
                $errorMsg = $resultData['error'] ?? "Неизвестная ошибка Google Script";
                throw new Exception($errorMsg);
            }

            if (isset($_SESSION['discord_id'])) {
                $pdo->prepare("UPDATE users SET reattestations_count = reattestations_count + 1 WHERE discord_id = ?")->execute([$_SESSION['discord_id']]);
            }
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'add_support') {
    try {
        $nick = $_POST['nickname'] ?? '';
        $discordId = $_POST['discord_id'] ?? '';
        $shift = $_POST['shift'] ?? '';
        if (!$nick || !$discordId || $shift === '')
            throw new Exception("Error");

        $webhook = $appConfig['app_script_webhook_url'] ?? '';
        if ($webhook) {
            $payload = ['token' => $appConfig['app_script_webhook_token'] ?? '', 'action' => 'add_support', 'nick' => $nick, 'discord_id' => $discordId, 'shift' => $shift, 'date' => $_POST['date'] ?? date('d.m.Y')];
            $ch = curl_init($webhook);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false) {
                throw new Exception("Ошибка cURL: " . curl_error($ch));
            }

            $resultData = json_decode($response, true);
            if (!$resultData || !isset($resultData['ok']) || $resultData['ok'] !== true) {
                $errorMsg = $resultData['error'] ?? "Ошибка Google Script (HTTP $httpCode). Ответ: " . substr($response, 0, 100);
                throw new Exception($errorMsg);
            }

            if (isset($_SESSION['discord_id']) && !empty($_SESSION['discord_id'])) {
                try {
                    $pdo->prepare("UPDATE users SET added_supports_count = added_supports_count + 1 WHERE discord_id = ?")->execute([$_SESSION['discord_id']]);
                } catch (Exception $e) {
                }
            }
        } else {
            throw new Exception("Webhook URL is not configured in app_config.php");
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_user_stats') {
    $stmt = $pdo->prepare("SELECT added_supports_count, reattestations_count FROM users WHERE discord_id = ?");
    $stmt->execute([$_GET['discord_id'] ?? '']);
    echo json_encode(['success' => true, 'stats' => $stmt->fetch(PDO::FETCH_ASSOC)]);
    exit;
}

echo json_encode(['success' => true]);