<?php
session_start();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Только администратор может управлять пользователями
if (($_SESSION['role'] ?? 'master') !== 'admin') {
    header('Location: index.php');
    exit;
}

require_once 'db.php';

// ПРОВЕРКА БД: Добавляем колонку бана, если её нет
try {
    $pdo->query("SELECT is_banned FROM users LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_banned TINYINT(1) DEFAULT 0");
}

require_once 'user_header.php';

$message = '';
$messageType = '';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Добавить нового пользователя
    if ($action === 'add') {
        $new_login    = trim($_POST['new_login'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $new_discord  = trim($_POST['new_discord'] ?? '');
        $new_role     = $_POST['new_role'] ?? 'master';

        if (!$new_login || !$new_password) {
            $message = 'Логин и пароль обязательны!';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$new_login]);
            if ($stmt->fetch()) {
                $message = 'Пользователь с таким логином уже существует!';
                $messageType = 'error';
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, discord_id, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $new_login,
                    $new_password,
                    $new_discord ?: 'system',
                    in_array($new_role, ['admin', 'curator', 'master']) ? $new_role : 'master'
                ]);
                $message = "Пользователь «{$new_login}» добавлен!";
                $messageType = 'success';
            }
        }
    }

    // Удалить пользователя
    if ($action === 'delete') {
        $del_login = trim($_POST['del_login'] ?? '');
        if ($del_login === 'admin') {
            $message = 'Нельзя удалить главного администратора!';
            $messageType = 'error';
        } elseif ($del_login === $_SESSION['username']) {
            $message = 'Нельзя удалить самого себя!';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
            $stmt->execute([$del_login]);
            $message = "Пользователь «{$del_login}» удалён!";
            $messageType = 'success';
        }
    }

    // Забанить/Разбанить
    if ($action === 'toggle_ban') {
        $ban_login = trim($_POST['ban_login'] ?? '');
        if ($ban_login === 'admin' || $ban_login === $_SESSION['username']) {
            $message = 'Нельзя забанить администратора или самого себя!';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET is_banned = 1 - is_banned WHERE username = ?");
            $stmt->execute([$ban_login]);
            $message = "Статус блокировки для «{$ban_login}» изменен!";
            $messageType = 'success';
        }
    }
}

// Загрузка пользователей
$stmt = $pdo->query("SELECT * FROM users ORDER BY username ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление кадрами | Панель</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .users-table { width: 100%; border-collapse: collapse; color: var(--text-main); }
        .users-table th, .users-table td { text-align: left; padding: 0.9rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
        .users-table th { font-weight: 600; color: #A78BFA; border-bottom: 1px solid rgba(99, 102, 241, 0.3); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .users-table tr.banned-row td { background: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.1); }
        .banned-text { text-decoration: line-through; color: #EF4444 !important; opacity: 0.7; }
        
        .role-badge { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.25rem 0.65rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .role-admin { background: rgba(245, 158, 11, 0.15); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3); }
        .role-curator { background: rgba(16, 185, 129, 0.15); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .role-master { background: rgba(99, 102, 241, 0.15); color: #818CF8; border: 1px solid rgba(99, 102, 241, 0.3); }
        
        .form-row { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
        .form-field { display: flex; flex-direction: column; gap: 0.4rem; flex: 1; min-width: 140px; }
        .form-field label { font-size: 0.82rem; color: var(--text-muted); font-weight: 500; }
        .form-input { padding: 0.7rem 0.9rem; border-radius: 8px; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); color: white; outline: none; font-size: 0.9rem; transition: border-color 0.2s; font-family: 'Inter', sans-serif; }
        .form-input:focus { border-color: #6366F1; }
        .form-select { padding: 0.7rem 0.9rem; border-radius: 8px; background: #0F172A; border: 1px solid var(--border-color); color: white; outline: none; font-size: 0.9rem; cursor: pointer; }
        
        .btn-add { background: linear-gradient(135deg, #6366F1, #8B5CF6); color: white; border: none; padding: 0.7rem 1.4rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: transform 0.1s; }
        .btn-add:hover { transform: translateY(-1px); }
        .btn-delete { background: rgba(239, 68, 68, 0.1); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.2); padding: 0.35rem 0.8rem; border-radius: 6px; font-size: 0.82rem; cursor: pointer; }
        .btn-ban { background: rgba(239, 68, 68, 0.2); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.4); padding: 0.35rem 0.8rem; border-radius: 6px; font-size: 0.82rem; cursor: pointer; }
        .btn-unban { background: rgba(16, 185, 129, 0.2); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.4); padding: 0.35rem 0.8rem; border-radius: 6px; font-size: 0.82rem; cursor: pointer; }
        
        .alert-msg { padding: 0.85rem 1.2rem; border-radius: 10px; font-weight: 500; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #10B981; }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #EF4444; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9998; backdrop-filter: blur(4px); justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: #0F172A; border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 16px; padding: 2rem; width: 350px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <main class="main-content">
            <header class="header glass">
                <h1>👥 Кадры</h1>
                <div class="user-profile">
                    <img src="<?= htmlspecialchars($avatar_url) ?>" style="width: 32px; height: 32px; border-radius: 50%;">
                    <span style="font-weight: 500; color: #A78BFA; margin-left:8px;"><?= htmlspecialchars($_SESSION['username']) ?></span>
                </div>
            </header>

            <section class="content">
                <?php if ($message): ?>
                    <div class="alert-msg <?= $messageType === 'success' ? 'alert-success' : 'alert-error' ?>" style="grid-column: 1 / -1;">
                        <?= $messageType === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="card glass" style="grid-column: 1 / -1;">
                    <div class="card-header"><h3>👥 Список пользователей</h3></div>
                    <div class="card-body" style="overflow-x: auto;">
                        <table class="users-table">
                            <thead>
                                <tr><th>Логин</th><th>Роль</th><th>Discord ID</th><th>Статус</th><th>Действия</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr class="<?= $u['is_banned'] ? 'banned-row' : '' ?>">
                                    <td class="<?= $u['is_banned'] ? 'banned-text' : '' ?> font-weight: 600; color: #E2E8F0;">
                                        <?= htmlspecialchars($u['username']) ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $uRole = $u['role'] ?? 'master';
                                            $lbl = 'Мастер';
                                            if ($uRole === 'admin') $lbl = 'Админ';
                                            elseif ($uRole === 'chief') $lbl = 'Гл. Куратор';
                                            elseif ($uRole === 'curator') $lbl = 'Куратор';
                                        ?>
                                        <span class="role-badge <?= $uRole === 'admin' ? 'role-admin' : ($uRole === 'master' ? 'role-master' : 'role-curator') ?>">
                                            <?= $lbl ?>
                                        </span>
                                    </td>
                                    <td style="color: #64748B; font-family: monospace;"><?= htmlspecialchars($u['discord_id'] ?? '—') ?></td>
                                    <td>
                                        <?php if ($u['is_banned']): ?>
                                            <span style="color: #EF4444; font-size: 0.8rem; font-weight: 600;">🚫 ЗАБАНЕН</span>
                                        <?php else: ?>
                                            <span style="color: #10B981; font-size: 0.8rem; font-weight: 600;">✅ АКТИВЕН</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="display: flex; gap: 0.5rem;">
                                        <?php if ($u['username'] !== 'admin' && $u['username'] !== $_SESSION['username']): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_ban">
                                                <input type="hidden" name="ban_login" value="<?= htmlspecialchars($u['username']) ?>">
                                                <button type="submit" class="<?= $u['is_banned'] ? 'btn-unban' : 'btn-ban' ?>">
                                                    <?= $u['is_banned'] ? '🔓 Разбанить' : '🚫 Забанить' ?>
                                                </button>
                                            </form>
                                            <button class="btn-delete" onclick="openDeleteModal('<?= htmlspecialchars($u['username']) ?>')">🗑 Удалить</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <div class="modal-overlay" id="delete-modal">
        <div class="modal-box">
            <h3>🗑 Удалить пользователя?</h3>
            <p id="del-name-display" style="color: #EF4444; margin: 1rem 0; font-weight: 600;"></p>
            <form method="POST">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="del_login" id="del-login-input">
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button type="button" class="btn-add" style="background: grey;" onclick="document.getElementById('delete-modal').classList.remove('active')">Отмена</button>
                    <button type="submit" class="btn-delete">Да, удалить</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openDeleteModal(login) {
            document.getElementById('del-login-input').value = login;
            document.getElementById('del-name-display').textContent = login;
            document.getElementById('delete-modal').classList.add('active');
        }
    </script>
</body>
</html>
