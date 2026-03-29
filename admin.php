<?php
session_start();
require_once 'config.php';

// === HTTP АВТОРИЗАЦИЯ ===
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Требуется авторизация';
    exit;
}

$stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE username = ?");
$stmt->execute([$_SERVER['PHP_AUTH_USER']]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($_SERVER['PHP_AUTH_PW'], $admin['password_hash'])) {
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Неверный логин или пароль';
    exit;
}

// === ОБРАБОТКА ДЕЙСТВИЙ ===
// Удаление
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM form_data WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header('Location: admin.php');
    exit;
}

// Редактирование
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $stmt = $pdo->prepare("UPDATE form_data SET name=?, email=?, message=?, language=? WHERE id=?");
    $stmt->execute([
        $_POST['name'],
        $_POST['email'],
        $_POST['message'],
        $_POST['language'],
        $_POST['edit_id']
    ]);
    header('Location: admin.php');
    exit;
}

// Получаем все данные
$data = $pdo->query("SELECT * FROM form_data ORDER BY created_at DESC")->fetchAll();

// Статистика по языкам
$stats = $pdo->query("
    SELECT language, COUNT(*) as count 
    FROM form_data 
    WHERE language IS NOT NULL AND language != ''
    GROUP BY language
")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { margin-bottom: 20px; }
        .stats { background: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .stat-item { display: inline-block; margin: 10px 20px; padding: 10px; background: #4CAF50; color: white; border-radius: 5px; }
        table { width: 100%; background: white; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f0; }
        tr:hover { background: #f9f9f9; }
        .btn { display: inline-block; padding: 5px 10px; text-decoration: none; border-radius: 3px; margin: 2px; }
        .btn-delete { background: #f44336; color: white; }
        .btn-edit { background: #2196F3; color: white; cursor: pointer; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; width: 500px; margin: 50px auto; padding: 20px; border-radius: 5px; }
        input, textarea, select { width: 100%; padding: 8px; margin: 5px 0 15px; border: 1px solid #ddd; border-radius: 3px; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
        .close { float: right; cursor: pointer; font-size: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Панель администратора</h1>
        <p>Вы вошли как: <strong><?php echo htmlspecialchars($_SERVER['PHP_AUTH_USER']); ?></strong></p>
        
        <!-- Статистика -->
        <div class="stats">
            <h3>Статистика по языкам программирования</h3>
            <?php if (empty($stats)): ?>
                <p>Нет данных</p>
            <?php else: ?>
                <?php foreach ($stats as $stat): ?>
                    <div class="stat-item">
                        <?php echo htmlspecialchars($stat['language']); ?>: 
                        <?php echo $stat['count']; ?> чел.
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Таблица с данными -->
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Имя</th>
                    <th>Email</th>
                    <th>Сообщение</th>
                    <th>Язык</th>
                    <th>Дата</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td><?php echo htmlspecialchars(substr($row['message'], 0, 50)); ?></td>
                    <td><?php echo htmlspecialchars($row['language'] ?? '-'); ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($row['created_at'])); ?></td>
                    <td>
                        <button class="btn-edit" onclick="openEdit(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>', '<?php echo addslashes($row['email']); ?>', '<?php echo addslashes($row['message']); ?>', '<?php echo addslashes($row['language']); ?>')">Редактировать</button>
                        <a href="?delete=<?php echo $row['id']; ?>" class="btn-delete btn" onclick="return confirm('Удалить?')">Удалить</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Модальное окно редактирования -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>Редактирование записи</h3>
            <form method="POST">
                <input type="hidden" name="edit_id" id="edit_id">
                <label>Имя:</label>
                <input type="text" name="name" id="edit_name" required>
                
                <label>Email:</label>
                <input type="email" name="email" id="edit_email" required>
                
                <label>Сообщение:</label>
                <textarea name="message" id="edit_message" rows="4" required></textarea>
                
                <label>Любимый язык:</label>
                <select name="language" id="edit_language">
                    <option value="">Не выбран</option>
                    <option>PHP</option>
                    <option>Python</option>
                    <option>JavaScript</option>
                    <option>Java</option>
                    <option>C++</option>
                    <option>C#</option>
                    <option>Go</option>
                    <option>Ruby</option>
                </select>
                
                <button type="submit">Сохранить</button>
            </form>
        </div>
    </div>
    
    <script>
        function openEdit(id, name, email, message, language) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_message').value = message;
            document.getElementById('edit_language').value = language;
            document.getElementById('modal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target == document.getElementById('modal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>
