/**
 * Google Apps Script webhook для отметки "сдал" из сайта.
 * Версия с улучшенным поиском колонок и поддержкой столбцов E и H.
 */

const CONFIG = {
  SHEET_NAME: 'Переаттестация', // Название листа
  
  // Токен для защиты (должен совпадать с тем, что в app_config.php)
  SECRET_TOKEN: 'futika_2026_q7N4vP2xLm8Kc5Rz',

  // Варианты названий колонок
  HEADER_DISCORD_ID: ['Discord ID', 'ID', 'Айди', 'Никнейм/ID'],
  HEADER_STATUS: ['Результат', 'Сдал/не сдал', 'Статус'],
  HEADER_CURATOR: ['Проводящий куратор', 'Проводящий', 'Куратор'],
  HEADER_DATE: ['Дата проведения', 'Дата'],
};

function doPost(e) {
  try {
    const body = parseRequestBody_(e);
    if (!body) return json_(400, { success: false, error: 'Пустое тело запроса' });

    if (CONFIG.SECRET_TOKEN && String(body.token || '') !== CONFIG.SECRET_TOKEN) {
      return json_(403, { success: false, error: 'Ошибка авторизации (Токен не совпадает)' });
    }

    const discordId = String(body.discord_id || '').trim();
    const result = markResultByDiscordId_({
      discordId: discordId,
      status: String(body.status || 'сдал'),
      curator: String(body.curator || '').trim(),
      date: String(body.date || '').trim()
    });

    return json_(result.success ? 200 : 400, result);
  } catch (err) {
    return json_(500, { success: false, error: 'Ошибка скрипта: ' + err.message });
  }
}

function markResultByDiscordId_(payload) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName(CONFIG.SHEET_NAME);
  
  if (!sheet) {
    return { success: false, error: `Лист "${CONFIG.SHEET_NAME}" не найден. Проверьте название вкладки!` };
  }

  const lastRow = sheet.getLastRow();
  const lastCol = sheet.getLastColumn();
  
  let headerRowIndex = -1;
  let colDiscordId = -1;
  let colStatus = -1;
  let colCurator = -1;
  let colDate = -1;

  // Ищем заголовки в первых 10 строках
  const searchRange = sheet.getRange(1, 1, Math.min(10, Math.max(1, lastRow)), Math.max(1, lastCol)).getValues();
  
  for (let r = 0; r < searchRange.length; r++) {
    const row = searchRange[r];
    const dIdx = findCol_(row, CONFIG.HEADER_DISCORD_ID);
    const sIdx = findCol_(row, CONFIG.HEADER_STATUS);
    
    if (dIdx !== -1 && sIdx !== -1) {
      headerRowIndex = r + 1;
      colDiscordId = dIdx;
      colStatus = sIdx;
      colCurator = findCol_(row, CONFIG.HEADER_CURATOR);
      colDate = findCol_(row, CONFIG.HEADER_DATE);
      break;
    }
  }

  // ФОЛБЭК: Если не нашли по именам, используем E (4) и H (7)
  if (colDiscordId === -1) colDiscordId = 4; // Столбец E
  if (colStatus === -1) colStatus = 7;      // Столбец H
  if (headerRowIndex === -1) headerRowIndex = 6; // Предположим, заголовки на 6 строке

  const dataStartRow = headerRowIndex + 1;
  if (lastRow < dataStartRow) {
    // Если данных под заголовками нет, просто проверим саму таблицу
    if (lastRow < headerRowIndex) return { success: false, error: 'Таблица слишком короткая' };
  }

  const data = sheet.getRange(dataStartRow, 1, Math.max(1, lastRow - dataStartRow + 1), lastCol).getValues();
  let foundRowInSheet = -1;

  for (let i = 0; i < data.length; i++) {
    const cellValue = String(data[i][colDiscordId] || '').trim();
    if (cellValue === payload.discordId) {
      foundRowInSheet = dataStartRow + i;
      break;
    }
  }

  if (foundRowInSheet === -1) {
    return { success: false, error: `ID ${payload.discordId} не найден в столбце ${String.fromCharCode(65 + colDiscordId)}` };
  }

  // Записываем результат
  sheet.getRange(foundRowInSheet, colStatus + 1).setValue(payload.status);
  
  if (colCurator !== -1 && payload.curator) sheet.getRange(foundRowInSheet, colCurator + 1).setValue(payload.curator);
  if (colDate !== -1 && payload.date) sheet.getRange(foundRowInSheet, colDate + 1).setValue(payload.date);

  return { success: true, message: 'Данные в таблице обновлены!', row: foundRowInSheet };
}

function findCol_(row, targets) {
  for (let i = 0; i < row.length; i++) {
    const cell = normalize_(row[i]);
    if (targets.some(t => cell === normalize_(t) || cell.indexOf(normalize_(t)) !== -1)) return i;
  }
  return -1;
}

function normalize_(v) {
  return String(v || '').toLowerCase().replace(/ё/g, 'е').replace(/[^a-zа-я0-9]/g, '').trim();
}

function parseRequestBody_(e) {
  try { return JSON.parse(e.postData.contents); } catch (f) { return null; }
}

function json_(code, obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}
