<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'user_header.php';
$role = $_SESSION['role'] ?? 'master';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FUTURAMA STAFF | Достижения</title>
    <link rel="icon" type="image/png" href="favicon_futurama_staff_1776084855108.png">
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .ach-card { background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; backdrop-filter: blur(10px); }
        .ach-summary { display:flex; align-items:center; gap:1.25rem; flex-wrap:wrap; }
        .ach-ring { width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; color:#fff; flex-shrink:0; }
        .ach-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:0.8rem; }
        .ach-item { border-radius:12px; padding:1rem; }
        .ach-item.locked { background:rgba(15,23,42,0.4); border:1px solid rgba(255,255,255,0.06); }
        .ach-item.unlocked { background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.35); }
        .ach-track { height:8px; background:rgba(255,255,255,0.08); border-radius:999px; overflow:hidden; margin-top:8px; }
        .ach-fill { height:100%; background:linear-gradient(90deg,#F59E0B,#FBBF24); border-radius:999px; }
        .ach-done { color:#10B981; font-weight:800; }
        .muted { color:#64748B; font-size:0.85rem; }
        .ach-section-title { color:#94A3B8; font-size:0.8rem; text-transform:uppercase; letter-spacing:1.5px; margin:1.5rem 0 0.7rem; font-weight:700; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Достижения</h1>
                    <p>Награды за работу, стаж и прокачку питомца</p>
                </div>
                <div class="header-actions">
                    <a href="logout.php" class="btn-logout-premium"><i class="fas fa-sign-out-alt"></i> Выйти</a>
                </div>
            </header>

            <div class="page-body">
            <section class="content">
                <div id="achRoot">
                    <div class="ach-card" style="text-align:center; color:#64748B;">Загрузка...</div>
                </div>
            </section>
            </div>
        </main>
    </div>

    <script>
        function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

        // Группировка по типу метрики (по префиксу id)
        const GROUPS = [
            { key:'reatt', title:'<i class="fas fa-clipboard-list"></i> Переаттестации' },
            { key:'supp',  title:'<i class="fas fa-handshake"></i> Добавление саппортов' },
            { key:'days',  title:'<i class="fas fa-tree"></i> Стаж на ветке' },
            { key:'lvl',   title:'<i class="fas fa-paw"></i> Питомец' },
        ];

        function itemHtml(a) {
            const pct = Math.min(100, Math.round(a.progress / a.goal * 100));
            return `
                <div class="ach-item ${a.unlocked?'unlocked':'locked'}">
                    <div style="display:flex; align-items:center; gap:0.8rem;">
                        <div style="width:42px; height:42px; flex-shrink:0; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; background:${a.unlocked?'rgba(16,185,129,0.15)':'rgba(255,255,255,0.05)'}; color:${a.unlocked?'#34D399':'#64748B'};"><i class="fas ${a.icon}"></i></div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:700; color:#F8FAFC;">${esc(a.title)} ${a.unlocked?'<span class="ach-done">✓</span>':''}</div>
                            <div class="muted">${esc(a.desc)}</div>
                        </div>
                        ${a.xp>0?`<div style="font-weight:800; color:#FBBF24; font-size:0.8rem;">+${a.xp} XP</div>`:''}
                    </div>
                    ${a.unlocked?'':`<div class="ach-track"><div class="ach-fill" style="width:${pct}%"></div></div>
                    <div class="muted" style="margin-top:4px;">${a.progress} / ${a.goal}</div>`}
                </div>`;
        }

        async function loadAchievements() {
            const root = document.getElementById('achRoot');
            const res = await fetch('api.php?action=achievements_get&t=' + Date.now()).then(r=>r.json());
            if (!res.success || !res.achievements) {
                root.innerHTML = '<div class="ach-card">Не удалось загрузить достижения.</div>';
                return;
            }
            const list = res.achievements;
            const total = list.length;
            const done = list.filter(a => a.unlocked).length;
            const pct = total ? Math.round(done/total*100) : 0;

            let html = `
                <div class="ach-card">
                    <div class="ach-summary">
                        <div class="ach-ring" style="background:conic-gradient(#10B981 ${pct*3.6}deg, rgba(255,255,255,0.08) 0deg);">
                            <div style="width:50px;height:50px;border-radius:50%;background:#0f172a;display:flex;align-items:center;justify-content:center;font-size:0.85rem;">${pct}%</div>
                        </div>
                        <div>
                            <div style="font-size:1.4rem; font-weight:800; color:#fff;">${done} из ${total}</div>
                            <div class="muted">достижений разблокировано</div>
                        </div>
                    </div>
                </div>`;

            let body = '';
            GROUPS.forEach(g => {
                const items = list.filter(a => a.id.indexOf(g.key) === 0);
                if (items.length === 0) return;
                body += `<div class="ach-section-title">${g.title}</div><div class="ach-grid">${items.map(itemHtml).join('')}</div>`;
            });
            html += `<div class="ach-card">${body || '<p class="muted">Достижений нет.</p>'}</div>`;

            root.innerHTML = html;
        }

        document.addEventListener('DOMContentLoaded', loadAchievements);
    </script>
</body>
</html>
