<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
$u = $_SESSION['username'] ?? '';
$r = $_SESSION['role'] ?? '';
if (!(($u === 'nevermore8465') || ($r === 'admin'))) {
    http_response_code(403);
    echo '<h1>403</h1><p>Доступ только для nevermore8465 и системного админа.</p>';
    exit;
}
require_once 'db.php';
require_once 'user_header.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>FUTURAMA STAFF | /voice массовая команда</title>
<link rel="stylesheet" href="index.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    .vc-card { max-width: 720px; margin: 0 auto; padding: 2rem; background: var(--card-bg, rgba(20,20,30,0.7)); border-radius: 18px; }
    .vc-title { font-size: 1.6rem; font-weight: 800; margin-bottom: 0.5rem; }
    .vc-sub { color: #aaa; margin-bottom: 1.5rem; }
    .vc-btn { padding: 0.9rem 1.6rem; font-size: 1rem; font-weight: 700; border: none; border-radius: 12px;
              background: var(--accent, #7c3aed); color: #fff; cursor: pointer; transition: transform .15s, opacity .2s; }
    .vc-btn:hover:not(:disabled) { transform: translateY(-2px); }
    .vc-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .vc-status { margin-top: 1.5rem; padding: 1rem; border-radius: 10px; background: rgba(0,0,0,0.3); font-family: monospace; font-size: 0.85rem; white-space: pre-wrap; max-height: 320px; overflow:auto; }
    .vc-pill { display: inline-block; padding: 0.2rem 0.7rem; border-radius: 20px; font-size: 0.8rem; font-weight: 700; margin-left: 0.5rem; }
    .vc-pill.pending { background: #f59e0b; color: #000; }
    .vc-pill.processing { background: #3b82f6; color: #fff; }
    .vc-pill.done { background: #10b981; color: #fff; }
    .vc-pill.failed { background: #ef4444; color: #fff; }
    .vc-rtable { width: 100%; border-collapse: collapse; background: rgba(0,0,0,0.25); border-radius: 10px; overflow: hidden; font-size: 0.85rem; }
    .vc-rtable th, .vc-rtable td { padding: 0.45rem 0.6rem; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .vc-rtable th { background: rgba(0,0,0,0.4); color: #aaa; font-weight: 700; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; }
    .vc-rtable tr:hover { background: rgba(255,255,255,0.02); }
    .vc-shiftgroup { margin-bottom: 1rem; }
    .vc-shifthdr { padding: 0.5rem 0.8rem; background: rgba(124,58,237,0.18); border-left: 3px solid var(--accent,#7c3aed); border-radius: 6px; margin-bottom: 0.4rem; font-weight: 700; }
    .vc-tabs { display: flex; gap: 0.4rem; flex-wrap: wrap; }
    .vc-tab { padding: 0.35rem 0.8rem; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); cursor: pointer; font-size: 0.85rem; }
    .vc-tab.active { background: var(--accent,#7c3aed); border-color: transparent; }
    .vc-zero { color: #555; }
    .vc-tot { color: var(--accent,#7c3aed); font-weight: 700; }
</style>
</head>
<body>
<?php include 'sidebar_v2.php'; ?>
<main class="main-content">
    <div class="vc-card">
        <div class="vc-title"><i class="fa-solid fa-microphone"></i> Массовый /voice по смене 7-9</div>
        <div class="vc-sub">Запускает <code>/voice группа:Support target:&lt;id&gt;</code> в канале <b>1218497082168705145</b> для каждого саппорта, кто стоит на смене 7, 8 или 9 в таблице.</div>

        <button id="runBtn" class="vc-btn"><i class="fa-solid fa-play"></i> Запустить</button>
        <button id="resetBtn" class="vc-btn" style="background:#ef4444;margin-left:0.5rem"><i class="fa-solid fa-rotate-left"></i> Сбросить очередь</button>

        <div id="status" class="vc-status">Загрузка статуса…</div>

        <div id="resultsWrap" style="margin-top:1.5rem;display:none">
            <h3 style="margin:0 0 0.6rem 0;font-size:1.05rem"><i class="fa-solid fa-table"></i> Результат за текущую неделю</h3>
            <div id="shiftFilter" style="margin-bottom:0.6rem"></div>
            <div id="resultsBody"></div>
        </div>
    </div>
</main>

<script>
const $btn = document.getElementById('runBtn');
const $status = document.getElementById('status');

function fmt(t) { return t ? new Date(t.replace(' ', 'T')+'Z').toLocaleString('ru-RU') : '—'; }

async function parseUtc(t) {
    if (!t) return null;
    return new Date(t.replace(' ', 'T') + 'Z');
}
function fmtRemain(ms) {
    if (ms <= 0) return 'почти готово';
    const m = Math.ceil(ms / 60000);
    if (m < 60) return `~${m} мин`;
    const h = Math.floor(m / 60); const r = m % 60;
    return `~${h}ч ${r}м`;
}
function calcEta(d) {
    // Из лога ищем "Прогон ~X мин"
    let estMin = null;
    if (d.log) {
        const m = d.log.match(/Прогон\s+~(\d+)\s*мин/);
        if (m) estMin = parseInt(m[1], 10);
    }
    // Если не нашли — грубо оцениваем: ~50 сек на человека
    if (!estMin && d.total) estMin = Math.ceil((d.total * 50) / 60);
    if (!estMin) estMin = 15;

    const start = parseUtc(d.requested_at);
    if (!start) return null;
    const eta = new Date(start.getTime() + estMin * 60 * 1000);
    const remain = eta.getTime() - Date.now();
    return { eta, remain, estMin };
}

function refresh() {
    try {
        const r = await fetch('api.php?action=voice_cmd_status');
        const j = await r.json();
        const d = j.data;
        if (!d) { $status.textContent = 'Запросов ещё не было.'; return; }

        const statusPill = `<span class="vc-pill ${d.status}">${d.status}</span>`;
        let html = `<div><b>Последний запрос #${d.id}</b> ${statusPill}</div>`;
        html += `<div>Запросил: ${d.requested_by}</div>`;
        html += `<div>Когда: ${fmt(d.requested_at)} → ${fmt(d.completed_at)}</div>`;
        html += `<div>Смены: ${d.shifts} | Всего: ${d.total} | OK: ${d.success_count} | FAIL: ${d.fail_count}</div>`;

        // ETA для pending/processing
        if (d.status === 'pending' || d.status === 'processing') {
            const eta = calcEta(d);
            if (eta) {
                const etaTime = eta.eta.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
                const remain = fmtRemain(eta.remain);
                html += `<div style="margin-top:0.6rem;padding:0.6rem 0.8rem;background:rgba(124,58,237,0.15);border-left:3px solid var(--accent,#7c3aed);border-radius:6px">`;
                html += `<i class="fa-regular fa-clock"></i> Ориентировочно готово в <b>${etaTime}</b> (через ${remain})`;
                html += `</div>`;
            }
        }

        if (d.log) html += `<hr><pre style="margin:0;white-space:pre-wrap">${d.log.replace(/</g,'&lt;')}</pre>`;
        $status.innerHTML = html;

        // Если ещё в работе — блокируем кнопку и через 3с перепроверяем
        if (d.status === 'pending' || d.status === 'processing') {
            $btn.disabled = true;
            setTimeout(refresh, 3000);
        } else {
            $btn.disabled = false;
        }
    } catch (e) {
        $status.textContent = 'Ошибка загрузки: ' + e.message;
    }
}

document.getElementById('resetBtn').addEventListener('click', async () => {
    if (!confirm('Удалить все записи из очереди и снять блокировку?')) return;
    try {
        const r = await fetch('api.php?action=voice_cmd_reset', { method: 'POST' });
        const j = await r.json();
        if (!j.success) { alert('Ошибка сброса: ' + (j.error || 'неизвестно')); return; }
        refresh();
    } catch (e) { alert('Сеть: ' + e.message); }
});

$btn.addEventListener('click', async () => {
    if (!confirm('Прогнать /voice для всех саппортов смены 7-9?')) return;
    $btn.disabled = true;
    try {
        const r = await fetch('api.php?action=voice_cmd_request', { method: 'POST' });
        const j = await r.json();
        if (!j.success) { alert('Ошибка: ' + (j.error || 'неизвестно')); $btn.disabled = false; return; }
        refresh();
    } catch (e) {
        alert('Сеть: ' + e.message);
        $btn.disabled = false;
    }
});

refresh();

// === Таблица результатов ===
const $resultsWrap = document.getElementById('resultsWrap');
const $resultsBody = document.getElementById('resultsBody');
const $shiftFilter = document.getElementById('shiftFilter');
let resultsRows = [];
let activeShift = 'all';

function fmtSec(sec) {
    sec = parseInt(sec, 10) || 0;
    if (sec <= 0) return '<span class="vc-zero">—</span>';
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    const p = [];
    if (h) p.push(h + 'ч');
    if (m) p.push(m + 'м');
    if (!h && !m) p.push(s + 'с');
    return p.join(' ');
}

function renderResults() {
    const filtered = activeShift === 'all' ? resultsRows : resultsRows.filter(r => String(r.shift) === activeShift);
    if (!filtered.length) {
        $resultsBody.innerHTML = '<div style="padding:1rem;color:#888;text-align:center">Нет данных. Возможно бот ещё не ответил или парсер не сработал.</div>';
        return;
    }
    // Группируем по сменам
    const byShift = {};
    filtered.forEach(r => {
        const sh = r.shift || '—';
        (byShift[sh] = byShift[sh] || []).push(r);
    });
    const shifts = Object.keys(byShift).sort();

    let html = '';
    shifts.forEach(sh => {
        const list = byShift[sh].slice().sort((a, b) => (b.total_seconds|0) - (a.total_seconds|0));
        const sum = list.reduce((acc, r) => acc + (r.total_seconds|0), 0);
        html += `<div class="vc-shiftgroup">`;
        html += `<div class="vc-shifthdr">Смена ${sh} · ${list.length} чел · итого ${fmtSec(sum)}</div>`;
        html += `<table class="vc-rtable"><thead><tr>`;
        html += `<th>#</th><th>Ник</th><th>Пн</th><th>Вт</th><th>Ср</th><th>Чт</th><th>Пт</th><th>Сб</th><th>Вс</th><th>Итого</th>`;
        html += `</tr></thead><tbody>`;
        list.forEach((r, i) => {
            html += `<tr>`;
            html += `<td>${i+1}</td>`;
            html += `<td>${(r.nick||'').replace(/</g,'&lt;')}<br><span style="color:#666;font-size:0.7rem">${r.discord_id}</span></td>`;
            html += `<td>${fmtSec(r.mon_seconds)}</td>`;
            html += `<td>${fmtSec(r.tue_seconds)}</td>`;
            html += `<td>${fmtSec(r.wed_seconds)}</td>`;
            html += `<td>${fmtSec(r.thu_seconds)}</td>`;
            html += `<td>${fmtSec(r.fri_seconds)}</td>`;
            html += `<td>${fmtSec(r.sat_seconds)}</td>`;
            html += `<td>${fmtSec(r.sun_seconds)}</td>`;
            html += `<td class="vc-tot">${fmtSec(r.total_seconds)}</td>`;
            html += `</tr>`;
        });
        html += `</tbody></table></div>`;
    });
    $resultsBody.innerHTML = html;
}

function renderShiftFilter() {
    const shifts = Array.from(new Set(resultsRows.map(r => r.shift).filter(Boolean))).sort();
    if (!shifts.length) { $shiftFilter.innerHTML = ''; return; }
    let html = '<div class="vc-tabs">';
    html += `<div class="vc-tab ${activeShift==='all'?'active':''}" data-sh="all">Все смены</div>`;
    shifts.forEach(sh => {
        html += `<div class="vc-tab ${activeShift===sh?'active':''}" data-sh="${sh}">Смена ${sh}</div>`;
    });
    html += '</div>';
    $shiftFilter.innerHTML = html;
    $shiftFilter.querySelectorAll('.vc-tab').forEach(el => {
        el.addEventListener('click', () => { activeShift = el.dataset.sh; renderShiftFilter(); renderResults(); });
    });
}

async function loadResults() {
    try {
        const r = await fetch('api.php?action=voice_stats_get');
        const j = await r.json();
        if (!j.success || !j.rows?.length) {
            $resultsWrap.style.display = 'none';
            return;
        }
        resultsRows = j.rows;
        $resultsWrap.style.display = 'block';
        renderShiftFilter();
        renderResults();
    } catch (e) {
        $resultsWrap.style.display = 'none';
    }
}

// Грузим результат при открытии и обновляем каждые 10 сек
loadResults();
setInterval(loadResults, 10000);
</script>
</body>
</html>
