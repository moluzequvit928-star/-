<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'user_header.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FUTURAMA STAFF | Собеседование на саппорта</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;700;800&family=Roboto+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .interview-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
        }

        /* Навигационная панель собеседования */
        .interview-nav-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            height: fit-content;
            position: sticky;
            top: 90px;
        }

        .interview-nav-title {
            font-size: 0.75rem;
            font-weight: 800;
            color: #94A3B8;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 1rem;
            padding-left: 0.5rem;
        }

        .interview-nav-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            list-style: none;
        }

        .interview-nav-btn {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.85rem 1rem;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 14px;
            color: #94A3B8;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-align: left;
            transition: all 0.3s;
        }

        .interview-nav-btn:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.03);
            transform: translateX(3px);
        }

        .interview-nav-btn.active {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15) 0%, rgba(99, 102, 241, 0.15) 100%);
            border-color: rgba(139, 92, 246, 0.3);
            color: #a78bfa;
        }

        /* Прогресс собеседования */
        .progress-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 1.25rem 1.5rem;
            backdrop-filter: blur(10px);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
        }

        .progress-bar-container {
            flex: 1;
        }

        .progress-bar-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            font-weight: 700;
            color: #e2e8f0;
            margin-bottom: 8px;
        }

        .progress-bar-bg {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar-fill {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #8b5cf6 0%, #6366f1 100%);
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(139, 92, 246, 0.3);
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-reset-checklist {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #94A3B8;
            padding: 0.6rem 1.2rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-reset-checklist:hover {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        /* Секции контента */
        .interview-content-wrapper {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .interview-tab-content {
            display: none;
            animation: tabFadeIn 0.4s ease;
        }

        .interview-tab-content.active {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        @keyframes tabFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Интерактивные карточки-чеклисты */
        .checklist-card {
            background: rgba(30, 41, 59, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 20px;
            padding: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .checklist-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: rgba(255, 255, 255, 0.08);
            transition: all 0.3s;
        }

        .checklist-card:hover {
            background: rgba(255, 255, 255, 0.02);
            border-color: rgba(139, 92, 246, 0.2);
            transform: translateX(4px);
        }

        .checklist-card.completed {
            border-color: rgba(34, 197, 94, 0.25);
            background: rgba(34, 197, 94, 0.02);
        }

        .checklist-card.completed::before {
            background: #22c55e;
        }

        .checklist-checkbox {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            border: 2.5px solid #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 2px;
            flex-shrink: 0;
            color: transparent;
            font-size: 0.75rem;
            transition: all 0.2s;
        }

        .checklist-card.completed .checklist-checkbox {
            background: #22c55e;
            border-color: #22c55e;
            color: #fff;
        }

        .checklist-info {
            flex: 1;
        }

        .checklist-title {
            font-weight: 700;
            color: #F8FAFC;
            font-size: 1.05rem;
            margin-bottom: 6px;
            line-height: 1.4;
            transition: color 0.3s;
        }

        .checklist-card.completed .checklist-title {
            color: #a3e635;
        }

        .checklist-desc {
            color: #94A3B8;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        /* Премиум блоки предупреждений */
        .warning-glow-box {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(30, 41, 59, 0.4) 100%);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            box-shadow: 0 0 25px rgba(239, 68, 68, 0.08);
            position: relative;
            overflow: hidden;
            animation: pulseWarning 2s infinite alternate;
        }

        @keyframes pulseWarning {
            from { box-shadow: 0 0 20px rgba(239, 68, 68, 0.05); }
            to { box-shadow: 0 0 35px rgba(239, 68, 68, 0.15); }
        }

        .warning-glow-icon {
            width: 48px;
            height: 48px;
            background: rgba(239, 68, 68, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ef4444;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        /* Копируемые скрипты */
        .copy-script-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 1.25rem;
            margin-top: 1rem;
            position: relative;
        }

        .script-text {
            font-size: 0.88rem;
            color: #cbd5e1;
            line-height: 1.6;
            white-space: pre-wrap;
            font-family: inherit;
        }

        .btn-copy-script {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(99, 102, 241, 0.15);
            color: #a5b4fc;
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-copy-script:hover {
            background: #6366f1;
            color: #fff;
            border-color: #6366f1;
        }

        .command-badge {
            font-family: 'Roboto Mono', monospace;
            background: rgba(139, 92, 246, 0.15);
            color: #c084fc;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid rgba(139, 92, 246, 0.2);
            cursor: copy;
            transition: all 0.2s;
        }

        .command-badge:hover {
            background: #8b5cf6;
            color: #fff;
        }

        @media (max-width: 1024px) {
            .interview-container {
                grid-template-columns: 1fr;
            }
            .interview-nav-card {
                position: static;
                top: 0;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Собеседование саппортов</h1>
                    <p>Интерактивный собесник и справочник по приему персонала</p>
                </div>
                <div class="header-actions">
                    <a href="logout.php" class="btn-logout-premium">
                        <i class="fas fa-sign-out-alt"></i> Выйти
                    </a>
                </div>
            </header>

            <div class="page-body">
                
                <!-- Верхняя карточка прогресса -->
                <div class="progress-card">
                    <div class="progress-bar-container">
                        <div class="progress-bar-label">
                            <span>Прохождение собеседования (по шагам):</span>
                            <span id="progressPercent">0%</span>
                        </div>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" id="progressBarFill"></div>
                        </div>
                    </div>
                    <button class="btn-reset-checklist" onclick="resetChecklist()">
                        <i class="fas fa-arrows-rotate"></i> Сбросить прогресс
                    </button>
                </div>

                <div class="interview-container">
                    
                    <!-- Боковая навигация собеса -->
                    <aside class="interview-nav-card">
                        <div class="interview-nav-title">Этапы собеседования</div>
                        <ul class="interview-nav-list">
                            <li>
                                <button class="interview-nav-btn active" onclick="switchTab('tab1', this)">
                                    <i class="fas fa-info-circle"></i> <span>1. Важная инфо</span>
                                </button>
                            </li>
                            <li>
                                <button class="interview-nav-btn" onclick="switchTab('tab2', this)">
                                    <i class="fas fa-shield-halved"></i> <span>2. Принцип пропуска</span>
                                </button>
                            </li>
                            <li>
                                <button class="interview-nav-btn" onclick="switchTab('tab3', this)">
                                    <i class="fas fa-user-xmark"></i> <span>3. Черные списки</span>
                                </button>
                            </li>
                            <li>
                                <button class="interview-nav-btn" onclick="switchTab('tab4', this)">
                                    <i class="fas fa-question-circle"></i> <span>4. Ситуации & Вопросы</span>
                                </button>
                            </li>
                            <li>
                                <button class="interview-nav-btn" onclick="switchTab('tab5', this)">
                                    <i class="fas fa-square-check"></i> <span>5. Порядок и Скрипты</span>
                                </button>
                            </li>
                            <li>
                                <button class="interview-nav-btn" onclick="switchTab('tab6', this)">
                                    <i class="fas fa-square-poll-horizontal"></i> <span>6. Завершение ИС</span>
                                </button>
                            </li>
                        </ul>
                    </aside>

                    <!-- Основной контент (Вкладки) -->
                    <div class="interview-content-wrapper">
                        
                        <!-- ВКЛАДКА 1: Важная информация -->
                        <div class="interview-tab-content active" id="tab1">
                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Испытательный срок (1 неделя)</div>
                                    <div class="checklist-desc">Перед началом уведомите человека об обязательном испытательном сроке в <b>1 неделю</b>. В случае если он снимется с должности по любой причине, он получит <b>ЧС ветки на 2 недели</b> с причиной <u>“Не пройден ИС”</u>.</div>
                                </div>
                            </div>
                            
                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Свободное время (минимум 2 часа в день)</div>
                                    <div class="checklist-desc">Обязательно уточните у кандидата, есть ли у него **МИНИМУМ 2 часа свободного времени в день**, которые он сможет регулярно проводить в голосовых проходных комнатах.</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Обязательная переаттестация</div>
                                    <div class="checklist-desc">Проинформируйте, что в конце первой недели (испытательного срока) будет проведена обязательная переаттестация кандидата на **1 уровень знаний** для окончательного зачисления.</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Ограничение по пропускам смен</div>
                                    <div class="checklist-desc">Сообщите, что если кандидат на ИС **пропустит более 3 смен за неделю**, он будет немедленно снят за инактив с выдачей **ЧС ветки на 2 недели** (за непройденный ИС).</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Запрет на дабл-стафф (Double Staff)</div>
                                    <div class="checklist-desc">Кандидат должен быть строго предупрежден: если он встанет на должность персонала (стаффа) на других Discord-серверах (за исключением официальных серверов-партнеров), он будет снят с **ЧС ветки за дабл-стафф**.</div>
                                </div>
                            </div>
                        </div>

                        <!-- ВКЛАДКА 2: Принцип пропуска unverify -->
                        <div class="interview-tab-content" id="tab2">
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 1.5rem; color: #cbd5e1; font-size: 0.95rem;">
                                <i class="fas fa-book-open" style="color:#a78bfa; margin-right: 8px;"></i>
                                <strong>Инструкция:</strong> Просто зачитайте основы пропуска unverify-пользователей кандидату.
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Пропускной возраст - 13+ лет</div>
                                    <div class="checklist-desc">Пользоваться Discord разрешено строго с 13 лет. Проходной возраст пользователям **не разглашаем**! Если кандидату меньше 13 лет, в причину недопуска пишем строго названный возраст.</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Шокирующий, сексуальный контент и суицид</div>
                                    <div class="checklist-desc">Discord запрещает любой шокирующий (взрывы, открытые раны, теракты), сексуальный, тошнотворный контент. Также строго запрещена дискриминация (особенно в профиле) и любые призывы к суициду.</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Оскорбительный профиль</div>
                                    <div class="checklist-desc">Запрещено использование оскорбительного профиля. Например, если в разделе "Обо мне" написано нецензурное выражение (например, "пошел нахуй").</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Коммерция на платформе</div>
                                    <div class="checklist-desc">Внутри Discord не приветствуются любые несанкционированные коммерческие действия (продажи, реклама услуг, ссылки на магазины в био).</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Ограничения по оружию, нашивкам и флагам</div>
                                    <div class="checklist-desc">
                                        <ul style="margin-left: 1.25rem; margin-top: 0.5rem; display: flex; flex-direction: column; gap: 8px;">
                                            <li><b>Нашивки:</b> Запрещены абсолютно любые нашивки военных компаний (ЧВК Вагнер и т.д.).</li>
                                            <li><b>Оружие:</b> Запрещено только в тех случаях, когда оно направлено на человека или животных. В остальных нейтральных ракурсах - разрешено.</li>
                                            <li><b>Флаги:</b> Запрещены флаги, попадающие под законодательные и этические запреты (СССР, Третий рейх и т.д.). Флаги обычных стран разрешены обычным пользователям, но строго **запрещены для стаффа (персонала)**.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ВКЛАДКА 3: Черные списки (ВНИМАНИЕ!) -->
                        <div class="interview-tab-content" id="tab3">
                            <div class="warning-glow-box">
                                <div class="warning-glow-icon">
                                    <i class="fas fa-user-lock"></i>
                                </div>
                                <div>
                                    <h3 style="color: #f87171; font-weight: 800; font-size: 1.2rem; margin-bottom: 4px;">КРИТИЧЕСКИЙ БЛОК-ЛИСТ</h3>
                                    <p style="color: #cbd5e1; font-size: 0.9rem; line-height: 1.5;">Нижеуказанных лиц категорически запрещено пропускать или верифицировать при любых условиях!</p>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)" style="border-color: rgba(239, 68, 68, 0.2);">
                                <div class="checklist-checkbox" style="border-color: #ef4444;"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title" style="color: #ef4444; text-shadow: 0 0 10px rgba(239, 68, 68, 0.2);">АРТЁМА ВАВИЛОВА НЕ ПРОПУСКАТЬ!!!!!</div>
                                    <div class="checklist-desc">Личность с именем <b>Артём Вавилов</b> находится в вечном черном списке. Полный запрет на пропуск на сервер.</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)" style="border-color: rgba(239, 68, 68, 0.2);">
                                <div class="checklist-checkbox" style="border-color: #ef4444;"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title" style="color: #ef4444;">555.APK / Модератор Саппортов</div>
                                    <div class="checklist-desc">Пользователя с ником <b>555.APK</b> (иногда может использовать отображаемый никнейм <u>Модератор Саппортов</u>) — **НЕ ВЕРИФИЦИРОВАТЬ!**</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)" style="border-color: rgba(239, 68, 68, 0.2);">
                                <div class="checklist-checkbox" style="border-color: #ef4444;"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title" style="color: #ef4444;">KAMA516 (бес на аватарке)</div>
                                    <div class="checklist-desc">Пользователя с никнеймом <b>KAMA516</b> (ориентир: на аватарке должен быть изображен бес/черт) — **НЕ ВЕРИФИЦИРОВАТЬ!**</div>
                                </div>
                            </div>
                        </div>

                        <!-- ВКЛАДКА 4: Возможные ситуации и Q&A -->
                        <div class="interview-tab-content" id="tab4">
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 1.5rem; color: #cbd5e1; font-size: 0.95rem;">
                                <i class="fas fa-circle-question" style="color:#a78bfa; margin-right: 8px;"></i>
                                <strong>Инструкция:</strong> Задавайте ситуации кандидату. Независимо от его ответа, обязательно объясните ему правильный алгоритм действий!
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Ситуация 1: Человек говорит, что ему 12 лет</div>
                                    <div class="checklist-desc"><b>Решение:</b> Выдать сразу недопуск, указав в причине «12 лет», так как использование Discord разрешено строго с 13 лет.</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Ситуация 2: Человек кидает провокации</div>
                                    <div class="checklist-desc"><b>Решение:</b> Выдать ПКМ мут, попросить успокоиться. Если не прекращает и продолжает вести себя неадекватно — выдать недопуск с причиной: <u>неадекват</u>.</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Ситуация 3: Запрещенный контент в профиле (аватарка, био, баннер)</div>
                                    <div class="checklist-desc"><b>Решение:</b> Попросить сменить, опираясь на правила Discord и правило сервера 3.5. Даем 2 предупреждения. Если отказывается менять — выдаем недопуск с причиной: <u>запретки в профиле</u>.</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Ситуация 4: Человек зашел в проходку и молчит</div>
                                    <div class="checklist-desc"><b>Решение:</b> Просим зайти позже, когда он сможет говорить, так как верификация идет только голосом. Недопуск дается <b>исключительно за джампинг</b> (если пользователь часто перезаходит и молчит).</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Ситуация 5: Реклама / коммерческий био ("продам картошку...")</div>
                                    <div class="checklist-desc"><b>Решение:</b> Объяснить, что коммерция запрещена правилами. Просим скрыть под спойлер или сменить. В случае отказа выдаем недопуск с причиной: <u>коммерция</u>.</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Ситуация 6: Призывы в нике ("погнали вскроемся")</div>
                                    <div class="checklist-desc"><b>Решение:</b> Просим сменить ник. Если не меняет — выдаем недопуск за призыв к суициду в никнейме.</div>
                                </div>
                            </div>
                        </div>

                        <!-- ВКЛАДКА 5: Порядок верификации и Скрипты -->
                        <div class="interview-tab-content" id="tab5">
                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">1. Начальные вопросы</div>
                                    <div class="checklist-desc">Спросите у пользователя: <i>«Больше ли тебе 13 лет?»</i> и <i>«Согласен ли ты с политикой Discord?»</i>.</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">2. Команда верификации</div>
                                    <div class="checklist-desc">
                                        После успешного ответа прописываем команду <span class="command-badge" onclick="copyText('/action')">/action</span> и верифицируем, попутно предлагая провести навигацию по серверу.
                                    </div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">3. Проведение навигации по серверу (Скрипт)</div>
                                    <div class="checklist-desc">
                                        Если пользователь согласился на навигацию, зачитайте или скопируйте следующий текст:
                                        <div class="copy-script-card">
                                            <button class="btn-copy-script" onclick="copyScript(this)">
                                                <i class="far fa-copy"></i> Копировать
                                            </button>
                                            <div class="script-text">Сервер для совместного проведения времени, где присутствуют глобальные мероприятия по типу трибун и ивентов, кинотеатра и творчества. 
Также есть модерируемые войсы, где работают все правила сервера и также дискорда.
Есть личные комнаты, приватные комнаты и лав румы, где ты можешь посидеть с девушкой или подругой. 
Если вы хотите попасть на стафф, у нас есть вкладки Информация, Наборы, где вы можете подать заявку на желаемую должность. Также на сервере присутствует магазин, где вы можете приобрести роли за серверную валюту (/shop). 
Из интересного у нас есть канал селфи, где вы можете выложить свое фото, где его оценят другие люди. 
Плюсом есть канал знакомства, где вы можете найти друга или подругу. 
И для любителей игр есть канал Тиммейты, где вы можете написать анкету и найти себе тиммейта.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">4. Напоминание о команде помощи</div>
                                    <div class="checklist-desc">Обязательно сообщите пользователю: <i>«Если будут вопросы по серверу — пиши в чат слово "корзина" или пиши <span class="command-badge" onclick="copyText('/помощь')">/помощь</span> и задавай вопрос»</i>.</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">5. Технические нюансы смены (для саппорта)</div>
                                    <div class="checklist-desc">
                                        Объясните (кратко):
                                        <ul style="margin-left: 1.25rem; margin-top: 0.5rem; display: flex; flex-direction: column; gap: 8px;">
                                            <li>Как пользоваться <span class="command-badge" onclick="copyText('/action')">/action</span> и копировать ID (режим разработчика).</li>
                                            <li>Рассказать про закреп в чате саппортов, FAQ и канал новостей.</li>
                                            <li><b>Смена:</b> как поставить смену, и напомнить про норму в **2 часа отсидки ПТ минимум**.</li>
                                            <li><b>Размучивание в проходных:</b> В 1, 2 и 3 проходной unverify заходят **сразу с размутом**, а с 4 по 8 проходную саппорт должен размучивать их вручную.</li>
                                            <li><b>Правило 8 саппортов:</b> Правило фуллмута в проходной (Вышестоящие, спонсоры и обычные юзеры слотом не считаются).</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ВКЛАДКА 6: Снятие, отпуска и Завершение -->
                        <div class="interview-tab-content" id="tab6">
                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Правила отгулов и отпусков</div>
                                    <div class="checklist-desc">
                                        <ul style="margin-left: 1.25rem; display: flex; flex-direction: column; gap: 8px;">
                                            <li><b>Отгулы:</b> 1 раз в неделю, максимум на 2 дня.</li>
                                            <li><b>Отпуск:</b> 1 раз в месяц, максимум на 7 дней (в крайних случаях — 2 недели). **В первую неделю работы отпуск брать категорически запрещено**.</li>
                                            <li>При смене основного Discord никнейма саппорт обязан незамедлительно предупредить Вышку.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Переаттестация и ИС</div>
                                    <div class="checklist-desc">Еще раз напомните про переаттестацию через неделю. Если снимется раньше — получит ЧС саппортов за непройденный ИС. 3 пропуска подряд на ИС = снятие.</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)">
                                <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title">Вопросы и поинты</div>
                                    <div class="checklist-desc">Объясните, что по всем вопросам писать строго в чат "саппортики" или вам лично (в личку другим писать только если вы недоступны). Объясните систему поинтов и доп. часов (зачитайте из FAQ).</div>
                                </div>
                            </div>

                            <div class="checklist-card" onclick="toggleCard(this)" style="border-color: rgba(167, 139, 250, 0.3); background: rgba(139, 92, 246, 0.03);">
                                <div class="checklist-checkbox" style="border-color: #a78bfa;"><i class="fas fa-check"></i></div>
                                <div class="checklist-info">
                                    <div class="checklist-title" style="color: #a78bfa;">ОБЯЗАТЕЛЬНЫЙ ФИНАЛЬНЫЙ ШАГ</div>
                                    <div class="checklist-desc" style="color: #e2e8f0; font-weight: 600;">После завершения собеседования ОБЯЗАТЕЛЬНО скажите человеку, чтобы он зашел на ЛОКАЛКУ!</div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

            </div>
        </main>
    </div>

    <script>
        // Инициализация прогресс-бара
        let totalItems = 0;
        let completedItems = 0;

        function updateProgress() {
            const cards = document.querySelectorAll('.checklist-card');
            totalItems = cards.length;
            completedItems = document.querySelectorAll('.checklist-card.completed').length;
            
            const percent = totalItems > 0 ? Math.round((completedItems / totalItems) * 100) : 0;
            
            document.getElementById('progressPercent').textContent = percent + '%';
            document.getElementById('progressBarFill').style.width = percent + '%';
            
            // Сохраняем состояние чекнутых индексов в localStorage
            const completedIndices = [];
            cards.forEach((card, idx) => {
                if (card.classList.contains('completed')) {
                    completedIndices.push(idx);
                }
            });
            localStorage.setItem('interview_checklist', JSON.stringify(completedIndices));
        }

        function toggleCard(card) {
            card.classList.toggle('completed');
            updateProgress();
        }

        function resetChecklist() {
            if (confirm('Вы уверены, что хотите сбросить весь прогресс собеседования?')) {
                document.querySelectorAll('.checklist-card').forEach(card => {
                    card.classList.remove('completed');
                });
                localStorage.removeItem('interview_checklist');
                updateProgress();
            }
        }

        // Переключение вкладок
        function switchTab(tabId, btn) {
            document.querySelectorAll('.interview-tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabId).classList.add('active');

            document.querySelectorAll('.interview-nav-btn').forEach(b => {
                b.classList.remove('active');
            });
            btn.classList.add('active');
            
            // Прокручиваем к началу секции
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Копирование текста скрипта
        function copyScript(btn) {
            const text = btn.nextElementSibling.textContent;
            navigator.clipboard.writeText(text).then(() => {
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Скопировано!';
                btn.style.background = '#22c55e';
                btn.style.color = '#fff';
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.background = '';
                    btn.style.color = '';
                }, 2000);
            }).catch(err => {
                alert('Не удалось скопировать текст: ' + err);
            });
        }

        // Копирование баджей с командами
        function copyText(str) {
            navigator.clipboard.writeText(str).then(() => {
                // Создаем временное плавающее уведомление
                const alertDiv = document.createElement('div');
                alertDiv.style.position = 'fixed';
                alertDiv.style.bottom = '20px';
                alertDiv.style.right = '20px';
                alertDiv.style.background = '#8b5cf6';
                alertDiv.style.color = '#fff';
                alertDiv.style.padding = '12px 24px';
                alertDiv.style.borderRadius = '12px';
                alertDiv.style.fontSize = '0.9rem';
                alertDiv.style.fontWeight = '700';
                alertDiv.style.boxShadow = '0 10px 25px rgba(139, 92, 246, 0.4)';
                alertDiv.style.zIndex = '99999';
                alertDiv.innerHTML = `<i class="fas fa-check-circle"></i> Скопировано: <code>${str}</code>`;
                
                document.body.appendChild(alertDiv);
                setTimeout(() => {
                    alertDiv.style.transition = 'opacity 0.5s';
                    alertDiv.style.opacity = '0';
                    setTimeout(() => alertDiv.remove(), 500);
                }, 2000);
            });
        }

        // Восстановление состояния при загрузке
        document.addEventListener('DOMContentLoaded', () => {
            const savedChecklist = localStorage.getItem('interview_checklist');
            const cards = document.querySelectorAll('.checklist-card');
            
            if (savedChecklist) {
                const indices = JSON.parse(savedChecklist);
                indices.forEach(idx => {
                    if (cards[idx]) {
                        cards[idx].classList.add('completed');
                    }
                });
            }
            updateProgress();
        });
    </script>
</body>
</html>
