<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', 0);

require_once 'db.php';
require_once 'staff_functions.php';
require_once 'pet_functions.php';

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

// === ПИТОМЕЦ: получить данные текущего пользователя ===
if ($action === 'pet_get') {
    $discordId = $_SESSION['discord_id'] ?? '';
    $role = $_SESSION['role'] ?? 'master';
    if (!$discordId) { echo json_encode(['success' => false, 'error' => 'not_logged_in']); exit; }

    $stmt = $pdo->prepare("SELECT * FROM pets WHERE discord_id = ?");
    $stmt->execute([$discordId]);
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pet) {
        // список занятых типов — чтобы UI пометил их как недоступные
        $taken = [];
        try {
            $t = $pdo->query("SELECT pet_type FROM pets");
            while ($r = $t->fetch(PDO::FETCH_ASSOC)) $taken[] = $r['pet_type'];
        } catch (Exception $e) {}
        echo json_encode(['success' => true, 'has_pet' => false, 'types' => petTypes(), 'taken' => $taken]);
        exit;
    }

    $info = petLevelInfo($pet['xp']);
    $canFeed = (($pet['last_fed'] ?? null) !== date('Y-m-d'));
    echo json_encode([
        'success'  => true,
        'has_pet'  => true,
        'pet'      => [
            'type'  => $pet['pet_type'],
            'name'  => $pet['pet_name'],
            'emoji' => petEmoji($pet['pet_type']),
            'xp'    => (int) $pet['xp'],
        ],
        'level'    => $info,
        'can_feed' => $canFeed,
        'quests'   => petGetUserQuests($pdo, $discordId, $role),
        'is_admin' => ($role === 'admin'),
        'types'    => petTypes(),
    ]);
    exit;
}

// === ПИТОМЕЦ: покормить (раз в день, бонусный XP) ===
if ($action === 'pet_feed') {
    $discordId = $_SESSION['discord_id'] ?? '';
    if (!$discordId) { echo json_encode(['success' => false, 'error' => 'not_logged_in']); exit; }
    $FEED_XP = 30;
    try {
        $stmt = $pdo->prepare("SELECT last_fed FROM pets WHERE discord_id = ?");
        $stmt->execute([$discordId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['success' => false, 'error' => 'no_pet']); exit; }
        if (($row['last_fed'] ?? null) === date('Y-m-d')) {
            echo json_encode(['success' => false, 'error' => 'already_fed']);
            exit;
        }
        $pdo->prepare("UPDATE pets SET xp = xp + ?, last_fed = CURDATE() WHERE discord_id = ?")
            ->execute([$FEED_XP, $discordId]);
        echo json_encode(['success' => true, 'xp_gained' => $FEED_XP]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === ПИТОМЕЦ: лидерборд ===
if ($action === 'pet_leaderboard') {
    echo json_encode(['success' => true, 'top' => petLeaderboard($pdo, 15)]);
    exit;
}

// === ДОСТИЖЕНИЯ ===
if ($action === 'achievements_get') {
    $discordId = $_SESSION['discord_id'] ?? '';
    $role = $_SESSION['role'] ?? 'master';
    if (!$discordId) { echo json_encode(['success' => false, 'error' => 'not_logged_in']); exit; }
    echo json_encode(['success' => true, 'achievements' => petCheckAchievements($pdo, $discordId, $role)]);
    exit;
}

// === ПИТОМЕЦ: завести / переименовать ===
if ($action === 'pet_create') {
    $discordId = $_SESSION['discord_id'] ?? '';
    $name = $_SESSION['username'] ?? '';
    if (!$discordId) { echo json_encode(['success' => false, 'error' => 'not_logged_in']); exit; }

    $type = $_POST['pet_type'] ?? 'dog';
    $petName = trim($_POST['pet_name'] ?? '');
    if (!array_key_exists($type, petTypes())) $type = 'dog';
    if ($petName === '') $petName = 'Питомец';
    if (mb_strlen($petName) > 50) $petName = mb_substr($petName, 0, 50);

    try {
        // Проверка уникальности: этот тип не должен быть занят другим пользователем
        $chk = $pdo->prepare("SELECT discord_id FROM pets WHERE pet_type = ?");
        $chk->execute([$type]);
        $owner = $chk->fetchColumn();
        if ($owner && $owner !== $discordId) {
            echo json_encode(['success' => false, 'error' => 'Этого питомца уже завёл другой сотрудник — выбери другого.']);
            exit;
        }

        $pdo->prepare(
            "INSERT INTO pets (discord_id, owner_name, pet_type, pet_name) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE pet_type = VALUES(pet_type), pet_name = VALUES(pet_name)"
        )->execute([$discordId, $name, $type, $petName]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === ПИТОМЕЦ: переименовать ===
if ($action === 'pet_rename') {
    $discordId = $_SESSION['discord_id'] ?? '';
    if (!$discordId) { echo json_encode(['success' => false, 'error' => 'not_logged_in']); exit; }
    $name = trim($_POST['pet_name'] ?? '');
    if ($name === '') { echo json_encode(['success' => false, 'error' => 'Имя не может быть пустым']); exit; }
    if (mb_strlen($name) > 50) $name = mb_substr($name, 0, 50);
    try {
        $stmt = $pdo->prepare("UPDATE pets SET pet_name = ? WHERE discord_id = ?");
        $stmt->execute([$name, $discordId]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === ПИТОМЕЦ: удалить ===
if ($action === 'pet_delete') {
    $discordId = $_SESSION['discord_id'] ?? '';
    if (!$discordId) { echo json_encode(['success' => false, 'error' => 'not_logged_in']); exit; }
    try {
        $pdo->prepare("DELETE FROM pets WHERE discord_id = ?")->execute([$discordId]);
        // также чистим прогресс по квестам и достижения этого пользователя
        $pdo->prepare("DELETE FROM pet_quest_progress WHERE discord_id = ?")->execute([$discordId]);
        $pdo->prepare("DELETE FROM pet_achievements WHERE discord_id = ?")->execute([$discordId]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === КВЕСТЫ: список всех (только админ) ===
if ($action === 'quest_list_admin') {
    if (($_SESSION['role'] ?? '') !== 'admin') { echo json_encode(['success' => false, 'error' => 'forbidden']); exit; }
    $rows = $pdo->query("SELECT * FROM pet_quests ORDER BY is_active DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'quests' => $rows]);
    exit;
}

// === КВЕСТЫ: создать (только админ) ===
if ($action === 'quest_create') {
    if (($_SESSION['role'] ?? '') !== 'admin') { echo json_encode(['success' => false, 'error' => 'forbidden']); exit; }
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $reward = max(1, (int) ($_POST['xp_reward'] ?? 50));
    $kind = $_POST['kind'] ?? 'custom';
    $role = $_POST['target_role'] ?? 'all';
    $goal = max(1, (int) ($_POST['goal_count'] ?? 1));
    $allowedKinds = ['custom', 'reattestation', 'add_support'];
    $allowedRoles = ['all', 'master', 'curator', 'chief', 'admin'];
    if (!in_array($kind, $allowedKinds)) $kind = 'custom';
    if (!in_array($role, $allowedRoles)) $role = 'all';
    if ($title === '') { echo json_encode(['success' => false, 'error' => 'Укажите название']); exit; }

    try {
        $pdo->prepare(
            "INSERT INTO pet_quests (title, description, xp_reward, kind, target_role, goal_count, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([$title, $desc, $reward, $kind, $role, $goal, $_SESSION['username'] ?? 'admin']);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === КВЕСТЫ: вкл/выкл или удалить (только админ) ===
if ($action === 'quest_toggle') {
    if (($_SESSION['role'] ?? '') !== 'admin') { echo json_encode(['success' => false, 'error' => 'forbidden']); exit; }
    $id = (int) ($_POST['id'] ?? 0);
    $pdo->prepare("UPDATE pet_quests SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}
if ($action === 'quest_delete') {
    if (($_SESSION['role'] ?? '') !== 'admin') { echo json_encode(['success' => false, 'error' => 'forbidden']); exit; }
    $id = (int) ($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM pet_quests WHERE id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM pet_quest_progress WHERE quest_id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

// === КВЕСТЫ: админ вручную выдаёт награду за custom-квест пользователю ===
if ($action === 'quest_award') {
    if (($_SESSION['role'] ?? '') !== 'admin') { echo json_encode(['success' => false, 'error' => 'forbidden']); exit; }
    $questId = (int) ($_POST['quest_id'] ?? 0);
    $targetId = trim($_POST['discord_id'] ?? '');
    if (!$questId || !$targetId) { echo json_encode(['success' => false, 'error' => 'Нужны quest_id и discord_id']); exit; }

    $q = $pdo->prepare("SELECT * FROM pet_quests WHERE id = ?");
    $q->execute([$questId]);
    $quest = $q->fetch(PDO::FETCH_ASSOC);
    if (!$quest) { echo json_encode(['success' => false, 'error' => 'Квест не найден']); exit; }

    // проверяем, что у пользователя есть питомец
    $chk = $pdo->prepare("SELECT discord_id FROM pets WHERE discord_id = ?");
    $chk->execute([$targetId]);
    if (!$chk->fetch()) { echo json_encode(['success' => false, 'error' => 'У пользователя нет питомца']); exit; }

    try {
        $pdo->prepare(
            "INSERT INTO pet_quest_progress (quest_id, discord_id, progress, completed, rewarded)
             VALUES (?, ?, ?, 1, 1)
             ON DUPLICATE KEY UPDATE completed = 1, rewarded = 1, progress = VALUES(progress)"
        )->execute([$questId, $targetId, (int) $quest['goal_count']]);
        petAwardXp($pdo, $targetId, (int) $quest['xp_reward']);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
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
    $answersJson = $_POST['answers_json'] ?? null;
    if (!$discordId || !$result) {
        echo json_encode(['success' => false]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO reattestations (discord_id, discord_nickname, curator, result, answers_json) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$discordId, $nickname, $curator, $result, $answersJson]);

        // Определяем результат и номер попытки для записи в таблицу.
        // ВАЖНО: "не сдал" содержит подстроку "сдал", поэтому проверяем наличие "не" отдельно.
        $normResult = mb_strtolower(trim($result));
        $isPass = (mb_strpos($normResult, 'не') === false && mb_strpos($normResult, 'сдал') !== false);

        // Номер попытки = количество проваленных переаттестаций этого человека (включая текущую).
        $attemptNum = 1;
        try {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM reattestations WHERE discord_id = ? AND result LIKE '%не%сдал%'");
            $cnt->execute([$discordId]);
            $attemptNum = max(1, (int) $cnt->fetchColumn());
        } catch (Exception $e) {}

        // Значения должны ТОЧНО совпадать с выпадающим списком в колонке H ("сдал" / "Не сдал" / "-"),
        // иначе Google Таблица отклонит запись по правилам проверки данных.
        $statusLabel  = $isPass ? 'сдал' : 'Не сдал';                      // колонка H (Сдал/Не сдал)
        $attemptLabel = $isPass ? 'прошел' : (min($attemptNum, 3) . '/3'); // колонка I (Попытка)

        $webhook = configValue('APP_SCRIPT_WEBHOOK_URL', 'app_script_webhook_url');
        if ($webhook) {
            $webhookToken = configValue('APP_SCRIPT_WEBHOOK_TOKEN', 'app_script_webhook_token');
            $payload = ['token' => $webhookToken, 'action' => 'update_reattestation', 'discord_id' => $discordId, 'result' => $result, 'status' => $statusLabel, 'attempt' => $attemptLabel, 'curator' => $curator];
            $webhookUrl = $webhook . (strpos($webhook, '?') === false ? '?' : '&') . 'token=' . $webhookToken . '&action=' . $action;
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $effUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);

            $resultData = json_decode($response, true);
            if (!$resultData || !isset($resultData['ok']) || $resultData['ok'] !== true) {
                if (isset($resultData['error'])) {
                    // Google ответил валидным JSON с ошибкой (например "ID не найден")
                    throw new Exception($resultData['error']);
                }
                // Ответ не JSON — показываем диагностику прямо в ошибке
                $isLogin = (strpos((string) $effUrl, 'accounts.google.com') !== false) ? ' [РЕДИРЕКТ НА ЛОГИН GOOGLE → доступ деплоя НЕ «Все»]' : '';
                $raw = trim(strip_tags((string) $response));
                if (mb_strlen($raw) > 200) $raw = mb_substr($raw, 0, 200) . '…';
                throw new Exception("Google вернул не JSON (HTTP $httpCode)$isLogin. URL: ..." . substr((string) $webhook, -22) . ". Ответ: " . $raw);
            }

            if (isset($_SESSION['discord_id'])) {
                $pdo->prepare("UPDATE users SET reattestations_count = reattestations_count + 1 WHERE discord_id = ?")->execute([$_SESSION['discord_id']]);
            }
        }

        // 🐾 Питомец: куратор получает XP за проведённую переаттестацию + прогресс квестов
        $myId = $_SESSION['discord_id'] ?? '';
        $myRole = $_SESSION['role'] ?? 'curator';
        if ($myId) {
            petAwardXp($pdo, $myId, 20);
            petAdvanceQuests($pdo, $myId, 'reattestation', $myRole, 1);
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

        $webhook = configValue('APP_SCRIPT_WEBHOOK_URL', 'app_script_webhook_url');
        if ($webhook) {
            $webhookToken = configValue('APP_SCRIPT_WEBHOOK_TOKEN', 'app_script_webhook_token');
            $payload = ['token' => $webhookToken, 'action' => 'add_support', 'nick' => $nick, 'discord_id' => $discordId, 'shift' => $shift, 'date' => $_POST['date'] ?? date('d.m.Y')];
            
            // Добавляем параметры в URL для совместимости с GET/POST обработкой в Apps Script
            $webhookUrl = $webhook . (strpos($webhook, '?') === false ? '?' : '&') . 'token=' . $webhookToken . '&action=add_support';
            
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

                // 🐾 Питомец: мастер получает XP за добавленного саппорта + прогресс квестов
                $myId = $_SESSION['discord_id'];
                $myRole = $_SESSION['role'] ?? 'master';
                petAwardXp($pdo, $myId, 15);
                petAdvanceQuests($pdo, $myId, 'add_support', $myRole, 1);
            }
        } else {
            $keys = is_array($appConfig) ? implode(', ', array_keys($appConfig)) : 'not an array';
            throw new Exception("Webhook URL is not configured in app_config.php. Dir: " . __DIR__ . ". Keys found: [$keys]");
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_all_supports') {
    $csvUrl = getGoogleSheetCsvUrl(configValue('MAIN_SHEET_GID', 'main_sheet_gid', '2053240546'));
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
    if (!in_array($_SESSION['role'] ?? 'master', ['admin', 'chief', 'curator'])) {
        echo json_encode(['success' => false, 'error' => 'Нет прав']);
        exit;
    }
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
    $subDir = $target === 'avatar' ? 'uploads/avatars/' : ($target === 'wallpaper' ? 'uploads/wallpapers/' : 'uploads/banners/');
    $uploadDir = __DIR__ . '/' . $subDir;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $finalUrl = '';

    if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION);
        $fileName = $discord_id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['media_file']['tmp_name'], $uploadDir . $fileName)) {
            $finalUrl = $subDir . $fileName;
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
                $finalUrl = $subDir . $fileName;
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
    $remover_nick = $_SESSION['username'] ?? 'System';
    try {
        $stmt = $pdo->prepare("UPDATE warnings SET expires_at = NOW(), removed_by_nickname = ? WHERE id = ?");
        $stmt->execute([$remover_nick, $warning_id]);
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

// === Список стафа для чекера дабл-стаффа (id + ник из таблицы состава/смен) ===
if ($action === 'get_staff_ids') {
    $token = $_GET['token'] ?? '';
    if ($token !== $apiToken) {
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }
    try {
        $csvUrl = getGoogleSheetCsvUrl(configValue('MAIN_SHEET_GID', 'main_sheet_gid', '2053240546'));
        $rows = loadCsvRows($csvUrl, 120);
        $staff = [];
        foreach ($rows as $row) {
            $nick = trim((string) ($row[2] ?? ''));
            $id = preg_replace('/[^0-9]/', '', (string) ($row[3] ?? ''));
            if ($id === '' || $nick === '' || $nick === 'Никнейм' || mb_stripos($nick, 'смена') !== false) continue;
            $staff[$id] = $nick; // уникальность по id
        }
        // Плюс стафф с аккаунтами на сайте (кураторы/гл.кураторы/админы/мастера)
        try {
            $u = $pdo->query("SELECT discord_id, username FROM users WHERE discord_id IS NOT NULL AND discord_id <> ''");
            while ($row = $u->fetch(PDO::FETCH_ASSOC)) {
                $uid = preg_replace('/[^0-9]/', '', (string) $row['discord_id']);
                if ($uid !== '' && !isset($staff[$uid])) $staff[$uid] = $row['username'];
            }
        } catch (Exception $e) {}
        $out = [];
        foreach ($staff as $id => $nick) $out[] = ['id' => $id, 'username' => $nick];
        echo json_encode(['success' => true, 'staff' => $out]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'bot_profile') {
    $discordId = $_GET['discord_id'] ?? '';
    $token = $_GET['token'] ?? '';

    // Простая проверка токена
    if ($token !== $apiToken) {
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT username, role, banner_url, about_me, added_supports_count, reattestations_count FROM users WHERE discord_id = ?");
        $stmt->execute([$discordId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'username' => $user['username'],
            'role' => $user['role'],
            'banner' => $user['banner_url'],
            'about' => $user['about_me'],
            'stats' => [
                'approved' => $user['added_supports_count'],
                'reattestations' => $user['reattestations_count']
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
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

// === ДАБЛ-СТАФФ: приём результатов от чекера (селф-бот) ===
if ($action === 'update_doublestaff') {
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';
    if ($token !== $apiToken) {
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }
    $results = $data['results'] ?? [];
    try {
        // Полная замена: чекер присылает актуальный полный список
        $pdo->exec("DELETE FROM double_staff");
        $ins = $pdo->prepare("INSERT INTO double_staff (discord_id, username, guild_name, role_name) VALUES (?, ?, ?, ?)");
        $count = 0;
        foreach ($results as $r) {
            $did = (string) ($r['discord_id'] ?? '');
            $uname = $r['username'] ?? null;
            if ($did === '') continue;
            foreach (($r['entries'] ?? []) as $e) {
                $ins->execute([$did, $uname, mb_substr((string)($e['guild'] ?? ''), 0, 150), mb_substr((string)($e['role'] ?? ''), 0, 150)]);
                $count++;
            }
        }
        echo json_encode(['success' => true, 'inserted' => $count]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'log_voice') {
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';
    if ($token !== $apiToken) {
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }

    $discordId = $data['discord_id'] ?? '';
    $channelId = $data['channel_id'] ?? '';
    $startTime = $data['start_time'] ?? '';
    $endTime = $data['end_time'] ?? '';
    $duration = (int)($data['duration'] ?? 0);

    if (!$discordId || !$startTime || !$endTime) {
        echo json_encode(['success' => false, 'error' => 'Missing data']);
        exit;
    }

    try {
        $start = date('Y-m-d H:i:s', strtotime($startTime));
        $end = date('Y-m-d H:i:s', strtotime($endTime));

        // Защита от дублей (проверяем по времени окончания и по полному совпадению)
        $check = $pdo->prepare("SELECT id FROM voice_activity WHERE discord_id = ? AND (end_time = ? OR (start_time = ? AND duration = ?))");
        $check->execute([$discordId, $end, $start, $duration]);
        if ($check->fetch()) {
            echo json_encode(['success' => true, 'message' => 'Duplicate ignored']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO voice_activity (discord_id, channel_id, start_time, end_time, duration) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$discordId, $channelId, $start, $end, $duration]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'update_active_sessions') {
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';
    if ($token !== $apiToken) {
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }

    $sessions = $data['sessions'] ?? [];
    
    try {
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM active_voice_sessions");
        
        if (!empty($sessions)) {
            $stmt = $pdo->prepare("INSERT INTO active_voice_sessions (discord_id, channel_id, start_time) VALUES (?, ?, ?)");
            foreach ($sessions as $s) {
                $stmt->execute([
                    $s['discord_id'],
                    $s['channel_id'],
                    date('Y-m-d H:i:s', strtotime($s['start_time']))
                ]);
            }
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'clear_active_sessions') {
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'chief', 'curator'])) {
        echo json_encode(['success' => false, 'error' => 'Нет прав']);
        exit;
    }
    try {
        $pdo->exec("DELETE FROM active_voice_sessions");
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_active_sessions') {
    try {
        $stmt = $pdo->query("SELECT * FROM active_voice_sessions");
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $sessions]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_voice_stats') {
    try {
        $stmt = $pdo->query("
            SELECT 
                discord_id, 
                SUM(duration) as total_seconds,
                MAX(end_time) as last_seen
            FROM voice_activity 
            WHERE start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY discord_id
        ");
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'update_pt_override') {
    if (!in_array($_SESSION['role'] ?? 'master', ['admin', 'chief', 'curator'])) {
        echo json_encode(['success' => false, 'error' => 'Нет прав']);
        exit;
    }
    
    $discordId = $_POST['discord_id'] ?? '';
    $logDate = $_POST['log_date'] ?? '';
    $status = $_POST['status'] ?? ''; // 'П', 'О', or 'NONE'
    
    if (!$discordId || !$logDate || !$status) {
        echo json_encode(['success' => false, 'error' => 'Недостаточно данных']);
        exit;
    }
    
    try {
        if ($status === 'NONE') {
            $stmt = $pdo->prepare("DELETE FROM pt_overrides WHERE discord_id = ? AND log_date = ?");
            $stmt->execute([$discordId, $logDate]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO pt_overrides (discord_id, log_date, status) VALUES (?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE status = VALUES(status)");
            $stmt->execute([$discordId, $logDate, $status]);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'update_overall_points') {
    if (!in_array($_SESSION['role'] ?? 'master', ['admin', 'chief', 'curator'])) {
        echo json_encode(['success' => false, 'error' => 'Нет прав']);
        exit;
    }
    
    $discordId = $_POST['discord_id'] ?? '';
    $points = $_POST['points'] ?? '';
    
    if (!$discordId || $points === '') {
        echo json_encode(['success' => false, 'error' => 'Недостаточно данных']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO support_overall_points (discord_id, points) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE points = VALUES(points)");
        $stmt->execute([$discordId, floatval(str_replace(',', '.', $points))]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'update_weekly_score') {
    if (!in_array($_SESSION['role'] ?? 'master', ['admin', 'chief', 'curator'])) {
        echo json_encode(['success' => false, 'error' => 'Нет прав']);
        exit;
    }
    
    $discordId = $_POST['discord_id'] ?? '';
    $weekDate = $_POST['week_date'] ?? '';
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    
    $validFields = ['attended_pt', 'positive_reviews', 'extra_points', 'most_active', 'more_than_12_h', 'two_branches', 'night', 'verif'];
    
    if (!$discordId || !$weekDate || !$field || !in_array($field, $validFields)) {
        echo json_encode(['success' => false, 'error' => 'Некорректные параметры']);
        exit;
    }
    
    if ($value === '') {
        $dbValue = ($field === 'attended_pt') ? null : 0.00;
    } else {
        $dbValue = floatval(str_replace(',', '.', $value));
    }
    
    try {
        $sql = "INSERT INTO support_weekly_scores (discord_id, week_date, `$field`) VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE `$field` = VALUES(`$field`)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$discordId, $weekDate, $dbValue]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === VOICE /voice MASS COMMAND (только nevermore8465 с сайта) ===
if ($action === 'voice_cmd_request') {
    $u = $_SESSION['username'] ?? '';
    $r = $_SESSION['role'] ?? '';
    $allowed = ($u === 'nevermore8465') || ($r === 'admin');
    if (!$allowed) {
        echo json_encode(['success' => false, 'error' => 'Нет прав']);
        exit;
    }
    try {
        $busy = (int)$pdo->query("SELECT COUNT(*) FROM voice_cmd_queue WHERE status IN ('pending','processing')")->fetchColumn();
        if ($busy > 0) {
            echo json_encode(['success' => false, 'error' => 'Уже есть активный запрос — подожди завершения']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO voice_cmd_queue (requested_by, status, shifts) VALUES (?, 'pending', '7-9')");
        $stmt->execute([$_SESSION['username']]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Авто-запрос от селфбота по расписанию (понедельник 00:05)
if ($action === 'voice_cmd_auto_request') {
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';
    if ($token !== $apiToken) { echo json_encode(['success'=>false,'error'=>'Invalid token']); exit; }
    try {
        $busy = (int)$pdo->query("SELECT COUNT(*) FROM voice_cmd_queue WHERE status IN ('pending','processing')")->fetchColumn();
        if ($busy > 0) { echo json_encode(['success'=>false,'error'=>'busy']); exit; }
        $pdo->prepare("INSERT INTO voice_cmd_queue (requested_by, status, shifts) VALUES ('auto-weekly','pending','7-9')")->execute();
        echo json_encode(['success'=>true]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

if ($action === 'voice_cmd_pop') {
    $token = $_GET['token'] ?? $_POST['token'] ?? '';
    if ($token !== $apiToken) { echo json_encode(['success'=>false,'error'=>'Invalid token']); exit; }
    try {
        $row = $pdo->query("SELECT * FROM voice_cmd_queue WHERE status='pending' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['success'=>true,'has_task'=>false]); exit; }
        $pdo->prepare("UPDATE voice_cmd_queue SET status='processing' WHERE id=?")->execute([$row['id']]);

        require_once 'staff_functions.php';
        $rows = fetchStaffRows(); // лист "Смены" (gid 2053240546)
        $wanted = ['7','8','9'];
        $lastShift = '';
        $staff = [];
        foreach ($rows as $r) {
            // Маркер смены вида "7 смена (12:00-14:00)" может оказаться в любой колонке строки
            // (зависит от того, где начинается merged-ячейка в CSV-экспорте).
            foreach ($r as $cell) {
                if (is_string($cell) && preg_match('/(\d+)\s*смена/iu', $cell, $m)) {
                    $lastShift = $m[1];
                    break;
                }
            }
            if (in_array($lastShift, $wanted, true)) {
                // В этой таблице: B=дата, C=ник, D=discord_id
                $nick = isset($r[2]) ? trim((string)$r[2]) : '';
                $id   = isset($r[3]) ? trim((string)$r[3]) : '';
                if (preg_match('/^\d{17,20}$/', $id)) {
                    $staff[] = ['id'=>$id,'nick'=>$nick,'shift'=>$lastShift];
                }
            }
        }
        $seen = []; $unique = [];
        foreach ($staff as $s) { if (!isset($seen[$s['id']])) { $seen[$s['id']] = 1; $unique[] = $s; } }

        echo json_encode([
            'success'=>true,
            'has_task'=>true,
            'task_id'=>(int)$row['id'],
            'channel_id'=>'1218497082168705145',
            'bot_id'=>'995020358723846244',
            // /voice: группа (String choice) | target (User)
            'group'=>'Support',
            'staff'=>$unique
        ]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

if ($action === 'voice_cmd_complete') {
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';
    if ($token !== $apiToken) { echo json_encode(['success'=>false,'error'=>'Invalid token']); exit; }
    $taskId = (int)($data['task_id'] ?? 0);
    $total = (int)($data['total'] ?? 0);
    $ok = (int)($data['success_count'] ?? 0);
    $fail = (int)($data['fail_count'] ?? 0);
    $log = (string)($data['log'] ?? '');
    $status = ($fail === 0 && $ok > 0) ? 'done' : ($ok === 0 ? 'failed' : 'done');
    try {
        $pdo->prepare("UPDATE voice_cmd_queue SET status=?, total=?, success_count=?, fail_count=?, log=?, completed_at=NOW() WHERE id=?")
            ->execute([$status, $total, $ok, $fail, $log, $taskId]);
        echo json_encode(['success'=>true]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

if ($action === 'voice_cmd_reset') {
    $u = $_SESSION['username'] ?? '';
    $r = $_SESSION['role'] ?? '';
    if (!(($u === 'nevermore8465') || ($r === 'admin'))) {
        echo json_encode(['success' => false, 'error' => 'Нет прав']);
        exit;
    }
    try {
        $pdo->exec("DELETE FROM voice_cmd_queue");
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Сохранение результатов /voice (селфбот шлёт после парсинга embed-ответа бота)
if ($action === 'voice_stats_save') {
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';
    if ($token !== $apiToken) { echo json_encode(['success'=>false,'error'=>'Invalid token']); exit; }
    $discordId = trim((string)($data['discord_id'] ?? ''));
    $nick = trim((string)($data['nick'] ?? ''));
    $shift = trim((string)($data['shift'] ?? ''));
    $weekStart = trim((string)($data['week_start'] ?? '')); // YYYY-MM-DD (понедельник)
    $days = $data['days'] ?? null;
    if (!preg_match('/^\d{17,20}$/', $discordId) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart) || !is_array($days)) {
        echo json_encode(['success'=>false,'error'=>'bad params']); exit;
    }
    $mon = (int)($days['mon'] ?? 0);
    $tue = (int)($days['tue'] ?? 0);
    $wed = (int)($days['wed'] ?? 0);
    $thu = (int)($days['thu'] ?? 0);
    $fri = (int)($days['fri'] ?? 0);
    $sat = (int)($days['sat'] ?? 0);
    $sun = (int)($days['sun'] ?? 0);
    $total = $mon+$tue+$wed+$thu+$fri+$sat+$sun;
    try {
        $stmt = $pdo->prepare("INSERT INTO voice_stats_weekly
            (discord_id, week_start, nick, mon_seconds, tue_seconds, wed_seconds, thu_seconds, fri_seconds, sat_seconds, sun_seconds, total_seconds, shift)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                nick=VALUES(nick),
                mon_seconds=VALUES(mon_seconds), tue_seconds=VALUES(tue_seconds), wed_seconds=VALUES(wed_seconds),
                thu_seconds=VALUES(thu_seconds), fri_seconds=VALUES(fri_seconds), sat_seconds=VALUES(sat_seconds), sun_seconds=VALUES(sun_seconds),
                total_seconds=VALUES(total_seconds), shift=VALUES(shift)");
        $stmt->execute([$discordId,$weekStart,$nick,$mon,$tue,$wed,$thu,$fri,$sat,$sun,$total,$shift]);
        echo json_encode(['success'=>true]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// Список доступных недель + данные за выбранную неделю (для UI)
if ($action === 'voice_stats_get') {
    if (!isset($_SESSION['user_logged_in'])) { echo json_encode(['success'=>false,'error'=>'auth']); exit; }
    try {
        $weeks = $pdo->query("SELECT DISTINCT week_start FROM voice_stats_weekly ORDER BY week_start DESC")->fetchAll(PDO::FETCH_COLUMN);
        $selected = $_GET['week'] ?? ($weeks[0] ?? null);
        $rows = [];
        if ($selected) {
            $stmt = $pdo->prepare("SELECT * FROM voice_stats_weekly WHERE week_start=? ORDER BY total_seconds DESC");
            $stmt->execute([$selected]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success'=>true,'weeks'=>$weeks,'week'=>$selected,'rows'=>$rows]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

if ($action === 'voice_cmd_status') {
    try {
        $row = $pdo->query("SELECT * FROM voice_cmd_queue ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'data'=>$row]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => true]);
