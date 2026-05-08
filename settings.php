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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;700&family=Montserrat:wght@400;600;700&family=Roboto+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            <header class="header">
                <div class="header-title">
                    <h1>Настройки интерфейса</h1>
                    <p>Темы, шрифты и оформление</p>
                </div>
                <div class="header-actions">
                    <a href="logout.php" class="btn-logout-premium"><i class="fas fa-sign-out-alt"></i> Выйти</a>
                </div>
            </header>

            <div class="page-body">
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
                                <span style="font-weight: 600;">Темно-синяя</span>
                            </div>

                            <!-- Тема: Глубокий черный (Black) -->
                            <div class="theme-card" id="theme-black" onclick="setTheme('black')">
                                <div class="theme-preview" style="background: #000000; border: 4px solid #111111;"></div>
                                <span style="font-weight: 600;">Глубокая</span>
                            </div>

                            <!-- Тема: Алая (Red) -->
                            <div class="theme-card" id="theme-red" onclick="setTheme('red')">
                                <div class="theme-preview" style="background: #1a0505; border: 4px solid #ff0000;"></div>
                                <span style="font-weight: 600;">Красно-алая</span>
                            </div>

                            <!-- Тема: Светлая (Light) -->
                            <div class="theme-card" id="theme-light" onclick="setTheme('light')">
                                <div class="theme-preview" style="background: #F1F5F9; border: 4px solid #FFFFFF;"></div>
                                <span style="font-weight: 600;">Светлая</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card glass" style="margin-top: 2rem;">
                    <div class="card-header">
                        <h3>🔤 Настройка шрифтов</h3>
                    </div>
                    <div class="card-body">
                        <div class="theme-grid" id="font-grid">
                            <div class="theme-card" onclick="setFont('\'Inter\', sans-serif')" id="font-inter">
                                <div style="font-family: 'Inter', sans-serif; font-size: 1.5rem; margin-bottom: 0.5rem;">Aa</div>
                                <span>Inter (Стандарт)</span>
                            </div>
                            <div class="theme-card" onclick="setFont('\'Outfit\', sans-serif')" id="font-outfit">
                                <div style="font-family: 'Outfit', sans-serif; font-size: 1.5rem; margin-bottom: 0.5rem;">Aa</div>
                                <span>Outfit (Модерн)</span>
                            </div>
                            <div class="theme-card" onclick="setFont('\'Montserrat\', sans-serif')" id="font-montserrat">
                                <div style="font-family: 'Montserrat', sans-serif; font-size: 1.5rem; margin-bottom: 0.5rem;">Aa</div>
                                <span>Montserrat</span>
                            </div>
                            <div class="theme-card" onclick="setFont('\'Roboto Mono\', monospace')" id="font-roboto">
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 1.5rem; margin-bottom: 0.5rem;">Aa</div>
                                <span>Roboto Mono</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            </div>
        </main>
    </div>

    <script>
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('site_theme', theme);
            updateActiveCard(theme);
        }

        function setFont(font) {
            document.documentElement.style.setProperty('--font-family', font);
            localStorage.setItem('site_font', font);
            updateActiveFont(font);
        }

        function updateActiveCard(theme) {
            document.querySelectorAll('.theme-card').forEach(card => {
                if(card.id.startsWith('theme-')) card.classList.remove('active');
            });
            const activeCard = document.getElementById('theme-' + theme);
            if (activeCard) activeCard.classList.add('active');
        }

        function updateActiveFont(font) {
            document.querySelectorAll('#font-grid .theme-card').forEach(card => card.classList.remove('active'));
            if (font.includes('Inter')) document.getElementById('font-inter').classList.add('active');
            else if (font.includes('Outfit')) document.getElementById('font-outfit').classList.add('active');
            else if (font.includes('Montserrat')) document.getElementById('font-montserrat').classList.add('active');
            else if (font.includes('Roboto Mono')) document.getElementById('font-roboto').classList.add('active');
            else if (font.includes('Roboto')) document.getElementById('font-roboto').classList.add('active');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const currentTheme = localStorage.getItem('site_theme') || 'dark';
            updateActiveCard(currentTheme);
            
            const currentFont = localStorage.getItem('site_font') || "'Inter', sans-serif";
            updateActiveFont(currentFont);
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
