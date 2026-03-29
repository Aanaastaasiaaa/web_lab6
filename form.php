<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анкета программиста</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(145deg, #fbbf24 0%, #f59e0b 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }
        .container {
            max-width: 820px;
            margin: 0 auto;
            background: #fff9e6;
            border-radius: 20px;
            border: 1px solid #fde68a;
            overflow: hidden;
        }
        .header {
            background: #fffbeb;
            padding: 35px 30px;
            text-align: center;
            border-bottom: 2px solid #fcd34d;
            position: relative;
        }
        .header h1 { color: #92400e; font-size: 2.2em; margin-bottom: 8px; }
        .header p { color: #b45309; font-size: 1.1em; }
        .auth-section {
            position: absolute;
            top: 20px;
            right: 30px;
        }
        .auth-button {
            background: #f59e0b;
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
        }
        .auth-button:hover { background: #d97706; }
        .auth-form {
            background: #fefce8;
            border: 2px solid #fde68a;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .auth-form h3 { color: #92400e; margin-bottom: 15px; }
        .auth-form input {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 2px solid #fde68a;
            border-radius: 8px;
        }
        .auth-form .auth-buttons { display: flex; gap: 10px; }
        .auth-form button {
            flex: 1;
            padding: 10px;
            background: #f59e0b;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .credentials-message {
            background: #f0fdf4;
            color: #166534;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
        }
        .credentials-message h3 { margin-bottom: 15px; }
        .credentials-box { display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; margin-bottom: 15px; }
        .credential-item {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            border: 2px solid #86efac;
        }
        .credential-item strong { display: block; margin-bottom: 8px; }
        .credential-value { font-family: monospace; font-size: 1.2em; }
        .form-content { padding: 35px; background: white; }
        .form-group { margin-bottom: 25px; }
        .form-group.has-error label { color: #dc2626; }
        .form-group.has-error input,
        .form-group.has-error textarea,
        .form-group.has-error select {
            border-color: #dc2626;
            background: #fef2f2;
        }
        label { display: block; margin-bottom: 8px; color: #92400e; font-weight: 600; }
        .required::after { content: " *"; color: #dc2626; }
        .error-message {
            color: #dc2626;
            font-size: 0.85em;
            margin-top: 5px;
            padding: 5px 10px;
            background: #fef2f2;
            border-radius: 6px;
        }
        .global-error {
            background: #fef2f2;
            color: #991b1b;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .success-message {
            background: #f0fdf4;
            color: #166534;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
        }
        input[type="text"], input[type="tel"], input[type="email"], input[type="date"], textarea, select {
            width: 100%;
            padding: 14px;
            border: 2px solid #fde68a;
            border-radius: 12px;
            background: #fefce8;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #f59e0b;
            background: white;
        }
        .radio-group {
            display: flex;
            gap: 25px;
            background: #fefce8;
            padding: 15px;
            border-radius: 12px;
            border: 2px solid #fde68a;
        }
        .radio-option { display: flex; align-items: center; gap: 8px; }
        select[multiple] { height: 200px; }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fefce8;
            padding: 15px;
            border-radius: 12px;
        }
        .hint { font-size: 0.85em; color: #b45309; margin-top: 5px; }
        button[type="submit"] {
            background: #f59e0b;
            color: white;
            padding: 16px;
            font-size: 1.2em;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            width: 100%;
        }
        button[type="submit"]:hover { background: #d97706; }
        @media (max-width: 768px) {
            .form-content { padding: 20px; }
            .auth-section { position: static; margin-top: 15px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Анкета</h1>
            <p><?= isset($_SESSION['user_id']) ? 'Редактирование анкеты' : 'Заполните форму' ?></p>
            <div class="auth-section">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="#login" class="auth-button" onclick="toggleLoginForm()">Войти</a>
                <?php else: ?>
                    <a href="?logout=1" class="auth-button">Выйти</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="form-content">
            <div id="login-form" class="auth-form" style="display: none;">
                <h3>Вход в личный кабинет</h3>
                <form method="POST">
                    <input type="text" name="login" placeholder="Логин" required>
                    <input type="password" name="password" placeholder="Пароль" required>
                    <div class="auth-buttons">
                        <button type="submit" name="login_submit">Войти</button>
                        <button type="button" onclick="toggleLoginForm()">Отмена</button>
                    </div>
                </form>
            </div>
            
            <?php if (isset($_SESSION['new_credentials'])): ?>
                <div class="credentials-message">
                    <h3> Ваши данные для входа</h3>
                    <div class="credentials-box">
                        <div class="credential-item">
                            <strong>Логин</strong>
                            <div class="credential-value"><?= $_SESSION['new_credentials']['login'] ?></div>
                        </div>
                        <div class="credential-item">
                            <strong>Пароль</strong>
                            <div class="credential-value"><?= $_SESSION['new_credentials']['password'] ?></div>
                        </div>
                    </div>
                    <p> Сохраните их! Показываются только один раз.</p>
                </div>
                <?php unset($_SESSION['new_credentials']); ?>
            <?php endif; ?>
            
            <?php if (!empty($errors) && !isset($errors['db'])): ?>
                <div class="global-error">
                    <strong>Исправьте ошибки:</strong>
                    <ul><?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group <?= isset($errors['full_name']) ? 'has-error' : '' ?>">
                    <label class="required">ФИО</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($old_data['full_name'] ?? $saved_data['full_name'] ?? '') ?>" required>
                    <?php if (isset($errors['full_name'])): ?>
                        <div class="error-message"><?= $errors['full_name'] ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group <?= isset($errors['phone']) ? 'has-error' : '' ?>">
                    <label class="required">Телефон</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($old_data['phone'] ?? $saved_data['phone'] ?? '') ?>" required>
                    <?php if (isset($errors['phone'])): ?>
                        <div class="error-message"><?= $errors['phone'] ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
                    <label class="required">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($old_data['email'] ?? $saved_data['email'] ?? '') ?>" required>
                    <?php if (isset($errors['email'])): ?>
                        <div class="error-message"><?= $errors['email'] ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group <?= isset($errors['birth_date']) ? 'has-error' : '' ?>">
                    <label class="required">Дата рождения</label>
                    <input type="date" name="birth_date" value="<?= htmlspecialchars($old_data['birth_date'] ?? $saved_data['birth_date'] ?? '') ?>" required>
                    <?php if (isset($errors['birth_date'])): ?>
                        <div class="error-message"><?= $errors['birth_date'] ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group <?= isset($errors['gender']) ? 'has-error' : '' ?>">
                    <label class="required">Пол</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="gender" value="male" <?= (($old_data['gender'] ?? $saved_data['gender'] ?? '') == 'male') ? 'checked' : '' ?>> Мужской
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="gender" value="female" <?= (($old_data['gender'] ?? $saved_data['gender'] ?? '') == 'female') ? 'checked' : '' ?>> Женский
                        </label>
                    </div>
                    <?php if (isset($errors['gender'])): ?>
                        <div class="error-message"><?= $errors['gender'] ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group <?= isset($errors['languages']) ? 'has-error' : '' ?>">
                    <label class="required">Языки программирования</label>
                    <select name="languages[]" multiple required size="6">
                        <?php
                        $selected = $old_data['languages'] ?? $saved_data['languages'] ?? [];
                        if (!is_array($selected)) $selected = explode(',', $selected);
                        $langs = [1=>'Pascal',2=>'C',3=>'C++',4=>'JavaScript',5=>'PHP',6=>'Python',7=>'Java',8=>'Haskell',9=>'Clojure',10=>'Prolog',11=>'Scala',12=>'Go'];
                        foreach ($langs as $id => $name): ?>
                            <option value="<?= $id ?>" <?= in_array((string)$id, $selected) ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['languages'])): ?>
                        <div class="error-message"><?= $errors['languages'] ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group <?= isset($errors['biography']) ? 'has-error' : '' ?>">
                    <label class="required">Биография</label>
                    <textarea name="biography" rows="5" required><?= htmlspecialchars($old_data['biography'] ?? $saved_data['biography'] ?? '') ?></textarea>
                    <?php if (isset($errors['biography'])): ?>
                        <div class="error-message"><?= $errors['biography'] ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group <?= isset($errors['contract_accepted']) ? 'has-error' : '' ?>">
                    <div class="checkbox-group">
                        <input type="checkbox" name="contract_accepted" value="1" <?= isset($old_data['contract_accepted']) || isset($saved_data['contract_accepted']) ? 'checked' : '' ?>>
                        <label>Я ознакомлен(а) с условиями</label>
                    </div>
                    <?php if (isset($errors['contract_accepted'])): ?>
                        <div class="error-message"><?= $errors['contract_accepted'] ?></div>
                    <?php endif; ?>
                </div>
                
                <button type="submit"><?= isset($_SESSION['user_id']) ? 'Обновить' : 'Сохранить' ?></button>
            </form>
        </div>
    </div>
    
    <script>
    function toggleLoginForm() {
        var form = document.getElementById('login-form');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
    </script>
</body>
</html>
