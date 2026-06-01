<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Доступно всем авторизованным пользователям

require_once 'db.php';
require_once 'user_header.php';

// Статистика проходок
$todayReports = 0;
$weekReports = 0;
$monthReports = 0;

try {
    // Источник статистики — voice_activity (сюда voice-трекер пишет каждую сессию в проходных).
    // Считаем уникальные сессии (по строкам).

    // Сегодня
    $stmtT = $pdo->query("SELECT COUNT(*) FROM voice_activity WHERE DATE(start_time) = CURDATE()");
    $todayReports = $stmtT->fetchColumn();

    // Неделя (последние 7 дней)
    $stmtW = $pdo->query("SELECT COUNT(*) FROM voice_activity WHERE start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $weekReports = $stmtW->fetchColumn();

    // Месяц (последние 30 дней)
    $stmtM = $pdo->query("SELECT COUNT(*) FROM voice_activity WHERE start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $monthReports = $stmtM->fetchColumn();

    // Данные для графика за последние 7 дней
    $chartLabels = [];
    $chartData = [];

    $stmtChart = $pdo->query("
        SELECT DATE(start_time) as report_date, COUNT(*) as count
        FROM voice_activity
        WHERE start_time >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(start_time)
        ORDER BY DATE(start_time) ASC
    ");
    $chartRows = $stmtChart->fetchAll(PDO::FETCH_ASSOC);
    
    $indexedChart = [];
    foreach ($chartRows as $cr) {
        $indexedChart[$cr['report_date']] = (int)$cr['count'];
    }
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chartLabels[] = date('d.m', strtotime($date));
        $chartData[] = $indexedChart[$date] ?? 0;
    }
} catch (Exception $e) {
    // В случае ошибки БД заполняем нулями
    $chartLabels = [];
    $chartData = [];
    for ($i = 6; $i >= 0; $i--) {
        $chartLabels[] = date('d.m', strtotime("-$i days"));
        $chartData[] = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FUTURAMA STAFF | Чек проходных</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .lobby-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .lobby-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-layout-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        @media (max-width: 768px) {
            .chart-layout-grid {
                grid-template-columns: 1fr !important;
            }
        }

        .lobby-stat-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.2rem;
            backdrop-filter: blur(10px);
            transition: transform 0.3s, border-color 0.3s;
        }

        .lobby-stat-card:hover {
            transform: translateY(-2px);
            border-color: rgba(99, 102, 241, 0.3);
        }

        .lobby-stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .lobby-stat-num {
            font-size: 1.8rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.2;
        }

        .lobby-stat-label {
            color: #94A3B8;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 2px;
        }

        .lobby-card-wrapper {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            position: relative;
        }

        .lobby-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .lobby-card-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .lobby-card-title i {
            color: #818cf8;
            font-size: 1.5rem;
        }

        .btn-lobby-refresh {
            background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);
            color: #fff;
            border: none;
            padding: 0.8rem 1.6rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
            transition: all 0.3s;
        }

        .btn-lobby-refresh:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
            filter: brightness(1.1);
        }

        .btn-lobby-refresh:active {
            transform: translateY(0);
        }

        .btn-lobby-refresh:disabled {
            background: #4b5563;
            cursor: not-allowed;
            box-shadow: none;
        }

        /* Анимированный лоадер с обратным отсчетом */
        .lobby-loader {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 4rem 2rem;
            text-align: center;
        }

        .countdown-circle {
            position: relative;
            width: 100px;
            height: 100px;
            margin-bottom: 1.5rem;
        }

        .countdown-circle svg {
            width: 100px;
            height: 100px;
            transform: rotate(-90deg);
        }

        .countdown-circle circle {
            fill: none;
            stroke-width: 8;
            stroke-linecap: round;
        }

        .countdown-circle .bg-circle {
            stroke: rgba(255, 255, 255, 0.05);
        }

        .countdown-circle .progress-circle {
            stroke: #818cf8;
            stroke-dasharray: 283;
            stroke-dashoffset: 0;
            transition: stroke-dashoffset 0.1s linear;
        }

        .countdown-number {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.8rem;
            font-weight: 800;
            color: #fff;
        }

        .lobby-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.25rem;
        }

        .lobby-room-card {
            background: rgba(15, 23, 42, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.04);
            border-radius: 18px;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            transition: all 0.3s;
        }

        .lobby-room-card.active {
            border-color: rgba(34, 197, 94, 0.3);
            background: rgba(34, 197, 94, 0.03);
            box-shadow: 0 4px 20px rgba(34, 197, 94, 0.05);
        }

        .lobby-room-card.empty {
            opacity: 0.6;
        }

        .room-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .room-title {
            font-weight: 700;
            color: #F8FAFC;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .room-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
        }

        .lobby-room-card.active .room-dot {
            background: #22c55e;
            box-shadow: 0 0 10px #22c55e;
        }

        .room-badge {
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .room-badge.active {
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .room-badge.empty {
            background: rgba(255, 255, 255, 0.05);
            color: #94A3B8;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .room-users-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 0.25rem;
        }

        .room-user-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.02);
            padding: 8px 12px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.03);
            transition: all 0.2s;
        }

        .room-user-item:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(2px);
        }

        .room-user-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 1.5px solid rgba(255, 255, 255, 0.1);
        }

        .room-user-tag {
            font-size: 0.82rem;
            font-weight: 600;
            color: #E2E8F0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .placeholder-text {
            color: #64748B;
            font-size: 0.85rem;
            font-style: italic;
            text-align: center;
            padding: 1.5rem 0;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Чек проходных</h1>
                    <p>Мониторинг активности саппортов в голосовых лобби</p>
                </div>
                <div class="user-profile" style="display: flex; align-items: center; gap: 1rem;">
                    <img src="<?= getAvatarUrl($_SESSION['discord_id'], $_SESSION['username']) ?>" 
                         style="width: 38px; height: 38px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1);" 
                         alt="">
                    <a href="logout.php" class="btn-logout-premium">
                        <i class="fas fa-sign-out-alt"></i> Выйти
                    </a>
                </div>
            </header>

            <div class="page-body">
                <div class="lobby-container">

                    <!-- Карточки статистики -->
                    <div class="lobby-stats-grid">
                        <div class="lobby-stat-card">
                            <div class="lobby-stat-icon" style="background: rgba(99, 102, 241, 0.1); color: #818cf8;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <div class="lobby-stat-num" id="statTotalUsers">0</div>
                                <div class="lobby-stat-label">Всего на сменах</div>
                            </div>
                        </div>
                        <div class="lobby-stat-card">
                            <div class="lobby-stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #4ade80;">
                                <i class="fas fa-door-open"></i>
                            </div>
                            <div>
                                <div class="lobby-stat-num" id="statActiveRooms">0</div>
                                <div class="lobby-stat-label">Активно комнат</div>
                            </div>
                        </div>
                        <div class="lobby-stat-card">
                            <div class="lobby-stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #f87171;">
                                <i class="fas fa-door-closed"></i>
                            </div>
                            <div>
                                <div class="lobby-stat-num" id="statEmptyRooms">12</div>
                                <div class="lobby-stat-label">Пустых комнат</div>
                            </div>
                        </div>
                    </div>

                    <!-- Статистика забитых проходок -->
                    <div style="display: flex; align-items: center; gap: 12px; margin-top: 1rem; margin-bottom: 1.25rem; padding-left: 0.5rem;">
                        <i class="fas fa-chart-line" style="color: #A78BFA; font-size: 1.2rem;"></i>
                        <span style="font-size: 1.1rem; font-weight: 700; color: #fff; font-family: 'Outfit', sans-serif;">Учет забитых проходок</span>
                    </div>
                    <div class="chart-layout-grid">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <!-- За сегодня -->
                            <div class="lobby-stat-card" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.05) 0%, rgba(30, 41, 59, 0.4) 100%); margin-bottom: 0;">
                                <div class="lobby-stat-icon" style="background: rgba(139, 92, 246, 0.15); color: #a78bfa; text-shadow: 0 0 10px rgba(167, 139, 250, 0.3);">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div>
                                    <div class="lobby-stat-num" style="color: #fff; font-family: 'Outfit', sans-serif;"><?= $todayReports ?></div>
                                    <div class="lobby-stat-label">За сегодня</div>
                                </div>
                            </div>
                            <!-- За неделю -->
                            <div class="lobby-stat-card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(30, 41, 59, 0.4) 100%); margin-bottom: 0;">
                                <div class="lobby-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; text-shadow: 0 0 10px rgba(96, 165, 250, 0.3);">
                                    <i class="fas fa-calendar-week"></i>
                                </div>
                                <div>
                                    <div class="lobby-stat-num" style="color: #fff; font-family: 'Outfit', sans-serif;"><?= $weekReports ?></div>
                                    <div class="lobby-stat-label">За неделю</div>
                                </div>
                            </div>
                            <!-- За месяц -->
                            <div class="lobby-stat-card" style="background: linear-gradient(135deg, rgba(236, 72, 153, 0.05) 0%, rgba(30, 41, 59, 0.4) 100%); margin-bottom: 0;">
                                <div class="lobby-stat-icon" style="background: rgba(236, 72, 153, 0.15); color: #f472b6; text-shadow: 0 0 10px rgba(244, 114, 182, 0.3);">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div>
                                    <div class="lobby-stat-num" style="color: #fff; font-family: 'Outfit', sans-serif;"><?= $monthReports ?></div>
                                    <div class="lobby-stat-label">За месяц</div>
                                </div>
                            </div>
                        </div>
                        <div class="lobby-stat-card" style="background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; padding: 1.5rem; display: flex; flex-direction: column; justify-content: center; height: 100%;">
                            <canvas id="reportsChart" style="max-height: 220px; width: 100%;"></canvas>
                        </div>
                    </div>

                    <!-- Панель управления и сетка комнат -->
                    <div class="lobby-card-wrapper">
                        <div class="lobby-card-header">
                            <div class="lobby-card-title">
                                <i class="fas fa-headset"></i>
                                Текущее состояние комнат
                            </div>
                            <button class="btn-lobby-refresh" id="btnRefreshLobby">
                                <i class="fas fa-rotate-right" id="refreshIcon"></i> Обновить статус
                            </button>
                        </div>

                        <!-- Лоадер с отсчетом -->
                        <div class="lobby-loader" id="lobbyLoader">
                            <div class="countdown-circle">
                                <svg>
                                    <circle class="bg-circle" cx="50" cy="50" r="45"></circle>
                                    <circle class="progress-circle" id="loaderCircle" cx="50" cy="50" r="45"></circle>
                                </svg>
                                <div class="countdown-number" id="countdownSec">15</div>
                            </div>
                            <h3 style="color: #fff; margin-bottom: 0.5rem; font-weight: 700;">Подключение селф-бота...</h3>
                            <p style="color: #94A3B8; font-size: 0.88rem; max-width: 320px; margin: 0 auto;">Считываем участников в голосовых проходных комнатах в Discord</p>
                        </div>

                        <!-- Сетка комнат -->
                        <div class="lobby-grid" id="lobbyGrid">
                            <!-- Начальное состояние -->
                            <div style="grid-column: 1/-1; text-align: center; padding: 4rem 1rem; color: #64748B;">
                                <i class="fas fa-headset" style="font-size: 3rem; color: rgba(129, 138, 248, 0.3); margin-bottom: 1.5rem; display: block;"></i>
                                <span style="font-size: 1.1rem; display: block; margin-bottom: 0.5rem; font-weight: 700; color: #E2E8F0;">Мониторинг готов к запуску</span>
                                Нажмите кнопку «Обновить статус», чтобы подключить бота и запросить данные в реальном времени.
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('btnRefreshLobby').addEventListener('click', async function() {
            const btn = this;
            const icon = document.getElementById('refreshIcon');
            const loader = document.getElementById('lobbyLoader');
            const grid = document.getElementById('lobbyGrid');
            const circle = document.getElementById('loaderCircle');
            const timerText = document.getElementById('countdownSec');

            btn.disabled = true;
            icon.classList.add('fa-spin');
            grid.style.display = 'none';
            loader.style.display = 'flex';

            // Настройка анимации лоадера
            const totalDuration = 15000; // 15 секунд
            const startTime = Date.now();
            circle.style.strokeDashoffset = 0;

            const progressInterval = setInterval(() => {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / totalDuration, 1);
                
                // stroke-dashoffset меняется от 0 до 283 (длина окружности радиуса 45)
                circle.style.strokeDashoffset = 283 * progress;
                
                const secondsLeft = Math.ceil((totalDuration - elapsed) / 1000);
                timerText.textContent = Math.max(secondsLeft, 0);

                if (elapsed >= totalDuration) {
                    clearInterval(progressInterval);
                }
            }, 50);

            try {
                const res = await fetch('api_channels.php');
                
                // Проверяем HTTP статус
                if (!res.ok) {
                    clearInterval(progressInterval);
                    grid.innerHTML = `
                        <div style="grid-column: 1/-1; text-align: center; padding: 3rem 1rem;">
                            <i class="fas fa-database" style="font-size: 3rem; margin-bottom: 1rem; display: block; color: rgba(167, 139, 250, 0.4);"></i>
                            <span style="font-size: 1.15rem; display: block; margin-bottom: 0.75rem; font-weight: 800; color: #f1f5f9;">Ошибка подключения к БД</span>
                            <p style="color: #94a3b8; font-size: 0.9rem; max-width: 400px; margin: 0 auto;">Ошибка ${res.status}: не удалось получить данные.</p>
                        </div>`;
                    return;
                }
                
                const text = await res.text();
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch(e) {
                    console.error("Non-JSON API response:", text);
                    clearInterval(progressInterval);
                    grid.innerHTML = `
                        <div style="grid-column: 1/-1; text-align: center; padding: 3rem 1rem; color: #f87171;">
                            <i class="fas fa-triangle-exclamation" style="font-size: 2.5rem; margin-bottom: 1rem; display: block;"></i>
                            <span style="font-size: 1.1rem; display: block; margin-bottom: 0.5rem; font-weight: 700;">Некорректный ответ сервера</span>
                            <p style="color:#94a3b8; font-size:0.88rem; margin-bottom:1rem;">Убедитесь, что бот запущен и сайт работает локально.</p>
                            <pre style="text-align: left; background: rgba(0,0,0,0.4); padding: 1.25rem; border-radius: 12px; font-family: monospace; font-size: 0.8rem; margin-top: 1rem; overflow-x: auto; color: #e2e8f0; border: 1px solid rgba(255,255,255,0.06); max-height: 200px; line-height: 1.4;">${text.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</pre>
                        </div>`;
                    return;
                }

                clearInterval(progressInterval);

                if (!data.success) {
                    grid.innerHTML = `
                        <div style="grid-column: 1/-1; text-align: center; padding: 4rem 1rem; color: #f87171;">
                            <i class="fas fa-triangle-exclamation" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                            <span style="font-size: 1.1rem; display: block; margin-bottom: 0.5rem; font-weight: 700;">Произошла ошибка</span>
                            ${data.error || 'Не удалось получить данные от селф-бота.'}
                            ${data.raw ? `<pre style="text-align: left; background: rgba(0,0,0,0.4); padding: 1.25rem; border-radius: 12px; font-family: monospace; font-size: 0.8rem; margin-top: 1.5rem; overflow-x: auto; color: #e2e8f0; border: 1px solid rgba(255,255,255,0.06); max-height: 250px;">${data.raw.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</pre>` : ''}
                        </div>`;
                    return;
                }

                const channels = data.channels;
                let activeCount = 0;
                let emptyCount = 0;
                let totalUsers = 0;

                let html = '';
                channels.forEach(ch => {
                    const busy = ch.count > 0;
                    if (busy) {
                        activeCount++;
                        totalUsers += ch.count;
                    } else {
                        emptyCount++;
                    }

                    let usersHtml = '';
                    if (busy) {
                        usersHtml = ch.members.map(m => {
                            const since = m.since ? (() => {
                                const diff = Math.round((Date.now() - new Date(m.since.replace(' ', 'T')).getTime()) / 60000);
                                return diff > 0 ? `<span style="font-size:0.7rem; color:#64748b; margin-left:4px;">${diff} мин.</span>` : '';
                            })() : '';
                            return `
                            <div class="room-user-item" title="${m.tag}">
                                <img class="room-user-avatar" src="${m.avatar}" alt=""
                                     onerror="this.src='https://cdn.discordapp.com/embed/avatars/0.png'">
                                <span class="room-user-tag">${m.tag}${since}</span>
                            </div>`;
                        }).join('');
                    } else {
                        usersHtml = '<p class="placeholder-text">Комната пуста</p>';
                    }

                    html += `
                        <div class="lobby-room-card ${busy ? 'active' : 'empty'}">
                            <div class="room-header">
                                <div class="room-title">
                                    <div class="room-dot"></div>
                                    <span>${ch.name}</span>
                                </div>
                                <span class="room-badge ${busy ? 'active' : 'empty'}">${busy ? ch.count + ' чел.' : 'свободно'}</span>
                            </div>
                            <div class="room-users-list">
                                ${usersHtml}
                            </div>
                        </div>`;
                });

                grid.innerHTML = html;

                // Обновляем статистику
                document.getElementById('statTotalUsers').textContent = totalUsers;
                document.getElementById('statActiveRooms').textContent = activeCount;
                document.getElementById('statEmptyRooms').textContent = emptyCount;

                // Показываем время последнего обновления
                if (data.last_update) {
                    const lu = new Date(data.last_update.replace(' ', 'T'));
                    const mins = Math.round((Date.now() - lu.getTime()) / 60000);
                    const luText = mins <= 0 ? 'только что' : `${mins} мин. назад`;
                    const existingBadge = document.getElementById('lastUpdateBadge');
                    if (existingBadge) existingBadge.remove();
                    const badge = document.createElement('div');
                    badge.id = 'lastUpdateBadge';
                    badge.style.cssText = 'display:inline-flex; gap:6px; align-items:center; background:rgba(34,197,94,0.08); border:1px solid rgba(34,197,94,0.2); border-radius:10px; padding:0.4rem 1rem; color:#4ade80; font-size:0.78rem; margin-top:1rem;';
                    badge.innerHTML = `<i class="fas fa-circle-dot" style="animation:pulse 2s infinite;"></i> Бот обновил данные: ${luText}`;
                    document.getElementById('lobbyGrid').parentElement.appendChild(badge);
                }

            } catch (e) {
                console.error(e);
                clearInterval(progressInterval);
                grid.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 4rem 1rem; color: #f87171;">
                        <i class="fas fa-triangle-exclamation" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                        <span style="font-size: 1.1rem; display: block; margin-bottom: 0.5rem; font-weight: 700;">Критическая ошибка</span>
                        Не удалось установить связь с API сервера.
                    </div>`;
            } finally {
                btn.disabled = false;
                icon.classList.remove('fa-spin');
                loader.style.display = 'none';
                grid.style.display = 'grid';
            }
        });
    </script>

    <!-- Инициализация интерактивного графика проходок -->
    <script>
        const ctxChart = document.getElementById('reportsChart').getContext('2d');
        
        // Создаем градиент для плавной заливки под графиком
        const gradient = ctxChart.createLinearGradient(0, 0, 0, 200);
        gradient.addColorStop(0, 'rgba(167, 139, 250, 0.4)');
        gradient.addColorStop(1, 'rgba(167, 139, 250, 0)');

        new Chart(ctxChart, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Забитые проходные',
                    data: <?php echo json_encode($chartData); ?>,
                    borderColor: '#a78bfa',
                    borderWidth: 3,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4, // Плавный изгиб кривой
                    pointBackgroundColor: '#8b5cf6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#fff',
                        bodyColor: '#e2e8f0',
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return `Проходок: ${context.parsed.y}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.03)'
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                family: 'Inter',
                                size: 11
                            }
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.03)'
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                family: 'Inter',
                                size: 11
                            },
                            stepSize: 1,
                            precision: 0
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
