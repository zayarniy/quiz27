<?php
// get_stats.php - Скрипт для получения статистики (AJAX)
header('Content-Type: application/json');

// Подключение к базе данных
require_once 'db.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка подключения к БД']);
    exit();
}

try {
    // Общее количество пользователей
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Новые пользователи за неделю
    $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE created_at >= ?");
    $stmt->execute([$weekAgo]);
    $newUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Всего викторин
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM quizzes");
    $totalQuizzes = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Проведено игр
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM game_sessions");
    $totalGames = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'success' => true,
        'totalUsers' => $totalUsers,
        'newUsers' => $newUsers,
        'totalQuizzes' => $totalQuizzes,
        'totalGames' => $totalGames
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>