<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';
require_once 'user_header.php';

function fmtDuration($sec) {
    $sec = (int)$sec;
    if ($sec <= 0) return '—';
    $h = intdiv($sec, 3600);
    $m = intdiv($sec % 3600, 60);
    $s = $sec % 60;
    $parts = [];
    if ($h) $parts[] = $h . 'ч';
    if ($m) $parts[] = $m . 'м';
    if (!$h && !$m) $parts[] = $s . 'с';
    return implode(' ', $parts);
}
function ruDate($iso) {
    if (!$iso) return '';
    $d = DateTime::createFromFormat('Y-m-d', $iso);
    return $d ? $d->format('d.m.Y') : $iso;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>FUTURAMA STAFF | Часы саппортов</title>
<link rel="stylesheet" href="index.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    .vs-wrap { max-width: 1200px; margin: 0 auto; }
    .vs-head { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.2rem; flex-wrap: wrap; }
    .vs-head h1 { margin: 0; font-size: 1.5rem; font-weight: 800; }
    .vs-pick { padding: 0.5rem 0.9rem; border-radius: 10px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; font-size: 0.95rem; }
    .vs-table { width: 100%; border-collapse: collapse; background: rgba(20,20,30,0.6); border-radius: 14px; overflow: hidden; }
    .vs-table th, .vs-table td { padding: 0.6rem 0.8rem; text-align: left; font-size: 0.9rem; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .vs-table th { background: rgba(0,0,0,0.3); font-weight: 700; color: #aaa; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; }
    .vs-table tr:hover { background: rgba(255,255,255,0.03); }
    .vs-total { color: var(--accent, #7c3aed); font-weight: 700; }
    .vs-zero { color: #666; }
    .vs-empty { padding: 3rem; text-align: center; color: #888; }
    .vs-search { padding: 0.5rem 0.9rem; border-radius: 10px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; font-size: 0.95rem; min-width: 220px; }
</style>
</head>
<body>
<?php include 'sidebar_v2.php'; ?>
<main class="main-content">
    <div class="vs-wrap">
        <div class="vs-head">
            <h1><i class="fa-solid fa-microphone"></i> Часы саппортов по неделям</h1>
            <select id="weekPick" class="vs-pick"><option>Загрузка...</option></select>
            <input id="search" class="vs-search" placeholder="Поиск по нику / ID...">
            <div id="meta" style="color:#777;font-size:0.85rem;margin-left:auto"></div>
        </div>
        <div id="tableBox"></div>
    </div>
</main>

<script>
const $week = document.getElementById('weekPick');
const $box = document.getElementById('tableBox');
const $search = document.getElementById('search');
const $meta = document.getElementById('meta');
let currentRows = [];

function fmt(sec) {
    sec = parseInt(sec, 10) || 0;
    if (sec <= 0) return '<span class="vs-zero">—</span>';
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    const p = [];
    if (h) p.push(h + 'ч');
    if (m) p.push(m + 'м');
    if (!h && !m) p.push(s + 'с');
    return p.join(' ');
}
function ruDate(iso) {
    if (!iso) return '';
    const [y,m,d] = iso.split('-');
    return `${d}.${m}.${y}`;
}

function render() {
    const q = ($search.value || '').toLowerCase().trim();
    const rows = currentRows.filter(r => !q || (r.nick||'').toLowerCase().includes(q) || (r.discord_id||'').includes(q));
    if (!rows.length) { $box.innerHTML = '<div class="vs-empty">Нет данных за выбранную неделю.</div>'; return; }

    let html = '<table class="vs-table"><thead><tr>';
    html += '<th>#</th><th>Ник</th><th>Смена</th><th>Пн</th><th>Вт</th><th>Ср</th><th>Чт</th><th>Пт</th><th>Сб</th><th>Вс</th><th>Итого</th>';
    html += '</tr></thead><tbody>';
    rows.forEach((r, i) => {
        html += '<tr>';
        html += `<td>${i+1}</td>`;
        html += `<td>${(r.nick||'').replace(/</g,'&lt;')} <span style="color:#666;font-size:0.75rem">${r.discord_id}</span></td>`;
        html += `<td>${r.shift||'—'}</td>`;
        html += `<td>${fmt(r.mon_seconds)}</td>`;
        html += `<td>${fmt(r.tue_seconds)}</td>`;
        html += `<td>${fmt(r.wed_seconds)}</td>`;
        html += `<td>${fmt(r.thu_seconds)}</td>`;
        html += `<td>${fmt(r.fri_seconds)}</td>`;
        html += `<td>${fmt(r.sat_seconds)}</td>`;
        html += `<td>${fmt(r.sun_seconds)}</td>`;
        html += `<td class="vs-total">${fmt(r.total_seconds)}</td>`;
        html += '</tr>';
    });
    html += '</tbody></table>';
    $box.innerHTML = html;
    $meta.textContent = `${rows.length} записей`;
}

async function loadWeek(week) {
    $box.innerHTML = '<div class="vs-empty">Загрузка...</div>';
    const url = 'api.php?action=voice_stats_get' + (week ? '&week=' + encodeURIComponent(week) : '');
    const r = await fetch(url);
    const j = await r.json();
    if (!j.success) { $box.innerHTML = '<div class="vs-empty">Ошибка: ' + (j.error || '?') + '</div>'; return; }
    currentRows = j.rows || [];
    // Заполняем дропдаун (только при первой загрузке)
    if (!$week.dataset.filled) {
        $week.innerHTML = '';
        if (!j.weeks?.length) {
            $week.innerHTML = '<option>Нет недель</option>';
        } else {
            j.weeks.forEach(w => {
                const opt = document.createElement('option');
                opt.value = w;
                opt.textContent = 'Неделя с ' + ruDate(w);
                if (w === j.week) opt.selected = true;
                $week.appendChild(opt);
            });
        }
        $week.dataset.filled = '1';
    }
    render();
}

$week.addEventListener('change', () => loadWeek($week.value));
$search.addEventListener('input', render);
loadWeek(null);
</script>
</body>
</html>
