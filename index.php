<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'user_header.php';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления персоналом</title>
    <link rel="stylesheet" href="index.css">
    <link rel="icon" type="image/png" href="favicon_futurama_staff_1776084855108.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .shift-badge {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
            color: #A5B4FC;
            padding: 0.15rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
            border: 1px solid rgba(99, 102, 241, 0.3);
            text-transform: uppercase;
        }
        .member-nick {
            color: #E2E8F0;
            font-weight: 500;
        }
        .management-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .management-item {
            background: rgba(255, 255, 255, 0.02);
            padding: 0.8rem 1rem;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
        }
        .management-label {
            font-weight: 700;
            color: #94A3B8;
            min-width: 170px;
            font-size: 0.9rem;
        }
        .management-values {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            flex: 1;
        }
        .member-wrapper {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.05);
            padding: 0.3rem 0.6rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            border: 1px solid rgba(255, 255, 255, 0.03);
        }
        .member-wrapper:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(99, 102, 241, 0.4);
        }
    </style>
</head>

<body>
    <!-- Mobile burger button -->
    <button class="burger-btn" id="burgerBtn" aria-label="Меню">
        <span></span><span></span><span></span>
    </button>
    <!-- Sidebar overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header glass">
                <h1>Обзор панели</h1>
                <div class="user-profile">
                    <div class="avatar-container" style="display: flex; align-items: center; gap: 0.75rem;">
                        <img src="<?= htmlspecialchars($avatar_url) ?>" alt="avatar"
                            style="width: 36px; height: 36px; border-radius: 50%; border: 2px solid rgba(167, 139, 250, 0.5); object-fit: cover;">
                        <span class="username"
                            style="font-weight: 500; color: #A78BFA;"><?= htmlspecialchars($username) ?> <span style="font-size: 0.75rem; color: #94A3B8; font-weight: 400; margin-left: 5px;">(<?= htmlspecialchars($role_display) ?>)</span></span>
                        <a href="logout.php" class="btn btn-primary"
                            style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: rgba(239, 68, 68, 0.2); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.4);">Выйти</a>
                    </div>
                </div>
            </header>

            <section class="content">


                <!-- Top Management Card -->
                <div class="card glass">
                    <div class="card-header">
                        <h3>Состав Вышки</h3>
                        <span class="status warning">Загрузка...</span>
                    </div>
                    <div class="card-body" id="management-container">
                        <p style="color: #94A3B8;">Синхронизация с таблицей...</p>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Script to load Google Sheets Data -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            fetch('api.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const m = data.management;
                        const container = document.getElementById('management-container');
                        
                        const renderBlock = (label, members) => {
                            if (!members || members.length === 0) return '';
                            const html = members.map(member => `
                                <div class="member-wrapper">
                                    <span class="member-nick">${member.nick}</span>
                                    ${member.shift ? `<span class="shift-badge">${member.shift}</span>` : ''}
                                </div>
                            `).join('');
                            
                            return `
                                <div class="management-item">
                                    <div class="management-label">${label}:</div>
                                    <div class="management-values">${html}</div>
                                </div>
                            `;
                        };

                        container.innerHTML = `
                            <div class="management-list">
                                ${renderBlock('Администратор', m.admin)}
                                ${renderBlock('Главный куратор', m.chief)}
                                ${renderBlock('Кураторы', m.curators)}
                                ${renderBlock('Мастера кураторов', m.masters)}
                            </div>
                        `;

                        const status = document.querySelector('.status');
                        status.textContent = 'Синхронизировано';
                        status.className = 'status';
                        status.style.background = 'rgba(16, 185, 129, 0.15)';
                        status.style.color = '#10B981';
                        status.style.border = '1px solid rgba(16, 185, 129, 0.3)';

                    } else {
                        console.error('Ошибка API:', data.error);
                    }
                })
                .catch(err => {
                    console.error('Ошибка при запросе:', err);
                });
        });
    </script>
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
</body>

</html>