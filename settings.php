<?php
session_start();
require_once 'db.php';
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
    <title>Настройки | Панель</title>
    <link rel="icon" type="image/png" href="favicon_futurama_staff_1776084855108.png">
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .theme-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        .theme-card {
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            cursor: pointer;
            transition: 0.3s;
            text-align: center;
        }
        .theme-card:hover {
            border-color: var(--accent);
            transform: translateY(-5px);
        }
        .theme-card.active {
            border-color: var(--accent);
            background: rgba(99, 102, 241, 0.1);
        }
        .theme-preview {
            height: 100px;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>
    <button class="burger-btn" id="burgerBtn"><span></span><span></span><span></span></button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>
        
        <main class="main-content">
            <header class="header glass">
                <h1>Настройки интерфейса</h1>
            </header>

            <section class="content">
                <div class="card glass">
                    <div class="card-header">
                        <h3>🎨 Выбор темы оформления</h3>
                    </div>
                    <div class="card-body">
                        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Выберите наиболее комфортную тему для работы с панелью управления.</p>
                        
                        <div class="theme-grid">
                            <!-- Тема: Стандартная (Dark) -->
                            <div class="theme-card" id="theme-dark" onclick="setTheme('dark')">
                                <div class="theme-preview" style="background: #0B0F19; border: 4px solid #141B2D;"></div>
                                <span style="font-weight: 600;">Темно-синяя (Стандарт)</span>
                            </div>

                            <!-- Тема: Глубокий черный (Black) -->
                            <div class="theme-card" id="theme-black" onclick="setTheme('black')">
                                <div class="theme-preview" style="background: #000000; border: 4px solid #111111;"></div>
                                <span style="font-weight: 600;">Глубоко-черная</span>
                            </div>

                            <!-- Тема: Светлая (Light) -->
                            <div class="theme-card" id="theme-light" onclick="setTheme('light')">
                                <div class="theme-preview" style="background: #F1F5F9; border: 4px solid #FFFFFF;"></div>
                                <span style="font-weight: 600; color: #0F172A;">Светлая</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        function setTheme(theme) {
            // Применяем тему к HTML
            document.documentElement.setAttribute('data-theme', theme);
            // Сохраняем выбор
            localStorage.setItem('site_theme', theme);
            // Визуально обновляем карточки
            updateActiveCard(theme);
        }

        function updateActiveCard(theme) {
            document.querySelectorAll('.theme-card').forEach(card => card.classList.remove('active'));
            const activeCard = document.getElementById('theme-' + theme);
            if (activeCard) activeCard.classList.add('active');
        }

        // Инициализация активной карточки при загрузке
        document.addEventListener('DOMContentLoaded', () => {
            const currentTheme = localStorage.getItem('site_theme') || 'dark';
            updateActiveCard(currentTheme);
        });

        // Бургер меню
        const burgerBtn = document.getElementById('burgerBtn');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        burgerBtn.addEventListener('click', () => {
            burgerBtn.classList.toggle('open');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });
        overlay.addEventListener('click', () => {
            burgerBtn.classList.remove('open');
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    </script>
</body>
</html>
