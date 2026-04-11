<?php
require_once 'db.php';

try {
    // Добавляем индексы для ускорения поиска
    $pdo->exec("ALTER TABLE reports ADD INDEX IF NOT EXISTS idx_master (master_name)");
    $pdo->exec("ALTER TABLE reports ADD INDEX IF NOT EXISTS idx_status (status)");
    echo "Индексы успешно добавлены (или уже существовали).\n";
} catch (Exception $e) {
    // В некоторых версиях MySQL/MariaDB синтаксис IF NOT EXISTS не работает для ADD INDEX
    // Пробуем обычный способ
    try {
        $pdo->exec("ALTER TABLE reports ADD INDEX idx_master (master_name)");
        $pdo->exec("ALTER TABLE reports ADD INDEX idx_status (status)");
        echo "Индексы добавлены.\n";
    } catch (Exception $e2) {
        echo "Возможно, индексы уже есть: " . $e2->getMessage() . "\n";
    }
}
?>
