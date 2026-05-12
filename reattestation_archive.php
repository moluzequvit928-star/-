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
        }
    }

    // Создаем таблицу с правильной структурой
    $pdo->exec("CREATE TABLE IF NOT EXISTS reattestations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        discord_id VARCHAR(50) NOT NULL,
        discord_nickname VARCHAR(100) NOT NULL,
        curator VARCHAR(100) NOT NULL,
        result VARCHAR(20) NOT NULL,
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
                    <a href="reattestation.php" class="btn-logout-premium" style="background: rgba(99, 102, 241, 0.1); color: #A78BFA; border: 1px solid rgba(99, 102, 241, 0.2);">
                        <i class="fas fa-list"></i> Результаты переаттестации
                    </a>
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($archive)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 5rem; color: #64748B;">
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
