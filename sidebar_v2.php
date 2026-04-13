<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Гарантируем наличие переменной для счетчика отчетов
if (!isset($sidebarPendingCount)) {
    try {
        if (isset($pdo)) {
            $stmtPending = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
            $sidebarPendingCount = (int)$stmtPending->fetchColumn();
        } else {
            $sidebarPendingCount = 0;
        }
    } catch (Exception $e) {
        $sidebarPendingCount = 0;
    }
}
//SIDEBAR VERSION 2.2 - ROLE FIX
$role = $_SESSION['role'] ?? 'master';
?>
<!-- SIDEBAR VERSION 2.2 - NO MORE DISAPPEARING -->
<aside class="sidebar glass">
    <div class="logo">
        <h2 style="letter-spacing: 1px;">Панель </h2>
    </div>
    <nav class="menu">
        <a href="index.php" class="menu-item <?= $current_page === 'index.php' || $current_page === '' ? 'active' : '' ?>">Главная</a>
        
        <?php if ($role === 'admin'): ?>
            <a href="admin_stats.php" class="menu-item <?= $current_page === 'admin_stats.php' ? 'active' : '' ?>">Статистика</a>
            <a href="users_manage.php" class="menu-item <?= $current_page === 'users_manage.php' ? 'active' : '' ?>" style="display: flex; justify-content: space-between; align-items: center;">
                <span>Пользователи</span>
                <?php
                try {
                    if (isset($pdo)) {
                        $stmtCount = $pdo->query("SELECT COUNT(*) FROM users");
                        $totalUsers = $stmtCount->fetchColumn();
                        echo '<span style="background: var(--accent); color: white; font-size: 0.75rem; font-weight: 700; padding: 0.1rem 0.45rem; border-radius: 999px; line-height: 1.6; min-width: 20px; text-align: center;">' . $totalUsers . '</span>';
                    }
                } catch (Exception $e) {}
                ?>
            </a>
        <?php endif; ?>

        <?php if ($role === 'admin' || $role === 'curator' || $role === 'chief'): ?>
            <a href="reattestation.php" class="menu-item <?= $current_page === 'reattestation.php' ? 'active' : '' ?>">Переаттестация</a>
            <a href="reattestation_archive.php" class="menu-item <?= $current_page === 'reattestation_archive.php' ? 'active' : '' ?>">Архив переаттестаций</a>
            <a href="check_reports.php" class="menu-item <?= $current_page === 'check_reports.php' ? 'active' : '' ?>"
                style="display: flex; justify-content: space-between; align-items: center;">
                <span>Проверка отчетов</span>
                <?php if ($sidebarPendingCount > 0): ?>
                    <span
                        style="background: #EF4444; color: white; font-size: 0.75rem; font-weight: 700; padding: 0.1rem 0.45rem; border-radius: 999px; line-height: 1.6; min-width: 20px; text-align: center;"><?= $sidebarPendingCount ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if ($role === 'master'): ?>
            <a href="master_info.php" class="menu-item <?= $current_page === 'master_info.php' ? 'active' : '' ?>">Информация</a>
        <?php endif; ?>

        <div class="menu-section-title">Полезные ссылки</div>
        <a href="https://docs.google.com/spreadsheets/d/1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754/edit"
            class="menu-item highlight" target="_blank">Google Таблица ↗</a>
        <a href="https://docs.google.com/document/d/1tef_iQ0GuuIVgQRI15Ql8H74BFPjEcI9Cg3qZCQrtL8/edit"
            class="menu-item highlight" target="_blank">Собес на саппорта ↗</a>

        <div class="menu-section-title">Работа</div>
        <a href="reports.php" class="menu-item <?= $current_page === 'reports.php' ? 'active' : '' ?>">Отчеты по наборам</a>
    </nav>
</aside>
