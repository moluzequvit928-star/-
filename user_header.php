<?php
require_once 'db.php';

// Получаем дискорд ID из сессии
$discord_id = $_SESSION['discord_id'] ?? null;
$username = $_SESSION['username'] ?? 'Гость';

// Если роль не сохранена в сессии — подгружаем из users.json (фикс для старых сессий)
if (!isset($_SESSION['role']) && $username !== 'Гость') {
    $users_file = __DIR__ . '/users.json';
    if (file_exists($users_file)) {
        $all_users = json_decode(file_get_contents($users_file), true);
        if (isset($all_users[$username]['role'])) {
            $_SESSION['role'] = $all_users[$username]['role'];
        } else {
            $_SESSION['role'] = ($username === 'admin') ? 'admin' : 'master';
        }
    }
}
$current_role = $_SESSION['role'] ?? 'master';
$avatar_url = $_SESSION['avatar_url'] ?? 'https://cdn.discordapp.com/embed/avatars/0.png';

// Если пользователь залогинен, обновляем его время активности
if (isset($_SESSION['username'])) {
    try {
        if (!empty($_SESSION['discord_id'])) {
            // Самый надежный способ - по Discord ID
            $stmtLastSeen = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE discord_id = ?");
            $stmtLastSeen->execute([$_SESSION['discord_id']]);
        } else {
            // Если ID нет (старый вход), обновляем по нику
            $stmtLastSeen = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE username = ?");
            $stmtLastSeen->execute([$_SESSION['username']]);
        }
    } catch (Exception $e) {}
}

require_once 'staff_functions.php';

// Красивое название роли
$role_names = [
    'admin' => 'Администратор',
    'chief' => 'Гл. Куратор',
    'curator' => 'Куратор',
    'master' => 'Мастер'
];
$role_display = $role_names[$current_role] ?? $current_role;

// Аватарка: используем общую функцию
$avatar_url = getAvatarUrl($discord_id, $username);

$sidebarPendingCount = 0;
?>
<script>
    // Инициализация темы и шрифта до отрисовки основной части
    (function() {
        const savedTheme = localStorage.getItem('site_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
        
        const savedFont = localStorage.getItem('site_font') || "'Inter', sans-serif";
        document.documentElement.style.setProperty('--font-family', savedFont);
    })();
</script>
