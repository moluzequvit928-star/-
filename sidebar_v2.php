<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'master';
$total_users = 0;
try {
    if (isset($pdo) && $pdo) {
        $stmtCount = $pdo->query("SELECT COUNT(*) FROM users");
        if ($stmtCount) {
            $total_users = $stmtCount->fetchColumn();
        }
    }
} catch (Exception $e) {}
?>
<button class="mobile-nav-toggle" id="mobileMenuBtn">
    <i class="fas fa-bars"></i>
</button>

<aside class="sidebar" id="mainSidebar">
    <div class="sidebar-header" style="padding-top: 1rem; margin-bottom: 2rem;">
        <div class="logo" style="font-size: 1.6rem; display: flex; align-items: center; margin-bottom: 1.5rem;">
            <i class="fas fa-rocket" style="color: #A78BFA; margin-right: 12px; font-size: 1.4rem;"></i>
            <span>Futurama <span style="color: #A78BFA; font-weight: 800;">Staff</span></span>
        </div>

        <?php 
        $my_discord = $_SESSION['discord_id'] ?? '';
        $my_name = $_SESSION['username'] ?? 'Гость';
        $my_avatar = getAvatarUrl($my_discord, $my_name);
        ?>
        <div class="sidebar-user-card" style="display: flex; align-items: center; gap: 12px; padding: 1rem; background: rgba(255,255,255,0.03); border-radius: 16px; border: 1px solid rgba(255,255,255,0.05); margin-top: 1rem;">
            <img src="<?= $my_avatar ?>" style="width: 40px; height: 40px; border-radius: 10px; object-fit: cover;" alt="Me">
            <div style="overflow: hidden;">
                <div style="font-weight: 700; color: #fff; font-size: 0.9rem; white-space: nowrap; text-overflow: ellipsis;"><?= htmlspecialchars($my_name) ?></div>
                <div style="font-size: 0.7rem; color: #A78BFA; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;"><?= $role_display ?? '' ?></div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-label">ОСНОВНОЕ</div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="profile.php" class="nav-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-circle"></i> <span>Профиль</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">
                        <i class="fas fa-th-large"></i> <span>Главная</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="supports.php" class="nav-link <?= $currentPage === 'supports.php' ? 'active' : '' ?>">
                        <i class="fas fa-users"></i> <span>Список саппортов</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="warnings_history.php" class="nav-link <?= $currentPage === 'warnings_history.php' ? 'active' : '' ?>">
                        <i class="fas fa-history"></i> <span>История устников</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="pet.php" class="nav-link <?= $currentPage === 'pet.php' ? 'active' : '' ?>">
                        <i class="fas fa-paw"></i> <span>Питомец</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="achievements.php" class="nav-link <?= $currentPage === 'achievements.php' ? 'active' : '' ?>">
                        <i class="fas fa-medal"></i> <span>Достижения</span>
                    </a>
                </li>
            </ul>
        </div>

        <?php if (in_array($_SESSION['role'] ?? 'master', ['admin', 'chief', 'curator'])): ?>
        <div class="nav-section">
            <div class="nav-label">УПРАВЛЕНИЕ</div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="reattestation.php" class="nav-link <?= $currentPage === 'reattestation.php' ? 'active' : '' ?>">
                        <i class="fas fa-file-signature"></i> <span>Переаттестация</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reattestation_archive.php" class="nav-link <?= $currentPage === 'reattestation_archive.php' ? 'active' : '' ?>">
                        <i class="fas fa-archive"></i> <span>Архив</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="staff_history.php" class="nav-link <?= $currentPage === 'staff_history.php' ? 'active' : '' ?>">
                        <i class="fas fa-door-open"></i> <span>История стафа</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="double_staff.php" class="nav-link <?= $currentPage === 'double_staff.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-secret"></i> <span>Дабл-стафф</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="lobby_check.php" class="nav-link <?= $currentPage === 'lobby_check.php' ? 'active' : '' ?>">
                        <i class="fas fa-headset"></i> <span>Чек проходных</span>
                    </a>
                </li>
                <?php if ((($_SESSION['username'] ?? '') === 'nevermore8465') || (($_SESSION['role'] ?? '') === 'admin')): ?>
                <li class="nav-item">
                    <a href="voice_command.php" class="nav-link <?= $currentPage === 'voice_command.php' ? 'active' : '' ?>">
                        <i class="fas fa-microphone"></i> <span>/voice массово</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="voice_stats.php" class="nav-link <?= $currentPage === 'voice_stats.php' ? 'active' : '' ?>">
                        <i class="fas fa-clock"></i> <span>Часы саппортов</span>
                    </a>
                </li>
<li class="nav-item">
                    <a href="points_calculator.php" class="nav-link <?= $currentPage === 'points_calculator.php' ? 'active' : '' ?>">
                        <i class="fas fa-calculator"></i> <span>Подсчет баллов</span>
                    </a>
                </li>
                <?php if (in_array($_SESSION['role'] ?? 'master', ['admin', 'chief', 'curator'])): ?>
                <li class="nav-item">
                    <a href="fortune_wheel.php" class="nav-link <?= $currentPage === 'fortune_wheel.php' ? 'active' : '' ?>">
                        <i class="fas fa-dharmachakra"></i> <span>Колесо фортуны</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (in_array($_SESSION['role'] ?? 'master', ['admin', 'chief'])): ?>
                <li class="nav-item">
                    <a href="fortune_wheel_settings.php" class="nav-link <?= $currentPage === 'fortune_wheel_settings.php' ? 'active' : '' ?>">
                        <i class="fas fa-sliders-h"></i> <span>Настройка колеса</span>
                    </a>
                </li>
                <?php endif; ?>

            </ul>
        </div>
        <?php endif; ?>

        <div class="nav-section">
            <div class="nav-label">РЕСУРСЫ</div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="https://docs.google.com/spreadsheets/d/1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754/edit" target="_blank" class="nav-link">
                        <i class="fas fa-table"></i> <span>Таблица Google</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="sync.php" class="nav-link <?= $currentPage === 'sync.php' ? 'active' : '' ?>">
                        <i class="fas fa-sync-alt"></i> <span>Сверка таблиц</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="interview.php" class="nav-link <?= $currentPage === 'interview.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-check"></i> <span>Собесник саппортов</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="nav-section">
            <div class="nav-label">СИСТЕМА</div>
            <ul class="nav-menu">
                <?php if (($_SESSION['role'] ?? 'master') === 'admin'): ?>
                <li class="nav-item">
                    <a href="users_manage.php" class="nav-link <?= $currentPage === 'users_manage.php' ? 'active' : '' ?>">
                        <i class="fas fa-users-cog"></i> <span>Пользователи</span>
                        <?php if ($total_users > 0): ?>
                            <span class="badge"><?= $total_users ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (in_array($_SESSION['role'] ?? 'master', ['admin', 'chief', 'curator', 'master'])): ?>
                <li class="nav-item">
                    <a href="add_support.php" class="nav-link <?= $currentPage === 'add_support.php' ? 'active' : '' ?>">
                        <i class="fas fa-plus-circle"></i> <span>Добавить саппорта</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
                        <i class="fas fa-cog"></i> <span>Настройки</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const menuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('mainSidebar');
        
        if (menuBtn && sidebar) {
            menuBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('open');
                const icon = menuBtn.querySelector('i');
                if (sidebar.classList.contains('open')) {
                    icon.classList.replace('fa-bars', 'fa-times');
                } else {
                    icon.classList.replace('fa-times', 'fa-bars');
                }
            });

            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 1024 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== menuBtn) {
                    sidebar.classList.remove('open');
                    menuBtn.querySelector('i').classList.replace('fa-times', 'fa-bars');
                }
            });
        }
    });
</script>

<?php if ($currentPage !== 'pet.php'): ?>
<!-- 🐾 Плавающий виджет питомца -->
<style>
    #petWidget { position: fixed; bottom: 18px; right: 18px; z-index: 9000; cursor: pointer; text-decoration: none; display: none; animation: pwWander 10s ease-in-out infinite; }
    #petWidget .pw-emoji { font-size: 2.8rem; line-height: 1; filter: drop-shadow(0 6px 10px rgba(0,0,0,0.45)); animation: pwIdle 2.2s ease-in-out infinite; display: block; }
    @keyframes pwIdle { 0%,100% { transform: translateY(0) rotate(-3deg); } 50% { transform: translateY(-10px) rotate(3deg); } }
    @keyframes pwWander { 0%,100% { transform: translateX(0); } 50% { transform: translateX(-70px); } }
    #petWidget .pw-card { position: absolute; bottom: 100%; right: 0; margin-bottom: 8px; background: rgba(15,23,42,0.96); border: 1px solid rgba(167,139,250,0.35); border-radius: 12px; padding: 0.6rem 0.8rem; min-width: 150px; opacity: 0; transform: translateY(6px); pointer-events: none; transition: 0.2s; box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
    #petWidget:hover .pw-card { opacity: 1; transform: translateY(0); }
    #petWidget .pw-name { font-weight: 800; color: #fff; font-size: 0.85rem; }
    #petWidget .pw-lvl { color: #A78BFA; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    #petWidget .pw-bar { height: 6px; background: rgba(255,255,255,0.1); border-radius: 999px; overflow: hidden; margin-top: 5px; }
    #petWidget .pw-fill { height: 100%; background: linear-gradient(90deg,#10B981,#34D399); border-radius: 999px; }
    #petWidget.pw-new .pw-emoji { animation: pwIdle 2.2s ease-in-out infinite; }
    @media (max-width: 640px) { #petWidget { bottom: 76px; } }
</style>
<a href="pet.php" id="petWidget" title="Питомец">
    <div class="pw-card" id="pwCard"></div>
    <span class="pw-emoji" id="pwEmoji">🐾</span>
</a>
<script>
    (function() {
        const w = document.getElementById('petWidget');
        if (!w) return;
        // Если пользователь скрыл виджет — не показываем (кнопка «Скрыть виджет» на странице питомца)
        if (localStorage.getItem('pet_hidden') === '1') return;
        fetch('api.php?action=pet_get&t=' + Date.now())
            .then(r => r.json())
            .then(res => {
                if (!res || !res.success) return;
                const emoji = document.getElementById('pwEmoji');
                const card = document.getElementById('pwCard');
                if (res.has_pet) {
                    const lv = res.level, p = res.pet;
                    const pct = Math.min(100, Math.round(lv.xp_into / lv.xp_for_level * 100));
                    emoji.innerHTML = p.emoji; // может быть эмодзи или <img> (например, герой Доты)
                    card.innerHTML = `<div class="pw-name">${(p.name||'').replace(/[<>&]/g,'')}</div>`
                        + `<div class="pw-lvl">Уровень ${lv.level}</div>`
                        + `<div class="pw-bar"><div class="pw-fill" style="width:${pct}%"></div></div>`;
                } else {
                    w.classList.add('pw-new');
                    emoji.innerHTML = '🐾';
                    card.innerHTML = '<div class="pw-name">Заведи питомца!</div><div class="pw-lvl">нажми сюда</div>';
                }
                w.style.display = 'block';
            })
            .catch(() => {});
    })();
</script>
<?php endif; ?>
