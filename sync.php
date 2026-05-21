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
    <title>FUTURAMA STAFF | Сверка таблиц</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Outfit:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sync-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .sync-hero {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(168, 85, 247, 0.1) 100%);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 3rem;
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        .sync-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.05) 0%, transparent 70%);
            animation: pulse 10s infinite alternate;
        }
        @keyframes pulse {
            from { transform: scale(1); opacity: 0.3; }
            to { transform: scale(1.2); opacity: 0.6; }
        }
        .btn-sync-large {
            background: var(--accent);
            color: white;
            border: none;
            padding: 1.2rem 3rem;
            border-radius: 16px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4);
            position: relative;
            z-index: 2;
        }
        .btn-sync-large:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px -5px rgba(99, 102, 241, 0.6);
            filter: brightness(1.1);
        }
        .btn-sync-large:active { transform: translateY(0); }
        .btn-sync-large:disabled {
            background: #4b5563;
            cursor: not-allowed;
            box-shadow: none;
        }
        .results-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-top: 2rem;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s ease;
        }
        .results-grid.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .result-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem;
            height: fit-content;
        }
        .result-card.danger { border-top: 4px solid #ef4444; }
        .result-card.warning { border-top: 4px solid #fbbf24; }
        .result-card.warning-orange { border-top: 4px solid #f97316; }
        .result-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        .result-title {
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .badge-count {
            background: rgba(255,255,255,0.05);
            padding: 4px 12px;
            border-radius: 10px;
            font-size: 0.9rem;
        }
        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            background: rgba(255,255,255,0.02);
            border-radius: 12px;
            margin-bottom: 8px;
            transition: background 0.2s;
        }
        .user-item:hover { background: rgba(255,255,255,0.05); }
        .user-id {
            font-family: 'Roboto Mono', monospace;
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-left: auto;
        }
        .loader-container {
            display: none;
            margin-top: 2rem;
        }
        .sync-progress {
            width: 100%;
            height: 6px;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .progress-bar {
            width: 0%;
            height: 100%;
            background: var(--accent);
            transition: width 0.3s;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Сверка таблиц</h1>
                    <p>Синхронизация Google Таблицы и Discord сервера</p>
                </div>
            </header>

            <div class="page-body">
                <div class="sync-container">
                    <div class="sync-hero">
                        <i class="fas fa-sync-alt" style="font-size: 4rem; color: var(--accent); margin-bottom: 1.5rem; display: block;"></i>
                        <h2 style="font-size: 2rem; margin-bottom: 1rem;">Запустить аудит персонала</h2>
                        <p style="color: var(--text-secondary); max-width: 500px; margin: 0 auto 2rem;">
                            Система проверит список из Google Таблицы и сравнит его с текущим списком участников в Discord.
                        </p>
                        <button id="startSync" class="btn-sync-large">
                            <i class="fas fa-play"></i> Начать проверку
                        </button>
                        <p style="margin-top: 1rem; font-size: 0.8rem; color: rgba(255,255,255,0.3); letter-spacing: 0.5px;">
                            <i class="fas fa-info-circle"></i> Система автоматически зафиксирует изменения в статистике<br>
                            <span style="opacity: 0.7; margin-top: 5px; display: inline-block;">Ожидание может занять до 5 минут</span>
                        </p>

                        <div class="loader-container" id="loader">
                            <div class="sync-progress">
                                <div class="progress-bar" id="progressBar"></div>
                            </div>
                            <p id="loaderStatus" style="font-size: 0.9rem; color: var(--text-secondary);">Подключение к Discord API...</p>
                        </div>
                    </div>

                    <script>
                        document.getElementById('startSync').addEventListener('click', async function() {
                            const btn = this;
                            const loader = document.getElementById('loader');
                            const progress = document.getElementById('progressBar');
                            const status = document.getElementById('loaderStatus');
                            const grid = document.getElementById('resultsGrid');
                            
                            btn.disabled = true;
                            loader.style.display = 'block';
                            grid.classList.remove('visible');
                            
                            // Имитация прогресса
                            let p = 0;
                            const interval = setInterval(() => {
                                p += Math.random() * 5;
                                if (p > 90) p = 90;
                                progress.style.width = p + '%';
                                if (p > 30) status.textContent = 'Скачивание Google Таблицы...';
                                if (p > 60) status.textContent = 'Анализ ролей Discord...';
                            }, 300);

                            try {
                                const response = await fetch('run_sync.php');
                                const data = await response.json();
                                
                                clearInterval(interval);
                                progress.style.width = '100%';
                                status.textContent = 'Готово!';

                                if (data.success) {
                                    renderResults(data);
                                    setTimeout(() => {
                                        grid.classList.add('visible');
                                        loader.style.display = 'none';
                                        btn.disabled = false;
                                    }, 500);
                                } else {
                                    alert('Ошибка: ' + data.error);
                                    btn.disabled = false;
                                }
                            } catch (e) {
                                clearInterval(interval);
                                console.error(e);
                                // Попробуем прочитать текст ошибки, если запрос вообще прошел
                                alert('Произошла критическая ошибка при запросе. Проверьте консоль браузера или логи Railway.');
                                btn.disabled = false;
                            }
                        });
                    </script>

                    <div class="results-grid" id="resultsGrid">
                        <!-- Дубликаты ID (Таблица) -->
                        <div class="result-card warning-orange" id="duplicateCard" style="grid-column: span 2; display: none;">
                            <div class="result-header">
                                <div class="result-title" style="color: #f97316;">
                                    <i class="fas fa-clone"></i> Дубликаты ID в таблице
                                </div>
                                <span class="badge-count" id="duplicateCount">0</span>
                            </div>
                            <div id="duplicateList" class="user-list">
                                <!-- JS items here -->
                            </div>
                        </div>

                        <!-- Лишние (Discord) -->
                        <div class="result-card danger">
                            <div class="result-header">
                                <div class="result-title" style="color: #ef4444;">
                                    <i class="fas fa-exclamation-triangle"></i> Нет в гугл таблице
                                </div>
                                <span class="badge-count" id="extraCount">0</span>
                            </div>
                            <div id="extraList" class="user-list">
                                <!-- JS items here -->
                            </div>
                        </div>

                        <!-- Нет роли (Таблица) -->
                        <div class="result-card warning">
                            <div class="result-header">
                                <div class="result-title" style="color: #fbbf24;">
                                    <i class="fas fa-user-minus"></i> Убрать из гугл таблицы
                                </div>
                                <span class="badge-count" id="missingCount">0</span>
                            </div>
                            <div id="missingList" class="user-list">
                                <!-- JS items here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function renderResults(data) {
            const extraList = document.getElementById('extraList');
            const missingList = document.getElementById('missingList');
            const duplicateList = document.getElementById('duplicateList');
            const duplicateCard = document.getElementById('duplicateCard');
            
            document.getElementById('extraCount').textContent = data.extra.length;
            document.getElementById('missingCount').textContent = data.missing.length;
            document.getElementById('duplicateCount').textContent = data.duplicates ? data.duplicates.length : 0;

            extraList.innerHTML = data.extra.length ? data.extra.map(item => `
                <div class="user-item">
                    <i class="fab fa-discord" style="color: #6366f1;"></i>
                    <span>${item.split(' (')[0]}</span>
                    <span class="user-id">${item.split(' (')[1]?.replace(')', '') || ''}</span>
                </div>
            `).join('') : '<p style="text-align: center; color: var(--text-secondary); padding: 1rem;">Лишних нет ✅</p>';

            missingList.innerHTML = data.missing.length ? data.missing.map(item => `
                <div class="user-item">
                    <i class="fas fa-file-excel" style="color: #fbbf24;"></i>
                    <span>${item.split(' (')[0]}</span>
                    <span class="user-id">${item.split(' (')[1]?.replace(')', '') || ''}</span>
                </div>
            `).join('') : '<p style="text-align: center; color: var(--text-secondary); padding: 1rem;">Все в порядке ✅</p>';

            if (data.duplicates && data.duplicates.length) {
                duplicateList.innerHTML = data.duplicates.map(item => {
                    const idMatch = item.match(/ID (\d+)/);
                    const idPart = idMatch ? idMatch[1] : '';
                    const rowsMatch = item.match(/\((.*)\)/);
                    const rowsPart = rowsMatch ? rowsMatch[1] : '';
                    
                    return `
                        <div class="user-item" style="border-left: 3px solid #f97316; padding-left: 10px;">
                            <i class="fas fa-clone" style="color: #f97316;"></i>
                            <div style="display: flex; flex-direction: column; gap: 2px;">
                                <span style="font-weight: 600;">ID: ${idPart}</span>
                                <span style="font-size: 0.85rem; color: var(--text-secondary);">${rowsPart}</span>
                            </div>
                        </div>
                    `;
                }).join('');
                duplicateCard.style.display = 'block';
            } else {
                duplicateList.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 1rem;">Дубликатов нет ✅</p>';
                duplicateCard.style.display = 'block';
            }
        }
    </script>
</body>
</html>
