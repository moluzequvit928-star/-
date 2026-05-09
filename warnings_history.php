<?php
session_start();

// Проверка авторизации
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
    <title>FUTURAMA STAFF | История устников</title>
    <link rel="icon" type="image/png" href="favicon_futurama_staff_1776084855108.png">
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;700&family=Montserrat:wght@400;600;700&family=Roboto+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .warning-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .warning-row {
            background: var(--bg-card);
            border-radius: 16px;
            transition: 0.3s;
        }

        .warning-row td {
            padding: 1.2rem;
            padding-left: 1.2rem;
            padding-right: 1.2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }

        .warning-table th {
            vertical-align: middle;
        }

        .warning-row td:first-child {
            border-left: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px 0 0 16px;
        }

        .warning-row td:last-child {
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 0 16px 16px 0;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-active { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .status-expired { background: rgba(100, 116, 139, 0.1); color: #64748b; }
        .status-justified { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>История устников</h1>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">Список всех выданных предупреждений.</p>
                </div>
                
                <div class="user-profile" style="display: flex; align-items: center; gap: 1rem;">
                    <img src="<?= getAvatarUrl($_SESSION['discord_id'], $_SESSION['username']) ?>" 
                         style="width: 38px; height: 38px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1);" 
                         alt="">
                    <a href="logout.php" class="btn-logout-premium">
                        <i class="fas fa-sign-out-alt"></i> Выйти
                    </a>
                </div>
            </header>

            <div class="page-body">
                <table class="warning-table">
                    <thead>
                        <tr style="color: var(--text-secondary); font-size: 0.85rem; text-align: left;">
                            <th style="padding: 0.5rem 1.2rem;">Саппорт</th>
                            <th style="padding: 0.5rem 1.2rem;">Причина</th>
                            <th style="padding: 0.5rem 1.2rem;">Срок</th>
                            <th style="padding: 0.5rem 1.2rem;">Кем выдан</th>
                            <th style="padding: 0.5rem 1.2rem;">Статус</th>
                            <th style="padding: 0.5rem 1.2rem;">Дата</th>
                            <th style="padding: 0.5rem 1.2rem;">Истекает</th>
                        </tr>
                    </thead>
                    <tbody id="warningsBody">
                        <!-- Rows will be here -->
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        async function loadWarnings() {
            try {
                const response = await fetch('api.php?action=get_warnings');
                const result = await response.json();
                if (result.success) {
                    const container = document.getElementById('warningsBody');
                    container.innerHTML = result.data.map(w => `
                        <tr class="warning-row">
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="avatar.php?id=${w.support_id}&seed=${encodeURIComponent(w.support_nickname)}" style="width: 32px; height: 32px; border-radius: 8px;">
                                    <div>
                                        <div style="font-weight: 600; color: #fff;">${w.support_nickname}</div>
                                        <div style="font-size: 0.7rem; color: var(--text-secondary);">${w.support_id}</div>
                                    </div>
                                </div>
                            </td>
                            <td style="color: #cbd5e1; max-width: 250px;">${w.reason}</td>
                            <td style="color: #fff; font-weight: 500;">${w.duration}</td>
                            <td style="color: var(--text-secondary); font-size: 0.9rem;">${w.admin_nickname}</td>
                            <td>
                                <span class="status-badge ${w.is_active ? 'status-active' : (w.removed_by_nickname ? 'status-justified' : 'status-expired')}">
                                    ${w.is_active ? 'Активен' : (w.removed_by_nickname ? 'Оправдан (' + w.removed_by_nickname + ')' : 'Истек')}
                                </span>
                            </td>
                            <td style="color: var(--text-secondary); font-size: 0.85rem;">${new Date(w.created_at).toLocaleDateString('ru-RU')}</td>
                            <td style="color: var(--text-secondary); font-size: 0.85rem;">${w.expires_at ? new Date(w.expires_at).toLocaleString('ru-RU') : '—'}</td>
                        </tr>
                    `).join('');
                }
            } catch (err) {
                console.error(err);
            }
        }

        document.addEventListener('DOMContentLoaded', loadWarnings);
    </script>
</body>

</html>
