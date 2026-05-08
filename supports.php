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
    <title>FUTURAMA STAFF | Список саппортов</title>
    <link rel="icon" type="image/png" href="favicon_futurama_staff_1776084855108.png">
    <link rel="stylesheet" href="index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;700&family=Montserrat:wght@400;600;700&family=Roboto+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .search-container {
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.03);
            padding: 1rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .search-input {
            flex: 1;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            color: #fff;
            font-family: inherit;
            outline: none;
            transition: 0.3s;
        }

        .search-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .supports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .support-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            flex-direction: column;
            gap: 1rem;
            transition: 0.3s;
            position: relative;
            overflow: hidden;
        }

        .support-card:hover {
            transform: translateY(-5px);
            border-color: rgba(99, 102, 241, 0.3);
            background: rgba(99, 102, 241, 0.02);
        }

        .support-header {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .support-avatar {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .support-info h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 2px;
            color: #fff;
        }

        .support-info p {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-family: 'Roboto Mono', monospace;
        }

        .support-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: var(--text-secondary);
            background: rgba(0, 0, 0, 0.1);
            padding: 0.8rem;
            border-radius: 12px;
        }

        .btn-warning {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 0.6rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
        }

        .btn-warning:hover {
            background: #ef4444;
            color: #fff;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--bg-card);
            width: 100%;
            max-width: 500px;
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: modalIn 0.3s ease-out;
        }

        @keyframes modalIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-header {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .close-modal {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .form-control {
            width: 100%;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 0.8rem 1rem;
            color: #fff;
            font-family: inherit;
            resize: none;
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: var(--accent);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 1rem;
        }

        .btn-submit:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php require_once 'sidebar_v2.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1>Список саппортов <span id="totalCount" style="font-size: 1rem; background: rgba(99, 102, 241, 0.1); color: var(--accent); padding: 4px 12px; border-radius: 8px; margin-left: 10px; vertical-align: middle;">...</span></h1>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">Просмотр всех саппортов системы.</p>
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

            <section class="content">
                <div class="search-container">
                    <i class="fas fa-search" style="margin-top: 12px; color: var(--text-secondary);"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Поиск по ID или никнейму...">
                </div>

                <div id="supportsContainer" class="supports-grid">
                    <!-- Cards will be here -->
                    <div style="grid-column: 1/-1; text-align: center; padding: 3rem;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--accent);"></i>
                        <p style="margin-top: 1rem; color: var(--text-secondary);">Загрузка саппортов...</p>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal for Warning -->
    <div id="warningModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Выдать устник</h2>
                <button class="close-modal" onclick="closeWarningModal()">&times;</button>
            </div>
            <form id="warningForm">
                <input type="hidden" id="targetId" name="support_id">
                <input type="hidden" id="targetNick" name="support_nick">
                
                <div class="form-group">
                    <label>Никнейм саппорта</label>
                    <input type="text" id="displayNick" class="form-control" readonly>
                </div>

                <div class="form-group">
                    <label>Причина</label>
                    <textarea name="reason" class="form-control" rows="3" required style="resize: none;"></textarea>
                </div>

                <div class="form-group">
                    <label>Срок (напр. 1d, 12h, 30m)</label>
                    <input type="text" name="duration" class="form-control" placeholder="1d" value="1d" required>
                </div>

                <button type="submit" class="btn-submit">Подтвердить выдачу</button>
            </form>
        </div>
    </div>

    <!-- Modal for Managing Warnings -->
    <div id="manageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Активные устники</h2>
                <button class="close-modal" onclick="closeManageModal()">&times;</button>
            </div>
            <div id="activeWarningsList" style="display: flex; flex-direction: column; gap: 12px; max-height: 400px; overflow-y: auto; padding-right: 5px;">
                <!-- Warnings will be listed here -->
            </div>
        </div>
    </div>

    <script>
        let allSupports = [];

        async function loadSupports() {
            try {
                const response = await fetch('api.php?action=get_all_supports');
                const result = await response.json();
                if (result.success) {
                    allSupports = result.data;
                    document.getElementById('totalCount').textContent = allSupports.length;
                    renderSupports(allSupports);
                }
            } catch (err) {
                console.error('Error loading supports:', err);
            }
        }

        function renderSupports(supports) {
            const container = document.getElementById('supportsContainer');
            if (supports.length === 0) {
                container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-secondary);">Никого не найдено</div>';
                return;
            }

            container.innerHTML = supports.map(s => `
                <div class="support-card">
                    <div class="support-header">
                        <img src="avatar.php?id=${s.discord_id}&seed=${encodeURIComponent(s.nick)}" class="support-avatar">
                        <div class="support-info">
                            <h3>${s.nick}</h3>
                            <p>${s.discord_id || 'ID не указан'}</p>
                        </div>
                        ${s.active_warnings > 0 ? `
                            <div onclick="openManageModal('${s.discord_id}')" style="cursor:pointer; margin-left: auto; background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; border: 1px solid rgba(239, 68, 68, 0.2);">
                                <i class="fas fa-exclamation-circle"></i> ${s.active_warnings}
                            </div>
                        ` : ''}
                    </div>
                    <div class="support-details">
                        <span><i class="far fa-calendar-alt" style="margin-right: 5px;"></i> Дата постановки:</span>
                        <span style="color: #fff; font-weight: 600;">${s.date}</span>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn-warning" style="flex: 1;" onclick="openWarningModal('${s.discord_id}', '${s.nick}')">
                            <i class="fas fa-plus-circle"></i> Выдать
                        </button>
                        ${s.active_warnings > 0 ? `
                            <button class="btn-warning" style="flex: 1; background: rgba(16, 185, 129, 0.1); color: #10b981; border-color: rgba(16, 185, 129, 0.2);" onclick="openManageModal('${s.discord_id}')">
                                <i class="fas fa-check-circle"></i> Снять
                            </button>
                        ` : ''}
                    </div>
                </div>
            `).join('');
        }

        document.getElementById('searchInput').addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            const filtered = allSupports.filter(s => 
                s.nick.toLowerCase().includes(query) || 
                (s.discord_id && s.discord_id.includes(query))
            );
            renderSupports(filtered);
        });

        // Warning Modal Logic
        const warningModal = document.getElementById('warningModal');
        function openWarningModal(id, nick) {
            document.getElementById('targetId').value = id;
            document.getElementById('targetNick').value = nick;
            document.getElementById('displayNick').value = nick;
            warningModal.classList.add('active');
        }
        function closeWarningModal() { warningModal.classList.remove('active'); }

        // Manage Modal Logic
        const manageModal = document.getElementById('manageModal');
        async function openManageModal(supportId) {
            const list = document.getElementById('activeWarningsList');
            list.innerHTML = '<div style="text-align:center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>';
            manageModal.classList.add('active');

            try {
                const res = await fetch(`api.php?action=get_warnings&support_id=${supportId}`);
                const result = await res.json();
                if (result.success) {
                    const activeOnes = result.data.filter(w => w.is_active);
                    if (activeOnes.length === 0) {
                        list.innerHTML = '<div style="text-align:center; padding: 2rem; color: var(--text-secondary);">Активных устников нет.</div>';
                    } else {
                        list.innerHTML = activeOnes.map(w => `
                            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 1rem; display: flex; justify-content: space-between; align-items: center; gap: 15px;">
                                <div style="flex:1;">
                                    <div style="color:#fff; font-size: 0.9rem; margin-bottom: 4px;">${w.reason}</div>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary);">Выдан: ${w.admin_nickname} | До: ${w.expires_at ? new Date(w.expires_at).toLocaleString('ru-RU') : 'Бессрочно'}</div>
                                </div>
                                <button onclick="removeWarning(${w.id})" style="background: #ef4444; color:#fff; border:none; border-radius: 8px; padding: 6px 12px; font-size: 0.8rem; font-weight: 700; cursor:pointer;">Снять</button>
                            </div>
                        `).join('');
                    }
                }
            } catch (err) {
                list.innerHTML = '<div style="color:#ef4444; text-align:center;">Ошибка загрузки</div>';
            }
        }
        function closeManageModal() { manageModal.classList.remove('active'); }

        async function removeWarning(id) {
            if (!confirm('Вы уверены, что хотите снять этот устник?')) return;
            const params = new URLSearchParams();
            params.append('action', 'remove_warning');
            params.append('id', id);

            try {
                const res = await fetch('api.php', { method: 'POST', body: params });
                const result = await res.json();
                if (result.success) {
                    alert('Устник успешно снят!');
                    closeManageModal();
                    loadSupports(); // Refresh list
                } else {
                    alert('Ошибка: ' + result.error);
                }
            } catch (e) {
                alert('Ошибка сервера');
            }
        }

        window.onclick = (e) => { 
            if (e.target == warningModal) closeWarningModal(); 
            if (e.target == manageModal) closeManageModal(); 
        }

        document.getElementById('warningForm').onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'give_warning');

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    alert('Устник успешно выдан!');
                    closeWarningModal();
                    e.target.reset();
                    loadSupports();
                } else {
                    alert('Ошибка: ' + result.error);
                }
            } catch (err) {
                alert('Произошла ошибка при отправке запроса');
            }
        };

        document.addEventListener('DOMContentLoaded', loadSupports);
    </script>
</body>

</html>
