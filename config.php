<?php
$pdo = new PDO('mysql:host=localhost;dbname=web_app;charset=utf8', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Создаем таблицы, если нет
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        login VARCHAR(50) UNIQUE,
        password_hash VARCHAR(255)
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS form_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        name VARCHAR(100),
        email VARCHAR(100),
        message TEXT,
        language VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE,
        password_hash VARCHAR(255)
    )
");

// Добавляем админа admin/123456 если нет
$stmt = $pdo->query("SELECT COUNT(*) FROM admins");
if ($stmt->fetchColumn() == 0) {
    $hash = password_hash('123456', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO admins (username, password_hash) VALUES ('admin', '$hash')");
}
?>
