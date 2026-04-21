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

// Красивое название роли
$role_names = [
    'admin' => 'Администратор',
    'chief' => 'Гл. Куратор',
    'curator' => 'Куратор',
    'master' => 'Мастер'
];
$role_display = $role_names[$current_role] ?? $current_role;

// Аватарка: обращаемся к Lanyard API (бесплатно, без токена)
// Если discord_id не числовой (например admin = 'system'), показываем заглушку

if ($discord_id && $discord_id !== 'system' && is_numeric($discord_id)) {
    $api_response = @file_get_contents("https://api.lanyard.rest/v1/users/{$discord_id}");
    if ($api_response) {
        $data = json_decode($api_response, true);
        $avatar_hash = $data['data']['discord_user']['avatar'] ?? null;
        if ($avatar_hash) {
            $ext = str_starts_with($avatar_hash, 'a_') ? 'gif' : 'png';
            $avatar_url = "https://cdn.discordapp.com/avatars/{$discord_id}/{$avatar_hash}.{$ext}?size=64";
        }
    }
}

// Глобальный расчет уведомлений для боковой панели
require_once 'staff_functions.php';

$sidebarPendingCount = 0;
if ($current_role === 'admin') {
    $stmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
    $sidebarPendingCount = (int)$stmt->fetchColumn();
} elseif ($current_role === 'curator') {
    $myMasters = getMasterNicksForCurator($_SESSION['username']);
    if (!empty($myMasters)) {
        $placeholders = implode(',', array_fill(0, count($myMasters), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE status = 'pending' AND master_name IN ($placeholders)");
        $stmt->execute($myMasters);
        $sidebarPendingCount = (int)$stmt->fetchColumn();
    }
}
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

<script>
// AJAX tab loader: intercept sidebar links with data-ajax and load into .main-content
(function(){
    function loadAjax(url, pushState) {
        fetch(url + (url.indexOf('?')===-1 ? '?ajax=1' : '&ajax=1'))
            .then(r => r.text())
            .then(html => {
                const main = document.querySelector('.main-content');
                if (main) main.innerHTML = html;
                // update active menu item
                document.querySelectorAll('.menu-item').forEach(el=>el.classList.remove('active'));
                const link = document.querySelector('[data-ajax="' + url + '"]');
                if (link) link.classList.add('active');
                if (pushState) history.pushState({ajax:true, url:url}, '', '#'+url.replace('.php',''));
            }).catch(err=>{
                console.error('Ajax load error', err);
            });
    }

    document.addEventListener('click', function(e){
        const a = e.target.closest('a[data-ajax]');
        if (!a) return;
        e.preventDefault();
        const url = a.getAttribute('data-ajax');
        if (url) loadAjax(url, true);
    });

    window.addEventListener('popstate', function(e){
        if (e.state && e.state.ajax && e.state.url) {
            loadAjax(e.state.url, false);
        }
    });
})();
</script>

