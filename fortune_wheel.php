<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Разрешаем доступ админам, гл. кураторам и кураторам
$u_role = $_SESSION['role'] ?? 'master';
if (!in_array($u_role, ['admin', 'chief', 'curator'])) {
    header('Location: index.php');
    exit;
}

require_once 'db.php';
require_once 'user_header.php';

$configFile = __DIR__ . '/fortune_wheel_config.json';
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
    <title>FUTURAMA STAFF | Колесо Фортуны</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <style>
        .wheel-container {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            margin-top: 1rem;
        }

        .wheel-box {
            flex: 1.2;
            min-width: 350px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 2.5rem !important;
        }

        .controls-box {
            flex: 0.8;
            min-width: 300px;
            background: var(--bg-card);
            border-radius: 24px;
            padding: 2rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
        }

        .wheel-wrapper {
            position: relative;
            display: inline-block;
            margin: 0 auto;
        }

        #wheel-canvas {
            max-width: 100%;
            height: auto;
            border-radius: 50%;
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.5), 0 0 20px var(--accent-glow);
            border: 10px solid #1e293b;
        }

        .wheel-pointer {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 40px;
            background: #ef4444;
            clip-path: polygon(50% 100%, 0 0, 100% 0);
            z-index: 10;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.5));
        }

        .option-item-display {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            background: rgba(255,255,255,0.02);
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.04);
            transition: 0.2s;
        }

        .option-item-display:hover {
            background: rgba(255,255,255,0.04);
            transform: translateX(3px);
        }

        .color-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
        }

        .btn-spin {
            width: 100%;
            padding: 16px;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 15px var(--accent-glow);
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 20px;
        }

        .btn-spin:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px var(--accent-glow);
        }

        .btn-spin:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .winner-display {
            margin-top: 2rem;
            text-align: center;
            padding: 1.5rem;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 16px;
            display: none;
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .winner-name {
            font-size: 1.8rem;
            font-weight: 800;
            color: #10b981;
            margin-top: 5px;
            text-shadow: 0 0 10px rgba(16, 185, 129, 0.3);
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Колесо Фортуны</h1>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">Испытайте удачу и посмотрите, кто или какой приз выпадет сегодня!</p>
                </div>
            </header>

            <div class="wheel-container">
                <div class="wheel-box card">
                    <div class="wheel-wrapper">
                        <div class="wheel-pointer"></div>
                        <canvas id="wheel-canvas" width="500" height="500"></canvas>
                    </div>
                    
                    <div id="winner-box" class="winner-display">
                        <div style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-secondary); font-weight: 600;">Победитель:</div>
                        <div id="winner-name" class="winner-name">Никто</div>
                    </div>
                </div>

                <div class="controls-box">
                    <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
                        <span style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-list-ul" style="color: var(--accent);"></i> Варианты
                        </span>
                        <?php if (in_array($_SESSION['role'] ?? 'master', ['admin', 'chief'])): ?>
                            <a href="fortune_wheel_settings.php" style="font-size: 0.85rem; color: var(--accent); text-decoration: none; display: flex; align-items: center; gap: 5px;">
                                <i class="fas fa-cog"></i> Настроить
                            </a>
                        <?php endif; ?>
                    </h3>
                    
                    <div id="options-list" style="max-height: 320px; overflow-y: auto; padding-right: 5px;">
                        <!-- Наполняется динамически -->
                    </div>

                    <button id="spin-button" class="btn-spin" onclick="spinWheel()">
                        КРУТИТЬ КОЛЕСО
                    </button>
                </div>
            </div>
        </main>
    </div>

    <script>
        const canvas = document.getElementById('wheel-canvas');
        const ctx = canvas.getContext('2d');
        const spinBtn = document.getElementById('spin-button');
        const optionsList = document.getElementById('options-list');
        const winnerBox = document.getElementById('winner-box');
        const winnerName = document.getElementById('winner-name');

        // Загрузка настроек с бэкенда
        const optionsData = <?php echo json_encode($optionsData); ?>;
        const spinSpeed = <?php echo (int)$spinSpeed; ?>;
        
        let startAngle = 0;
        let arc = Math.PI / (optionsData.length / 2);
        
        // Отрисовка колеса
        function drawWheel() {
            ctx.clearRect(0, 0, 500, 500);
            
            const radius = 230;
            const centerX = 250;
            const centerY = 250;

            arc = Math.PI / (optionsData.length / 2);

            for (let i = 0; i < optionsData.length; i++) {
                const angle = startAngle + i * arc;
                ctx.fillStyle = optionsData[i].color;

                ctx.beginPath();
                ctx.moveTo(centerX, centerY);
                ctx.arc(centerX, centerY, radius, angle, angle + arc, false);
                ctx.lineTo(centerX, centerY);
                ctx.fill();

                // Добавление текста
                ctx.save();
                ctx.fillStyle = "white";
                ctx.translate(centerX + Math.cos(angle + arc / 2) * radius * 0.65, 
                              centerY + Math.sin(angle + arc / 2) * radius * 0.65);
                ctx.rotate(angle + arc / 2 + Math.PI / 2);
                
                const text = optionsData[i].name;
                ctx.font = 'bold 14px Inter';
                ctx.shadowColor = "rgba(0,0,0,0.5)";
                ctx.shadowBlur = 4;
                
                // Обрезка текста если слишком длинный
                let displayName = text;
                if (displayName.length > 15) {
                    displayName = displayName.substring(0, 13) + '..';
                }
                ctx.fillText(displayName, -ctx.measureText(displayName).width / 2, 0);
                ctx.restore();
            }

            // Центральный круг
            ctx.beginPath();
            ctx.arc(centerX, centerY, 45, 0, Math.PI * 2, false);
            ctx.fillStyle = "#1e293b";
            ctx.fill();
            
            // Градиентная обводка центрального круга
            ctx.strokeStyle = "rgba(255,255,255,0.15)";
            ctx.lineWidth = 6;
            ctx.stroke();

            // Текст внутри круга
            ctx.fillStyle = "white";
            ctx.font = 'bold 11px Outfit';
            ctx.fillText("FUTURAMA", centerX - 29, centerY + 4);
        }

        // Рендеринг вариантов в панели справа
        function renderOptions() {
            optionsList.innerHTML = '';
            
            optionsData.forEach(opt => {
                const div = document.createElement('div');
                div.className = 'option-item-display';
                div.innerHTML = `
                    <div style="display: flex; align-items: center;">
                        <span class="color-dot" style="background-color: ${opt.color}; box-shadow: 0 0 8px ${opt.color}aa;"></span>
                        <span style="font-weight: 500; color: #f1f5f9;">${opt.name}</span>
                    </div>
                `;
                optionsList.appendChild(div);
            });
        }

        // Кручение колеса (с учетом шансов/весов)
        let animationFrameId = null;
        
        function spinWheel() {
            if (optionsData.length === 0) return;
            winnerBox.style.display = 'none';
            spinBtn.disabled = true;

            // 1. Рассчитываем общий вес
            let totalWeight = 0;
            optionsData.forEach(opt => totalWeight += parseFloat(opt.weight));

            // 2. Выбираем победителя по весу
            const rand = Math.random() * totalWeight;
            let targetIndex = 0;
            let cumulativeWeight = 0;
            
            for (let i = 0; i < optionsData.length; i++) {
                cumulativeWeight += parseFloat(optionsData[i].weight);
                if (rand <= cumulativeWeight) {
                    targetIndex = i;
                    break;
                }
            }

            // 3. Вычисляем финальный угол, при котором выбранный сектор окажется на 12 часах
            arc = Math.PI / (optionsData.length / 2);

            // Определяем длительность анимации и количество полных оборотов на основе скорости
            let duration = 6500; // По умолчанию (скорость 3)
            let rotationsCount = 6;
            if (spinSpeed === 1) {
                duration = 10000;
                rotationsCount = 4;
            } else if (spinSpeed === 2) {
                duration = 8000;
                rotationsCount = 5;
            } else if (spinSpeed === 3) {
                duration = 6500;
                rotationsCount = 6;
            } else if (spinSpeed === 4) {
                duration = 4000;
                rotationsCount = 8;
            } else if (spinSpeed === 5) {
                duration = 2000;
                rotationsCount = 10;
            }

            // 1.5 * Math.PI - 12 часов в canvas. Смещаем на середину выигравшего сектора.
            const targetAngle = 1.5 * Math.PI - (targetIndex * arc + arc / 2) + (Math.PI * 2 * rotationsCount);

            // 4. Запуск премиальной requestAnimationFrame анимации
            const startTime = performance.now();
            const startAngleState = startAngle % (Math.PI * 2);

            function animate(now) {
                const elapsed = now - startTime;
                const progress = Math.min(elapsed / duration, 1);

                // Easing out cubic: progress = 1 - (1 - progress)^3
                const ease = 1 - Math.pow(1 - progress, 3);
                startAngle = startAngleState + ease * (targetAngle - startAngleState);
                
                drawWheel();

                if (progress < 1) {
                    animationFrameId = requestAnimationFrame(animate);
                } else {
                    stopWheelAnimation(targetIndex);
                }
            }

            animationFrameId = requestAnimationFrame(animate);
        }

        function stopWheelAnimation(winnerIndex) {
            cancelAnimationFrame(animationFrameId);
            
            const winner = optionsData[winnerIndex];
            winnerName.textContent = winner.name;
            winnerBox.style.display = 'block';
            spinBtn.disabled = false;

            // Эффект победных конфетти
            confetti({
                particleCount: 150,
                spread: 70,
                origin: { y: 0.6 },
                colors: ['#6366f1', '#8b5cf6', '#ec4899', '#10b981']
            });
        }

        // Инициализация
        renderOptions();
        drawWheel();
    </script>
</body>

</html>
