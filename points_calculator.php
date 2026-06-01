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

// Время смен
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

// Получаем список саппортов из основной Google Таблицы
$csvUrl = getGoogleSheetCsvUrl(configValue('MAIN_SHEET_GID', 'main_sheet_gid', '2053240546'));
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

// Генерация календарных дней недели (с понедельника по воскресенье)
$today = date('Y-m-d');
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
$dayOfWeek = date('N', strtotime($today)); // 1 (Пн) - 7 (Вс)
$monday = date('Y-m-d', strtotime($today . ' - ' . ($dayOfWeek - 1) . ' days + ' . ($weekOffset * 7) . ' days'));

$days = [];
$daysFormatted = []; // Вариации дат для поиска заголовков
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime("$monday +$i days"));
    $days[] = $d;
    $daysFormatted[$i] = [
        date('d.m', strtotime($d)),         // e.g. "25.05"
        date('d.m.Y', strtotime($d)),       // e.g. "25.05.2026"
        intval(date('d', strtotime($d)))     // e.g. "25"
    ];
}

$weekLabel = date('d.m', strtotime($days[0])) . ' — ' . date('d.m.Y', strtotime($days[6]));

// Загрузка смен из GID 2053240546
$shiftsCsvUrl = getGoogleSheetCsvUrl('2053240546');
$shiftsRows = loadCsvRows($shiftsCsvUrl);
$sheetCalculatedPt = []; // discord_id => кол-во "П" в гугл таблице за неделю

if (!empty($shiftsRows)) {
    // 1. Ищем строку заголовка по совпадению дат нашей недели
    $headerRowIndex = -1;
    $dayColumns = []; // day_offset => column_index
    
    foreach ($shiftsRows as $rIdx => $row) {
        $matchesCount = 0;
        $tempCols = [];
        
        foreach ($row as $cIdx => $cell) {
            $cellVal = trim($cell);
            // Проверяем, совпадает ли ячейка с какой-то из дат нашей недели
            for ($dayIdx = 0; $dayIdx < 7; $dayIdx++) {
                foreach ($daysFormatted[$dayIdx] as $fmt) {
                    if ($cellVal == strval($fmt)) {
                        $tempCols[$dayIdx] = $cIdx;
                        $matchesCount++;
                        break;
                    }
                }
            }
        }
        
        // Если нашли хотя бы 3 совпадения дат в одной строке — это заголовок!
        if ($matchesCount >= 3) {
            $headerRowIndex = $rIdx;
            $dayColumns = $tempCols;
            break;
        }
    }
    
    // 2. Если заголовок найден, считываем присутствия "П" саппортов
    if ($headerRowIndex !== -1 && !empty($dayColumns)) {
        for ($rIdx = $headerRowIndex + 1; $rIdx < count($shiftsRows); $rIdx++) {
            $row = $shiftsRows[$rIdx];
            $discordId = preg_replace('/[^0-9]/', '', $row[3] ?? '');
            $nick = trim($row[2] ?? '');
            
            if (empty($discordId) || $nick === '' || mb_strpos(mb_strtolower($nick), 'смена') !== false) {
                continue;
            }
            
            $pCount = 0;
            foreach ($dayColumns as $dayIdx => $cIdx) {
                $cellVal = mb_strtoupper(trim($row[$cIdx] ?? ''));
                if ($cellVal === 'П' || $cellVal === 'ПШКА' || $cellVal === '+') {
                    $pCount++;
                }
            }
            $sheetCalculatedPt[$discordId] = $pCount;
        }
    }
}

// Запрашиваем из базы данных общие (lifetime) поинты
$overallPoints = [];
try {
    $stmtOP = $pdo->query("SELECT discord_id, points FROM support_overall_points");
    while ($row = $stmtOP->fetch(PDO::FETCH_ASSOC)) {
        $overallPoints[$row['discord_id']] = floatval($row['points']);
    }
} catch (Exception $e) {}

// Запрашиваем из базы данных недельные ручные баллы
$weeklyScores = [];
try {
    $stmtWS = $pdo->prepare("SELECT * FROM support_weekly_scores WHERE week_date = ?");
    $stmtWS->execute([$monday]);
    while ($row = $stmtWS->fetch(PDO::FETCH_ASSOC)) {
        $weeklyScores[$row['discord_id']] = $row;
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FUTURAMA STAFF | Подсчет баллов</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;700;800&family=Roboto+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .points-container {
            max-width: 100%;
            margin: 0 auto;
        }

        .points-info-card {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.08) 0%, rgba(59, 130, 246, 0.08) 100%);
            border: 1px solid rgba(139, 92, 246, 0.15);
            border-radius: 20px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            backdrop-filter: blur(10px);
        }

        .points-info-icon {
            font-size: 2rem;
            color: #c084fc;
            text-shadow: 0 0 15px rgba(167, 139, 250, 0.4);
        }

        .points-info-content h3 {
            margin: 0 0 0.25rem 0;
            color: #fff;
            font-size: 1.05rem;
            font-weight: 700;
        }

        .points-info-content p {
            margin: 0;
            color: #cbd5e1;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .points-table-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 2rem;
            backdrop-filter: blur(10px);
        }

        .points-table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .points-table-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .points-table-title i {
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

        .points-table-wrapper {
            overflow-x: auto;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            background: rgba(15, 23, 42, 0.15);
        }

        .points-table {
            width: 100%;
            border-collapse: collapse;
            color: #e2e8f0;
            font-size: 0.9rem;
        }

        .points-table th {
            padding: 1rem 0.75rem;
            text-align: center;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 800;
            color: #94a3b8;
            border-bottom: 2px solid rgba(255, 255, 255, 0.05);
            background: rgba(15, 23, 42, 0.4);
            white-space: nowrap;
        }

        .points-table th.support-col, .points-table td.support-col {
            text-align: left;
            padding-left: 1.25rem;
            min-width: 220px;
        }

        .points-table td {
            padding: 0.8rem 0.6rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            vertical-align: middle;
        }

        .points-table tr:hover td {
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
            font-size: 0.92rem;
        }

        .support-id-pt {
            font-size: 0.75rem;
            color: #64748b;
            font-family: 'Roboto Mono', monospace;
            margin-top: 1px;
        }

        /* Поле ввода для таблиц */
        .table-input {
            width: 70px;
            background: rgba(15, 23, 42, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 0.4rem 0.5rem;
            color: #fff;
            font-size: 0.85rem;
            font-weight: 700;
            text-align: center;
            outline: none;
            transition: all 0.2s;
            font-family: 'Roboto Mono', monospace;
        }

        .table-input:hover {
            border-color: rgba(167, 139, 250, 0.3);
            background: rgba(15, 23, 42, 0.5);
        }

        .table-input:focus {
            border-color: #a78bfa;
            background: rgba(15, 23, 42, 0.8);
            box-shadow: 0 0 8px rgba(167, 139, 250, 0.2);
            width: 80px;
        }

        .table-input.points-base-input {
            background: rgba(139, 92, 246, 0.05);
            border-color: rgba(139, 92, 246, 0.1);
            color: #c084fc;
        }

        .table-input.points-base-input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 8px rgba(139, 92, 246, 0.25);
        }

        /* Отличающийся стиль для авторасчета */
        .table-input.auto-calc {
            background: rgba(34, 197, 94, 0.03);
            border-color: rgba(34, 197, 94, 0.1);
            color: #4ade80;
        }

        .table-input.auto-calc:focus {
            border-color: #22c55e;
            box-shadow: 0 0 8px rgba(34, 197, 94, 0.25);
        }

        /* Индикатор успешного сохранения ячейки */
        .input-saved-success {
            border-color: #22c55e !important;
            box-shadow: 0 0 8px rgba(34, 197, 94, 0.4) !important;
            background: rgba(34, 197, 94, 0.15) !important;
            color: #fff !important;
        }

        /* Столбец итого */
        .total-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 50px;
            padding: 0.45rem 0.75rem;
            border-radius: 8px;
            font-weight: 800;
            font-size: 0.95rem;
            background: rgba(99, 102, 241, 0.15);
            color: #818cf8;
            border: 1px solid rgba(99, 102, 241, 0.25);
            font-family: 'Roboto Mono', monospace;
            box-shadow: 0 0 10px rgba(99, 102, 241, 0.05);
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
                    <h1>Подсчет еженедельных баллов</h1>
                    <p>Неделя: <strong style="color:#a78bfa;"><?= $weekLabel ?></strong></p>
                </div>
                <div class="header-actions">
                    <div style="display:flex; gap:8px; align-items:center;">
                        <a href="points_calculator.php?week=<?= $weekOffset - 1 ?>" class="btn-sync-pt" style="padding: 0.65rem 1rem;">
                            <i class="fas fa-chevron-left"></i> Пред. неделя
                        </a>
                        <?php if ($weekOffset < 0): ?>
                        <a href="points_calculator.php?week=<?= $weekOffset + 1 ?>" class="btn-sync-pt" style="padding: 0.65rem 1rem;">
                            След. неделя <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($weekOffset !== 0): ?>
                        <a href="points_calculator.php" class="btn-sync-pt" style="padding: 0.65rem 1rem; background: rgba(99,102,241,0.15); border-color: rgba(99,102,241,0.4); color:#818cf8;">
                            <i class="fas fa-calendar-day"></i> Тек. неделя
                        </a>
                        <?php endif; ?>
                    </div>
                    <a href="logout.php" class="btn-logout-premium"><i class="fas fa-sign-out-alt"></i> Выйти</a>
                </div>
            </header>

            <div class="page-body">
                <div class="points-container">
                    
                    <div class="points-info-card">
                        <div class="points-info-icon">
                            <i class="fas fa-star-half-stroke"></i>
                        </div>
                        <div class="points-info-content">
                            <h3>Spreadsheet-калькулятор баллов саппортов</h3>
                            <p>Колонка <strong>«Поинты»</strong> отображает общий баланс человека. Столбец <strong>«Отсиженный пт»</strong> автоматически считает баллы за пшки из смен за неделю <em>(при отсутствии смен в таблице или для переопределения вы можете отредактировать поле вручную)</em>. <strong style="color:#c084fc;">Смены 1–4: 1 пшка = 1.5 балла.</strong> Остальные смены: 1 пшка = 1 балл. Все остальные еженедельные показатели вводятся вручную и обнуляются каждую неделю.</p>
                        </div>
                    </div>

                    <div class="points-table-card">
                        <div class="points-table-header">
                            <div class="points-table-title">
                                <i class="fas fa-calculator"></i>
                                Таблица подсчета баллов
                            </div>
                            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                                <select id="pointsShiftFilter" class="search-input" style="width: 220px; padding: 0.75rem 1rem; cursor: pointer; border-radius: 12px; background: rgba(15, 23, 42, 0.4); border: 1px solid rgba(255, 255, 255, 0.08); color: #fff; font-size: 0.88rem; outline: none; transition: all 0.3s;">
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
                                    <input type="text" id="pointsSearch" class="search-input" placeholder="Поиск по никнейму...">
                                </div>
                            </div>
                        </div>

                        <div class="points-table-wrapper">
                            <table class="points-table">
                                <thead>
                                    <tr>
                                        <th class="support-col">Саппорт</th>
                                        <th style="width: 100px;">Смена</th>
                                        <th style="width: 90px; color: #a78bfa;">Поинты</th>
                                        <th style="width: 90px;">Отсиженный пт</th>
                                        <th style="width: 90px;">Полож отзывы</th>
                                        <th style="width: 90px;">Доп поинты</th>
                                        <th style="width: 90px;">Самый активный</th>
                                        <th style="width: 90px;">Больше 12 доп.ч</th>
                                        <th style="width: 90px;">2 ветки</th>
                                        <th style="width: 90px;">Ночь</th>
                                        <th style="width: 90px;">Вериф</th>
                                        <th style="width: 100px; color: #818cf8; font-weight: 800;">Итого</th>
                                    </tr>
                                </thead>
                                <tbody id="pointsTableBody">
                                    <?php if (empty($supports)): ?>
                                        <tr>
                                            <td colspan="12" style="text-align:center; padding: 4rem 1rem; color:#64748b;">
                                                Список саппортов пуст (не удалось загрузить из Google Таблицы).
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($supports as $discordId => $s): 
                                            // Базовые поинты
                                            $basePoints = $overallPoints[$discordId] ?? 0.00;
                                            
                                            // Недельные данные
                                            $weekData = $weeklyScores[$discordId] ?? [];
                                            $dbAttendedPt = $weekData['attended_pt'] ?? null;
                                            
                                            // Если в базе нет ручной отметки отсиженного ПТ, берем автоматически подсчитанное из Google Таблицы
                                            // Смены 1-4: за каждую пшку 1.5 балла, остальные смены: 1 балл
                                            $shiftNumRaw = (int)preg_replace('/[^0-9]/', '', $s['shift'] ?? '0');
                                            $pshkaMultiplier = ($shiftNumRaw >= 1 && $shiftNumRaw <= 4) ? 1.5 : 1.0;
                                            $calculatedPt = round(($sheetCalculatedPt[$discordId] ?? 0) * $pshkaMultiplier, 2);
                                            $effectiveAttendedPt = ($dbAttendedPt !== null) ? floatval($dbAttendedPt) : floatval($calculatedPt);
                                            
                                            $positiveReviews = floatval($weekData['positive_reviews'] ?? 0.00);
                                            $extraPoints = floatval($weekData['extra_points'] ?? 0.00);
                                            $mostActive = floatval($weekData['most_active'] ?? 0.00);
                                            $moreThan12H = floatval($weekData['more_than_12_h'] ?? 0.00);
                                            $twoBranches = floatval($weekData['two_branches'] ?? 0.00);
                                            $night = floatval($weekData['night'] ?? 0.00);
                                            $verif = floatval($weekData['verif'] ?? 0.00);
                                            
                                            // Итоговая сумма поинтов за неделю
                                            $totalScore = $effectiveAttendedPt + $positiveReviews + $extraPoints + $mostActive + $moreThan12H + $twoBranches + $night + $verif;
                                        ?>
                                            <tr class="points-row" data-nick="<?= htmlspecialchars(mb_strtolower($s['nick'])) ?>" data-shift="<?= htmlspecialchars($s['shift'] ?? '') ?>">
                                                <td class="support-col">
                                                    <div class="support-info-cell">
                                                        <img class="support-avatar-pt" src="avatar.php?id=<?= $discordId ?>&seed=<?= urlencode($s['nick']) ?>" alt="" onerror="this.src='https://cdn.discordapp.com/embed/avatars/0.png'">
                                                        <div>
                                                            <div class="support-name-pt"><?= htmlspecialchars($s['nick']) ?></div>
                                                            <div class="support-id-pt">ID: <?= $discordId ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style="font-size: 0.8rem; font-weight: 700; color: #94a3b8;">
                                                    <?= htmlspecialchars(str_replace(' смена', '', $s['shift'] ?? '—')) ?>
                                                </td>
                                                <!-- Поинты (Persistent) -->
                                                <td>
                                                    <input type="text" class="table-input points-base-input" 
                                                           value="<?= $basePoints ?>" 
                                                           data-discord-id="<?= $discordId ?>"
                                                           onchange="updateOverallPoints(this)">
                                                </td>
                                                <!-- Отсиженный пт (Calculated/Overridden) -->
                                                <td>
                                                    <input type="text" class="table-input auto-calc" 
                                                           placeholder="<?= $calculatedPt ?>" 
                                                           value="<?= ($dbAttendedPt !== null) ? $dbAttendedPt : '' ?>" 
                                                           data-discord-id="<?= $discordId ?>"
                                                           data-field="attended_pt"
                                                           onchange="updateWeeklyScore(this)">
                                                </td>
                                                <!-- Полож отзывы -->
                                                <td>
                                                    <input type="text" class="table-input" 
                                                           value="<?= $positiveReviews ?: '' ?>" 
                                                           placeholder="0"
                                                           data-discord-id="<?= $discordId ?>"
                                                           data-field="positive_reviews"
                                                           onchange="updateWeeklyScore(this)">
                                                </td>
                                                <!-- Доп поинты -->
                                                <td>
                                                    <input type="text" class="table-input" 
                                                           value="<?= $extraPoints ?: '' ?>" 
                                                           placeholder="0"
                                                           data-discord-id="<?= $discordId ?>"
                                                           data-field="extra_points"
                                                           onchange="updateWeeklyScore(this)">
                                                </td>
                                                <!-- Самый активный -->
                                                <td>
                                                    <input type="text" class="table-input" 
                                                           value="<?= $mostActive ?: '' ?>" 
                                                           placeholder="0"
                                                           data-discord-id="<?= $discordId ?>"
                                                           data-field="most_active"
                                                           onchange="updateWeeklyScore(this)">
                                                </td>
                                                <!-- Больше 12 доп.ч -->
                                                <td>
                                                    <input type="text" class="table-input" 
                                                           value="<?= $moreThan12H ?: '' ?>" 
                                                           placeholder="0"
                                                           data-discord-id="<?= $discordId ?>"
                                                           data-field="more_than_12_h"
                                                           onchange="updateWeeklyScore(this)">
                                                </td>
                                                <!-- работа на 2 ветках -->
                                                <td>
                                                    <input type="text" class="table-input" 
                                                           value="<?= $twoBranches ?: '' ?>" 
                                                           placeholder="0"
                                                           data-discord-id="<?= $discordId ?>"
                                                           data-field="two_branches"
                                                           onchange="updateWeeklyScore(this)">
                                                </td>
                                                <!-- Ночь -->
                                                <td>
                                                    <input type="text" class="table-input" 
                                                           value="<?= $night ?: '' ?>" 
                                                           placeholder="0"
                                                           data-discord-id="<?= $discordId ?>"
                                                           data-field="night"
                                                           onchange="updateWeeklyScore(this)">
                                                </td>
                                                <!-- Вериф -->
                                                <td>
                                                    <input type="text" class="table-input" 
                                                           value="<?= $verif ?: '' ?>" 
                                                           placeholder="0"
                                                           data-discord-id="<?= $discordId ?>"
                                                           data-field="verif"
                                                           onchange="updateWeeklyScore(this)">
                                                </td>
                                                <!-- Итого -->
                                                <td>
                                                    <span class="total-badge" id="total_<?= $discordId ?>"><?= $totalScore ?></span>
                                                </td>
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
        const weekDate = '<?= $monday ?>';

        // Функция фильтрации
        function filterPointsSupports() {
            const query = document.getElementById('pointsSearch').value.toLowerCase().trim();
            const shift = document.getElementById('pointsShiftFilter').value;
            const rows = document.querySelectorAll('.points-row');
            
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

        document.getElementById('pointsSearch').addEventListener('input', filterPointsSupports);
        document.getElementById('pointsShiftFilter').addEventListener('change', filterPointsSupports);

        // Функция обновления базовых поинтов (overall)
        function updateOverallPoints(input) {
            const discordId = input.getAttribute('data-discord-id');
            let val = input.value.trim().replace(',', '.');
            
            if (val === '') val = '0';
            
            if (isNaN(parseFloat(val))) {
                input.style.borderColor = '#ef4444';
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update_overall_points');
            formData.append('discord_id', discordId);
            formData.append('points', val);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    animateInputSuccess(input);
                } else {
                    alert('Ошибка: ' + (data.error || 'Не удалось сохранить'));
                    input.style.borderColor = '#ef4444';
                }
            })
            .catch(err => {
                alert('Ошибка сети: ' + err.message);
                input.style.borderColor = '#ef4444';
            });
        }

        // Функция обновления еженедельных баллов (weekly)
        function updateWeeklyScore(input) {
            const discordId = input.getAttribute('data-discord-id');
            const field = input.getAttribute('data-field');
            let val = input.value.trim().replace(',', '.');
            
            if (val !== '' && isNaN(parseFloat(val))) {
                input.style.borderColor = '#ef4444';
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update_weekly_score');
            formData.append('discord_id', discordId);
            formData.append('week_date', weekDate);
            formData.append('field', field);
            formData.append('value', val);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    animateInputSuccess(input);
                    recalculateRowTotal(discordId);
                } else {
                    alert('Ошибка: ' + (data.error || 'Не удалось сохранить'));
                    input.style.borderColor = '#ef4444';
                }
            })
            .catch(err => {
                alert('Ошибка сети: ' + err.message);
                input.style.borderColor = '#ef4444';
            });
        }

        // Анимация успешного сохранения
        function animateInputSuccess(input) {
            input.classList.add('input-saved-success');
            setTimeout(() => {
                input.classList.remove('input-saved-success');
                input.style.borderColor = '';
            }, 1000);
        }

        // Пересчет "Итого" строки в браузере
        function recalculateRowTotal(discordId) {
            const row = document.querySelector(`input[data-discord-id="${discordId}"][data-field="positive_reviews"]`).closest('tr');
            
            // Отсиженный ПТ (с учетом плейсхолдера авторасчета)
            const attendedInput = row.querySelector('input[data-field="attended_pt"]');
            let attendedVal = attendedInput.value.trim().replace(',', '.');
            if (attendedVal === '') {
                // Если пусто, берем значение плейсхолдера (подсчитано автоматически)
                attendedVal = attendedInput.getAttribute('placeholder') || '0';
            }
            
            const attended = parseFloat(attendedVal) || 0;
            const positive = parseFloat(row.querySelector('input[data-field="positive_reviews"]').value.replace(',', '.')) || 0;
            const extra = parseFloat(row.querySelector('input[data-field="extra_points"]').value.replace(',', '.')) || 0;
            const active = parseFloat(row.querySelector('input[data-field="most_active"]').value.replace(',', '.')) || 0;
            const more12 = parseFloat(row.querySelector('input[data-field="more_than_12_h"]').value.replace(',', '.')) || 0;
            const branches = parseFloat(row.querySelector('input[data-field="two_branches"]').value.replace(',', '.')) || 0;
            const night = parseFloat(row.querySelector('input[data-field="night"]').value.replace(',', '.')) || 0;
            const verif = parseFloat(row.querySelector('input[data-field="verif"]').value.replace(',', '.')) || 0;
            
            const total = attended + positive + extra + active + more12 + branches + night + verif;
            
            // Округляем до двух знаков после запятой, убирая лишние нули
            const formattedTotal = Math.round(total * 100) / 100;
            document.getElementById(`total_${discordId}`).textContent = formattedTotal;
        }
    </script>
</body>
</html>
