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

require_once 'db.php';
require_once 'staff_functions.php';
require_once 'user_header.php';

// Карта времени смен
$shiftTimes = [
    '0 смена' => 'Свободный график',
    '1 смена' => '00:00 - 02:00',
    '2 смена' => '02:00 - 04:00',
    '3 смена' => '04:00 - 06:00',
    '4 смена' => '06:00 - 08:00',
    '5 смена' => '08:00 - 10:00',
    '6 смена' => '10:00 - 12:00',
    '7 смена' => '12:00 - 14:00',
    '8 смена' => '14:00 - 16:00',
    '9 смена' => '16:00 - 18:00',
    '10 смена' => '18:00 - 20:00',
    '11 смена' => '20:00 - 22:00',
    '12 смена' => '22:00 - 00:00'
];

// Получаем список саппортов из Google Таблицы
$csvUrl = getGoogleSheetCsvUrl(configValue('MAIN_SHEET_GID', 'main_sheet_gid', '1970062457'));
$rows = loadCsvRows($csvUrl);
$supports = [];

$currentShift = '0 смена';

foreach ($rows as $index => $row) {
    if ($index < 2) continue; // Пропускаем заголовки
    
    // Проверяем, не является ли строка разделителем смены
    $cell = trim($row[2] ?? '');
    if (preg_match('/^(\d+)\s+смена/i', $cell, $matches)) {
        $currentShift = $matches[1] . ' смена';
        continue;
    }
    
    $date = trim($row[1] ?? '');
    $nick = trim($row[2] ?? '');
    $discord_id = preg_replace('/[^0-9]/', '', (string)($row[3] ?? ''));
    
    if ($nick !== '' && $nick !== '-' && $nick !== 'Никнейм' && mb_strpos(mb_strtolower($nick), 'смена') === false && !empty($discord_id)) {
        $supports[$discord_id] = [
            'nick' => $nick,
            'discord_id' => $discord_id,
            'date_joined' => $date,
            'shift' => $currentShift
        ];
    }
}

// Генерируем дни нужной календарной недели (с понедельника по воскресенье)
$today = date('Y-m-d');
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
$dayOfWeek = date('N', strtotime($today)); // 1 (Пн) - 7 (Вс)
$monday = date('Y-m-d', strtotime($today . ' - ' . ($dayOfWeek - 1) . ' days + ' . ($weekOffset * 7) . ' days'));

$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = date('Y-m-d', strtotime("$monday +$i days"));
}

$weekLabel = date('d.m', strtotime($days[0])) . ' — ' . date('d.m.Y', strtotime($days[6]));

// Запрашиваем статистику активности из базы данных за эти 7 дней
$startDate = $days[0] . ' 00:00:00';
$endDate = $days[6] . ' 23:59:59';

$stmt = $pdo->prepare("
    SELECT 
        discord_id,
        DATE(start_time) as log_date,
        SUM(duration) as daily_duration
    FROM voice_activity
    WHERE start_time >= ? AND start_time <= ?
    GROUP BY discord_id, DATE(start_time)
");
$stmt->execute([$startDate, $endDate]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Форматируем логи в массив: discord_id -> date -> duration (секунды)
$activityData = [];
foreach ($logs as $log) {
    $dId = $log['discord_id'];
    $date = $log['log_date'];
    $duration = (int)$log['daily_duration'];
    
    if (!isset($activityData[$dId])) {
        $activityData[$dId] = [];
    }
    $activityData[$dId][$date] = $duration;
}

// Запрашиваем ручные переопределения
$overrideData = [];
try {
    $stmtO = $pdo->prepare("
        SELECT discord_id, log_date, status 
        FROM pt_overrides 
        WHERE log_date >= ? AND log_date <= ?
    ");
    $stmtO->execute([$days[0], $days[6]]);
    $overrides = $stmtO->fetchAll(PDO::FETCH_ASSOC);
    foreach ($overrides as $ov) {
        $overrideData[$ov['discord_id']][$ov['log_date']] = $ov['status'];
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FUTURAMA STAFF | Учет ПТ</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .pt-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .pt-rules-card {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(59, 130, 246, 0.1) 100%);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            backdrop-filter: blur(10px);
        }

        .pt-rules-icon {
            font-size: 2.2rem;
            color: #a78bfa;
            text-shadow: 0 0 15px rgba(167, 139, 250, 0.4);
        }

        .pt-rules-content h3 {
            margin: 0 0 0.25rem 0;
            color: #fff;
            font-size: 1.05rem;
            font-weight: 700;
        }

        .pt-rules-content p {
            margin: 0;
            color: #cbd5e1;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .pt-table-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 2rem;
            backdrop-filter: blur(10px);
        }

        .pt-table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .pt-table-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pt-table-title i {
            color: #a78bfa;
        }

        .search-wrapper {
            position: relative;
            width: 300px;
        }

        .search-input {
            width: 100%;
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            color: #fff;
            font-size: 0.88rem;
            outline: none;
            transition: all 0.3s;
        }

        .search-input:focus {
            border-color: #a78bfa;
            box-shadow: 0 0 10px rgba(167, 139, 250, 0.2);
        }

        .search-icon {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 0.9rem;
        }

        .pt-table {
            width: 100%;
            border-collapse: collapse;
            color: #e2e8f0;
        }

        .pt-table th {
            padding: 1rem;
            text-align: center;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 800;
            color: #94a3b8;
            border-bottom: 2px solid rgba(255, 255, 255, 0.05);
            background: rgba(15, 23, 42, 0.2);
        }

        .pt-table th.support-col, .pt-table td.support-col {
            text-align: left;
            padding-left: 1.5rem;
            width: 250px;
        }

        .pt-table td {
            padding: 1.1rem 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 0.92rem;
            vertical-align: middle;
        }

        .pt-table tr:hover td {
            background: rgba(255, 255, 255, 0.01);
        }

        .support-info-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .support-avatar-pt {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            object-fit: cover;
            border: 1.5px solid rgba(255, 255, 255, 0.1);
        }

        .support-name-pt {
            font-weight: 700;
            color: #f8fafc;
        }

        .support-id-pt {
            font-size: 0.75rem;
            color: #64748b;
            font-family: monospace;
            margin-top: 1px;
        }

        /* Бейджи статусов */
        .pt-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 0.95rem;
            cursor: help;
            position: relative;
            transition: all 0.2s;
        }

        .pt-badge.pass {
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.25);
            box-shadow: 0 0 10px rgba(34, 197, 94, 0.05);
        }

        .pt-badge.pass:hover {
            background: rgba(34, 197, 94, 0.25);
            transform: scale(1.08);
        }

        .pt-badge.fail {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.25);
        }

        .pt-badge.fail:hover {
            background: rgba(239, 68, 68, 0.25);
            transform: scale(1.08);
        }

        .pt-badge.none {
            background: rgba(255, 255, 255, 0.02);
            color: #475569;
            border: 1px solid rgba(255, 255, 255, 0.03);
        }

        /* Всплывающая подсказка */
        .pt-tooltip {
            position: absolute;
            bottom: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%);
            background: #0f172a;
            color: #f8fafc;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 0.72rem;
            white-space: nowrap;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.08);
            z-index: 10;
        }

        .pt-badge:hover .pt-tooltip {
            opacity: 1;
        }

        .btn-sync-pt {
            background: rgba(167, 139, 250, 0.1);
            border: 1px solid rgba(167, 139, 250, 0.3);
            color: #A78BFA;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-sync-pt:hover {
            background: rgba(167, 139, 250, 0.2);
            border-color: rgba(167, 139, 250, 0.5);
            color: #fff;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Учет ПТ саппортов</h1>
                    <p>Неделя: <strong style="color:#a78bfa;"><?= $weekLabel ?></strong></p>
                </div>
                <div class="header-actions">
                    <div style="display:flex; gap:8px; align-items:center;">
                        <a href="pt_tracker.php?week=<?= $weekOffset - 1 ?>" class="btn-sync-pt" style="padding: 0.65rem 1rem;">
                            <i class="fas fa-chevron-left"></i> Пред. неделя
                        </a>
                        <?php if ($weekOffset < 0): ?>
                        <a href="pt_tracker.php?week=<?= $weekOffset + 1 ?>" class="btn-sync-pt" style="padding: 0.65rem 1rem;">
                            След. неделя <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($weekOffset !== 0): ?>
                        <a href="pt_tracker.php" class="btn-sync-pt" style="padding: 0.65rem 1rem; background: rgba(99,102,241,0.15); border-color: rgba(99,102,241,0.4); color:#818cf8;">
                            <i class="fas fa-calendar-day"></i> Тек. неделя
                        </a>
                        <?php endif; ?>
                    </div>
                    <a href="logout.php" class="btn-logout-premium"><i class="fas fa-sign-out-alt"></i> Выйти</a>
                </div>
            </header>

            <div class="page-body">
                <div class="pt-container">

                    <!-- ЗАГЛУШКА: В разработке -->
                    <div style="
                        background: linear-gradient(135deg, rgba(139, 92, 246, 0.08) 0%, rgba(99, 102, 241, 0.05) 100%);
                        border: 1px solid rgba(139, 92, 246, 0.2);
                        border-radius: 28px;
                        padding: 5rem 3rem;
                        text-align: center;
                        backdrop-filter: blur(10px);
                        position: relative;
                        overflow: hidden;
                    ">
                        <!-- Декоративные круги -->
                        <div style="position:absolute;top:-60px;right:-60px;width:250px;height:250px;border-radius:50%;background:radial-gradient(circle,rgba(139,92,246,0.12),transparent 70%);pointer-events:none;"></div>
                        <div style="position:absolute;bottom:-80px;left:-80px;width:300px;height:300px;border-radius:50%;background:radial-gradient(circle,rgba(99,102,241,0.1),transparent 70%);pointer-events:none;"></div>

                        <div style="
                            width: 90px;
                            height: 90px;
                            border-radius: 24px;
                            background: linear-gradient(135deg, rgba(139,92,246,0.25), rgba(99,102,241,0.15));
                            border: 1px solid rgba(139,92,246,0.3);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto 2rem;
                            box-shadow: 0 0 40px rgba(139,92,246,0.2);
                            animation: wip-pulse 3s ease-in-out infinite;
                        ">
                            <i class="fas fa-wrench" style="font-size:2.2rem;color:#a78bfa;"></i>
                        </div>

                        <h2 style="
                            font-size: 2rem;
                            font-weight: 800;
                            color: #fff;
                            margin: 0 0 0.75rem;
                            font-family: 'Outfit', sans-serif;
                            letter-spacing: -0.5px;
                        ">В разработке</h2>

                        <p style="
                            color: #94a3b8;
                            font-size: 1rem;
                            max-width: 480px;
                            margin: 0 auto 2.5rem;
                            line-height: 1.7;
                        ">Функция учёта ПТ саппортов находится на стадии активной разработки и будет доступна в ближайшее время.</p>

                        <div style="
                            display: inline-flex;
                            align-items: center;
                            gap: 10px;
                            background: rgba(139,92,246,0.1);
                            border: 1px solid rgba(139,92,246,0.25);
                            border-radius: 12px;
                            padding: 0.75rem 1.5rem;
                            color: #c084fc;
                            font-size: 0.9rem;
                            font-weight: 700;
                        ">
                            <i class="fas fa-clock"></i>
                            Скоро доступно
                        </div>
                    </div>

                    <style>
                        @keyframes wip-pulse {
                            0%, 100% { box-shadow: 0 0 30px rgba(139,92,246,0.15); transform: translateY(0); }
                            50% { box-shadow: 0 0 55px rgba(139,92,246,0.35); transform: translateY(-4px); }
                        }
                    </style>

                    <!-- СКРЫТАЯ ТАБЛИЦА (старая функциональность) -->
                    <div class="pt-table-card" style="display:none;">
                        <div class="pt-table-header">
                            <div class="pt-table-title">
                                <i class="fas fa-calendar-week"></i>
                                Журнал активности
                            </div>
                            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                                <select id="ptShiftFilter" class="search-input" style="width: 220px; padding: 0.75rem 1rem; cursor: pointer; border-radius: 12px; background: rgba(15, 23, 42, 0.4); border: 1px solid rgba(255, 255, 255, 0.08); color: #fff; font-size: 0.88rem; outline: none; transition: all 0.3s;">
                                    <option value="" style="background: #0f172a; color: #fff;">Все смены</option>
                                    <option value="1 смена" style="background: #0f172a; color: #fff;">1 смена (00:00 - 02:00)</option>
                                    <option value="2 смена" style="background: #0f172a; color: #fff;">2 смена (02:00 - 04:00)</option>
                                    <option value="3 смена" style="background: #0f172a; color: #fff;">3 смена (04:00 - 06:00)</option>
                                    <option value="4 смена" style="background: #0f172a; color: #fff;">4 смена (06:00 - 08:00)</option>
                                    <option value="5 смена" style="background: #0f172a; color: #fff;">5 смена (08:00 - 10:00)</option>
                                    <option value="6 смена" style="background: #0f172a; color: #fff;">6 смена (10:00 - 12:00)</option>
                                    <option value="7 смена" style="background: #0f172a; color: #fff;">7 смена (12:00 - 14:00)</option>
                                    <option value="8 смена" style="background: #0f172a; color: #fff;">8 смена (14:00 - 16:00)</option>
                                    <option value="9 смена" style="background: #0f172a; color: #fff;">9 смена (16:00 - 18:00)</option>
                                    <option value="10 смена" style="background: #0f172a; color: #fff;">10 смена (18:00 - 20:00)</option>
                                    <option value="11 смена" style="background: #0f172a; color: #fff;">11 смена (20:00 - 22:00)</option>
                                    <option value="12 смена" style="background: #0f172a; color: #fff;">12 смена (22:00 - 00:00)</option>
                                    <option value="0 смена" style="background: #0f172a; color: #fff;">0 смена (Свободный график)</option>
                                </select>
                                <div class="search-wrapper">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" id="ptSearch" class="search-input" placeholder="Поиск по никнейму...">
                                </div>
                            </div>
                        </div>

                        <div style="overflow-x: auto;">
                            <table class="pt-table">
                                <thead>
                                    <tr>
                                        <th class="support-col">Саппорт</th>
                                        <th style="width: 140px; text-align: left;">Смена</th>
                                        <?php 
                                        $daysRu = [
                                            'Mon' => 'Пн',
                                            'Tue' => 'Вт',
                                            'Wed' => 'Ср',
                                            'Thu' => 'Чт',
                                            'Fri' => 'Пт',
                                            'Sat' => 'Сб',
                                            'Sun' => 'Вс'
                                        ];
                                        foreach ($days as $day): ?>
                                            <th>
                                                <div style="font-weight: 800;"><?= date('d.m', strtotime($day)) ?></div>
                                                <div style="font-size: 0.65rem; color:#64748b; font-weight: 500; margin-top:2px;">
                                                    <?= $daysRu[date('D', strtotime($day))] ?? date('D', strtotime($day)) ?>
                                                </div>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody id="ptTableBody">
                                    <?php if (empty($supports)): ?>
                                        <tr>
                                            <td colspan="9" style="text-align:center; padding: 4rem 1rem; color:#64748b;">
                                                Список саппортов пуст (не удалось загрузить из Google Таблицы).
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($supports as $discordId => $s): ?>
                                            <tr class="pt-row" data-nick="<?= htmlspecialchars(mb_strtolower($s['nick'])) ?>" data-shift="<?= htmlspecialchars($s['shift'] ?? '') ?>">
                                                <td class="support-col">
                                                    <div class="support-info-cell">
                                                        <img class="support-avatar-pt" src="avatar.php?id=<?= $discordId ?>&seed=<?= urlencode($s['nick']) ?>" alt="">
                                                        <div>
                                                            <div class="support-name-pt"><?= htmlspecialchars($s['nick']) ?></div>
                                                            <div class="support-id-pt">ID: <?= $discordId ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style="text-align: left; vertical-align: middle;">
                                                    <span style="font-weight: 700; color: #a78bfa; font-size: 0.85rem; display: block;"><?= htmlspecialchars($s['shift'] ?? '—') ?></span>
                                                    <span style="font-size: 0.72rem; color: #64748b; font-weight: 500;"><?= htmlspecialchars($shiftTimes[$s['shift']] ?? 'Свободный график') ?></span>
                                                </td>
                                                <?php foreach ($days as $day): 
                                                    $duration = $activityData[$discordId][$day] ?? 0;
                                                    $overrideStatus = $overrideData[$discordId][$day] ?? 'NONE'; // 'П', 'О', or 'NONE'
                                                    
                                                    $minutes = round($duration / 60);
                                                    $hours = floor($minutes / 60);
                                                    $remMinutes = $minutes % 60;
                                                    
                                                    $timeStr = "";
                                                    if ($hours > 0) {
                                                        $timeStr .= "$hours ч. ";
                                                    }
                                                    $timeStr .= "$remMinutes мин.";
                                                    
                                                    // 1 час 40 минут = 100 минут = 6000 секунд
                                                    $calculatedStatus = ($duration >= 6000) ? 'П' : 'О';
                                                    if ($duration === 0) {
                                                        $calculatedStatus = 'NONE';
                                                    }
                                                    
                                                    $effectiveStatus = ($overrideStatus !== 'NONE') ? $overrideStatus : $calculatedStatus;
                                                    
                                                    if ($effectiveStatus === 'П') {
                                                        $badgeText = 'П';
                                                        $badgeClass = 'pass';
                                                        $tooltipText = ($overrideStatus !== 'NONE') ? 'Установлено вручную: П' : "Норма сдана: $timeStr";
                                                    } elseif ($effectiveStatus === 'О') {
                                                        $badgeText = 'О';
                                                        $badgeClass = 'fail';
                                                        $tooltipText = ($overrideStatus !== 'NONE') ? 'Установлено вручную: О' : "Недобор: $timeStr";
                                                    } else {
                                                        $badgeText = '0';
                                                        $badgeClass = 'none';
                                                        $tooltipText = 'Нет активности';
                                                    }
                                                    
                                                    $manualStyle = ($overrideStatus !== 'NONE') ? 'border: 2px dashed currentColor;' : '';
                                                ?>
                                                    <td>
                                                        <div class="pt-badge <?= $badgeClass ?> clickable-pt-badge" 
                                                             data-discord-id="<?= $discordId ?>" 
                                                             data-date="<?= $day ?>" 
                                                             data-current="<?= $overrideStatus ?>" 
                                                             data-calculated-status="<?= $calculatedStatus ?>"
                                                             data-calculated-time="<?= $timeStr ?>"
                                                             onclick="togglePTOverride(this)"
                                                             style="cursor: pointer; position: relative; <?= $manualStyle ?>">
                                                            <span class="badge-text"><?= $badgeText ?></span>
                                                            <div class="pt-tooltip"><?= $tooltipText ?></div>
                                                        </div>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <script>
        // Функция фильтрации саппортов по нику и смене
        function filterSupports() {
            const query = document.getElementById('ptSearch').value.toLowerCase().trim();
            const shift = document.getElementById('ptShiftFilter').value;
            const rows = document.querySelectorAll('.pt-row');
            
            rows.forEach(row => {
                const nick = row.getAttribute('data-nick') || '';
                const rowShift = row.getAttribute('data-shift') || '';
                
                const matchesNick = nick.includes(query);
                const matchesShift = shift === '' || rowShift === shift;
                
                if (matchesNick && matchesShift) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        document.getElementById('ptSearch').addEventListener('input', filterSupports);
        document.getElementById('ptShiftFilter').addEventListener('change', filterSupports);

        // Функция синхронизации с Google Таблицей (демонстрационная заглушка с алертом)
        function syncWithGoogleSheets() {
            alert('Служба синхронизации ПТ запущена!\nСистема проверяет наличие Apps Script вебхука и выгрузит данные за сегодня.');
        }

        // Функция ручного переопределения статуса ПТ
        function togglePTOverride(el) {
            const discordId = el.getAttribute('data-discord-id');
            const logDate = el.getAttribute('data-date');
            const current = el.getAttribute('data-current'); // 'NONE', 'П', 'О'
            const calculatedStatus = el.getAttribute('data-calculated-status');
            const calculatedTime = el.getAttribute('data-calculated-time');
            
            // Цикл: NONE -> П -> О -> NONE
            let nextStatus = 'NONE';
            if (current === 'NONE') {
                nextStatus = 'П';
            } else if (current === 'П') {
                nextStatus = 'О';
            } else {
                nextStatus = 'NONE';
            }
            
            // Оптимистичное обновление интерфейса
            updateBadgeVisually(el, nextStatus, calculatedStatus, calculatedTime);
            
            // Отправка запроса в API
            const formData = new FormData();
            formData.append('action', 'update_pt_override');
            formData.append('discord_id', discordId);
            formData.append('log_date', logDate);
            formData.append('status', nextStatus);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    // Откат при ошибке
                    updateBadgeVisually(el, current, calculatedStatus, calculatedTime);
                    alert('Ошибка при сохранении: ' + (data.error || 'Неизвестная ошибка'));
                }
            })
            .catch(err => {
                updateBadgeVisually(el, current, calculatedStatus, calculatedTime);
                alert('Ошибка сети: ' + err.message);
            });
        }

        function updateBadgeVisually(el, status, calculatedStatus, calculatedTime) {
            el.setAttribute('data-current', status);
            
            const effectiveStatus = (status !== 'NONE') ? status : calculatedStatus;
            const badgeTextEl = el.querySelector('.badge-text');
            const tooltipEl = el.querySelector('.pt-tooltip');
            
            // Сброс классов
            el.classList.remove('pass', 'fail', 'none');
            
            if (effectiveStatus === 'П') {
                badgeTextEl.textContent = 'П';
                el.classList.add('pass');
                tooltipEl.textContent = (status !== 'NONE') ? 'Установлено вручную: П' : 'Норма сдана: ' + calculatedTime;
            } else if (effectiveStatus === 'О') {
                badgeTextEl.textContent = 'О';
                el.classList.add('fail');
                tooltipEl.textContent = (status !== 'NONE') ? 'Установлено вручную: О' : 'Недобор: ' + calculatedTime;
            } else {
                badgeTextEl.textContent = '0';
                el.classList.add('none');
                tooltipEl.textContent = 'Нет активности';
            }
            
            if (status !== 'NONE') {
                el.style.border = '2px dashed currentColor';
            } else {
                el.style.border = '';
            }
        }
    </script>
</body>
</html>
