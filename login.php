<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';
// Ошибка, переданная из discord_login.php
if (!empty($_SESSION['discord_login_error'])) {
    $error = $_SESSION['discord_login_error'];
    unset($_SESSION['discord_login_error']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Синхронизация с users.json (фикс для бота)
    $users_json = __DIR__ . '/users.json';
    if (file_exists($users_json)) {
        $users_data = json_decode(file_get_contents($users_json), true);
        if (isset($users_data[$username])) {
            $udata = $users_data[$username];
            $json_pass = (string)($udata['password'] ?? '');
            
            if (!$user) {
                // Если пользователя нет в БД — создаем
                $stmtIns = $pdo->prepare("INSERT INTO users (username, password, discord_id, role) VALUES (?, ?, ?, ?)");
                $stmtIns->execute([
                    $username,
                    $json_pass,
                    (string)($udata['discord_id'] ?? 'system'),
                    (string)($udata['role'] ?? 'master')
                ]);
            } elseif ($user['password'] !== $json_pass && !empty($json_pass)) {
                // Если пароль в JSON новее — обновляем в БД
                $stmtUpd = $pdo->prepare("UPDATE users SET password = ?, role = ? WHERE username = ?");
                $stmtUpd->execute([$json_pass, (string)($udata['role'] ?? $user['role']), $username]);
            }
            
            // Перезагружаем данные пользователя после синхронизации
            $stmt->execute([$username]);
            $user = $stmt->fetch();
        }
    }

    if ($user && $password === $user['password']) {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['discord_id'] = $user['discord_id'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Неверное имя пользователя или пароль';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FUTURAMA STAFF | Авторизация</title>
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at top right, #1e293b, #0f172a);
            padding: 20px;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background: #161922;
            border-radius: 24px;
            padding: 2.5rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.6s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header .logo-text {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 600;
        }
        .input-wrapper {
            position: relative;
        }
        .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }
        .form-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 12px 12px 12px 2.8rem;
            color: white;
            font-size: 1rem;
            transition: var(--transition);
        }
        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            background: rgba(99, 102, 241, 0.05);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        .login-btn {
            width: 100%;
            padding: 14px;
            background: var(--gradient-primary);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 1rem;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px var(--accent-glow);
        }
        .discord-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 13px;
            background: #5865F2;
            border: none;
            border-radius: 12px;
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        .discord-btn:hover {
            background: #4752c4;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(88, 101, 242, 0.3);
        }
        .discord-btn i { font-size: 1.2rem; }
        .error-msg {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 12px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(239, 68, 68, 0.2);
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="logo-text">FUTURAMA</div>
            <p style="color: var(--text-secondary); font-size: 0.9rem;">Вход в панель управления</p>
        </div>

        <?php if ($error): ?>
            <div class="error-msg">
                <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>ИМЯ ПОЛЬЗОВАТЕЛЯ</label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" class="form-input" placeholder="Введите ник..." required>
                </div>
            </div>
            <div class="form-group">
                <label>ПАРОЛЬ</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" class="form-input" placeholder="••••••••" required>
                </div>
            </div>
            <button type="submit" class="login-btn">
                ВОЙТИ В СИСТЕМУ
            </button>
        </form>

        <div style="display:flex; align-items:center; gap:12px; margin:1.5rem 0; color:#475569; font-size:0.75rem;">
            <div style="flex:1; height:1px; background:rgba(255,255,255,0.08);"></div>
            ИЛИ
            <div style="flex:1; height:1px; background:rgba(255,255,255,0.08);"></div>
        </div>

        <a href="discord_login.php" class="discord-btn">
            <i class="fab fa-discord"></i> Войти через Discord
        </a>

        <div style="margin-top: 2rem; text-align: center;">
            <p style="color: #475569; font-size: 0.75rem;">Powered by Futurama Staff System</p>
        </div>
    </div>
</body>
</html>
