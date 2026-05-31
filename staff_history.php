<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'user_header.php';

$role = $_SESSION['role'] ?? 'master';
if (!in_array($role, ['admin', 'chief', 'curator'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FUTURAMA STAFF | История стафа</title>
    <link rel="icon" type="image/png" href="favicon_futurama_staff_1776084855108.png">
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .wip-card {
            background: rgba(30,41,59,0.4);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 4rem 2rem;
            text-align: center;
            backdrop-filter: blur(10px);
            max-width: 640px;
            margin: 2rem auto;
        }
        .wip-ico {
            width: 88px; height: 88px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(167,139,250,0.15), rgba(99,102,241,0.15));
            border: 1px solid rgba(167,139,250,0.3);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 2.4rem; color: #A78BFA;
            margin-bottom: 1.5rem;
        }
        .wip-title { font-size: 1.6rem; font-weight: 800; color: #fff; margin: 0 0 0.5rem; }
        .wip-sub { color: #94A3B8; font-size: 0.95rem; line-height: 1.5; margin: 0; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>История стафа</h1>
                    <p>Ушедшие с ветки: роль и сколько простояли</p>
                </div>
                <div class="header-actions">
                    <a href="logout.php" class="btn-logout-premium"><i class="fas fa-sign-out-alt"></i> Выйти</a>
                </div>
            </header>

            <div class="page-body">
            <section class="content">
                <div class="wip-card">
                    <div class="wip-ico"><i class="fas fa-tools"></i></div>
                    <h2 class="wip-title">Раздел в разработке</h2>
                    <p class="wip-sub">Функция временно недоступна. Мы дорабатываем её и скоро вернём.</p>
                </div>
            </section>
            </div>
        </main>
    </div>
</body>
</html>
