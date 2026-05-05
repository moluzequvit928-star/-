<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Проверка роли (только админ)
if (($_SESSION['role'] ?? 'master') !== 'admin') {
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
            flex: 1;
            min-width: 300px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .controls-box {
            width: 400px;
            background: var(--bg-card);
            border-radius: 24px;
            padding: 2rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
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
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 40px;
            background: #ef4444;
            clip-path: polygon(50% 100%, 0 0, 100% 0);
            z-index: 10;
            filter: drop-shadow(0 2px 5px rgba(0,0,0,0.5));
        }

        .option-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            background: rgba(255,255,255,0.03);
            padding: 10px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .option-item input {
            background: transparent;
            border: none;
            color: white;
            flex: 1;
            font-size: 0.9rem;
            outline: none;
        }

        .btn-add {
            width: 100%;
            padding: 12px;
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent);
            border: 1px dashed var(--accent);
            border-radius: 12px;
            cursor: pointer;
            margin-bottom: 20px;
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-add:hover {
            background: var(--accent);
            color: white;
        }

        .btn-spin {
            width: 100%;
            padding: 15px;
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
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .winner-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: #10b981;
            margin-top: 5px;
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
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">Настройте варианты и испытайте удачу.</p>
                </div>
                
                <div class="user-profile">
                    <img src="<?= $avatar_url ?>" class="avatar" alt="">
                    <span style="font-weight: 700; margin-right: 1rem;"><?= htmlspecialchars($username) ?></span>
                </div>
            </header>

            <div class="wheel-container">
                <div class="wheel-box card">
                    <div class="wheel-pointer"></div>
                    <canvas id="wheel-canvas" width="500" height="500"></canvas>
                    
                    <div id="winner-box" class="winner-display">
                        <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary);">Победитель:</div>
                        <div id="winner-name" class="winner-name">Никто</div>
                    </div>
                </div>

                <div class="controls-box">
                    <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-list-ul" style="color: var(--accent);"></i> Варианты
                    </h3>
                    
                    <div id="options-list">
                        <!-- Options will be here -->
                    </div>

                    <button class="btn-add" onclick="addOption()">
                        <i class="fas fa-plus"></i> Добавить вариант
                    </button>

                    <button id="spin-button" class="btn-spin" onclick="spinWheel()">
                        КРУТИТЬ КОЛЕСО
                    </button>
                    
                    <button class="btn" style="width: 100%; margin-top: 10px; background: rgba(255,255,255,0.05); color: var(--text-secondary);" onclick="resetWheel()">
                        Сбросить
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

        let options = ["Вариант 1", "Вариант 2", "Вариант 3", "Вариант 4", "Вариант 5"];
        let startAngle = 0;
        let arc = Math.PI / (options.length / 2);
        let spinTimeout = null;
        let spinAngleStart = 10;
        let spinTime = 0;
        let spinTimeTotal = 0;

        const colors = [
            "#6366f1", "#8b5cf6", "#ec4899", "#ef4444", "#f59e0b", 
            "#10b981", "#06b6d4", "#3b82f6", "#6d28d9", "#db2777"
        ];

        function drawWheel() {
            ctx.clearRect(0, 0, 500, 500);
            
            const radius = 240;
            const centerX = 250;
            const centerY = 250;

            arc = Math.PI / (options.length / 2);

            for (let i = 0; i < options.length; i++) {
                const angle = startAngle + i * arc;
                ctx.fillStyle = colors[i % colors.length];

                ctx.beginPath();
                ctx.moveTo(centerX, centerY);
                ctx.arc(centerX, centerY, radius, angle, angle + arc, false);
                ctx.lineTo(centerX, centerY);
                ctx.fill();

                // Add text
                ctx.save();
                ctx.fillStyle = "white";
                ctx.translate(centerX + Math.cos(angle + arc / 2) * radius * 0.7, 
                              centerY + Math.sin(angle + arc / 2) * radius * 0.7);
                ctx.rotate(angle + arc / 2 + Math.PI / 2);
                const text = options[i];
                ctx.font = 'bold 16px Inter';
                ctx.fillText(text, -ctx.measureText(text).width / 2, 0);
                ctx.restore();
            }

            // Draw center circle
            ctx.beginPath();
            ctx.arc(centerX, centerY, 40, 0, Math.PI * 2, false);
            ctx.fillStyle = "#1e293b";
            ctx.fill();
            ctx.strokeStyle = "rgba(255,255,255,0.1)";
            ctx.lineWidth = 5;
            ctx.stroke();

            // Center icon or text
            ctx.fillStyle = "white";
            ctx.font = 'bold 12px Inter';
            ctx.fillText("FUTURAMA", centerX - 32, centerY + 5);
        }

        function rotateWheel() {
            spinTime += 30;
            if (spinTime >= spinTimeTotal) {
                stopRotateWheel();
                return;
            }
            const spinAngle = spinAngleStart - easeOut(spinTime, 0, spinAngleStart, spinTimeTotal);
            startAngle += (spinAngle * Math.PI / 180);
            drawWheel();
            spinTimeout = setTimeout(rotateWheel, 30);
        }

        function stopRotateWheel() {
            clearTimeout(spinTimeout);
            const degrees = startAngle * 180 / Math.PI + 90;
            const arcd = arc * 180 / Math.PI;
            const index = Math.floor((360 - degrees % 360) / arcd);
            
            const winner = options[index];
            winnerName.textContent = winner;
            winnerBox.style.display = 'block';
            spinBtn.disabled = false;

            // Celebration
            confetti({
                particleCount: 150,
                spread: 70,
                origin: { y: 0.6 },
                colors: ['#6366f1', '#8b5cf6', '#10b981']
            });
        }

        function easeOut(t, b, c, d) {
            const ts = (t /= d) * t;
            const tc = ts * t;
            return b + c * (tc + -3 * ts + 3 * t);
        }

        function spinWheel() {
            if (options.length === 0) return;
            winnerBox.style.display = 'none';
            // Увеличиваем начальную скорость (было ~10-20, стало ~25-35)
            spinAngleStart = Math.random() * 10 + 25;
            spinTime = 0;
            // Увеличиваем время вращения (было 4-7 сек, стало 8-12 сек)
            spinTimeTotal = Math.random() * 4000 + 8000;
            spinBtn.disabled = true;
            rotateWheel();
        }

        function updateOptions() {
            options = [];
            const inputs = optionsList.querySelectorAll('input');
            inputs.forEach(input => {
                if (input.value.trim() !== "") {
                    options.push(input.value.trim());
                }
            });
            if (options.length === 0) options = ["Пусто"];
            drawWheel();
        }

        function addOption(val = "") {
            const div = document.createElement('div');
            div.className = 'option-item';
            div.innerHTML = `
                <i class="fas fa-grip-lines" style="color: var(--text-muted); cursor: move;"></i>
                <input type="text" value="${val}" placeholder="Введите вариант..." onchange="updateOptions()">
                <button class="btn-danger" style="padding: 5px 10px; border-radius: 8px;" onclick="removeOption(this)">
                    <i class="fas fa-trash-alt"></i>
                </button>
            `;
            optionsList.appendChild(div);
            updateOptions();
        }

        function removeOption(btn) {
            btn.parentElement.remove();
            updateOptions();
        }

        function resetWheel() {
            optionsList.innerHTML = '';
            ["Вариант 1", "Вариант 2", "Вариант 3"].forEach(opt => addOption(opt));
            winnerBox.style.display = 'none';
        }

        // Initialize
        options.forEach(opt => addOption(opt));
        drawWheel();
    </script>
</body>

</html>
