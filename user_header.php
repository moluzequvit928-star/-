<?php
require_once 'db.php';

// Получаем дискорд ID из сессии
$discord_id = $_SESSION['discord_id'] ?? null;
$username = $_SESSION['username'] ?? 'Гость';

// Получаем актуальные данные пользователя из БД при каждой загрузке
if ($username !== 'Гость') {
    try {
        $stmtUser = $pdo->prepare("SELECT role, discord_id FROM users WHERE username = ?");
        $stmtUser->execute([$username]);
        $dbUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
        
        if ($dbUser) {
            $_SESSION['role'] = $dbUser['role'];
            if (empty($_SESSION['discord_id'])) {
                $_SESSION['discord_id'] = $dbUser['discord_id'];
            }
        }
    } catch (Exception $e) {}
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
