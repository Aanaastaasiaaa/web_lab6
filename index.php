<?php
// index.php - Главный обработчик формы

session_start();
require_once 'functions.php';

// Если GET запрос - показываем форму
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    unset($_SESSION['form_data']);
    unset($_SESSION['errors']);
    unset($_SESSION['success_message']);
    
    // Загружаем данные для формы
    $saved_data = loadFromCookies();
    
    // Если пользователь авторизован - загружаем его данные из БД
    if (isset($_SESSION['user_id'])) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();
        
        if ($user_data) {
            $stmt = $pdo->prepare("SELECT language_id FROM application_languages WHERE application_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $langs = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($langs) {
                $user_data['languages'] = $langs;
            }
            $saved_data = $user_data;
        }
    }
    
    $errors = [];
    $old_data = [];
    include 'form.php';
    exit;
}

// POST запрос - обрабатываем данные
$_SESSION['form_data'] = $_POST;

// Валидация
$errors = validateFormData($_POST);

if (!empty($errors)) {
    setcookie('form_errors', json_encode($errors), 0, '/');
    setcookie('old_data', json_encode($_POST), 0, '/');
    header('Location: index.php');
    exit;
}

// Сохранение в БД
try {
    $pdo = getDB();
    $pdo->beginTransaction();
    
    if (isset($_SESSION['user_id'])) {
        // Редактирование существующей записи
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
            $_SESSION['user_id']
        ]);
        
        // Удаляем старые языки
        $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        $application_id = $_SESSION['user_id'];
        
    } else {
        // Новая запись - генерируем логин/пароль
        $credentials = generateCredentials();
        
        $stmt = $pdo->prepare("
            INSERT INTO applications 
            (full_name, phone, email, birth_date, gender, biography, contract_accepted, login, password_hash) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['full_name'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['birth_date'],
            $_POST['gender'],
            $_POST['biography'],
            isset($_POST['contract_accepted']) ? 1 : 0,
            $credentials['login'],
            password_hash($credentials['password'], PASSWORD_DEFAULT)
        ]);
        
        $application_id = $pdo->lastInsertId();
        
        $_SESSION['new_credentials'] = $credentials;
    }
    
    // Вставляем языки
    $stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
    foreach ($_POST['languages'] as $lang_id) {
        $stmt->execute([$application_id, $lang_id]);
    }
    
    $pdo->commit();
    
    // Сохраняем в Cookies для неавторизованных
    if (!isset($_SESSION['user_id'])) {
        saveToCookies($_POST);
    }
    
    $_SESSION['success_message'] = "Анкета успешно сохранена!";
    header('Location: index.php?save=1');
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    setcookie('form_errors', json_encode(['db' => $e->getMessage()]), 0, '/');
    setcookie('old_data', json_encode($_POST), 0, '/');
    header('Location: index.php');
    exit;
}
?>
