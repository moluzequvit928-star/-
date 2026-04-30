<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'user_header.php';
require_once 'db.php';

// Определяем, чей профиль смотрим
$target_id = $_GET['id'] ?? $_SESSION['discord_id'];
$is_my_profile = ($target_id === $_SESSION['discord_id']);

// Загружаем данные пользователя из БД
$stmt = $pdo->prepare("SELECT * FROM users WHERE discord_id = ?");
$stmt->execute([$target_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Если в БД нет, попробуем поискать в сессии (если это свой профиль)
    if ($is_my_profile) {
        $user = [
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'] ?? 'master',
            'discord_id' => $_SESSION['discord_id'],
            'added_supports_count' => 0,
            'reattestations_count' => 0
        ];
    } else {
        die("Пользователь не найден.");
    }
}

$u_name = $user['username'];
$u_role = $user['role'];
$u_discord = $user['discord_id'];
$u_added = $user['added_supports_count'] ?? 0;
$u_reatt = $user['reattestations_count'] ?? 0;

// Красивое название роли
$role_map = [
    'admin' => 'Администратор',
    'chief' => 'Главный куратор',
    'curator' => 'Куратор',
    'master' => 'Мастер'
];
$u_role_display = $role_map[$u_role] ?? $u_role;

// Аватарка по умолчанию (Dicebear)
$fallback_avatar = "https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($u_name) . "&backgroundColor=b6e3f4,c0aede,d1d4f9";
$u_avatar = $fallback_avatar;

// Если есть бот-мост, пробуем взять реальную аватарку
if (is_numeric($u_discord)) {
    $u_avatar = "http://localhost:3000/avatar?id={$u_discord}";
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль <?= htmlspecialchars($u_name) ?> | Futurama Staff</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            max-width: 900px;
            margin: 0 auto;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .profile-header-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(167, 139, 250, 0.1) 100%);
            border-radius: 32px;
            padding: 3rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            gap: 3rem;
            margin-bottom: 2.5rem;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .profile-header-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: var(--accent);
            filter: blur(120px);
            opacity: 0.15;
        }

        .u-avatar-wrap {
            position: relative;
            z-index: 2;
        }

        .u-avatar-img {
            width: 150px;
            height: 150px;
            border-radius: 40px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            object-fit: cover;
            background: var(--bg-card);
        }

        .u-info { z-index: 2; flex: 1; }
        .u-name-text { font-family: 'Outfit', sans-serif; font-size: 2.5rem; font-weight: 800; color: #fff; margin-bottom: 0.5rem; }
        
        .u-badges { display: flex; gap: 1rem; align-items: center; margin-bottom: 1.5rem; }
        .u-badge {
            padding: 0.5rem 1.2rem;
            background: rgba(99, 102, 241, 0.15);
            color: #818cf8;
            border-radius: 100px;
            font-size: 0.85rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }

        .stats-title { font-family: 'Outfit', sans-serif; font-size: 1.4rem; margin-bottom: 1.5rem; color: #fff; display: flex; align-items: center; gap: 10px; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 2rem;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .stat-card:hover { transform: translateY(-5px); border-color: var(--accent); background: rgba(99, 102, 241, 0.05); }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-info h4 { font-size: 2rem; font-weight: 800; color: #fff; line-height: 1; }
        .stat-info p { color: #94a3b8; font-size: 0.9rem; margin-top: 4px; }

        .btn-edit-profile {
            padding: 0.8rem 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
            cursor: pointer;
        }
        .btn-edit-profile:hover { background: #fff; color: #000; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar_v2.php'; ?>
        
        <main class="main-content">
            <!-- ШАПКА С ВЫХОДОМ -->
            <header class="header glass" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1 style="font-family: 'Outfit', sans-serif; font-size: 2rem; color: #fff;">Профиль</h1>
                <div class="user-profile">
                    <a href="logout.php" class="btn-logout-premium">
                        <i class="fas fa-sign-out-alt"></i> Выйти
                    </a>
                </div>
            </header>

            <div class="profile-container">
                
                <div class="profile-header-card">
                    <div class="u-avatar-wrap">
                        <img src="<?= $u_avatar ?>" 
                             class="u-avatar-img" 
                             alt="Avatar"
                             onerror="this.src='<?= $fallback_avatar ?>'">
                    </div>
                    <div class="u-info">
                        <div class="u-badges">
                            <span class="u-badge"><?= $u_role_display ?></span>
                            <?php if ($is_my_profile): ?>
                                <span class="u-badge" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border-color: rgba(16, 185, 129, 0.2);">Это вы</span>
                            <?php endif; ?>
                        </div>
                        <h1 class="u-name-text"><?= htmlspecialchars($u_name) ?></h1>
                        <p style="color: #94a3b8; margin-bottom: 2rem;">Discord ID: <?= htmlspecialchars($u_discord) ?></p>
                        
                        <?php if ($is_my_profile): ?>
                            <button class="btn-edit-profile" onclick="alert('Редактирование профиля будет доступно скоро!')">
                                <i class="fas fa-user-edit"></i> Изменить профиль
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="stats-title">
                    <i class="fas fa-chart-line" style="color: var(--accent);"></i> Личная статистика
                </div>

                <div class="stats-grid">
                    <?php if (in_array($u_role, ['admin', 'chief', 'curator'])): ?>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
                            <div class="stat-info">
                                <h4><?= $u_reatt ?></h4>
                                <p>Проведено переаттестаций</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array($u_role, ['admin', 'chief', 'curator', 'master'])): ?>
                        <div class="stat-card">
                            <div class="stat-icon" style="color: #fbbf24; background: rgba(251, 191, 36, 0.1);"><i class="fas fa-user-plus"></i></div>
                            <div class="stat-info">
                                <h4><?= $u_added ?></h4>
                                <p>Добавлено саппортов</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <script>
        document.getElementById('burgerBtn')?.addEventListener('click', () => {
            document.querySelector('.sidebar').classList.toggle('open');
        });
    </script>
</body>
</html>
