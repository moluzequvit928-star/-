<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'user_header.php';
$username = $_SESSION['username'] ?? 'Гость';
$role = $_SESSION['role'] ?? 'master';
$avatar_url = $_SESSION['avatar_url'] ?? 'https://cdn.discordapp.com/embed/avatars/0.png';

// Доступ к этой странице только для админов и кураторов
if ($role !== 'admin' && $role !== 'curator') {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Очередь переаттестации</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .q-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
        }
        .q-item {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }
        .q-item:hover {
            border-color: rgba(167, 139, 250, 0.3);
            background: rgba(255, 255, 255, 0.04);
            transform: translateX(5px);
        }
        .q-info {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .q-nick {
            font-size: 1.1rem;
            font-weight: 700;
            color: #F8FAFC;
        }
        .q-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.85rem;
            color: #94A3B8;
        }
        .q-divider {
            width: 1px;
            height: 12px;
            background: rgba(255, 255, 255, 0.1);
        }
        .q-badge {
            background: rgba(167, 139, 250, 0.1);
            color: #A78BFA;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .attempt-tag {
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-size: 0.85rem;
        }
    </style>
</head>

<body>
    <button class="burger-btn" id="burgerBtn" aria-label="Меню">
        <span></span><span></span><span></span>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <main class="main-content">
            <header class="header glass">
                <div class="header-titles">
                    <h1>Переаттестация</h1>
                    <p style="color: #94A3B8; font-size: 0.9rem; margin-top: 0.25rem;">Очередь мастеров на проверку знаний</p>
                </div>
                <div class="user-profile">
                    <div class="avatar-container" style="display: flex; align-items: center; gap: 0.75rem;">
                        <img src="<?= htmlspecialchars($avatar_url) ?>" alt="avatar"
                            style="width: 36px; height: 36px; border-radius: 50%; border: 2px solid rgba(167, 139, 250, 0.5); object-fit: cover;">
                        <span class="username" style="font-weight: 500; color: #A78BFA;"><?= htmlspecialchars($username) ?></span>
                    </div>
                </div>
            </header>

            <section class="content">
                <div class="q-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h2 style="margin: 0; font-size: 1.25rem;">Очередь на переаттестацию</h2>
                        <button onclick="loadQueue()" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">🔄 Обновить</button>
                    </div>

                    <div id="queue-container">
                        <!-- Сюда грузится список -->
                        <div style="text-align: center; padding: 3rem; color: #94A3B8;">Загрузка списка...</div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        function loadQueue() {
            const container = document.getElementById('queue-container');
            
            fetch('api.php?action=reattestation_queue&t=' + Date.now())
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        if (res.data.length === 0) {
                            container.innerHTML = `
                                <div style="text-align: center; padding: 4rem; background: rgba(0,0,0,0.2); border-radius: 12px; border: 1px dashed rgba(255,255,255,0.1);">
                                    <div style="font-size: 2.5rem; margin-bottom: 1rem;">☕</div>
                                    <div style="color: #94A3B8; font-size: 1.1rem;">Очередь пуста</div>
                                    <div style="color: #64748B; font-size: 0.85rem; margin-top: 0.5rem;">Все мастера либо уже сдали, либо закончились попытки.</div>
                                </div>`;
                            return;
                        }

                        container.innerHTML = res.data.map(item => {
                            const attemptVal = parseInt(item.attempt_count);
                            const attemptColor = attemptVal === 1 ? 'rgba(16, 185, 129, 0.15)' : attemptVal === 2 ? 'rgba(245, 158, 11, 0.15)' : 'rgba(239, 68, 68, 0.15)';
                            const attemptTextCol = attemptVal === 1 ? '#10B981' : attemptVal === 2 ? '#F59E0B' : '#EF4444';

                            return `
                                <div class="q-item">
                                    <div class="q-info">
                                        <div class="q-nick">${item.nickname}</div>
                                        <div class="q-meta">
                                            <span>ID: <span style="color: #E2E8F0; font-family: monospace;">${item.id}</span></span>
                                            <span class="q-divider">|</span>
                                            <span>Попытка: <span class="attempt-tag" style="background: ${attemptColor}; color: ${attemptTextCol}">${item.attempt_count}</span></span>
                                            <span class="q-divider">|</span>
                                            <span>Куратор: <span class="q-badge">${item.curator}</span></span>
                                        </div>
                                    </div>
                                    <a href="conduct.php?id=${item.id}&nick=${encodeURIComponent(item.nickname)}" class="btn btn-primary">Начать проверку</a>
                                </div>
                            `;
                        }).join('');
                    } else {
                        container.innerHTML = `<div style="color: #EF4444; padding: 2rem;">Ошибка: ${res.error}</div>`;
                    }
                })
                .catch(err => {
                    container.innerHTML = `<div style="color: #EF4444; padding: 2rem;">Сетевая ошибка при загрузке очереди.</div>`;
                });
        }

        // Сайдбар меню
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

        // Инициализация
        document.addEventListener('DOMContentLoaded', loadQueue);
    </script>
</body>
</html>