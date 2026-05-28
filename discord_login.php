<?php
session_start();
require_once 'db.php';
require_once 'staff_functions.php';

// Конфиг Discord OAuth (env приоритетнее app_config.php)
$clientId     = configValue('DISCORD_CLIENT_ID', 'discord_client_id');
$clientSecret = configValue('DISCORD_CLIENT_SECRET', 'discord_client_secret');

// redirect_uri: берём из конфига, иначе вычисляем из текущего адреса
$redirectUri = configValue('DISCORD_REDIRECT_URI', 'discord_redirect_uri');
if (!$redirectUri) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $redirectUri = $scheme . '://' . $host . '/discord_login.php';
}

function redirectToLoginWithError($msg)
{
    $_SESSION['discord_login_error'] = $msg;
    header('Location: login.php');
    exit;
}

if (!$clientId || !$clientSecret) {
    redirectToLoginWithError('Вход через Discord не настроен (нет client_id / client_secret).');
}

// === ШАГ 1: нет кода — отправляем пользователя на авторизацию Discord ===
if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['discord_oauth_state'] = $state;

    $params = http_build_query([
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'identify',
        'state'         => $state,
    ]);
    header('Location: https://discord.com/api/oauth2/authorize?' . $params);
    exit;
}

// === ШАГ 2: вернулись с кодом — проверяем state ===
$state = $_GET['state'] ?? '';
if (!$state || !isset($_SESSION['discord_oauth_state']) || !hash_equals($_SESSION['discord_oauth_state'], $state)) {
    redirectToLoginWithError('Сбой проверки безопасности (state). Попробуйте ещё раз.');
}
unset($_SESSION['discord_oauth_state']);

// === ШАГ 3: меняем код на access_token ===
$ch = curl_init('https://discord.com/api/oauth2/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'grant_type'    => 'authorization_code',
    'code'          => $_GET['code'],
    'redirect_uri'  => $redirectUri,
]));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$tokenResp = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($tokenResp, true);
if (!isset($tokenData['access_token'])) {
    redirectToLoginWithError('Не удалось получить токен Discord. ' . htmlspecialchars($tokenData['error_description'] ?? ''));
}

// === ШАГ 4: получаем данные пользователя ===
$ch = curl_init('https://discord.com/api/users/@me');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenData['access_token']]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$userResp = curl_exec($ch);
curl_close($ch);

$dUser = json_decode($userResp, true);
if (!isset($dUser['id'])) {
    redirectToLoginWithError('Не удалось получить профиль Discord.');
}

$discordId   = (string) $dUser['id'];
$discordName = $dUser['global_name'] ?? $dUser['username'] ?? '';

// === ШАГ 5: ищем сотрудника в БД ===
try {
    // 1) по discord_id
    $stmt = $pdo->prepare("SELECT * FROM users WHERE discord_id = ?");
    $stmt->execute([$discordId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2) если не нашли — пробуем по нику Discord (и привязываем discord_id для будущих входов)
    if (!$user && $discordName !== '') {
        $stmt2 = $pdo->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
        $stmt2->execute([$discordName]);
        $user = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $pdo->prepare("UPDATE users SET discord_id = ? WHERE id = ?")->execute([$discordId, $user['id']]);
            $user['discord_id'] = $discordId;
        }
    }
} catch (Exception $e) {
    redirectToLoginWithError('Ошибка БД при входе.');
}

if (!$user) {
    redirectToLoginWithError('Этот Discord не привязан к аккаунту на сайте. Обратитесь к администратору.');
}

// === ШАГ 6: логиним ===
$_SESSION['user_logged_in'] = true;
$_SESSION['username']       = $user['username'];
$_SESSION['role']           = $user['role'];
$_SESSION['discord_id']     = $user['discord_id'];

// Аватар прямо из Discord (если есть), иначе сработает avatar.php по discord_id
if (!empty($dUser['avatar'])) {
    $ext = strpos($dUser['avatar'], 'a_') === 0 ? 'gif' : 'png';
    $_SESSION['avatar_url'] = "https://cdn.discordapp.com/avatars/{$discordId}/{$dUser['avatar']}.{$ext}?size=128";
}

header('Location: index.php');
exit;
