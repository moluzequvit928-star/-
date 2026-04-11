<?php
session_start();
require_once 'db.php';
require_once 'user_header.php';
require_once 'staff_functions.php';

// Только админ может видеть статистику
if ($current_role !== 'admin') {
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
                // Ник есть — просто обновляем время последнего визита в кэше
                $stmtUpd = $pdo->prepare("UPDATE staff_current_cache SET last_seen = ? WHERE nickname = ?");
                $stmtUpd->execute([$now, $nick]);
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
            $stmtInsRem = $pdo->prepare("INSERT INTO staff_events (nickname, event_type, event_date, discord_id) VALUES (?, 'removed', CURDATE(), ?)");
            $stmtInsRem->execute([$mNick, $m['discord_id']]);
        }

        // Удаляем из актуального кэша
        $stmtDelCache = $pdo->prepare("DELETE FROM staff_current_cache WHERE nickname = ?");
        $stmtDelCache->execute([$mNick]);
    }
}

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

// Список последних событий
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
        <!-- Sidebar -->
        <aside class="sidebar glass">
            <div class="logo">
                <h2>Панель</h2>
            </div>
            <nav class="menu">
                <a href="index.php" class="menu-item">Главная</a>
                <a href="admin_stats.php" class="menu-item active">Статистика</a>
                <a href="reattestation.php" class="menu-item">Переаттестация</a>
                <a href="check_reports.php" class="menu-item" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Проверка отчетов</span>
                    <?php if ($sidebarPendingCount > 0): ?>
                    <span style="background: #EF4444; color: white; font-size: 0.75rem; font-weight: 700; padding: 0.1rem 0.45rem; border-radius: 999px; line-height: 1.6; min-width: 20px; text-align: center;"><?= $sidebarPendingCount ?></span>
                    <?php endif; ?>
                </a>
                
                <div class="menu-section-title">Полезные ссылки</div>
                <a href="https://docs.google.com/spreadsheets/d/1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754/edit?gid=1970062457#gid=1970062457" class="menu-item highlight" target="_blank">Google Таблица ↗</a>
                <a href="https://docs.google.com/document/d/1tef_iQ0GuuIVgQRI15Ql8H74BFPjEcI9Cg3qZCQrtL8/edit?tab=t.0" class="menu-item highlight" target="_blank">Собес на саппорта ↗</a>

                <div class="menu-section-title">Работа</div>
                <a href="reports.php" class="menu-item">Отчеты по наборам</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header glass">
                <h1>Статистика Персонала</h1>
                <div class="user-profile">
                    <div class="avatar-container" style="display: flex; align-items: center; gap: 0.75rem;">
                        <img src="<?= htmlspecialchars($avatar_url) ?>" alt="avatar" style="width: 36px; height: 36px; border-radius: 50%; border: 2px solid rgba(167, 139, 250, 0.5); object-fit: cover;">
                        <span class="username" style="font-weight: 500; color: #A78BFA;"><?= htmlspecialchars($_SESSION['username']) ?> <span style="font-size: 0.75rem; color: #94A3B8; font-weight: 400; margin-left: 5px;">(Администратор)</span></span>
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
                    <div class="card-header">
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
