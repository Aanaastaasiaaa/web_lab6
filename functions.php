<?php
// functions.php - Общие функции (DRY)

require_once 'config.php';

// Валидация данных формы
function validateFormData($data) {
    $errors = [];
    
    // 1. ФИО
    if (empty($data['full_name'])) {
        $errors['full_name'] = 'ФИО обязательно для заполнения';
    } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s-]+$/u', $data['full_name'])) {
        $errors['full_name'] = 'ФИО может содержать только буквы, пробелы и дефисы';
    } elseif (strlen($data['full_name']) > 150) {
        $errors['full_name'] = 'ФИО не должно превышать 150 символов';
    }
    
    // 2. Телефон
    if (empty($data['phone'])) {
        $errors['phone'] = 'Телефон обязателен для заполнения';
    } elseif (!preg_match('/^[\+\d\s\-\(\)]{6,20}$/', $data['phone'])) {
        $errors['phone'] = 'Телефон может содержать только цифры, пробелы, дефисы, скобки и + (6-20 символов)';
    }
    
    // 3. Email
    if (empty($data['email'])) {
        $errors['email'] = 'Email обязателен для заполнения';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный формат email (пример: name@domain.com)';
    }
    
    // 4. Дата рождения
    if (empty($data['birth_date'])) {
        $errors['birth_date'] = 'Дата рождения обязательна';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['birth_date'])) {
        $errors['birth_date'] = 'Дата должна быть в формате ГГГГ-ММ-ДД';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $data['birth_date']);
        if (!$date || $date > new DateTime()) {
            $errors['birth_date'] = 'Некорректная дата';
        }
    }
    
    // 5. Пол
    if (empty($data['gender'])) {
        $errors['gender'] = 'Выберите пол';
    } elseif (!in_array($data['gender'], ['male', 'female'])) {
        $errors['gender'] = 'Некорректное значение пола';
    }
    
    // 6. Языки
    if (empty($data['languages']) || !is_array($data['languages'])) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования';
    } else {
        foreach ($data['languages'] as $lang_id) {
            if (!preg_match('/^[1-9]$|^1[0-2]$/', $lang_id)) {
                $errors['languages'] = 'Выбран недопустимый язык программирования';
                break;
            }
        }
    }
    
    // 7. Биография
    if (empty($data['biography'])) {
        $errors['biography'] = 'Биография обязательна для заполнения';
    } elseif (strlen($data['biography']) > 5000) {
        $errors['biography'] = 'Биография не должна превышать 5000 символов';
    }
    
    // 8. Чекбокс
    if (!isset($data['contract_accepted'])) {
        $errors['contract_accepted'] = 'Необходимо подтвердить ознакомление с контрактом';
    }
    
    return $errors;
}

// Сохранение данных в Cookies на год
function saveToCookies($data) {
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        setcookie("saved_$key", $value, time() + 365*24*60*60, '/');
    }
}

// Загрузка данных из Cookies
function loadFromCookies() {
    $data = [];
    foreach ($_COOKIE as $key => $value) {
        if (strpos($key, 'saved_') === 0) {
            $field = substr($key, 6);
            if ($field === 'languages' && strpos($value, ',') !== false) {
                $data[$field] = explode(',', $value);
            } else {
                $data[$field] = $value;
            }
        }
    }
    return $data;
}

// Генерация логина и пароля
function generateCredentials() {
    $login = 'user_' . bin2hex(random_bytes(4));
    $password = bin2hex(random_bytes(8));
    return ['login' => $login, 'password' => $password];
}
?>
