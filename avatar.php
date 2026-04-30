<?php
/**
 * Прокси-скрипт для получения аватарок через дискорд-бота.
 * Это решает проблему Mixed Content (HTTP/HTTPS) и закрытых портов.
 */

$discord_id = $_GET['id'] ?? '';
$username = $_GET['seed'] ?? 'default';

if (!$discord_id || $discord_id === 'system' || !is_numeric($discord_id)) {
    // Редирект на Dicebear если нет ID
    header("Location: https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($username) . "&backgroundColor=b6e3f4,c0aede,d1d4f9");
    exit;
}

// Пробуем получить аватарку от локального бота (порт 3000)
$bot_url = "http://localhost:3000/avatar?id=" . $discord_id;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $bot_url);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Быстрый таймаут если бот не запущен

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 302) {
    // Бот нашел аватарку и вернул редирект
    if (preg_match('/Location: (.*)/i', $response, $matches)) {
        header("Location: " . trim($matches[1]));
        exit;
    }
}

// Если бот не ответил или не нашел — запасной вариант (Dicebear)
header("Location: https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($username) . "&backgroundColor=b6e3f4,c0aede,d1d4f9");
exit;
