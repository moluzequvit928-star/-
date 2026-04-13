<?php
session_start();
require_once 'db.php';

// УДАЛЕНИЕ СОБЫТИЯ (Только для Админа) - СРАЗУ В НАЧАЛЕ ФАЙЛА
if (isset($_POST['action']) && $_POST['action'] === 'delete_event' && ($_SESSION['role'] ?? '') === 'admin') {
    $eventId = (int)$_POST['event_id'];
    $stmtDel = $pdo->prepare("DELETE FROM staff_events WHERE id = ?");
    $stmtDel->execute([$eventId]);
    header("Location: admin_stats.php");
    exit;
}

require_once 'user_header.php';
require_once 'staff_functions.php';

// Только админ, гл. куратор и куратор могут видеть статистику
if ($current_role !== 'admin' && $current_role !== 'curator' && $current_role !== 'chief') {
    header('Location: index.php');
    exit;
}

// Ранги строк, которые нужно мониторить (блоки смен)
$targetRanges = [
    [12, 27], [32, 47], [52, 67], [72, 86], [91, 106],
    [111, 126], [131, 146], [151, 166], [171, 186],
    [191, 206], [211, 226], [231, 246], [251, 266]
];

/**
 * Функция синхронизации и логирования событий
 */
function syncStaffStats($pdo) {
    $rows = fetchStaffRows();
    if (empty($rows)) return;

    global $targetRanges;
    $currentActiveNicks = []; // Список ников, которые СЕЙЧАС в таблице
    $now = date('Y-m-d H:i:s');
    $thisWeekStart = date('Y-m-d', strtotime('monday this week'));

    foreach ($targetRanges as $range) {
        for ($i = $range[0] - 1; $i < $range[1]; $i++) {
            if (!isset($rows[$i])) continue;
            $row = $rows[$i];
            
            // Колонка B (1) - Дата, C (2) - Ник, D (3) - Discord ID
            $joinDateStr = trim($row[1] ?? '');
            $nick = trim($row[2] ?? '');
            $discordId = trim($row[3] ?? '');

            if ($nick === '' || strpos($nick, 'Мастер') !== false || strpos($nick, 'Куратор') !== false) continue;

            $currentActiveNicks[$nick] = [
                'id' => $discordId,
                'join_date' => $joinDateStr
            ];

            // 1. Проверяем, есть ли в кэше
            $stmt = $pdo->prepare("SELECT nickname FROM staff_current_cache WHERE nickname = ?");
            $stmt->execute([$nick]);
            if (!$stmt->fetch()) {
                // Ника нет в кэше — значит это "Новое добавление" (или мы первый раз его видим)
                
                // Проверяем, не логировали ли мы уже добавление этого ника на этой неделе
                $stmtCheckEv = $pdo->prepare("SELECT id FROM staff_events WHERE nickname = ? AND event_type = 'added' AND event_date >= ?");
                $stmtCheckEv->execute([$nick, $thisWeekStart]);
                
                if (!$stmtCheckEv->fetch()) {
                    // Пытаемся распарсить дату из таблицы для точности
                    $eventDate = date('Y-m-d');
                    if (!empty($joinDateStr)) {
                        $timestamp = strtotime($joinDateStr);
                        if ($timestamp) $eventDate = date('Y-m-d', $timestamp);
                    }
                    
                    // Логируем добавление
                    $stmtIns = $pdo->prepare("INSERT INTO staff_events (nickname, event_type, event_date, discord_id) VALUES (?, 'added', ?, ?)");
                    $stmtIns->execute([$nick, $eventDate, $discordId]);
                }

                // Добавляем в кэш
                $stmtCache = $pdo->prepare("REPLACE INTO staff_current_cache (nickname, discord_id, last_seen) VALUES (?, ?, ?)");
                $stmtCache->execute([$nick, $discordId, $now]);
            } else {
                // Ник есть — обновляем время и ПРОВЕРЯЕМ ID (вдруг его вписали позже)
                $stmtUpd = $pdo->prepare("UPDATE staff_current_cache SET last_seen = ?, discord_id = CASE WHEN discord_id = '' OR discord_id IS NULL THEN ? ELSE discord_id END WHERE nickname = ?");
                $stmtUpd->execute([$now, $discordId, $nick]);
                
                // Если в кэше ID был пустой, а сейчас пришел не пустой — принудительно обновляем все равно
                if (!empty($discordId)) {
                    $stmtForceUpd = $pdo->prepare("UPDATE staff_current_cache SET discord_id = ? WHERE nickname = ? AND discord_id != ?");
                    $stmtForceUpd->execute([$discordId, $nick, $discordId]);
                }
            }
        }
    }

    // 2. Ищем тех, кто пропал (был в кэше, но нет в текущем скане)
    // Мы считаем пропавшими тех, у кого last_seen не обновился в этом цикле
    $stmtMissing = $pdo->prepare("SELECT nickname, discord_id FROM staff_current_cache WHERE last_seen < ?");
    $stmtMissing->execute([$now]);
    $missing = $stmtMissing->fetchAll(PDO::FETCH_ASSOC);

    foreach ($missing as $m) {
        $mNick = $m['nickname'];
        
        // Логируем увольнение (снятие)
        // Проверяем, не логировали ли уже снятие сегодня (чтобы не дублить при рефрешах)
        $stmtCheckRem = $pdo->prepare("SELECT id FROM staff_events WHERE nickname = ? AND event_type = 'removed' AND event_date = CURDATE()");
        $stmtCheckRem->execute([$mNick]);
        
        if (!$stmtCheckRem->fetch()) {
            $mId = $m['discord_id'];
            
            // ЛЕЧИЛКА: если ID пустой, пробуем найти его в базе отчетов
            if (empty($mId)) {
                $stmtFindId = $pdo->prepare("SELECT discord_id FROM reports WHERE master_name = ? AND discord_id != '' LIMIT 1");
                $stmtFindId->execute([$mNick]);
                $found = $stmtFindId->fetchColumn();
                if ($found) $mId = $found;
            }

            $stmtInsRem = $pdo->prepare("INSERT INTO staff_events (nickname, event_type, event_date, discord_id) VALUES (?, 'removed', CURDATE(), ?)");
            $stmtInsRem->execute([$mNick, $mId]);
        }

        // Удаляем из актуального кэша
        $stmtDelCache = $pdo->prepare("DELETE FROM staff_current_cache WHERE nickname = ?");
        $stmtDelCache->execute([$mNick]);
    }
}

// Запускаем синхронизацию
syncStaffStats($pdo);

// Запускаем синхронизацию
syncStaffStats($pdo);

// Получаем статистику за текущую неделю
$monday = date('Y-m-d', strtotime('monday this week'));
$sunday = date('Y-m-d', strtotime('sunday this week'));

$stmtAdded = $pdo->prepare("SELECT COUNT(*) FROM staff_events WHERE event_type = 'added' AND event_date BETWEEN ? AND ?");
$stmtAdded->execute([$monday, $sunday]);
$addedCount = $stmtAdded->fetchColumn();

$stmtRemoved = $pdo->prepare("SELECT COUNT(*) FROM staff_events WHERE event_type = 'removed' AND event_date BETWEEN ? AND ?");
$stmtRemoved->execute([$monday, $sunday]);
$removedCount = $stmtRemoved->fetchColumn();

// Получаем последние события в журнале (Неделя)
$stmtEvents = $pdo->prepare("SELECT * FROM staff_events WHERE event_date BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 50");
$stmtEvents->execute([$monday, $sunday]);
$recentEvents = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика персонала | Панель</title>
    <link rel="icon" type="image/png" href="favicon_futurama_staff_1776084855108.png">
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
            max-width: 800px;
        }
        .stat-card {
            padding: 1.2rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            border-radius: 16px;
            transition: all 0.2s;
            min-height: 120px;
            justify-content: center;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-val {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1;
        }
        .stat-label {
            color: #94A3B8;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .added-color { color: #10B981; }
        .removed-color { color: #EF4444; }
        
        .events-table {
            width: 100%;
            border-collapse: collapse;
        }
        .events-table th, .events-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .events-table th {
            color: #A78BFA;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        .type-pill {
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
        }
        .pill-added { background: rgba(16, 185, 129, 0.15); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .pill-removed { background: rgba(239, 68, 68, 0.15); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.3); }
    </style>
</head>
<body>
    <button class="burger-btn" id="burgerBtn" aria-label="Меню">
        <span></span><span></span><span></span>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header glass">
                <div style="display: flex; align-items: center; gap: 1.5rem;">
                    <h1>Статистика Персонала</h1>
                    <button onclick="location.reload()" class="refresh-btn" style="background: rgba(167, 139, 250, 0.1); border: 1px solid rgba(167, 139, 250, 0.3); color: #A78BFA; width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s;" title="Обновить данные">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                    </button>
                </div>
                <div class="user-profile">
                    <div class="avatar-container" style="display: flex; align-items: center; gap: 0.75rem;">
                        <img src="<?= htmlspecialchars($avatar_url) ?>" alt="avatar" style="width: 36px; height: 36px; border-radius: 50%; border: 2px solid rgba(167, 139, 250, 0.5); object-fit: cover;">
                        <span class="username" style="font-weight: 500; color: #A78BFA;"><?= htmlspecialchars($_SESSION['username']) ?> <span style="font-size: 0.75rem; color: #94A3B8; font-weight: 400; margin-left: 5px;">(<?= htmlspecialchars($role_display) ?>)</span></span>
                        <a href="logout.php" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: rgba(239, 68, 68, 0.2); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.4);">Выйти</a>
                    </div>
                </div>
            </header>

            <section class="content">
                <div style="margin-bottom: 1rem; color: #94A3B8; font-size: 0.9rem; grid-column: 1 / -1;">
                    Период: <strong style="color: #E2E8F0;"><?= date('d.m.Y', strtotime($monday)) ?></strong> — <strong style="color: #E2E8F0;"><?= date('d.m.Y', strtotime($sunday)) ?></strong>
                </div>

                <div class="stats-grid" style="grid-column: 1 / -1;">
                    <div class="card glass stat-card">
                        <span class="stat-label">Добавилось саппортов</span>
                        <span class="stat-val added-color">+<?= $addedCount ?></span>
                        <p style="margin:0; font-size: 0.8rem; color: #64748B;">За текущую неделю</p>
                    </div>
                    <div class="card glass stat-card">
                        <span class="stat-label">Снялось саппортов</span>
                        <span class="stat-val removed-color">-<?= $removedCount ?></span>
                        <p style="margin:0; font-size: 0.8rem; color: #64748B;">За текущую неделю</p>
                    </div>
                </div>

                    <div class="card glass" style="grid-column: 1 / -1;">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <h3>Журнал изменений (Неделя)</h3>
                        <span class="status info" style="background: rgba(167, 139, 250, 0.15); color: #C4B5FD; border: 1px solid rgba(167, 139, 250, 0.3);">Динамика состава</span>
                    </div>
                    <div class="card-body" style="overflow-x: auto;">
                        <table class="events-table">
                            <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>Событие</th>
                                    <th>Пользователь</th>
                                    <th>Discord ID</th>
                                    <?php if ($current_role === 'admin'): ?><th>Действ.</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recentEvents) > 0): ?>
                                    <?php foreach ($recentEvents as $ev): ?>
                                        <tr>
                                            <td style="color: #94A3B8;"><?= date('d.m.Y', strtotime($ev['event_date'])) ?></td>
                                            <td>
                                                <?php if ($ev['event_type'] === 'added'): ?>
                                                    <span class="type-pill pill-added">ПРИНЯТ</span>
                                                <?php else: ?>
                                                    <span class="type-pill pill-removed">СНЯТ</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-weight: 600; color: #E2E8F0;"><?= htmlspecialchars($ev['nickname']) ?></td>
                                            <td style="font-family: monospace; color: #64748B; font-size: 0.85rem;"><?= htmlspecialchars($ev['discord_id']) ?></td>
                                            <?php if ($current_role === 'admin'): ?>
                                            <td>
                                                <form method="POST" onsubmit="return confirm('Удалить эту запись из журнала?');">
                                                    <input type="hidden" name="action" value="delete_event">
                                                    <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                                                    <button type="submit" style="background: none; border: none; color: #EF4444; cursor: pointer; padding: 2px 5px; font-size: 0.9rem;" title="Удалить запись">🗑️</button>
                                                </form>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #64748B; padding: 2rem;">Событий пока не зафиксировано. Статистика начнет наполняться при изменении состава в таблице.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
<script>
    const burgerBtn = document.getElementById('burgerBtn');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    function toggleMenu() {
        burgerBtn.classList.toggle('open');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    }
    burgerBtn.addEventListener('click', toggleMenu);
    overlay.addEventListener('click', toggleMenu);
</script>
</html>
