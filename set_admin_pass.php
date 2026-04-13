<?php
require_once 'db.php';

$username = 'ronnieemeh_08807';
$password = '568933';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->execute([$hashed_password, $username]);
    
    if ($stmt->rowCount() > 0) {
        echo "Пароль для $username успешно обновлен!";
    } else {
        echo "Пользователь $username не найден в базе данных или пароль уже такой.";
    }
} catch (PDOException $e) {
    echo "Ошибка: " . $e->getMessage();
}
?>
