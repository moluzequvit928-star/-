const { Client, GatewayIntentBits, REST, Routes } = require('discord.js');
const fs = require('fs');
const crypto = require('crypto');
const path = require('path');

// Берем токен из переменных хостинга, чтобы Дискорд его не банил на GitHub
const TOKEN = process.env.DISCORD_TOKEN || 'СЮДА_ВСТАВИТЬ_ДЛЯ_ЛОКАЛЬНОГО_ТЕСТА';
const CLIENT_ID = process.env.DISCORD_CLIENT_ID || '1489782412924813445';


const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMessages
    ]
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
    let panelRole = 'master';
    const guild = interaction.guild;
    if (!guild) return panelRole;

    const interactionRoles = interaction.member?.roles;
    if (!interactionRoles) return panelRole;

    const roleIds = Array.isArray(interactionRoles) ? interactionRoles : (interactionRoles.cache ? Array.from(interactionRoles.cache.keys()) : []);
    if (roleIds.length === 0) return panelRole;

    await guild.roles.fetch();
    const roleNames = roleIds.map((id) => guild.roles.cache.get(id)?.name).filter(Boolean).map((name) => normalizeRoleName(name));

    if (hasAnyRole(roleNames, ['админ', 'администратор', 'admin', 'administrator'])) return 'admin';
    if (hasAnyRole(roleNames, ['главный куратор', 'гл. куратор', 'куратор', 'curator', 'chief curator'])) return 'curator';
    if (hasAnyRole(roleNames, ['мастер', 'master'])) return 'master';
    return panelRole;
}

const commands = [
    {
        name: 'get_access',
        description: 'Получить логин и пароль для панели Futurama',
    }
];

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
                    content: `🔄 Доступ обновлен!\n**Логин:** \`${existingLogin}\`\n**Новый пароль:** \`${newPassword}\`\n**Роль в панели:** \`${panelRole}\`\nСсылка на панель: <https://cooperative-joy-production-fa8a.up.railway.app>`
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
                content: `✅ Доступ создан!\n\n**Логин:** \`${login}\`\n**Пароль:** \`${password}\`\n**Роль:** \`${panelRole}\`\nСсылка: <https://cooperative-joy-production-fa8a.up.railway.app>`
            });
        } catch (error) {
            console.error('Ошибка /get_access:', error);
            await interaction.editReply({ content: 'Произошла ошибка.' }).catch(() => { });
        }
    }
});

startBot();
