<?php
require_once 'db.php';

echo "<h2>Users in MySQL Database:</h2>";
try {
    $stmt = $pdo->query("SELECT id, username, password, role FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($users) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Password</th><th>Role</th></tr>";
        foreach ($users as $u) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($u['id']) . "</td>";
            echo "<td>" . htmlspecialchars($u['username']) . "</td>";
            echo "<td>" . htmlspecialchars($u['password']) . "</td>";
            echo "<td>" . htmlspecialchars($u['role']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No users found in database.</p>";
    }
} catch (Exception $e) {
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}

echo "<h2>Content of users.json:</h2>";
$json_path = __DIR__ . '/users.json';
if (file_exists($json_path)) {
    $content = file_get_contents($json_path);
    echo "<pre style='background: #eee; padding: 10px;'>" . htmlspecialchars($content) . "</pre>";
} else {
    echo "<p style='color: red;'>File users.json does not exist!</p>";
}
echo "<br><a href='login.php'>Go to Login</a>";
?>
