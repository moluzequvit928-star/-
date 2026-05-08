<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'user_header.php';
require_once 'db.php';

$target_id = $_GET['id'] ?? null;
$target_nick = $_GET['nick'] ?? null;

// Если ничего не передано, показываем свой профиль
if (!$target_id && !$target_nick) {
    $target_id = $_SESSION['discord_id'] ?? null;
}

$is_my_profile = ($target_id && isset($_SESSION['discord_id']) && $target_id === $_SESSION['discord_id']);

$user = null;
if ($target_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE discord_id = ?");
    $stmt->execute([$target_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($target_nick) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$target_nick]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $target_id = $user['discord_id'];
    }
}

// Если пользователя нет в базе, пробуем найти его в данных из таблицы или создать временный объект
if (!$user) {
    $data = getAllDashboardData($pdo);
    $foundInSheet = null;
    $searchKey = $target_id ?: $target_nick;

    foreach ($data['management'] as $role_key => $members) {
        foreach ($members as $m) {
            if (($target_id && $m['discord_id'] === $target_id) || 
                ($target_nick && mb_strtolower($m['nick']) === mb_strtolower($target_nick))) {
                $foundInSheet = $m;
                $foundInSheet['role_key'] = $role_key;
                break 2;
            }
        }
    }

    if ($foundInSheet) {
        $user = [
            'username' => $foundInSheet['nick'],
            'role' => substr($foundInSheet['role_key'], 0, -1), // убираем 's' на конце (masters -> master)
            'discord_id' => $foundInSheet['discord_id'] ?: ($target_id ?: 'system'),
            'about_me' => 'Этот пользователь еще не заходил в систему.',
            'banner_url' => $foundInSheet['banner'] ?? ''
        ];
        // Корректируем роли
        if ($user['role'] === 'curator') {} // ok
        elseif ($user['role'] === 'master') {} // ok
        elseif ($user['role'] === 'admin') {} // ok
        elseif ($foundInSheet['role_key'] === 'chief') { $user['role'] = 'chief'; }
    } elseif ($is_my_profile) {
        $user = [
            'username' => $_SESSION['username'], 
            'role' => $_SESSION['role'] ?? 'master', 
            'discord_id' => $_SESSION['discord_id'], 
            'about_me' => '', 
            'banner_url' => ''
        ];
    }
}

$u_name = $user['username'] ?? 'Неизвестно';
$u_role = $user['role'] ?? 'master';
$u_discord = $user['discord_id'] ?? 'system';
$u_about = $user['about_me'] ?? '';
$u_banner = $user['banner_url'] ?? '';

$role_map = ['admin' => 'Администратор', 'chief' => 'Главный куратор', 'curator' => 'Куратор', 'master' => 'Мастер'];
$u_role_display = $role_map[$u_role] ?? $u_role;
$u_avatar = getAvatarUrl($u_discord, $u_name);
$reports_count = 0;
if ($u_role === 'master') {
    $stmtR = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE master_name = ?");
    $stmtR->execute([$u_name]);
    $reports_count = $stmtR->fetchColumn();
}

// Считаем устники
$stmtWI = $pdo->prepare("SELECT COUNT(*) FROM warnings WHERE admin_id = ?");
$stmtWI->execute([$u_discord]);
$warnings_issued = $stmtWI->fetchColumn();

$stmtWR = $pdo->prepare("SELECT COUNT(*) FROM warnings WHERE support_id = ?");
$stmtWR->execute([$u_discord]);
$warnings_received = $stmtWR->fetchColumn();

// Командные связи
$team_info = [];
$data = getAllDashboardData($pdo);

if ($u_role === 'master') {
    // Ищем куратора для мастера
    $my_shift = '';
    foreach ($data['management'] as $role_key => $members) {
        foreach ($members as $m) {
            if (mb_strtolower($m['nick']) === mb_strtolower($u_name)) {
                $my_shift = $m['shift'];
                break 2;
            }
        }
    }
    if ($my_shift) {
        foreach ($data['management']['curators'] as $c) {
            if ($c['shift'] === $my_shift) {
                $team_info['curator'] = $c['nick'];
                break;
            }
        }
    }
} elseif ($u_role === 'curator') {
    // Ищем мастеров для куратора
    $my_shifts = [];
    foreach ($data['management']['curators'] as $c) {
        if (mb_strtolower($c['nick']) === mb_strtolower($u_name)) {
            $my_shifts[] = $c['shift'];
        }
    }
    if (!empty($my_shifts)) {
        $my_masters = [];
        foreach ($data['management']['masters'] as $m) {
            if (in_array($m['shift'], $my_shifts)) {
                $my_masters[] = $m['nick'];
            }
        }
        $team_info['masters'] = $my_masters;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль <?= htmlspecialchars($u_name) ?> | Futurama Staff</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;800&family=Montserrat:wght@400;600;700&family=Roboto+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container { max-width: 900px; margin: 0 auto; }
        .profile-header-card {
            background: <?= $u_banner ? "url('$u_banner')" : "linear-gradient(135deg, #0f172a 0%, #1e293b 100%)" ?>;
            background-size: cover; background-position: center; border-radius: 32px; padding: 4rem; border: 1px solid rgba(255, 255, 255, 0.05); display: flex; align-items: center; gap: 4rem; margin-bottom: 3rem; position: relative; overflow: hidden; backdrop-filter: blur(20px); box-shadow: 0 30px 60px rgba(0,0,0,0.5);
        }
        .profile-header-card::after { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(to right, rgba(2, 2, 5, 0.85), rgba(2, 2, 5, 0.4)); z-index: 1; }
        .u-avatar-wrap { position: relative; z-index: 2; }
        .u-avatar-img { width: 180px; height: 180px; border-radius: 48px; border: 6px solid rgba(255, 255, 255, 0.05); object-fit: cover; background: var(--bg-card); box-shadow: 0 15px 35px rgba(0,0,0,0.4); transition: 0.4s; }
        .u-avatar-img:hover { transform: scale(1.02); border-color: var(--accent); }
        .u-info { z-index: 2; flex: 1; }
        .u-name-text { font-family: 'Outfit', sans-serif; font-size: 3.5rem; font-weight: 800; color: #fff; margin-bottom: 0.5rem; letter-spacing: -1px; }
        .u-discord-id { font-size: 1rem; color: var(--text-secondary); opacity: 0.6; font-family: 'Roboto Mono', monospace; margin-bottom: 1.5rem; }
        .u-badge { padding: 0.6rem 1.5rem; background: rgba(99, 102, 241, 0.1); color: #818cf8; border-radius: 100px; font-size: 0.9rem; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; border: 1px solid rgba(99, 102, 241, 0.2); }

        .btn-confirm-save { background: var(--accent); color: #fff; border: none; padding: 0.8rem 1.8rem; border-radius: 14px; font-weight: 700; cursor: pointer; transition: 0.3s; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3); }
        .btn-confirm-save:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(16, 185, 129, 0.4); }

        .u-about-box { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 20px; padding: 1.5rem; margin-bottom: 2rem; }
        .u-about-box h3 { font-size: 1rem; color: var(--text-secondary); margin-bottom: 0.8rem; text-transform: uppercase; letter-spacing: 1px; }
        .u-about-text { color: #fff; line-height: 1.6; font-size: 1rem; white-space: pre-wrap; }

        .btn-profile-action { padding: 0.8rem 1.2rem; background: rgba(255, 255, 255, 0.1); color: #fff; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; font-weight: 600; cursor: pointer; transition: 0.3s; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: var(--bg-card); width: 100%; max-width: 500px; border-radius: 24px; border: 1px solid rgba(255,255,255,0.1); padding: 2rem; }
        .form-control { width: 100%; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 0.8rem 1rem; color: #fff; font-family: inherit; margin-bottom: 1.5rem; }
        
        .banner-zone { border: 2px dashed rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 2rem; display: flex; flex-direction: column; align-items: center; gap: 10px; cursor: pointer; transition: 0.3s; background-size: cover; background-position: center; margin-bottom: 1.5rem; min-height: 120px; }
        .banner-zone:hover { border-color: var(--accent); }
        
        .u-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .u-stat-card { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 20px; padding: 1.5rem; display: flex; flex-direction: column; gap: 0.5rem; transition: 0.3s; }
        .u-stat-card:hover { background: rgba(255, 255, 255, 0.05); transform: translateY(-3px); }
        .u-stat-value { font-size: 1.8rem; font-weight: 800; color: var(--accent); font-family: 'Outfit', sans-serif; }
        .u-stat-label { font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; }

        .u-team-box { background: rgba(139, 92, 246, 0.05); border: 1px solid rgba(139, 92, 246, 0.1); border-radius: 20px; padding: 1.5rem; margin-bottom: 2rem; }
        .u-team-box h3 { font-size: 0.9rem; color: #A78BFA; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 8px; }
        .team-list { display: flex; flex-wrap: wrap; gap: 8px; }
        .team-item { background: rgba(255, 255, 255, 0.05); padding: 0.5rem 1rem; border-radius: 10px; font-size: 0.9rem; color: #fff; border: 1px solid rgba(255, 255, 255, 0.05); }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar_v2.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Профиль</h1>
                    <p><?= htmlspecialchars($u_name) ?></p>
                </div>
                <div class="header-actions">
                    <a href="logout.php" class="btn-logout-premium">
                        <i class="fas fa-sign-out-alt"></i> Выйти
                    </a>
                </div>
            </header>

            <div class="page-body">
            <div class="profile-container">
                <div class="save-bar" id="saveBar" style="display: none;">
                    <div style="color: #10b981; font-weight: 600;"><i class="fas fa-info-circle"></i> У вас есть несохраненные изменения</div>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn-profile-action" style="padding: 0.6rem 1rem; font-size: 0.8rem;" onclick="location.reload()">Сбросить</button>
                        <button class="btn-confirm-save" onclick="saveAllToDB()">Подтвердить изменения</button>
                    </div>
                </div>

                <div class="profile-header-card" id="pageHeader">
                    <div class="u-avatar-wrap">
                        <img src="<?= $u_avatar ?>" class="u-avatar-img" alt="Avatar">
                    </div>
                    <div class="u-info">
                        <h1 class="u-name-text"><?= htmlspecialchars($u_name) ?></h1>
                        <div class="u-discord-id"><i class="fab fa-discord"></i> <?= htmlspecialchars($u_discord) ?></div>
                        <span class="u-badge"><?= $u_role_display ?></span>
                    </div>
                    <?php if ($is_my_profile): ?>
                    <div style="z-index: 2; align-self: flex-start;">
                        <button class="btn-profile-action" onclick="document.getElementById('editModal').classList.add('active')">
                            <i class="fas fa-pen-nib"></i> Оформить профиль
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="u-stats-grid">
                    <div class="u-stat-card">
                        <span class="u-stat-label">Добавлено саппортов</span>
                        <span class="u-stat-value" id="stat-added"><?= $user['added_supports_count'] ?? 0 ?></span>
                    </div>
                    <div class="u-stat-card">
                        <span class="u-stat-label">Проведено переаттестаций</span>
                        <span class="u-stat-value" id="stat-reatt"><?= $user['reattestations_count'] ?? 0 ?></span>
                    </div>
                    
                    <div class="u-stat-card">
                        <span class="u-stat-label">Выдано устников</span>
                        <span class="u-stat-value" id="stat-warn-issued"><?= $warnings_issued ?></span>
                    </div>
                </div>

                <?php if (!empty($team_info)): ?>
                <div class="u-team-box">
                    <?php if (isset($team_info['curator'])): ?>
                        <h3><i class="fas fa-shield-alt"></i> Ваш куратор</h3>
                        <div class="team-list">
                            <div class="team-item"><?= htmlspecialchars($team_info['curator']) ?></div>
                        </div>
                    <?php elseif (isset($team_info['masters'])): ?>
                        <h3><i class="fas fa-users"></i> Ваши мастера</h3>
                        <div class="team-list">
                            <?php foreach ($team_info['masters'] as $m): ?>
                                <div class="team-item"><?= htmlspecialchars($m) ?></div>
                            <?php endforeach; ?>
                            <?php if (empty($team_info['masters'])): ?>
                                <div style="color: var(--text-secondary); font-size: 0.9rem;">Мастера не назначены</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="u-about-box">
                    <h3>Обо мне</h3>
                    <div class="u-about-text" id="pageAbout"><?= $u_about ? htmlspecialchars($u_about) : 'Пользователь еще не заполнил информацию о себе.' ?></div>
                </div>
            </div>
            </div>
        </main>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem;">Изменить профиль</h2>
            
            <div class="banner-zone" id="bannerZone" style="<?= $u_banner ? "background-image: url('$u_banner')" : "" ?>">
                <i class="fas fa-image" style="<?= $u_banner ? 'display:none' : '' ?>"></i>
                <span id="bannerLabel" style="<?= $u_banner ? 'display:none' : '' ?>">Баннер профиля<br>(Ctrl+V или клик)</span>
            </div>

            <label style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.5rem; display: block;">Обо мне</label>
            <textarea id="modalAboutInput" class="form-control" rows="5" style="resize: none;"><?= htmlspecialchars($u_about) ?></textarea>
            
            <div style="display: flex; gap: 1rem;">
                <button class="btn-profile-action" style="flex: 1;" onclick="document.getElementById('editModal').classList.remove('active')">Отмена</button>
                <button class="btn-confirm-save" style="flex: 1; background: var(--accent);" onclick="applyModalChanges()">Применить</button>
            </div>
        </div>
        <input type="file" id="mediaInput" style="display:none" accept="image/*">
    </div>

    <script>
        let currentData = { 
            about: <?= json_encode($u_about) ?>, 
            banner: <?= json_encode($u_banner) ?> 
        };
        
        const mediaInput = document.getElementById('mediaInput');
        const modalAboutInput = document.getElementById('modalAboutInput');
        const pageHeader = document.getElementById('pageHeader');
        const pageAbout = document.getElementById('pageAbout');
        const saveBar = document.getElementById('saveBar');
        const bannerZone = document.getElementById('bannerZone');

        bannerZone.onclick = () => mediaInput.click();
        mediaInput.onchange = (e) => { if (e.target.files[0]) handleFile(e.target.files[0]); };

        window.addEventListener('paste', (e) => {
            if (!document.getElementById('editModal').classList.contains('active')) return;
            const item = Array.from(e.clipboardData.items).find(x => x.type.startsWith('image'));
            if (item) handleFile(item.getAsFile());
        });

        async function handleFile(file) {
            const formData = new FormData();
            formData.append('action', 'upload_media');
            formData.append('target', 'banner');
            formData.append('media_file', file);

            try {
                const res = await fetch('api.php', { method: 'POST', body: formData });
                const result = await res.json();
                if (result.success) {
                    currentData.banner = result.url;
                    bannerZone.style.backgroundImage = `url(${result.url})`;
                    document.getElementById('bannerLabel').style.display = 'none';
                    if (bannerZone.querySelector('i')) bannerZone.querySelector('i').style.display = 'none';
                }
            } catch (e) { alert('Ошибка загрузки'); }
        }

        function applyModalChanges() {
            currentData.about = modalAboutInput.value;
            pageHeader.style.backgroundImage = currentData.banner ? `url(${currentData.banner})` : '';
            pageAbout.innerText = currentData.about || 'Пользователь еще не заполнил информацию о себе.';
            saveBar.style.display = 'flex';
            document.getElementById('editModal').classList.remove('active');
        }

        async function saveAllToDB() {
            const params = new URLSearchParams();
            params.append('action', 'update_profile');
            params.append('about_me', currentData.about);
            params.append('banner_url', currentData.banner);

            try {
                const res = await fetch('api.php', { 
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params
                });
                const result = await res.json();
                if (result.success) { 
                    alert('Данные сохранены успешно!');
                    location.reload(); 
                }
                else { alert('Ошибка сохранения: ' + result.error); }
            } catch (e) {
                alert('Ошибка сети или сервера: ' + e);
            }
        }

        <?php if ($u_role === 'master'): ?>
        // Дополнительная подгрузка отчетов для мастеров
        fetch('api.php?action=get_warnings&support_id=<?= $u_discord ?>') // Используем как пример или другой эндпоинт
            .then(r => r.json())
            .then(d => {
                // Здесь можно было бы подгрузить реальное кол-во отчетов, если бы был эндпоинт
                // Пока оставим заглушку или потянем из БД если добавим поле
            });
        <?php endif; ?>
    </script>
</body>
</html>
