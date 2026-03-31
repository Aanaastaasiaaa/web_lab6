<?php
// admin.php - Админ-панель

require_once 'functions.php';

// HTTP Basic авторизация
$admin_login = 'admin';
$admin_password = 'admin123';

if (!isset($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] !== $admin_login || 
    $_SERVER['PHP_AUTH_PW'] !== $admin_password) {
    
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Доступ запрещен. Неверный логин или пароль.';
    exit;
}

$pdo = getDB();

// Удаление записи
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
        $stmt->execute([$id]);
        $pdo->commit();
        $success = "Запись #$id удалена";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Ошибка удаления: " . $e->getMessage();
    }
}

// Редактирование записи
$edit_id = null;
$edit_data = null;
$edit_errors = [];

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("
        SELECT a.*, GROUP_CONCAT(al.language_id) as lang_ids
        FROM applications a
        LEFT JOIN application_languages al ON a.id = al.application_id
        WHERE a.id = ?
        GROUP BY a.id
    ");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch();
    
    if ($edit_data) {
        $edit_data['languages'] = explode(',', $edit_data['lang_ids'] ?? '');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $edit_id = (int)$_POST['edit_id'];
    $edit_errors = validateFormData($_POST);
    
    if (empty($edit_errors)) {
        try {
            $pdo->beginTransaction();
            
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
                $edit_id
            ]);
            
            $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
            $stmt->execute([$edit_id]);
            
            $stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
            foreach ($_POST['languages'] as $lang_id) {
                $stmt->execute([$edit_id, $lang_id]);
            }
            
            $pdo->commit();
            $success = "Запись #$edit_id обновлена";
            $edit_id = null;
            $edit_data = null;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Ошибка обновления: " . $e->getMessage();
        }
    } else {
        $edit_data = $_POST;
        $edit_data['id'] = $edit_id;
    }
}

// Получаем все заявки
$applications = $pdo->query("
    SELECT a.*, GROUP_CONCAT(pl.name SEPARATOR ', ') as languages
    FROM applications a
    LEFT JOIN application_languages al ON a.id = al.application_id
    LEFT JOIN programming_languages pl ON al.language_id = pl.id
    GROUP BY a.id
    ORDER BY a.id DESC
")->fetchAll();

// Статистика по языкам
$languages_stats = $pdo->query("
    SELECT pl.name, COUNT(al.application_id) as count
    FROM programming_languages pl
    LEFT JOIN application_languages al ON pl.id = al.language_id
    GROUP BY pl.id
    ORDER BY count DESC
")->fetchAll();

$total_users = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();

$languages_list = $pdo->query("SELECT id, name FROM programming_languages ORDER BY id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            font-size: 1.5em;
            color: #333;
            font-weight: 500;
        }
        
        .stat-card {
            background: #e8f0fe;
            padding: 8px 16px;
            border-radius: 6px;
        }
        
        .stat-card .number {
            font-size: 1.3em;
            font-weight: 600;
            color: #1a73e8;
        }
        
        .stat-card .label {
            font-size: 0.8em;
            color: #5f6368;
        }
        
        .section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            font-size: 1.2em;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
            font-weight: 500;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }
        
        th, td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background: #f8f9fa;
            color: #5f6368;
            font-weight: 500;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 4px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.85em;
            display: inline-block;
        }
        
        .btn-edit {
            background: #e8f0fe;
            color: #1a73e8;
        }
        
        .btn-edit:hover {
            background: #d2e3fc;
        }
        
        .btn-delete {
            background: #fee7e7;
            color: #d93025;
        }
        
        .btn-delete:hover {
            background: #fdd;
        }
        
        .btn-save {
            background: #1a73e8;
            color: white;
        }
        
        .btn-save:hover {
            background: #1557b0;
        }
        
        .btn-cancel {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-cancel:hover {
            background: #d0d0d0;
        }
        
        .message {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .message-success {
            background: #e6f4ea;
            color: #137333;
            border: 1px solid #ceead6;
        }
        
        .message-error {
            background: #fce8e6;
            color: #c5221f;
            border: 1px solid #f9d9d8;
        }
        
        .edit-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
            font-size: 0.9em;
        }
        
        .form-group input, 
        .form-group textarea, 
        .form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9em;
            font-family: inherit;
        }
        
        .form-group select[multiple] {
            height: 100px;
        }
        
        .form-group .error {
            color: #d93025;
            font-size: 0.8em;
            margin-top: 3px;
        }
        
        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .stat-item {
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 6px;
            min-width: 80px;
            text-align: center;
        }
        
        .stat-item .lang-name {
            font-weight: 500;
            font-size: 0.85em;
            color: #5f6368;
        }
        
        .stat-item .lang-count {
            font-size: 1.1em;
            font-weight: 600;
            color: #1a73e8;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            th, td {
                font-size: 0.8em;
                padding: 6px;
            }
            .actions {
                flex-direction: column;
                gap: 4px;
            }
            .stats-grid {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Админ-панель</h1>
            <div class="stat-card">
                <div class="number"><?= $total_users ?></div>
                <div class="label">Всего анкет</div>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="message message-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="message message-error"><?= $error ?></div>
        <?php endif; ?>
        
        <!-- Статистика по языкам -->
        <div class="section">
            <h2>Статистика по языкам</h2>
            <div class="stats-grid">
                <?php foreach ($languages_stats as $stat): ?>
                    <div class="stat-item">
                        <div class="lang-name"><?= htmlspecialchars($stat['name']) ?></div>
                        <div class="lang-count"><?= $stat['count'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Редактирование -->
        <?php if ($edit_id && $edit_data): ?>
            <div class="section">
                <h2>Редактирование записи #<?= $edit_id ?></h2>
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
                            <option value="male" <?= ($edit_data['gender'] ?? '') == 'male' ? 'selected' : '' ?>>Мужской</option>
                            <option value="female" <?= ($edit_data['gender'] ?? '') == 'female' ? 'selected' : '' ?>>Женский</option>
                        </select>
                        <?php if (isset($edit_errors['gender'])): ?>
                            <div class="error"><?= $edit_errors['gender'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>Языки программирования *</label>
                        <select name="languages[]" multiple required>
                            <?php foreach ($languages_list as $lang): ?>
                                <?php $selected = in_array($lang['id'], $edit_data['languages'] ?? []); ?>
                                <option value="<?= $lang['id'] ?>" <?= $selected ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lang['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="hint">Держите Ctrl для выбора нескольких</div>
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
                            <input type="checkbox" name="contract_accepted" value="1" <?= isset($edit_data['contract_accepted']) && $edit_data['contract_accepted'] ? 'checked' : '' ?>>
                            Контракт принят
                        </label>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" class="btn btn-save">Сохранить</button>
                        <a href="admin.php" class="btn btn-cancel">Отмена</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Таблица пользователей -->
        <div class="section">
            <h2>Список анкет</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ФИО</th>
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Дата</th>
                            <th>Пол</th>
                            <th>Языки</th>
                            <th>Биография</th>
                            <th>Контракт</th>
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
                                <td><?= $app['gender'] == 'male' ? 'М' : 'Ж' ?></td>
                                <td><?= htmlspecialchars(substr($app['languages'] ?? '', 0, 40)) ?></td>
                                <td><?= htmlspecialchars(substr($app['biography'] ?? '', 0, 40)) ?>...</td>
                                <td><?= $app['contract_accepted'] ? 'Да' : 'Нет' ?></td>
                                <td class="actions">
                                    <a href="?edit=<?= $app['id'] ?>" class="btn btn-edit">Изменить</a>
                                    <a href="?delete=<?= $app['id'] ?>" class="btn btn-delete" onclick="return confirm('Удалить запись?')">Удалить</a>
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
