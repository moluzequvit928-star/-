<?php
session_start();
require_once 'db.php'; // Подключение к базе данных

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'user_header.php';

$message = '';
$messageType = '';

if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $message = 'Отчет успешно сохранен!';
    $messageType = 'success';
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidate = trim($_POST['candidate'] ?? '');
    $candidate_nickname = trim($_POST['candidate_nickname'] ?? '');
    $invited = isset($_POST['invited']) ? 1 : 0;
    $comment = trim($_POST['comment'] ?? '');
    $master_name = $_SESSION['username'] ?? 'Неизвестный мастер';

    $newFileName = '';
    $rollbackFileName = '';
    $uploadSuccess = true;
    $fileType = null;
    $fileSize = null;

    // Обработка скриншота/изображения отчета
    if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['report_file']['tmp_name'];
        $fileName = $_FILES['report_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Разрешенные форматы для скриншотов
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // Проверяем расширение
        if (!in_array($fileExtension, $allowedExts)) {
            $uploadSuccess = false;
            $message = 'Неподдерживаемый формат изображения. Используйте: JPG, PNG, GIF, WebP';
            $messageType = 'error';
        } else {
            // Генерация уникального имени
            $newFileName = uniqid('report_') . '.' . ($fileExtension ?: 'png');
            $uploadFileDir = __DIR__ . '/uploads/';

            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0777, true);
            }

            $dest_path = $uploadFileDir . $newFileName;

            if (!move_uploaded_file($fileTmpPath, $dest_path)) {
                $uploadSuccess = false;
                $message = 'Произошла ошибка при сохранении скриншота.';
                $messageType = 'error';
            }
        }
    } elseif (!$invited) {
        $uploadSuccess = false;
        $message = 'Пожалуйста, прикрепите скриншот.';
        $messageType = 'error';
    }

    // Обработка файла отката (архива) - опционально
    if ($uploadSuccess && isset($_FILES['rollback_file']) && $_FILES['rollback_file']['error'] === UPLOAD_ERR_OK) {
        $rollbackTmpPath = $_FILES['rollback_file']['tmp_name'];
        $rollbackFileName_orig = $_FILES['rollback_file']['name'];
        $rollbackExtension = strtolower(pathinfo($rollbackFileName_orig, PATHINFO_EXTENSION));

        // Разрешенные форматы для откатов/архивов
        $allowedArchiveExts = ['zip', 'rar', '7z', 'tar', 'gz', 'tar.gz', 'tgz', 'tar.bz2', 'tbz2'];
        
        // Проверяем расширение
        if (!in_array($rollbackExtension, $allowedArchiveExts)) {
            // Пытаемся определить по полному имени для .tar.gz и т.д.
            $basename_parts = explode('.', $rollbackFileName_orig);
            $combined_ext = implode('.', array_slice($basename_parts, -2));
            if (!in_array($combined_ext, $allowedArchiveExts)) {
                $message = 'Неподдерживаемый формат архива. Используйте: ZIP, RAR, 7Z, TAR, GZ, TAR.GZ и т.д.';
                $messageType = 'warning';
            }
        }
        
        if ($messageType !== 'warning') {
            $fileSize = filesize($rollbackTmpPath);
            $maxSize = 500 * 1024 * 1024; // 500 MB
            
            if ($fileSize > $maxSize) {
                $uploadSuccess = false;
                $message = 'Файл отката слишком большой. Максимум: 500 MB';
                $messageType = 'error';
            } else {
                // Генерация уникального имени для отката
                $rollbackFileName = uniqid('rollback_') . '.' . $rollbackExtension;
                $rollbackFileDir = __DIR__ . '/rollbacks/';

                if (!is_dir($rollbackFileDir)) {
                    mkdir($rollbackFileDir, 0777, true);
                }

                $rollback_dest_path = $rollbackFileDir . $rollbackFileName;

                if (!move_uploaded_file($rollbackTmpPath, $rollback_dest_path)) {
                    $uploadSuccess = false;
                    $message = 'Произошла ошибка при сохранении файла отката.';
                    $messageType = 'error';
                } else {
                    $fileType = $rollbackExtension;
                }
            }
        }
    }

    if ($uploadSuccess) {
        // ЗАЩИТА ОТ ДУБЛИКАТОВ (АНТИ-СПАМ 10 секунд)
        $stmtCheck = $pdo->prepare("SELECT id FROM reports WHERE master_name = ? AND candidate_id = ? AND created_at > (NOW() - INTERVAL 10 SECOND) LIMIT 1");
        $stmtCheck->execute([$master_name, $candidate]);
        if ($stmtCheck->fetch()) {
            header('Location: reports.php?status=success'); // Пропускаем молча, так как это дубль
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO reports (master_name, candidate_id, candidate_nickname, invited, screenshot_path, comment, rollback_file, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$master_name, $candidate, $candidate_nickname, $invited, $newFileName, $comment, $rollbackFileName, $fileType, $fileSize])) {
                header('Location: reports.php?status=success');
                exit;
            } else {
                $message = 'Ошибка сохранения в базу данных.';
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            $stmt = $pdo->prepare("INSERT INTO reports (master_name, candidate_id, invited, screenshot_path, comment, rollback_file, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$master_name, $candidate, $invited, $newFileName, $comment, $rollbackFileName, $fileType, $fileSize])) {
                header('Location: reports.php?status=success');
                exit;
            } else {
                $message = 'Ошибка сохранения в базу данных.';
                $messageType = 'error';
            }
        }
    }
}

// Загрузка последних отчетов текущего пользователя для отображения
$currentUsername = $_SESSION['username'] ?? '';

// Статистика за неделю (пн 00:00 - вс 23:59), ОТКЛОНЕННЫЕ НЕ СЧИТАЮТСЯ
$monday = date('Y-m-d 00:00:00', strtotime('monday this week'));
$sunday = date('Y-m-d 23:59:59', strtotime('sunday this week'));
$stmtWeek = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE master_name = ? AND created_at BETWEEN ? AND ? AND status = 'approved'");
$stmtWeek->execute([$currentUsername, $monday, $sunday]);
$weeklyCount = $stmtWeek->fetchColumn();

// Статистика по статусам
$stmtStats = $pdo->prepare("SELECT status, COUNT(*) FROM reports WHERE master_name = ? GROUP BY status");
$stmtStats->execute([$currentUsername]);
$statsRaw = $stmtStats->fetchAll(PDO::FETCH_KEY_PAIR);
$pendingCount = $statsRaw['pending'] ?? 0;
$approvedCount = $statsRaw['approved'] ?? 0;
$rejectedCount = $statsRaw['rejected'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM reports WHERE master_name = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$currentUsername]);
$recentReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusBadgeForMaster($status)
{
    if ($status === 'approved') {
        return '<span class="status success" style="background: rgba(16, 185, 129, 0.15); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.3);">✅ Одобрено</span>';
    }

    if ($status === 'rejected') {
        return '<span class="status error" style="background: rgba(239, 68, 68, 0.15); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.3);">❌ Отклонено</span>';
    }

    return '<span class="status warning" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3);">⏳ Ожидание проверки</span>';
}

// Данные для сайдбара уже загружены в user_header.php
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отчеты по наборам | Панель</title>
    <link rel="icon" type="image/png" href="favicon_futurama_staff_1776084855108.png">
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <button class="burger-btn" id="burgerBtn" aria-label="Меню">
        <span></span><span></span><span></span>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header glass">
                <h1>Отчеты Мастеров по наборам</h1>
                <div class="user-profile">
                    <div class="avatar-container" style="display: flex; align-items: center; gap: 0.75rem;">
                        <img src="<?= htmlspecialchars($avatar_url) ?>" alt="avatar"
                            style="width: 36px; height: 36px; border-radius: 50%; border: 2px solid rgba(167, 139, 250, 0.5); object-fit: cover;">
                        <span class="username"
                            style="font-weight: 500; color: #A78BFA;"><?= htmlspecialchars($_SESSION['username']) ?> <span style="font-size: 0.75rem; color: #94A3B8; font-weight: 400; margin-left: 5px;">(<?= htmlspecialchars($role_display) ?>)</span></span>
                        <a href="logout.php" class="btn btn-primary"
                            style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: rgba(239, 68, 68, 0.2); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.4);">Выйти</a>
                    </div>
                </div>
            </header>

            <section class="content">
                <?php if ($message): ?>
                    <div class="card glass"
                        style="grid-column: 1 / -1; padding: 1rem; text-align: center; border-color: <?= $messageType === 'success' ? '#10B981' : '#EF4444' ?>;">
                        <strong
                            style="color: <?= $messageType === 'success' ? '#10B981' : '#EF4444' ?>;"><?= htmlspecialchars($message) ?></strong>
                    </div>
                <?php endif; ?>

                <!-- Форма отправки отчета -->
                <div class="card glass" style="grid-column: 1 / -1;">
                    <div class="card-header">
                        <h3>Заполнить отчет по набору (Собеседованию)</h3>
                        <span class="status info"
                            style="background: rgba(99, 102, 241, 0.15); color: #818CF8; border: 1px solid rgba(99, 102, 241, 0.3);">Для
                            Мастеров</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="reportForm" onsubmit="const btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerText = 'Отправка...';" style="display: flex; flex-direction: column; gap: 1rem; max-width: 600px;">
                        <input type="hidden" name="action" value="add">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.2rem; width: 100%;">
                                <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                                    <label for="candidate_nickname" style="font-weight: 500; font-size: 0.95rem; color: var(--text-main);">Ник пользователя</label>
                                    <input type="text" id="candidate_nickname" name="candidate_nickname" required
                                        placeholder="Например: nevermore"
                                        style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255,255,255,0.1); color: white; outline: none; transition: 0.2s; font-size: 0.95rem;"
                                        onfocus="this.style.borderColor='rgba(99, 102, 241, 0.5)'; this.style.background='rgba(99, 102, 241, 0.05)'"
                                        onblur="this.style.borderColor='rgba(255,255,255,0.1)'; this.style.background='rgba(255, 255, 255, 0.03)'">
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                                    <label for="candidate" style="font-weight: 500; font-size: 0.95rem; color: var(--text-main);">Discord ID</label>
                                    <input type="text" id="candidate" name="candidate" required
                                        placeholder="Например: 123456789012345678" pattern="[0-9]+" inputmode="numeric"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                        style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255,255,255,0.1); color: white; outline: none; transition: 0.2s; font-family: monospace; font-size: 0.95rem;"
                                        onfocus="this.style.borderColor='rgba(99, 102, 241, 0.5)'; this.style.background='rgba(99, 102, 241, 0.05)'"
                                        onblur="this.style.borderColor='rgba(255,255,255,0.1)'; this.style.background='rgba(255, 255, 255, 0.03)'">
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 1.2rem 1.5rem; margin-top: 0.5rem; margin-bottom: 0.5rem;">
                                <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                                    <span style="font-weight: 600; color: var(--text-main); font-size: 1.05rem;">Статус кандидата</span>
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">Оставьте включенным, если вы пригласили пользователя на сервер</span>
                                </div>
                                <div id="toggle-switch" onclick="toggleInvited()"
                                    style="position: relative; width: 50px; min-width: 50px; height: 28px; background-color: rgba(255,255,255,0.1); border-radius: 28px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);">
                                    <div id="toggle-knob"
                                        style="position: absolute; top: 2px; left: 2px; width: 22px; height: 22px; background-color: #94A3B8; border-radius: 50%; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                    </div>
                                </div>
                                <input type="checkbox" id="invited" name="invited" style="display: none;">
                            </div>

                            <script>
                                function toggleInvited() {
                                    var cb = document.getElementById('invited');
                                    var track = document.getElementById('toggle-switch');
                                    var knob = document.getElementById('toggle-knob');
                                    var fileInput = document.getElementById('report_file');
                                    var dropZoneText = document.getElementById('drop-zone-text');
                                    cb.checked = !cb.checked;
                                    if (cb.checked) {
                                        track.style.backgroundColor = '#10B981';
                                        track.style.borderColor = '#10B981';
                                        track.style.boxShadow = '0 0 15px rgba(16, 185, 129, 0.4)';
                                        knob.style.transform = 'translateX(22px)';
                                        knob.style.backgroundColor = 'white';
                                        if (fileInput) fileInput.required = false;
                                        if (dropZoneText) {
                                            const p = dropZoneText.querySelector('p');
                                            if (p) p.innerHTML = 'Нажмите, чтобы загрузить файл <br><span style="color:#10B981; font-size: 0.9em; font-weight: 500;">(Необязательно)</span>';
                                        }
                                    } else {
                                        track.style.backgroundColor = 'rgba(255,255,255,0.1)';
                                        track.style.borderColor = 'rgba(255,255,255,0.1)';
                                        track.style.boxShadow = 'inset 0 2px 4px rgba(0,0,0,0.2)';
                                        knob.style.transform = 'translateX(0px)';
                                        knob.style.backgroundColor = '#94A3B8';
                                        if (fileInput) fileInput.required = true;
                                        if (dropZoneText) {
                                            const p = dropZoneText.querySelector('p');
                                            if (p) p.innerHTML = 'Нажмите, чтобы загрузить файл';
                                        }
                                    }
                                }
                            </script>

                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                <label style="font-weight: 500; color: var(--text-main);">Скриншот или файл
                                    отчета:</label>
                                <div id="drop-zone"
                                    style="border: 2px dashed rgba(99, 102, 241, 0.5); border-radius: 8px; padding: 2rem; text-align: center; background: rgba(15, 23, 42, 0.6); cursor: pointer; transition: all 0.2s; position: relative;">
                                    <div id="drop-zone-text">
                                        <p style="color: var(--text-muted); margin-bottom: 0.5rem;">Нажмите, чтобы
                                            загрузить файл</p>
                                        <p style="font-size: 0.85rem; color: #64748B;">или нажмите <kbd
                                                style="background: #1E293B; padding: 2px 6px; border-radius: 4px;">Ctrl+V</kbd>
                                            чтобы вставить скриншот</p>
                                    </div>
                                    <!-- Важно: убран 'required', так как файл ставится через JS (иногда DataTransfer теряет required на некоторых браузерах), но добавлена проверка -->
                                    <input type="file" id="report_file" name="report_file" accept="image/*"
                                        style="display: none;" required>

                                    <!-- Блок превью файла -->
                                    <div id="file-preview-container"
                                        style="display: none; flex-direction: column; gap: 0.5rem; width: 100%;">
                                        <div style="position: relative; width: 100%;">
                                            <img id="image-preview" src="" alt="Превью"
                                                style="width: 100%; max-height: 350px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                                            <button type="button" id="remove-file-btn"
                                                style="position: absolute; top: 8px; right: 8px; background: #EF4444; color: white; border: none; border-radius: 50%; width: 28px; height: 28px; cursor: pointer; font-weight: bold; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.5);">✕</button>
                                        </div>
                                        <span id="file-preview-text"
                                            style="color: #10B981; font-weight: 500; font-size: 0.9rem; text-align: center;"></span>
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                <label style="font-weight: 500; color: var(--text-main);">Файл отката (опционально):
                                    <span style="font-size: 0.85rem; color: #94A3B8; font-weight: 400;">поддерживаются архивы до 500MB</span>
                                </label>
                                <div id="rollback-drop-zone"
                                    style="border: 2px dashed rgba(168, 85, 247, 0.5); border-radius: 8px; padding: 1.5rem; text-align: center; background: rgba(15, 23, 42, 0.6); cursor: pointer; transition: all 0.2s; position: relative;">
                                    <div id="rollback-drop-zone-text">
                                        <p style="color: var(--text-muted); margin-bottom: 0.5rem; font-size: 0.9rem;">📦 Нажмите для загрузки архива отката</p>
                                        <p style="font-size: 0.8rem; color: #64748B;">ZIP • RAR • 7Z • TAR • GZ и т.д.</p>
                                    </div>
                                    <input type="file" id="rollback_file" name="rollback_file" 
                                        accept=".zip,.rar,.7z,.tar,.gz,.tar.gz,.tgz,.tar.bz2,.tbz2"
                                        style="display: none;">

                                    <!-- Блок превью для отката -->
                                    <div id="rollback-preview-container"
                                        style="display: none; flex-direction: column; gap: 0.5rem; width: 100%;">
                                        <div style="position: relative; width: 100%; text-align: center;">
                                            <div style="background: rgba(168, 85, 247, 0.1); border: 1px solid rgba(168, 85, 247, 0.3); border-radius: 8px; padding: 1rem; color: #C4B5FD;">
                                                <div style="font-size: 2rem; margin-bottom: 0.5rem;">📦</div>
                                                <div id="rollback-file-info" style="font-weight: 500;"></div>
                                                <div id="rollback-file-size" style="font-size: 0.85rem; color: #A78BFA; margin-top: 0.25rem;"></div>
                                            </div>
                                            <button type="button" id="remove-rollback-btn"
                                                style="position: absolute; top: 8px; right: 8px; background: #EF4444; color: white; border: none; border-radius: 50%; width: 28px; height: 28px; cursor: pointer; font-weight: bold; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.5);">✕</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                <label for="comment" style="font-weight: 500; color: var(--text-main);">Комментарий
                                    (необязательно):</label>
                                <textarea id="comment" name="comment" rows="3"
                                    style="padding: 0.8rem; border-radius: 8px; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); color: white; outline: none; resize: vertical;"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary"
                                style="margin-top: 1rem; align-self: flex-start;">Отправить отчет</button>
                        </form>
                    </div>
                </div>

                <!-- Таблица последних отчетов -->
                <div class="card glass" style="grid-column: 1 / -1;">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                        <?php if ($current_role === 'master'): ?>
                        <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                            <h3 style="margin: 0;">Мои отчеты</h3>
                            <span style="font-size: 0.9rem; color: #94A3B8; background: rgba(0,0,0,0.2); padding: 0.3rem 0.6rem; border-radius: 6px;">
                                На рассмотрении: <strong style="color: #F59E0B;"><?= $pendingCount ?></strong> | 
                                Одобрено: <strong style="color: #10B981;"><?= $approvedCount ?></strong> | 
                                Отклонено: <strong style="color: #EF4444;"><?= $rejectedCount ?></strong>
                            </span>
                        </div>
                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.4rem;">
                            <span class="status warning" id="reports-status">Синхронизировано</span>
                            <div style="font-size: 0.9rem; color: #E2E8F0; background: rgba(255,255,255,0.05); padding: 0.3rem 0.6rem; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1);">
                                Норма: <strong style="color: <?= $weeklyCount >= 10 ? '#10B981' : '#F59E0B' ?>; font-size: 1.05rem;"><?= $weeklyCount ?>/10</strong> <span style="color:#64748B; font-size: 0.8rem;">(за неделю)</span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                            <h3 style="margin: 0;">Последние отчеты (под моим ником)</h3>
                            <span class="status warning" id="reports-status">Синхронизировано</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <ul class="data-list" id="reports-list">
                            <?php if (count($recentReports) > 0): ?>
                                <?php foreach ($recentReports as $report): ?>
                                    <li style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1.2rem; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; margin-bottom: 0.75rem; transition: background 0.2s; overflow: hidden; position: relative;" onmouseover="this.style.background='rgba(255,255,255,0.04)'" onmouseout="this.style.background='rgba(255,255,255,0.02)'">
                                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                                <strong style="color: white; font-size: 1.1rem; letter-spacing: 0.02em;"><?= htmlspecialchars($report['candidate_nickname'] ?: 'Не указан') ?></strong>
                                                <span style="background: rgba(167,139,250,0.15); color: #C4B5FD; padding: 0.1rem 0.5rem; border-radius: 6px; font-family: monospace; font-size: 0.85rem; border: 1px solid rgba(167,139,250,0.3);">
                                                    ID: <?= htmlspecialchars($report['candidate_id']) ?>
                                                </span>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 1rem; font-size: 0.85rem; flex-wrap: wrap;">
                                                <span style="color: var(--text-muted); display: flex; align-items: center; gap: 0.3rem;">
                                                    <span style="width: 6px; height: 6px; background: #6366F1; border-radius: 50%; display: inline-block;"></span>
                                                    Автор: <span style="color: #A5B4FC; font-weight: 500;"><?= htmlspecialchars($report['master_name']) ?></span>
                                                </span>
                                                <span style="color: <?= $report['invited'] ? '#34D399' : '#F87171' ?>; background: <?= $report['invited'] ? 'rgba(52, 211, 153, 0.1)' : 'rgba(248, 113, 113, 0.1)' ?>; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 500;">
                                                    <?= $report['invited'] ? '✅ Приглашен' : '❌ Не приглашен' ?>
                                                </span>
                                                <?php if ($report['comment']): ?>
                                                    <span style="color: #94A3B8; max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; background: rgba(0,0,0,0.2); padding: 0.2rem 0.6rem; border-radius: 6px;" title="<?= htmlspecialchars($report['comment']) ?>">
                                                        💬 <?= htmlspecialchars($report['comment']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 1.5rem;">
                                            <div style="min-width: 130px; text-align: center;">
                                                <?= getStatusBadgeForMaster($report['status'] ?? 'pending') ?>
                                            </div>
                                            <div style="display: flex; flex-direction: column; gap: 0.5rem; min-width: 200px;">
                                                <?php if ($report['screenshot_path']): ?>
                                                    <a href="#" onclick="openModal('uploads/<?= htmlspecialchars($report['screenshot_path']) ?>'); return false;"
                                                        style="color: white; font-size: 0.9rem; text-decoration: none; padding: 0.5rem 0.8rem; background: rgba(99,102,241,0.6); border: 1px solid rgba(99,102,241,1); border-radius: 6px; transition: all 0.2s; display: inline-block; text-align: center;" onmouseover="this.style.background='rgba(99,102,241,0.8)'; this.style.transform='scale(1.02)'" onmouseout="this.style.background='rgba(99,102,241,0.6)'; this.style.transform='scale(1)'">
                                                        📷 Посмотреть скрин
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color: #64748B; font-size: 0.85rem; font-style: italic; padding: 0.5rem; background: rgba(255,255,255,0.03); border-radius: 6px; display: inline-block; text-align: center;">Без скриншота</span>
                                                <?php endif; ?>
                                                <?php if ($report['rollback_file']): ?>
                                                    <a href="rollbacks/<?= htmlspecialchars($report['rollback_file']) ?>" download
                                                        style="color: white; font-size: 0.9rem; text-decoration: none; padding: 0.5rem 0.8rem; background: rgba(168,85,247,0.6); border: 1px solid rgba(168,85,247,1); border-radius: 6px; transition: all 0.2s; display: inline-block; text-align: center;" onmouseover="this.style.background='rgba(168,85,247,0.8)'; this.style.transform='scale(1.02)'" onmouseout="this.style.background='rgba(168,85,247,0.6)'; this.style.transform='scale(1)'">
                                                        📦 Откат (<?= htmlspecialchars($report['file_type'] ?? 'архив') ?>) - <?= $report['file_size'] ? round($report['file_size']/1024/1024, 1).' MB' : '?' ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>Пока нет ни одного отчета. Будьте первым!</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Модальное окно для картинок -->
    <div id="image-modal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(5px);">
        <span
            style="position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; font-weight: bold; cursor: pointer; transition: 0.2s;"
            onclick="closeModal()" onmouseover="this.style.color='#EF4444'"
            onmouseout="this.style.color='white'">&times;</span>
        <img id="modal-img" src=""
            style="max-width: 90%; max-height: 90%; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
    </div>

    <!-- Script to handle modal -->
    <script>
        function openModal(src) {
            document.getElementById('modal-img').src = src;
            document.getElementById('image-modal').style.display = 'flex';
        }
        function closeModal() {
            document.getElementById('image-modal').style.display = 'none';
        }
        // Закрытие по клику вне картинки
        document.getElementById('image-modal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeModal();
            }
        });
        // Закрытие по кнопке Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });
    </script>

    <!-- Script to handle file uploads and pasting -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dropZone = document.getElementById('drop-zone');
            const dropZoneText = document.getElementById('drop-zone-text');
            const fileInput = document.getElementById('report_file');
            const previewContainer = document.getElementById('file-preview-container');
            const imagePreview = document.getElementById('image-preview');
            const filePreviewText = document.getElementById('file-preview-text');
            const removeBtn = document.getElementById('remove-file-btn');

            // Click to open file dialog, but prevent if clicking on the remove button or image
            dropZone.addEventListener('click', (e) => {
                if (e.target !== removeBtn && e.target !== imagePreview) {
                    fileInput.click();
                }
            });

            // Handle file selection
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    showFile(e.target.files[0]);
                }
            });

            // Remove file
            removeBtn.addEventListener('click', (e) => {
                e.stopPropagation(); // Не открывать окно выбора файлов
                fileInput.value = ''; // Очищаем input

                // Сбрасываем UI
                previewContainer.style.display = 'none';
                dropZoneText.style.display = 'block';
                dropZone.style.borderColor = 'rgba(99, 102, 241, 0.5)';
                dropZone.style.background = 'rgba(15, 23, 42, 0.6)';
            });

            // Handle Ctrl+V paste capability across the document
            document.addEventListener('paste', (e) => {
                if (e.clipboardData && e.clipboardData.files.length > 0) {
                    const pastedFile = e.clipboardData.files[0];
                    if (pastedFile.type.startsWith('image/')) {
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(pastedFile);
                        fileInput.files = dataTransfer.files;
                        showFile(pastedFile);
                    }
                }
            });

            function showFile(file) {
                // Если это картинка, показываем превью
                if (file.type.startsWith('image/')) {
                    const objectUrl = URL.createObjectURL(file);
                    imagePreview.src = objectUrl;
                    imagePreview.onload = () => URL.revokeObjectURL(objectUrl); // Очистка памяти
                    imagePreview.style.display = 'block';
                } else {
                    imagePreview.style.display = 'none';
                }

                dropZoneText.style.display = 'none';
                previewContainer.style.display = 'flex';
                filePreviewText.textContent = `${file.name || 'Скриншот из буфера'} (${(file.size / 1024).toFixed(1)} KB)`;

                dropZone.style.borderColor = '#10B981';
                dropZone.style.background = 'rgba(16, 185, 129, 0.05)';
            }

            // Drag and Drop
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                if (fileInput.files.length === 0) {
                    dropZone.style.borderColor = '#6366F1';
                    dropZone.style.background = 'rgba(99, 102, 241, 0.1)';
                }
            });
            dropZone.addEventListener('dragleave', () => {
                if (fileInput.files.length === 0) {
                    dropZone.style.borderColor = 'rgba(99, 102, 241, 0.5)';
                    dropZone.style.background = 'rgba(15, 23, 42, 0.6)';
                }
            });
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                if (e.dataTransfer.files.length > 0) {
                    const droppedFile = e.dataTransfer.files[0];
                    if (droppedFile.type.startsWith('image/')) {
                        fileInput.files = e.dataTransfer.files;
                        showFile(droppedFile);
                    }
                }
            });

            // ========== ОБРАБОТКА ФАЙЛОВ ОТКАТОВ (АРХИВОВ) ==========
            const rollbackDropZone = document.getElementById('rollback-drop-zone');
            const rollbackDropZoneText = document.getElementById('rollback-drop-zone-text');
            const rollbackFileInput = document.getElementById('rollback_file');
            const rollbackPreviewContainer = document.getElementById('rollback-preview-container');
            const rollbackFileInfo = document.getElementById('rollback-file-info');
            const rollbackFileSize = document.getElementById('rollback-file-size');
            const removeRollbackBtn = document.getElementById('remove-rollback-btn');

            const allowedArchiveTypes = [
                'application/zip',
                'application/x-zip-compressed',
                'application/x-rar-compressed',
                'application/x-7z-compressed',
                'application/gzip',
                'application/x-tar',
                'application/x-gzip',
                'application/x-bzip2'
            ];

            rollbackDropZone.addEventListener('click', (e) => {
                if (e.target !== removeRollbackBtn) {
                    rollbackFileInput.click();
                }
            });

            rollbackFileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleRollbackFile(e.target.files[0]);
                }
            });

            removeRollbackBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                rollbackFileInput.value = '';
                rollbackPreviewContainer.style.display = 'none';
                rollbackDropZoneText.style.display = 'block';
                rollbackDropZone.style.borderColor = 'rgba(168, 85, 247, 0.5)';
                rollbackDropZone.style.background = 'rgba(15, 23, 42, 0.6)';
            });

            function handleRollbackFile(file) {
                const isArchive = file.name.match(/\.(zip|rar|7z|tar|gz|tar\.gz|tgz|tar\.bz2|tbz2)$/i);
                
                if (!isArchive) {
                    alert('Пожалуйста, загрузите архив (ZIP, RAR, 7Z, TAR, GZ и т.д.)');
                    return;
                }

                const maxSize = 500 * 1024 * 1024; // 500 MB
                if (file.size > maxSize) {
                    alert('Файл слишком большой. Максимум: 500 MB');
                    return;
                }

                const sizeInMB = (file.size / 1024 / 1024).toFixed(2);
                rollbackFileInfo.textContent = file.name;
                rollbackFileSize.textContent = `${sizeInMB} MB`;

                rollbackDropZoneText.style.display = 'none';
                rollbackPreviewContainer.style.display = 'flex';
                rollbackDropZone.style.borderColor = '#A78BFA';
                rollbackDropZone.style.background = 'rgba(168, 85, 247, 0.05)';
            }

            // Drag and Drop для откатов
            rollbackDropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                if (rollbackFileInput.files.length === 0) {
                    rollbackDropZone.style.borderColor = '#A78BFA';
                    rollbackDropZone.style.background = 'rgba(168, 85, 247, 0.1)';
                }
            });
            rollbackDropZone.addEventListener('dragleave', () => {
                if (rollbackFileInput.files.length === 0) {
                    rollbackDropZone.style.borderColor = 'rgba(168, 85, 247, 0.5)';
                    rollbackDropZone.style.background = 'rgba(15, 23, 42, 0.6)';
                }
            });
            rollbackDropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                if (e.dataTransfer.files.length > 0) {
                    const droppedFile = e.dataTransfer.files[0];
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(droppedFile);
                    rollbackFileInput.files = dataTransfer.files;
                    handleRollbackFile(droppedFile);
                }
            });
        });
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