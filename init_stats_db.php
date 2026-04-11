<?php
require_once 'db.php';

// История событий (принятия/увольнения)
$pdo->exec("CREATE TABLE IF NOT EXISTS staff_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nickname VARCHAR(100),
    event_type ENUM('added', 'removed'),
    event_date DATE,
    discord_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Актуальный кэш состава для сравнения
$pdo->exec("CREATE TABLE IF NOT EXISTS staff_current_cache (
    nickname VARCHAR(100) PRIMARY KEY,
    discord_id VARCHAR(50),
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

echo "Database initialized successfully.\n";
