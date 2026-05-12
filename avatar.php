<?php
/**
 * Прокси-скрипт для получения аватарок через дискорд-бота.
 * ОПТИМИЗИРОВАНО: Быстрый таймаут, чтобы сайт не тормозил если бот выключен.
 */

$discord_id = $_GET['id'] ?? '';
$username = $_GET['seed'] ?? 'default';

// Если ID нет или это системный аккаунт - сразу на Dicebear
if (!$discord_id || $discord_id === 'system' || !is_numeric($discord_id)) {
    header("Location: https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($username) . "&backgroundColor=b6e3f4,c0aede,d1d4f9");
    exit;
}

// Пробуем получить аватарку от локального бота (порт 3000)
// Уменьшаем таймаут до 500мс (0.5 сек). Если бот запущен, он ответит за 10-50мс.
$bot_url = "http://127.0.0.1:3000/avatar?id=" . $discord_id;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $bot_url);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 300); // 300мс на подключение
curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);        // 500мс общий таймаут

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 302) {
    if (preg_match('/Location: (.*)/i', $response, $matches)) {
        header("Location: " . trim($matches[1]));
        exit;
    }
}

// Запасной вариант (Dicebear)
header("Location: https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($username) . "&backgroundColor=b6e3f4,c0aede,d1d4f9");
exit;
