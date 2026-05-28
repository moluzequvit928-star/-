<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'user_header.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки | Панель</title>
    <link rel="icon" type="image/png" href="favicon_futurama_staff_1776084855108.png">
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;700&family=Montserrat:wght@400;600;700&family=Roboto+Mono&family=Roboto:wght@400;500;700&family=Nunito:wght@400;600;700&family=Rubik:wght@400;500;700&family=Manrope:wght@400;600;700&family=Jost:wght@400;500;700&family=Oswald:wght@400;500;700&family=Comfortaa:wght@400;600;700&family=Exo+2:wght@400;600;700&family=Unbounded:wght@400;600;800&family=Caveat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .content { max-width: 1320px; }
        .settings-layout { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 1.5rem; align-items: start; }
        .settings-preview { position: sticky; top: 1.5rem; }
        .preview-frame { overflow: hidden; padding: 0 !important; }
        .preview-cap { text-align: center; margin-top: 12px; color: var(--text-muted); font-size: 0.8rem; }
        @media (max-width: 1150px) {
            .settings-layout { grid-template-columns: 1fr; }
            .settings-preview { display: none; }
        }
        .card.glass { border-radius: 20px; margin-bottom: 1.5rem; }
        .card.glass .card-body { padding-top: 1.25rem; }

        /* Заголовки секций с акцентной полосой */
        .card-header { position: relative; padding-left: 16px; }
        .card-header::before {
            content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%);
            width: 4px; height: 22px; border-radius: 4px;
            background: linear-gradient(180deg, var(--accent), #3b82f6);
        }
        .card-header h3 { font-size: 1.05rem; font-weight: 800; letter-spacing: 0.3px; }

        /* Карточки тем/шрифтов */
        .theme-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
            gap: 1.1rem;
            margin-top: 1rem;
        }
        .theme-card {
            background: rgba(255,255,255,0.025);
            border: 2px solid rgba(255,255,255,0.07);
            border-radius: 16px;
            padding: 1.15rem;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
            text-align: center;
            position: relative;
        }
        .theme-card:hover {
            border-color: var(--accent);
            transform: translateY(-4px);
            box-shadow: 0 14px 26px -12px var(--accent-glow);
        }
        .theme-card.active {
            border-color: var(--accent);
            background: rgba(99, 102, 241, 0.08);
        }
        .theme-card.active::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; top: 9px; right: 9px;
            width: 22px; height: 22px; border-radius: 50%;
            background: var(--accent); color: #fff;
            display: flex; align-items: center; justify-content: center; font-size: 0.68rem;
            box-shadow: 0 0 12px var(--accent-glow);
        }
        .theme-preview {
            height: 92px;
            border-radius: 10px;
            margin-bottom: 0.85rem;
            border: 1px solid rgba(255,255,255,0.08);
        }

        /* Сегментированные переключатели */
        .profile-mini-btn {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            color: var(--text-secondary);
            border-radius: 11px;
            transition: all 0.2s;
        }
        .profile-mini-btn:hover { background: rgba(255,255,255,0.09); color: #fff; }
        .profile-mini-btn.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
            box-shadow: 0 0 15px var(--accent-glow);
        }

        /* Плитки палитр и свотчи */
        .sw { transition: transform 0.15s, box-shadow 0.15s; }
        .sw:hover { transform: translateY(-3px) scale(1.08); box-shadow: 0 6px 14px -4px rgba(0,0,0,0.5); }
        .tp, .wp-thumb { transition: transform 0.18s, box-shadow 0.18s; }
        .tp:hover, .wp-thumb:hover { transform: translateY(-4px); box-shadow: 0 12px 22px -8px rgba(0,0,0,0.55); }

        .form-input:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 3px var(--accent-glow); }
        input[type=color] { transition: transform 0.15s; }
        input[type=color]:hover { transform: scale(1.05); }

        /* Вкладки настроек */
        .settings-tabs {
            display: flex; gap: 6px; flex-wrap: wrap;
            margin-bottom: 1.75rem; padding: 6px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
        }
        .stab {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 20px; border-radius: 11px; border: none;
            background: transparent; color: var(--text-secondary);
            font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: all 0.2s;
        }
        .stab i { font-size: 0.95rem; }
        .stab:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .stab.active { background: var(--accent); color: #fff; box-shadow: 0 6px 16px -3px var(--accent-glow); }
        .settings-panel { animation: panelIn 0.3s ease; }
        @keyframes panelIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 560px) { .stab { flex: 1; justify-content: center; padding: 11px 8px; } .stab span { display: none; } }
    </style>
</head>
<body>
    <button class="burger-btn" id="burgerBtn"><span></span><span></span><span></span></button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Настройки интерфейса</h1>
                    <p>Темы, шрифты и оформление</p>
                </div>
                <div class="header-actions">
                    <a href="logout.php" class="btn-logout-premium"><i class="fas fa-sign-out-alt"></i> Выйти</a>
                </div>
            </header>

            <div class="page-body">
            <section class="content">
                <div class="settings-tabs">
                    <button class="stab active" data-t="theme" onclick="showTab('theme')"><i class="fas fa-palette"></i> <span>Тема</span></button>
                    <button class="stab" data-t="fonts" onclick="showTab('fonts')"><i class="fas fa-font"></i> <span>Шрифт</span></button>
                    <button class="stab" data-t="effects" onclick="showTab('effects')"><i class="fas fa-wand-magic-sparkles"></i> <span>Эффекты</span></button>
                    <button class="stab" data-t="wallpaper" onclick="showTab('wallpaper')"><i class="fas fa-image"></i> <span>Обои</span></button>
                    <button onclick="resetAllSettings()" title="Сбросить все настройки оформления"
                        style="margin-left:auto; display:inline-flex; align-items:center; gap:8px; padding:11px 18px; border-radius:11px; border:1px solid rgba(239,68,68,0.3); background:rgba(239,68,68,0.1); color:#F87171; font-weight:700; font-size:0.9rem; cursor:pointer; transition:all 0.2s;"
                        onmouseover="this.style.background='rgba(239,68,68,0.2)'" onmouseout="this.style.background='rgba(239,68,68,0.1)'">
                        <i class="fas fa-rotate-left"></i> <span>Сбросить всё</span>
                    </button>
                </div>

                <div class="settings-layout">
                <div class="settings-main">

                <div class="card glass settings-panel" id="panel-theme">
                    <div class="card-header">
                        <h3>Выбор темы оформления</h3>
                    </div>
                    <div class="card-body">
                        <div class="theme-grid">
                            <!-- Тема: Стандартная (Dark) -->
                            <div class="theme-card" id="theme-dark" onclick="setTheme('dark')">
                                <div class="theme-preview" style="background: #0B0F19; border: 4px solid #141B2D;"></div>
                                <span style="font-weight: 600;">Темно-синяя</span>
                            </div>

                            <!-- Тема: Глубокий черный (Black) -->
                            <div class="theme-card" id="theme-black" onclick="setTheme('black')">
                                <div class="theme-preview" style="background: #000000; border: 4px solid #111111;"></div>
                                <span style="font-weight: 600;">Глубокая</span>
                            </div>

                            <!-- Тема: Алая (Red) -->
                            <div class="theme-card" id="theme-red" onclick="setTheme('red')">
                                <div class="theme-preview" style="background: #1a0505; border: 4px solid #ff0000;"></div>
                                <span style="font-weight: 600;">Красно-алая</span>
                            </div>

                            <!-- Тема: Светлая (Light) -->
                            <div class="theme-card" id="theme-light" onclick="setTheme('light')">
                                <div class="theme-preview" style="background: #F1F5F9; border: 4px solid #FFFFFF;"></div>
                                <span style="font-weight: 600;">Светлая</span>
                            </div>

                            <!-- Свой цвет -->
                            <div class="theme-card" style="position:relative;" title="Выбрать любой цвет">
                                <div class="theme-preview" id="customColorPreview" style="background: conic-gradient(from 0deg, #ff4d4d, #ffb84d, #fff34d, #4dff88, #4dd2ff, #6a4dff, #ff4dd2, #ff4d4d);"></div>
                                <span style="font-weight: 600;">Свой цвет</span>
                                <input type="color" id="themeColorPicker" value="#8b5cf6"
                                    oninput="setAccent(this.value); document.getElementById('customColorPreview').style.background = this.value;"
                                    style="position:absolute; inset:0; width:100%; height:100%; opacity:0; cursor:pointer;">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card glass settings-panel" id="panel-fonts" style="display:none;">
                    <div class="card-header">
                        <h3>Настройка шрифтов</h3>
                    </div>
                    <div class="card-body">
                        <div class="theme-grid" id="font-grid"></div>
                    </div>
                </div>

                <!-- Персонализация и эффекты -->
                <div class="card glass settings-panel" id="panel-effects" style="display:none;">
                    <div class="card-header">
                        <h3>Эффекты и персонализация</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem;">
                            <!-- Glassmorphism -->
                            <div>
                                <h4 style="margin-bottom: 0.75rem; color: #fff; font-size: 0.95rem; font-weight: 700;">Эффект размытия (Glassmorphism)</h4>
                                <div style="display: flex; gap: 10px;">
                                    <button class="profile-mini-btn" id="glass-on-btn" onclick="setGlassmorphism('on')" style="flex: 1; justify-content: center; height: 42px; font-weight: 700;">Включен</button>
                                    <button class="profile-mini-btn" id="glass-off-btn" onclick="setGlassmorphism('off')" style="flex: 1; justify-content: center; height: 42px; font-weight: 700;">Отключен</button>
                                </div>
                            </div>

                            <!-- Neon Glow -->
                            <div>
                                <h4 style="margin-bottom: 0.75rem; color: #fff; font-size: 0.95rem; font-weight: 700;">Неоновое свечение (Neon Glow)</h4>
                                <div style="display: flex; gap: 10px;">
                                    <button class="profile-mini-btn" id="glow-on-btn" onclick="setNeonGlow('on')" style="flex: 1; justify-content: center; height: 42px; font-weight: 700;">Включено</button>
                                    <button class="profile-mini-btn" id="glow-off-btn" onclick="setNeonGlow('off')" style="flex: 1; justify-content: center; height: 42px; font-weight: 700;">Отключено</button>
                                </div>
                            </div>

                            <!-- Scale -->
                            <div>
                                <h4 style="margin-bottom: 0.75rem; color: #fff; font-size: 0.95rem; font-weight: 700;">Масштаб интерфейса (Scale)</h4>
                                <div style="display: flex; gap: 10px;">
                                    <button class="profile-mini-btn" id="scale-small-btn" onclick="setScale('small')" style="flex: 1; justify-content: center; height: 42px; font-weight: 700;">Мелкий</button>
                                    <button class="profile-mini-btn" id="scale-normal-btn" onclick="setScale('normal')" style="flex: 1; justify-content: center; height: 42px; font-weight: 700;">Обычный</button>
                                    <button class="profile-mini-btn" id="scale-large-btn" onclick="setScale('large')" style="flex: 1; justify-content: center; height: 42px; font-weight: 700;">Крупный</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Обои -->
                <div class="card glass settings-panel" id="panel-wallpaper" style="display:none;">
                    <div class="card-header">
                        <h3>Кастомные обои</h3>
                    </div>
                    <div class="card-body">
                        <h4 style="margin:0 0 0.85rem; color:#fff; font-size:0.9rem; font-weight:700;">Готовые фоны</h4>
                        <div id="wpPresets" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(100px,1fr)); gap:10px; margin-bottom:1.5rem;"></div>
                        <h4 style="margin:0 0 0.85rem; color:#fff; font-size:0.9rem; font-weight:700;">Палитра цветов</h4>
                        <div id="wpColors" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:1.5rem;"></div>
                        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:1rem;">
                            <input type="text" id="wallpaperUrl" class="form-input" placeholder="https://...jpg / png" style="flex:1; min-width:220px; padding:12px; border-radius:10px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); color:#fff;">
                            <button class="profile-mini-btn" onclick="setWallpaperFromUrl()" style="height:46px; font-weight:700;">Применить</button>
                        </div>
                        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                            <label class="profile-mini-btn" style="height:46px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                                <i class="fas fa-upload"></i> Загрузить файл
                                <input type="file" accept="image/*" onchange="uploadWallpaper(this.files[0])" style="display:none;">
                            </label>
                            <button class="profile-mini-btn" onclick="clearWallpaper()" style="height:46px; font-weight:700;">Убрать обои</button>
                            <span style="color:var(--text-muted); font-size:0.85rem;"><i class="fas fa-paste"></i> или вставь картинку через Ctrl + V</span>
                            <span id="wallpaperStatus" style="color:var(--text-muted); font-size:0.85rem;"></span>
                        </div>
                    </div>
                </div>

                </div><!-- /.settings-main -->

                <aside class="settings-preview">
                    <div class="card glass preview-frame">
                        <div style="background: var(--gradient-primary); padding: 14px 16px; color:#fff; font-weight:800; font-size:0.9rem;">
                            <i class="fas fa-eye"></i> Превью интерфейса
                        </div>
                        <div style="display:flex; min-height:210px;">
                            <div style="width:74px; background:rgba(255,255,255,0.04); padding:12px 8px; display:flex; flex-direction:column; gap:9px;">
                                <div style="height:11px;border-radius:4px;background:var(--accent);"></div>
                                <div style="height:8px;border-radius:4px;background:rgba(255,255,255,0.18);"></div>
                                <div style="height:8px;border-radius:4px;background:rgba(255,255,255,0.12);"></div>
                                <div style="height:8px;border-radius:4px;background:rgba(255,255,255,0.12);"></div>
                                <div style="height:8px;border-radius:4px;background:rgba(255,255,255,0.12);"></div>
                            </div>
                            <div style="flex:1; padding:16px;">
                                <div style="font-weight:800; color:var(--text-primary); margin-bottom:10px;">Заголовок</div>
                                <div style="height:8px;border-radius:4px;background:rgba(255,255,255,0.14); margin-bottom:7px;"></div>
                                <div style="height:8px;width:72%;border-radius:4px;background:rgba(255,255,255,0.14); margin-bottom:16px;"></div>
                                <button style="background:var(--accent); color:#fff; border:none; padding:9px 18px; border-radius:9px; font-weight:700; box-shadow:0 6px 16px -3px var(--accent-glow); cursor:default;">Кнопка</button>
                                <div style="margin-top:16px; height:8px; border-radius:999px; background:rgba(255,255,255,0.1); overflow:hidden;">
                                    <div style="width:62%;height:100%;background:var(--accent);"></div>
                                </div>
                                <div style="margin-top:14px; display:flex; gap:6px;">
                                    <span style="font-size:0.7rem; padding:3px 8px; border-radius:6px; background:var(--accent-glow); color:var(--accent); font-weight:700;">тег</span>
                                    <span style="font-size:0.7rem; padding:3px 8px; border-radius:6px; background:rgba(255,255,255,0.08); color:var(--text-secondary);">тег</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="preview-cap">Так будет выглядеть интерфейс с выбранными настройками</p>
                </aside>

                </div><!-- /.settings-layout -->
            </section>
            </div>
        </main>
    </div>

    <script>
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('site_theme', theme);
            updateActiveCard(theme);
        }

        function setFont(font) {
            document.documentElement.style.setProperty('--font-family', font);
            localStorage.setItem('site_font', font);
            updateActiveFont(font);
        }

        function updateActiveCard(theme) {
            document.querySelectorAll('.theme-card').forEach(card => {
                if(card.id.startsWith('theme-')) card.classList.remove('active');
            });
            const activeCard = document.getElementById('theme-' + theme);
            if (activeCard) activeCard.classList.add('active');
        }

        const FONTS = [
            { label: 'Inter (Стандарт)', val: "'Inter', sans-serif" },
            { label: 'Outfit (Модерн)',  val: "'Outfit', sans-serif" },
            { label: 'Montserrat',       val: "'Montserrat', sans-serif" },
            { label: 'Roboto',           val: "'Roboto', sans-serif" },
            { label: 'Nunito',           val: "'Nunito', sans-serif" },
            { label: 'Rubik',            val: "'Rubik', sans-serif" },
            { label: 'Manrope',          val: "'Manrope', sans-serif" },
            { label: 'Jost',             val: "'Jost', sans-serif" },
            { label: 'Oswald',           val: "'Oswald', sans-serif" },
            { label: 'Comfortaa',        val: "'Comfortaa', cursive" },
            { label: 'Exo 2',            val: "'Exo 2', sans-serif" },
            { label: 'Unbounded',        val: "'Unbounded', sans-serif" },
            { label: 'Caveat',           val: "'Caveat', cursive" },
            { label: 'Roboto Mono',      val: "'Roboto Mono', monospace" },
        ];
        function renderFonts() {
            const box = document.getElementById('font-grid');
            if (!box) return;
            box.innerHTML = FONTS.map(f => {
                const attr = f.val.replace(/"/g, '&quot;');
                return `<div class="theme-card font-opt" data-font="${attr}">
                    <div style="font-family:${f.val}; font-size:1.7rem; margin-bottom:0.4rem;">Aa</div>
                    <span>${f.label}</span>
                </div>`;
            }).join('');
            box.querySelectorAll('.font-opt').forEach(el => el.addEventListener('click', () => setFont(el.dataset.font)));
        }
        function updateActiveFont(font) {
            document.querySelectorAll('#font-grid .theme-card').forEach(card => card.classList.toggle('active', card.dataset.font === font));
        }

        function setGlassmorphism(state) {
            document.documentElement.setAttribute('data-glassmorphism', state);
            localStorage.setItem('site_glassmorphism', state);
            updateActiveGlass(state);
        }

        function setNeonGlow(state) {
            document.documentElement.setAttribute('data-neon-glow', state);
            localStorage.setItem('site_neon_glow', state);
            updateActiveGlow(state);
        }

        function setScale(scale) {
            document.documentElement.setAttribute('data-scale', scale);
            localStorage.setItem('site_scale', scale);
            updateActiveScale(scale);
        }

        // === ПАЛИТРА (акцентный цвет) ===
        const ACCENT_PRESETS = ['#8b5cf6','#6366f1','#3b82f6','#06b6d4','#10b981','#22c55e','#f59e0b','#ef4444','#ec4899','#f97316'];
        function applyAccent(color) {
            const r = document.documentElement.style;
            r.setProperty('--accent', color);
            r.setProperty('--accent-glow', color + '73');
            r.setProperty('--gradient-primary', 'linear-gradient(135deg, ' + color + ' 0%, #3b82f6 100%)');
        }
        function setAccent(color) {
            applyAccent(color);
            localStorage.setItem('site_accent', color);
            const p = document.getElementById('accentPicker'); if (p) p.value = color;
            const tp = document.getElementById('themeColorPicker'); if (tp) tp.value = color;
            const cp = document.getElementById('customColorPreview'); if (cp) cp.style.background = color;
            highlightSwatch(color);
        }
        function resetAccent() { localStorage.removeItem('site_accent'); location.reload(); }
        function highlightSwatch(color) {
            document.querySelectorAll('#accentSwatches .sw').forEach(s => s.style.outline = (s.dataset.c.toLowerCase() === String(color).toLowerCase()) ? '3px solid #fff' : 'none');
        }
        function renderSwatches() {
            const box = document.getElementById('accentSwatches');
            if (!box) return;
            box.innerHTML = ACCENT_PRESETS.map(c => `<div class="sw" data-c="${c}" onclick="setAccent('${c}')" title="${c}" style="width:34px;height:34px;border-radius:8px;background:${c};cursor:pointer;border:1px solid rgba(255,255,255,0.15);"></div>`).join('');
        }

        // === ОБОИ ===
        function wallpaperCss(val) {
            const v = String(val).trim();
            // градиент применяем как есть, картинку — через url()
            const img = /^(linear-gradient|radial-gradient|conic-gradient)/i.test(v)
                ? v
                : 'url("' + v.replace(/["\\]/g, '') + '")';
            return 'body{background-image:' + img + '!important;background-size:cover!important;background-attachment:fixed!important;background-position:center!important;}';
        }
        function applyWallpaper(val) {
            let st = document.getElementById('customWallpaperStyle');
            if (!st) { st = document.createElement('style'); st.id = 'customWallpaperStyle'; document.head.appendChild(st); }
            st.textContent = wallpaperCss(val);
        }
        function setWallpaperPreset(val) {
            applyWallpaper(val);
            localStorage.setItem('site_wallpaper', val);
            document.getElementById('wallpaperStatus').textContent = 'Обои применены';
            highlightWpPreset(val);
        }
        function highlightWpPreset(val) {
            document.querySelectorAll('#wpPresets .wp-thumb').forEach(t => t.style.outline = (t.dataset.v === val) ? '3px solid #fff' : 'none');
        }
        const WP_PRESETS = [
            'linear-gradient(135deg,#0f0c29,#302b63,#24243e)',
            'linear-gradient(135deg,#000428,#004e92)',
            'linear-gradient(135deg,#0f2027,#203a43,#2c5364)',
            'linear-gradient(135deg,#232526,#414345)',
            'linear-gradient(135deg,#42275a,#734b6d)',
            'radial-gradient(circle at top,#3a1c71,#d76d77,#ffaf7b)',
            'linear-gradient(135deg,#1a2a6c,#b21f1f,#fdbb2d)',
            'linear-gradient(135deg,#16222a,#3a6073)',
            'linear-gradient(135deg,#0b0f19,#1a1a2e,#16213e)',
            'linear-gradient(135deg,#2c3e50,#4ca1af)'
        ];
        function renderWpPresets() {
            const box = document.getElementById('wpPresets');
            if (!box) return;
            box.innerHTML = WP_PRESETS.map(v =>
                `<div class="wp-thumb" data-v="${v}" onclick="setWallpaperPreset('${v}')" style="height:64px;border-radius:10px;cursor:pointer;background-image:${v};background-size:cover;border:1px solid rgba(255,255,255,0.12);"></div>`
            ).join('');
        }
        // Палитра сплошных цветов для фона
        const WP_COLORS = ['#0f172a','#111827','#1e293b','#1a1a2e','#0b1437','#241b3a','#2d1b1b','#10231c','#23201a','#1b1b1b','#202840','#000000'];
        function renderWpColors() {
            const box = document.getElementById('wpColors');
            if (!box) return;
            box.innerHTML = WP_COLORS.map(c => {
                const val = 'linear-gradient(135deg,' + c + ',' + c + ')';
                return `<div class="wp-thumb" data-v="${val}" onclick="setWallpaperPreset('${val}')" title="${c}" style="width:46px;height:46px;border-radius:10px;cursor:pointer;background:${c};border:1px solid rgba(255,255,255,0.15);"></div>`;
            }).join('');
        }
        function setWallpaperFromUrl() {
            const url = (document.getElementById('wallpaperUrl').value || '').trim();
            if (!url) return;
            applyWallpaper(url);
            localStorage.setItem('site_wallpaper', url);
            document.getElementById('wallpaperStatus').textContent = 'Обои применены';
        }
        function clearWallpaper() {
            localStorage.removeItem('site_wallpaper');
            const st = document.getElementById('customWallpaperStyle'); if (st) st.remove();
            document.getElementById('wallpaperUrl').value = '';
            document.getElementById('wallpaperStatus').textContent = 'Обои убраны';
        }
        // Вставка обоев через Ctrl+V (картинка из буфера обмена)
        document.addEventListener('paste', (e) => {
            const items = (e.clipboardData || window.clipboardData)?.items;
            if (!items) return;
            for (const it of items) {
                if (it.type && it.type.indexOf('image') === 0) {
                    const file = it.getAsFile();
                    if (file) { e.preventDefault(); uploadWallpaper(file); }
                    break;
                }
            }
        });

        async function uploadWallpaper(file) {
            if (!file) return;
            const status = document.getElementById('wallpaperStatus');
            status.textContent = 'Загрузка...';
            const fd = new FormData();
            fd.append('target', 'wallpaper');
            fd.append('media_file', file);
            try {
                const res = await fetch('api.php?action=upload_media', { method: 'POST', body: fd }).then(r => r.json());
                if (res.success && res.url) {
                    applyWallpaper(res.url);
                    localStorage.setItem('site_wallpaper', res.url);
                    status.textContent = 'Обои загружены ✓';
                } else {
                    status.textContent = 'Ошибка загрузки';
                }
            } catch (e) { status.textContent = 'Ошибка: ' + e.message; }
        }

        function updateActiveGlass(state) {
            document.getElementById('glass-on-btn').classList.remove('active');
            document.getElementById('glass-off-btn').classList.remove('active');
            if (state === 'on') {
                document.getElementById('glass-on-btn').classList.add('active');
            } else {
                document.getElementById('glass-off-btn').classList.add('active');
            }
        }

        function updateActiveGlow(state) {
            document.getElementById('glow-on-btn').classList.remove('active');
            document.getElementById('glow-off-btn').classList.remove('active');
            if (state === 'on') {
                document.getElementById('glow-on-btn').classList.add('active');
            } else {
                document.getElementById('glow-off-btn').classList.add('active');
            }
        }

        function updateActiveScale(scale) {
            document.getElementById('scale-small-btn').classList.remove('active');
            document.getElementById('scale-normal-btn').classList.remove('active');
            document.getElementById('scale-large-btn').classList.remove('active');
            if (scale === 'small') {
                document.getElementById('scale-small-btn').classList.add('active');
            } else if (scale === 'large') {
                document.getElementById('scale-large-btn').classList.add('active');
            } else {
                document.getElementById('scale-normal-btn').classList.add('active');
            }
        }

        // === СБРОС ВСЕХ НАСТРОЕК ===
        function resetAllSettings() {
            if (!confirm('Сбросить все настройки оформления по умолчанию?')) return;
            ['site_theme', 'site_font', 'site_accent', 'site_wallpaper', 'site_glassmorphism', 'site_neon_glow', 'site_scale']
                .forEach(k => localStorage.removeItem(k));
            location.reload();
        }

        // === ВКЛАДКИ НАСТРОЕК ===
        function showTab(name) {
            if (!document.getElementById('panel-' + name)) name = 'theme';
            document.querySelectorAll('.settings-panel').forEach(p => p.style.display = 'none');
            const panel = document.getElementById('panel-' + name);
            if (panel) panel.style.display = 'block';
            document.querySelectorAll('.stab').forEach(t => t.classList.toggle('active', t.dataset.t === name));
            localStorage.setItem('settings_tab', name);
        }

        document.addEventListener('DOMContentLoaded', () => {
            showTab(localStorage.getItem('settings_tab') || 'theme');

            const currentTheme = localStorage.getItem('site_theme') || 'dark';
            updateActiveCard(currentTheme);
            
            renderFonts();
            const currentFont = localStorage.getItem('site_font') || "'Inter', sans-serif";
            updateActiveFont(currentFont);

            const currentGlass = localStorage.getItem('site_glassmorphism') || 'on';
            updateActiveGlass(currentGlass);

            const currentGlow = localStorage.getItem('site_neon_glow') || 'on';
            updateActiveGlow(currentGlow);

            const currentScale = localStorage.getItem('site_scale') || 'normal';
            updateActiveScale(currentScale);

            // Палитра
            renderSwatches();
            const savedAccent = localStorage.getItem('site_accent');
            if (savedAccent) {
                const p = document.getElementById('accentPicker'); if (p) p.value = savedAccent;
                const tp = document.getElementById('themeColorPicker'); if (tp) tp.value = savedAccent;
                const cp = document.getElementById('customColorPreview'); if (cp) cp.style.background = savedAccent;
                highlightSwatch(savedAccent);
            }
            // Обои
            renderWpPresets();
            renderWpColors();
            const savedWp = localStorage.getItem('site_wallpaper');
            if (savedWp) {
                const wpInput = document.getElementById('wallpaperUrl');
                const isPreset = /^(linear-gradient|radial-gradient|conic-gradient)/i.test(savedWp);
                if (wpInput && !savedWp.startsWith('uploads/') && !isPreset) wpInput.value = savedWp;
                if (isPreset) highlightWpPreset(savedWp);
                document.getElementById('wallpaperStatus').textContent = 'Обои установлены';
            }
        });

        // Бургер меню
        const burgerBtn = document.getElementById('burgerBtn');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        burgerBtn.addEventListener('click', () => {
            burgerBtn.classList.toggle('open');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });
        overlay.addEventListener('click', () => {
            burgerBtn.classList.remove('open');
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    </script>
</body>
</html>
