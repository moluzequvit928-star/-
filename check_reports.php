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

// Обработка действий: очистка отчетов и удаление мастера (по запросу пользователя)
$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'clear_reports') {
        $target = trim($_POST['master'] ?? '');
        if ($target !== '') {
            $stmtDel = $pdo->prepare("DELETE FROM reports WHERE master_name = ?");
            $stmtDel->execute([$target]);
            $message = "Все отчёты мастера «{$target}» удалены.";
            $messageType = 'success';
        } else {
            $message = 'Не указан мастер для очистки отчетов.';
            $messageType = 'error';
        }
    }

    // удаление мастера через этот интерфейс больше не поддерживается
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

// КЭШ НОРМ: Считаем сколько кто сделал за неделю
$weeklyCounts = [];
$mon = date('Y-m-d 00:00:00', strtotime('monday this week'));
$sun = date('Y-m-d 23:59:59', strtotime('sunday this week'));

$stmtCounts = $pdo->prepare("SELECT master_name, COUNT(*) as total FROM reports WHERE status = 'approved' AND created_at BETWEEN ? AND ? GROUP BY master_name");
$stmtCounts->execute([$mon, $sun]);
while ($rowC = $stmtCounts->fetch()) {
    $weeklyCounts[$rowC['master_name']] = $rowC['total'];
}

// КЭШ ВСЕХ НАБОРОВ: общее количество отчетов (всех статусов) по мастеру
$totalCounts = [];
$stmtTotal = $pdo->prepare("SELECT master_name, COUNT(*) as total_all FROM reports GROUP BY master_name");
$stmtTotal->execute();
while ($r = $stmtTotal->fetch()) {
    $totalCounts[$r['master_name']] = $r['total_all'];
}

// Если админ — показать всех мастеров в блоке 'Мои мастера'
if ($role === 'admin') {
    // Берём всех пользователей с ролью master из таблицы users, чтобы видеть и тех, у кого 0 наборов
    $stmtMasters = $pdo->query("SELECT username FROM users WHERE role = 'master' ORDER BY username ASC");
    $allMastersFromUsers = $stmtMasters->fetchAll(PDO::FETCH_COLUMN);
    $myMasters = $allMastersFromUsers;
}

function getStatusBadge($status)
{
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
    <link rel="icon" type="image/png" href="favicon_futurama_staff_1776084855108.png">
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .report-table {
            width: 100%;
            border-collapse: collapse;
            color: var(--text-main);
        }
    
    <!-- Модал для очистки отчётов (глобально) -->
    <div class="modal-overlay" id="clear-reports-modal">
        <div class="modal-box">
            <h3>🧹 Очистить отчёты мастера</h3>
            <p style="color:#94A3B8;">Выберите мастера, чьи отчёты нужно удалить (будет удалено всё):</p>
            <form method="POST">
                <input type="hidden" name="action" value="clear_reports">
                <div style="margin: 0.75rem 0;">
                    <select name="master" style="width:100%; padding:0.6rem; border-radius:8px; background:#0F172A; border:1px solid rgba(255,255,255,0.06); color:white;">
                        <?php foreach ($myMasters as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?> (<?= $totalCounts[$m] ?? 0 ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
                    <button type="button" class="btn-add" style="background: grey;" onclick="document.getElementById('clear-reports-modal').classList.remove('active')">Отмена</button>
                    <button type="submit" class="btn-delete">Удалить отчёты</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('open-clear-modal').addEventListener('click', function () {
            document.getElementById('clear-reports-modal').classList.add('active');
        });
    </script>

        .report-table th,
        .report-table td {
            text-align: left;
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .report-table th {
            font-weight: 600;
            color: #A78BFA;
            border-bottom: 1px solid rgba(99, 102, 241, 0.3);
        }

        .report-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
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

        .btn-approve:hover {
            background: rgba(16, 185, 129, 0.4);
        }

        .btn-reject {
            background: rgba(239, 68, 68, 0.2);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.4);
    </style>
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
                <h1>Проверка Отчетов (Для Кураторов и выше)</h1>
                <div class="user-profile">
                    <div class="avatar-container" style="display: flex; align-items: center; gap: 0.75rem;">
                        <img src="<?= htmlspecialchars($avatar_url) ?>" alt="avatar"
                            style="width: 36px; height: 36px; border-radius: 50%; border: 2px solid rgba(167, 139, 250, 0.5); object-fit: cover;">
                        <span class="username"
                            style="font-weight: 500; color: #A78BFA;"><?= htmlspecialchars($_SESSION['username']) ?>
                            <span
                                style="font-size: 0.75rem; color: #94A3B8; font-weight: 400; margin-left: 5px;">(<?= htmlspecialchars($role_display) ?>)</span></span>
                        <a href="logout.php" class="btn btn-primary"
                            style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: rgba(239, 68, 68, 0.2); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.4);">Выйти</a>
                    </div>
                </div>
    
                    <!-- Удаление мастера убрано по запросу; очистка отчётов доступна в шапке карточки "Все отчеты" -->
            </header>

            <section class="content">
                <?php if ($role === 'curator' || $role === 'admin'): ?>
                    <div class="card glass"
                        style="margin-bottom: 2rem; border-left: 4px solid #A78BFA; grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3 style="font-size: 1.1rem; color: #A78BFA; display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-size: 1.4rem;">👥</span> Мои мастера:
                            </h3>
                            <span class="status info"
                                style="background: rgba(167, 139, 250, 0.1); color: #A78BFA; border: none;">Активен</span>
                        </div>
                            <div class="card-body" style="display: flex; gap: 0.75rem; flex-wrap: wrap; padding-top: 0.5rem;">
                            <?php if (!empty($myMasters)): ?>
                                <?php foreach ($myMasters as $mNick): 
                                    $totalForMaster = $totalCounts[$mNick] ?? 0;
                                ?>
                                    <div style="background: rgba(167, 139, 250, 0.05); color: #F1F5F9; padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid rgba(167, 139, 250, 0.15); font-size: 0.95rem; font-weight: 500; display: flex; flex-direction: column; gap: 0.4rem; min-width: 200px;">
                                        <div style="display:flex; align-items:center; gap:0.5rem; justify-content: space-between;">
                                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                                <div style="width: 8px; height: 8px; background: #10B981; border-radius: 50%; box-shadow: 0 0 8px rgba(16, 185, 129, 0.4);"></div>
                                                <div style="font-weight:600; color:#EDE9FE;"><?= htmlspecialchars($mNick) ?></div>
                                            </div>
                                        </div>
                                        <div style="font-size:0.85rem; color:#C4B5FD; background: rgba(167,139,250,0.04); padding: 4px 8px; border-radius: 8px; width: fit-content;">
                                            Всего наборов: <strong style="color:#A78BFA; margin-left:6px;"><?= $totalForMaster ?></strong>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color: #64748B; font-style: italic; font-size: 0.9rem;">Мастера не назначены (проверьте таблицу)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card glass" style="grid-column: 1 / -1;">
                    <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <h3 style="margin:0">Все отчеты</h3>
                            <span class="status info"
                                style="background: rgba(99, 102, 241, 0.15); color: #818CF8; border: 1px solid rgba(99, 102, 241, 0.3);">Требуют проверки</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <button id="open-clear-modal" class="btn-delete" style="background: rgba(99,102,241,0.12); border:1px solid rgba(99,102,241,0.18); padding:6px 10px;">🧹 Очистить отчёты</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
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
                                    <?php foreach ($reports as $report):
                                        $mName = $report['master_name'];
                                        $mCount = $weeklyCounts[$mName] ?? 0;
                                        ?>
                                        <tr id="report-row-<?= $report['id'] ?>"
                                            style="transition: opacity 0.3s ease, transform 0.3s ease;">
                                            <td style="font-weight: 500; color: #E2E8F0;">
                                                <?= htmlspecialchars($mName) ?>
                                            </td>
                                            <td style="color: #94A3B8; font-size: 0.9rem;">
                                                <?= date('d.m.Y H:i', strtotime($report['created_at'])) ?></td>
                                            <td style="color: #E2E8F0;">
                                                <strong
                                                    style="color: #A5B4FC;"><?= htmlspecialchars($report['candidate_nickname'] ?: '...') ?></strong>
                                                <br>
                                                <span style="font-family: monospace; color: #64748B; font-size: 0.85rem;">ID:
                                                    <?= htmlspecialchars($report['candidate_id']) ?></span>
                                            </td>
                                            <td><?= $report['invited'] ? '✅ Да' : '❌ Нет' ?></td>
                                            <td>
                                                <a href="#"
                                                    onclick="openModal('uploads/<?= htmlspecialchars($report['screenshot_path']) ?>'); return false;"
                                                    style="color: #6366F1; text-decoration: none;">Посмотреть ↗</a>
                                            </td>
                                            <td id="status-cell-<?= $report['id'] ?>"><?= getStatusBadge($report['status']) ?>
                                            </td>
                                            <td style="color: #94A3B8; font-size: 0.9rem; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                                title="<?= htmlspecialchars($report['comment']) ?>">
                                                <?= $report['comment'] ? htmlspecialchars($report['comment']) : '-' ?>
                                            </td>
                                            <td id="actions-cell-<?= $report['id'] ?>">
                                                <?php if ($report['status'] === 'pending'): ?>
                                                    <div style="display: flex; gap: 0.5rem;">
                                                        <button onclick="updateReportStatus(<?= $report['id'] ?>, 'approved', this)"
                                                            class="action-btn btn-approve">Одобрить</button>
                                                        <button onclick="updateReportStatus(<?= $report['id'] ?>, 'rejected', this)"
                                                            class="action-btn btn-reject">Отклонить</button>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted); font-size: 0.85rem;">Завершено</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8"
                                            style="text-align: center; color: var(--text-muted); padding: 2rem;">Отчетов
                                            пока нет.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            </section>
        </main>
    </div>

    <!-- Модальное окно для картинок (переиспользовано) -->
    <div id="image-modal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(5px);">
        <span
            style="position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; font-weight: bold; cursor: pointer; transition: 0.2s;"
            onclick="closeModal()" onmouseover="this.style.color='#EF4444'"
            onmouseout="this.style.color='white'">&times;</span>
        <img id="modal-img" src=""
            style="max-width: 90%; max-height: 90%; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
    </div>

    <script>
        function updateReportStatus(reportId, status, btn) {
            const formData = new FormData();
            formData.append('report_id', reportId);
            formData.append('status', status);

            const row = document.getElementById('report-row-' + reportId);
            const buttons = row.querySelectorAll('button');
            buttons.forEach(b => b.disabled = true);
            btn.innerText = '⌛...';

            fetch('api.php?action=update_report_status', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Анимация исчезновения
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(20px)';
                        setTimeout(() => {
                            row.remove();

                            // Проверяем, остались ли еще отчеты
                            const tbody = document.querySelector('.report-table tbody');
                            if (tbody.children.length === 0) {
                                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">Отчетов пока нет.</td></tr>';
                            }

                            // Обновляем счетчик в сайдбаре
                            const sidebarLinks = document.querySelectorAll('.sidebar .menu-item');
                            sidebarLinks.forEach(link => {
                                if (link.innerText.includes('Проверка отчетов')) {
                                    const badge = link.querySelector('span:last-child');
                                    if (badge && !isNaN(parseInt(badge.innerText))) {
                                        let count = parseInt(badge.innerText);
                                        if (count > 1) {
                                            badge.innerText = count - 1;
                                        } else {
                                            badge.remove();
                                        }
                                    }
                                }
                            });
                        }, 300);
                    } else {
                        alert('Ошибка: ' + (data.message || 'неизвестная ошибка'));
                        buttons.forEach(b => b.disabled = false);
                        btn.innerText = status === 'approved' ? 'Одобрить' : 'Отклонить';
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Сетевая ошибка при обновлении статуса.');
                    buttons.forEach(b => b.disabled = false);
                });
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