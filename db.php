<?php
// Если сессия еще не запущена - запускаем (нужно для db_initialized)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = getenv('MYSQLHOST') ?: '127.0.0.1';
$port = getenv('MYSQLPORT') ?: '3306';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$dbname = getenv('MYSQLDATABASE') ?: 'futurama';

try {
    // Подключение к MySQL
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    // Безопасное авто-добавление колонки при первом подключении
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN appointment_date DATE DEFAULT NULL");
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE reattestations ADD COLUMN answers_json TEXT DEFAULT NULL");
    } catch (Exception $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS pt_overrides (
            discord_id VARCHAR(50) NOT NULL,
            log_date DATE NOT NULL,
            status VARCHAR(10) NOT NULL,
            PRIMARY KEY (discord_id, log_date)
        )");
    } catch (Exception $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS active_voice_sessions (
            discord_id VARCHAR(50) PRIMARY KEY,
            channel_id VARCHAR(50) NOT NULL,
            start_time TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS voice_activity (
            id INT AUTO_INCREMENT PRIMARY KEY,
            discord_id VARCHAR(50) NOT NULL,
            channel_id VARCHAR(50) NOT NULL,
            start_time TIMESTAMP NULL,
            end_time TIMESTAMP NULL,
            duration INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (discord_id),
            INDEX (start_time)
        )");
    } catch (Exception $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS voice_cmd_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            requested_by VARCHAR(100) NOT NULL,
            status ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
            shifts VARCHAR(20) NOT NULL DEFAULT '7-9',
            total INT DEFAULT 0,
            success_count INT DEFAULT 0,
            fail_count INT DEFAULT 0,
            log TEXT,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            INDEX (status)
        )");
    } catch (Exception $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS voice_stats_weekly (
            discord_id VARCHAR(50) NOT NULL,
            week_start DATE NOT NULL,
            nick VARCHAR(100) DEFAULT NULL,
            mon_seconds INT DEFAULT 0,
            tue_seconds INT DEFAULT 0,
            wed_seconds INT DEFAULT 0,
            thu_seconds INT DEFAULT 0,
            fri_seconds INT DEFAULT 0,
            sat_seconds INT DEFAULT 0,
            sun_seconds INT DEFAULT 0,
            total_seconds INT DEFAULT 0,
            shift VARCHAR(10) DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (discord_id, week_start),
            INDEX (week_start)
        )");
    } catch (Exception $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS support_overall_points (
            discord_id VARCHAR(50) PRIMARY KEY,
            points DECIMAL(5,2) DEFAULT 0.00
        )");
    } catch (Exception $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS support_weekly_scores (
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
        )");
    } catch (Exception $e) {}

    // === СИСТЕМА ПИТОМЦЕВ ===
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS pets (
            discord_id VARCHAR(50) PRIMARY KEY,
            owner_name VARCHAR(100) DEFAULT NULL,
            pet_type VARCHAR(30) NOT NULL DEFAULT 'dog',
            pet_name VARCHAR(50) NOT NULL DEFAULT 'Питомец',
            xp INT NOT NULL DEFAULT 0,
            last_fed DATE DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {}
    // на случай, если таблица pets уже создана без колонки last_fed
    try { $pdo->exec("ALTER TABLE pets ADD COLUMN last_fed DATE DEFAULT NULL"); } catch (Exception $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS pet_quests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(120) NOT NULL,
            description TEXT,
            xp_reward INT NOT NULL DEFAULT 50,
            kind VARCHAR(20) NOT NULL DEFAULT 'custom',
            target_role VARCHAR(20) NOT NULL DEFAULT 'all',
            goal_count INT NOT NULL DEFAULT 1,
            created_by VARCHAR(100) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS pet_quest_progress (
            quest_id INT NOT NULL,
            discord_id VARCHAR(50) NOT NULL,
            progress INT NOT NULL DEFAULT 0,
            completed TINYINT(1) DEFAULT 0,
            rewarded TINYINT(1) DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (quest_id, discord_id)
        )");
    } catch (Exception $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS pet_achievements (
            discord_id VARCHAR(50) NOT NULL,
            achievement_id VARCHAR(40) NOT NULL,
            unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (discord_id, achievement_id)
        )");
    } catch (Exception $e) {}

    // === ИСТОРИЯ СТАФА (ВЫШКИ) ===
    // staff_seen — когда участника впервые увидели при сверке + дата захода с таблицы (для стажа)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS staff_seen (
            discord_id VARCHAR(50) PRIMARY KEY,
            username VARCHAR(100) DEFAULT NULL,
            join_date DATE DEFAULT NULL,
            first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {}
    // staff_history — лог ушедших: роль, стаж, дата ухода
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS staff_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            discord_id VARCHAR(50) NOT NULL,
            username VARCHAR(100) DEFAULT NULL,
            role VARCHAR(50) DEFAULT 'master',
            joined_at DATE DEFAULT NULL,
            left_at DATE DEFAULT NULL,
            days_on_branch INT DEFAULT 0,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {}

    // double_staff — найденные «дабл-стаффы» (роли на других серверах)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS double_staff (
            id INT AUTO_INCREMENT PRIMARY KEY,
            discord_id VARCHAR(50) NOT NULL,
            username VARCHAR(100) DEFAULT NULL,
            guild_name VARCHAR(150) DEFAULT NULL,
            role_name VARCHAR(150) DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (discord_id)
        )");
    } catch (Exception $e) {}

} catch (PDOException $e) {
    // Если базы не существует, попробуем подключиться без нее и создать (только для локалки)
    try {
        $tmp_pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass);
        $tmp_pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (Exception $e2) {
        die("Ошибка подключения к БД: " . $e2->getMessage());
    }
}
?>