<?php
// register_handler.php - Обработчик регистрации
header('Content-Type: application/json');

// Подключение к базе данных
$host = 'localhost';
$dbname = 'quiz27';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка подключения к базе данных']);
    exit();
}

// Получение данных из POST запроса
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Валидация
if (empty($username) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Все поля обязательны для заполнения']);
    exit();
}

if (strlen($username) < 3) {
    echo json_encode(['success' => false, 'message' => 'Логин должен содержать минимум 3 символа']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Некорректный email адрес']);
    exit();
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Пароль должен содержать минимум 6 символов']);
    exit();
}

// Проверка существования пользователя
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    
    if ($stmt->rowCount() > 0) {
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmtCheck = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmtCheck->execute([$username]);
        if ($stmtCheck->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Пользователь с таким логином уже существует']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Пользователь с таким email уже существует']);
        }
        exit();
    }
    
    // Хеширование пароля
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Создание пользователя
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$username, $email, $password_hash]);
    
    echo json_encode(['success' => true, 'message' => 'Регистрация успешна! Сейчас вы будете перенаправлены на страницу входа']);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()]);
}
?>