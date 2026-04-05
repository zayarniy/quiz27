<?php
// admin_users.php - Центральная страница управления пользователями
session_start();

// Проверка авторизации
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'] ?? 'Пользователь';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пользователями - Quiz27</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .logo h1 {
            font-size: 24px;
        }

        .logo p {
            font-size: 12px;
            opacity: 0.9;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .username {
            font-weight: 600;
        }

        .back-btn, .logout-btn {
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }

        .back-btn:hover, .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Breadcrumb */
        .breadcrumb {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .breadcrumb span {
            color: #999;
        }

        /* Page Title */
        .page-title {
            margin-bottom: 30px;
        }

        .page-title h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }

        .page-title p {
            color: #666;
        }

        /* Cards Grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .card-header {
            padding: 25px;
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .card-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .card-header h3 {
            font-size: 20px;
        }

        .card-body {
            padding: 25px;
        }

        .card-body p {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .card-features {
            list-style: none;
            margin-bottom: 20px;
        }

        .card-features li {
            padding: 8px 0;
            color: #555;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-features li:before {
            content: "✓";
            color: #48bb78;
            font-weight: bold;
        }

        .card-link {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            text-align: center;
            width: 100%;
            font-weight: 600;
        }

        .card-link:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }

        /* Stats Section */
        .stats-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-top: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stats-section h3 {
            margin-bottom: 20px;
            color: #333;
            font-size: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .quick-actions h3 {
            margin-bottom: 20px;
            color: #333;
            font-size: 20px;
        }

        .actions-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 12px 24px;
            background: #f0f0f0;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .action-btn:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }

        .action-btn.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* Info Box */
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .info-box p {
            color: #1565c0;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .actions-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>👥 Quiz27</h1>
            <p>Управление пользователями</p>
        </div>
        <div class="user-info">
            <span class="username">👤 <?php echo htmlspecialchars($username); ?></span>
            <a href="dashboard.php" class="back-btn">← Панель управления</a>
            <a href="logout.php" class="logout-btn">Выйти</a>
        </div>
    </div>

    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="dashboard.php">Главная</a> / 
            <span>Управление пользователями</span>
        </div>

        <!-- Page Title -->
        <div class="page-title">
            <h2>👥 Управление пользователями</h2>
            <p>Создавайте, редактируйте и управляйте пользователями системы Quiz27</p>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3>⚡ Быстрые действия</h3>
            <div class="actions-buttons">
                <a href="add_user.php" class="action-btn primary">➕ Добавить пользователя</a>
                <a href="register.php" class="action-btn">📝 Регистрация нового пользователя</a>
                <button class="action-btn" onclick="window.location.reload()">🔄 Обновить</button>
            </div>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <p>💡 <strong>Информация:</strong> Здесь собраны все инструменты для управления пользователями. Вы можете добавлять новых пользователей, просматривать список существующих, а также удалять пользователей при необходимости.</p>
        </div>

        <!-- Cards Grid -->
        <div class="cards-grid">
            <!-- Card 1: Добавление пользователя -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">➕</div>
                    <h3>Добавление пользователя</h3>
                </div>
                <div class="card-body">
                    <p>Полноценная административная панель для управления пользователями системы.</p>
                    <ul class="card-features">
                        <li>Добавление новых пользователей</li>
                        <li>Просмотр списка всех пользователей</li>
                        <li>Удаление пользователей</li>
                        <li>Статистика по пользователям</li>
                        <li>Данные о регистрации</li>
                    </ul>
                    <a href="add_user.php" class="card-link">Перейти к управлению →</a>
                </div>
            </div>

            <!-- Card 2: Регистрация -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">📝</div>
                    <h3>Регистрация</h3>
                </div>
                <div class="card-body">
                    <p>Страница самостоятельной регистрации для новых пользователей.</p>
                    <ul class="card-features">
                        <li>Форма с валидацией</li>
                        <li>Проверка уникальности логина</li>
                        <li>Проверка корректности email</li>
                        <li>Подтверждение пароля</li>
                        <li>Автоматическая проверка</li>
                    </ul>
                    <a href="register.php" class="card-link">Перейти к регистрации →</a>
                </div>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="stats-section">
            <h3>📊 Статистика пользователей</h3>
            <div class="stats-grid" id="statsGrid">
                <div class="stat-item">
                    <div class="stat-number" id="totalUsers">-</div>
                    <div class="stat-label">Всего пользователей</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="newUsers">-</div>
                    <div class="stat-label">Новых за неделю</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="totalQuizzes">-</div>
                    <div class="stat-label">Всего викторин</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="totalGames">-</div>
                    <div class="stat-label">Проведено игр</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Функция для получения статистики через AJAX
        async function loadStats() {
            try {
                const response = await fetch('get_stats.php');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('totalUsers').textContent = data.totalUsers;
                    document.getElementById('newUsers').textContent = data.newUsers;
                    document.getElementById('totalQuizzes').textContent = data.totalQuizzes;
                    document.getElementById('totalGames').textContent = data.totalGames;
                } else {
                    console.error('Ошибка загрузки статистики');
                    setDefaultStats();
                }
            } catch (error) {
                console.error('Ошибка:', error);
                setDefaultStats();
            }
        }
        
        function setDefaultStats() {
            document.getElementById('totalUsers').textContent = '0';
            document.getElementById('newUsers').textContent = '0';
            document.getElementById('totalQuizzes').textContent = '0';
            document.getElementById('totalGames').textContent = '0';
        }
        
        // Загрузка статистики при открытии страницы
        loadStats();
        
        // Обновление статистики каждые 30 секунд
        setInterval(loadStats, 30000);
    </script>
</body>
</html>