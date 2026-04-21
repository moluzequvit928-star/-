// Apps Script: append staff row via JSON webhook
// Deploy as Web App and set the webhook URL in app_config.php
function doPost(e) {
  try {
    var payload = JSON.parse(e.postData.contents || '{}');
    var expectedToken = PropertiesService.getScriptProperties().getProperty('APP_SCRIPT_TOKEN') || 'futika_2026_q7N4vP2xLm8Kc5Rz';
    if (!payload.token || payload.token !== expectedToken) {
      return ContentService.createTextOutput(JSON.stringify({error: 'invalid_token'})).setMimeType(ContentService.MimeType.JSON);
    }

    if (payload.action !== 'append_staff_row') {
      return ContentService.createTextOutput(JSON.stringify({error: 'unknown_action'})).setMimeType(ContentService.MimeType.JSON);
    }

    var ssId = '1w2r_C3R7kh5CDvlehOHOjd3DPnvCMBQ9SnXZnB6t754'; // change if needed
    var targetGid = 1970062457;
    var ss = SpreadsheetApp.openById(ssId);

    // Find sheet by gid (sheetId)
    var sheets = ss.getSheets();
    var sheet = null;
    for (var i = 0; i < sheets.length; i++) {
      if (sheets[i].getSheetId() === targetGid) {
        sheet = sheets[i];
        break;
      }
    }
    if (!sheet) {
      sheet = ss.getActiveSheet();
    }

    // Build row and write into specific columns.
    // Your sheet uses columns B = StartAt, C = Nick, D = DiscordID
    // We'll write: B: StartAt, C: Nick, D: DiscordID, E: Shift, F: AddedBy
    var startAtRaw = payload.start_at || '';
    var startAt = '';
    if (startAtRaw) {
      // try parse ISO datetime
      try { startAt = new Date(startAtRaw); } catch (e) { startAt = startAtRaw; }
    }
    var nick = payload.nick || '';
    var discordId = payload.discord_id || '';
    var shift = (typeof payload.shift !== 'undefined') ? payload.shift : '';
    var addedBy = payload.added_by || '';

    var targetRow = sheet.getLastRow() + 1;
    // Ensure there's at least one header row; if sheet empty, append headers first
    if (targetRow < 2) targetRow = 2;

    // Prepare values array for columns B-F
    var values = [ startAt, nick, discordId, shift, addedBy ];
    // setRange(row, column, numRows, numCols)
    sheet.getRange(targetRow, 2, 1, values.length).setValues([values]);

    return ContentService.createTextOutput(JSON.stringify({ok: true})).setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({error: String(err)})).setMimeType(ContentService.MimeType.JSON);
  }
}

function doGet(e) {
  return ContentService.createTextOutput(JSON.stringify({status: 'ready'})).setMimeType(ContentService.MimeType.JSON);
}

/**
 * Optional helper: set the token in Script Properties via the Apps Script editor
 * from the left menu: Project Settings -> Script Properties -> Add key APP_SCRIPT_TOKEN
 */
