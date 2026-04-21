<?php
session_start();
require_once 'db.php';
$config = @include __DIR__ . '/app_config.php';

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$isAjaxFragment = isset($_GET['ajax']) && $_GET['ajax'];
$isAjaxRequest = $isAjaxFragment || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

$response = ['ok' => false, 'error' => null, 'message' => null];

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nick = trim($_POST['nick'] ?? '');
    $discord = trim($_POST['discord_id'] ?? '');
    $shift = intval($_POST['shift'] ?? 0);
    $start_at = trim($_POST['start_at'] ?? '');

    if ($nick === '' || $discord === '') {
        $response['error'] = 'Ник и ID обязательны.';
    } elseif ($shift < 0 || $shift > 12) {
        $response['error'] = 'Выберите корректную смену (0-12).';
    } else {
        $webhook = $config['app_script_webhook_url'] ?? '';
        $token = $config['app_script_webhook_token'] ?? '';

        if (!$webhook) {
            $response['error'] = 'Webhook не настроен в app_config.php';
        } else {
            $payload = [
                'token' => $token,
                'action' => 'append_staff_row',
                'nick' => $nick,
                'discord_id' => $discord,
                'shift' => $shift,
                'start_at' => $start_at,
                'added_by' => $_SESSION['username'] ?? 'unknown'
            ];

            $ch = curl_init($webhook);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $res = curl_exec($ch);
            $err = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($res === false || $code >= 400) {
                $response['error'] = 'Ошибка при отправке в Apps Script: ' . ($err ?: $res);
            } else {
                $response['ok'] = true;
                $response['message'] = 'Данные отправлены в таблицу.';
            }
        }
    }

    if ($isAjaxRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
        exit;
    }
}

function render_fragment($config) {
    ?>
    <div class="card glass">
        <div class="card-header"><h3>Добавить саппорта</h3></div>
        <div class="card-body">
            <div id="add-support-msg"></div>
            <form id="add-support-form" method="POST" style="display:flex; flex-direction:column; gap:0.75rem; max-width:520px;">
                <label>Ник саппорта
                    <input name="nick" class="form-input" placeholder="например: supportnick" required>
                </label>
                <label>ID (Discord)
                    <input name="discord_id" class="form-input" placeholder="123456789012345678" required>
                </label>
                <label>Смена
                    <select name="shift" class="form-select">
                        <?php for ($i = 0; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>"><?= $i === 0 ? '0 (доп.смена)' : $i . ' смена' ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
                <label>Когда начал (локальное время)
                    <input type="datetime-local" name="start_at" class="form-input">
                </label>

                <div style="display:flex; gap:0.6rem;">
                    <button type="submit" class="btn btn-primary">Добавить в таблицу</button>
                    <a href="https://docs.google.com/spreadsheets/d/<?= htmlspecialchars($config['google_sheet_id'] ?? '') ?>/edit#gid=<?= htmlspecialchars($config['main_sheet_gid'] ?? '') ?>" class="btn" target="_blank">Открыть таблицу</a>
                </div>
            </form>
            <hr style="margin:1.25rem 0;">
            <h4>Интеграция Apps Script</h4>
            <p style="color:#94A3B8;">Скрипт принимает JSON POST: <code>{token, action:'append_staff_row', nick, discord_id, shift, start_at, added_by}</code>. Токен и URL берутся из <code>app_config.php</code>.</p>
        </div>
    </div>

    <script>
    (function(){
        const form = document.getElementById('add-support-form');
        const msg = document.getElementById('add-support-msg');
        form.addEventListener('submit', function(ev){
            ev.preventDefault();
            const data = new FormData(form);
            data.append('ajax', '1');
            fetch('add_support.php', {method:'POST', body: data})
            .then(r => r.json())
            .then(j => {
                if (j.ok) {
                    msg.innerHTML = '<div style="padding:0.6rem;border-radius:6px;background:rgba(16,185,129,0.08);color:#10B981">'+(j.message||'OK')+'</div>';
                    form.reset();
                } else {
                    msg.innerHTML = '<div style="padding:0.6rem;border-radius:6px;background:rgba(239,68,68,0.06);color:#EF4444">'+(j.error||'Ошибка')+'</div>';
                }
            }).catch(err=>{
                msg.innerHTML = '<div style="padding:0.6rem;border-radius:6px;background:rgba(239,68,68,0.06);color:#EF4444">'+String(err)+'</div>';
            });
        });
    })();
    </script>
    <?php
}

if ($isAjaxFragment) {
    render_fragment($config);
    exit;
}

require_once 'user_header.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Добавить саппорта</title>
    <link rel="stylesheet" href="index.css">
</head>
<body>

<main class="main-content" style="max-width:900px;margin:2rem auto;">
    <?php render_fragment($config); ?>
</main>

</body>
</html>
