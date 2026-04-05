<?php
// delete_user.php - Скрипт удаления пользователя
session_start();

// Проверка прав администратора
// if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
//     header('Location: login.php');
//     exit();
// }

// Подключение к базе данных
$host = 'localhost';
$dbname = 'quiz27';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? 0;
    
    if ($user_id > 0) {
        try {
            // Удаление пользователя (викторины и ответы удалятся каскадно)
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            header('Location: add_user.php?message=Пользователь удален&type=success');
            exit();
        } catch(PDOException $e) {
            header('Location: add_user.php?message=Ошибка удаления&type=error');
            exit();
        }
    }
}

header('Location: add_user.php');
exit();
?>