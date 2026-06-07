require('dotenv').config();
const { Client, SnowflakeUtil } = require('discord.js-selfbot-v13');
const axios = require('axios');
const util = require('util');

// Шлём autocomplete-запрос боту и ждём его ответа (UNHANDLED_PACKET с APPLICATION_COMMAND_AUTOCOMPLETE_RESPONSE).
// Бот вернёт список choices — берём тот, что совпал с typed value (по name или value), либо первый.
async function runAutocomplete(client, channel, app, command, opt, typedValue, alreadyFilledOpts) {
    const nonce = SnowflakeUtil.generate().toString();
    const body = {
        type: 4, // APPLICATION_COMMAND_AUTOCOMPLETE
        application_id: app.id,
        guild_id: channel.guild?.id,
        channel_id: channel.id,
        session_id: client.sessionId,
        data: {
            version: command.version,
            id: command.id,
            name: command.name_default || command.name,
            type: command.type || 1,
            options: [
                // Уже введённые опции (без focused)
                ...alreadyFilledOpts.map(o => ({ type: o.type, name: o.name, value: o.value })),
                // Текущая опция — focused (та, для которой автодополняем)
                { type: opt.type, name: opt.name_default || opt.name, value: String(typedValue), focused: true }
            ],
            attachments: [],
            guild_id: channel.guild?.id,
        },
        nonce,
    };

    // Подписываемся на ответ ДО отправки
    const waitPromise = new Promise(resolve => {
        const handler = packet => {
            if (packet?.t !== 'APPLICATION_COMMAND_AUTOCOMPLETE_RESPONSE') return;
            if (packet?.d?.nonce !== nonce) return;
            cleanup();
            resolve(packet.d.choices || []);
        };
        const timer = setTimeout(() => { cleanup(); resolve(null); }, 5000);
        const cleanup = () => {
            clearTimeout(timer);
            client.removeListener('raw', handler);
        };
        client.on('raw', handler);
    });

    await client.api.interactions.post({ data: body });
    const choices = await waitPromise;

    if (Array.isArray(choices) && choices.length) {
        // Пробуем найти точное совпадение по имени или значению
        const exact = choices.find(c => c.name === typedValue || c.value === typedValue);
        return (exact || choices[0]).value;
    }
    // Если бот не ответил — возвращаем что было; запрос всё равно может пройти
    return typedValue;
}

// Прямая отправка slash-команды через сырой API. Минует sendSlash,
// который ломается на параметрах с autocomplete=true (даже если есть choices).
async function sendSlashRaw(client, channel, botRef, commandName, values) {
    const data = await channel.searchInteraction();
    const botId = client.users.resolveId(botRef) || (typeof botRef === 'string' ? botRef : botRef.id);
    const app = data.applications.find(a => a.id == botId || a.bot_id == botId);
    if (!app) throw new Error(`Bot ${botId} не найден в гильдии`);
    const command = data.application_commands.find(
        c => (c.name === commandName || c.name_default === commandName) && c.application_id == app.id
    );
    if (!command) throw new Error(`Команда /${commandName} не найдена у бота ${botId}`);

    // ОТЛАДКА: вывести полную схему ровно того /voice, что нашли (со ВСЕМИ полями)
    const schemaDump = {
        name: command.name,
        name_default: command.name_default,
        id: command.id,
        version: command.version,
        type: command.type,
        application_id: command.application_id,
        options: (command.options || []).map(o => ({
            name: o.name,
            name_default: o.name_default,
            type: o.type,
            required: o.required,
            autocomplete: o.autocomplete,
            choices: o.choices?.map(c => ({ name: c.name, name_default: c.name_default, value: c.value })) || null,
            description: o.description,
            // На всякий случай — выводим ВСЕ ключи опции
            _allKeys: Object.keys(o)
        }))
    };
    if (!sendSlashRaw._dumpedSchema) {
        sendSlashRaw._dumpedSchema = JSON.stringify(schemaDump, null, 2);
        console.log('  📋 ПОЛНАЯ СХЕМА /voice:');
        console.log(sendSlashRaw._dumpedSchema);
    }
    // Сохраняем последнюю схему — используем её в логе ошибок
    sendSlashRaw._lastSchema = schemaDump;

    const opts = [];
    for (let idx = 0; idx < (command.options || []).length; idx++) {
        const opt = command.options[idx];
        let value = values[idx];
        if (value === undefined) {
            if (opt.required) throw new Error(`Не передан обязательный параметр ${opt.name}`);
            continue;
        }
        switch (opt.type) {
            case 4: case 10: value = Number(value); break;
            case 5: value = Boolean(value); break;
            default: value = String(value);
        }
        if (Array.isArray(opt.choices) && opt.choices.length) {
            const c = opt.choices.find(c => c.name === value || c.value === value);
            if (c) value = c.value;
        }
        // Используем name_default если есть — это оригинальное (не локализованное) имя,
        // которое Discord ждёт в interactions API.
        const sendName = opt.name_default || opt.name;
        // ===== AUTOCOMPLETE-параметры =====
        if (opt.autocomplete) {
            const acValue = await runAutocomplete(client, channel, app, command, opt, value, opts);
            value = acValue;
        }
        opts.push({ type: opt.type, name: sendName, value });
    }

    const body = {
        type: 2, // APPLICATION_COMMAND (не autocomplete!)
        application_id: app.id,
        guild_id: channel.guild?.id,
        channel_id: channel.id,
        session_id: client.sessionId,
        data: {
            version: command.version,
            id: command.id,
            name: command.name_default || command.name,
            type: command.type || 1,
            options: opts,
            attachments: [],
            ...(channel.guild ? { guild_id: channel.guild.id } : {}),
        },
        nonce: SnowflakeUtil.generate().toString(),
    };
    await client.api.interactions.post({ data: body });
}

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

        let staffIds = staffList.map(s => String(s.id));
        const nameById = {};
        staffList.forEach(s => { nameById[String(s.id)] = s.username; });

        // Оставляем только тех, у кого РЕАЛЬНО есть роль саппорта на основном сервере
        // (исключаем бывших / тех, кто в "Убрать из гугл таблицы").
        const mainGuild = client.guilds.cache.get(GUILD_ID);
        if (mainGuild) {
            const confirmed = [];
            for (let i = 0; i < staffIds.length; i += 100) {
                const chunk = staffIds.slice(i, i + 100);
                let members;
                try { members = await mainGuild.members.fetch({ user: chunk }); }
                catch (e) { continue; }
                members.forEach(m => {
                    if (m.roles.cache.has(SUPPORT_ROLE_ID) && !m.roles.cache.has(EXCLUDE_ROLE_ID)) confirmed.push(m.id);
                });
                await dsSleep(800);
            }
            console.log(`✅ [DS] Активных саппортов на основном сервере: ${confirmed.length} (из ${staffIds.length} в списке)`);
            if (confirmed.length > 0) staffIds = confirmed;
        } else {
            console.warn('⚠️ [DS] Основной сервер не в кэше — проверяю без фильтра по роли.');
        }

        const found = new Map();
        const otherGuilds = client.guilds.cache.filter(g => g.id !== GUILD_ID);
        console.log(`🌐 [DS] Проверяю ${otherGuilds.size} других серверов...`);

        for (const [, guild] of otherGuilds) {
            let fetchedInGuild = 0;
            let matchedInGuild = 0;
            // запрашиваем ТОЛЬКО наших активных саппортов по ID, батчами по 100
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

// === МАССОВЫЙ /voice ПО СМЕНЕ 7-9 (триггер с сайта от nevermore8465 / admin) ===
const VC_POP_URL = `${API_BASE}/api.php?action=voice_cmd_pop&token=${API_TOKEN}`;
const VC_DONE_URL = `${API_BASE}/api.php?action=voice_cmd_complete`;
const VC_STATS_URL = `${API_BASE}/api.php?action=voice_stats_save`;

// Ждёт следующее сообщение в канале channelId от автора botId, не дольше timeoutMs.
function waitBotReply(client, channelId, botId, timeoutMs) {
    return new Promise(resolve => {
        const handler = msg => {
            if (msg.channelId !== channelId) return;
            if (msg.author?.id !== botId) return;
            cleanup();
            resolve(msg);
        };
        const timer = setTimeout(() => { cleanup(); resolve(null); }, timeoutMs);
        const cleanup = () => {
            clearTimeout(timer);
            client.removeListener('messageCreate', handler);
        };
        client.on('messageCreate', handler);
    });
}

// Из текста "X ч. Y мин. Z сек." → секунды
function parseDuration(str) {
    let total = 0;
    const h = str.match(/(\d+)\s*ч/i);  if (h) total += parseInt(h[1], 10) * 3600;
    const m = str.match(/(\d+)\s*мин/i); if (m) total += parseInt(m[1], 10) * 60;
    const s = str.match(/(\d+)\s*сек/i); if (s) total += parseInt(s[1], 10);
    return total;
}

// Парсим embed-ответ FuturamaMod: ищем 7 дней (Понедельник…Воскресенье) и их длительность.
// Возвращает {mon, tue, wed, thu, fri, sat, sun, weekStart} или null.
function parseVoiceReply(message) {
    const dayMap = {
        'понедельник': 'mon', 'вторник': 'tue', 'среда': 'wed', 'четверг': 'thu',
        'пятница': 'fri', 'суббота': 'sat', 'воскресенье': 'sun'
    };
    const result = { mon: 0, tue: 0, wed: 0, thu: 0, fri: 0, sat: 0, sun: 0, weekStart: null };
    const sources = [];
    if (message.content) sources.push(message.content);
    for (const e of (message.embeds || [])) {
        if (e.title) sources.push(e.title);
        if (e.description) sources.push(e.description);
        for (const f of (e.fields || [])) {
            if (f.name) sources.push(f.name);
            if (f.value) sources.push(f.value);
        }
    }
    const fullText = sources.join('\n');
    if (!fullText) return null;

    // Ищем по строкам "<день недели> (DD.MM.YYYY): ... За день: <длительность>"
    const lines = fullText.split('\n');
    let foundAny = false;
    let firstDate = null;
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].toLowerCase();
        for (const [ru, en] of Object.entries(dayMap)) {
            if (line.includes(ru)) {
                // Дата дня
                const dm = lines[i].match(/(\d{2})\.(\d{2})\.(\d{4})/);
                if (dm && !firstDate && en === 'mon') firstDate = `${dm[3]}-${dm[2]}-${dm[1]}`;
                // Длительность — может быть на этой или следующей строке
                let durStr = lines[i];
                if (i + 1 < lines.length) durStr += ' ' + lines[i + 1];
                const dur = parseDuration(durStr);
                result[en] = dur;
                foundAny = true;
            }
        }
    }
    if (!foundAny) return null;
    // Если понедельника нет — вычисляем дату начала недели по любой найденной дате
    if (!firstDate) {
        const anyDate = fullText.match(/(\d{2})\.(\d{2})\.(\d{4})/);
        if (anyDate) {
            const d = new Date(`${anyDate[3]}-${anyDate[2]}-${anyDate[1]}T00:00:00Z`);
            const wd = d.getUTCDay(); // 0=Sun, 1=Mon, ...
            const diff = (wd === 0 ? -6 : 1 - wd);
            d.setUTCDate(d.getUTCDate() + diff);
            firstDate = d.toISOString().slice(0, 10);
        }
    }
    result.weekStart = firstDate;
    return result;
}
const VC_POLL_MS = 8000;

// Антибан-задержки между вызовами /voice. Можно переопределить через env.
const VC_MIN_DELAY_MS  = parseInt(process.env.VC_MIN_DELAY_MS  || '15000', 10); // 15 сек
const VC_MAX_DELAY_MS  = parseInt(process.env.VC_MAX_DELAY_MS  || '45000', 10); // 45 сек
const VC_LONG_PAUSE_MS = parseInt(process.env.VC_LONG_PAUSE_MS || '120000', 10); // 2 мин
const VC_LONG_EVERY    = parseInt(process.env.VC_LONG_EVERY    || '5', 10); // каждые 5 человек

let vcBusy = false;

function vcSleep(ms) { return new Promise(r => setTimeout(r, ms)); }
function vcRand(min, max) { return Math.floor(min + Math.random() * (max - min + 1)); }
function vcShuffle(a) { const x = a.slice(); for (let i = x.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [x[i], x[j]] = [x[j], x[i]]; } return x; }

async function pollVoiceCommand() {
    if (vcBusy) return;
    try {
        const { data } = await axios.get(VC_POP_URL, { timeout: 15000 });
        if (!data.success || !data.has_task) return;

        vcBusy = true;
        const taskId = data.task_id;
        const channelId = data.channel_id;
        const botId = data.bot_id;
        const group = data.group || 'Support';
        // Порядок перемешиваем — детектору сложнее увидеть «один и тот же скрипт»
        const staff = vcShuffle(data.staff || []);
        const logLines = [];
        let okCount = 0, failCount = 0;

        const estMin = Math.round(((VC_MIN_DELAY_MS + VC_MAX_DELAY_MS) / 2 * staff.length + Math.floor(staff.length / VC_LONG_EVERY) * VC_LONG_PAUSE_MS) / 60000);
        logLines.push(`[${new Date().toISOString()}] Старт задачи #${taskId}. Найдено ${staff.length} саппортов. Прогон ~${estMin} мин.`);
        console.log(`🎙️ [VC] Старт #${taskId}, ${staff.length} саппортов, ~${estMin} мин`);

        const channel = await client.channels.fetch(channelId).catch(e => {
            logLines.push(`FATAL: канал ${channelId} не открывается: ${e.message}`);
            return null;
        });

        // Бот гарантированно состоит на сервере канала. members.fetch отдаёт его даже когда
        // users.fetch падает с Unauthorized (нет прямых связей с селфботом).
        let bot = null;
        if (channel && channel.guild) {
            const member = await channel.guild.members.fetch(botId).catch(() => null);
            if (member) bot = member.user;
        }
        if (!bot) bot = await client.users.fetch(botId).catch(() => null);
        if (!bot) {
            logLines.push(`WARN: не смог получить User объект бота ${botId} — пробую с raw ID`);
            console.log(`  ⚠️ bot fetch fail → raw id`);
            bot = botId;
        } else {
            logLines.push(`INFO: бот резолвлен как ${bot.tag || bot.username || bot.id}`);
            console.log(`  ℹ️  бот: ${bot.tag || bot.username || bot.id}`);
        }

        if (channel) {
            // Диагностика: глянем какие команды бот реально регистрировал в этой гильдии
            try {
                if (channel.guild && typeof channel.guild.searchInteraction === 'function') {
                    const found = await channel.guild.searchInteraction({
                        type: 1, query: 'voice', limit: 10, applications: true
                    });
                    const list = (found?.application_commands || found?.commands || found || [])
                        .map(c => ({
                            name: c.name,
                            app: c.application_id,
                            options: (c.options || []).map(o => ({ name: o.name, type: o.type, required: o.required, choices: o.choices }))
                        }));
                    console.log('  🔎 найдено /voice команд:', JSON.stringify(list, null, 2));
                    logLines.push(`SCHEMA: ${JSON.stringify(list)}`);
                }
            } catch (e) {
                console.log('  ⚠️ schema fetch err:', e.message);
                logLines.push(`SCHEMA ERR: ${e.message}`);
            }

            let idx = 0;
            let stopAfterFirstError = false; // отладку выключили — работаем по полной

            for (const s of staff) {
                try {
                    // /voice группа:Support target:<id>
                    await sendSlashRaw(client, channel, bot, 'voice', [group, s.id]);
                    okCount++;
                    logLines.push(`OK   ${s.nick} (${s.id}) смена ${s.shift}`);
                    console.log(`  ✅ ${s.nick} (${s.id})`);

                    // Ждём ответ бота и парсим
                    try {
                        const botId = (typeof bot === 'object') ? bot.id : bot;
                        const reply = await waitBotReply(client, channel.id, botId, 10000);
                        if (reply) {
                            const parsed = parseVoiceReply(reply);
                            if (parsed && parsed.weekStart) {
                                await axios.post(VC_STATS_URL, {
                                    token: API_TOKEN,
                                    discord_id: s.id,
                                    nick: s.nick,
                                    shift: s.shift,
                                    week_start: parsed.weekStart,
                                    days: {
                                        mon: parsed.mon, tue: parsed.tue, wed: parsed.wed,
                                        thu: parsed.thu, fri: parsed.fri, sat: parsed.sat, sun: parsed.sun
                                    }
                                }).catch(() => {});
                                logLines.push(`   STATS сохранены: пн=${parsed.mon}с вт=${parsed.tue}с ср=${parsed.wed}с чт=${parsed.thu}с пт=${parsed.fri}с сб=${parsed.sat}с вс=${parsed.sun}с (неделя ${parsed.weekStart})`);
                            } else {
                                logLines.push(`   WARN: не распарсил ответ бота для ${s.nick}`);
                            }
                        } else {
                            logLines.push(`   WARN: бот не ответил за 10 сек на ${s.nick}`);
                        }
                    } catch (pe) {
                        logLines.push(`   ошибка парсинга/сохранения: ${pe.message}`);
                    }
                } catch (e) {
                    failCount++;
                    // Полный дамп — все скрытые поля
                    const detail = util.inspect(e, { depth: 6, showHidden: false, breakLength: 200 });
                    const keys = Object.getOwnPropertyNames(e).join(', ');
                    const schema = sendSlashRaw._lastSchema
                        ? `СХЕМА КОМАНДЫ:\n${JSON.stringify(sendSlashRaw._lastSchema, null, 2)}\n\n`
                        : '';
                    logLines.push(`FAIL ${s.nick} (${s.id}):\n${schema}KEYS: ${keys}\n${detail}`);
                    console.log(`  ❌ ${s.nick} (${s.id})`);
                    console.log('  KEYS:', keys);
                    console.log(detail);
                    if (stopAfterFirstError) {
                        logLines.push('Остановлен после первой ошибки для диагностики.');
                        console.log('  🛑 стоп — диагностика');
                        break;
                    }
                }
                idx++;

                if (idx >= staff.length) break; // последнего ждать незачем

                // Каждые N человек — длинная пауза, чтобы выглядеть как живой юзер
                if (idx % VC_LONG_EVERY === 0) {
                    const pause = VC_LONG_PAUSE_MS + vcRand(0, 30000);
                    logLines.push(`... длинная пауза ${Math.round(pause/1000)}с (после ${idx}/${staff.length})`);
                    console.log(`  ⏸  длинная пауза ${Math.round(pause/1000)}с`);
                    await vcSleep(pause);
                } else {
                    // Обычная рандомная пауза 15-45 сек
                    await vcSleep(vcRand(VC_MIN_DELAY_MS, VC_MAX_DELAY_MS));
                }
            }
        }

        await axios.post(VC_DONE_URL, {
            token: API_TOKEN,
            task_id: taskId,
            total: staff.length,
            success_count: okCount,
            fail_count: failCount,
            log: logLines.join('\n')
        }).catch(e => console.error(`[VC] complete не отправился: ${e.message}`));

        console.log(`🎙️ [VC] Задача #${taskId} завершена: OK=${okCount}, FAIL=${failCount}`);
    } catch (e) {
        console.error(`[VC] ошибка поллинга: ${e.message}`);
    } finally {
        vcBusy = false;
    }
}

setInterval(pollVoiceCommand, VC_POLL_MS);

// Авто-запуск каждый понедельник в 00:05 локального времени
let vcLastAutoRunDate = '';
async function tryWeeklyAutoRun() {
    const now = new Date();
    if (now.getDay() !== 1) return; // понедельник
    if (now.getHours() !== 0 || now.getMinutes() < 5 || now.getMinutes() > 9) return;
    const dateKey = now.toISOString().slice(0, 10);
    if (vcLastAutoRunDate === dateKey) return;
    vcLastAutoRunDate = dateKey;
    try {
        // INSERT pending напрямую через API (бот ставит как 'auto')
        await axios.post(`${API_BASE}/api.php?action=voice_cmd_auto_request`, {
            token: API_TOKEN
        }).then(r => {
            if (r.data?.success) console.log('🗓️ [VC] Авто-запуск понедельника поставлен в очередь');
            else console.log('🗓️ [VC] Авто-запуск НЕ удался:', r.data?.error);
        });
    } catch (e) {
        console.log('🗓️ [VC] Авто-запуск ошибка:', e.message);
    }
}
setInterval(tryWeeklyAutoRun, 60000); // проверяем раз в минуту

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
