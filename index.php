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
    <title>FUTURAMA STAFF | Главная</title>
    <link rel="icon" type="image/png" href="favicon_futurama_staff_1776084855108.png">
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Состав вышки</h1>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">Добро пожаловать в систему управления персоналом.</p>
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

            <section class="content">
                <!-- Stats Cards Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <!-- Кол-во саппортов -->
                    <div class="card" style="margin-bottom: 0; padding: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="background: rgba(56, 189, 248, 0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #38bdf8;">
                                <i class="fas fa-users" style="font-size: 1.2rem;"></i>
                            </div>
                            <div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">Кол-во саппортов</div>
                                <div id="stat-support-count" style="font-weight: 700; font-size: 1.1rem; color: var(--text-primary);">...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Всего ЗП -->
                    <div class="card" style="margin-bottom: 0; padding: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="background: rgba(251, 191, 36, 0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fbbf24;">
                                <i class="fas fa-wallet" style="font-size: 1.2rem;"></i>
                            </div>
                            <div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">Всего ЗП</div>
                                <div id="stat-total-salary" style="font-weight: 700; font-size: 1.1rem; color: var(--text-primary);">...</div>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="margin-bottom: 0; padding: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="background: rgba(99, 102, 241, 0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--accent);">
                                <i class="fas fa-user-check" style="font-size: 1.2rem;"></i>
                            </div>
                            <div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">Ваша роль</div>
                                <div style="font-weight: 700; font-size: 1.1rem; color: var(--text-primary);"><?= htmlspecialchars($role_display) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="card" style="margin-bottom: 0; padding: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="background: rgba(16, 185, 129, 0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #10B981;">
                                <i class="fas fa-calendar-alt" style="font-size: 1.2rem;"></i>
                            </div>
                            <div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">Текущая дата</div>
                                <div style="font-weight: 700; font-size: 1.1rem; color: var(--text-primary);"><?= date('d.m.Y') ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Management Card -->
                <div class="card">
                    <div class="card-header">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-crown" style="color: #fbbf24; font-size: 1.5rem;"></i>
                            <h3>Состав Администрации</h3>
                        </div>
                        <span class="status-badge" id="sync-status">Синхронизация...</span>
                    </div>
                    <div class="card-body" id="management-container">
                        <div style="display: flex; justify-content: center; padding: 3rem;">
                            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--accent);"></i>
                        </div>
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
                        
                        // Обновляем статистику
                        if (data.stats) {
                            document.getElementById('stat-support-count').textContent = data.stats.support_count || '0';
                            document.getElementById('stat-total-salary').textContent = data.stats.total_salary || '0';
                        }
                        
                        console.log('--- ОТЛАДКА СОСТАВА ---');
                        Object.keys(m).forEach(cat => {
                            m[cat].forEach(p => {
                                console.log(`Ник: ${p.nick}, ID: ${p.discord_id || 'НЕ НАЙДЕН'}`);
                            });
                        });

                        const renderBlock = (label, members, icon, categoryClass) => {
                            if (!members || members.length === 0) return '';
                            const html = members.map(member => `
                                <div class="staff-card" ${member.discord_id ? `data-discord-id="${member.discord_id}"` : ''}>
                                    <img class="staff-avatar" src="avatar.php?id=${member.discord_id || ''}&seed=${encodeURIComponent(member.nick)}" 
                                         alt="${member.nick}" 
                                         id="avatar-${member.nick.replace(/\s+/g, '-')}">
                                    <div class="staff-info">
                                        <div class="staff-name">${member.nick}</div>
                                        <div style="font-size: 0.9rem; color: var(--text-secondary); font-family: monospace; opacity: 0.8;">${member.discord_id || 'ID не указан'}</div>
                                        <div style="display: flex; gap: 8px; align-items: center; margin-top: 8px;">
                                            ${member.shift ? `<div class="staff-tag">${member.shift}</div>` : ''}
                                            ${member.discord_id ? `<a href="profile.php?id=${member.discord_id}" class="profile-mini-btn">Профиль</a>` : ''}
                                        </div>
                                    </div>
                                </div>
                            `).join('');
                            
                            return `
                                <div class="management-category ${categoryClass}">
                                    <div class="category-header">
                                        <i class="${icon}"></i>
                                        <span class="category-title">${label}</span>
                                    </div>
                                    <div class="members-grid">
                                        ${html}
                                    </div>
                                </div>
                            `;
                        };

                        container.innerHTML = `
                            <div class="management-list">
                                ${renderBlock('Администратор', m.admin, 'fas fa-crown', 'category-admin')}
                                ${renderBlock('Главный куратор', m.chief, 'fas fa-star', 'category-chief')}
                                ${renderBlock('Кураторы', m.curators, 'fas fa-shield-alt', 'category-curators')}
                                ${renderBlock('Мастера кураторов', m.masters, 'fas fa-user-graduate', 'category-masters')}
                            </div>
                        `;

                        const status = document.getElementById('sync-status');
                        status.textContent = 'Обновлено';
                        status.style.background = 'rgba(16, 185, 129, 0.1)';
                        status.style.color = '#10B981';

                    } else {
                        console.error('Ошибка API:', data.error);
                    }
                })
                .catch(err => {
                    console.error('Ошибка при запросе:', err);
                });
        });
    </script>
</body>
</html>