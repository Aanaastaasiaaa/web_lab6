<?php
// admin.php - Админ-панель с HTTP-авторизацией

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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1e1e2e;
            color: #cdd6f4;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
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
        .header h1 { color: #f9e2af; }
        .stat-card {
            background: #45475a;
            padding: 10px 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card .number { font-size: 1.5em; font-weight: bold; color: #89b4fa; }
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
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #45475a; }
        th { background: #45475a; color: #89b4fa; }
        tr:hover { background: #45475a; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-edit { background: #f9e2af; color: #1e1e2e; }
        .btn-delete { background: #f38ba8; color: #1e1e2e; }
        .btn-save { background: #a6e3a1; color: #1e1e2e; }
        .btn-cancel { background: #6c7086; color: #cdd6f4; }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .message-success { background: #a6e3a1; color: #1e1e2e; }
        .message-error { background: #f38ba8; color: #1e1e2e; }
        .edit-form {
            background: #45475a;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #6c7086;
            border-radius: 6px;
            background: #1e1e2e;
            color: #cdd6f4;
        }
        .form-group select[multiple] { height: 100px; }
        .form-group .error { color: #f38ba8; font-size: 0.85em; margin-top: 5px; }
        .form-buttons { display: flex; gap: 10px; margin-top: 15px; }
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
            min-width: 100px;
            text-align: center;
        }
        .stat-item .lang-name { font-weight: bold; margin-bottom: 8px; color: #f9e2af; }
        .stat-item .lang-count { font-size: 1.5em; font-weight: bold; color: #89b4fa; }
        @media (max-width: 768px) {
            th, td { font-size: 0.85em; padding: 8px; }
            .actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Админ-панель</h1>
            <div class="stat-card">
                <div class="number"><?= $total_users ?></div>
                <div class="label">Всего анкет</div>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="message message-success">✅ <?= $success ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="message message-error">❌ <?= $error ?></div>
        <?php endif; ?>
        
        <!-- Статистика -->
        <div class="section">
            <h2>📊 Статистика по языкам</h2>
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
                <h2>✏️ Редактирование #<?= $edit_id ?></h2>
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
                        <label>Языки *</label>
                        <select name="languages[]" multiple required>
                            <?php foreach ($languages_list as $lang): ?>
                                <?php $selected = in_array($lang['id'], $edit_data['languages'] ?? []); ?>
                                <option value="<?= $lang['id'] ?>" <?= $selected ? 'selected' : '' ?>>
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
                            <input type="checkbox" name="contract_accepted" value="1" <?= isset($edit_data['contract_accepted']) && $edit_data['contract_accepted'] ? 'checked' : '' ?>>
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
        
        <!-- Таблица пользователей -->
        <div class="section">
            <h2>📋 Список анкет</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>ID</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Дата</th><th>Пол</th><th>Языки</th><th>Биография</th><th>Контракт</th><th>Действия</th></tr>
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
                                <td><?= htmlspecialchars(substr($app['languages'] ?? '', 0, 30)) ?></td>
                                <td><?= htmlspecialchars(substr($app['biography'] ?? '', 0, 40)) ?>...</td>
                                <td><?= $app['contract_accepted'] ? '✅' : '❌' ?></td>
                                <td class="actions">
                                    <a href="?edit=<?= $app['id'] ?>" class="btn btn-edit">✏️</a>
                                    <a href="?delete=<?= $app['id'] ?>" class="btn btn-delete" onclick="return confirm('Удалить?')">🗑️</a>
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
