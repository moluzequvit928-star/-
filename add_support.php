<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';
require_once 'user_header.php';

$current_role = $_SESSION['role'] ?? 'master';
if (!in_array($current_role, ['admin', 'chief', 'curator', 'master'])) {
    die("У вас нет прав для доступа к этой странице.");
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="black">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить саппорта | Futurama Staff</title>
    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;700&display=swap"
        rel="stylesheet">
    <style>
        .add-card {
            max-width: 600px;
            margin: 2rem auto;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 30px;
            padding: 3rem;
            animation: fadeIn 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i {
            position: absolute;
            left: 1rem;
            color: #6366f1;
            font-size: 1.1rem;
            z-index: 2;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 2.8rem;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            font-size: 1rem;
            transition: 0.3s;
        }

        .form-control:focus {
            border-color: #6366f1;
            outline: none;
            background: rgba(15, 23, 42, 0.8);
        }

        /* CUSTOM SELECT STYLES */
        .custom-select {
            position: relative;
            width: 100%;
            user-select: none;
        }

        .select-trigger {
            padding: 0.8rem 1rem 0.8rem 2.8rem;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.3s;
        }

        .select-trigger:hover {
            background: rgba(15, 23, 42, 0.8);
            border-color: rgba(99, 102, 241, 0.5);
        }

        .select-trigger.open {
            border-color: #6366f1;
            border-radius: 12px 12px 0 0;
        }

        .select-options {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #1e293b;
            border: 1px solid #6366f1;
            border-top: none;
            border-radius: 0 0 12px 12px;
            z-index: 100;
            max-height: 250px;
            overflow-y: auto;
            display: none;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.4);
        }

        .select-options.show {
            display: block;
        }

        .option-group {
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.05);
            color: #6366f1;
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .option-item {
            padding: 10px 15px 10px 40px;
            cursor: pointer;
            transition: 0.2s;
            color: #e2e8f0;
        }

        .option-item:hover {
            background: rgba(99, 102, 241, 0.2);
            color: #fff;
        }

        .option-item.selected {
            background: #6366f1;
            color: #fff;
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            margin-top: 1rem;
            background: linear-gradient(135deg, #6366f1 0%, #a78bfa 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: none;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include 'sidebar_v2.php'; ?>
        <main class="main-content">
            <div class="add-card">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.8rem; color: #fff;">Добавить саппорта
                    </h2>
                    <p style="color: #94a3b8;">Данные будут внесены в таблицу смен и переаттестации</p>
                </div>

                <div id="statusMsg" class="alert"></div>

                <form id="addSupportForm">
                    <div class="form-group">
                        <label>Дата вступления</label>
                        <div class="input-wrapper">
                            <i class="fas fa-calendar"></i>
                            <input type="text" name="date" class="form-control" value="<?= date('d.m.Y') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Никнейм</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" name="nickname" class="form-control"
                                placeholder="Например: admin.original" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Discord ID</label>
                        <div class="input-wrapper">
                            <i class="fas fa-fingerprint"></i>
                            <input type="text" name="discord_id" class="form-control"
                                placeholder="Например: 1129175113967882331" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Номер смены</label>
                        <div class="custom-select" id="shiftSelect">
                            <i class="fas fa-clock"
                                style="position: absolute; left: 1rem; top: 0.85rem; color: #6366f1; z-index: 5;"></i>
                            <div class="select-trigger" id="selectTrigger">
                                <span id="selectedLabel">Выберите смену из списка...</span>
                                <i class="fas fa-chevron-down"
                                    style="position: static; font-size: 0.8rem; color: #94a3b8;"></i>
                            </div>
                            <div class="select-options" id="selectOptions">
                                <div class="option-group">Основные смены</div>
                                <div class="option-item" data-value="1">1 смена (00:00-02:00)</div>
                                <div class="option-item" data-value="2">2 смена (02:00-04:00)</div>
                                <div class="option-item" data-value="3">3 смена (04:00-06:00)</div>
                                <div class="option-item" data-value="4">4 смена (06:00-08:00)</div>
                                <div class="option-item" data-value="5">5 смена (08:00-10:00)</div>
                                <div class="option-item" data-value="6">6 смена (10:00-12:00)</div>
                                <div class="option-item" data-value="7">7 смена (12:00-14:00)</div>
                                <div class="option-item" data-value="8">8 смена (14:00-16:00)</div>
                                <div class="option-item" data-value="9">9 смена (16:00-18:00)</div>
                                <div class="option-item" data-value="10">10 смена (18:00-20:00)</div>
                                <div class="option-item" data-value="11">11 смена (20:00-22:00)</div>
                                <div class="option-item" data-value="12">12 смена (22:00-00:00)</div>
                                <div class="option-group">Дополнительно</div>
                                <div class="option-item" data-value="0">0 смена (Замена)</div>
                            </div>
                        </div>
                        <input type="hidden" name="shift" id="shiftInput" required>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fas fa-save"></i>
                        <span>Внести в таблицу</span>
                    </button>
                </form>
            </div>

            <!-- Секция свободных слотов -->
            <div style="margin-top: 5rem; margin-bottom: 5rem;">
                <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 3rem;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="width: 50px; height: 3px; background: linear-gradient(to right, var(--accent), transparent); border-radius: 10px;"></div>
                        <h2 style="font-family: 'Outfit', sans-serif; font-size: 2rem; color: #fff; margin: 0; letter-spacing: -0.5px;">Свободные слоты</h2>
                    </div>
                    <p style="color: var(--text-secondary); margin-left: 65px; font-weight: 500;">Выберите подходящую смену для нового сотрудника</p>
                </div>
                
                <div id="shiftGrid" class="shift-grid">
                    <!-- Будет заполнено через JS -->
                    <div style="color: var(--text-secondary); text-align: center; grid-column: 1/-1; padding: 2rem;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                        Загрузка актуальных данных...
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Custom Select Logic
        const trigger = document.getElementById('selectTrigger');
        const options = document.getElementById('selectOptions');
        const label = document.getElementById('selectedLabel');
        const hiddenInput = document.getElementById('shiftInput');

        trigger.addEventListener('click', () => {
            options.classList.toggle('show');
            trigger.classList.toggle('open');
        });

        document.querySelectorAll('.option-item').forEach(item => {
            item.addEventListener('click', function () {
                const val = this.getAttribute('data-value');
                const txt = this.innerText;

                label.innerText = txt;
                hiddenInput.value = val;

                document.querySelectorAll('.option-item').forEach(i => i.classList.remove('selected'));
                this.classList.add('selected');

                options.classList.remove('show');
                trigger.classList.remove('open');
            });
        });

        document.addEventListener('click', (e) => {
            if (!document.getElementById('shiftSelect').contains(e.target)) {
                options.classList.remove('show');
                trigger.classList.remove('open');
            }
        });

        // Fetch and Render Shift Slots
        async function fetchShiftSlots() {
            const grid = document.getElementById('shiftGrid');
            try {
                const response = await fetch('api.php?action=get_shift_slots');
                const res = await response.json();
                
                if (res.success && res.data) {
                    grid.innerHTML = '';
                    res.data.forEach(shift => {
                        const card = document.createElement('div');
                        card.className = 'shift-card';
                        card.dataset.id = shift.id;
                        card.dataset.label = shift.label + ' (' + shift.time + ')';
                        
                        const isAvailable = shift.free_slots > 0;
                        const slotClass = isAvailable ? 'available' : '';
                        
                        card.innerHTML = `
                            <div class="shift-card-header">
                                <div class="shift-number">${shift.id} смена:</div>
                                <div class="shift-time">${shift.time}</div>
                            </div>
                            <div class="shift-slots ${slotClass}">
                                Кол-во свободных мест: <span>${shift.free_slots}</span>
                            </div>
                        `;
                        
                        card.addEventListener('click', () => {
                            // Select in form
                            hiddenInput.value = shift.id;
                            label.innerText = shift.label + ' (' + shift.time + ')';
                            
                            // Highlight card
                            document.querySelectorAll('.shift-card').forEach(c => c.classList.remove('active'));
                            card.classList.add('active');
                            
                            // Highlight in dropdown
                            document.querySelectorAll('.option-item').forEach(opt => {
                                opt.classList.remove('selected');
                                if (opt.dataset.value == shift.id) opt.classList.add('selected');
                            });

                            // Scroll to form
                            document.querySelector('.add-card').scrollIntoView({ behavior: 'smooth' });
                        });
                        
                        grid.appendChild(card);
                    });
                }
            } catch (err) {
                grid.innerHTML = '<div style="color: #ef4444; grid-column: 1/-1; text-align: center;">Ошибка загрузки данных о сменах</div>';
            }
        }

        // Initial load
        fetchShiftSlots();

        // Form Submit Logic
        document.getElementById('addSupportForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            if (!hiddenInput.value) { alert('Пожалуйста, выберите смену!'); return; }

            const btn = document.getElementById('submitBtn');
            const status = document.getElementById('statusMsg');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Отправка...</span>';

            try {
                const response = await fetch('api.php?action=add_support', {
                    method: 'POST',
                    body: new FormData(this)
                });
                const res = await response.json();
                status.style.display = 'block';
                if (res.success) {
                    status.className = 'alert alert-success';
                    status.innerHTML = 'Успешно внесено в таблицу!';
                    this.reset();
                    label.innerText = 'Выберите смену из списка...';
                    hiddenInput.value = '';
                } else {
                    status.className = 'alert alert-error';
                    status.innerHTML = 'Ошибка: ' + (res.error || 'Неизвестная ошибка сервера');
                }
            } catch (err) {
                status.style.display = 'block';
                status.className = 'alert alert-error';
                status.innerHTML = 'Ошибка сети';
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> <span>Внести в таблицу</span>';
            }
        });
    </script>
</body>

</html>