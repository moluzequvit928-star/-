<?php
/**
 * Скрипт инициализации базы данных.
 * Запустите его один раз при установке или обновлении структуры БД.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = getenv('MYSQLHOST') ?: '127.0.0.1';
$port = getenv('MYSQLPORT') ?: '3306';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$dbname = getenv('MYSQLDATABASE') ?: 'futurama';

echo "<h2>Настройка базы данных...</h2>";

try {
    // 1. Подключение к MySQL
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 2. Создание БД
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname` ");
    echo "✅ База данных `$dbname` готова.<br>";

    // 3. Создание таблиц
    $tables = [
        "reports" => "CREATE TABLE IF NOT EXISTS reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            master_name VARCHAR(100) NOT NULL,
            candidate_id VARCHAR(50) NOT NULL,
            invited TINYINT(1) DEFAULT 0,
            screenshot_path VARCHAR(255) NOT NULL,
            comment TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "users" => "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            discord_id VARCHAR(100) DEFAULT NULL,
            role VARCHAR(50) DEFAULT 'master',
            added_supports_count INT DEFAULT 0,
            reattestations_count INT DEFAULT 0,
            last_seen DATETIME DEFAULT NULL,
            appointment_date DATE DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "warnings" => "CREATE TABLE IF NOT EXISTS warnings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            support_id VARCHAR(100) NOT NULL,
            support_nickname VARCHAR(100) NOT NULL,
            admin_id VARCHAR(100) NOT NULL,
            admin_nickname VARCHAR(100) NOT NULL,
            reason TEXT NOT NULL,
            duration VARCHAR(50) NOT NULL,
            expires_at DATETIME DEFAULT NULL,
            removed_by_nickname VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "reattestations" => "CREATE TABLE IF NOT EXISTS reattestations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            discord_id VARCHAR(50) NOT NULL,
            discord_nickname VARCHAR(100) NOT NULL,
            curator VARCHAR(100) NOT NULL,
            result VARCHAR(20) NOT NULL,
            answers_json TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "sync_stats" => "CREATE TABLE IF NOT EXISTS sync_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            added_count INT DEFAULT 0,
            removed_count INT DEFAULT 0,
            sheet_total INT DEFAULT 0,
            discord_total INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "supports_current" => "CREATE TABLE IF NOT EXISTS supports_current (
            discord_id VARCHAR(50) PRIMARY KEY,
            username VARCHAR(100) DEFAULT NULL,
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "voice_activity" => "CREATE TABLE IF NOT EXISTS voice_activity (
            id INT AUTO_INCREMENT PRIMARY KEY,
            discord_id VARCHAR(50) NOT NULL,
            channel_id VARCHAR(50) NOT NULL,
            start_time TIMESTAMP NOT NULL,
            end_time TIMESTAMP NULL,
            duration INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (discord_id),
            INDEX (start_time)
        )",
        "active_voice_sessions" => "CREATE TABLE IF NOT EXISTS active_voice_sessions (
            discord_id VARCHAR(50) PRIMARY KEY,
            channel_id VARCHAR(50) NOT NULL,
            start_time TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "support_overall_points" => "CREATE TABLE IF NOT EXISTS support_overall_points (
            discord_id VARCHAR(50) PRIMARY KEY,
            points DECIMAL(5,2) DEFAULT 0.00
        )",
        "support_weekly_scores" => "CREATE TABLE IF NOT EXISTS support_weekly_scores (
            discord_id VARCHAR(50) NOT NULL,
            week_date DATE NOT NULL,
            attended_pt DECIMAL(5,2) DEFAULT NULL,
            positive_reviews DECIMAL(5,2) DEFAULT 0.00,
            extra_points DECIMAL(5,2) DEFAULT 0.00,
            most_active DECIMAL(5,2) DEFAULT 0.00,
            more_than_12_h DECIMAL(5,2) DEFAULT 0.00,
            two_branches DECIMAL(5,2) DEFAULT 0.00,
            night DECIMAL(5,2) DEFAULT 0.00,
            verif DECIMAL(5,2) DEFAULT 0.00,
            PRIMARY KEY (discord_id, week_date)
        )"
    ];

    foreach ($tables as $name => $sql) {
        $pdo->exec($sql);
        echo "✅ Таблица `$name` проверена/создана.<br>";
    }

    // 4. Импорт пользователей из users.json
    $users_json = __DIR__ . '/users.json';
    if (file_exists($users_json)) {
        $users_data = json_decode(file_get_contents($users_json), true);
        if (is_array($users_data)) {
            $insert_stmt = $pdo->prepare("INSERT INTO users (username, password, discord_id, role) VALUES (?, ?, ?, ?) 
                                          ON DUPLICATE KEY UPDATE role = VALUES(role)");
            $count = 0;
            foreach ($users_data as $uname => $udata) {
                $role = $udata['role'] ?? 'master';
                if ($uname === 'admin') $role = 'admin'; // Принудительно админ

                $insert_stmt->execute([
                    (string)$uname,
                    (string)($udata['password'] ?? 'admin123'),
                    (string)($udata['discord_id'] ?? 'system'),
                    (string)$role
                ]);
                $count++;
            }
            echo "✅ Импортировано/обновлено пользователей: $count.<br>";
        }
    }

    echo "<br><strong style='color: green;'>Установка завершена успешно!</strong><br>";
    echo "<a href='index.php'>Перейти на главную</a>";

} catch (Exception $e) {
    echo "<br><strong style='color: red;'>Ошибка: " . $e->getMessage() . "</strong>";
}
