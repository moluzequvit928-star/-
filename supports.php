<?php
session_start();

// Проверка авторизации
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
    <title>FUTURAMA STAFF | Список саппортов</title>
    <link rel="icon" type="image/png" href="favicon_futurama_staff_1776084855108.png">
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;700&family=Montserrat:wght@400;600;700&family=Roboto+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Список саппортов</h1>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">Просмотр всех саппортов системы.</p>
                </div>
                
                <div class="user-profile" style="display: flex; align-items: center; gap: 1rem;">
                    <img src="<?= getAvatarUrl($_SESSION['discord_id'], $_SESSION['username']) ?>" 
                         style="width: 38px; height: 38px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1);" 
                         alt="">
                    <a href="logout.php" class="btn-logout-premium">
                        <i class="fas fa-sign-out-alt"></i> Выйти
                    </a>
                </div>
            </header>

            <section class="content">
                <div class="card" style="padding: 4rem 2rem; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 400px;">
                    <div style="background: rgba(167, 139, 250, 0.1); width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #A78BFA; margin-bottom: 2rem;">
                        <i class="fas fa-tools" style="font-size: 3rem;"></i>
                    </div>
                    <h2 style="font-size: 2rem; margin-bottom: 1rem; color: var(--text-primary);">Раздел в разработке</h2>
                    <p style="color: var(--text-secondary); max-width: 500px; line-height: 1.6; font-size: 1.1rem;">
                        Мы работаем над созданием удобного интерфейса для управления и просмотра списка всех саппортов. 
                        Совсем скоро здесь появится полный перечень персонала с их статистикой и статусами.
                    </p>
                    <div style="margin-top: 3rem;">
                        <div style="display: inline-block; padding: 0.5rem 1.5rem; background: rgba(167, 139, 250, 0.05); border: 1px solid rgba(167, 139, 250, 0.2); border-radius: 100px; color: #A78BFA; font-size: 0.9rem; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;">
                            <i class="fas fa-hourglass-half" style="margin-right: 8px;"></i> Скоро открытие
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
