<?php
require_once 'db.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_banned TINYINT(1) DEFAULT 0");
    echo "Column is_banned added successfully!\n";
} catch (PDOException $e) {
    echo "Error or already exists: " . $e->getMessage() . "\n";
}
unlink(__FILE__); // Удаляем себя после выполнения
?>
