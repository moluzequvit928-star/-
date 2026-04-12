<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'user_header.php';

$curator = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? 'master';

// Создаем таблицу, если её нет (чтобы не было ошибки Column not found)
$pdo->exec("CREATE TABLE IF NOT EXISTS reattestations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    discord_id VARCHAR(50) NOT NULL,
    discord_nickname VARCHAR(100) NOT NULL,
    curator VARCHAR(100) NOT NULL,
    result VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Админы видят всё, остальные только своё (или если админ зашел под кем-то)
if ($role === 'admin' || $role === 'chief' || $role === 'senior_curator') {
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
                <h1>Архив переаттестаций</h1>
                <div class="user-profile">
                    <span class="user-name"><?= htmlspecialchars($curator) ?></span>
                    <span class="user-role"><?= htmlspecialchars($role) ?></span>
                </div>
            </header>

            <section class="content">
                <div class="card glass">
                    <div class="card-header">
                        <h3>История проверок</h3>
                        <span class="badge">Всего: <?= count($archive) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Дата</th>
                                        <th>Никнейм</th>
                                        <th>Discord ID</th>
                                        <th>Куратор</th>
                                        <th>Результат</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($archive)): ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; padding: 2rem; color: #94A3B8;">Архив пока пуст</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($archive as $row): ?>
                                            <tr>
                                                <td><?= date('d.m.Y H:i', strtotime($row['created_at'])) ?></td>
                                                <td style="font-weight: 600; color: #E2E8F0;"><?= htmlspecialchars($row['discord_nickname']) ?></td>
                                                <td style="color: #94A3B8; font-size: 0.85rem;"><?= htmlspecialchars($row['discord_id']) ?></td>
                                                <td><?= htmlspecialchars($row['curator']) ?></td>
                                                <td>
                                                    <span class="status-badge <?= $row['result'] === 'сдал' ? 'status-approved' : 'status-rejected' ?>">
                                                        <?= mb_strtoupper($row['result']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
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
