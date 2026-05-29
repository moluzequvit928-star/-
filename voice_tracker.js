require('dotenv').config();
const { Client } = require('discord.js-selfbot-v13');
const axios = require('axios');

const client = new Client({ checkUpdate: false });

const GUILD_ID = process.env.GUILD_ID;
const SUPPORT_ROLE_ID = process.env.ROLE_ID; // 993871290161172480
const EXCLUDE_ROLE_ID = '993876339260129311';

// Только эти каналы считаются рабочими
const TARGET_CHANNELS = [
    '1268331705194774643', '1268327713463341168', '1268327800767774720',
    '1268327820736598128', '1268327846494081064', '1268327884045684807',
    '1268328226607075338', '1268328281761906698', '1318228034016514128',
    '1501951333790384189', '1503680035528376571', '1503680189391966238'
];

let API_BASE = process.env.SITE_URL || 'http://127.0.0.1:8000';
// Нормализация: дописываем схему, если её забыли, и убираем хвостовой слеш
if (API_BASE && !/^https?:\/\//i.test(API_BASE)) API_BASE = 'https://' + API_BASE;
API_BASE = API_BASE.replace(/\/+$/, '');
console.log(`🌐 [DS] SITE_URL = ${API_BASE}`);

const API_URL = `${API_BASE}/api.php?action=log_voice`;
const API_TOKEN = process.env.BOT_API_TOKEN || 'futika_bot_secret_2026';
const SYNC_URL = `${API_BASE}/api.php?action=update_active_sessions`;

// === ДАБЛ-СТАФФ (сканирование на фоне) ===
const DS_URL = `${API_BASE}/api.php?action=update_doublestaff`;
const DS_INTERVAL = parseInt(process.env.DS_INTERVAL_MS || '3600000', 10); // раз в час
const STAFF_KEYWORDS = [
    // саппорт
    'саппорт', 'support', 'поддержка', 'отвечает',
    // модерка
    'модер', 'moder', 'moderator', 'модератор', 'mod',
    // контроль
    'контрол', 'контроль', 'control',
    // админка
    'админ', 'admin', 'administrator', 'администратор',
    // кураторка
    'куратор', 'curator',
    // прочее
    'staff', 'стафф', 'хелпер', 'helper', 'blum', 'content', 'contentmaker', 'гл.'
];
function isStaffRole(name) {
    const n = (name || '').toLowerCase();
    return STAFF_KEYWORDS.some(k => n.includes(k));
}
function dsSleep(ms) { return new Promise(r => setTimeout(r, ms)); }

const STAFF_URL = `${API_BASE}/api.php?action=get_staff_ids&token=${API_TOKEN}`;

async function runDoubleStaffScan() {
    try {
        console.log('🔎 [DS] Запускаю скан дабл-стаффа...');
        console.log(`🔎 [DS] Аккаунт состоит в ${client.guilds.cache.size} серверах`);

        // 1) Список стафа берём с САЙТА (а не качаем 133к участников Discord)
        let staffList = [];
        try {
            const r = await axios.get(STAFF_URL);
            if (r.data && r.data.success) staffList = r.data.staff || [];
        } catch (e) {
            console.error('❌ [DS] Не смог получить список стафа с сайта:', e.message);
            return;
        }
        console.log(`👥 [DS] Стафа из таблицы: ${staffList.length}`);
        if (staffList.length === 0) {
            console.warn('⚠️ [DS] Пустой список стафа — проверь, что таблица состава отдаётся (get_staff_ids).');
            return;
        }

        const staffIds = staffList.map(s => String(s.id));
        const nameById = {};
        staffList.forEach(s => { nameById[String(s.id)] = s.username; });

        const found = new Map();
        const otherGuilds = client.guilds.cache.filter(g => g.id !== GUILD_ID);
        console.log(`🌐 [DS] Проверяю ${otherGuilds.size} других серверов...`);

        for (const [, guild] of otherGuilds) {
            let fetchedInGuild = 0;
            let matchedInGuild = 0;
            // запрашиваем ТОЛЬКО наших стафферов по ID, батчами по 100
            for (let i = 0; i < staffIds.length; i += 100) {
                const chunk = staffIds.slice(i, i + 100);
                let members;
                try { members = await guild.members.fetch({ user: chunk }); }
                catch (e) { console.warn(`⚠️ [DS] ${guild.name}: ошибка fetch — ${e.message}`); continue; }
                fetchedInGuild += members.size;
                members.forEach(member => {
                    const staffRoles = member.roles.cache
                        .filter(r => r.name !== '@everyone' && isStaffRole(r.name))
                        .map(r => r.name);
                    if (staffRoles.length > 0) {
                        matchedInGuild++;
                        const id = member.id;
                        if (!found.has(id)) found.set(id, { discord_id: id, username: nameById[id] || member.user.username, entries: [] });
                        staffRoles.forEach(rn => found.get(id).entries.push({ guild: guild.name, role: rn }));
                    }
                });
                await dsSleep(800);
            }
            console.log(`[DS] ${guild.name}: наших участников ${fetchedInGuild}, со стаф-ролью ${matchedInGuild}`);
        }

        const results = Array.from(found.values());
        await axios.post(DS_URL, { token: API_TOKEN, results });
        console.log(`🔎 Дабл-стафф: найдено ${results.length}, отправлено на сайт`);
    } catch (err) {
        console.error('❌ Ошибка скана дабл-стаффа:', err.message);
    }
}

// Хранилище активных сессий: userId -> { channelId, startTime }
const activeSessions = new Map();

function isTrackedChannel(channelId) {
    return TARGET_CHANNELS.includes(channelId);
}

function shouldTrack(member) {
    if (!member) return false;
    const hasSupport = member.roles.cache.has(SUPPORT_ROLE_ID);
    const hasExclude = member.roles.cache.has(EXCLUDE_ROLE_ID);
    return hasSupport && !hasExclude;
}

client.on('ready', async () => {
    console.log(`✅ Voice Tracker запущен как ${client.user.tag}`);
    const guild = client.guilds.cache.get(GUILD_ID);
    if (!guild) return console.error('❌ Сервер не найден!');

    console.log(`📡 Мониторинг запущен для ${TARGET_CHANNELS.length} каналов.`);

    guild.channels.cache.forEach(channel => {
        if (channel.isVoice() && isTrackedChannel(channel.id)) {
            channel.members.forEach(member => {
                if (shouldTrack(member)) {
                    activeSessions.set(member.id, {
                        channelId: channel.id,
                        startTime: new Date()
                    });
                    console.log(`[INIT] ${member.user.tag} уже в канале ${channel.name}`);
                }
            });
        }
    });

    setInterval(syncActiveSessions, 10000);
    syncActiveSessions();

    // Дабл-стафф: первый скан через минуту после старта, далее по интервалу
    setTimeout(runDoubleStaffScan, 60000);
    setInterval(runDoubleStaffScan, DS_INTERVAL);
});

client.on('voiceStateUpdate', async (oldState, newState) => {
    const member = newState.member;
    if (!member) return;

    const userId = member.id;
    const oldChannelId = oldState.channelId;
    const newChannelId = newState.channelId;

    // Зашел в рабочий канал
    if (newChannelId && isTrackedChannel(newChannelId)) {
        if (!activeSessions.has(userId)) {
            if (shouldTrack(member)) {
                activeSessions.set(userId, {
                    channelId: newChannelId,
                    startTime: new Date()
                });
                console.log(`[JOIN] ${member.user.tag} -> ${newState.channel.name}`);
            }
        } else {
            activeSessions.get(userId).channelId = newChannelId;
        }
    } 
    // Вышел из рабочего канала
    else if (oldChannelId && isTrackedChannel(oldChannelId)) {
        if (activeSessions.has(userId)) {
            const session = activeSessions.get(userId);
            const endTime = new Date();
            const duration = Math.floor((endTime - session.startTime) / 1000);

            activeSessions.delete(userId);
            console.log(`[LEAVE] ${member.user.tag} покинул канал. Длительность: ${duration} сек.`);

            if (duration > 5) {
                saveVoiceLog(userId, session.channelId, session.startTime, endTime, duration);
            }
        }
    }
});

async function saveVoiceLog(discordId, channelId, startTime, endTime, duration) {
    try {
        await axios.post(API_URL, {
            discord_id: discordId,
            channel_id: channelId,
            start_time: startTime.toISOString(),
            end_time: endTime.toISOString(),
            duration: duration,
            token: API_TOKEN
        });
    } catch (error) {
        console.error(`❌ Ошибка сохранения: ${error.message}`);
    }
}

async function syncActiveSessions() {
    try {
        const sessions = [];
        activeSessions.forEach((value, key) => {
            sessions.push({
                discord_id: key,
                channel_id: value.channelId,
                start_time: value.startTime.toISOString()
            });
        });
        await axios.post(SYNC_URL, { token: API_TOKEN, sessions: sessions });
    } catch (error) {}
}

client.login(process.env.Self_bot);
