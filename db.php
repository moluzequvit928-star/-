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
    $pdo->exec("USE `$dbname`");

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

    try {
        $pdo->exec("ALTER TABLE reports ADD COLUMN status VARCHAR(20) DEFAULT 'pending'");
    } catch (PDOException $e) {
    }
    
    try {
        $pdo->exec("ALTER TABLE reports ADD COLUMN candidate_nickname VARCHAR(100) NULL");
    } catch (PDOException $e) {
    }

    try {
        $pdo->exec("ALTER TABLE reports ADD COLUMN rollback_file VARCHAR(255) NULL");
    } catch (PDOException $e) {
    }

    try {
        $pdo->exec("ALTER TABLE reports ADD COLUMN file_type VARCHAR(50) NULL");
    } catch (PDOException $e) {
    }

    try {
        $pdo->exec("ALTER TABLE reports ADD COLUMN file_size INT NULL");
    } catch (PDOException $e) {
    }

    // Таблица пользователей
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        discord_id VARCHAR(100) DEFAULT NULL,
        role VARCHAR(50) DEFAULT 'master',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Синхронизация из users.json (Источник данных для бота)
    $users_json = __DIR__ . '/users.json';
    if (file_exists($users_json)) {
        $users_data = json_decode(file_get_contents($users_json), true);
        if (is_array($users_data)) {
            // Используем REPLACE INTO чтобы обновлять пароли и роли, если бот их изменил
            $insert_stmt = $pdo->prepare("REPLACE INTO users (username, password, discord_id, role) VALUES (?, ?, ?, ?)");
            foreach ($users_data as $uname => $udata) {
                $insert_stmt->execute([
                    $uname,
                    $udata['password'] ?? 'admin123',
                    $udata['discord_id'] ?? 'system',
                    $udata['role'] ?? ($uname === 'admin' ? 'admin' : 'master')
                ]);
            }
        }
    }

} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}
?>