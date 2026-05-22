<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Проверка прав (только админы, гл. кураторы и кураторы)
$allowed_roles = ['admin', 'chief', 'curator'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: index.php');
    exit;
}

require_once 'user_header.php';
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
                const res = await fetch('run_channels.php');
                const data = await res.json();

                clearInterval(progressInterval);

                if (!data.success) {
                    grid.innerHTML = `
                        <div style="grid-column: 1/-1; text-align: center; padding: 4rem 1rem; color: #f87171;">
                            <i class="fas fa-triangle-exclamation" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                            <span style="font-size: 1.1rem; display: block; margin-bottom: 0.5rem; font-weight: 700;">Произошла ошибка</span>
                            ${data.error || 'Не удалось получить данные от селф-бота.'}
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
                        usersHtml = ch.members.map(m => `
                            <div class="room-user-item" title="${m.tag}">
                                <img class="room-user-avatar" src="${m.avatar}" alt=""
                                     onerror="this.src='https://cdn.discordapp.com/embed/avatars/0.png'">
                                <span class="room-user-tag">${m.tag}</span>
                            </div>`).join('');
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
</body>
</html>
