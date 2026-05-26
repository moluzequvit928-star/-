require('dotenv').config();
const { Client } = require('discord.js-selfbot-v13');

const client = new Client({ checkUpdate: false });

const GUILD_ID = process.env.GUILD_ID;

const CHANNELS = [
    { name: '1 проходная',  id: '1268331705194774643' },
    { name: '2 проходная',  id: '1268327713463341168' },
    { name: '3 проходная',  id: '1268327800767774720' },
    { name: '4 проходная',  id: '1268327820736598128' },
    { name: '5 проходная',  id: '1268327846494081064' },
    { name: '6 проходная',  id: '1268327884045684807' },
    { name: '7 проходная',  id: '1268328226607075338' },
    { name: '8 проходная',  id: '1268328281761906698' },
    { name: '9 проходная',  id: '1318228034016514128' },
    { name: '10 проходная', id: '1501951333790384189' },
    { name: '11 проходная', id: '1503680035528376571' },
    { name: '12 проходная', id: '1503680189391966238' },
];

client.on('ready', async () => {
    const guild = client.guilds.cache.get(GUILD_ID);
    if (!guild) {
        console.log('ERROR: Guild not found');
        process.exit(1);
    }

    const results = [];

    for (const ch of CHANNELS) {
        const channel = guild.channels.cache.get(ch.id);
        if (!channel) {
            results.push({ name: ch.name, id: ch.id, count: 0, members: [] });
            continue;
        }

        const members = channel.members
            ? Array.from(channel.members.values()).map(m => {
                const user = m.user;
                const avatarHash = user.avatar;
                const avatarUrl = avatarHash
                    ? `https://cdn.discordapp.com/avatars/${user.id}/${avatarHash}.png?size=64`
                    : `https://cdn.discordapp.com/embed/avatars/${Number(BigInt(user.id) % 5n)}.png`;
                return { id: user.id, tag: user.tag, avatar: avatarUrl };
            })
            : [];

        results.push({
            name: ch.name,
            id: ch.id,
            count: members.length,
            members: members
        });
    }

    console.log('---CHANNELS_DATA---');
    console.log(JSON.stringify(results));
    console.log('---END_CHANNELS_DATA---');

    process.exit(0);
});

// Таймаут безопасности — выход через 30 секунд
setTimeout(() => {
    console.log('ERROR: Timeout');
    process.exit(1);
}, 30000);

client.login(process.env.Self_bot);
