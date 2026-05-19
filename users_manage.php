<?php
session_start();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Доступ к странице только для Системного Администратора (admin)
$current_role = $_SESSION['role'] ?? 'master';
if ($current_role !== 'admin') {
    header('Location: index.php');
    exit;
}

require_once 'db.php';
require_once 'user_header.php';

$message = '';
$messageType = '';

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Установка даты становления (только админ)
    if ($action === 'set_appointment_date') {
        if ($current_role !== 'admin') {
            $message = 'Ошибка: У вас недостаточно прав для установки даты становления!';
            $messageType = 'error';
        } else {
            $tgt_username = trim($_POST['username'] ?? '');
            $appt_date    = trim($_POST['appointment_date'] ?? '');
            
            if ($appt_date === '') {
                $appt_date = null;
            }
            
            try {
                $stmt = $pdo->prepare("UPDATE users SET appointment_date = ? WHERE username = ?");
                $stmt->execute([$appt_date, $tgt_username]);
                $message = "Дата становления для «{$tgt_username}» успешно обновлена!";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = "Ошибка при обновлении даты: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    // Только АДМИН и ГЛ. КУРАТОР может добавлять, удалять или банить
    elseif ($current_role !== 'admin' && $current_role !== 'chief') {
        $message = 'Ошибка: У вас недостаточно прав для этого действия!';
        $messageType = 'error';
    } else {
        // ДОБАВЛЕНИЕ
        if ($action === 'add') {
            $new_login    = trim($_POST['new_login'] ?? '');
            $new_password = trim($_POST['new_password'] ?? '');
            $new_discord  = trim($_POST['new_discord'] ?? '');
            $new_role     = $_POST['new_role'] ?? 'master';
            $new_appt     = trim($_POST['new_appointment_date'] ?? '');

            if ($new_appt === '') {
                $new_appt = null;
            }

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
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, discord_id, role, appointment_date) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$new_login, $new_password, $new_discord ?: 'system', $new_role, $new_appt]);
                    $message = "Пользователь «{$new_login}» успешно добавлен!";
                    $messageType = 'success';
                }
            }
        }

        // УДАЛЕНИЕ
        if ($action === 'delete') {
            $del_login = trim($_POST['del_login'] ?? '');
            if ($del_login === 'admin' || $del_login === $_SESSION['username']) {
                $message = 'Нельзя удалить этого пользователя!';
                $messageType = 'error';
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
                $stmt->execute([$del_login]);
                $message = "Пользователь «{$del_login}» удалён из системы.";
                $messageType = 'success';
            }
        }

        // БАН / РАЗБАН
        if ($action === 'toggle_ban') {
            $ban_login = trim($_POST['ban_login'] ?? '');
            if ($ban_login !== 'admin' && $ban_login !== $_SESSION['username']) {
                $stmt = $pdo->prepare("UPDATE users SET is_banned = 1 - is_banned WHERE username = ?");
                $stmt->execute([$ban_login]);
                $message = "Статус доступа для «{$ban_login}» изменен.";
                $messageType = 'success';
            }
        }
    }
}

// АВТО-ОБНОВЛЕНИЕ БАЗЫ ДАННЫХ (last_seen, appointment_date)
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN last_seen DATETIME DEFAULT NULL");
} catch (Exception $e) {}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN appointment_date DATE DEFAULT NULL");
} catch (Exception $e) {}

require_once 'staff_functions.php';

// Получаем список активных сотрудников с главной страницы (из Google Таблицы)
$dashboardData = getAllDashboardData($pdo);
$activeUsernames = [];
$activeMembersList = [];
if (!empty($dashboardData['management'])) {
    foreach ($dashboardData['management'] as $category => $members) {
        foreach ($members as $member) {
            if (!empty($member['nick'])) {
                $normNick = str_replace('_', '', mb_strtolower(trim($member['nick'])));
                $activeUsernames[] = $normNick;
                $activeMembersList[$normNick] = [
                    'nick' => $member['nick'],
                    'discord_id' => $member['discord_id'] ?? null,
                    'role_cat' => $category
                ];
            }
        }
    }
}
$activeUsernames = array_unique($activeUsernames);

// Загрузка всех существующих пользователей из БД
$stmt = $pdo->query("SELECT * FROM users WHERE username != 'admin'");
$allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$existingNormUsers = [];
foreach ($allUsers as $u) {
    $existingNormUsers[] = str_replace('_', '', mb_strtolower(trim($u['username'])));
}

// Загрузим users.json, чтобы подтянуть пароли и Discord ID, если они там есть
$users_json = __DIR__ . '/users.json';
$users_json_data = [];
if (file_exists($users_json)) {
    $users_json_data = json_decode(file_get_contents($users_json), true) ?: [];
}

// Автоматически регистрируем сотрудников из Google Таблицы, которых еще нет в БД, и обновляем роли для существующих!
foreach ($activeMembersList as $normNick => $mInfo) {
    // Определяем правильную роль на основе Google Таблицы
    $gRole = 'master';
    if ($mInfo['role_cat'] === 'admin') {
        $gRole = 'admin';
    } elseif ($mInfo['role_cat'] === 'chief') {
        $gRole = 'chief';
    } elseif ($mInfo['role_cat'] === 'curators') {
        $gRole = 'curator';
    }

    if (!in_array($normNick, $existingNormUsers)) {
        // Пытаемся найти пароль и ID из users.json
        $realUsername = $mInfo['nick'];
        $realPassword = '123'; // Пароль по умолчанию
        $realDiscord = $mInfo['discord_id'] ?: 'system';

        // Ищем в users.json (без учета регистра и подчеркиваний)
        foreach ($users_json_data as $jUser => $jData) {
            if (str_replace('_', '', mb_strtolower(trim($jUser))) === $normNick) {
                $realUsername = $jUser;
                if (!empty($jData['password'])) {
                    $realPassword = $jData['password'];
                }
                if (!empty($jData['discord_id'])) {
                    $realDiscord = $jData['discord_id'];
                }
                if (!empty($jData['role'])) {
                    $gRole = $jData['role'];
                }
                break;
            }
        }

        // Вставляем нового пользователя
        try {
            $stmtIns = $pdo->prepare("INSERT INTO users (username, password, discord_id, role) VALUES (?, ?, ?, ?)");
            $stmtIns->execute([$realUsername, $realPassword, $realDiscord, $gRole]);
        } catch (Exception $e) {}
    } else {
        // Если пользователь уже существует, обновляем его роль в базе данных, если она изменилась в таблице
        try {
            $stmtUpdRole = $pdo->prepare("UPDATE users SET role = ? WHERE username = ? AND role != ?");
            foreach ($allUsers as $u) {
                if (str_replace('_', '', mb_strtolower(trim($u['username']))) === $normNick) {
                    $stmtUpdRole->execute([$gRole, $u['username'], $gRole]);
                    break;
                }
            }
        } catch (Exception $e) {}
    }
}

// Загрузка списка пользователей (СКРЫВАЕМ admin)
$stmt = $pdo->query("SELECT * FROM users WHERE username != 'admin' ORDER BY role ASC, username ASC");
$allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Оставляем только тех пользователей, которые видны на главной странице
$users = [];
foreach ($allUsers as $u) {
    $normalizedDbUser = str_replace('_', '', mb_strtolower(trim($u['username'])));
    if (in_array($normalizedDbUser, $activeUsernames)) {
        $users[] = $u;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление персоналом | Futurama Staff</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .users-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; margin-top: 1rem; }
        .users-table th { padding: 1rem; color: #94a3b8; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; text-align: left; }
        .users-table td { padding: 1.2rem 1rem; background: rgba(255, 255, 255, 0.02); border-top: 1px solid rgba(255, 255, 255, 0.05); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .users-table td:first-child { border-left: 1px solid rgba(255, 255, 255, 0.05); border-radius: 16px 0 0 16px; }
        .users-table td:last-child { border-right: 1px solid rgba(255, 255, 255, 0.05); border-radius: 0 16px 16px 0; }
        
        .user-row:hover td { background: rgba(255, 255, 255, 0.04); }

        .role-badge {
            padding: 4px 12px;
            border-radius: 100px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .role-admin { background: rgba(251, 191, 36, 0.1); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.2); }
        .role-chief { background: rgba(139, 92, 246, 0.1); color: #a78bfa; border: 1px solid rgba(139, 92, 246, 0.2); }
        .role-curator { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .role-master { background: rgba(99, 102, 241, 0.1); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.2); }

        .btn-action {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.03);
            color: #94a3b8;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-action:hover { background: #fff; color: #000; }
        .btn-action.danger:hover { background: #ef4444; color: #fff; border-color: #ef4444; }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(10px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 2rem;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .alert-box {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar_v2.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Управление кадрами</h1>
                </div>
                <div class="header-actions">
                    <?php if ($current_role === 'admin' || $current_role === 'chief'): ?>
                        <button style="background: var(--accent); border: none; color: #fff; padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem;" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Добавить
                        </button>
                    <?php endif; ?>
                    <a href="logout.php" class="btn-logout-premium"><i class="fas fa-sign-out-alt"></i> Выйти</a>
                </div>
            </header>

            <div class="page-body">
            <section class="content">
                <?php if ($message): ?>
                    <div class="alert-box <?= $messageType === 'success' ? 'alert-success' : 'alert-error' ?>">
                        <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div style="overflow-x: auto;">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Сотрудник</th>
                                    <th>Должность</th>
                                    <th>Discord ID</th>
                                    <th>На ветке с</th>
                                    <th>Последний вход</th>
                                    <th style="text-align: right;">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 3rem; color: #64748b;">
                                            Здесь пока пусто. В списке появятся только те, кто хотя бы раз зашел на сайт.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($users as $u): ?>
                                <tr class="user-row">
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div class="user-avatar-container" style="position: relative;">
                                                <img src="<?= getAvatarUrl($u['discord_id'], $u['username']) ?>" 
                                                     style="width: 40px; height: 40px; border-radius: 12px; object-fit: cover; border: 1px solid rgba(255,255,255,0.1);" 
                                                     alt="">
                                            </div>
                                            <div>
                                                <div style="font-weight: 700; color: #fff;"><?= htmlspecialchars($u['username']) ?></div>
                                                <?php if (isset($u['is_banned']) && $u['is_banned']): ?>
                                                    <span style="font-size: 0.7rem; color: #ef4444; font-weight: 700;">ЗАБЛОКИРОВАН</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $r = $u['role'] ?? 'master';
                                            $lbl = 'Мастер';
                                            if ($r === 'admin') $lbl = 'Админ';
                                            elseif ($r === 'chief') $lbl = 'Гл. Куратор';
                                            elseif ($r === 'curator') $lbl = 'Куратор';
                                        ?>
                                        <span class="role-badge role-<?= $r ?>">
                                            <i class="fas <?= $r === 'admin' ? 'fa-crown' : 'fa-user' ?>"></i>
                                            <?= $lbl ?>
                                        </span>
                                    </td>
                                    <td><code style="color: #94a3b8; font-size: 0.8rem;"><?= htmlspecialchars($u['discord_id'] ?? '—') ?></code></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="color: #f1f5f9; font-size: 0.85rem; font-family: monospace;">
                                                <?= !empty($u['appointment_date']) ? date('d.m.Y', strtotime($u['appointment_date'])) : '—' ?>
                                            </span>
                                            <?php if ($current_role === 'admin'): ?>
                                                <button class="btn-action" style="width: 26px; height: 26px; border-radius: 6px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" 
                                                        onclick="openSetDateModal('<?= htmlspecialchars($u['username']) ?>', '<?= htmlspecialchars($u['appointment_date'] ?? '') ?>')" 
                                                        title="Изменить дату становления">
                                                    <i class="fas fa-edit" style="font-size: 0.75rem;"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="color: #64748b; font-size: 0.85rem;">
                                            <?= !empty($u['last_seen']) ? date('d.m.Y H:i', strtotime($u['last_seen'])) : '—' ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php 
                                        $canManage = ($current_role === 'admin' || $current_role === 'chief');
                                        $isProtected = ($u['username'] === 'admin' || $u['username'] === $_SESSION['username']);
                                        
                                        if ($canManage && !$isProtected): 
                                            $isBanned = isset($u['is_banned']) && $u['is_banned'];
                                        ?>
                                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                                <form method="POST" style="margin: 0;">
                                                    <input type="hidden" name="action" value="toggle_ban">
                                                    <input type="hidden" name="ban_login" value="<?= htmlspecialchars($u['username']) ?>">
                                                    <button type="submit" class="btn-action" title="<?= $isBanned ? 'Разблокировать' : 'Заблокировать' ?>">
                                                        <i class="fas <?= $isBanned ? 'fa-unlock' : 'fa-ban' ?>"></i>
                                                    </button>
                                                </form>
                                                <button class="btn-action danger" onclick="confirmDelete('<?= htmlspecialchars($u['username']) ?>')" title="Удалить">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
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

    <!-- Modal: Удаление -->
    <div class="modal" id="modalDelete">
        <div class="modal-content" style="text-align: center;">
            <div style="width: 60px; height: 60px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1.5rem;">
                <i class="fas fa-trash-alt"></i>
            </div>
            <h3 style="color: #fff; margin-bottom: 1rem;">Удалить аккаунт?</h3>
            <p style="color: #94a3b8; margin-bottom: 2rem;">Вы уверены, что хотите удалить <b id="delName" style="color: #fff;"></b>? Это действие нельзя отменить.</p>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="del_login" id="delLoginInput">
                <div style="display: flex; gap: 12px;">
                    <button type="button" class="btn-edit-profile" style="flex: 1; justify-content: center;" onclick="closeModals()">Отмена</button>
                    <button type="submit" class="btn-action danger" style="flex: 1; height: 45px; font-weight: 700;">Да, удалить</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Добавление -->
    <div class="modal" id="modalAdd">
        <div class="modal-content">
            <h3 style="color: #fff; margin-bottom: 1.5rem;">Новый сотрудник</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <input type="text" name="new_login" class="form-control" placeholder="Логин" required style="padding-left: 1rem;">
                    <input type="password" name="new_password" class="form-control" placeholder="Пароль" required style="padding-left: 1rem;">
                    <input type="text" name="new_discord" class="form-control" placeholder="Discord ID" style="padding-left: 1rem;">
                    <select name="new_role" class="form-control" style="padding-left: 1rem;">
                        <option value="master">Мастер</option>
                        <option value="curator">Куратор</option>
                        <option value="chief">Главный Куратор</option>
                        <option value="admin">Администратор</option>
                    </select>
                    <div style="color: #94a3b8; font-size: 0.85rem; margin-top: 4px; margin-bottom: -4px;">Дата становления:</div>
                    <input type="date" name="new_appointment_date" class="form-control" style="padding-left: 1rem; color: #fff; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 10px; height: 45px;">
                    <div style="display: flex; gap: 12px; margin-top: 1rem;">
                        <button type="button" class="btn-edit-profile" style="flex: 1; justify-content: center;" onclick="closeModals()">Отмена</button>
                        <button type="submit" class="btn-edit-profile" style="flex: 2; justify-content: center; background: var(--accent); border: none; height: 45px; color: #fff; cursor: pointer; font-weight: 700; border-radius: 10px;">Создать</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Установка даты становления -->
    <div class="modal" id="modalSetDate">
        <div class="modal-content">
            <h3 style="color: #fff; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-calendar-alt" style="color: var(--accent);"></i> Дата становления
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="set_appointment_date">
                <input type="hidden" name="username" id="setDateUsernameInput">
                <div style="display: flex; flex-direction: column; gap: 1.2rem;">
                    <div style="color: #94a3b8; font-size: 0.9rem;">
                        Укажите дату назначения сотрудника <b id="setDateUserDisplay" style="color: #fff;"></b> на должность:
                    </div>
                    <input type="date" name="appointment_date" id="setDateInput" class="form-control" style="padding-left: 1rem; color: #fff; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 10px; height: 45px;">
                    <div style="display: flex; gap: 12px; margin-top: 1rem;">
                        <button type="button" class="btn-edit-profile" style="flex: 1; justify-content: center; height: 45px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 10px; cursor: pointer; font-weight: 600;" onclick="closeModals()">Отмена</button>
                        <button type="submit" class="btn-edit-profile" style="flex: 1; justify-content: center; height: 45px; background: var(--accent); border: none; color: #fff; border-radius: 10px; cursor: pointer; font-weight: 700;">Сохранить</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function confirmDelete(login) {
            document.getElementById('delName').textContent = login;
            document.getElementById('delLoginInput').value = login;
            document.getElementById('modalDelete').classList.add('active');
        }
        function openAddModal() {
            document.getElementById('modalAdd').classList.add('active');
        }
        function openSetDateModal(username, currentDate) {
            document.getElementById('setDateUserDisplay').textContent = username;
            document.getElementById('setDateUsernameInput').value = username;
            document.getElementById('setDateInput').value = currentDate;
            document.getElementById('modalSetDate').classList.add('active');
        }
        function closeModals() {
            document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
        }
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) closeModals();
        }
    </script>
</body>
</html>
