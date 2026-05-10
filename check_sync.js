require('dotenv').config();
const { Client } = require('discord.js-selfbot-v13');
const fs = require('fs');
const { parse } = require('csv-parse/sync');
const axios = require('axios');

const client = new Client({ checkUpdate: false });

const GUILD_ID = process.env.GUILD_ID;
const ROLE_ID = process.env.ROLE_ID;
const SHEET_URL = process.env.SHEET_URL;
const FILE_PATH = './table_temp.csv';

async function downloadTable() {
    try {
        console.log('📥 Скачиваю таблицу из Google Sheets...');
        const response = await axios.get(SHEET_URL, { timeout: 30000, responseType: 'text' });
        fs.writeFileSync(FILE_PATH, response.data);
        return true;
    } catch (error) {
        console.error(`⚠️ Ошибка скачивания: ${error.message}`);
        return false;
    }
}

function getSheetData() {
    try {
        if (!fs.existsSync(FILE_PATH)) return null;
        const fileContent = fs.readFileSync(FILE_PATH, 'utf-8');
        const records = parse(fileContent, { columns: false, skip_empty_lines: true, relax_column_count: true });
        
        const mandatoryIds = new Set();
        const ignoredIds = new Set();

        records.forEach(row => {
            // Столбец D (индекс 3) - саппорты
            const supportId = row[3]?.trim().replace(/"/g, '');
            if (supportId && /^\d{17,20}$/.test(supportId)) {
                mandatoryIds.add(supportId);
            }

            // Столбец W (индекс 22) - вышка
            const highUpId = row[22]?.trim().replace(/"/g, '');
            if (highUpId && /^\d{17,20}$/.test(highUpId)) {
                ignoredIds.add(highUpId);
            }
        });

        return { mandatoryIds, ignoredIds };
    } catch (error) {
        console.error('❌ Ошибка при чтении файла:', error.message);
        return null;
    }
}

client.on('ready', async () => {
    console.log(`✅ Залогинился как ${client.user.tag}`);
    const guild = client.guilds.cache.get(GUILD_ID);
    if (!guild) {
        console.error('❌ Сервер не найден!');
        process.exit(1);
    }

    const role = guild.roles.cache.get(ROLE_ID);
    console.log(`Сервер: ${guild.name} | Роль: ${role ? role.name : 'НЕ НАЙДЕНА'}`);

    await downloadTable();
    const sheetData = getSheetData();
    if (!sheetData) return;

    const { mandatoryIds, ignoredIds } = sheetData;
    console.log(`📊 В таблице (саппорты): ${mandatoryIds.size} чел.`);
    console.log(`📊 В таблице (вышка): ${ignoredIds.size} чел.`);

    try {
        console.log('🔍 Получаю данные участников...');
        
        // На селфботах лучше грузить пачками по 50
        const allRelevantIds = Array.from(new Set([...mandatoryIds, ...ignoredIds]));
        const sheetMembers = new Map();

        // Постепенно наполняем кэш и собираем участников
        for (let i = 0; i < allRelevantIds.length; i += 50) {
            const chunk = allRelevantIds.slice(i, i + 50);
            try {
                const fetched = await guild.members.fetch({ user: chunk, force: true });
                fetched.forEach(m => sheetMembers.set(m.id, m));
            } catch (e) {}
            process.stdout.write(`\r   Прогресс: ${Math.round((i / allRelevantIds.length) * 100)}%   `);
        }
        console.log('\n✅ Данные участников получены.');

        // Получаем всех с ролью (используем кэш + fetch по роли)
        let membersWithRole = new Map();
        try {
            membersWithRole = await guild.members.fetch({ role: ROLE_ID, force: true });
        } catch (e) {
            // Если fetch по роли упал, фильтруем то что есть в кэше
            membersWithRole = guild.members.cache.filter(m => m.roles.cache.has(ROLE_ID));
        }

        console.log(`👥 В Discord найдено участников с ролью: ${membersWithRole.size}`);

        const extraInDiscord = [];
        const missingInDiscord = [];

        // Ищем лишних: есть роль, но нет ни в D, ни в W
        membersWithRole.forEach(member => {
            if (!mandatoryIds.has(member.id) && !ignoredIds.has(member.id)) {
                extraInDiscord.push(`${member.user.tag} (${member.id})`);
            }
        });

        // Ищем тех, кто в D, но роли нет (вышку W не считаем если у них нет роли)
        mandatoryIds.forEach(id => {
            const member = sheetMembers.get(id);
            if (!member || !member.roles.cache.has(ROLE_ID)) {
                missingInDiscord.push(id);
            }
        });

        console.log('\n' + '═'.repeat(45));
        console.log('           РЕЗУЛЬТАТЫ СВЕРКИ');
        console.log('═'.repeat(45));

        if (extraInDiscord.length > 0) {
            console.log(`🔴 ЛИШНИЕ (есть роль, нет в таблице) [${extraInDiscord.length}]:`);
            extraInDiscord.forEach(m => console.log(` > ${m}`));
        } else {
            console.log('✅ Лишних участников с ролью нет.');
        }

        if (missingInDiscord.length > 0) {
            console.log(`\n🟡 НЕТ РОЛИ (есть в таблице, нет роли) [${missingInDiscord.length}]:`);
            for (const id of missingInDiscord) {
                let user = client.users.cache.get(id) || await client.users.fetch(id).catch(() => null);
                console.log(` > ${user ? user.tag : 'Неизвестный'} (${id})`);
            }
        } else {
            console.log('\n✅ Все участники из таблицы имеют роль.');
        }

        // Выводим список всех текущих ID с ролью для авто-трекинга (скрыто для пользователя в PHP)
        console.log('---CURRENT_DISCORD_IDS---');
        console.log(Array.from(membersWithRole.keys()).join(','));
        console.log('---END_CURRENT_DISCORD_IDS---');

    } catch (err) {
        console.error('Ошибка:', err);
    }

    console.log('\nГотово. Выхожу...');
    process.exit(0);
});

client.login(process.env.TOKEN);
