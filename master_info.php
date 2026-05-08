<?php
session_start();
require_once 'db.php';
require_once 'user_header.php';

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? '';
$current_role = $_SESSION['role'] ?? 'master';

// 1. Считаем общее количество отчетов мастера
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE master_name = ?");
$stmt->execute([$username]);
$totalReports = $stmt->fetchColumn();

// 2. Считаем одобренные и отклоненные для статистики
$stmtApproved = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE master_name = ? AND status = 'approved'");
$stmtApproved->execute([$username]);
$approvedReports = $stmtApproved->fetchColumn();

// Счетчики для боковой панели уже в user_header.php
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Информация | Панель</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        .stat-card {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #A78BFA;
        }
        .stat-label {
            color: #94A3B8;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .curator-box {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: rgba(139, 92, 246, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Информация</h1>
                    <p>Ваша статистика и назначенный куратор</p>
                </div>
                <div class="header-actions">
                    <a href="logout.php" class="btn-logout-premium"><i class="fas fa-sign-out-alt"></i> Выйти</a>
                </div>
            </header>

            <div class="page-body">
            <section class="content">
                <div class="info-grid">
                    <!-- Общая стата -->
                    <div class="card glass stat-card">
                        <span class="stat-label">Всего наборов</span>
                        <span class="stat-value"><?= $totalReports ?></span>
                        <div style="margin-top: auto; font-size: 0.85rem; color: #94A3B8;">
                            Из них одобрено: <span style="color: #10B981; font-weight: 600;"><?= $approvedReports ?></span>
                        </div>
                    </div>

                    <!-- Информация о кураторе -->
                    <div class="card glass">
                        <div class="card-header">
                            <h3>Ваш куратор</h3>
                            <span class="status info">Назначен</span>
                        </div>
                        <div class="card-body">
                            <div id="curator-info" class="curator-box">
                                <div class="spinner"></div>
                                <span style="color: #94A3B8;">Загрузка данных из таблицы...</span>
                            </div>
                            <p style="margin-top: 1rem; color: #94A3B8; font-size: 0.85rem;">
                                Информация подтягивается напрямую из основной Google Таблицы.
                                <br><span style="font-size: 0.75rem; color: #475569;">Поиск для: <?= htmlspecialchars($username) ?></span>
                            </p>
                        </div>
                    </div>
                </div>

            </section>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Подгружаем данные мастера через API
            fetch('api.php?action=master_details')
                .then(response => response.json())
                .then(data => {
                    const curatorBox = document.getElementById('curator-info');
                    if (data.success) {
                        if (data.curator && data.curator !== 'Не назначен') {
                            curatorBox.innerHTML = `
                                <div style="background: #A78BFA; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;">
                                    ${data.curator[0].toUpperCase()}
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #F1F5F9;">${data.curator} ${data.shift ? `<span class="shift-badge">${data.shift}</span>` : ''}</div>
                                    <div style="font-size: 0.8rem; color: #94A3B8;">Ваш куратор</div>
                                </div>
                            `;
                        } else {
                            curatorBox.innerHTML = '<span style="color: #94A3B8;">Куратор не найден (проверьте ник в таблице)</span>';
                        }
                    } else {
                        curatorBox.innerHTML = '<span style="color: #EF4444;">Ошибка: ' + (data.error || 'не удалось загрузить') + '</span>';
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('curator-info').innerHTML = '<span style="color: #EF4444;">Сбой подгрузки</span>';
                });
        });
    </script>
</body>
</html>
