<?php
// pet_functions.php — логика системы питомцев (уровни, XP, квесты)

// HTML-тег картинки героя Доты (CDN Valve). Размер привязан к font-size родителя.
function dotaImg($slug)
{
    return '<img src="https://cdn.cloudflare.steamstatic.com/apps/dota2/images/dota_react/heroes/icons/' . $slug . '.png" style="width:1em;height:1em;object-fit:cover;border-radius:14%;vertical-align:middle;display:inline-block;" alt="">';
}

// Доступные типы питомцев: ключ => HTML/эмодзи для отображения
function petTypes()
{
    return [
        // эмодзи-зверушки
        'dog'     => '🐶',
        'cat'     => '🐱',
        'rabbit'  => '🐰',
        'fox'     => '🦊',
        'panda'   => '🐼',
        'hamster' => '🐹',
        'frog'    => '🐸',
        'penguin' => '🐧',
        'lion'    => '🦁',
        'dragon'  => '🐲',
        // герои Доты
        'pudge'          => dotaImg('pudge'),
        'invoker'        => dotaImg('invoker'),
        'juggernaut'     => dotaImg('juggernaut'),
        'antimage'       => dotaImg('antimage'),
        'crystal_maiden' => dotaImg('crystal_maiden'),
        'shadow_fiend'   => dotaImg('nevermore'), // у SF внутренний slug — 'nevermore'
        'lina'           => dotaImg('lina'),
        'axe'            => dotaImg('axe'),
        'sniper'         => dotaImg('sniper'),
        'lion_dota'      => dotaImg('lion'),
        'batrider'       => dotaImg('batrider'),
        // прочие
        'raccoon'        => '🦝',
        'shou_kusakabe'  => '<img src="https://cdn.discordapp.com/attachments/1436976322206236703/1510645520685600809/2026-05-31_190457676-no-bg-preview_carve.photos.png?ex=6a1d91c6&is=6a1c4046&hm=e8f250275be83400d25b8a6ac57bb402afc128d54be23cb213aaa80ea5392531&" style="width:1em;height:1em;object-fit:cover;border-radius:14%;vertical-align:middle;display:inline-block;" alt="">',
    ];
}

function petEmoji($type)
{
    $types = petTypes();
    return $types[$type] ?? '🐶';
}

// XP, необходимый суммарно для достижения уровня L: C(L) = 50 * (L-1) * L
// Переход L -> L+1 стоит 100 * L опыта.
function petLevelInfo($xp)
{
    $xp = max(0, (int) $xp);
    $level = 1;
    while ($xp >= 50 * $level * ($level + 1)) {
        $level++;
    }
    $base = 50 * ($level - 1) * $level;        // XP на старте текущего уровня
    $next = 50 * $level * ($level + 1);        // XP для следующего уровня
    return [
        'level'        => $level,
        'xp'           => $xp,
        'xp_into'      => $xp - $base,           // сколько набрано внутри уровня
        'xp_for_level' => $next - $base,         // сколько нужно для перехода
        'xp_total_next' => $next,
    ];
}

// Начислить опыт питомцу (если он заведён). Возвращает true, если начислено.
function petAwardXp($pdo, $discordId, $amount)
{
    if (!$discordId || $amount <= 0) return false;
    try {
        $stmt = $pdo->prepare("UPDATE pets SET xp = xp + ? WHERE discord_id = ?");
        $stmt->execute([(int) $amount, $discordId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Продвинуть автоматические квесты заданного типа (reattestation / add_support).
// При достижении цели — начисляет награду один раз.
function petAdvanceQuests($pdo, $discordId, $kind, $role, $inc = 1)
{
    if (!$discordId) return;
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM pet_quests
             WHERE is_active = 1 AND kind = ?
               AND (target_role = 'all' OR target_role = ?)"
        );
        $stmt->execute([$kind, $role]);
        $quests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($quests as $q) {
            // upsert прогресса
            $pdo->prepare(
                "INSERT INTO pet_quest_progress (quest_id, discord_id, progress)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE progress = progress + VALUES(progress)"
            )->execute([$q['id'], $discordId, $inc]);

            // проверяем завершение
            $p = $pdo->prepare("SELECT progress, rewarded FROM pet_quest_progress WHERE quest_id = ? AND discord_id = ?");
            $p->execute([$q['id'], $discordId]);
            $row = $p->fetch(PDO::FETCH_ASSOC);

            if ($row && (int) $row['progress'] >= (int) $q['goal_count'] && (int) $row['rewarded'] === 0) {
                $pdo->prepare("UPDATE pet_quest_progress SET completed = 1, rewarded = 1 WHERE quest_id = ? AND discord_id = ?")
                    ->execute([$q['id'], $discordId]);
                petAwardXp($pdo, $discordId, (int) $q['xp_reward']);
            }
        }
    } catch (Exception $e) {}
}

// Определения достижений.
// metric: reattestations | supports | tenure_days | level
// role:   all | curator | master  (для фильтра по роли владельца)
function achievementDefs()
{
    return [
        // Переаттестации (кураторы)
        ['id' => 'reatt_10',  'metric' => 'reattestations', 'goal' => 10,  'role' => 'curator', 'icon' => 'fa-clipboard-list',  'title' => 'Красавик!',              'desc' => 'Провести 10 переаттестаций', 'xp' => 50],
        ['id' => 'reatt_25',  'metric' => 'reattestations', 'goal' => 25,  'role' => 'curator', 'icon' => 'fa-clipboard-check', 'title' => 'Легенда!',               'desc' => 'Провести 25 переаттестаций', 'xp' => 100],
        ['id' => 'reatt_50',  'metric' => 'reattestations', 'goal' => 50,  'role' => 'curator', 'icon' => 'fa-graduation-cap',  'title' => 'Мастер аттестаций',      'desc' => 'Провести 50 переаттестаций', 'xp' => 200],
        ['id' => 'reatt_100', 'metric' => 'reattestations', 'goal' => 100, 'role' => 'curator', 'icon' => 'fa-crown',           'title' => 'Прикинь я проиграл',     'desc' => 'Провести 100 переаттестаций', 'xp' => 400],

        // Добавление саппортов (мастера)
        ['id' => 'supp_20',  'metric' => 'supports', 'goal' => 20,  'role' => 'master', 'icon' => 'fa-handshake',  'title' => 'Стажёр',             'desc' => 'Добавить 20 саппортов',  'xp' => 50],
        ['id' => 'supp_30',  'metric' => 'supports', 'goal' => 30,  'role' => 'master', 'icon' => 'fa-chart-line', 'title' => 'Безумец',            'desc' => 'Добавить 30 саппортов',  'xp' => 100],
        ['id' => 'supp_50',  'metric' => 'supports', 'goal' => 50,  'role' => 'master', 'icon' => 'fa-user-tie',   'title' => 'Кадровик года',      'desc' => 'Добавить 50 саппортов',  'xp' => 200],
        ['id' => 'supp_100', 'metric' => 'supports', 'goal' => 100, 'role' => 'master', 'icon' => 'fa-star',       'title' => 'Просто мусорнись',   'desc' => 'Добавить 100 саппортов', 'xp' => 400],

        // Стаж «на ветке» (все)
        ['id' => 'days_7',   'metric' => 'tenure_days', 'goal' => 7,   'role' => 'all', 'icon' => 'fa-seedling', 'title' => 'Прижился',     'desc' => 'Простоять на ветке 7 дней',   'xp' => 30],
        ['id' => 'days_30',  'metric' => 'tenure_days', 'goal' => 30,  'role' => 'all', 'icon' => 'fa-leaf',     'title' => 'Месяц в строю', 'desc' => 'Простоять на ветке 30 дней',  'xp' => 80],
        ['id' => 'days_50',  'metric' => 'tenure_days', 'goal' => 50,  'role' => 'all', 'icon' => 'fa-tree',     'title' => 'Старожил',      'desc' => 'Простоять на ветке 50 дней',  'xp' => 150],
        ['id' => 'days_100', 'metric' => 'tenure_days', 'goal' => 100, 'role' => 'all', 'icon' => 'fa-mountain', 'title' => 'Ветеран',       'desc' => 'Простоять на ветке 100 дней', 'xp' => 300],
        ['id' => 'days_365', 'metric' => 'tenure_days', 'goal' => 365, 'role' => 'all', 'icon' => 'fa-gem',      'title' => 'Год верности',  'desc' => 'Простоять на ветке 365 дней', 'xp' => 1000],

        // Уровень питомца (все)
        ['id' => 'lvl_5',  'metric' => 'level', 'goal' => 5,  'role' => 'all', 'icon' => 'fa-paw',    'title' => 'Питомец растёт',  'desc' => 'Прокачать питомца до 5 уровня',  'xp' => 0],
        ['id' => 'lvl_10', 'metric' => 'level', 'goal' => 10, 'role' => 'all', 'icon' => 'fa-bone',   'title' => 'Хороший хозяин',  'desc' => 'Прокачать питомца до 10 уровня', 'xp' => 0],
        ['id' => 'lvl_25', 'metric' => 'level', 'goal' => 25, 'role' => 'all', 'icon' => 'fa-trophy', 'title' => 'Лучший друг',     'desc' => 'Прокачать питомца до 25 уровня', 'xp' => 0],
    ];
}

// Метрики пользователя для достижений.
function getUserMetrics($pdo, $discordId)
{
    $m = ['reattestations' => 0, 'supports' => 0, 'tenure_days' => 0, 'level' => 1];
    try {
        $stmt = $pdo->prepare("SELECT reattestations_count, added_supports_count, appointment_date, created_at FROM users WHERE discord_id = ?");
        $stmt->execute([$discordId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $m['reattestations'] = (int) ($u['reattestations_count'] ?? 0);
            $m['supports'] = (int) ($u['added_supports_count'] ?? 0);
            $start = $u['appointment_date'] ?: ($u['created_at'] ?? null);
            if ($start) {
                $days = (int) floor((time() - strtotime($start)) / 86400);
                $m['tenure_days'] = max(0, $days);
            }
        }
    } catch (Exception $e) {}
    try {
        $p = $pdo->prepare("SELECT xp FROM pets WHERE discord_id = ?");
        $p->execute([$discordId]);
        $xp = $p->fetchColumn();
        if ($xp !== false) {
            $info = petLevelInfo((int) $xp);
            $m['level'] = $info['level'];
        }
    } catch (Exception $e) {}
    return $m;
}

// Проверяет достижения: выдаёт XP за впервые разблокированные, возвращает список со статусом.
function petCheckAchievements($pdo, $discordId, $role)
{
    $defs = achievementDefs();
    $metrics = getUserMetrics($pdo, $discordId);

    // уже разблокированные
    $unlocked = [];
    try {
        $stmt = $pdo->prepare("SELECT achievement_id FROM pet_achievements WHERE discord_id = ?");
        $stmt->execute([$discordId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $aid) $unlocked[$aid] = true;
    } catch (Exception $e) {}

    $result = [];
    foreach ($defs as $d) {
        // фильтр по роли (achievements мастера показываем мастерам, кураторские — кураторам/гл.куратору/админу)
        if ($d['role'] === 'curator' && !in_array($role, ['curator', 'chief', 'admin'])) continue;
        if ($d['role'] === 'master' && !in_array($role, ['master', 'curator', 'chief', 'admin'])) continue;

        $value = $metrics[$d['metric']] ?? 0;
        $isUnlocked = $value >= $d['goal'];

        // впервые разблокировано — фиксируем и начисляем XP
        if ($isUnlocked && empty($unlocked[$d['id']])) {
            try {
                $pdo->prepare("INSERT IGNORE INTO pet_achievements (discord_id, achievement_id) VALUES (?, ?)")
                    ->execute([$discordId, $d['id']]);
                if ((int) $d['xp'] > 0) petAwardXp($pdo, $discordId, (int) $d['xp']);
                $unlocked[$d['id']] = true;
            } catch (Exception $e) {}
        }

        $result[] = [
            'id' => $d['id'],
            'icon' => $d['icon'],
            'title' => $d['title'],
            'desc' => $d['desc'],
            'xp' => (int) $d['xp'],
            'goal' => (int) $d['goal'],
            'progress' => min((int) $value, (int) $d['goal']),
            'value' => (int) $value,
            'unlocked' => $isUnlocked,
        ];
    }
    return $result;
}

// Топ питомцев по опыту (для лидерборда).
function petLeaderboard($pdo, $limit = 15)
{
    try {
        $limit = max(1, min(50, (int) $limit));
        $stmt = $pdo->query("SELECT discord_id, owner_name, pet_type, pet_name, xp FROM pets ORDER BY xp DESC, updated_at ASC LIMIT $limit");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $info = petLevelInfo($r['xp']);
            $out[] = [
                'owner_name' => $r['owner_name'] ?: '—',
                'pet_name'   => $r['pet_name'],
                'emoji'      => petEmoji($r['pet_type']),
                'level'      => $info['level'],
                'xp'         => (int) $r['xp'],
            ];
        }
        return $out;
    } catch (Exception $e) {
        return [];
    }
}

// Список квестов, применимых к пользователю, с его прогрессом.
function petGetUserQuests($pdo, $discordId, $role)
{
    try {
        $stmt = $pdo->prepare(
            "SELECT q.*, COALESCE(p.progress, 0) AS progress, COALESCE(p.completed, 0) AS completed
             FROM pet_quests q
             LEFT JOIN pet_quest_progress p ON p.quest_id = q.id AND p.discord_id = ?
             WHERE q.is_active = 1 AND (q.target_role = 'all' OR q.target_role = ?)
             ORDER BY q.created_at DESC"
        );
        $stmt->execute([$discordId, $role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}
