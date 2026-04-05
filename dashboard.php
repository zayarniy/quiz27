<?php
// dashboard.php - Панель управления с подключением к БД
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$host = 'localhost';
$dbname = 'quiz27';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Пользователь';

// Получение статистики
try {
    // Всего викторин пользователя
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM quizzes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $totalQuizzes = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Всего проведено игр (по викторинам пользователя)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM game_sessions gs JOIN quizzes q ON gs.quiz_id = q.id WHERE q.user_id = ?");
    $stmt->execute([$user_id]);
    $totalGames = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Всего игроков (уникальных) в играх пользователя
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT gp.player_name) as count FROM game_players gp JOIN game_sessions gs ON gp.session_id = gs.id JOIN quizzes q ON gs.quiz_id = q.id WHERE q.user_id = ?");
    $stmt->execute([$user_id]);
    $totalPlayers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Средний балл (заглушка)
    $avgScore = 78;
    
} catch(PDOException $e) {
    $totalQuizzes = 0;
    $totalGames = 0;
    $totalPlayers = 0;
    $avgScore = 0;
}

// Получение списка викторин
$quizzes = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, description, created_at, updated_at FROM quizzes WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$user_id]);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Подсчет количества слайдов для каждой викторины
    foreach ($quizzes as &$quiz) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM slides WHERE quiz_id = ?");
        $stmt->execute([$quiz['id']]);
        $quiz['slides_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
} catch(PDOException $e) {
    $quizzes = [];
}

// Обработка AJAX запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create_quiz') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($title)) {
                echo json_encode(['success' => false, 'message' => 'Введите название викторины']);
                exit();
            }
            
            $stmt = $pdo->prepare("INSERT INTO quizzes (user_id, title, description, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->execute([$user_id, $title, $description]);
            $quiz_id = $pdo->lastInsertId();
            
            echo json_encode(['success' => true, 'quiz_id' => $quiz_id, 'message' => 'Викторина создана']);
            exit();
        }
        
        if ($action === 'delete_quiz') {
            $quiz_id = (int)$_POST['quiz_id'];
            
            $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ? AND user_id = ?");
            $stmt->execute([$quiz_id, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Викторина удалена']);
            exit();
        }
        
        if ($action === 'get_stats') {
            echo json_encode([
                'success' => true,
                'totalQuizzes' => $totalQuizzes,
                'totalGames' => $totalGames,
                'totalPlayers' => $totalPlayers,
                'avgScore' => $avgScore
            ]);
            exit();
        }
        
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления - Quiz27</title>
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

        .logout-btn {
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Main Container */
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

        /* Modules Grid */
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .module-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }

        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .module-header {
            padding: 30px;
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .module-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .module-header h3 {
            font-size: 20px;
        }

        .module-body {
            padding: 20px;
            color: #666;
            text-align: center;
        }

        .module-body p {
            margin-bottom: 15px;
            font-size: 14px;
        }

        .module-btn {
            display: inline-block;
            padding: 8px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            transition: background 0.3s;
        }

        .module-btn:hover {
            background: #5a67d8;
        }

        /* Quizzes Section */
        .quizzes-section {
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
            font-size: 24px;
        }

        .create-quiz-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .create-quiz-btn:hover {
            transform: translateY(-2px);
        }

        /* Quiz Table */
        .quiz-table {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
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

        .quiz-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .quiz-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }

        .quiz-btn-play {
            background: #48bb78;
            color: white;
        }

        .quiz-btn-play:hover {
            background: #38a169;
        }

        .quiz-btn-edit {
            background: #4299e1;
            color: white;
        }

        .quiz-btn-edit:hover {
            background: #3182ce;
        }

        .quiz-btn-stats {
            background: #ed8936;
            color: white;
        }

        .quiz-btn-stats:hover {
            background: #dd6b20;
        }

        .quiz-btn-delete {
            background: #f56565;
            color: white;
        }

        .quiz-btn-delete:hover {
            background: #e53e3e;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #999;
        }

        /* Modal */
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

        .modal-content h3 {
            margin-bottom: 20px;
            color: #333;
        }

        .modal-content input,
        .modal-content textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .modal-content textarea {
            resize: vertical;
            min-height: 100px;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .modal-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .modal-btn-save {
            background: #667eea;
            color: white;
        }

        .modal-btn-cancel {
            background: #e0e0e0;
            color: #333;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            z-index: 1100;
            animation: slideIn 0.3s ease;
        }

        .toast.success {
            background: #48bb78;
        }

        .toast.error {
            background: #f56565;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .quiz-actions {
                flex-direction: column;
            }
            
            .quiz-btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>🎮 Quiz27</h1>
            <p>Панель управления</p>
        </div>
        <div class="user-info">
            <span class="username">👤 <?php echo htmlspecialchars($username); ?></span>
            <a href="logout.php" class="logout-btn">Выйти</a>
        </div>
    </div>

    <div class="container">
        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value" id="totalQuizzes"><?php echo $totalQuizzes; ?></div>
                <div class="stat-label">Всего викторин</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎯</div>
                <div class="stat-value" id="totalGames"><?php echo $totalGames; ?></div>
                <div class="stat-label">Проведено игр</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value" id="totalPlayers"><?php echo $totalPlayers; ?></div>
                <div class="stat-label">Всего игроков</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-value" id="avgScore"><?php echo $avgScore; ?>%</div>
                <div class="stat-label">Средний балл</div>
            </div>
        </div>

        <!-- Модули -->
        <div class="modules-grid">
            <div class="module-card" onclick="window.location.href='editor.php'">
                <div class="module-header">
                    <div class="module-icon">✏️</div>
                    <h3>Редактор</h3>
                </div>
                <div class="module-body">
                    <p>Создавайте и редактируйте викторины, добавляйте вопросы, изображения и варианты ответов</p>
                    <span class="module-btn">Перейти →</span>
                </div>
            </div>

            <div class="module-card" onclick="window.location.href='statistics.php'">
                <div class="module-header">
                    <div class="module-icon">📈</div>
                    <h3>Статистика</h3>
                </div>
                <div class="module-body">
                    <p>Анализируйте результаты проведенных викторин, отслеживайте прогресс участников</p>
                    <span class="module-btn">Перейти →</span>
                </div>
            </div>

            <div class="module-card" onclick="window.location.href='game_lobby.php'">
                <div class="module-header">
                    <div class="module-icon">🎮</div>
                    <h3>Игра</h3>
                </div>
                <div class="module-body">
                    <p>Запустите викторину и пригласите игроков для участия в реальном времени</p>
                    <span class="module-btn">Перейти →</span>
                </div>
            </div>
        </div>

        <!-- Список викторин -->
        <div class="quizzes-section">
            <div class="section-header">
                <h2>📚 Мои викторины</h2>
                <button class="create-quiz-btn" onclick="openCreateQuizModal()">+ Создать викторину</button>
            </div>
            <div class="quiz-table" id="quizzesList">
                <?php if (empty($quizzes)): ?>
                    <div class="empty-state">
                        <p>📭 У вас пока нет созданных викторин</p>
                        <p style="font-size: 12px; margin-top: 10px;">Нажмите "Создать викторину", чтобы начать</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Описание</th>
                                <th>Слайдов</th>
                                <th>Обновлена</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quizzes as $quiz): ?>
                            <tr data-quiz-id="<?php echo $quiz['id']; ?>">
                                <td><strong><?php echo htmlspecialchars($quiz['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($quiz['description'] ?: '—'); ?></td>
                                <td><?php echo $quiz['slides_count']; ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($quiz['updated_at'])); ?></td>
                                <td class="quiz-actions">
                                    <button class="quiz-btn quiz-btn-play" onclick="playQuiz(<?php echo $quiz['id']; ?>)">🎮 Играть</button>
                                    <button class="quiz-btn quiz-btn-edit" onclick="editQuiz(<?php echo $quiz['id']; ?>)">✏️ Редактировать</button>
                                    <button class="quiz-btn quiz-btn-stats" onclick="viewStats(<?php echo $quiz['id']; ?>)">📊 Статистика</button>
                                    <button class="quiz-btn quiz-btn-delete" onclick="deleteQuiz(<?php echo $quiz['id']; ?>)">🗑️ Удалить</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Модальное окно создания викторины -->
    <div id="createQuizModal" class="modal">
        <div class="modal-content">
            <h3>Создание новой викторины</h3>
            <input type="text" id="quizTitle" placeholder="Название викторины">
            <textarea id="quizDescription" placeholder="Описание викторины (необязательно)"></textarea>
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-cancel" onclick="closeCreateQuizModal()">Отмена</button>
                <button class="modal-btn modal-btn-save" onclick="createQuiz()">Создать</button>
            </div>
        </div>
    </div>

    <script>
        // Создание викторины
        function openCreateQuizModal() {
            document.getElementById('createQuizModal').style.display = 'flex';
        }

        function closeCreateQuizModal() {
            document.getElementById('createQuizModal').style.display = 'none';
            document.getElementById('quizTitle').value = '';
            document.getElementById('quizDescription').value = '';
        }

        async function createQuiz() {
            const title = document.getElementById('quizTitle').value.trim();
            const description = document.getElementById('quizDescription').value.trim();
            
            if (!title) {
                showToast('Введите название викторины', 'error');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'create_quiz');
                formData.append('title', title);
                formData.append('description', description);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('Викторина создана!', 'success');
                    closeCreateQuizModal();
                    // Перенаправляем в редактор
                    window.location.href = 'editor.php?quiz_id=' + data.quiz_id;
                } else {
                    showToast('Ошибка: ' + data.message, 'error');
                }
            } catch (error) {
                showToast('Ошибка при создании викторины', 'error');
            }
        }

        // Действия с викторинами
        function playQuiz(quizId) {
            window.location.href = 'game_host.php?quiz_id=' + quizId;
        }

        function editQuiz(quizId) {
            window.location.href = 'editor.php?quiz_id=' + quizId;
        }

        function viewStats(quizId) {
            window.location.href = 'statistics.php?quiz_id=' + quizId;
        }

        async function deleteQuiz(quizId) {
            if (!confirm('Вы уверены, что хотите удалить эту викторину? Все слайды и результаты будут удалены безвозвратно.')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete_quiz');
                formData.append('quiz_id', quizId);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('Викторина удалена', 'success');
                    // Удаляем строку из таблицы
                    const row = document.querySelector(`tr[data-quiz-id="${quizId}"]`);
                    if (row) row.remove();
                    
                    // Обновляем счетчик
                    const totalQuizzesSpan = document.getElementById('totalQuizzes');
                    let currentCount = parseInt(totalQuizzesSpan.textContent);
                    totalQuizzesSpan.textContent = currentCount - 1;
                    
                    // Если викторин не осталось, показываем пустое состояние
                    const tbody = document.querySelector('#quizzesList tbody');
                    if (tbody && tbody.children.length === 0) {
                        document.getElementById('quizzesList').innerHTML = `
                            <div class="empty-state">
                                <p>📭 У вас пока нет созданных викторин</p>
                                <p style="font-size: 12px; margin-top: 10px;">Нажмите "Создать викторину", чтобы начать</p>
                            </div>
                        `;
                    }
                } else {
                    showToast('Ошибка: ' + data.message, 'error');
                }
            } catch (error) {
                showToast('Ошибка при удалении викторины', 'error');
            }
        }

        // Уведомления
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // Закрытие модального окна по клику вне его
        window.onclick = function(event) {
            const modal = document.getElementById('createQuizModal');
            if (event.target === modal) {
                closeCreateQuizModal();
            }
        }
    </script>
</body>
</html>