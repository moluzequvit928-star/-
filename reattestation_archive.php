<?php
session_start();
require_once 'db.php';
require_once 'user_header.php';

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$curator = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? 'master';

// Обычным мастерам здесь делать нечего
if ($role !== 'admin' && $role !== 'chief' && $role !== 'curator') {
    header('Location: index.php');
    exit;
}

// Радикальный фикс: если колонка отсутствует - пересоздаем таблицу полностью
try {
    // Проверяем наличие колонки curator
    $checkTable = $pdo->query("SHOW TABLES LIKE 'reattestations'")->fetch();
    if ($checkTable) {
        $columns = $pdo->query("SHOW COLUMNS FROM reattestations LIKE 'curator'")->fetchAll();
        if (empty($columns)) {
            // Если таблицы есть, но колонки нет - сносим и пересоздаем
            $pdo->exec("DROP TABLE reattestations");
        } else {
            // Если curator есть, проверим и добавим answers_json если её нет
            $checkCol = $pdo->query("SHOW COLUMNS FROM reattestations LIKE 'answers_json'")->fetchAll();
            if (empty($checkCol)) {
                $pdo->exec("ALTER TABLE reattestations ADD COLUMN answers_json TEXT DEFAULT NULL");
            }
        }
    }

    // Создаем таблицу с правильной структурой
    $pdo->exec("CREATE TABLE IF NOT EXISTS reattestations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        discord_id VARCHAR(50) NOT NULL,
        discord_nickname VARCHAR(100) NOT NULL,
        curator VARCHAR(100) NOT NULL,
        result VARCHAR(20) NOT NULL,
        answers_json TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // Если что-то не так
}

// Теперь делаем запрос: АДМИН и ГЛ. КУРАТОР видят всё. Остальные - только своё.
if ($role === 'admin' || $role === 'chief') {
    $stmt = $pdo->prepare("SELECT * FROM reattestations ORDER BY created_at DESC");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT * FROM reattestations WHERE curator = ? ORDER BY created_at DESC");
    $stmt->execute([$curator]);
}
$archive = $stmt->fetchAll(PDO::FETCH_ASSOC);

$questionsList = [
    ["q" => "1. Что такое дискриминация?", "a" => "Неравное отношение."],
    ["q" => "2. Что такое субординация?", "a" => "Означает установленный порядок иерархических отношений между сотрудниками разного уровня в компании. Это строгое служебное подчинение младших старшим."],
    ["q" => "3. Пропаганда?", "a" => "Это распространение взглядов, фактов, аргументов, а часто — слухов, искаженной информации или заведомо ложных сведений, с целью манипулирования общественным сознанием."],
    ["q" => "4. Деструктивные действия?", "a" => "Это действия, направленные на разрушение чего-либо посредством введения в заблуждение, агитации, обмана, использования сторонних ресурсов для получения информации о человеке и её дальнейшего распространения."],
    ["q" => "5. Аᴦиᴛᴀция?", "a" => "Это устная, печатная и наглядная деятельность, воздействующая на сознание и настроение людей с целью побудить их к политическим или другим действиям."],
    ["q" => "6. 1488?", "a" => "Верность нацизму и расизму.\n\n88 — закодированное приветствие Heil Hitler! (буква H — восьмая в латинском алфавите).\n14 — сокращение от слогана 14 слов (Мы должны обеспечить существование нашего народа и будущее для белых детей)."],
    ["q" => "7. 514?", "a" => "Код смерти в Китае."],
    ["q" => "8. 1-11?", "a" => "Цифровой символ, используемый тюремной бандой Арийские рыцари."],
    ["q" => "9. С какой по какую проходную действует пкм мут?", "a" => "4/10."],
    ["q" => "10. Проведи мне голосовую верификацию?", "a" => "Навиция своими словами. Должно быть знание про пользовательское соглашение в случае отказа назвать возраст."],
    ["q" => "11. Что делать в ситуации если у unverify аватарка гиф где убили паука тапком?", "a" => "Пред. Недопуск."],
    ["q" => "12. Человек заходит с ником рублю чурок, добавляя, что он собирает древесные изделия. Твои действия и если есть такие такие, то за что именно?", "a" => "Спросить что человек подразумевает под словом чурок если древесное изделие, то норм, а если людей то не норм, в таком случае просим сменить."],
    ["q" => "13. Человек говорит что большинство иностранцев в России преступники. Выдашь ли недопуск такому человеку? Если да, то почему?", "a" => "выдать устное предупреждение за фразы, образы, эмоции, дегуманизирующие или поддерживающие негативные стереотипы, а именно контент, намекающий на то, что представители групп являются преступниками. Если продолжает такое говорить, в таком случае выдаем недопуск по причине: неадекватное поведение."],
    ["q" => "14. Модерируем ли мы основные/коренные ники в профиле?", "a" => "Нет."],
    ["q" => "15. Что делать в случае если саппорт сливает стафф инфу ?", "a" => "Записать откат. Скинуть куратору."]
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Архив переаттестаций</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .archive-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            overflow: hidden;
            margin-top: 1rem;
            backdrop-filter: blur(10px);
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.02);
        }

        .archive-table {
            width: 100%;
            border-collapse: collapse;
            color: #E2E8F0;
        }

        .archive-table th {
            text-align: left;
            padding: 1rem 1.5rem;
            color: #94A3B8;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 800;
            background: rgba(15, 23, 42, 0.6);
        }

        .archive-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.95rem;
        }

        .archive-table tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .archive-table tr:last-child td {
            border-bottom: none;
        }

        .user-cell {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .user-nick {
            font-weight: 700;
            color: #F8FAFC;
        }

        .user-id {
            font-size: 0.8rem;
            color: #64748B;
            font-family: monospace;
        }

        .status-pill {
            display: inline-flex;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .status-pill.pass {
            background: rgba(16, 185, 129, 0.15);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-pill.fail {
            background: rgba(239, 68, 68, 0.15);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .date-cell {
            color: #94A3B8;
            font-size: 0.9rem;
        }

        .curator-badge {
            background: rgba(99, 102, 241, 0.15);
            color: #A5B4FC;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid rgba(99, 102, 241, 0.2);
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
            <header class="header glass">
                <h1>Архив переаттестаций</h1>
                <div class="user-profile" style="display: flex; gap: 10px;">
                    <a href="logout.php" class="btn-logout-premium">
                        <i class="fas fa-sign-out-alt"></i> Выйти
                    </a>
                </div>
            </header>

            <section class="content">
                <div class="archive-card">
                    <div class="table-header">
                        <h3 style="margin:0; font-size: 1.1rem; color: #F8FAFC; display: flex; align-items: center; gap: 0.75rem;">
                            <span style="font-size: 1.5rem;">📜</span> История всех проверок
                        </h3>
                        <span style="color: #94A3B8; font-size: 0.85rem; background: rgba(0,0,0,0.3); padding: 0.5rem 1rem; border-radius: 10px; border: 1px solid rgba(255,255,255,0.05);">
                            Всего записей: <strong style="color: #A78BFA; font-size: 1rem;"><?= count($archive) ?></strong>
                        </span>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="archive-table">
                            <thead>
                                <tr>
                                    <th>Дата проведения</th>
                                    <th>Результат</th>
                                    <th>Объект (Кандидат)</th>
                                    <th>Проверяющий (Куратор)</th>
                                    <th style="text-align: right;">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($archive)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 5rem; color: #64748B;">
                                            <div style="font-size: 3rem; margin-bottom: 1.5rem; opacity: 0.5;">📂</div>
                                            <div style="font-size: 1.2rem; color: #94A3B8;">Архив пуст</div>
                                            <div style="font-size: 0.9rem; margin-top: 0.5rem;">Как только вы завершите первую проверку, она появится здесь.</div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($archive as $row): ?>
                                        <tr>
                                            <td class="date-cell"><?= date('d.m.Y в H:i', strtotime($row['created_at'])) ?></td>
                                            <td>
                                                <span class="status-pill <?= $row['result'] === 'сдал' ? 'pass' : 'fail' ?>">
                                                    <?= mb_strtoupper($row['result']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="user-cell">
                                                    <span class="user-nick"><?= htmlspecialchars($row['discord_nickname']) ?></span>
                                                    <span class="user-id">ID: <?= htmlspecialchars($row['discord_id']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="curator-badge"><?= htmlspecialchars($row['curator']) ?></span>
                                            </td>
                                            <td style="text-align: right;">
                                                <button class="btn-logout-premium" onclick='showDetails(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="background: rgba(167, 139, 250, 0.1); border: 1px solid rgba(167, 139, 250, 0.3); color: #A78BFA; cursor: pointer; transition: 0.2s; padding: 0.4rem 0.8rem; font-size: 0.8rem; border-radius: 8px;">
                                                    <i class="fas fa-eye"></i> Детали
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Модальное окно деталей переаттестации -->
    <div id="detailsModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(12px); z-index: 1000; align-items: center; justify-content: center; padding: 1.5rem;">
        <div class="modal-content" style="background: rgba(30, 41, 59, 0.85); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 24px; width: 100%; max-width: 700px; padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); position: relative; display: flex; flex-direction: column; gap: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255, 255, 255, 0.08); padding-bottom: 1rem;">
                <div>
                    <h2 style="margin: 0; font-size: 1.3rem; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-file-invoice" style="color: #A78BFA;"></i> Детали аттестации: <span id="modalCandidateName" style="color: #A78BFA;">-</span>
                    </h2>
                    <p style="margin: 4px 0 0 0; font-size: 0.8rem; color: #94A3B8;">ID: <span id="modalCandidateId">-</span> | Дата: <span id="modalDate">-</span></p>
                </div>
                <button onclick="closeDetailsModal()" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; transition: 0.2s; font-size: 1.2rem;">&times;</button>
            </div>
            
            <!-- Сводка с результатами -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 12px; padding: 0.8rem; text-align: center;">
                    <div style="font-size: 0.75rem; color: #34D399; font-weight: 600; text-transform: uppercase;">Верных (+)</div>
                    <div id="statModalPlus" style="font-size: 1.5rem; font-weight: 800; color: #34D399; margin-top: 4px;">0</div>
                </div>
                <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 12px; padding: 0.8rem; text-align: center;">
                    <div style="font-size: 0.75rem; color: #FBBF24; font-weight: 600; text-transform: uppercase;">Частичных (+/-)</div>
                    <div id="statModalPart" style="font-size: 1.5rem; font-weight: 800; color: #FBBF24; margin-top: 4px;">0</div>
                </div>
                <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px; padding: 0.8rem; text-align: center;">
                    <div style="font-size: 0.75rem; color: #F87171; font-weight: 600; text-transform: uppercase;">Неверных (-)</div>
                    <div id="statModalMinus" style="font-size: 1.5rem; font-weight: 800; color: #F87171; margin-top: 4px;">0</div>
                </div>
            </div>

            <!-- Список вопросов -->
            <div id="modalQuestionsList" style="max-height: 380px; overflow-y: auto; padding-right: 5px; display: flex; flex-direction: column; gap: 0.75rem;">
                <!-- Динамически рендерится через JS -->
            </div>
        </div>
    </div>

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

        const questionsList = <?= json_encode($questionsList) ?>;

        function showDetails(row) {
            document.getElementById('modalCandidateName').innerText = row.discord_nickname;
            document.getElementById('modalCandidateId').innerText = row.discord_id;
            
            const dateObj = new Date(row.created_at);
            const dateStr = dateObj.toLocaleDateString('ru-RU') + ' в ' + dateObj.toLocaleTimeString('ru-RU', {hour: '2-digit', minute:'2-digit'});
            document.getElementById('modalDate').innerText = dateStr;

            const qListDiv = document.getElementById('modalQuestionsList');
            qListDiv.innerHTML = '';

            let answers = [];
            try {
                if (row.answers_json) {
                    answers = JSON.parse(row.answers_json);
                }
            } catch(e) {
                console.error(e);
            }

            if (!answers || !answers.length) {
                qListDiv.innerHTML = `
                    <div style="text-align: center; padding: 3rem 1rem; color: #64748B;">
                        <i class="fas fa-info-circle" style="font-size: 2.5rem; color: rgba(167, 139, 250, 0.4); margin-bottom: 1rem; display: block;"></i>
                        <span style="font-size: 0.95rem; display: block; margin-bottom: 0.5rem; font-weight: 600; color: #E2E8F0;">Детали недоступны</span>
                        Данная проверка была проведена до обновления системы.
                    </div>`;
                document.getElementById('statModalPlus').innerText = '-';
                document.getElementById('statModalPart').innerText = '-';
                document.getElementById('statModalMinus').innerText = '-';
            } else {
                let plusCount = 0;
                let partCount = 0;
                let minusCount = 0;

                answers.forEach((ans, index) => {
                    if (ans === 'plus') plusCount++;
                    else if (ans === 'part') partCount++;
                    else if (ans === 'minus') minusCount++;

                    const questionObj = questionsList[index] || { q: `Вопрос ${index + 1}`, a: '—' };
                    
                    let badgeHtml = '';
                    let cardBorderColor = 'rgba(255, 255, 255, 0.05)';
                    let cardBg = 'rgba(255, 255, 255, 0.01)';
                    if (ans === 'plus') {
                        badgeHtml = '<span style="background: rgba(16, 185, 129, 0.15); color: #34D399; border: 1px solid rgba(16, 185, 129, 0.3); padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">ПРАВИЛЬНО</span>';
                        cardBorderColor = 'rgba(16, 185, 129, 0.2)';
                    } else if (ans === 'part') {
                        badgeHtml = '<span style="background: rgba(245, 158, 11, 0.15); color: #FBBF24; border: 1px solid rgba(245, 158, 11, 0.3); padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">ЧАСТИЧНО</span>';
                        cardBorderColor = 'rgba(245, 158, 11, 0.2)';
                    } else {
                        badgeHtml = '<span style="background: rgba(239, 68, 68, 0.15); color: #F87171; border: 1px solid rgba(239, 68, 68, 0.3); padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">НЕПРАВИЛЬНО</span>';
                        cardBorderColor = 'rgba(239, 68, 68, 0.2)';
                    }

                    qListDiv.innerHTML += `
                        <div style="background: ${cardBg}; border: 1px solid ${cardBorderColor}; border-radius: 12px; padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem; transition: 0.2s;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                                <div style="font-weight: 700; color: #E2E8F0; font-size: 0.92rem; line-height: 1.4;">${questionObj.q}</div>
                                ${badgeHtml}
                            </div>
                            <div style="font-size: 0.82rem; color: #94A3B8; background: rgba(15, 23, 42, 0.3); padding: 0.6rem 0.8rem; border-radius: 8px; border-left: 2px solid #818cf8; margin-top: 2px; white-space: pre-line;">
                                <strong>Эталонный ответ:</strong> ${questionObj.a}
                            </div>
                        </div>`;
                });

                document.getElementById('statModalPlus').innerText = plusCount;
                document.getElementById('statModalPart').innerText = partCount;
                document.getElementById('statModalMinus').innerText = minusCount;
            }

            const modal = document.getElementById('detailsModal');
            modal.style.display = 'flex';
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }

        window.addEventListener('click', function(e) {
            const modal = document.getElementById('detailsModal');
            if (e.target === modal) {
                closeDetailsModal();
            }
        });
    </script>
</body>
</html>
