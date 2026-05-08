<?php
session_start();
$config = include 'app_config.php';
$url = $config['app_script_webhook_url'];
$token = $config['app_script_webhook_token'];

$payload = [
    'token' => $token,
    'action' => 'add_support',
    'nick' => 'Debug_User_' . rand(100, 999),
    'discord_id' => '1234567890' . rand(0, 9),
    'shift' => '1',
    'date' => date('d.m.Y')
];

echo "<!DOCTYPE html><html lang='ru'><head><meta charset='UTF-8'><title>Webhook Debug</title>";
echo "<style>body{background:#0f172a;color:#e2e8f0;font-family:sans-serif;padding:40px;} pre{background:#1e293b;padding:15px;border-radius:8px;border:1px solid #334155;overflow-x:auto;} .success{color:#10b981;} .error{color:#ef4444;}</style></head><body>";

echo "<h1>Отладка Webhook Google</h1>";
echo "<p><b>Целевой URL:</b> <br><code>" . htmlspecialchars($url) . "</code></p>";
echo "<p><b>Токен из конфига:</b> <code>" . htmlspecialchars($token) . "</code></p>";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

echo "<h3>Выполнение запроса...</h3>";
$response = curl_exec($ch);
$info = curl_getinfo($ch);
$error = curl_error($ch);
curl_close($ch);

echo "<p><b>HTTP Статус:</b> " . $info['http_code'] . "</p>";
if ($error) {
    echo "<p class='error'><b>Ошибка cURL:</b> $error</p>";
}

echo "<h3>СЫРОЙ ОТВЕТ ОТ GOOGLE:</h3>";
echo "<pre>";
if ($response) {
    echo htmlspecialchars($response);
} else {
    echo "ОТВЕТ ПУСТОЙ";
}
echo "</pre>";

echo "<h3>Попытка парсинга JSON:</h3>";
$json = json_decode($response, true);
if ($json) {
    echo "<pre>" . print_r($json, true) . "</pre>";
    if (isset($json['ok']) && $json['ok'] === true) {
        echo "<h2 class='success'>СВЯЗЬ УСПЕШНО УСТАНОВЛЕНА!</h2>";
    } else {
        echo "<h2 class='error'>ОШИБКА ВНУТРИ JSON</h2>";
    }
} else {
    echo "<p class='error'>Ответ не является валидным JSON-объектом.</p>";
}

echo "</body></html>";
