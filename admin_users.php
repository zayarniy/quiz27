<?php
// admin_users.php - Центральная страница управления пользователями
session_start();

// Проверка авторизации
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
//    header('Location: login.php');
    //exit();
}

$username = $_SESSION['username'] ?? 'Пользователь';

// Подключение к базе данных
$host = 'localhost';
$dbname = 'quiz27';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Получение статистики пользователей
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $newUsersWeek = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM quizzes");
    $totalQuizzes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM game_sessions");
    $totalGames = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch(PDOException $e) {
    $totalUsers = 0;
    $newUsersWeek = 0;
    $totalQuizzes = 0;
    $totalGames = 0;
}
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

        .logout-btn, .back-btn {
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }

        .logout-btn:hover, .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .quick-actions h2 {
            margin-bottom: 20px;
            color: #333;
            font-size: 20px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
        }

        .action-link {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background: #f8f9fa;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .action-link:hover {
            transform: translateX(5px);
            border-color: #667eea;
            background: #f0f0ff;
        }

        .action-icon {
            font-size: 32px;
            margin-right: 15px;
        }

        .action-info {
            flex: 1;
        }

        .action-title {
            font-weight: bold;
            color: #333;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .action-desc {
            font-size: 12px;
            color: #666;
        }

        .action-arrow {
            font-size: 20px;
            color: #667eea;
        }

        /* Users Table */
        .users-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h2 {
            color: #333;
            font-size: 20px;
        }

        .search-box {
            padding: 8px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            width: 250px;
        }

        .users-table {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-admin {
            background: #f56565;
            color: white;
        }

        .badge-user {
            background: #48bb78;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .icon-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .icon-btn.edit {
            background: #4299e1;
            color: white;
        }

        .icon-btn.delete {
            background: #f56565;
            color: white;
        }

        .icon-btn.view {
            background: #48bb78;
            color: white;
        }

        .icon-btn:hover {
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #999;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .page-btn {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            background: white;
            border-radius: 6px;
            cursor: pointer;
        }

        .page-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        @media (max-width: 768px) {
            .section-header {
                flex-direction: column;
                align-items: stretch;
            }
            .search-box {
                width: 100%;
            }
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>👥 Управление пользователями</h1>
            <p>Центральный узел управления пользователями системы</p>
        </div>
        <div class="user-info">
            <span class="username">👤 <?php echo htmlspecialchars($username); ?></span>
            <a href="dashboard.php" class="back-btn">← Панель управления</a>
            <a href="logout.php" class="logout-btn">Выйти</a>
        </div>
    </div>

    <div class="container">
        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo $totalUsers; ?></div>
                <div class="stat-label">Всего пользователей</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🆕</div>
                <div class="stat-value"><?php echo $newUsersWeek; ?></div>
                <div class="stat-label">Новых за неделю</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-value"><?php echo $totalQuizzes; ?></div>
                <div class="stat-label">Всего викторин</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎮</div>
                <div class="stat-value"><?php echo $totalGames; ?></div>
                <div class="stat-label">Проведено игр</div>
            </div>
        </div>

        <!-- Быстрые ссылки -->
        <div class="quick-actions">
            <h2>⚡ Быстрые действия</h2>
            <div class="actions-grid">
                <a href="add_user.php" class="action-link">
                    <div class="action-icon">➕</div>
                    <div class="action-info">
                        <div class="action-title">Добавить пользователя</div>
                        <div class="action-desc">Создание нового аккаунта</div>
                    </div>
                    <div class="action-arrow">→</div>
                </a>
                
                <a href="register.php" class="action-link">
                    <div class="action-icon">📝</div>
                    <div class="action-info">
                        <div class="action-title">Регистрация</div>
                        <div class="action-desc">Страница регистрации для новых пользователей</div>
                    </div>
                    <div class="action-arrow">→</div>
                </a>
                
                <a href="user_roles.php" class="action-link">
                    <div class="action-icon">⚙️</div>
                    <div class="action-info">
                        <div class="action-title">Управление ролями</div>
                        <div class="action-desc">Назначение прав администратора</div>
                    </div>
                    <div class="action-arrow">→</div>
                </a>
                
                <a href="user_activity.php" class="action-link">
                    <div class="action-icon">📊</div>
                    <div class="action-info">
                        <div class="action-title">Активность пользователей</div>
                        <div class="action-desc">Статистика и логи действий</div>
                    </div>
                    <div class="action-arrow">→</div>
                </a>
            </div>
        </div>

        <!-- Список пользователей -->
        <div class="users-section">
            <div class="section-header">
                <h2>📋 Список пользователей</h2>
                <input type="text" id="searchInput" class="search-box" placeholder="🔍 Поиск по логину или email...">
            </div>
            <div class="users-table">
                <table id="usersTable">
                    <thead>
                        <tr>
                            <th></th>
                            <th>ID</th>
                            <th>Логин</th>
                            <th>Email</th>
                            <th>Роль</th>
                            <th>Дата регистрации</th>
                            <th>Викторин</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <tr>
                            <td colspan="8" class="empty-state">Загрузка пользователей...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>

    <script>
        let allUsers = [];
        let currentPage = 1;
        const itemsPerPage = 10;

        // Загрузка пользователей
        async function loadUsers() {
            try {
                const response = await fetch('get_users.php');
                const result = await response.json();
                if (result.success) {
                    allUsers = result.users;
                    renderUsers();
                } else {
                    showError('Ошибка загрузки пользователей');
                }
            } catch (error) {
                // Демо-данные, если сервер не доступен
                allUsers = [
                    { id: 1, username: 'admin', email: 'admin@quiz27.com', role: 'admin', created_at: '2024-01-01 10:00:00', quizzes_count: 5 },
                    { id: 2, username: 'user1', email: 'user1@example.com', role: 'user', created_at: '2024-01-15 14:30:00', quizzes_count: 2 },
                    { id: 3, username: 'user2', email: 'user2@example.com', role: 'user', created_at: '2024-02-01 09:15:00', quizzes_count: 1 },
                    { id: 4, username: 'teacher', email: 'teacher@school.com', role: 'user', created_at: '2024-02-10 16:45:00', quizzes_count: 8 },
                    { id: 5, username: 'student', email: 'student@university.com', role: 'user', created_at: '2024-02-20 11:20:00', quizzes_count: 0 }
                ];
                renderUsers();
            }
        }

        // Рендер пользователей
        function renderUsers() {
            const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
            let filteredUsers = allUsers;
            
            if (searchTerm) {
                filteredUsers = allUsers.filter(user => 
                    user.username.toLowerCase().includes(searchTerm) || 
                    user.email.toLowerCase().includes(searchTerm)
                );
            }
            
            const totalPages = Math.ceil(filteredUsers.length / itemsPerPage);
            const start = (currentPage - 1) * itemsPerPage;
            const paginatedUsers = filteredUsers.slice(start, start + itemsPerPage);
            
            const tbody = document.getElementById('usersTableBody');
            
            if (paginatedUsers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="empty-state">👥 Пользователи не найдены</td></tr>';
                document.getElementById('pagination').innerHTML = '';
                return;
            }
            
            let html = '';
            paginatedUsers.forEach(user => {
                const initials = user.username.substring(0, 2).toUpperCase();
                const date = new Date(user.created_at).toLocaleDateString('ru-RU');
                const roleBadge = user.role === 'admin' 
                    ? '<span class="badge badge-admin">Администратор</span>' 
                    : '<span class="badge badge-user">Пользователь</span>';
                
                html += `
                    <tr>
                        <td><div class="user-avatar">${initials}</div></td>
                        <td>${user.id}</td>
                        <td><strong>${escapeHtml(user.username)}</strong></td>
                        <td>${escapeHtml(user.email)}</td>
                        <td>${roleBadge}</td>
                        <td>${date}</td>
                        <td>${user.quizzes_count || 0}</td>
                        <td class="action-buttons">
                            <a href="edit_user.php?id=${user.id}" class="icon-btn edit">✏️ Ред.</a>
                            <a href="user_quizzes.php?id=${user.id}" class="icon-btn view">📚 Викторины</a>
                            <button onclick="deleteUser(${user.id})" class="icon-btn delete">🗑️ Удалить</button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
            
            // Пагинация
            let paginationHtml = '';
            for (let i = 1; i <= totalPages; i++) {
                paginationHtml += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
            }
            document.getElementById('pagination').innerHTML = paginationHtml;
        }

        function goToPage(page) {
            currentPage = page;
            renderUsers();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showError(msg) {
            const tbody = document.getElementById('usersTableBody');
            tbody.innerHTML = `<tr><td colspan="8" class="empty-state">❌ ${msg}</td></tr>`;
        }

        function deleteUser(id) {
            if (confirm('Вы уверены, что хотите удалить этого пользователя? Все его викторины и данные будут удалены безвозвратно.')) {
                // Здесь будет AJAX запрос на удаление
                alert('Функция удаления будет доступна в следующей версии');
            }
        }

        // Поиск
        if (document.getElementById('searchInput')) {
            document.getElementById('searchInput').addEventListener('input', () => {
                currentPage = 1;
                renderUsers();
            });
        }

        // Загрузка при старте
        loadUsers();
    </script>
</body>
</html>