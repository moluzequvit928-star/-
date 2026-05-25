<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Проверка роли (только админ или гл. куратор)
$u_role = $_SESSION['role'] ?? 'master';
if ($u_role !== 'admin' && $u_role !== 'chief') {
    header('Location: index.php');
    exit;
}

require_once 'db.php';
require_once 'user_header.php';

$configFile = __DIR__ . '/fortune_wheel_config.json';
$message = '';
$messageType = '';

// Обработка сохранения настроек
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $names = $_POST['names'] ?? [];
        $weights = $_POST['weights'] ?? [];
        $colors = $_POST['colors'] ?? [];
        $spin_speed = isset($_POST['spin_speed']) ? (int)$_POST['spin_speed'] : 3;

        $newOptions = [];
        for ($i = 0; $i < count($names); $i++) {
            $name = trim($names[$i]);
            $weight = floatval($weights[$i]);
            $color = trim($colors[$i]);

            if ($name !== '' && $weight > 0) {
                $newOptions[] = [
                    'name' => $name,
                    'weight' => $weight,
                    'color' => $color ?: '#6366f1'
                ];
            }
        }

        if (empty($newOptions)) {
            throw new Exception("Необходимо указать хотя бы один корректный вариант с положительным шансом!");
        }

        $newConfig = [
            'spin_speed' => $spin_speed,
            'options' => $newOptions
        ];

        file_put_contents($configFile, json_encode($newConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $message = "Настройки колеса фортуны успешно сохранены!";
        $messageType = "success";
        $spinSpeed = $spin_speed;
    } catch (Exception $e) {
        $message = "Ошибка при сохранении: " . $e->getMessage();
        $messageType = "error";
    }
}

// Загрузка текущих настроек
$optionsData = [];
$spinSpeed = 3;
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
    if (is_array($config)) {
        if (isset($config['options'])) {
            $optionsData = $config['options'];
            $spinSpeed = isset($config['spin_speed']) ? (int)$config['spin_speed'] : 3;
        } else {
            $optionsData = $config;
        }
    }
}
if (empty($optionsData)) {
    $optionsData = [
        ['name' => 'Вариант 1', 'weight' => 20, 'color' => '#6366f1'],
        ['name' => 'Вариант 2', 'weight' => 20, 'color' => '#8b5cf6'],
        ['name' => 'Вариант 3', 'weight' => 20, 'color' => '#ec4899'],
        ['name' => 'Вариант 4', 'weight' => 20, 'color' => '#ef4444'],
        ['name' => 'Вариант 5', 'weight' => 20, 'color' => '#f59e0b']
    ];
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройка Колеса | Futurama Staff</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-top: 1.5rem;
        }

        @media (max-width: 1024px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }

        .option-row {
            display: grid;
            grid-template-columns: auto auto 1fr auto;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 14px 16px;
            border-radius: 16px;
            margin-bottom: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: slideIn 0.3s ease;
        }

        .option-row:hover {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(99, 102, 241, 0.2);
            transform: translateX(4px);
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .option-row-main {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .option-row-top {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .slider-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .weight-slider {
            flex: 1;
            -webkit-appearance: none;
            appearance: none;
            height: 6px;
            border-radius: 100px;
            background: rgba(255,255,255,0.08);
            outline: none;
            cursor: pointer;
            transition: 0.2s;
        }

        .weight-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #6366f1;
            cursor: pointer;
            box-shadow: 0 0 8px rgba(99,102,241,0.6);
            border: 2px solid #fff;
            transition: 0.2s;
        }

        .weight-slider::-webkit-slider-thumb:hover {
            transform: scale(1.2);
            box-shadow: 0 0 14px rgba(99,102,241,0.8);
        }

        .weight-slider::-moz-range-thumb {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #6366f1;
            cursor: pointer;
            border: 2px solid #fff;
            box-shadow: 0 0 8px rgba(99,102,241,0.6);
        }

        .weight-pct-badge {
            min-width: 52px;
            text-align: center;
            font-weight: 800;
            font-size: 0.85rem;
            padding: 3px 8px;
            border-radius: 8px;
            background: rgba(99,102,241,0.15);
            color: #a78bfa;
            border: 1px solid rgba(99,102,241,0.25);
            white-space: nowrap;
        }

        .color-picker-wrapper {
            position: relative;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.15);
            cursor: pointer;
            transition: 0.2s;
        }

        .color-picker-wrapper:hover {
            transform: scale(1.05);
            border-color: #6366f1;
        }

        .color-picker-wrapper input[type="color"] {
            position: absolute;
            top: -10px;
            left: -10px;
            width: 60px;
            height: 60px;
            border: none;
            cursor: pointer;
            background: none;
        }

        .form-control-settings {
            background: rgba(15, 23, 42, 0.6) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 10px !important;
            color: #fff !important;
            padding: 8px 12px !important;
            font-size: 0.95rem !important;
            transition: 0.2s;
        }

        .form-control-settings:focus {
            border-color: #6366f1 !important;
            outline: none;
        }

        .weight-input {
            width: 100px;
            text-align: center;
        }

        .btn-add-option {
            width: 100%;
            padding: 14px;
            background: rgba(99, 102, 241, 0.08);
            color: var(--accent);
            border: 1px dashed var(--accent);
            border-radius: 14px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
        }

        .btn-add-option:hover {
            background: var(--accent);
            color: white;
            border-style: solid;
        }

        .btn-delete-option {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-delete-option:hover {
            background: #ef4444;
            color: #fff;
            transform: scale(1.05);
        }

        .chance-summary-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            position: sticky;
            top: 20px;
        }

        .summary-item {
            margin-bottom: 15px;
        }

        .summary-item-header {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }

        .summary-progress-bg {
            background: rgba(255, 255, 255, 0.05);
            height: 6px;
            border-radius: 100px;
            overflow: hidden;
        }

        .summary-progress-bar {
            height: 100%;
            border-radius: 100px;
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-save-settings {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #6366f1 0%, #a78bfa 100%);
            border: none;
            border-radius: 14px;
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
        }

        .btn-save-settings:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(99, 102, 241, 0.3);
            filter: brightness(1.1);
        }

        .alert-box {
            padding: 1rem 1.5rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            animation: fadeIn 0.4s ease;
        }

        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Настройка Колеса Фортуны</h1>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">Управляйте вариантами, цветами секторов и шансами выпадения.</p>
                </div>
            </header>

            <div class="page-body">
                <?php if ($message): ?>
                    <div class="alert-box <?= $messageType === 'success' ? 'alert-success' : 'alert-error' ?>">
                        <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="settingsForm">
                    <div class="settings-grid">
                        <div class="card" style="margin: 0; padding: 2rem;">
                            <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-edit" style="color: var(--accent);"></i> Варианты колеса
                            </h3>

                            <div id="options-container">
                                <?php foreach ($optionsData as $opt): ?>
                                    <div class="option-row">
                                        <i class="fas fa-grip-lines" style="color: rgba(255,255,255,0.2); cursor: move;"></i>
                                        <div class="color-picker-wrapper" style="background-color: <?= htmlspecialchars($opt['color']) ?>">
                                            <input type="color" name="colors[]" value="<?= htmlspecialchars($opt['color']) ?>" onchange="updateRowColor(this)">
                                        </div>
                                        <div class="option-row-main">
                                            <div class="option-row-top">
                                                <input type="text" name="names[]" value="<?= htmlspecialchars($opt['name']) ?>" class="form-control-settings" style="flex:1;" placeholder="Название варианта" required oninput="calculateChances()">
                                                <input type="number" name="weights[]" value="<?= htmlspecialchars($opt['weight']) ?>" min="0.1" max="1000" step="0.1" class="form-control-settings weight-input" placeholder="Вес" required oninput="syncSliderFromInput(this)">
                                            </div>
                                            <div class="slider-row">
                                                <input type="range" class="weight-slider" min="1" max="100" value="<?= min(100, max(1, (int)$opt['weight'])) ?>" oninput="syncInputFromSlider(this)">
                                                <span class="weight-pct-badge">—%</span>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-delete-option" onclick="deleteRow(this)">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <button type="button" class="btn-add-option" onclick="addNewRow()">
                                <i class="fas fa-plus"></i> Добавить вариант
                            </button>

                            <button type="submit" class="btn-save-settings">
                                <i class="fas fa-save"></i> Сохранить все изменения
                            </button>
                        </div>

                        <div>
                            <!-- Настройка скорости вращения -->
                            <div class="chance-summary-card" style="margin-bottom: 1.5rem; padding: 1.5rem;">
                                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-gauge-high" style="color: var(--accent);"></i> Скорость вращения
                                </h3>
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600;">
                                        <span style="color: var(--text-secondary);">Текущая скорость:</span>
                                        <span id="speed-display-label" style="color: var(--accent); font-weight: 700;">Обычная</span>
                                    </div>
                                    <input type="range" name="spin_speed" id="spin_speed_slider" min="1" max="5" value="<?= $spinSpeed ?>" style="width: 100%; accent-color: var(--accent); cursor: pointer;" oninput="updateSpeedLabel(this.value)">
                                    <div style="display: flex; justify-content: space-between; font-size: 0.7rem; color: var(--text-muted); font-weight: 600;">
                                        <span>Медленная</span>
                                        <span>Обычная</span>
                                        <span>Молниеносная</span>
                                    </div>
                                </div>
                            </div>

                            <div class="chance-summary-card">
                                <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-chart-pie" style="color: var(--accent);"></i> Распределение шансов
                                </h3>
                                <div id="chances-summary-container">
                                    <!-- Будет динамически наполняться через JS -->
                                </div>
                                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.05); text-align: center;">
                                    <span style="font-size: 0.8rem; color: var(--text-secondary);">Шансы рассчитываются автоматически на основе весов вариантов.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        const colorsPreset = [
            "#6366f1", "#8b5cf6", "#ec4899", "#ef4444", "#f59e0b", 
            "#10b981", "#06b6d4", "#3b82f6", "#6d28d9", "#db2777"
        ];

        function updateRowColor(picker) {
            picker.parentElement.style.backgroundColor = picker.value;
            calculateChances();
        }

        function deleteRow(btn) {
            const row = btn.closest('.option-row');
            row.style.transform = 'scale(0.9)';
            row.style.opacity = '0';
            setTimeout(() => {
                row.remove();
                calculateChances();
            }, 250);
        }

        function addNewRow() {
            const container = document.getElementById('options-container');
            const rowCount = container.querySelectorAll('.option-row').length;
            const defaultColor = colorsPreset[rowCount % colorsPreset.length];

            const div = document.createElement('div');
            div.className = 'option-row';
            div.innerHTML = `
                <i class="fas fa-grip-lines" style="color: rgba(255,255,255,0.2); cursor: move;"></i>
                <div class="color-picker-wrapper" style="background-color: ${defaultColor}">
                    <input type="color" name="colors[]" value="${defaultColor}" onchange="updateRowColor(this)">
                </div>
                <div class="option-row-main">
                    <div class="option-row-top">
                        <input type="text" name="names[]" class="form-control-settings" style="flex:1;" placeholder="Название варианта" required oninput="calculateChances()">
                        <input type="number" name="weights[]" value="20" min="0.1" max="1000" step="0.1" class="form-control-settings weight-input" placeholder="Вес" required oninput="syncSliderFromInput(this)">
                    </div>
                    <div class="slider-row">
                        <input type="range" class="weight-slider" min="1" max="100" value="20" oninput="syncInputFromSlider(this)">
                        <span class="weight-pct-badge">—%</span>
                    </div>
                </div>
                <button type="button" class="btn-delete-option" onclick="deleteRow(this)">
                    <i class="fas fa-trash-alt"></i>
                </button>
            `;
            container.appendChild(div);
            // Update slider color
            const slider = div.querySelector('.weight-slider');
            updateSliderTrack(slider, defaultColor);
            calculateChances();
        }

        function syncSliderFromInput(input) {
            const row = input.closest('.option-row');
            const slider = row.querySelector('.weight-slider');
            const val = Math.min(100, Math.max(1, parseFloat(input.value) || 1));
            slider.value = val;
            const color = row.querySelector('input[name="colors[]"]').value;
            updateSliderTrack(slider, color);
            calculateChances();
        }

        function syncInputFromSlider(slider) {
            const row = slider.closest('.option-row');
            const input = row.querySelector('input[name="weights[]"]');
            input.value = slider.value;
            const color = row.querySelector('input[name="colors[]"]').value;
            updateSliderTrack(slider, color);
            calculateChances();
        }

        function updateSliderTrack(slider, color) {
            const pct = ((slider.value - slider.min) / (slider.max - slider.min)) * 100;
            slider.style.background = `linear-gradient(to right, ${color} ${pct}%, rgba(255,255,255,0.08) ${pct}%)`;
            slider.style.setProperty('--thumb-color', color);
            // Update thumb color via inline style hack for webkit
            slider.style.accentColor = color;
        }

        function calculateChances() {
            const container = document.getElementById('options-container');
            const summaryContainer = document.getElementById('chances-summary-container');
            const rows = container.querySelectorAll('.option-row');

            let totalWeight = 0;
            const items = [];

            rows.forEach(row => {
                const name = row.querySelector('input[name="names[]"]').value.trim() || 'Без названия';
                const weight = parseFloat(row.querySelector('input[name="weights[]"]').value) || 0;
                const color = row.querySelector('input[name="colors[]"]').value;
                const slider = row.querySelector('.weight-slider');
                
                if (weight > 0) {
                    totalWeight += weight;
                    items.push({ name, weight, color, slider });
                }
            });

            // Update badges
            rows.forEach(row => {
                const badge = row.querySelector('.weight-pct-badge');
                const weight = parseFloat(row.querySelector('input[name="weights[]"]').value) || 0;
                const color = row.querySelector('input[name="colors[]"]').value;
                const slider = row.querySelector('.weight-slider');
                if (badge && totalWeight > 0) {
                    const pct = ((weight / totalWeight) * 100).toFixed(1);
                    badge.textContent = pct + '%';
                    badge.style.color = color;
                    badge.style.background = color + '22';
                    badge.style.borderColor = color + '44';
                }
                if (slider) updateSliderTrack(slider, color);
            });

            summaryContainer.innerHTML = '';
            if (items.length === 0 || totalWeight === 0) {
                summaryContainer.innerHTML = '<div style="color: var(--text-secondary); text-align: center; padding: 1.5rem;">Нет вариантов для расчета шансов</div>';
                return;
            }

            items.forEach(item => {
                const pct = ((item.weight / totalWeight) * 100).toFixed(1);
                
                const div = document.createElement('div');
                div.className = 'summary-item';
                div.innerHTML = `
                    <div class="summary-item-header">
                        <span style="font-weight: 600; color: #e2e8f0;">${item.name}</span>
                        <span style="font-weight: 700; color: ${item.color};">${pct}%</span>
                    </div>
                    <div class="summary-progress-bg">
                        <div class="summary-progress-bar" style="width: ${pct}%; background-color: ${item.color}; box-shadow: 0 0 10px ${item.color}44;"></div>
                    </div>
                `;
                summaryContainer.appendChild(div);
            });
        }

        function updateSpeedLabel(val) {
            const labels = {
                1: "Медленная (10с)",
                2: "Умеренная (8с)",
                3: "Обычная (6.5с)",
                4: "Быстрая (4с)",
                5: "Молниеносная (2с)"
            };
            document.getElementById('speed-display-label').textContent = labels[val] || "Обычная";
        }

        // Вызов при инициализации
        document.addEventListener('DOMContentLoaded', () => {
            calculateChances();
            updateSpeedLabel(document.getElementById('spin_speed_slider').value);
        });
    </script>
</body>

</html>
