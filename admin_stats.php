<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || ($_SESSION['role'] ?? 'master') !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once 'user_header.php';

// Общая статистика пользователей
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stmt_roles = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$role_stats = $stmt_roles->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика | FUTURAMA</title>
    <link rel="stylesheet" href="index.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Аналитическая статистика</h1>
                </div>
                <div class="header-actions">
                    <a href="logout.php" class="btn-logout-premium"><i class="fas fa-sign-out-alt"></i> Выйти</a>
                </div>
            </header>

            <div class="page-body">
            <section class="content">
                <div
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
                    <div class="card" style="margin-bottom: 0;">
                        <div style="display: flex; align-items: center; gap: 1.5rem;">
                            <div
                                style="width: 60px; height: 60px; border-radius: 16px; background: rgba(16, 185, 129, 0.1); color: #10B981; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <div style="color: var(--text-secondary); font-size: 0.9rem; font-weight: 500;">Всего
                                    сотрудников</div>
                                <div style="font-size: 2rem; font-weight: 800; color: var(--text-primary);">
                                    <?= $total_users ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Распределение по ролям</h3>
                    </div>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <th
                                        style="text-align: left; padding: 1.25rem; color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase;">
                                        Роль</th>
                                    <th
                                        style="text-align: right; padding: 1.25rem; color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase;">
                                        Кол-во</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($role_stats as $stat):
                                    $role_name = $role_names[$stat['role']] ?? $stat['role'];
                                    ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 1.25rem; font-weight: 600;"><?= htmlspecialchars($role_name) ?>
                                        </td>
                                        <td
                                            style="padding: 1.25rem; text-align: right; font-weight: 700; color: var(--accent);">
                                            <?= $stat['count'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            </div>
        </main>
    </div>
</body>

</html>