<?php
session_start();
require_once 'db.php';

// Принудительное создание таблиц, если их нет (особенно важно для Railway)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sync_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        added_count INT DEFAULT 0,
        removed_count INT DEFAULT 0,
        sheet_total INT DEFAULT 0,
        discord_total INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS supports_current (
        discord_id VARCHAR(50) PRIMARY KEY,
        username VARCHAR(100) DEFAULT NULL,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Очистка от битых записей (в блоке try, чтобы не ронять страницу)
    $pdo->exec("DELETE FROM sync_stats WHERE discord_total = 0");
} catch (Exception $e) {
    // Если таблицы еще нет или ошибка прав - просто идем дальше
}

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Проверка прав (только админы, гл. кураторы и кураторы)
$allowed_roles = ['admin', 'chief', 'curator'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    die("У вас нет прав для просмотра этой страницы.");
}

require_once 'user_header.php';

$period = $_GET['period'] ?? 'month';
$days_limit = 30;
$date_format = "%d.%m";
$start_date = "DATE_SUB(NOW(), INTERVAL 30 DAY)";

if ($period === 'day') {
    $days_limit = 1;
    $start_date = "DATE_SUB(NOW(), INTERVAL 1 DAY)"; // Последние 24 часа
    $date_format = "%H:00";
} elseif ($period === 'week') {
    $days_limit = 7;
    $start_date = "DATE_SUB(NOW(), INTERVAL 7 DAY)";
}

// Получаем данные для графика (Исключаем аномальный первый запуск с 136+ новичками для корректности)
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(created_at, ?) as label, 
        SUM(IF(added_count > 100, 0, added_count)) as total_added, 
        SUM(removed_count) as total_removed,
        MAX(discord_total) as discord_total
    FROM sync_stats 
    WHERE created_at >= $start_date
    GROUP BY label 
    ORDER BY MIN(created_at) ASC
");
$stmt->execute([$date_format]);
$stats = $stmt->fetchAll();

$labels = [];
$added = [];
$removed = [];
$c_total = [];

foreach ($stats as $row) {
    $labels[] = $row['label'];
    $added[] = $row['total_added'];
    $removed[] = $row['total_removed'];
    $c_total[] = $row['discord_total'];
}

// Итого за выбранный период (фильтруем аномалию первого запуска)
$stmt_total = $pdo->prepare("SELECT SUM(IF(added_count > 100, 0, added_count)) as added, SUM(removed_count) as removed FROM sync_stats WHERE created_at >= $start_date");
$stmt_total->execute();
$totals = $stmt_total->fetch();

// ТЕКУЩЕЕ количество (самая последняя запись в БД)
$stmt_current = $pdo->query("SELECT discord_total FROM sync_stats ORDER BY id DESC LIMIT 1");
$current_count = $stmt_current->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика синхронизации | FUTURAMA STAFF</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Outfit:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .chart-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.3);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.2rem;
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }
        .stat-info .label {
            display: block;
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        .stat-info .value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .stats-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 2rem;
            background: rgba(255,255,255,0.02);
            padding: 5px;
            border-radius: 12px;
            width: fit-content;
            border: 1px solid var(--border-color);
        }
        .tab-link {
            padding: 8px 20px;
            border-radius: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .tab-link:hover {
            color: var(--text-primary);
            background: rgba(255,255,255,0.05);
        }
        .tab-link.active {
            background: var(--accent);
            color: white;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Статистика</h1>
                    <p>История изменений персонала в Discord</p>
                </div>
            </header>

            <div class="page-body">
                <div class="stats-container">
                    
                    <div class="stats-tabs">
                        <a href="?period=day" class="tab-link <?= $period === 'day' ? 'active' : '' ?>">День</a>
                        <a href="?period=week" class="tab-link <?= $period === 'week' ? 'active' : '' ?>">Неделя</a>
                        <a href="?period=month" class="tab-link <?= $period === 'month' ? 'active' : '' ?>">Месяц</a>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-icon" style="background: rgba(167, 139, 250, 0.1); color: #A78BFA;">
                                <i class="fab fa-discord"></i>
                            </div>
                            <div class="stat-info">
                                <span class="label">Всего сейчас</span>
                                <span class="value"><?= $current_count ?></span>
                            </div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="stat-info">
                                <span class="label">Новые (<?= $period === 'day' ? 'сегодня' : ($period === 'week' ? 'неделя' : 'месяц') ?>)</span>
                                <span class="value"><?= $totals['added'] ?: 0 ?></span>
                            </div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #EF4444;">
                                <i class="fas fa-user-minus"></i>
                            </div>
                            <div class="stat-info">
                                <span class="label">Снятые (<?= $period === 'day' ? 'сегодня' : ($period === 'week' ? 'неделя' : 'месяц') ?>)</span>
                                <span class="value"><?= $totals['removed'] ?: 0 ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <h3 style="font-family: 'Outfit', sans-serif; margin: 0;">Динамика состава</h3>
                            <!-- Кастомная легенда в HTML (будет видна всегда) -->
                            <div style="display: flex; gap: 15px; font-size: 0.85rem; font-weight: 600;">
                                <div style="display: flex; align-items: center; gap: 6px;"><span style="width: 12px; height: 12px; border-radius: 3px; background: #10B981;"></span> Поднялся</div>
                                <div style="display: flex; align-items: center; gap: 6px;"><span style="width: 12px; height: 12px; border-radius: 3px; background: #EF4444;"></span> Снялся</div>
                                <div style="display: flex; align-items: center; gap: 6px;"><span style="width: 12px; height: 12px; border-radius: 3px; background: #6366F1;"></span> Без изменений</div>
                                <div style="display: flex; align-items: center; gap: 6px;"><span style="width: 12px; height: 12px; border-radius: 3px; background: #A78BFA;"></span> Общий состав</div>
                            </div>
                        </div>
                        <div style="height: 450px;">
                            <canvas id="dynamicChart"></canvas>
                        </div>
                    </div>
                    
                    <p style="text-align: center; color: var(--text-secondary); font-size: 0.8rem; opacity: 0.5;">
                        Всего записей в истории: <?= $pdo->query("SELECT COUNT(*) FROM sync_stats")->fetchColumn() ?>
                    </p>

                </div>
            </div>
        </main>
    </div>

    <?php
    // Подготовка данных для объединенного графика
    $stmt_combined = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, ?) as label, 
            MAX(discord_total) as total
        FROM sync_stats 
        WHERE created_at >= $start_date
        GROUP BY label 
        ORDER BY MIN(created_at) ASC
    ");
    $stmt_combined->execute([$date_format]);
    $combined_data = $stmt_combined->fetchAll();
    
    $c_labels = [];
    $c_total = [];
    foreach ($combined_data as $row) {
        $c_labels[] = $row['label'];
        $c_total[] = $row['total'];
    }
    
    // Если для графика нет данных за сегодня, возьмем последние 24 часа, чтобы не было пусто
    if (empty($c_labels) && $period === 'day') {
        $stmt_fallback = $pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%H:00') as label, MAX(discord_total) as total
            FROM sync_stats WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            GROUP BY label ORDER BY MIN(created_at) ASC
        ");
        $stmt_fallback->execute();
        foreach ($stmt_fallback->fetchAll() as $row) {
            $c_labels[] = $row['label'];
            $c_total[] = $row['total'];
        }
    }
    ?>

    <script>
        const ctx = document.getElementById('dynamicChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($c_labels) ?>,
                datasets: [{
                    label: 'Всего саппортов',
                    data: <?= json_encode($c_total) ?>,
                    borderWidth: 4,
                    fill: false,
                    tension: 0,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    segment: {
                        borderColor: ctx => {
                            if (ctx.p0.parsed.y === undefined || ctx.p1.parsed.y === undefined) return '#A78BFA';
                            const val1 = ctx.p0.parsed.y;
                            const val2 = ctx.p1.parsed.y;
                            return val2 > val1 ? '#10B981' : (val2 < val1 ? '#EF4444' : '#6366F1');
                        }
                    }
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: { 
                            color: '#FFFFFF', 
                            font: { family: 'Inter', size: 14, weight: '700' },
                            usePointStyle: true,
                            padding: 30,
                            // Возвращаем кастомные подписи
                            generateLabels: (chart) => {
                                return [
                                    { text: 'Поднялся', fillStyle: '#10B981', strokeStyle: '#10B981', lineWidth: 0, fontColor: '#FFFFFF' },
                                    { text: 'Снялся', fillStyle: '#EF4444', strokeStyle: '#EF4444', lineWidth: 0, fontColor: '#FFFFFF' },
                                    { text: 'Без изменений', fillStyle: '#6366F1', strokeStyle: '#6366F1', lineWidth: 0, fontColor: '#FFFFFF' },
                                    { text: 'Всего саппортов', fillStyle: '#A78BFA', strokeStyle: '#A78BFA', lineWidth: 0, fontColor: '#FFFFFF' }
                                ];
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 12,
                        titleColor: '#fff',
                        bodyColor: '#fff'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#94a3b8' },
                        beginAtZero: false
                    },
                    x: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#94a3b8' }
                    }
                }
            }
        });
    </script>
</body>
</html>
