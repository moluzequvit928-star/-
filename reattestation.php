<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'user_header.php';
$username = $_SESSION['username'] ?? 'Гость';
$role = $_SESSION['role'] ?? 'master';
$avatar_url = $_SESSION['avatar_url'] ?? 'https://cdn.discordapp.com/embed/avatars/0.png';

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
    <link rel="icon" type="image/png" href="favicon_futurama_staff_1776084855108.png">
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
        .custom-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .custom-table th {
            padding: 1rem; text-align: left; color: #94A3B8; font-size: 0.75rem;
            text-transform: uppercase; letter-spacing: 1.5px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.05);
        }
        .custom-table td { padding: 1.2rem 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.05); color: #F8FAFC; }
        .custom-table tr:hover { background: rgba(255, 255, 255, 0.02); }
        
        .date-badge { padding: 0.3rem 0.6rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; }
        .date-overdue { background: rgba(239, 68, 68, 0.15); color: #F87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .date-today { background: rgba(245, 158, 11, 0.15); color: #FBBF24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .date-upcoming { background: rgba(16, 185, 129, 0.15); color: #34D399; border: 1px solid rgba(16, 185, 129, 0.3); }

        .refresh-btn {
            background: rgba(167, 139, 250, 0.1);
            border: 1px solid rgba(167, 139, 250, 0.3);
            color: #A78BFA;
            width: 38px; height: 38px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: 0.2s;
        }
        .refresh-btn:hover { background: rgba(167, 139, 250, 0.2); transform: rotate(45deg); }
        
        .badge-curator { background: rgba(167, 139, 250, 0.1); color: #A78BFA; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; }
        .btn-start { background: #6366F1; color: white; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-block; }
        .btn-start:hover { background: #4F46E5; }
    </style>
</head>
<body>
    <button class="burger-btn" id="burgerBtn"><span></span><span></span><span></span></button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>
        <main class="main-content">
            <header class="header glass">
                <div class="header-titles">
                    <h1>Переаттестация</h1>
                    <p style="color: #94A3B8; font-size: 0.85rem;">Очередь мастеров на проверку знаний: <span id="total-count" style="color: #A78BFA;">0</span></p>
                </div>
                <div class="user-profile">
                    <img src="<?= htmlspecialchars($avatar_url) ?>" style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid #A78BFA44;">
                    <span style="margin-left: 8px; font-weight: 500; color: #A78BFA;"><?= htmlspecialchars($username) ?></span>
                </div>
            </header>

            <section class="content">
                <div class="reattestation-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h2 style="margin: 0; font-size: 1.1rem; letter-spacing: 0.5px;">Список саппортов</h2>
                        <button onclick="loadQueue()" class="refresh-btn" title="Обновить список">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Саппорт</th>
                                    <th>Дата проведения</th>
                                    <th>Попытка</th>
                                    <th>Куратор</th>
                                    <th style="text-align: right;">Действие</th>
                                </tr>
                            </thead>
                            <tbody id="reattestation-list"></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        function parseRuDate(str) {
            if (!str || str === '-' || str === '—') return null;
            const parts = str.split('.');
            if (parts.length !== 3) return null;
            return new Date(parts[2], parts[1] - 1, parts[0]);
        }

        function loadQueue() {
            const list = document.getElementById('reattestation-list');
            list.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 3rem;">Загрузка данных...</td></tr>';
            
            fetch('api.php?action=reattestation_queue&t=' + Date.now())
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        document.getElementById('total-count').innerText = res.data.length;
                        if (res.data.length === 0) {
                            list.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 3rem; color: #64748B;">Список пуст</td></tr>';
                            return;
                        }

                        const now = new Date();
                        now.setHours(0,0,0,0);

                        list.innerHTML = res.data.map(item => {
                            let dateHtml = '<span style="color: #64748B;">—</span>';
                            const targetDate = parseRuDate(item.date);
                            
                            if (targetDate) {
                                const diffTime = targetDate - now;
                                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                                
                                if (diffDays < 0) {
                                    dateHtml = `<div class="date-badge date-overdue">Просрочено ${Math.abs(diffDays)} д.</div>`;
                                } else if (diffDays === 0) {
                                    dateHtml = `<div class="date-badge date-today">Сегодня</div>`;
                                } else {
                                    dateHtml = `<div class="date-badge date-upcoming">Через ${diffDays} д.</div>`;
                                }
                                dateHtml += `<div style="font-size: 0.7rem; color: #64748B; margin-top: 4px;">План: ${item.date}</div>`;
                            }

                            return `
                                <tr>
                                    <td>
                                        <div style="font-weight: 700;">${item.nickname}</div>
                                        <div style="font-size: 0.7rem; color: #64748B;">ID: ${item.id}</div>
                                    </td>
                                    <td>${dateHtml}</td>
                                    <td><span style="font-weight: 800; color: ${item.attempt_count.startsWith('1') ? '#10B981' : item.attempt_count.startsWith('2') ? '#F59E0B' : '#EF4444'}">${item.attempt_count}</span></td>
                                    <td><span class="badge-curator">${item.curator}</span></td>
                                    <td style="text-align: right;"><a href="conduct.php?id=${item.id}&nick=${encodeURIComponent(item.nickname)}" class="btn-start">Начать проверку</a></td>
                                </tr>`;
                        }).join('');
                    }
                });
        }
        document.addEventListener('DOMContentLoaded', loadQueue);
    </script>
</body>
</html>