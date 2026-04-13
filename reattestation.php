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
        .reattestation-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .custom-table th {
            padding: 1rem;
            text-align: left;
            color: #94A3B8;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.05);
        }

        .custom-table td {
            padding: 1.2rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #F8FAFC;
        }

        .custom-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .badge-curator {
            background: rgba(167, 139, 250, 0.1);
            color: #A78BFA;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-size: 0.85rem;
        }

        .btn-start {
            background: #6366F1;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-start:hover {
            background: #4F46E5;
            transform: translateY(-1px);
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
                    <p style="color: #94A3B8; font-size: 0.9rem;">Очередь мастеров на проверку знаний</p>
                </div>
                <div class="user-profile">
                    <div class="avatar-container" style="display: flex; align-items: center; gap: 0.75rem;">
                        <img src="<?= htmlspecialchars($avatar_url) ?>" alt="avatar" style="width: 36px; height: 36px; border-radius: 50%; border: 2px solid rgba(167, 139, 250, 0.5);">
                        <span class="username" style="font-weight: 500; color: #A78BFA;"><?= htmlspecialchars($username) ?></span>
                    </div>
                </div>
            </header>

            <section class="content">
                <div class="reattestation-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                        <h2 style="margin: 0;">Очередь на переаттестацию</h2>
                        <button onclick="loadQueue()" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">🔄 Обновить</button>
                    </div>

                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Мастер</th>
                                <th>Куратор</th>
                                <th>Попытка</th>
                                <th>Действие</th>
                            </tr>
                        </thead>
                        <tbody id="reattestation-list">
                            <!-- Загрузка -->
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script>
        function loadQueue() {
            const list = document.getElementById('reattestation-list');
            list.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 2rem;">Загрузка списка...</td></tr>';
            
            fetch('api.php?action=reattestation_queue&t=' + Date.now())
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        if (res.data.length === 0) {
                            list.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 2rem; color: #94A3B8;">Очередь пуста</td></tr>';
                            return;
                        }
                        list.innerHTML = res.data.map(item => `
                            <tr>
                                <td>
                                    <div style="font-weight: 600;">${item.nickname}</div>
                                    <div style="font-size: 0.75rem; color: #64748B;">ID: ${item.id}</div>
                                </td>
                                <td><span class="badge-curator">${item.curator}</span></td>
                                <td><span style="font-weight: 700; color: ${item.attempt_count.startsWith('1') ? '#10B981' : item.attempt_count.startsWith('2') ? '#F59E0B' : '#EF4444'}">${item.attempt_count}</span></td>
                                <td>
                                    <a href="conduct.php?id=${item.id}&nick=${encodeURIComponent(item.nickname)}" class="btn-start">Начать проверку</a>
                                </td>
                            </tr>
                        `).join('');
                    } else {
                        list.innerHTML = `<tr><td colspan="4" style="color: #EF4444; padding: 2rem;">Ошибка: ${res.error}</td></tr>`;
                    }
                })
                .catch(err => {
                    list.innerHTML = `<tr><td colspan="4" style="color: #EF4444; padding: 2rem;">Сетевая ошибка</td></tr>`;
                });
        }

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

        document.addEventListener('DOMContentLoaded', loadQueue);
    </script>
</body>
</html>