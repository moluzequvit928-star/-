<?php
// Временный диагностический скрипт. Открой на проде:
//   https://cooperative-joy-production-fa8a.up.railway.app/diag_webhook.php
// После диагностики удали этот файл.

require_once 'staff_functions.php';
header('Content-Type: text/plain; charset=utf-8');

$url   = configValue('APP_SCRIPT_WEBHOOK_URL', 'app_script_webhook_url');
$token = configValue('APP_SCRIPT_WEBHOOK_TOKEN', 'app_script_webhook_token');

echo "Источник URL: " . (getenv('APP_SCRIPT_WEBHOOK_URL') ? 'ENV (Railway)' : 'app_config.php') . "\n";
echo "URL (хвост): ..." . substr($url, -28) . "\n";
echo "Токен задан: " . ($token !== '' ? 'да' : 'НЕТ') . "\n\n";

$payload = [
    'token'      => $token,
    'action'     => 'update_reattestation',
    'discord_id' => '000000000000000000', // несуществующий id — таблица не изменится
    'result'     => 'не сдал',
    'status'     => 'не сдал',
    'attempt'    => '1/3',
    'curator'    => 'DIAG',
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$resp     = curl_exec($ch);
$code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$err      = curl_error($ch);
curl_close($ch);

echo "HTTP код: " . $code . "\n";
echo "cURL ошибка: " . ($err ?: '(нет)') . "\n";
echo "Итоговый URL после редиректов: " . $finalUrl . "\n";
echo "JSON распарсился: " . (json_decode($resp, true) !== null ? 'ДА' : 'НЕТ (значит вернулся не JSON)') . "\n";
echo "\n----- СЫРОЙ ОТВЕТ GOOGLE -----\n";
echo $resp . "\n";
echo "----- КОНЕЦ -----\n";
