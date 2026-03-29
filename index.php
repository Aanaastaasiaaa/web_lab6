<?php
session_start();
require_once 'config.php';

// Функции простые
function validate($data) {
    $errors = [];
    if (empty($data['name'])) $errors[] = 'Имя обязательно';
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email некорректен';
    if (strlen($data['message']) < 10) $errors[] = 'Сообщение минимум 10 символов';
    return $errors;
}

$errors = [];
$success = false;
$credentials = null;

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'message' => trim($_POST['message'] ?? ''),
        'language' => $_POST['language'] ?? ''
    ];
    
    $errors = validate($formData);
    
    if (empty($errors)) {
        // Сохраняем
        $stmt = $pdo->prepare("INSERT INTO form_data (name, email, message, language) VALUES (?, ?, ?, ?)");
        $stmt->execute([$formData['name'], $formData['email'], $formData['message'], $formData['language']]);
        
        // Генерируем логин/пароль при первой отправке
        if (!isset($_COOKIE['form_sent'])) {
            setcookie('form_sent', '1', time() + 86400 * 30);
            $login = 'user_' . rand(10000, 99999);
            $password = substr(md5(rand()), 0, 8);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (login, password_hash) VALUES (?, ?)");
            $stmt->execute([$login, $hash]);
            
            $credentials = ['login' => $login, 'password' => $password];
        }
        $success = true;
    }
}

// Восстанавливаем данные из кук
$cookieToken = $_COOKIE['form_token'] ?? '';
$savedData = ['name' => '', 'email' => '', 'message' => '', 'language' => ''];
if ($cookieToken) {
    $stmt = $pdo->prepare("SELECT name, email, message, language FROM form_data WHERE id = ?");
    $stmt->execute([$cookieToken]);
    $savedData = $stmt->fetch() ?: $savedData;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Форма обратной связи</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Форма обратной связи</h1>
        
        <?php if ($success && $credentials): ?>
            <div class="success">
                <h3>Данные сохранены!</h3>
                <p>Ваши данные для входа:</p>
                <p><strong>Логин:</strong> <?php echo $credentials['login']; ?></p>
                <p><strong>Пароль:</strong> <?php echo $credentials['password']; ?></p>
                <p class="warning">Сохраните их! Они показываются только один раз.</p>
            </div>
        <?php elseif ($success): ?>
            <div class="success">Данные успешно сохранены!</div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Имя:</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? $savedData['name'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? $savedData['email'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Любимый язык программирования:</label>
                <select name="language">
                    <option value="">Выберите</option>
                    <option <?php echo (($_POST['language'] ?? $savedData['language'] ?? '') == 'PHP') ? 'selected' : ''; ?>>PHP</option>
                    <option <?php echo (($_POST['language'] ?? $savedData['language'] ?? '') == 'Python') ? 'selected' : ''; ?>>Python</option>
                    <option <?php echo (($_POST['language'] ?? $savedData['language'] ?? '') == 'JavaScript') ? 'selected' : ''; ?>>JavaScript</option>
                    <option <?php echo (($_POST['language'] ?? $savedData['language'] ?? '') == 'Java') ? 'selected' : ''; ?>>Java</option>
                    <option <?php echo (($_POST['language'] ?? $savedData['language'] ?? '') == 'C++') ? 'selected' : ''; ?>>C++</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Сообщение:</label>
                <textarea name="message" rows="5" required><?php echo htmlspecialchars($_POST['message'] ?? $savedData['message'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit">Отправить</button>
        </form>
        
        <p><a href="admin.php">Админ-панель</a></p>
    </div>
</body>
</html>
