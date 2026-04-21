<?php
// admin_users.php - Полное управление пользователями
session_start();

// Подключение к базе данных
require_once 'db.php';

// Проверка авторизации
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_username = $_SESSION['username'];
$message = '';
$messageType = '';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_user') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($username) || empty($email) || empty($password)) {
            $message = 'Заполните все обязательные поля';
            $messageType = 'error';
        } elseif ($password !== $confirm_password) {
            $message = 'Пароли не совпадают';
            $messageType = 'error';
        } elseif (strlen($password) < 6) {
            $message = 'Пароль должен содержать минимум 6 символов';
            $messageType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Некорректный email адрес';
            $messageType = 'error';
        } else {
            try {
                // Проверка на существование
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                
                if ($stmt->rowCount() > 0) {
                    $message = 'Пользователь с таким логином или email уже существует';
                    $messageType = 'error';
                } else {
                    // Создание пользователя
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$username, $email, $password_hash]);
                    
                    $message = 'Пользователь успешно добавлен';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                $message = 'Ошибка: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    elseif ($action === 'edit_user') {
        $user_id = (int)$_POST['user_id'];
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($email)) {
            $message = 'Заполните все обязательные поля';
            $messageType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Некорректный email адрес';
            $messageType = 'error';
        } else {
            try {
                // Проверка на существование другого пользователя с таким логином/email
                $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$username, $email, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $message = 'Пользователь с таким логином или email уже существует';
                    $messageType = 'error';
                } else {
                    // Обновление данных
                    if (!empty($password)) {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password_hash = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$username, $email, $password_hash, $user_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$username, $email, $user_id]);
                    }
                    
                    $message = 'Данные пользователя обновлены';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                $message = 'Ошибка: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    elseif ($action === 'delete_user') {
        $user_id = (int)$_POST['user_id'];
        
        // Не даем удалить самого себя
        if ($user_id === $current_user_id) {
            $message = 'Нельзя удалить собственную учетную запись';
            $messageType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = 'Пользователь удален';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Ошибка: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    elseif ($action === 'change_role') {
        $user_id = (int)$_POST['user_id'];
        $role = $_POST['role'] ?? 'user';
        
        if ($user_id === $current_user_id && $role !== 'admin') {
            $message = 'Нельзя изменить собственную роль';
            $messageType = 'error';
        } else {
            try {
                // Добавляем колонку role, если её нет
                try {
                    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'user'");
                } catch (PDOException $e) {
                    // Колонка уже существует
                }
                
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$role, $user_id]);
                $message = 'Роль пользователя изменена';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Ошибка: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Получение списка пользователей
$users = [];
$totalQuizzes = 0;
$totalGames = 0;

try {
    // Добавляем колонку role, если её нет
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'user'");
    } catch (PDOException $e) {
        // Колонка уже существует
    }
    
    // Получаем пользователей
    $stmt = $pdo->query("SELECT id, username, email, role, created_at, updated_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
    
    // Статистика
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM quizzes");
    $totalQuizzes = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM game_sessions");
    $totalGames = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    $message = 'Ошибка загрузки данных: ' . $e->getMessage();
    $messageType = 'error';
}

// Получение данных пользователя для редактирования
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_user = $stmt->fetch();
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
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

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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

        /* Cards */
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .card h2 {
            margin-bottom: 20px;
            color: #333;
            font-size: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-edit {
            background: #4299e1;
            color: white;
        }

        .btn-delete {
            background: #f56565;
            color: white;
        }

        .btn-role {
            background: #ed8936;
            color: white;
        }

        .alert {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Users Table */
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

        .role-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
        }

        .role-admin {
            background: #667eea;
            color: white;
        }

        .role-user {
            background: #48bb78;
            color: white;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #999;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
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
            <span>👤 <?php echo htmlspecialchars($current_username); ?></span>
            <a href="dashboard.php" class="back-btn">← Панель управления</a>
            <a href="logout.php" class="logout-btn">Выйти</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($users); ?></div>
                <div class="stat-label">Всего пользователей</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalQuizzes; ?></div>
                <div class="stat-label">Всего викторин</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalGames; ?></div>
                <div class="stat-label">Проведено игр</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                        $adminCount = 0;
                        foreach ($users as $u) {
                            if (($u['role'] ?? 'user') === 'admin') $adminCount++;
                        }
                        echo $adminCount;
                    ?>
                </div>
                <div class="stat-label">Администраторов</div>
            </div>
        </div>

        <!-- Форма добавления пользователя -->
        <div class="card">
            <h2>➕ Добавить пользователя</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_user">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Логин *</label>
                        <input type="text" name="username" required minlength="3" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Пароль *</label>
                        <input type="password" name="password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Подтверждение пароля *</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">➕ Добавить пользователя</button>
            </form>
        </div>

        <!-- Редактирование пользователя -->
        <?php if ($edit_user): ?>
        <div class="card">
            <h2>✏️ Редактирование: <?php echo htmlspecialchars($edit_user['username']); ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Логин *</label>
                        <input type="text" name="username" required value="<?php echo htmlspecialchars($edit_user['username']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required value="<?php echo htmlspecialchars($edit_user['email']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Новый пароль (оставьте пустым, чтобы не менять)</label>
                        <input type="password" name="password" minlength="6">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">💾 Сохранить изменения</button>
                <a href="admin_users.php" class="btn" style="background: #e0e0e0; text-decoration: none; margin-left: 10px;">❌ Отмена</a>
            </form>
        </div>
        <?php endif; ?>

        <!-- Список пользователей -->
        <div class="card">
            <h2>📋 Список пользователей</h2>
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <p>Пользователей пока нет</p>
                </div>
            <?php else: ?>
                <div class="users-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Логин</th>
                                <th>Email</th>
                                <th>Роль</th>
                                <th>Дата регистрации</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?> 
                                    <?php if ($user['id'] === $current_user_id): ?>
                                        <span style="color: #667eea;">(Вы)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="role-badge <?php echo ($user['role'] ?? 'user') === 'admin' ? 'role-admin' : 'role-user'; ?>">
                                        <?php echo ($user['role'] ?? 'user') === 'admin' ? '👑 Администратор' : '👤 Пользователь'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                                <td class="actions">
                                    <a href="?edit=<?php echo $user['id']; ?>" class="action-btn btn-edit" style="text-decoration: none;">✏️ Редактировать</a>
                                    
                                    <?php if (($user['role'] ?? 'user') !== 'admin'): ?>
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Назначить пользователя <?php echo htmlspecialchars($user['username']); ?> администратором?');">
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="role" value="admin">
                                        <button type="submit" class="action-btn btn-role">👑 Сделать админом</button>
                                    </form>
                                    <?php elseif (($user['role'] ?? 'user') === 'admin' && $user['id'] !== $current_user_id): ?>
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Снять права администратора с пользователя <?php echo htmlspecialchars($user['username']); ?>?');">
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="role" value="user">
                                        <button type="submit" class="action-btn btn-role">👤 Снять админа</button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($user['id'] !== $current_user_id): ?>
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Удалить пользователя <?php echo htmlspecialchars($user['username']); ?>? Все его викторины и данные будут удалены безвозвратно.');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="action-btn btn-delete">🗑️ Удалить</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>