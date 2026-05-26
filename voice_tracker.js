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

const API_BASE = process.env.SITE_URL || 'http://127.0.0.1:8000';
const API_URL = `${API_BASE}/api.php?action=log_voice`;
const API_TOKEN = process.env.BOT_API_TOKEN || 'futika_bot_secret_2026';
const SYNC_URL = `${API_BASE}/api.php?action=update_active_sessions`;

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
