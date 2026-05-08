<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', 0);

require_once 'db.php';
require_once 'staff_functions.php';

$appConfig = getAppConfig();
$apiToken = $appConfig['bot_api_token'] ?? 'futika_bot_secret_2026';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

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
    echo json_encode(['success' => true, 'data' => getReattestationQueue($pdo)]);
    exit;
}

if ($action === 'get_shift_slots') {
    echo json_encode(['success' => true, 'data' => getShiftSlots()]);
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
            
            // Добавляем параметры в URL для совместимости с GET/POST обработкой в Apps Script
            $webhookUrl = $webhook . (strpos($webhook, '?') === false ? '?' : '&') . 'token=' . ($appConfig['app_script_webhook_token'] ?? '') . '&action=add_support';
            
            $ch = curl_init($webhookUrl);
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

if ($action === 'get_all_supports') {
    $csvUrl = getGoogleSheetCsvUrl(configValue('MAIN_SHEET_GID', 'main_sheet_gid', '1970062457'));
    $rows = loadCsvRows($csvUrl);
    $supports = [];
    $activeWarnings = [];
    $now = date('Y-m-d H:i:s');
    $stmtW = $pdo->prepare("SELECT support_id, COUNT(*) as count FROM warnings WHERE expires_at > ? OR expires_at IS NULL GROUP BY support_id");
    $stmtW->execute([$now]);
    while($w = $stmtW->fetch(PDO::FETCH_ASSOC)) {
        $activeWarnings[$w['support_id']] = $w['count'];
    }

    foreach ($rows as $index => $row) {
        if ($index < 2) continue;
        $date = trim($row[1] ?? '');
        $nick = trim($row[2] ?? '');
        $discord_id = preg_replace('/[^0-9]/', '', (string)($row[3] ?? ''));
        
        if ($nick !== '' && $nick !== '-' && $nick !== 'Никнейм' && mb_strpos(mb_strtolower($nick), 'смена') === false && !empty($discord_id)) {
            $supports[] = [
                'date' => $date,
                'nick' => $nick,
                'discord_id' => $discord_id,
                'active_warnings' => $activeWarnings[$discord_id] ?? 0
            ];
        }
    }
    echo json_encode(['success' => true, 'data' => $supports]);
    exit;
}

if ($action === 'give_warning') {
    $support_id = $_POST['support_id'] ?? '';
    $support_nick = $_POST['support_nick'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $duration = $_POST['duration'] ?? '1d';
    $admin_id = $_SESSION['discord_id'] ?? 'system';
    $admin_nick = $_SESSION['username'] ?? 'Admin';

    if (!$support_id || !$reason) {
        echo json_encode(['success' => false, 'error' => 'Missing data']);
        exit;
    }

    $expires_at = null;
    if (preg_match('/^(\d+)([dhm])$/i', $duration, $matches)) {
        $val = (int)$matches[1];
        $unit = strtolower($matches[2]);
        $seconds = 0;
        if ($unit === 'd') $seconds = $val * 86400;
        elseif ($unit === 'h') $seconds = $val * 3600;
        elseif ($unit === 'm') $seconds = $val * 60;
        $expires_at = date('Y-m-d H:i:s', time() + $seconds);
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO warnings (support_id, support_nickname, admin_id, admin_nickname, reason, duration, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$support_id, $support_nick, $admin_id, $admin_nick, $reason, $duration, $expires_at]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'upload_media') {
    $discord_id = $_SESSION['discord_id'] ?? '';
    if (!$discord_id) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $target = $_POST['target'] ?? 'banner'; 
    $uploadDir = __DIR__ . ($target === 'avatar' ? '/uploads/avatars/' : '/uploads/banners/');
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $finalUrl = '';

    if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION);
        $fileName = $discord_id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['media_file']['tmp_name'], $uploadDir . $fileName)) {
            $finalUrl = ($target === 'avatar' ? 'uploads/avatars/' : 'uploads/banners/') . $fileName;
        }
    } 
    elseif (isset($_POST['media_base64']) && !empty($_POST['media_base64'])) {
        $data = $_POST['media_base64'];
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $data = substr($data, strpos($data, ',') + 1);
            $type = strtolower($type[1]);
            $data = base64_decode($data);
            $fileName = $discord_id . '_' . time() . '.' . $type;
            if (file_put_contents($uploadDir . $fileName, $data)) {
                $finalUrl = ($target === 'avatar' ? 'uploads/avatars/' : 'uploads/banners/') . $fileName;
            }
        }
    }

    if ($finalUrl) {
        echo json_encode(['success' => true, 'url' => $finalUrl]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Upload failed']);
    }
    exit;
}

if ($action === 'update_profile') {
    $about_me = $_POST['about_me'] ?? '';
    $banner_url = $_POST['banner_url'] ?? '';
    $discord_id = $_SESSION['discord_id'] ?? '';

    if (!$discord_id) {
        echo json_encode(['success' => false, 'error' => 'Сессия истекла']);
        exit;
    }

    try {
        $username = $_SESSION['username'] ?? 'User';
        $role = $_SESSION['role'] ?? 'master';
        $pdo->exec("SET NAMES utf8mb4");

        $check = $pdo->prepare("SELECT id FROM users WHERE discord_id = ?");
        $check->execute([$discord_id]);
        $exists = $check->fetch();

        if ($exists) {
            $stmt = $pdo->prepare("UPDATE users SET about_me = ?, banner_url = ? WHERE discord_id = ?");
            $stmt->execute([$about_me, $banner_url, $discord_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (discord_id, username, role, password, about_me, banner_url) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$discord_id, $username, $role, 'default123', $about_me, $banner_url]);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_warnings') {
    $support_id = $_GET['support_id'] ?? null;
    try {
        if ($support_id) {
            $stmt = $pdo->prepare("SELECT * FROM warnings WHERE support_id = ? ORDER BY created_at DESC");
            $stmt->execute([$support_id]);
        } else {
            $stmt = $pdo->query("SELECT * FROM warnings ORDER BY created_at DESC LIMIT 100");
        }
        $warnings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $now = date('Y-m-d H:i:s');
        foreach ($warnings as &$w) {
            $w['is_active'] = ($w['expires_at'] === null || $w['expires_at'] > $now);
        }
        echo json_encode(['success' => true, 'data' => $warnings]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'remove_warning') {
    if (!in_array($_SESSION['role'] ?? 'master', ['admin', 'chief', 'curator'])) {
         echo json_encode(['success' => false, 'error' => 'Нет прав']);
         exit;
    }
    $warning_id = $_POST['id'] ?? null;
    try {
        $stmt = $pdo->prepare("UPDATE warnings SET expires_at = NOW() WHERE id = ?");
        $stmt->execute([$warning_id]);
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

if ($action === 'master_details') {
    $username = $_SESSION['username'] ?? '';
    if (!$username) {
        echo json_encode(['success' => false, 'error' => 'No username in session']);
        exit;
    }
    
    $data = getAllDashboardData($pdo);
    $foundMaster = null;
    
    foreach ($data['management'] as $role => $members) {
        foreach ($members as $m) {
            if (mb_strtolower($m['nick']) === mb_strtolower($username)) {
                $foundMaster = $m;
                break 2;
            }
        }
    }
    
    if ($foundMaster) {
        $curator = 'Не назначен';
        foreach ($data['management']['curators'] as $c) {
            if ($c['shift'] === $foundMaster['shift']) {
                $curator = $c['nick'];
                break;
            }
        }
        
        echo json_encode([
            'success' => true,
            'curator' => $curator,
            'shift' => $foundMaster['shift']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Master not found in sheet']);
    }
    exit;
}

echo json_encode(['success' => true]);
