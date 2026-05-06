<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'master';
$total_users = 0;
try {
    if (isset($pdo)) {
        $stmtCount = $pdo->query("SELECT COUNT(*) FROM users");
        $total_users = $stmtCount->fetchColumn();
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
                <?php if (($_SESSION['role'] ?? 'master') === 'admin'): ?>
                <li class="nav-item">
                    <a href="fortune_wheel.php" class="nav-link <?= $currentPage === 'fortune_wheel.php' ? 'active' : '' ?>">
                        <i class="fas fa-dharmachakra"></i> <span>Колесо фортуны</span>
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
                    <a href="https://docs.google.com/document/d/1tef_iQ0GuuIVgQRI15Ql8H74BFPjEcI9Cg3qZCQrtL8/edit?tab=t.0" target="_blank" class="nav-link">
                        <i class="fas fa-user-plus"></i> <span>Собеседование</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="nav-section">
            <div class="nav-label">СИСТЕМА</div>
            <ul class="nav-menu">
                <?php if (in_array($_SESSION['role'] ?? 'master', ['admin', 'chief', 'curator'])): ?>
                <li class="nav-item">
                    <a href="users_manage.php" onclick="window.location.href='users_manage.php'; return true;" class="nav-link <?= $currentPage === 'users_manage.php' ? 'active' : '' ?>">
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

            // �������� ��� ����� ��� ��������
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 1024 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== menuBtn) {
                    sidebar.classList.remove('open');
                    menuBtn.querySelector('i').classList.replace('fa-times', 'fa-bars');
                }
            });
        }
    });
</script>
