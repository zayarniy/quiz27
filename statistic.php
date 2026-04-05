<?php
// statistics.php - заглушка
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика - Quiz27</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .back-btn {
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            text-decoration: none;
        }
        .coming-soon {
            background: white;
            border-radius: 15px;
            padding: 60px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .coming-soon h2 {
            color: #667eea;
            margin-bottom: 20px;
        }
        .coming-soon p {
            color: #666;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📈 Статистика</h1>
            <a href="dashboard.php" class="back-btn">← Назад</a>
        </div>
        <div class="coming-soon">
            <h2>🚧 Модуль в разработке</h2>
            <p>Статистика викторин будет доступна в следующей версии.</p>
            <p style="font-size: 14px;">Функционал: аналитика по викторинам, результаты игроков, графики и отчеты.</p>
        </div>
    </div>
</body>
</html>