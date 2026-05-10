<?php
$host = getenv('MYSQLHOST') ?: '127.0.0.1';
$port = getenv('MYSQLPORT') ?: '3306';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$dbname = getenv('MYSQLDATABASE') ?: 'futurama';

try {
    // Подключение к MySQL без указания БД (для локального авто-создания)
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // На Railway база уже создается сервисом, но локально это нужно
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Подключаем выбранную БД
    $pdo->exec("USE `$dbname` ");
    $pdo->exec("SET NAMES utf8mb4");

    // Оптимизация: Создаем таблицы только если сессия еще не помечена как "проверенная"
    if (!isset($_SESSION['db_initialized'])) {
        // Таблица отчетов
        $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            master_name VARCHAR(100) NOT NULL,
            candidate_id VARCHAR(50) NOT NULL,
            invited TINYINT(1) DEFAULT 0,
            screenshot_path VARCHAR(255) NOT NULL,
            comment TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // ... другие таблицы (users, warnings, reattestations, sync_stats, supports_current) ...
        // [Код создания остальных таблиц остается, но теперь он под условием]
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            discord_id VARCHAR(100) DEFAULT NULL,
            role VARCHAR(50) DEFAULT 'master',
            added_supports_count INT DEFAULT 0,
            reattestations_count INT DEFAULT 0,
            last_seen DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS warnings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            support_id VARCHAR(100) NOT NULL,
            support_nickname VARCHAR(100) NOT NULL,
            admin_id VARCHAR(100) NOT NULL,
            admin_nickname VARCHAR(100) NOT NULL,
            reason TEXT NOT NULL,
            duration VARCHAR(50) NOT NULL,
            expires_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sync_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            added_count INT DEFAULT 0,
            removed_count INT DEFAULT 0,
            sheet_total INT DEFAULT 0,
            discord_total INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS supports_current (
            discord_id VARCHAR(50) PRIMARY KEY,
            username VARCHAR(100) DEFAULT NULL,
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Синхронизация из users.json (Делаем только 1 раз за сессию)
        $users_json = __DIR__ . '/users.json';
        if (file_exists($users_json)) {
            $users_data = json_decode(file_get_contents($users_json), true);
            if (is_array($users_data)) {
                $insert_stmt = $pdo->prepare("INSERT INTO users (username, password, discord_id, role) VALUES (?, ?, ?, ?) 
                                              ON DUPLICATE KEY UPDATE role = VALUES(role)");
                foreach ($users_data as $uname => $udata) {
                    $insert_stmt->execute([
                        (string)$uname,
                        (string)($udata['password'] ?? 'admin123'),
                        (string)($udata['discord_id'] ?? 'system'),
                        (string)($udata['role'] ?? 'master')
                    ]);
                }
            }
        }
        
        $_SESSION['db_initialized'] = true;
    }

} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}
?>