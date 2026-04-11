<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'user_header.php';

// Доступ к переаттестации только для админов и кураторов
$current_role = $_SESSION['role'] ?? 'master';
if ($current_role !== 'admin' && $current_role !== 'curator') {
    header('Location: index.php');
    exit;
}
$appConfig = @include __DIR__ . '/app_config.php';
if (!is_array($appConfig)) {
    $appConfig = [];
}

$message = '';
$messageType = '';
$isAdmin = (($_SESSION['role'] ?? '') === 'admin');

function appConfigValue(string $envName, string $configKey, string $default = ''): string
{
    global $appConfig;
    $env = getenv($envName);
    if ($env !== false && trim((string)$env) !== '') {
        return trim((string)$env);
    }
    $cfg = $appConfig[$configKey] ?? '';
    if (trim((string)$cfg) !== '') {
        return trim((string)$cfg);
    }
    return $default;
}

// Создаем таблицу для переаттестаций, если её нет
$pdo->exec("CREATE TABLE IF NOT EXISTS reattestations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    user_nickname VARCHAR(100) NOT NULL,
    master_name VARCHAR(100) NOT NULL,
    reattestation_type VARCHAR(50) NOT NULL,
    screenshot_path VARCHAR(255) NOT NULL,
    comment TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

try {
    $pdo->exec("ALTER TABLE reattestations ADD COLUMN conducting_curator VARCHAR(150) NULL");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE reattestations ADD COLUMN scheduled_date VARCHAR(100) NULL");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE reattestations ADD COLUMN sheet_sync_status VARCHAR(30) DEFAULT 'not_sent'");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE reattestations ADD COLUMN sheet_sync_message TEXT NULL");
} catch (PDOException $e) {}

?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Переаттестация | Панель</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            font-weight: 500;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }
        .form-input {
            padding: 0.8rem;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: white;
            outline: none;
            width: 100%;
            max-width: 400px;
        }
        .form-input:focus {
            border-color: #6366F1;
        }
        .form-select {
            padding: 0.8rem;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: white;
            outline: none;
            width: 100%;
            max-width: 400px;
            cursor: pointer;
        }
        .form-select option {
            background: #1E293B;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar glass">
            <div class="logo">
                <h2>Панель</h2>
            </div>
            <nav class="menu">
                <a href="index.php" class="menu-item">Главная</a>
                <?php if ($current_role === 'admin'): ?>
                    <a href="admin_stats.php" class="menu-item">Статистика</a>
                <?php endif; ?>
                <?php if ($current_role === 'master'): ?>
                    <a href="master_info.php" class="menu-item">Информация</a>
                <?php endif; ?>
                <a href="reattestation.php" class="menu-item active">Переаттестация</a>

                <div class="menu-section-title">Полезные ссылки</div>
                <a href="https://docs.google.com/spreadsheets/d/1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754/edit?gid=1970062457#gid=1970062457"
                    class="menu-item highlight" target="_blank">Google Таблица ↗</a>
                <a href="https://docs.google.com/document/d/1tef_iQ0GuuIVgQRI15Ql8H74BFPjEcI9Cg3qZCQrtL8/edit?tab=t.0"
                    class="menu-item highlight" target="_blank">Собес на саппорта ↗</a>

                <?php 
                // Данные для сайдбара уже в user_header.php
                if ($current_role === 'admin' || $current_role === 'curator'): 
                ?>
                    <a href="check_reports.php" class="menu-item" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Проверка отчетов</span>
                        <?php if ($sidebarPendingCount > 0): ?>
                            <span style="background: #EF4444; color: white; font-size: 0.75rem; font-weight: 700; padding: 0.1rem 0.45rem; border-radius: 999px; line-height: 1.6; min-width: 20px; text-align: center;"><?= $sidebarPendingCount ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <div class="menu-section-title">Работа</div>
                <a href="reports.php" class="menu-item">Отчеты по наборам</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header glass">
                <h1>Переаттестация</h1>
                <div class="user-profile">
                    <div class="avatar-container" style="display: flex; align-items: center; gap: 0.75rem;">
                        <img src="<?= htmlspecialchars($avatar_url) ?>" alt="avatar"
                            style="width: 36px; height: 36px; border-radius: 50%; border: 2px solid rgba(167, 139, 250, 0.5); object-fit: cover;">
                        <span class="username"
                            style="font-weight: 500; color: #A78BFA;"><?= htmlspecialchars($_SESSION['username']) ?> <span style="font-size: 0.75rem; color: #94A3B8; font-weight: 400; margin-left: 5px;">(<?= htmlspecialchars($role_display) ?>)</span></span>
                        <a href="logout.php" class="btn btn-primary"
                            style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: rgba(239, 68, 68, 0.2); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.4);">Выйти</a>
                    </div>
                </div>
            </header>

            <section class="content">
                <?php if ($message): ?>
                    <div class="card glass"
                        style="grid-column: 1 / -1; padding: 1rem; text-align: center; border-color: <?= $messageType === 'success' ? '#10B981' : '#EF4444' ?>;">
                        <strong
                            style="color: <?= $messageType === 'success' ? '#10B981' : '#EF4444' ?>;"><?= htmlspecialchars($message) ?></strong>
                    </div>
                <?php endif; ?>

                <div class="card glass" style="grid-column: 1 / -1;">
                    <div class="card-header">
                        <h3><?= $isAdmin ? 'Общая очередь (все кураторы)' : 'Саппорты на переаттестацию (мой список)' ?></h3>
                        <span class="status warning" id="queue-status">Загрузка...</span>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap;">
                            <button type="button" id="queue-refresh-btn" class="btn btn-primary"
                                style="padding: 0.45rem 0.8rem; font-size: 0.85rem;">Обновить список</button>
                            <!-- Фильтр: если админ, просим api дать всё (all) -->
                            <input type="hidden" id="queue-curator-input" value="<?= $isAdmin ? 'all' : htmlspecialchars($_SESSION['username'] ?? '') ?>">
                            <input type="checkbox" id="queue-show-all-curators" style="display:none;">
                        </div>
                        <ul class="data-list" id="queue-list">
                            <li>Загружаю данные из таблицы...</li>
                        </ul>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Модальное окно для картинок -->
    <div id="image-modal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(5px);">
        <span
            style="position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; font-weight: bold; cursor: pointer; transition: 0.2s;"
            onclick="closeModal()" onmouseover="this.style.color='#EF4444'"
            onmouseout="this.style.color='white'">&times;</span>
        <img id="modal-img" src=""
            style="max-width: 90%; max-height: 90%; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
    </div>

    <!-- Script to handle modal -->
    <script>
        function openModal(src) {
            document.getElementById('modal-img').src = src;
            document.getElementById('image-modal').style.display = 'flex';
        }
        function closeModal() {
            document.getElementById('image-modal').style.display = 'none';
        }
        document.getElementById('image-modal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeModal();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });
    </script>

    <!-- Script to handle file uploads and pasting -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const queueList = document.getElementById('queue-list');
            const queueStatus = document.getElementById('queue-status');
            const queueCuratorInput = document.getElementById('queue-curator-input');
            const queueRefreshBtn = document.getElementById('queue-refresh-btn');
            const queueShowAllCurators = document.getElementById('queue-show-all-curators');

            async function loadQueue() {
                const curator = (queueCuratorInput.value || '').trim();
                queueStatus.textContent = 'Загрузка...';
                queueStatus.style.color = '#F59E0B';
                queueList.innerHTML = '<li>Загружаю данные из Google Таблицы...</li>';
                queueRefreshBtn.disabled = true;

                try {
                    const response = await fetch(`api.php?action=reattestation_queue&curator=${encodeURIComponent(curator)}`);
                    const text = await response.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('Raw response:', text);
                        throw new Error('Сервер вернул некорректный ответ (проверьте консоль)');
                    }

                    if (!data.success) throw new Error(data.error || 'Ошибка загрузки списка');

                    if (!Array.isArray(data.items) || data.items.length === 0) {
                        queueList.innerHTML = '<li>Для этого куратора сейчас нет саппортов на переаттестацию 🎉</li>';
                    } else {
                        queueList.innerHTML = '';
                        data.items.forEach((item) => {
                            const li = document.createElement('li');
                            const nick = item.discord_nickname ? item.discord_nickname : 'Без никнейма';
                            const dateText = item.date ? item.date : 'дата не указана';
                            let badgeText = 'без даты';
                            let badgeColor = '#94A3B8';
                            if (item.date_state === 'overdue') {
                                badgeText = `просрочено на ${Math.abs(item.days_until_date || 0)} дн.`;
                                badgeColor = '#EF4444';
                            } else if (item.date_state === 'today') {
                                badgeText = 'сегодня';
                                badgeColor = '#F59E0B';
                            } else if (item.date_state === 'upcoming') {
                                badgeText = `через ${item.days_until_date} дн.`;
                                badgeColor = '#10B981';
                            }

                            li.innerHTML = `
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; width:100%;">
                                <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                                    <span style="color: #94A3B8; font-size: 0.85rem;">Встал: <span style="color: #E2E8F0;">${item.started_at || '...'}</span></span>
                                    <span class="q-divider">|</span>
                                    <span class="q-nick">${nick}</span>
                                    <span class="q-id">(${item.discord_id})</span>
                                    <span class="q-divider">|</span>
                                    <span class="q-date">Дата проведения: <span style="color: #E2E8F0;">${dateText || 'не указана'}</span></span>
                                    <span class="q-divider">|</span>
                                    <span class="q-curator">Проводящий: <span style="color:#A78BFA">${item.curator || '...'}</span></span>
                                    <span class="q-badge" style="background:transparent; color:${badgeColor}; border:1px solid ${badgeColor}44; padding:0.2rem 0.5rem; border-radius:4px;">${badgeText}</span>
                                </div>
                                <div style="display:flex; gap:0.45rem; align-items:center;">
                                    <a href="conduct.php?id=${item.discord_id}&nick=${encodeURIComponent(nick)}&curator=${encodeURIComponent(item.curator || '')}" 
                                       class="btn btn-primary" 
                                       style="padding:0.45rem 1rem; font-size:0.85rem; background:rgba(99,102,241,0.2); color:#818CF8; border-color:rgba(99,102,241,0.4); text-decoration:none;">
                                       Провести
                                    </a>
                                </div>
                            </div>`;
                            queueList.appendChild(li);
                        });
                    }

                    queueStatus.textContent = `Найдено: ${data.count}`;
                    queueStatus.style.color = '#10B981';
                } catch (error) {
                    queueStatus.textContent = 'Ошибка';
                    queueStatus.style.color = '#EF4444';
                    queueList.innerHTML = `<li style="color:#EF4444">Не удалось загрузить список: ${error.message}</li>`;
                } finally {
                    queueRefreshBtn.disabled = false;
                }
            }

            queueRefreshBtn.addEventListener('click', loadQueue);
            queueList.addEventListener('click', async (e) => {
                const passBtn = e.target.closest('.btn-result-pass');
                const failBtn = e.target.closest('.btn-result-fail');
                const btn = passBtn || failBtn;
                if (!btn) return;

                const result = passBtn ? 'сдал' : 'не сдал';
                const discordId = btn.dataset.id || '';
                const nick = btn.dataset.nick || '';
                const date = btn.dataset.date || '';
                const curator = btn.dataset.curator || (queueCuratorInput.value || '').trim();

                if (!discordId) return;
                if (!confirm(`Подтвердить результат "${result}" для ${nick} (${discordId})?`)) return;

                btn.disabled = true;
                try {
                    const body = new URLSearchParams();
                    body.set('discord_id', discordId);
                    body.set('discord_nickname', nick);
                    body.set('date', date);
                    body.set('curator', curator);
                    body.set('result', result);

                    const response = await fetch('api.php?action=set_reattestation_result', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                        body: body.toString()
                    });
                    const data = await response.json();
                    if (!data.success) {
                        throw new Error(data.error || 'Не удалось отправить результат');
                    }
                    queueStatus.textContent = `Результат "${result}" отправлен`;
                    queueStatus.style.color = '#10B981';
                    await loadQueue();
                } catch (error) {
                    queueStatus.textContent = `Ошибка отправки: ${error.message}`;
                    queueStatus.style.color = '#EF4444';
                } finally {
                    btn.disabled = false;
                }
            });
            loadQueue();
        });
    </script>
</body>

</html>