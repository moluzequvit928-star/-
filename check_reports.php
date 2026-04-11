<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Только администратор и куратор могут проверять отчёты
$role = $_SESSION['role'] ?? 'master';
if ($role !== 'admin' && $role !== 'curator') {
    header('Location: reports.php');
    exit;
}

require_once 'user_header.php';
require_once 'staff_functions.php';

// Обработка действий (Одобрить/Отклонить)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['report_id'])) {
    $report_id = (int)$_POST['report_id'];
    $new_status = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
    
    $stmt = $pdo->prepare("UPDATE reports SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $report_id]);
    
    header("Location: check_reports.php");
    exit;
}

// По умолчанию берем все
$whereClause = "";
$params = [];

// Если куратор (не админ!), фильтруем только его мастеров
if ($role === 'curator') {
    $myMasters = getMasterNicksForCurator($_SESSION['username']);
    if (!empty($myMasters)) {
        // Создаем плейсхолдеры (?,?,?)
        $placeholders = implode(',', array_fill(0, count($myMasters), '?'));
        $whereClause = " WHERE master_name IN ($placeholders)";
        $params = $myMasters;
    } else {
        // Если куратор, но мастеров не нашли - не показываем ничего
        $whereClause = " WHERE 1=0"; 
    }
}

// Получаем отчеты (сначала ожидающие, затем новые)
$query = "SELECT * FROM reports" . $whereClause . " ORDER BY status = 'pending' DESC, created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusBadge($status) {
    if ($status === 'approved') {
        return '<span class="status success" style="background: rgba(16, 185, 129, 0.15); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">✅ Одобрено</span>';
    } elseif ($status === 'rejected') {
        return '<span class="status error" style="background: rgba(239, 68, 68, 0.15); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">❌ Отклонено</span>';
    } else {
        return '<span class="status warning" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">⏳ Ожидает проверки</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Проверка отчетов | Панель</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .report-table {
            width: 100%;
            border-collapse: collapse;
            color: var(--text-main);
        }
        .report-table th, .report-table td {
            text-align: left;
            padding: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .report-table th {
            font-weight: 600;
            color: #A78BFA;
            border-bottom: 1px solid rgba(99, 102, 241, 0.3);
        }
        .report-table tr:hover {
            background: rgba(255,255,255,0.02);
        }
        .action-btn {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            border: none;
            transition: 0.2s;
        }
        .btn-approve {
            background: rgba(16, 185, 129, 0.2);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.4);
        }
        .btn-approve:hover { background: rgba(16, 185, 129, 0.4); }
        .btn-reject {
            background: rgba(239, 68, 68, 0.2);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.4);
        }
        .btn-reject:hover { background: rgba(239, 68, 68, 0.4); }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar glass">
            <div class="logo">
                <h2>Панель</h2>
            </div>
            <nav class="menu">
                <a href="index.php" class="menu-item">Главная</a>
                <?php if ($current_role === 'admin'): ?>
                    <a href="admin_stats.php" class="menu-item">Статистика</a>
                <?php endif; ?>
                <?php if ($current_role === 'master'): ?>
                    <a href="master_info.php" class="menu-item">Информация</a>
                <?php endif; ?>
                <a href="reattestation.php" class="menu-item">Переаттестация</a>

                <?php if ($current_role === 'admin' || $current_role === 'curator'): ?>
                <a href="check_reports.php" class="menu-item active" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Проверка отчетов</span>
                    <?php if ($sidebarPendingCount > 0): ?>
                    <span style="background: #EF4444; color: white; font-size: 0.75rem; font-weight: 700; padding: 0.1rem 0.45rem; border-radius: 999px; line-height: 1.6; min-width: 20px; text-align: center;"><?= $sidebarPendingCount ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>

                <div class="menu-section-title">Полезные ссылки</div>
                <a href="https://docs.google.com/spreadsheets/d/1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754/edit?gid=1970062457#gid=1970062457" class="menu-item highlight" target="_blank">Google Таблица ↗</a>
                <a href="https://docs.google.com/document/d/1tef_iQ0GuuIVgQRI15Ql8H74BFPjEcI9Cg3qZCQrtL8/edit?tab=t.0" class="menu-item highlight" target="_blank">Собес на саппорта ↗</a>

                <div class="menu-section-title">Работа</div>
                <a href="reports.php" class="menu-item">Отчеты по наборам</a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="header glass">
                <h1>Проверка Отчетов (Для Кураторов и выше)</h1>
                <div class="user-profile">
                    <div class="avatar-container" style="display: flex; align-items: center; gap: 0.75rem;">
                        <img src="<?= htmlspecialchars($avatar_url) ?>" alt="avatar" style="width: 36px; height: 36px; border-radius: 50%; border: 2px solid rgba(167, 139, 250, 0.5); object-fit: cover;">
                        <span class="username" style="font-weight: 500; color: #A78BFA;"><?= htmlspecialchars($_SESSION['username']) ?> <span style="font-size: 0.75rem; color: #94A3B8; font-weight: 400; margin-left: 5px;">(<?= htmlspecialchars($role_display) ?>)</span></span>
                        <a href="logout.php" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: rgba(239, 68, 68, 0.2); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.4);">Выйти</a>
                    </div>
                </div>
            </header>
            
            <section class="content">
                <div class="card glass" style="grid-column: 1 / -1;">
                    <div class="card-header">
                        <h3>Все отчеты</h3>
                        <span class="status info" style="background: rgba(99, 102, 241, 0.15); color: #818CF8; border: 1px solid rgba(99, 102, 241, 0.3);">Требуют проверки</span>
                    </div>
                    <div class="card-body" style="overflow-x: auto;">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Мастер</th>
                                    <th>Когда отправлено</th>
                                    <th>Кандидат (Ник / ID)</th>
                                    <th>Приглашен</th>
                                    <th>Скриншот</th>
                                    <th>Статус</th>
                                    <th>Комментарий</th>
                                    <th>Действие</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($reports) > 0): ?>
                                    <?php foreach ($reports as $report): ?>
                                        <tr>
                                            <td style="font-weight: 500; color: #E2E8F0;"><?= htmlspecialchars($report['master_name']) ?></td>
                                            <td style="color: #94A3B8; font-size: 0.9rem;"><?= date('d.m.Y H:i', strtotime($report['created_at'])) ?></td>
                                            <td style="color: #E2E8F0;">
                                                <strong style="color: #A5B4FC;"><?= htmlspecialchars($report['candidate_nickname'] ?: '...') ?></strong>
                                                <br>
                                                <span style="font-family: monospace; color: #64748B; font-size: 0.85rem;">ID: <?= htmlspecialchars($report['candidate_id']) ?></span>
                                            </td>
                                            <td><?= $report['invited'] ? '✅ Да' : '❌ Нет' ?></td>
                                            <td>
                                                <a href="#" onclick="openModal('uploads/<?= htmlspecialchars($report['screenshot_path']) ?>'); return false;" style="color: #6366F1; text-decoration: none;">Посмотреть ↗</a>
                                            </td>
                                            <td><?= getStatusBadge($report['status']) ?></td>
                                            <td style="color: #94A3B8; font-size: 0.9rem; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($report['comment']) ?>">
                                                <?= $report['comment'] ? htmlspecialchars($report['comment']) : '-' ?>
                                            </td>
                                            <td>
                                                <?php if ($report['status'] === 'pending'): ?>
                                                    <form method="POST" style="display: flex; gap: 0.5rem;">
                                                        <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                                        <button type="submit" name="action" value="approve" class="action-btn btn-approve">Одобрить</button>
                                                        <button type="submit" name="action" value="reject" class="action-btn btn-reject">Отклонить</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted); font-size: 0.85rem;">Обновлено</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">Отчетов пока нет.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Модальное окно для картинок (переиспользовано) -->
    <div id="image-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(5px);">
        <span style="position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; font-weight: bold; cursor: pointer; transition: 0.2s;" onclick="closeModal()" onmouseover="this.style.color='#EF4444'" onmouseout="this.style.color='white'">&times;</span>
        <img id="modal-img" src="" style="max-width: 90%; max-height: 90%; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
    </div>

    <script>
        function openModal(src) {
            document.getElementById('modal-img').src = src;
            document.getElementById('image-modal').style.display = 'flex';
        }
        function closeModal() {
            document.getElementById('image-modal').style.display = 'none';
        }
        document.getElementById('image-modal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>
