<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'user_header.php';

$role = $_SESSION['role'] ?? 'master';
if ($role !== 'admin' && $role !== 'chief' && $role !== 'curator') {
    header('Location: index.php');
    exit;
}

$targetId = $_GET['id'] ?? '';
$targetNick = $_GET['nick'] ?? 'Неизвестно';
$curator = $_GET['curator'] ?? ($_SESSION['username'] ?? '');

if (!$targetId) {
    header('Location: reattestation.php');
    exit;
}

// Вопросы (15 штук) от пользователя
$questions = [
    [
        "q" => "1. Что такое дискриминация?",
        "a" => "Неравное отношение."
    ],
    [
        "q" => "2. Что такое субординация?",
        "a" => "Означает установленный порядок иерархических отношений между сотрудниками разного уровня в компании. Это строгое служебное подчинение младших старшим."
    ],
    [
        "q" => "3. Пропаганда?",
        "a" => "Это распространение взглядов, фактов, аргументов, а часто — слухов, искаженной информации или заведомо ложных сведений, с целью манипулирования общественным сознанием."
    ],
    [
        "q" => "4. Деструктивные действия?",
        "a" => "Это действия, направленные на разрушение чего-либо посредством введения в заблуждение, агитации, обмана, использования сторонних ресурсов для получения информации о человеке и её дальнейшего распространения."
    ],
    [
        "q" => "5. Аᴦиᴛᴀция?",
        "a" => "Это устная, печатная и наглядная деятельность, воздействующая на сознание и настроение людей с целью побудить их к политическим или другим действиям."
    ],
    [
        "q" => "6. 1488?",
        "a" => "Верность нацизму и расизму.\n\n88 — закодированное приветствие Heil Hitler! (буква H — восьмая в латинском алфавите).\n14 — сокращение от слогана 14 слов (Мы должны обеспечить существование нашего народа и будущее для белых детей)."
    ],
    [
        "q" => "7. 514?",
        "a" => "Код смерти в Китае."
    ],
    [
        "q" => "8. 1-11?",
        "a" => "Цифровой символ, используемый тюремной бандой Арийские рыцари."
    ],
    [
        "q" => "9. С какой по какую проходную действует пкм мут?",
        "a" => "4/10."
    ],
    [
        "q" => "10. Проведи мне голосовую верификацию?",
        "a" => "Навиция своими словами. Должно быть знание про пользовательское соглашение в случае отказа назвать возраст."
    ],
    [
        "q" => "11. Что делать в ситуации если у unverify аватарка гиф где убили паука тапком?",
        "a" => "Пред. Недопуск."
    ],
    [
        "q" => "12. Человек заходит с ником рублю чурок, добавляя, что он собирает древесные изделия. Твои действия и если есть такие такие, то за что именно?",
        "a" => "Спросить что человек подразумевает под словом чурок если древесное изделие, то норм, а если людей то не норм, в таком случае просим сменить."
    ],
    [
        "q" => "13. Человек говорит что большинство иностранцев в России преступники. Выдашь ли недопуск такому человеку? Если да, то почему?",
        "a" => "выдать устное предупреждение за фразы, образы, эмоции, дегуманизирующие или поддерживающие негативные стереотипы, а именно контент, намекающий на то, что представители групп являются преступниками. Если продолжает такое говорить, в таком случае выдаем недопуск по причине: неадекватное поведение."
    ],
    [
        "q" => "14. Модерируем ли мы основные/коренные ники в профиле?",
        "a" => "Нет."
    ],
    [
        "q" => "15. Что делать в случае если саппорт сливает стафф инфу ?",
        "a" => "Записать откат. Скинуть куратору."
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        <?php require_once 'sidebar_v2.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <div style="display:flex; align-items:center; gap:1rem;">
                        <a href="reattestation.php" style="display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:10px; color:#94A3B8; text-decoration:none; font-size:1rem; transition:0.2s;" title="Назад">←</a>
                        <div>
                            <h1>Аттестация: <span style="color: #A78BFA;"><?= htmlspecialchars($targetNick) ?></span></h1>
                            <p>ID: <?= htmlspecialchars($targetId) ?></p>
                        </div>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="logout.php" class="btn-logout-premium"><i class="fas fa-sign-out-alt"></i> Выйти</a>
                </div>
            </header>

            <div class="page-body">
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
            </div>
        </main>
    </div>

    <script>
        const totalQuestionsCount = <?= count($questions) ?>;
        const answers = new Array(totalQuestionsCount).fill(null);

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
                } else if (filled === totalQuestionsCount) {
                    verdict.textContent = 'ПРОШЕЛ';
                    verdict.style.color = '#10B981';
                    verdict.style.textShadow = '0 0 20px rgba(16, 185, 129, 0.4)';
                } else {
                    verdict.textContent = 'В ПРОЦЕССЕ...';
                    verdict.style.color = '#F59E0B';
                }

                if (filled === totalQuestionsCount) {
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
                body.set('answers_json', JSON.stringify(answers));

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