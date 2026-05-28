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

// Проверка роли (админ, гл. куратор или куратор)
if ($role !== 'admin' && $role !== 'chief' && $role !== 'curator') {
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            <header class="header">
                <div class="header-title">
                    <h1>Переаттестация</h1>
                    <p>Управление очередью и проверка знаний</p>
                </div>
                <div class="header-actions">
                    <a href="logout.php" class="btn-logout-premium"><i class="fas fa-sign-out-alt"></i> Выйти</a>
                </div>
            </header>

            <div class="page-body">
            <section class="content">
                <!-- КАРТОЧКИ СТАТИСТИКИ -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="card" style="margin-bottom: 0; padding: 1.5rem; display: flex; align-items: center; gap: 1.2rem;">
                        <div style="width: 50px; height: 50px; background: rgba(99, 102, 241, 0.1); color: var(--accent); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                            <i class="fas fa-list-ul"></i>
                        </div>
                        <div>
                            <div id="stat-total" style="font-size: 1.5rem; font-weight: 800; color: #fff;">0</div>
                            <div style="color: #94a3b8; font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Всего назначено</div>
                        </div>
                    </div>
                    <div class="card" style="margin-bottom: 0; padding: 1.5rem; display: flex; align-items: center; gap: 1.2rem;">
                        <div style="width: 50px; height: 50px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div>
                            <div id="stat-overdue" style="font-size: 1.5rem; font-weight: 800; color: #ef4444;">0</div>
                            <div style="color: #94a3b8; font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Просрочено</div>
                        </div>
                    </div>
                </div>

                <div class="reattestation-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                        <h2 style="margin: 0; font-size: 1.1rem; letter-spacing: 0.5px;">Список саппортов</h2>
                        <div style="display: flex; align-items: center; gap: 0.6rem; flex: 1; justify-content: flex-end; min-width: 220px;">
                            <div style="position: relative; flex: 1; max-width: 320px;">
                                <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #64748B; font-size: 0.8rem;"></i>
                                <input type="text" id="searchInput" placeholder="Поиск по ID или нику..." autocomplete="off"
                                    oninput="applySearch()"
                                    style="width: 100%; padding: 0.6rem 0.8rem 0.6rem 2.2rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.6); color: #F8FAFC; font-size: 0.85rem; outline: none;">
                            </div>
                            <button onclick="loadQueue()" class="refresh-btn" title="Обновить список">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                            </button>
                        </div>
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
            </div>
        </main>
    </div>

    <script>
        function parseRuDate(str) {
            if (!str || str === '-' || str === '—') return null;
            const parts = str.split('.');
            if (parts.length !== 3) return null;
            return new Date(parts[2], parts[1] - 1, parts[0]);
        }

        const userRole = "<?php echo $_SESSION['role']; ?>";
        // Нормализация ника (нижний регистр + удаление подчеркиваний) для надежного сравнения
        const normalize = (str) => (str || "").toLowerCase().replace(/_/g, '').trim();
        const userNameNormalized = normalize("<?php echo $_SESSION['username']; ?>");

        // Здесь храним список, отфильтрованный по роли (без учёта строки поиска)
        let currentQueue = [];

        function isOverdue(item) {
            const targetDate = parseRuDate(item.date);
            if (!targetDate) return false;
            const now = new Date();
            now.setHours(0, 0, 0, 0);
            return (targetDate - now) < 0;
        }

        function updateStats() {
            document.getElementById('stat-total').innerText = currentQueue.length;
            document.getElementById('stat-overdue').innerText = currentQueue.filter(isOverdue).length;
        }

        function renderQueue(items) {
            const list = document.getElementById('reattestation-list');
            const now = new Date();
            now.setHours(0, 0, 0, 0);

            list.innerHTML = items.map(item => {
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

            if (items.length === 0) {
                const q = (document.getElementById('searchInput').value || '').trim();
                const msg = q
                    ? `Ничего не найдено по запросу «${q}»`
                    : 'Для вас нет назначенных переаттестаций';
                list.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 3rem; color: #64748B;">${msg}</td></tr>`;
            }
        }

        function applySearch() {
            const q = (document.getElementById('searchInput').value || '').toLowerCase().trim();
            if (!q) {
                renderQueue(currentQueue);
                return;
            }
            const filtered = currentQueue.filter(item =>
                String(item.id).toLowerCase().includes(q) ||
                String(item.nickname).toLowerCase().includes(q)
            );
            renderQueue(filtered);
        }

        function loadQueue() {
            const list = document.getElementById('reattestation-list');
            list.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 3rem;">Загрузка данных...</td></tr>';

            fetch('api.php?action=reattestation_queue&t=' + Date.now())
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        // ФИЛЬТРАЦИЯ: Админ и Гл. куратор видят всех, кураторы - только своих
                        let filteredData = res.data;
                        if (userRole !== 'admin' && userRole !== 'chief') {
                            filteredData = res.data.filter(item => {
                                return normalize(item.curator) === userNameNormalized;
                            });
                        }

                        currentQueue = filteredData;
                        updateStats();   // счётчики — по всему списку
                        applySearch();   // таблица — с учётом строки поиска
                    }
                });
        }
        document.addEventListener('DOMContentLoaded', loadQueue);
    </script>
</body>
</html>