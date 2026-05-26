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