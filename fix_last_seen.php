<?php
require_once 'db.php';
session_start();

echo "<h3>Диагностика системы активности:</h3>";

// 1. Проверяем/Создаем колонку
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN last_seen DATETIME NULL");
    echo "<p style='color: green;'>✅ Колонка last_seen создана!</p>";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "<p style='color: blue;'>ℹ️ Колонка last_seen уже была в базе.</p>";
    } else {
        echo "<p style='color: red;'>❌ Ошибка БД: " . $e->getMessage() . "</p>";
    }
}

// 2. Проверяем текущую сессию
if (isset($_SESSION['username'])) {
    $user = $_SESSION['username'];
    echo "<p>Текущий пользователь в сессии: <b>$user</b></p>";
    
    $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE username = ?");
    $stmt->execute([$user]);
    
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Время для <b>$user</b> успешно обновлено в базе!</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Запрос прошел, но ни одна строка не изменилась. Возможно, имя пользователя <b>$user</b> не совпадает с тем, что в таблице 'users'.</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Ошибка: Ты не залогинен! Зайди на сайт сначала.</p>";
}

echo "<br><a href='users_manage.php'>Вернуться в управление пользователями</a>";
?>
