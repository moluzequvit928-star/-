<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'user_header.php';

$targetId = $_GET['id'] ?? '';
$targetNick = $_GET['nick'] ?? 'Неизвестно';
$curator = $_GET['curator'] ?? ($_SESSION['username'] ?? '');

if (!$targetId) {
    header('Location: reattestation.php');
    exit;
}

// Вопросы (14 штук) от пользователя
$questions = [
    [
        "q" => "1. К тебе заходит unverify с ником даун, твои действия?",
        "a" => "Попросить поменять, в случае если не меняет, недопуск."
    ],
    [
        "q" => "2. Unverify на верификации говорит что ему 12 лет твои действия?",
        "a" => "Выдать недопуск по причине непроходной возраст."
    ],
    [
        "q" => "3. Unverify рассказывает что он зашел с твинка а основа у него в бане, твои действия?",
        "a" => "Выдать недопуск по причине (обход наказания)."
    ],
    [
        "q" => "4. В проходной начался конфликт между двумя саппортами, твои действия?",
        "a" => "Ни в коем случае не влезать в конфликт, записать откат и показать вышке."
    ],
    [
        "q" => "5. Саппорт начинает угрожать тебе в проходной говоря, что он сольёт твои данные, твои действия?",
        "a" => "Откат, и идти к вышке."
    ],
    [
        "q" => "6. В проходке саппорт начинает ставить красный кружок себе в ник и говорит провести ему верификацию, позже он говорит что это шутка, твои действия?",
        "a" => "Записать откат и к вышке."
    ],
    [
        "q" => "7. Саппорт начинает высказывать критику в сторону вышестоящих, говоря какие они плохие и как плохо выполняют свою работу, твои действия?",
        "a" => "Записать откат и к вышке."
    ],
    [
        "q" => "8. Что такое субординация?",
        "a" => "Это строгое служебное подчинение младших старшим."
    ],
    [
        "q" => "9. Что такое 1488? И разложи это число на две цифровые комбинации.",
        "a" => "1488 — верность нацизму и расизму. 14 — Words отсылка на 'должны защитить cамо существование нашего народа белых детей'. 88 — закодированное приветствие «Heil Hitler!»."
    ],
    [
        "q" => "10. Расшифруй AKIA и AYAK.",
        "a" => "AYAK — Are You A Klansman, приветствие ККK. AKIA — A Klansman I Am, приветствие ККК."
    ],
    [
        "q" => "11. Что такое 9%?",
        "a" => "Сторонники превосходства белой расы."
    ],
    [
        "q" => "12. Расшифруй мне 911, 514, 420.",
        "a" => "911 — башни близнецы, 514 — код смерти в Китае, 420 — код смерти в Китае."
    ],
    [
        "q" => "13. Что такое дискриминация?",
        "a" => "Это неравное отношение к разным людям, которое основано на стереотипных представлениях о различных социальных группах и ограничивает их представителей в правах и свободах."
    ],
    [
        "q" => "14. Скидываем 6 любых аватарок.",
        "a" => "Проверить наличие 6 адекватных аватарок."
    ]
];
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Проведение переаттестации | <?= htmlspecialchars($targetNick) ?></title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .questions-grid {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .q-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .q-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
        }

        .q-card.passed {
            border-color: rgba(16, 185, 129, 0.4);
            background: rgba(16, 185, 129, 0.05);
        }

        .q-card.passed::before {
            background: #10B981;
        }

        .q-card.failed {
            border-color: rgba(239, 68, 68, 0.4);
            background: rgba(239, 68, 68, 0.05);
        }

        .q-card.failed::before {
            background: #EF4444;
        }

        .q-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .q-text {
            font-weight: 700;
            color: #E2E8F0;
            line-height: 1.5;
            font-size: 1.05rem;
        }

        .q-answer {
            background: rgba(15, 23, 42, 0.6);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #94A3B8;
            border-left: 3px solid #6366F1;
            margin-top: 0.5rem;
        }

        .btn-group {
            display: flex;
            gap: 0.4rem;
            width: 280px;
            min-width: 280px;
        }

        .btn-check {
            flex: 1;
            padding: 0.6rem 0.4rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #94A3B8;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 700;
            transition: all 0.2s;
        }

        .btn-check:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .btn-check.active-plus {
            background: #10B981;
            color: white;
            border-color: #10B981;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.4);
        }

        .btn-check.active-part {
            background: #F59E0B;
            color: white;
            border-color: #F59E0B;
            box-shadow: 0 0 15px rgba(245, 158, 11, 0.4);
        }

        .btn-check.active-minus {
            background: #EF4444;
            color: white;
            border-color: #EF4444;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.4);
        }

        .show-answer-btn {
            background: transparent;
            border: none;
            color: #6366F1;
            cursor: pointer;
            font-size: 0.8rem;
            text-align: left;
            padding: 0;
            width: fit-content;
        }

        .show-answer-btn:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <button class="burger-btn" id="burgerBtn" aria-label="Меню">
        <span></span><span></span><span></span>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="dashboard-container">
        <?php require_once 'sidebar.php'; ?>

        <main class="main-content">
            <header class="header glass">
                <div style="display:flex; align-items:center; gap:1.5rem;">
                    <a href="reattestation.php" class="btn btn-primary"
                        style="padding: 0.4rem 0.8rem; background: rgba(255,255,255,0.05); color: #94A3B8; border: 1px solid rgba(255,255,255,0.1);">←
                        Назад</a>
                    <h1>Аттестация: <span style="color: #A78BFA;"><?= htmlspecialchars($targetNick) ?></span></h1>
                </div>
                <div class="user-profile">
                    <span
                        style="color:#94A3B8; font-size:0.9rem; background: rgba(0,0,0,0.2); padding: 0.4rem 0.8rem; border-radius: 6px;">ID:
                        <?= htmlspecialchars($targetId) ?></span>
                </div>
            </header>

            <section class="content">
                <div class="card glass" style="grid-column: 1 / -1;">
                    <div class="card-body">
                        <div class="questions-grid">
                            <?php foreach ($questions as $index => $q): ?>
                                <div class="q-card" id="q-card-<?= $index ?>">
                                    <div class="q-info">
                                        <div class="q-text"><?= htmlspecialchars($q['q']) ?></div>
                                        <div class="q-answer" id="q-answer-<?= $index ?>" style="display: block;">
                                            <strong>Ответ:</strong> <?= htmlspecialchars($q['a']) ?>
                                        </div>
                                    </div>
                                    <div class="btn-group">
                                        <button class="btn-check btn-plus"
                                            onclick="setAnswer(<?= $index ?>, 'plus')">+</button>
                                        <button class="btn-check btn-part"
                                            onclick="setAnswer(<?= $index ?>, 'part')">+-</button>
                                        <button class="btn-check btn-minus"
                                            onclick="setAnswer(<?= $index ?>, 'minus')">-</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div
                            style="margin-top: 3rem; padding: 2rem; background: rgba(15, 23, 42, 0.4); border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 1.5rem;">
                            <div>
                                <h4
                                    style="color:#94A3B8; margin-bottom:0.5rem; text-transform: uppercase; letter-spacing: 1px; font-size: 0.8rem;">
                                    Статус аттестации:</h4>
                                <div id="final-verdict" style="font-size: 1.5rem; font-weight: 800; color: #475569;">
                                    ОЖИДАНИЕ ОТВЕТОВ...</div>
                            </div>
                            <button id="finish-btn" class="btn btn-primary"
                                style="padding: 1rem 2.5rem; font-size: 1.1rem; opacity: 0.3; pointer-events: none; height: fit-content;"
                                onclick="submitResults()">
                                ЗАВЕРШИТЬ ПРОВЕРКУ
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        const answers = new Array(14).fill(null);

        function setAnswer(index, status) {
            answers[index] = status;

            const card = document.getElementById(`q-card-${index}`);
            const btnPlus = card.querySelector('.btn-plus');
            const btnPart = card.querySelector('.btn-part');
            const btnMinus = card.querySelector('.btn-minus');

            card.classList.remove('passed', 'failed');
            btnPlus.classList.remove('active-plus');
            btnPart.classList.remove('active-part');
            btnMinus.classList.remove('active-minus');

            if (status === 'plus' || status === 'part') {
                card.classList.add('passed');
                if (status === 'plus') btnPlus.classList.add('active-plus');
                else btnPart.classList.add('active-part');
            } else {
                card.classList.add('failed');
                btnMinus.classList.add('active-minus');
            }

            updateProgress();
        }

        function updateProgress() {
            const filled = answers.filter(a => a !== null).length;

            // Считаем общее кол-во неправильных (только чистые минусы)
            const scatteredWrong = answers.filter(a => a === 'minus').length;

            // Считаем макс. кол-во минусов подряд
            let maxConsecutiveMinus = 0;
            let currentConsecutive = 0;
            for (const a of answers) {
                if (a === 'minus') {
                    currentConsecutive++;
                    if (currentConsecutive > maxConsecutiveMinus) maxConsecutiveMinus = currentConsecutive;
                } else {
                    currentConsecutive = 0;
                }
            }

            if (filled > 0) {
                const finishBtn = document.getElementById('finish-btn');
                const verdict = document.getElementById('final-verdict');

                // Условия провала
                const failScattered = (scatteredWrong >= 6);
                const failConsecutive = (maxConsecutiveMinus >= 6);

                if (failScattered || failConsecutive) {
                    verdict.textContent = 'НЕ ПРОШЕЛ';
                    verdict.style.color = '#EF4444';
                    verdict.style.textShadow = '0 0 20px rgba(239, 68, 68, 0.4)';
                } else if (filled === 14) {
                    verdict.textContent = 'ПРОШЕЛ';
                    verdict.style.color = '#10B981';
                    verdict.style.textShadow = '0 0 20px rgba(16, 185, 129, 0.4)';
                } else {
                    verdict.textContent = 'В ПРОЦЕССЕ...';
                    verdict.style.color = '#F59E0B';
                }

                if (filled === 14) {
                    finishBtn.style.opacity = '1';
                    finishBtn.style.pointerEvents = 'auto';
                }
            }
        }

        async function submitResults() {
            const filled = answers.filter(a => a !== null).length;
            const passedCount = answers.filter(a => a === 'plus').length;
            const scatteredWrong = answers.filter(a => a === 'minus').length;

            let maxConsecutiveMinus = 0;
            let currentConsecutive = 0;
            for (const a of answers) {
                if (a === 'minus') {
                    currentConsecutive++;
                    if (currentConsecutive > maxConsecutiveMinus) maxConsecutiveMinus = currentConsecutive;
                } else {
                    currentConsecutive = 0;
                }
            }

            const failScattered = (scatteredWrong >= 6);
            const failConsecutive = (maxConsecutiveMinus >= 6);
            const result = (failScattered || failConsecutive) ? 'не сдал' : 'сдал';

            const btn = document.getElementById('finish-btn');
            btn.disabled = true;
            btn.textContent = 'СОХРАНЕНИЕ...';

            try {
                const body = new URLSearchParams();
                body.set('discord_id', '<?= $targetId ?>');
                body.set('discord_nickname', '<?= $targetNick ?>');
                body.set('curator', '<?= $curator ?>');
                body.set('result', result);

                const response = await fetch('api.php?action=set_reattestation_result', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body: body.toString()
                });

                const data = await response.json();
                if (data.success) {
                    alert('Результат переаттестации успешно сохранен!');
                    window.location.href = 'reattestation.php';
                } else {
                    alert('Ошибка при сохранении: ' + (data.error || 'Неизвестная ошибка'));
                }
            } catch (e) {
                alert('Сетевая ошибка: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.textContent = 'ЗАВЕРШИТЬ ПРОВЕРКУ';
            }
        }
    </script>
    <script>
        const burgerBtn = document.getElementById('burgerBtn');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        function toggleMenu() {
            burgerBtn.classList.toggle('open');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        }
        burgerBtn.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', toggleMenu);
    </script>
</body>

</html>