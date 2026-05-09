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

    // Таблица пользователей
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

    try { $pdo->exec("ALTER TABLE users ADD COLUMN added_supports_count INT DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN reattestations_count INT DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN last_seen DATETIME DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN about_me TEXT DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN banner_url VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}

    // Таблица устных предупреждений (устников)
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

    try { $pdo->exec("ALTER TABLE warnings ADD COLUMN removed_by_nickname VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}

    // Таблица архива переаттестаций
    $pdo->exec("CREATE TABLE IF NOT EXISTS reattestations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        discord_id VARCHAR(50) NOT NULL,
        discord_nickname VARCHAR(100) NOT NULL,
        curator VARCHAR(100) NOT NULL,
        result VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Синхронизация из users.json (Источник данных для бота)
    $users_json = __DIR__ . '/users.json';
    if (file_exists($users_json)) {
        $users_data = json_decode(file_get_contents($users_json), true);
        if (is_array($users_data)) {
            // Используем INSERT ... ON DUPLICATE KEY UPDATE, чтобы не затирать banner_url и about_me
            $insert_stmt = $pdo->prepare("INSERT INTO users (username, password, discord_id, role) VALUES (?, ?, ?, ?) 
                                          ON DUPLICATE KEY UPDATE username = VALUES(username), password = VALUES(password), role = VALUES(role)");
            foreach ($users_data as $uname => $udata) {
                $u_id = !empty($udata['discord_id']) ? (string)$udata['discord_id'] : 'system';
                $u_role = $udata['role'] ?? ($uname === 'admin' ? 'admin' : 'master');
                $u_pass = $udata['password'] ?? 'admin123';
                
                $insert_stmt->execute([
                    (string)$uname,
                    (string)$u_pass,
                    (string)$u_id,
                    (string)$u_role
                ]);
            }
        }
    }

} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}
?>