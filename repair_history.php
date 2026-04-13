<?php
require_once 'db.php';

// 1. Ищем в журнале записи без Discord ID
$stmt = $pdo->query("SELECT id, nickname FROM staff_events WHERE discord_id = '' OR discord_id IS NULL");
$emptyEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($emptyEvents) . " events without Discord ID.\n";

foreach ($emptyEvents as $event) {
    $nick = $event['nickname'];
    
    // 2. Пытаемся найти этот ID в таблице отчетов (если человек хоть раз что-то отправлял)
    $stmtId = $pdo->prepare("SELECT discord_id FROM reports WHERE master_name = ? AND discord_id != '' LIMIT 1");
    $stmtId->execute([$nick]);
    $foundId = $stmtId->fetchColumn();
    
    if ($foundId) {
        $pdo->prepare("UPDATE staff_events SET discord_id = ? WHERE id = ?")
            ->execute([$foundId, $event['id']]);
        echo "Fixed event for {$nick}: set ID {$foundId}\n";
    } else {
        echo "Could not find ID for {$nick} in reports table.\n";
    }
}

unlink(__FILE__);
?>
