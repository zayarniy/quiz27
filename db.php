<?php
// config/db.php
// Параметры подключения к базе данных

$db_config = [
    'host' => 'localhost',
    'dbname' => 'quiz27',
    'user' => 'quiz',
    'pass' => 'Quiz2025',
    'charset' => 'utf8mb4'
];

$host = 'localhost';
$dbname = 'quiz27';
$user = 'quiz';
$pass = 'Quiz2025!@#Secure';
$charset = 'utf8mb4'; // ДОБАВЬТЕ ЭТУ СТРОКУ
// Функция для получения PDO подключения
function getDBConnection() {
    global $db_config;
    
    try {
        $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
        $pdo = new PDO($dsn, $db_config['user'], $db_config['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Ошибка подключения к БД: " . $e->getMessage());
    }
}

// Альтернативный вариант с mysqli
function getMySQLiConnection() {
    global $db_config;
    
    $conn = new mysqli(
        $db_config['host'], 
        $db_config['user'], 
        $db_config['pass'], 
        $db_config['dbname']
    );
    
    if ($conn->connect_error) {
        die("Ошибка подключения: " . $conn->connect_error);
    }
    
    $conn->set_charset($db_config['charset']);
    return $conn;
}
?>