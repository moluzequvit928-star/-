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
            if ($stmt->rowCount() > 0) {
                $message = "Пользователь «{$del_login}» удалён!";
                $messageType = 'success';
            } else {
                $message = 'Пользователь не найден!';
                $messageType = 'error';
            }
        }
    }

    // Сменить пароль
    if ($action === 'change_password') {
        $cp_login    = trim($_POST['cp_login'] ?? '');
        $cp_password = trim($_POST['cp_password'] ?? '');
        if (!$cp_login || !$cp_password) {
            $message = 'Укажите логин и новый пароль!';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
            $stmt->execute([$cp_password, $cp_login]);
            if ($stmt->rowCount() > 0) {
                $message = "Пароль для «{$cp_login}» обновлён!";
                $messageType = 'success';
            } else {
                $message = 'Пользователь не найден или пароль совпадает!';
                $messageType = 'error';
            }
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
        .users-table {
            width: 100%;
            border-collapse: collapse;
            color: var(--text-main);
        }
        .users-table th, .users-table td {
            text-align: left;
            padding: 0.9rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            vertical-align: middle;
        }
        .users-table th {
            font-weight: 600;
            color: #A78BFA;
            border-bottom: 1px solid rgba(99, 102, 241, 0.3);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .users-table tr:hover td {
            background: rgba(255,255,255,0.02);
        }
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.65rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .role-admin {
            background: rgba(245, 158, 11, 0.15);
            color: #F59E0B;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .role-curator {
            background: rgba(16, 185, 129, 0.15);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .role-master {
            background: rgba(99, 102, 241, 0.15);
            color: #818CF8;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
        .form-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .form-field {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            flex: 1;
            min-width: 140px;
        }
        .form-field label {
            font-size: 0.82rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .form-input {
            padding: 0.7rem 0.9rem;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: white;
            outline: none;
            font-size: 0.9rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .form-input:focus {
            border-color: #6366F1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
        }
        .form-select {
            padding: 0.7rem 0.9rem;
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--border-color);
            color: white;
            outline: none;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
        }
        .btn-add {
            background: linear-gradient(135deg, #6366F1, #8B5CF6);
            color: white;
            border: none;
            padding: 0.7rem 1.4rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.1s;
            white-space: nowrap;
        }
        .btn-add:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-delete {
            background: rgba(239, 68, 68, 0.15);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 0.35rem 0.8rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-delete:hover { background: rgba(239, 68, 68, 0.35); }
        .btn-password {
            background: rgba(245, 158, 11, 0.15);
            color: #F59E0B;
            border: 1px solid rgba(245, 158, 11, 0.3);
            padding: 0.35rem 0.8rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-password:hover { background: rgba(245, 158, 11, 0.35); }
        .discord-mono {
            font-family: monospace;
            font-size: 0.85rem;
            color: #64748B;
        }
        .alert-msg {
            padding: 0.85rem 1.2rem;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10B981;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #EF4444;
        }
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 9998;
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #0F172A;
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 16px;
            padding: 2rem;
            width: 350px;
            max-width: 90vw;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .modal-box h3 {
            margin: 0 0 1.2rem;
            font-size: 1.1rem;
            color: #A78BFA;
        }
        .modal-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.2rem;
            justify-content: flex-end;
        }
        .btn-cancel {
            background: rgba(255,255,255,0.07);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            padding: 0.55rem 1.1rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        .btn-cancel:hover { background: rgba(255,255,255,0.12); }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar glass">
            <div class="logo">
                <h2>Панель</h2>
            </div>
            <nav class="menu">
                <a href="index.php" class="menu-item">Главная</a>
                <?php if ($current_role === 'admin'): ?>
                    <a href="admin_stats.php" class="menu-item">Статистика</a>
                <?php endif; ?>
                <?php if ($current_role === 'master'): ?>
                    <a href="master_info.php" class="menu-item">Информация</a>
                <?php endif; ?>
                <a href="reports.php" class="menu-item">Отчеты по наборам</a>
                <a href="https://docs.google.com/spreadsheets/d/1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754/edit?gid=1970062457#gid=1970062457" class="menu-item" target="_blank">Google Таблица ↗</a>
                <a href="https://docs.google.com/document/d/1tef_iQ0GuuIVgQRI15Ql8H74BFPjEcI9Cg3qZCQrtL8/edit?tab=t.0" class="menu-item" target="_blank">Собес на саппорта ↗</a>
                <?php 
                // Данные для сайдбара уже в user_header.php
                if ($current_role === 'admin' || $current_role === 'curator'): 
                ?>
                <a href="check_reports.php" class="menu-item" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Проверка отчетов</span>
                    <?php if ($sidebarPendingCount > 0): ?>
                    <span style="background: #EF4444; color: white; font-size: 0.75rem; font-weight: 700; padding: 0.1rem 0.45rem; border-radius: 999px; line-height: 1.6; min-width: 20px; text-align: center;"><?= $sidebarPendingCount ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                <?php if ($current_role === 'admin'): ?>
                    <a href="reattestation.php" class="menu-item">Переаттестация</a>
                <?php endif; ?>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header glass">
                <h1></h1>
                <div class="user-profile">
                    <div class="avatar-container" style="display: flex; align-items: center; gap: 0.75rem;">
                        <img src="<?= htmlspecialchars($avatar_url) ?>" alt="avatar"
                            style="width: 36px; height: 36px; border-radius: 50%; border: 2px solid rgba(167, 139, 250, 0.5); object-fit: cover;">
                        <span class="username" style="font-weight: 500; color: #A78BFA;"><?= htmlspecialchars($_SESSION['username']) ?> <span style="font-size: 0.75rem; color: #94A3B8; font-weight: 400; margin-left: 5px;">(<?= htmlspecialchars($role_display) ?>)</span></span>
                        <a href="logout.php" class="btn btn-primary"
                            style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: rgba(239, 68, 68, 0.2); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.4);">Выйти</a>
                    </div>
                </div>
            </header>

            <section class="content">

                <?php if ($message): ?>
                <div class="card glass" style="grid-column: 1 / -1;">
                    <div class="alert-msg <?= $messageType === 'success' ? 'alert-success' : 'alert-error' ?>">
                        <?= $messageType === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Добавить нового мастера -->
                <div class="card glass" style="grid-column: 1 / -1;">
                    <div class="card-header">
                        <h3>➕ Добавить пользователя</h3>
                        <span class="status" style="background: rgba(99,102,241,0.15); color:#818CF8; border: 1px solid rgba(99,102,241,0.3);">Только Администратор</span>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="form-row">
                                <div class="form-field">
                                    <label>Логин</label>
                                    <input type="text" name="new_login" class="form-input" placeholder="master_ivan" required autocomplete="off">
                                </div>
                                <div class="form-field">
                                    <label>Пароль</label>
                                    <input type="text" name="new_password" class="form-input" placeholder="Введите пароль" required autocomplete="off">
                                </div>
                                <div class="form-field">
                                    <label>Discord ID (необязательно)</label>
                                    <input type="text" name="new_discord" class="form-input" placeholder="123456789012345678">
                                </div>
                                <div class="form-field" style="max-width: 160px;">
                                    <label>Роль</label>
                                    <select name="new_role" class="form-select">
                                        <option value="master" selected>🎓 Мастер</option>
                                        <option value="curator">👁️ Куратор</option>
                                        <option value="admin">👑 Администратор</option>
                                    </select>
                                </div>
                                <div style="display: flex; align-items: flex-end;">
                                    <button type="submit" class="btn-add">+ Добавить</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Список пользователей -->
                <div class="card glass" style="grid-column: 1 / -1;">
                    <div class="card-header">
                        <h3>👥 Список пользователей</h3>
                        <span class="status" style="background: rgba(16,185,129,0.15); color:#10B981; border: 1px solid rgba(16,185,129,0.3);">
                            Всего: <?= count($users) ?>
                        </span>
                    </div>
                    <div class="card-body" style="overflow-x: auto;">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Логин</th>
                                    <th>Роль</th>
                                    <th>Discord ID</th>
                                    <th>Пароль</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($users as $udata): ?>
                                <?php $uname = $udata['username']; ?>
                                <tr>
                                    <td style="color: var(--text-muted); font-size:0.85rem;"><?= $i++ ?></td>
                                    <td style="font-weight: 600; color: #E2E8F0;">
                                        <?= htmlspecialchars($uname) ?>
                                        <?php if ($uname === $_SESSION['username']): ?>
                                            <span style="font-size:0.75rem; color: #64748B; margin-left:0.3rem;">(вы)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php $role = $udata['role'] ?? 'master'; ?>
                                        <span class="role-badge <?= $role === 'admin' ? 'role-admin' : ($role === 'curator' ? 'role-curator' : 'role-master') ?>">
                                            <?= $role === 'admin' ? '👑 Администратор' : ($role === 'curator' ? '👁️ Куратор' : '🎓 Мастер') ?>
                                        </span>
                                    </td>
                                    <td class="discord-mono"><?= htmlspecialchars($udata['discord_id'] ?? '—') ?></td>
                                    <td style="font-family: monospace; color: #64748B; letter-spacing: 0.1em;">••••••••</td>
                                    <td style="display: flex; gap: 0.5rem; flex-wrap: wrap; padding-top: 0.75rem;">
                                        <!-- Кнопка смены пароля -->
                                        <button class="btn-password"
                                            onclick="openPasswordModal('<?= htmlspecialchars($uname) ?>')">
                                            🔑 Пароль
                                        </button>
                                        <!-- Кнопка удаления (не для admin и не для себя) -->
                                        <?php if ($uname !== 'admin' && $uname !== $_SESSION['username']): ?>
                                        <button class="btn-delete"
                                            onclick="openDeleteModal('<?= htmlspecialchars($uname) ?>')">
                                            🗑 Удалить
                                        </button>
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

    <!-- Модалка смены пароля -->
    <div class="modal-overlay" id="password-modal">
        <div class="modal-box">
            <h3>🔑 Сменить пароль</h3>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="cp_login" id="cp-login-input">
                <div class="form-field" style="margin-bottom: 0.5rem;">
                    <label>Пользователь</label>
                    <input type="text" id="cp-login-display" class="form-input" disabled style="opacity:0.6;">
                </div>
                <div class="form-field">
                    <label>Новый пароль</label>
                    <input type="text" name="cp_password" class="form-input" placeholder="Новый пароль" required autocomplete="off">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closePasswordModal()">Отмена</button>
                    <button type="submit" class="btn-add">Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Модалка удаления -->
    <div class="modal-overlay" id="delete-modal">
        <div class="modal-box">
            <h3>🗑 Удалить пользователя?</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">
                Пользователь <strong id="del-name-display" style="color: #EF4444;"></strong> будет удалён безвозвратно.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="del_login" id="del-login-input">
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Отмена</button>
                    <button type="submit" class="btn-delete" style="font-size:0.9rem; padding: 0.55rem 1.1rem;">Удалить</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPasswordModal(login) {
            document.getElementById('cp-login-input').value = login;
            document.getElementById('cp-login-display').value = login;
            document.getElementById('password-modal').classList.add('active');
        }
        function closePasswordModal() {
            document.getElementById('password-modal').classList.remove('active');
        }
        function openDeleteModal(login) {
            document.getElementById('del-login-input').value = login;
            document.getElementById('del-name-display').textContent = login;
            document.getElementById('delete-modal').classList.add('active');
        }
        function closeDeleteModal() {
            document.getElementById('delete-modal').classList.remove('active');
        }
        // Закрытие по клику вне модалки
        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
            }
        });
    </script>
</body>
</html>
