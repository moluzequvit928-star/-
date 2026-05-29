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
        const records = parse(fileContent, { columns: false, skip_empty_lines: true, relax_column_count: true, info: true });
        
        const mandatoryIds = new Set();
        const ignoredIds = new Set();
        const supportIdOccurrences = new Map(); // id -> [{ row: rowNum, username: row[2] }]

        records.forEach((item, index) => {
            const row = item.record;
            const rowNum = item.info.lines;
            // Столбец D (индекс 3) - саппорты
            const supportId = row[3]?.trim().replace(/"/g, '');
            const username = row[2]?.trim().replace(/"/g, '') || 'Неизвестно';

            if (supportId && /^\d{17,20}$/.test(supportId)) {
                mandatoryIds.add(supportId);
                
                if (!supportIdOccurrences.has(supportId)) {
                    supportIdOccurrences.set(supportId, []);
                }
                supportIdOccurrences.get(supportId).push({ row: rowNum, username: username });
            }

            // Столбец W (индекс 22) - вышка
            const highUpId = row[22]?.trim().replace(/"/g, '');
            if (highUpId && /^\d{17,20}$/.test(highUpId)) {
                ignoredIds.add(highUpId);
            }
        });

        const duplicates = [];
        supportIdOccurrences.forEach((occurrences, id) => {
            if (occurrences.length > 1) {
                duplicates.push({
                    id: id,
                    occurrences: occurrences
                });
            }
        });

        return { mandatoryIds, ignoredIds, duplicates };
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

    const { mandatoryIds, ignoredIds, duplicates } = sheetData;
    console.log(`📊 В таблице (саппорты): ${mandatoryIds.size} чел.`);
    console.log(`📊 В таблице (вышка): ${ignoredIds.size} чел.`);
    console.log(`📊 Обнаружено дубликатов ID: ${duplicates.length}`);

    try {
        console.log('🔍 Получаю данные участников...');
        
        const allRelevantIds = Array.from(new Set([...mandatoryIds, ...ignoredIds]));
        const sheetMembers = new Map();

        // Оптимизация: Запускаем запросы параллельно пачками
        const chunkSize = 50;
        const promises = [];
        for (let i = 0; i < allRelevantIds.length; i += chunkSize) {
            const chunk = allRelevantIds.slice(i, i + chunkSize);
            promises.push(
                guild.members.fetch({ user: chunk, withPresences: false }).catch(e => {
                    console.error(`Ошибка при загрузке пачки: ${e.message}`);
                    return new Map();
                })
            );
        }

        const results = await Promise.all(promises);
        results.forEach(fetchedMap => {
            fetchedMap.forEach(m => sheetMembers.set(m.id, m));
        });

        console.log('✅ Данные участников получены.');

        // Получаем всех с ролью (быстро, без статусов) с таймаутом, чтобы предотвратить зависание
        let membersWithRole = new Map();
        try {
            const fetchPromise = guild.members.fetch({ role: ROLE_ID, withPresences: false });
            const timeoutPromise = new Promise((_, reject) => setTimeout(() => reject(new Error('Gateway Timeout')), 8000));
            membersWithRole = await Promise.race([fetchPromise, timeoutPromise]);
        } catch (e) {
            console.log(`⚠️ Не удалось получить участников с ролью по API (${e.message}), используем кэш...`);
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

        // Ищем тех, кто в D, но роли нет.
        // ВАЖНО: пачечный fetch({user}) через селф-бот возвращает не всех,
        // поэтому "подозрительных" перепроверяем точечно через REST (fetch по одному) —
        // иначе реальные саппорты ошибочно попадают в "убрать из таблицы".
        for (const id of mandatoryIds) {
            let member = sheetMembers.get(id) || membersWithRole.get(id);

            if (!member || !member.roles.cache.has(ROLE_ID)) {
                // точечная перепроверка
                try {
                    member = await guild.members.fetch({ user: id, force: true });
                } catch (e) {
                    member = null; // 404 = реально не в сервере
                }
                await new Promise(r => setTimeout(r, 150)); // лёгкая пауза от рейт-лимита
            }

            if (!member || !member.roles.cache.has(ROLE_ID)) {
                missingInDiscord.push(id);
            }
        }

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
                let user = sheetMembers.get(id)?.user || client.users.cache.get(id);
                if (!user) {
                    try {
                        const fetchUserPromise = client.users.fetch(id);
                        const timeoutUserPromise = new Promise((_, reject) => setTimeout(() => reject(new Error('Timeout')), 2500));
                        user = await Promise.race([fetchUserPromise, timeoutUserPromise]);
                    } catch (e) {
                        user = null;
                    }
                }
                console.log(` > ${user ? user.tag : 'Неизвестный'} (${id})`);
            }
        } else {
            console.log('\n✅ Все участники из таблицы имеют роль.');
        }

        if (duplicates.length > 0) {
            console.log(`\n🟠 ДУБЛИКАТЫ (дублирование ID в таблице) [${duplicates.length}]:`);
            duplicates.forEach(d => {
                const details = d.occurrences.map(o => `строка ${o.row} (${o.username})`).join(', ');
                console.log(` > ID ${d.id} (${details})`);
            });
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

client.login(process.env.Self_bot);
