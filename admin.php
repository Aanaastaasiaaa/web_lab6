<?php
session_start();

// Подключение к БД
$db_host = 'localhost';
$db_user = 'u82277';
$db_pass = '1452026';
$db_name = 'u82277';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// HTTP-авторизация администратора
$admin_login = 'admin';
$admin_password_hash = password_hash('admin123', PASSWORD_DEFAULT);

// Проверка авторизации
if (!isset($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] !== $admin_login || 
    !password_verify($_SERVER['PHP_AUTH_PW'], $admin_password_hash)) {
    
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Для доступа к админ-панели необходима авторизация.';
    exit;
}

// Обработка удаления записи
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        
        // Удаляем связи с языками
        $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
        $stmt->execute([$id]);
        
        // Удаляем саму заявку
        $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        $success_message = "Запись #$id успешно удалена";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Ошибка удаления: " . $e->getMessage();
    }
}

// Обработка редактирования записи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id = (int)$_POST['edit_id'];
    $errors = [];
    
    // Валидация
    if (empty($_POST['full_name'])) {
        $errors['full_name'] = 'ФИО обязательно';
    } elseif (strlen($_POST['full_name']) > 150) {
        $errors['full_name'] = 'ФИО не более 150 символов';
    }
    
    if (empty($_POST['phone'])) {
        $errors['phone'] = 'Телефон обязателен';
    } elseif (!preg_match('/^[\+\d\s\-\(\)]{6,20}$/', $_POST['phone'])) {
        $errors['phone'] = 'Неверный формат телефона';
    }
    
    if (empty($_POST['email'])) {
        $errors['email'] = 'Email обязателен';
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Неверный формат email';
    }
    
    if (empty($_POST['birth_date'])) {
        $errors['birth_date'] = 'Дата рождения обязательна';
    }
    
    if (empty($_POST['gender'])) {
        $errors['gender'] = 'Пол обязателен';
    }
    
    if (empty($_POST['languages']) || !is_array($_POST['languages'])) {
        $errors['languages'] = 'Выберите хотя бы один язык';
    }
    
    if (empty($_POST['biography'])) {
        $errors['biography'] = 'Биография обязательна';
    } elseif (strlen($_POST['biography']) > 5000) {
        $errors['biography'] = 'Биография не более 5000 символов';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Обновляем основную информацию
            $stmt = $pdo->prepare("
                UPDATE applications 
                SET full_name = ?, phone = ?, email = ?, birth_date = ?, 
                    gender = ?, biography = ?, contract_accepted = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $_POST['full_name'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['birth_date'],
                $_POST['gender'],
                $_POST['biography'],
                isset($_POST['contract_accepted']) ? 1 : 0,
                $id
            ]);
            
            // Удаляем старые языки
            $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
            $stmt->execute([$id]);
            
            // Вставляем новые языки
            $stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
            foreach ($_POST['languages'] as $lang_id) {
                $stmt->execute([$id, $lang_id]);
            }
            
            $pdo->commit();
            $success_message = "Запись #$id успешно обновлена";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Ошибка обновления: " . $e->getMessage();
        }
    } else {
        $error_message = "Исправьте ошибки в форме";
        $edit_errors = $errors;
        $edit_data = $_POST;
        $edit_id = $id;
    }
}

// Получаем все заявки
$applications = [];
$stmt = $pdo->query("
    SELECT a.*, 
           GROUP_CONCAT(pl.name SEPARATOR ', ') as languages
    FROM applications a
    LEFT JOIN application_languages al ON a.id = al.application_id
    LEFT JOIN programming_languages pl ON al.language_id = pl.id
    GROUP BY a.id
    ORDER BY a.id DESC
");
$applications = $stmt->fetchAll();

// Получаем статистику по языкам
$languages_stats = [];
$stmt = $pdo->query("
    SELECT pl.name, COUNT(al.application_id) as count
    FROM programming_languages pl
    LEFT JOIN application_languages al ON pl.id = al.language_id
    GROUP BY pl.id, pl.name
    ORDER BY count DESC
");
$languages_stats = $stmt->fetchAll();

// Получаем общее количество пользователей
$total_users = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();

// Список языков для формы редактирования
$languages_list = $pdo->query("SELECT id, name FROM programming_languages ORDER BY id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - Управление анкетами</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1e1e2e;
            color: #cdd6f4;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: #313244;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            color: #f9e2af;
            font-size: 1.8em;
        }
        
        .stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .stat-card {
            background: #45475a;
            padding: 10px 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-card .number {
            font-size: 1.5em;
            font-weight: bold;
            color: #89b4fa;
        }
        
        .stat-card .label {
            font-size: 0.8em;
            color: #a6adc8;
        }
        
        .section {
            background: #313244;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .section h2 {
            color: #f9e2af;
            margin-bottom: 20px;
            border-bottom: 2px solid #45475a;
            padding-bottom: 10px;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #45475a;
        }
        
        th {
            background: #45475a;
            color: #89b4fa;
            font-weight: 600;
        }
        
        tr:hover {
            background: #45475a;
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-edit {
            background: #f9e2af;
            color: #1e1e2e;
        }
        
        .btn-edit:hover {
            background: #f38ba8;
        }
        
        .btn-delete {
            background: #f38ba8;
            color: #1e1e2e;
        }
        
        .btn-delete:hover {
            background: #fab387;
        }
        
        .btn-save {
            background: #a6e3a1;
            color: #1e1e2e;
        }
        
        .btn-cancel {
            background: #6c7086;
            color: #cdd6f4;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .message-success {
            background: #a6e3a1;
            color: #1e1e2e;
        }
        
        .message-error {
            background: #f38ba8;
            color: #1e1e2e;
        }
        
        .edit-form {
            background: #45475a;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .edit-form h3 {
            margin-bottom: 15px;
            color: #f9e2af;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-group input, 
        .form-group textarea, 
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #6c7086;
            border-radius: 6px;
            background: #1e1e2e;
            color: #cdd6f4;
        }
        
        .form-group select[multiple] {
            height: 100px;
        }
        
        .form-group .error {
            color: #f38ba8;
            font-size: 0.85em;
            margin-top: 5px;
        }
        
        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .stat-item {
            background: #45475a;
            padding: 15px 20px;
            border-radius: 8px;
            flex: 1;
            min-width: 120px;
            text-align: center;
        }
        
        .stat-item .lang-name {
            font-weight: bold;
            margin-bottom: 8px;
            color: #f9e2af;
        }
        
        .stat-item .lang-count {
            font-size: 1.5em;
            font-weight: bold;
            color: #89b4fa;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            th, td {
                font-size: 0.85em;
                padding: 8px;
            }
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Админ-панель управления анкетами</h1>
            <div class="stats">
                <div class="stat-card">
                    <div class="number"><?= $total_users ?></div>
                    <div class="label">Всего пользователей</div>
                </div>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="message message-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message message-error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        
        <!-- Статистика по языкам -->
        <div class="section">
            <h2>📊 Статистика по языкам программирования</h2>
            <div class="stats-grid">
                <?php foreach ($languages_stats as $stat): ?>
                    <div class="stat-item">
                        <div class="lang-name"><?= htmlspecialchars($stat['name']) ?></div>
                        <div class="lang-count"><?= $stat['count'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Форма редактирования (если открыта) -->
        <?php if (isset($edit_id)): ?>
            <div class="section">
                <h2>✏️ Редактирование записи #<?= $edit_id ?></h2>
                <form method="POST" class="edit-form">
                    <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
                    
                    <div class="form-group">
                        <label>ФИО *</label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($edit_data['full_name'] ?? '') ?>" required>
                        <?php if (isset($edit_errors['full_name'])): ?>
                            <div class="error"><?= $edit_errors['full_name'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>Телефон *</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($edit_data['phone'] ?? '') ?>" required>
                        <?php if (isset($edit_errors['phone'])): ?>
                            <div class="error"><?= $edit_errors['phone'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($edit_data['email'] ?? '') ?>" required>
                        <?php if (isset($edit_errors['email'])): ?>
                            <div class="error"><?= $edit_errors['email'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>Дата рождения *</label>
                        <input type="date" name="birth_date" value="<?= htmlspecialchars($edit_data['birth_date'] ?? '') ?>" required>
                        <?php if (isset($edit_errors['birth_date'])): ?>
                            <div class="error"><?= $edit_errors['birth_date'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>Пол *</label>
                        <select name="gender" required>
                            <option value="male" <?= (($edit_data['gender'] ?? '') == 'male') ? 'selected' : '' ?>>Мужской</option>
                            <option value="female" <?= (($edit_data['gender'] ?? '') == 'female') ? 'selected' : '' ?>>Женский</option>
                        </select>
                        <?php if (isset($edit_errors['gender'])): ?>
                            <div class="error"><?= $edit_errors['gender'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>Языки программирования *</label>
                        <select name="languages[]" multiple required>
                            <?php foreach ($languages_list as $lang): ?>
                                <?php 
                                $selected_langs = explode(',', $edit_data['languages'] ?? '');
                                $is_selected = in_array($lang['name'], $selected_langs);
                                ?>
                                <option value="<?= $lang['id'] ?>" <?= $is_selected ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lang['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($edit_errors['languages'])): ?>
                            <div class="error"><?= $edit_errors['languages'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>Биография *</label>
                        <textarea name="biography" rows="4" required><?= htmlspecialchars($edit_data['biography'] ?? '') ?></textarea>
                        <?php if (isset($edit_errors['biography'])): ?>
                            <div class="error"><?= $edit_errors['biography'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="contract_accepted" value="1" <?= isset($edit_data['contract_accepted']) ? 'checked' : '' ?>>
                            Контракт принят
                        </label>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" class="btn btn-save">💾 Сохранить</button>
                        <a href="admin.php" class="btn btn-cancel">❌ Отмена</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Таблица всех пользователей -->
        <div class="section">
            <h2>📋 Список всех анкет</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ФИО</th>
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Дата рождения</th>
                            <th>Пол</th>
                            <th>Языки</th>
                            <th>Биография</th>
                            <th>Контракт</th>
                            <th>Дата создания</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td><?= $app['id'] ?></td>
                                <td><?= htmlspecialchars($app['full_name']) ?></td>
                                <td><?= htmlspecialchars($app['phone']) ?></td>
                                <td><?= htmlspecialchars($app['email']) ?></td>
                                <td><?= $app['birth_date'] ?></td>
                                <td><?= $app['gender'] == 'male' ? 'Мужской' : 'Женский' ?></td>
                                <td><?= htmlspecialchars($app['languages'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(mb_substr($app['biography'] ?? '', 0, 50)) ?>...</td>
                                <td><?= $app['contract_accepted'] ? '✅ Да' : '❌ Нет' ?></td>
                                <td><?= $app['created_at'] ?></td>
                                <td class="actions">
                                    <a href="?edit=<?= $app['id'] ?>" class="btn btn-edit">✏️ Ред.</a>
                                    <a href="?delete=<?= $app['id'] ?>" class="btn btn-delete" onclick="return confirm('Удалить запись #<?= $app['id'] ?>?')">🗑️ Удал.</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
