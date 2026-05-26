<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

// Проверка прав
$allowed_roles = ['admin', 'chief', 'curator'];
if (!isset($_SESSION['user_logged_in']) || !in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещён']);
    exit;
}

require_once 'db.php';

// Список проходных каналов (id => название)
$CHANNELS = [
    '1268331705194774643' => '1 проходная',
    '1268327713463341168' => '2 проходная',
    '1268327800767774720' => '3 проходная',
    '1268327820736598128' => '4 проходная',
    '1268327846494081064' => '5 проходная',
    '1268327884045684807' => '6 проходная',
    '1268328226607075338' => '7 проходная',
    '1268328281761906698' => '8 проходная',
    '1318228034016514128' => '9 проходная',
    '1501951333790384189' => '10 проходная',
    '1503680035528376571' => '11 проходная',
    '1503680189391966238' => '12 проходная',
];

try {
    $channelIds = array_keys($CHANNELS);
    $channelsData = null;
    $liveBotSucceeded = false;

    // 1. Попытка получить живые данные от селф-бота в реальном времени
    $use_live_bot = true; // Можно отключить при необходимости
    if ($use_live_bot) {
        if (PHP_OS_FAMILY === 'Windows') {
            $command = 'cmd /c "node ' . __DIR__ . '/check_channels.js"';
        } else {
            $command = 'node ' . __DIR__ . '/check_channels.js 2>&1';
        }

        $output = [];
        $return_var = 0;
        @exec($command, $output, $return_var);

        if ($return_var === 0) {
            $raw_output = implode("\n", $output);
            if (preg_match('/---CHANNELS_DATA---\n(.*?)\n---END_CHANNELS_DATA---/s', $raw_output, $matches)) {
                $parsedData = json_decode(trim($matches[1]), true);
                if (is_array($parsedData)) {
                    $channelsData = $parsedData;
                    $liveBotSucceeded = true;

                    // Синхронизируем базу данных active_voice_sessions
                    try {
                        $pdo->beginTransaction();
                        $pdo->exec("DELETE FROM active_voice_sessions");

                        $stmtIns = $pdo->prepare("INSERT INTO active_voice_sessions (discord_id, channel_id, start_time) VALUES (?, ?, ?)");
                        $nowStr = date('Y-m-d H:i:s');

                        foreach ($channelsData as $ch) {
                            if (isset($ch['members']) && is_array($ch['members'])) {
                                foreach ($ch['members'] as $m) {
                                    $stmtIns->execute([
                                        $m['id'],
                                        $ch['id'],
                                        $nowStr
                                    ]);
                                }
                            }
                        }
                        $pdo->commit();
                    } catch (Exception $dbEx) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                    }
                }
            }
        }
    }

    // 2. Если живой запрос удался, подготавливаем вывод в нужном формате
    if ($liveBotSucceeded && is_array($channelsData)) {
        // Добавляем кэширование имени пользователя, если он зарегистрирован на сайте
        // И приводим формат к ожидаемому для frontend
        $formattedChannels = [];
        foreach ($channelsData as $ch) {
            $members = [];
            if (isset($ch['members']) && is_array($ch['members'])) {
                foreach ($ch['members'] as $m) {
                    // Ищем настоящее имя в локальной БД пользователей
                    $stmtUser = $pdo->prepare("SELECT username FROM users WHERE discord_id = ?");
                    $stmtUser->execute([$m['id']]);
                    $dbUser = $stmtUser->fetchColumn();
                    
                    $members[] = [
                        'id'     => $m['id'],
                        'tag'    => $dbUser ? $dbUser : $m['tag'],
                        'avatar' => $m['avatar'],
                        'since'  => date('Y-m-d H:i:s') // для живого бота ставим текущее время
                    ];
                }
            }
            $formattedChannels[] = [
                'id'      => $ch['id'],
                'name'    => $ch['name'],
                'count'   => count($members),
                'members' => $members
            ];
        }

        echo json_encode([
            'success'     => true,
            'channels'    => $formattedChannels,
            'source'      => 'self-bot-live',
            'last_update' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    // 3. ФОЛБЕК: Читаем сохраненные сессии из БД (если бот не сработал или отключен)
    $placeholders = implode(',', array_fill(0, count($channelIds), '?'));

    $stmt = $pdo->prepare("
        SELECT 
            avs.discord_id,
            avs.channel_id,
            avs.start_time,
            COALESCE(u.username, avs.discord_id) as display_name
        FROM active_voice_sessions avs
        LEFT JOIN users u ON u.discord_id = avs.discord_id
        WHERE avs.channel_id IN ($placeholders)
        ORDER BY avs.channel_id, avs.start_time ASC
    ");
    $stmt->execute($channelIds);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Группируем по каналу
    $byChannel = [];
    foreach ($channelIds as $cid) {
        $byChannel[$cid] = [];
    }
    foreach ($sessions as $s) {
        if (isset($byChannel[$s['channel_id']])) {
            $byChannel[$s['channel_id']][] = $s;
        }
    }

    // Формируем ответ
    $result = [];
    foreach ($CHANNELS as $cid => $cname) {
        $members = array_map(function($s) {
            $avatarUrl = 'https://cdn.discordapp.com/embed/avatars/0.png';
            $numId = (float)$s['discord_id'];
            if ($numId > 0) {
                $avatarIdx = fmod($numId, 5);
                $avatarUrl = "https://cdn.discordapp.com/embed/avatars/{$avatarIdx}.png";
            }
            return [
                'id'     => $s['discord_id'],
                'tag'    => $s['display_name'],
                'avatar' => $avatarUrl,
                'since'  => $s['start_time'],
            ];
        }, $byChannel[$cid]);

        $result[] = [
            'id'      => $cid,
            'name'    => $cname,
            'count'   => count($members),
            'members' => $members,
        ];
    }

    // Время последнего обновления данных ботом
    $lastUpdate = null;
    try {
        $stmtLast = $pdo->query("SELECT MAX(created_at) FROM active_voice_sessions");
        $lastUpdate = $stmtLast->fetchColumn();
    } catch (Exception $e) {}

    echo json_encode([
        'success'     => true,
        'channels'    => $result,
        'source'      => 'database',
        'last_update' => $lastUpdate,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $e->getMessage()]);
}
