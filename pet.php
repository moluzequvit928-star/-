<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'user_header.php';
require_once 'pet_functions.php';
$role = $_SESSION['role'] ?? 'master';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FUTURAMA STAFF | Питомец</title>
    <link rel="icon" type="image/png" href="favicon_futurama_staff_1776084855108.png">
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .pet-card { background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 1.75rem; margin-bottom: 1.5rem; backdrop-filter: blur(10px); }
        .pet-stage { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; padding: 2rem 1rem; background: radial-gradient(circle at 50% 40%, rgba(167,139,250,0.12), transparent 70%); border-radius: 20px; }
        .pet-emoji-big { font-size: 6rem; line-height: 1; filter: drop-shadow(0 8px 16px rgba(0,0,0,0.4)); animation: petIdle 2.4s ease-in-out infinite; cursor: default; }
        @keyframes petIdle { 0%,100% { transform: translateY(0) rotate(-2deg); } 50% { transform: translateY(-14px) rotate(2deg); } }
        .pet-name { font-size: 1.6rem; font-weight: 800; color: #fff; }
        .pet-level-badge { background: linear-gradient(135deg,#6366F1,#A78BFA); color:#fff; padding: 0.3rem 0.9rem; border-radius: 999px; font-weight: 800; font-size: 0.85rem; letter-spacing: 0.5px; }
        .xp-bar-wrap { width: 100%; max-width: 460px; }
        .xp-bar-track { height: 16px; background: rgba(15,23,42,0.7); border-radius: 999px; overflow: hidden; border: 1px solid rgba(255,255,255,0.08); }
        .xp-bar-fill { height: 100%; background: linear-gradient(90deg,#10B981,#34D399); border-radius: 999px; transition: width 0.6s ease; }

        .type-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 0.75rem; margin: 1rem 0; }
        .type-btn { background: rgba(255,255,255,0.04); border: 2px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 1rem 0.5rem; font-size: 2.2rem; cursor: pointer; transition: 0.2s; text-align: center; }
        .type-btn:hover { background: rgba(255,255,255,0.08); transform: translateY(-3px); }
        .type-btn.selected { border-color: #A78BFA; background: rgba(167,139,250,0.15); box-shadow: 0 0 18px rgba(167,139,250,0.35); }
        .type-btn.taken { opacity: 0.32; cursor: not-allowed; filter: grayscale(0.7); position: relative; }
        .type-btn.taken:hover { transform: none; background: rgba(255,255,255,0.04); }
        .type-btn .taken-label { font-size: 0.55rem; margin-top: 4px; color: #F87171; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }

        .pet-input { width: 100%; padding: 0.8rem 1rem; border-radius: 10px; border: 1px solid rgba(255,255,255,0.12); background: rgba(15,23,42,0.6); color: #F8FAFC; font-size: 0.95rem; outline: none; margin-bottom: 0.5rem; }
        .pet-btn { background:#6366F1; color:#fff; border:none; padding: 0.8rem 1.6rem; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .pet-btn:hover { background:#4F46E5; }
        .pet-btn.ghost { background: rgba(255,255,255,0.06); }
        .pet-btn.danger { background: rgba(239,68,68,0.15); color:#F87171; }

        .quest-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding: 1rem; border-radius: 12px; background: rgba(15,23,42,0.4); border:1px solid rgba(255,255,255,0.06); margin-bottom: 0.7rem; flex-wrap: wrap; }
        .quest-progress-track { height: 8px; background: rgba(255,255,255,0.08); border-radius: 999px; overflow:hidden; margin-top: 6px; }
        .quest-progress-fill { height:100%; background: linear-gradient(90deg,#F59E0B,#FBBF24); border-radius:999px; }
        .quest-done { color:#10B981; font-weight: 800; }
        .badge-kind { font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: 6px; background: rgba(99,102,241,0.15); color:#A78BFA; font-weight:700; }
        .pet-grid-2 { display:grid; grid-template-columns: 1fr; gap: 0.6rem; }
        @media(min-width:640px){ .pet-grid-2{ grid-template-columns: 1fr 1fr; } }
        .muted { color:#64748B; font-size:0.85rem; }
        .pet-label { display:block; color:#94A3B8; font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; margin: 0.6rem 0 0.25rem; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Питомец 🐾</h1>
                    <p>Качай уровень за работу и выполняй квесты</p>
                </div>
                <div class="header-actions">
                    <a href="logout.php" class="btn-logout-premium"><i class="fas fa-sign-out-alt"></i> Выйти</a>
                </div>
            </header>

            <div class="page-body">
            <section class="content">
                <div id="petRoot">
                    <div class="pet-card" style="text-align:center; color:#64748B;">Загрузка...</div>
                </div>
            </section>
            </div>
        </main>
    </div>

    <script>
        const MY_ROLE = "<?php echo $role; ?>";
        const PET_TYPES = <?php echo json_encode(petTypes(), JSON_UNESCAPED_UNICODE); ?>;
        let selectedType = 'dog';

        function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

        async function loadPet() {
            const res = await fetch('api.php?action=pet_get&t=' + Date.now()).then(r=>r.json());
            const root = document.getElementById('petRoot');
            if (!res.success) { root.innerHTML = '<div class="pet-card">Ошибка загрузки</div>'; return; }

            if (!res.has_pet) { root.innerHTML = renderCreate(res.taken || []); bindCreate(); return; }

            let html = renderPet(res);
            html += renderQuests(res.quests);
            html += '<div id="leaderboard" class="pet-card"><h2 style="margin-top:0;">🏆 Топ питомцев</h2><p class="muted">Загрузка...</p></div>';
            if (res.is_admin) html += '<div id="adminPanel"></div>';
            root.innerHTML = html;
            loadLeaderboard();
            if (res.is_admin) loadAdmin();
        }

        async function loadLeaderboard() {
            const box = document.getElementById('leaderboard');
            if (!box) return;
            const res = await fetch('api.php?action=pet_leaderboard&t=' + Date.now()).then(r=>r.json());
            if (!res.success || !res.top || res.top.length === 0) {
                box.innerHTML = '<h2 style="margin-top:0;">🏆 Топ питомцев</h2><p class="muted">Пока пусто.</p>';
                return;
            }
            const medals = ['🥇','🥈','🥉'];
            const rows = res.top.map((t, i) => `
                <div class="quest-row" style="padding:0.7rem 1rem;">
                    <div style="display:flex; align-items:center; gap:0.8rem; flex:1; min-width:0;">
                        <div style="width:28px; text-align:center; font-weight:800; color:#94A3B8;">${medals[i] || (i+1)}</div>
                        <div style="font-size:1.6rem;">${t.emoji}</div>
                        <div style="min-width:0;">
                            <div style="font-weight:700; color:#F8FAFC; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${esc(t.pet_name)}</div>
                            <div class="muted">${esc(t.owner_name)}</div>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div class="pet-level-badge" style="font-size:0.75rem;">Ур. ${t.level}</div>
                        <div class="muted" style="margin-top:4px;">${t.xp} XP</div>
                    </div>
                </div>`).join('');
            box.innerHTML = '<h2 style="margin-top:0;">🏆 Топ питомцев</h2>' + rows;
        }

        function renderCreate(taken) {
            taken = taken || [];
            // если выбранный по умолчанию занят — переключаемся на первый свободный
            if (taken.includes(selectedType)) {
                const free = Object.keys(PET_TYPES).find(k => !taken.includes(k));
                if (free) selectedType = free;
            }
            let grid = Object.entries(PET_TYPES).map(([k,e]) => {
                const isTaken = taken.includes(k);
                const cls = `type-btn ${k===selectedType?'selected':''} ${isTaken?'taken':''}`;
                const click = isTaken ? '' : `onclick="pickType('${k}')"`;
                const title = isTaken ? 'title="Уже занят другим сотрудником"' : '';
                return `<div class="${cls}" data-type="${k}" ${click} ${title}>${e}${isTaken?'<div class="taken-label">занят</div>':''}</div>`;
            }).join('');
            return `
                <div class="pet-card">
                    <h2 style="margin-top:0;">Заведи себе питомца</h2>
                    <p class="muted">Выбери тип, дай имя — и он будет расти вместе с твоей работой. Серые питомцы уже заняты другими сотрудниками.</p>
                    <span class="pet-label">Тип питомца</span>
                    <div class="type-grid">${grid}</div>
                    <span class="pet-label">Имя питомца</span>
                    <input id="petNameInput" class="pet-input" maxlength="50" placeholder="Например: Барсик">
                    <button class="pet-btn" onclick="createPet()">Завести питомца</button>
                </div>`;
        }
        function pickType(k){ selectedType=k; document.querySelectorAll('.type-btn').forEach(b=>b.classList.toggle('selected', b.dataset.type===k)); }
        function bindCreate(){ const i=document.getElementById('petNameInput'); if(i) i.focus(); }

        async function createPet() {
            const name = (document.getElementById('petNameInput').value || '').trim() || 'Питомец';
            const body = new URLSearchParams({ pet_type: selectedType, pet_name: name });
            const res = await fetch('api.php?action=pet_create', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body: body.toString() }).then(r=>r.json());
            if (res.success) { loadPet(); document.dispatchEvent(new Event('pet-updated')); }
            else alert('Ошибка: ' + (res.error||'неизвестно'));
        }

        function renderPet(res) {
            const p = res.pet, lv = res.level;
            const pct = Math.min(100, Math.round(lv.xp_into / lv.xp_for_level * 100));
            return `
                <div class="pet-card">
                    <div class="pet-stage">
                        <div class="pet-emoji-big">${p.emoji}</div>
                        <div class="pet-name">${esc(p.name)}</div>
                        <div class="pet-level-badge">Уровень ${lv.level}</div>
                        <div class="xp-bar-wrap" style="margin-top:0.8rem;">
                            <div class="xp-bar-track"><div class="xp-bar-fill" style="width:${pct}%"></div></div>
                            <div class="muted" style="text-align:center; margin-top:6px;">${lv.xp_into} / ${lv.xp_for_level} XP до уровня ${lv.level+1} · всего ${lv.xp} XP</div>
                        </div>
                    </div>
                    <div style="text-align:center; margin-top:1.2rem;">
                        ${res.can_feed
                            ? `<button class="pet-btn" id="feedBtn" onclick="feedPet()">🍖 Покормить (+30 XP)</button>`
                            : `<button class="pet-btn ghost" disabled style="opacity:0.55; cursor:default;">🍖 Покормлен сегодня</button>`}
                    </div>
                    <div class="muted" style="text-align:center; margin-top:1rem;">
                        +20 XP за проведённую переаттестацию · +15 XP за добавленного саппорта · награды за квесты
                    </div>

                    <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:center; margin-top:1.25rem; border-top:1px solid rgba(255,255,255,0.06); padding-top:1.25rem;">
                        <button class="pet-btn ghost" onclick="renamePet()"><i class="fas fa-pen"></i> Переименовать</button>
                        <button class="pet-btn ghost" id="hideWidgetBtn" onclick="toggleHideWidget()">
                            <i class="fas ${localStorage.getItem('pet_hidden') === '1' ? 'fa-eye' : 'fa-eye-slash'}"></i>
                            ${localStorage.getItem('pet_hidden') === '1' ? 'Показать виджет' : 'Скрыть виджет'}
                        </button>
                        <button class="pet-btn danger" onclick="deletePet()"><i class="fas fa-trash"></i> Удалить питомца</button>
                    </div>
                </div>`;
        }

        async function renamePet() {
            const cur = document.querySelector('.pet-name')?.textContent || '';
            const name = prompt('Новое имя питомца:', cur);
            if (name === null) return;
            const trimmed = name.trim();
            if (!trimmed) { alert('Имя не может быть пустым'); return; }
            const body = new URLSearchParams({ pet_name: trimmed });
            const res = await fetch('api.php?action=pet_rename', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body: body.toString() }).then(r=>r.json());
            if (res.success) loadPet();
            else alert('Ошибка: ' + (res.error||'неизвестно'));
        }

        function toggleHideWidget() {
            const hidden = localStorage.getItem('pet_hidden') === '1';
            if (hidden) localStorage.removeItem('pet_hidden');
            else localStorage.setItem('pet_hidden', '1');
            loadPet(); // обновит надпись на кнопке
        }

        async function deletePet() {
            if (!confirm('Удалить питомца? Уровень, опыт, прогресс квестов и достижения будут сброшены.')) return;
            const res = await fetch('api.php?action=pet_delete', { method:'POST' }).then(r=>r.json());
            if (res.success) loadPet();
            else alert('Ошибка: ' + (res.error||'неизвестно'));
        }

        async function feedPet() {
            const btn = document.getElementById('feedBtn');
            if (btn) { btn.disabled = true; btn.textContent = '...'; }
            const res = await fetch('api.php?action=pet_feed', { method:'POST' }).then(r=>r.json());
            if (res.success) {
                const emoji = document.querySelector('.pet-emoji-big');
                if (emoji) { emoji.style.transition='transform .3s'; emoji.style.transform='scale(1.3)'; setTimeout(()=>emoji.style.transform='', 300); }
                loadPet();
            } else {
                alert(res.error === 'already_fed' ? 'Питомец уже покормлен сегодня 🐾' : ('Ошибка: ' + (res.error||'неизвестно')));
                loadPet();
            }
        }

        function renderQuests(quests) {
            let inner;
            if (!quests || quests.length === 0) {
                inner = '<p class="muted">Активных квестов нет.</p>';
            } else {
                inner = quests.map(q => {
                    const done = parseInt(q.completed) === 1;
                    const prog = parseInt(q.progress)||0, goal = parseInt(q.goal_count)||1;
                    const pct = Math.min(100, Math.round(prog/goal*100));
                    const kindLabel = q.kind==='reattestation'?'Переаттестации':q.kind==='add_support'?'Саппорты':'Задание';
                    return `
                        <div class="quest-row">
                            <div style="flex:1; min-width:220px;">
                                <div style="font-weight:700; color:#F8FAFC;">${esc(q.title)} <span class="badge-kind">${kindLabel}</span></div>
                                ${q.description?`<div class="muted">${esc(q.description)}</div>`:''}
                                ${q.kind!=='custom'?`
                                    <div class="quest-progress-track"><div class="quest-progress-fill" style="width:${pct}%"></div></div>
                                    <div class="muted" style="margin-top:4px;">${prog} / ${goal}</div>`:''}
                            </div>
                            <div style="text-align:right;">
                                <div style="font-weight:800; color:#FBBF24;">+${q.xp_reward} XP</div>
                                ${done?'<div class="quest-done">✓ Выполнено</div>':'<div class="muted">в процессе</div>'}
                            </div>
                        </div>`;
                }).join('');
            }
            return `<div class="pet-card"><h2 style="margin-top:0;">Квесты</h2>${inner}</div>`;
        }

        // ===== АДМИН-ПАНЕЛЬ =====
        async function loadAdmin() {
            const res = await fetch('api.php?action=quest_list_admin&t=' + Date.now()).then(r=>r.json());
            const panel = document.getElementById('adminPanel');
            if (!panel) return;
            if (!res.success) { panel.innerHTML=''; return; }

            const list = (res.quests||[]).map(q => {
                const active = parseInt(q.is_active)===1;
                const kindLabel = q.kind==='reattestation'?'авто: переаттестации':q.kind==='add_support'?'авто: саппорты':'ручной';
                return `
                    <div class="quest-row" style="${active?'':'opacity:0.5;'}">
                        <div style="flex:1; min-width:220px;">
                            <div style="font-weight:700;">${esc(q.title)} <span class="badge-kind">${kindLabel}</span></div>
                            <div class="muted">+${q.xp_reward} XP · цель ${q.goal_count} · роль: ${q.target_role}</div>
                            ${q.kind==='custom'?`
                                <div style="display:flex; gap:6px; margin-top:8px;">
                                    <input class="pet-input" style="margin:0; padding:0.5rem; width:auto; flex:1;" placeholder="Discord ID получателя" id="award-${q.id}">
                                    <button class="pet-btn" style="padding:0.5rem 0.9rem;" onclick="awardQuest(${q.id})">Выдать XP</button>
                                </div>`:''}
                        </div>
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <button class="pet-btn ghost" style="padding:0.4rem 0.8rem;" onclick="toggleQuest(${q.id})">${active?'Выключить':'Включить'}</button>
                            <button class="pet-btn danger" style="padding:0.4rem 0.8rem;" onclick="deleteQuest(${q.id})">Удалить</button>
                        </div>
                    </div>`;
            }).join('') || '<p class="muted">Квестов пока нет.</p>';

            panel.innerHTML = `
                <div class="pet-card" style="border-color: rgba(167,139,250,0.3);">
                    <h2 style="margin-top:0;"><i class="fas fa-shield-halved"></i> Управление квестами (админ)</h2>
                    <div class="pet-grid-2">
                        <div>
                            <span class="pet-label">Название</span>
                            <input id="q_title" class="pet-input" placeholder="Название квеста">
                            <span class="pet-label">Описание</span>
                            <input id="q_desc" class="pet-input" placeholder="Описание (необязательно)">
                        </div>
                        <div>
                            <span class="pet-label">Тип</span>
                            <select id="q_kind" class="pet-input" onchange="document.getElementById('q_goalWrap').style.display = this.value==='custom'?'none':'block'">
                                <option value="reattestation">Авто: переаттестации (куратор)</option>
                                <option value="add_support">Авто: добавить саппортов (мастер)</option>
                                <option value="custom">Ручной (выдаёт админ)</option>
                            </select>
                            <span class="pet-label">Для роли</span>
                            <select id="q_role" class="pet-input">
                                <option value="all">Все</option>
                                <option value="master">Мастер</option>
                                <option value="curator">Куратор</option>
                                <option value="chief">Гл. куратор</option>
                                <option value="admin">Админ</option>
                            </select>
                            <div class="pet-grid-2">
                                <div id="q_goalWrap"><span class="pet-label">Цель (кол-во)</span><input id="q_goal" type="number" min="1" value="3" class="pet-input"></div>
                                <div><span class="pet-label">Награда XP</span><input id="q_xp" type="number" min="1" value="50" class="pet-input"></div>
                            </div>
                        </div>
                    </div>
                    <button class="pet-btn" onclick="createQuest()">Создать квест</button>
                    <hr style="border-color:rgba(255,255,255,0.08); margin:1.5rem 0;">
                    <h3 style="margin:0 0 0.8rem;">Все квесты</h3>
                    ${list}
                </div>`;
        }

        async function createQuest() {
            const body = new URLSearchParams({
                title: document.getElementById('q_title').value,
                description: document.getElementById('q_desc').value,
                kind: document.getElementById('q_kind').value,
                target_role: document.getElementById('q_role').value,
                goal_count: document.getElementById('q_goal').value || 1,
                xp_reward: document.getElementById('q_xp').value || 50,
            });
            const res = await fetch('api.php?action=quest_create', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body: body.toString() }).then(r=>r.json());
            if (res.success) { loadAdmin(); } else alert('Ошибка: ' + (res.error||'неизвестно'));
        }
        async function toggleQuest(id){ await postQuest('quest_toggle', {id}); loadAdmin(); }
        async function deleteQuest(id){ if(!confirm('Удалить квест?'))return; await postQuest('quest_delete', {id}); loadAdmin(); }
        async function awardQuest(id){
            const did = (document.getElementById('award-'+id).value||'').trim();
            if(!did){ alert('Введите Discord ID'); return; }
            const res = await postQuest('quest_award', { quest_id:id, discord_id:did });
            if(res.success) alert('XP начислен!'); else alert('Ошибка: '+(res.error||'неизвестно'));
        }
        async function postQuest(action, data){
            return fetch('api.php?action='+action, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body: new URLSearchParams(data).toString() }).then(r=>r.json());
        }

        document.addEventListener('DOMContentLoaded', loadPet);
    </script>
</body>
</html>
