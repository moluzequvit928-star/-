<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'user_header.php';

$role = $_SESSION['role'] ?? 'master';
if (!in_array($role, ['admin', 'chief', 'curator'])) {
    header('Location: index.php');
    exit;
}

// Группируем по discord_id
$people = [];
$lastUpdate = null;
try {
    $rows = $pdo->query("SELECT * FROM double_staff ORDER BY username ASC, guild_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $id = $r['discord_id'];
        if (!isset($people[$id])) {
            $people[$id] = ['username' => $r['username'], 'discord_id' => $id, 'entries' => []];
        }
        $people[$id]['entries'][] = ['guild' => $r['guild_name'], 'role' => $r['role_name']];
        if (!$lastUpdate || $r['updated_at'] > $lastUpdate) $lastUpdate = $r['updated_at'];
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FUTURAMA STAFF | Дабл-стафф</title>
    <link rel="icon" type="image/png" href="favicon_futurama_staff_1776084855108.png">
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .ds-card { background: rgba(30,41,59,0.4); border:1px solid rgba(255,255,255,0.1); border-radius:16px; padding:1.25rem 1.5rem; margin-bottom:0.9rem; backdrop-filter:blur(10px); }
        .ds-name { font-weight:800; color:#fff; font-size:1.05rem; }
        .ds-tag { display:inline-flex; align-items:center; gap:6px; background:rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.3); color:#F87171; padding:4px 10px; border-radius:8px; font-size:0.8rem; font-weight:600; margin:4px 6px 0 0; }
        .ds-tag .g { color:#FBBF24; font-weight:800; }
        .muted { color:#64748B; font-size:0.85rem; }
        .ds-search { width:100%; max-width:320px; padding:0.6rem 0.9rem; border-radius:10px; border:1px solid rgba(255,255,255,0.1); background:rgba(15,23,42,0.6); color:#F8FAFC; outline:none; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Дабл-стафф</h1>
                    <p>Кто из стафа состоит в стафе на других серверах</p>
                </div>
                <div class="header-actions">
                    <a href="logout.php" class="btn-logout-premium"><i class="fas fa-sign-out-alt"></i> Выйти</a>
                </div>
            </header>

            <div class="page-body">
            <section class="content">
                <div style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap; margin-bottom:1.25rem;">
                    <div class="ds-card" style="margin:0; display:flex; align-items:center; gap:1rem;">
                        <div style="width:46px;height:46px;background:rgba(239,68,68,0.1);color:#ef4444;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;"><i class="fas fa-user-secret"></i></div>
                        <div>
                            <div style="font-size:1.4rem;font-weight:800;color:#fff;"><?= count($people) ?></div>
                            <div class="muted" style="text-transform:uppercase;font-weight:600;">Найдено дабл-стаффов</div>
                        </div>
                    </div>
                    <div class="muted">
                        <?= $lastUpdate ? 'Обновлено: ' . date('d.m.Y H:i', strtotime($lastUpdate)) : 'Данных ещё нет — запусти чекер' ?>
                    </div>
                    <input type="text" id="dsSearch" class="ds-search" placeholder="Поиск по нику или ID..." oninput="filterDs()" style="margin-left:auto;">
                </div>

                <div id="dsList">
                    <?php if (!$people): ?>
                        <div class="ds-card" style="text-align:center; color:#64748B; padding:3rem;">
                            Пока пусто. Данные появятся автоматически после первого скана voice-трекера (примерно через минуту после его запуска).
                        </div>
                    <?php else: foreach ($people as $p):
                        $nick = $p['username'] ?: ('ID ' . $p['discord_id']);
                    ?>
                        <div class="ds-card ds-row" data-search="<?= htmlspecialchars(mb_strtolower($nick . ' ' . $p['discord_id'])) ?>">
                            <div class="ds-name"><?= htmlspecialchars($nick) ?></div>
                            <div class="muted">ID: <?= htmlspecialchars($p['discord_id']) ?></div>
                            <div style="margin-top:0.5rem;">
                                <?php foreach ($p['entries'] as $e): ?>
                                    <span class="ds-tag"><i class="fas fa-shield-halved"></i> <span class="g"><?= htmlspecialchars($e['guild']) ?></span> · <?= htmlspecialchars($e['role']) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </section>
            </div>
        </main>
    </div>

    <script>
        function filterDs() {
            const q = (document.getElementById('dsSearch').value || '').toLowerCase().trim();
            document.querySelectorAll('#dsList .ds-row').forEach(row => {
                row.style.display = !q || (row.dataset.search || '').includes(q) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
