const { Client, GatewayIntentBits, REST, Routes } = require('discord.js');
const fs = require('fs');
const crypto = require('crypto');
const path = require('path');

// Загружаем конфиг бота (Файл bot_config.json не должен попасть на GitHub!)
let config = {};
try {
    config = require('./bot_config.json');
} catch (e) {
    console.warn("⚠️ Файл bot_config.json не найден. Бот будет использовать переменные окружения.");
}

const TOKEN = process.env.DISCORD_TOKEN || config.token;
const CLIENT_ID = process.env.DISCORD_CLIENT_ID || config.client_id;


const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMessages
    ]
});

// Добавляем мини-сервер для выдачи аватарок сайту
const http = require('http');
const AVATAR_PORT = 3000;

http.createServer(async (req, res) => {
    const url = new URL(req.url, `http://${req.headers.host}`);
    if (url.pathname === '/avatar') {
        const id = url.searchParams.get('id');
        if (!id) return res.end('No ID');
        try {
            const user = await client.users.fetch(id);
            const avatarUrl = user.displayAvatarURL({ extension: 'png', size: 128 });
            res.writeHead(302, { 'Location': avatarUrl });
            return res.end();
        } catch (e) {
            res.writeHead(404);
            return res.end('Not found');
        }
    }
    res.end('Bot API');
}).listen(AVATAR_PORT, () => {
    console.log(`🤖 Сервер аватарок запущен на порту ${AVATAR_PORT}`);
});

const usersFile = path.join(__dirname, 'users.json');
const defaultUsers = {
    admin: { password: 'admin123', discord_id: 'system' }
};
let reconnectDelayMs = 5000;
let reconnectTimer = null;
let isConnecting = false;

function generatePassword() {
    return crypto.randomBytes(4).toString('hex');
}

function loadUsers() {
    const dir = path.dirname(usersFile);
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
    if (!fs.existsSync(usersFile)) fs.writeFileSync(usersFile, JSON.stringify(defaultUsers, null, 4), 'utf8');

    try {
        const raw = fs.readFileSync(usersFile, 'utf8');
        return JSON.parse(raw);
    } catch (error) {
        console.error('Ошибка чтения users.json:', error);
        return { ...defaultUsers };
    }
}

function saveUsers(users) {
    fs.writeFileSync(usersFile, JSON.stringify(users, null, 4), 'utf8');
}

function normalizeRoleName(name) {
    return String(name || '').trim().toLowerCase();
}

function hasAnyRole(roleNames, variants) {
    return variants.some((v) => roleNames.includes(v));
}

async function resolvePanelRoleFromDiscord(interaction) {
    const guild = interaction.guild;
    if (!guild) return null;

    const interactionRoles = interaction.member?.roles;
    if (!interactionRoles) return null;

    const roleIds = Array.isArray(interactionRoles) ? interactionRoles : (interactionRoles.cache ? Array.from(interactionRoles.cache.keys()) : []);
    if (roleIds.length === 0) return null;

    await guild.roles.fetch();
    const roleNames = roleIds.map((id) => guild.roles.cache.get(id)?.name).filter(Boolean).map((name) => normalizeRoleName(name));

    if (hasAnyRole(roleNames, ['админ', 'администратор', 'admin', 'administrator'])) return 'admin';
    if (hasAnyRole(roleNames, ['главный куратор', 'гл. куратор', 'куратор', 'curator', 'chief curator'])) return 'curator';
    if (hasAnyRole(roleNames, ['мастер', 'master'])) return 'master';
    
    return null; // Нет подходящих ролей
}

const commands = [
    {
        name: 'get_access',
        description: 'Получить логин и пароль для панели Futurama',
    },
    {
        name: 'profile',
        description: 'Посмотреть свой профиль и статистику',
    }
];

const { EmbedBuilder } = require('discord.js');

const API_BASE_URL = process.env.API_BASE_URL || config.api_base_url || 'http://localhost';
const BOT_API_TOKEN = process.env.BOT_API_TOKEN || config.api_token || 'secret';

async function fetchUserProfile(discordId) {
    try {
        const response = await fetch(`${API_BASE_URL}/api.php?action=bot_profile&discord_id=${discordId}&token=${BOT_API_TOKEN}`);
        if (!response.ok) return null;
        return await response.json();
    } catch (e) {
        console.error('API Fetch error:', e);
        return null;
    }
}

const rest = new REST({ version: '10' }).setToken(TOKEN);

async function startBot() {
    if (isConnecting) return;
    isConnecting = true;
    try {
        await client.login(TOKEN);
    } catch (error) {
        console.error('Ошибка входа:', error);
        scheduleReconnect();
    } finally {
        isConnecting = false;
    }
}

function scheduleReconnect() {
    if (reconnectTimer) return;
    setTimeout(() => {
        reconnectTimer = null;
        startBot();
    }, 5000);
}

client.on('ready', async () => {
    console.log(`✅ Бот запущен как ${client.user.tag}`);
    try {
        console.log('Мгновенная регистрация команд для серверов...');
        const guilds = await client.guilds.fetch();
        for (const [guildId] of guilds) {
            await rest.put(Routes.applicationGuildCommands(client.user.id, guildId), { body: commands });
        }
        console.log('Команды обновлены!');
    } catch (error) {
        console.error('Ошибка регистрации:', error);
    }
});

client.on('interactionCreate', async interaction => {
    console.log(`[DEBUG] Получено взаимодействие: ${interaction.commandName} от ${interaction.user.tag}`);
    if (!interaction.isChatInputCommand()) return;

    if (interaction.commandName === 'get_access') {
        try {
            console.log(`[DEBUG] Обработка /get_access для ${interaction.user.tag}...`);
            await interaction.deferReply({ ephemeral: true });
            console.log(`[DEBUG] deferReply отправлен успешно.`);

            const users = loadUsers();
            console.log(`[DEBUG] users.json загружен.`);
            const panelRole = await resolvePanelRoleFromDiscord(interaction);
            console.log(`[DEBUG] Роль определена: ${panelRole}`);

            if (!panelRole) {
                return await interaction.editReply({
                    content: '❌ **Доступ запрещен!**\nУ вас нет необходимых ролей персонала (Мастер, Куратор или Администратор) на этом сервере.'
                });
            }

            let login = interaction.user.username.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
            if (login.length < 3) login = login + interaction.user.id.substring(0, 4);

            let existingLogin = null;
            for (const [key, val] of Object.entries(users)) {
                if (val.discord_id === interaction.user.id) {
                    existingLogin = key;
                    break;
                }
            }

            if (existingLogin) {
                const newPassword = generatePassword();
                users[existingLogin].password = newPassword;
                users[existingLogin].discord_tag = interaction.user.tag;
                users[existingLogin].role = panelRole;
                saveUsers(users);

                return await interaction.editReply({
                    content: `🔄 Доступ обновлен!\n**Логин:** \`${existingLogin}\`\n**Новый пароль:** \`${newPassword}\`\n**Роль в панели:** \`${panelRole}\`\nСсылка на панель: <${API_BASE_URL}>`
                });
            }

            while (users[login]) login += Math.floor(Math.random() * 10);

            const password = generatePassword();
            users[login] = {
                password: password,
                discord_id: interaction.user.id,
                discord_tag: interaction.user.tag,
                role: panelRole
            };

            saveUsers(users);

            await interaction.editReply({
                content: `✅ Доступ создан!\n\n**Логин:** \`${login}\`\n**Пароль:** \`${password}\`\n**Роль:** \`${panelRole}\`\nСсылка: <${API_BASE_URL}>`
            });
        } catch (error) {
            console.error('Ошибка /get_access:', error);
            await interaction.editReply({ content: 'Произошла ошибка.' }).catch(() => { });
        }
    }

    if (interaction.commandName === 'profile') {
        try {
            await interaction.deferReply();
            const data = await fetchUserProfile(interaction.user.id);

            if (!data || !data.success) {
                return await interaction.editReply({
                    content: '❌ **Профиль не найден!**\nСначала получите доступ к панели командой `/get_access`.'
                });
            }

            const roleColors = {
                'admin': 0xFF5555,
                'curator': 0x55FF55,
                'master': 0x5555FF
            };

            const roleNames = {
                'admin': 'Администратор',
                'curator': 'Куратор',
                'master': 'Мастер'
            };

            const stats = data.stats || { total: 0, approved: 0, rejected: 0, pending: 0 };
            const weeklyCount = data.weekly_approved || 0;
            const quotaStatus = weeklyCount >= 10 ? '✅ Выполнена' : `⏳ В процессе (${weeklyCount}/10)`;

            const embed = new EmbedBuilder()
                .setTitle(`👤 Профиль: ${data.username}`)
                .setColor(roleColors[data.role] || 0xAAAAAA)
                .setThumbnail(interaction.user.displayAvatarURL({ dynamic: true }))
                .addFields(
                    { name: '🔖 Роль', value: `\`${roleNames[data.role] || data.role}\``, inline: true },
                    { name: '🆔 Discord ID', value: `\`${interaction.user.id}\``, inline: true },
                    { name: '\u200B', value: '\u200B', inline: false },
                    { name: '📊 Статистика отчетов', value: [
                        `✅ Одобрено: **${stats.approved || 0}**`,
                        `⏳ Ожидают: **${stats.pending || 0}**`,
                        `❌ Отклонено: **${stats.rejected || 0}**`,
                        `📈 Всего: **${stats.total || 0}**`
                    ].join('\n'), inline: true },
                    { name: '📅 Норма за неделю', value: [
                        `Отработано: **${weeklyCount}**`,
                        `Статус: **${quotaStatus}**`
                    ].join('\n'), inline: true }
                )
                .setFooter({ text: 'Futurama Staff System', iconURL: client.user.displayAvatarURL() })
                .setTimestamp();

            await interaction.editReply({ embeds: [embed] });
        } catch (error) {
            console.error('Ошибка /profile:', error);
            await interaction.editReply({ content: 'Произошла ошибка при загрузке профиля.' }).catch(() => { });
        }
    }
});

startBot();
