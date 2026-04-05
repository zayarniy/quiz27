<?php
// game_host.php - Страница ведущего для создания и проведения игры
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$quiz_id = isset($_GET['quiz_id']) ? (int) $_GET['quiz_id'] : 0;
$user_id = $_SESSION['user_id'];

if (!$quiz_id) {
    header('Location: dashboard.php');
    exit();
}

// Загрузка данных викторины
$host = 'localhost';
$dbname = 'quiz27';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ? AND user_id = ?");
    $stmt->execute([$quiz_id, $user_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        header('Location: dashboard.php');
        exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM slides WHERE quiz_id = ? ORDER BY slide_order");
    $stmt->execute([$quiz_id]);
    $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($slides as &$slide) {
        $stmt = $pdo->prepare("SELECT * FROM answer_options WHERE slide_id = ? ORDER BY option_order");
        $stmt->execute([$slide['id']]);
        $slide['answers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($slide['image_url']) {
            $image_path = 'uploads/' . $quiz_id . '/' . $slide['image_url'];
            if (file_exists($image_path)) {
                $slide['image_url'] = $image_path;
            } else {
                $slide['image_url'] = '';
            }
        }
    }

} catch (PDOException $e) {
    die("Ошибка: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ведущий - Quiz27</title>
    <script src="https://cdn.socket.io/4.5.4/socket.io.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .game-code {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 5px;
        }

        .music-toggle {
            padding: 10px 20px;
            background: #48bb78;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
        }

        /* Players Panel */
        .players-panel {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .players-title {
            font-size: 20px;
            margin-bottom: 15px;
            color: #333;
        }

        .players-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }

        .player-card {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .player-name {
            font-weight: 600;
        }

        .kick-btn {
            background: #f56565;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
        }

        /* Game Area */
        .game-area {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .question {
            font-size: 28px;
            margin-bottom: 20px;
            text-align: center;
        }

        .question-image {
            text-align: center;
            margin-bottom: 20px;
        }

        .question-image img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
        }

        .answers-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 30px;
        }

        .answer-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .answer-card:hover {
            transform: translateY(-5px);
        }

        .answer-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .answer-text {
            font-size: 18px;
        }

        .timer {
            text-align: center;
            font-size: 48px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 20px;
        }

        .countdown {
            text-align: center;
            font-size: 72px;
            font-weight: bold;
            color: #f56565;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .results-area {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-top: 20px;
            display: none;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
        }

        .results-table th,
        .results-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .next-btn {
            margin-top: 20px;
            padding: 15px 30px;
            background: #4299e1;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 18px;
        }

        .start-btn {
            padding: 15px 40px;
            background: #48bb78;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 20px;
            font-weight: bold;
            margin-top: 20px;
        }

        .waiting-area {
            text-align: center;
            padding: 40px;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }

        .toast.success {
            background: #48bb78;
        }

        .toast.error {
            background: #f56565;
        }

        .toast.info {
            background: #4299e1;
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
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div>
                <h2>🎮 <?php echo htmlspecialchars($quiz['title']); ?></h2>
                <p style="color: #666;">Код игры: <strong id="gameCode">...</strong></p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="music-toggle" onclick="toggleMusic()">🎵 Музыка Вкл</button>
                <a href="dashboard.php" class="music-toggle" style="background: #718096; text-decoration: none;">←
                    Выйти</a>
            </div>
        </div>

        <div class="players-panel">
            <div class="players-title">👥 Игроки (<span id="playersCount">0</span>)</div>
            <div class="players-grid" id="playersList"></div>
        </div>
        <div style="margin-top: 15px; padding: 10px; background: #e3f2fd; border-radius: 10px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>📊 Прогресс ответов:</span>
                <span id="answeredProgress">0</span> / <span id="totalPlayersCount">0</span>
            </div>
            <div style="width: 100%; background: #e0e0e0; border-radius: 10px; margin-top: 5px; overflow: hidden;">
                <div id="progressBar"
                    style="width: 0%; height: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); transition: width 0.3s;">
                </div>
            </div>
        </div>
        <div id="waitingArea" class="waiting-area">
            <div class="game-code" style="font-size: 48px; margin-bottom: 20px;" id="displayGameCode">---</div>
            <p style="margin-bottom: 20px;">Скажите этот код игрокам для подключения</p>
            <button class="start-btn" onclick="startGame()">▶ Начать игру</button>
        </div>

        <div id="gameArea" class="game-area">
            <div id="countdownArea" class="countdown" style="display: none;">3</div>
            <div id="timerArea" class="timer" style="display: none;"></div>
            <div id="questionArea">
                <div class="question" id="questionText"></div>
                <div class="question-image" id="questionImage"></div>
                <div class="answers-grid" id="answersGrid"></div>
            </div>
        </div>

        <div id="resultsArea" class="results-area">
            <h3>📊 Результаты раунда</h3>
            <table class="results-table" id="resultsTable">
                <thead>
                    <tr>
                        <th>Игрок</th>
                        <th>Баллы</th>
                        <th>Ответ</th>
                    </tr>
                </thead>
                <tbody id="resultsBody"></tbody>
            </table>
            <button class="next-btn" onclick="nextSlide()">Следующий вопрос →</button>
        </div>
    </div>

    <script>
        const socket = io('http://localhost:3000');
        let gameCode = null;
        let currentSlide = 0;
        let totalSlides = <?php echo count($slides); ?>;
        let quizData = <?php
        $quizData = [
            'id' => $quiz['id'],
            'title' => $quiz['title'],
            'slides' => array_map(function ($slide) {
                return [
                    'id' => $slide['id'],
                    'question' => $slide['question_text'],
                    'image' => $slide['image_url'] ?? '',
                    'duration' => $slide['duration'],
                    'points' => $slide['points'],
                    'answers' => array_map(function ($answer) {
                    return [
                        'text' => $answer['option_text'],
                        'isCorrect' => (bool) $answer['is_correct'],
                        'shape' => $answer['shape_type']
                    ];
                }, $slide['answers'])
                ];
            }, $slides)
        ];
        echo json_encode($quizData);
        ?>;
        let musicEnabled = true;

        // Создание игры
        socket.emit('create_game', {
            quizId: <?php echo $quiz_id; ?>,
            userId: <?php echo $user_id; ?>,
            quizData: quizData
        });

        socket.on('game_created', (data) => {
            gameCode = data.gameCode;
            document.getElementById('gameCode').textContent = gameCode;
            document.getElementById('displayGameCode').textContent = gameCode;
        });

        socket.on('players_update', (data) => {
            const players = data.players;
            const container = document.getElementById('playersList');
            const countSpan = document.getElementById('playersCount');

            countSpan.textContent = players.length;

            if (players.length === 0) {
                container.innerHTML = '<div style="color: #999;">Нет подключенных игроков</div>';
            } else {
                container.innerHTML = players.map(player => `
                    <div class="player-card">
                        <span class="player-name">${escapeHtml(player.name)}</span>
                        <button class="kick-btn" onclick="kickPlayer('${player.id}')">Выгнать</button>
                    </div>
                `).join('');
            }
        });

        socket.on('slide_start', (data) => {
            currentSlide = data.currentSlide;
            showCountdown(data);
        });

        socket.on('slide_results', (data) => {
            showResults(data.results);
        });

        socket.on('game_end', (data) => {
            showFinalResults(data.results);
        });
        socket.on('players_count_update', (data) => {
            document.getElementById('answeredProgress').textContent = data.answeredCount;
            document.getElementById('totalPlayersCount').textContent = data.totalPlayers;
            const percent = (data.answeredCount / data.totalPlayers) * 100;
            document.getElementById('progressBar').style.width = percent + '%';

            if (data.answeredCount === data.totalPlayers && data.totalPlayers > 0) {
                showToast('Все игроки ответили! Переход к следующему вопросу...', 'info');
            }
        });

        socket.on('all_answered', (data) => {
            showToast(data.message, 'success');
            // Блокируем кнопку следующего вопроса на время
            const nextBtn = document.querySelector('.next-btn');
            if (nextBtn) {
                nextBtn.disabled = true;
                setTimeout(() => {
                    nextBtn.disabled = false;
                }, 2000);
            }
        });

        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
        
        function startGame() {
            if (confirm('Начать игру? Все подключенные игроки будут перенаправлены в игру.')) {
                socket.emit('start_game');
                document.getElementById('waitingArea').style.display = 'none';
            }
        }

        function showCountdown(data) {
            document.getElementById('gameArea').style.display = 'block';
            document.getElementById('resultsArea').style.display = 'none';
            document.getElementById('questionArea').style.display = 'none';
            document.getElementById('countdownArea').style.display = 'block';

            let count = 5;
            document.getElementById('countdownArea').textContent = count;

            const interval = setInterval(() => {
                count--;
                if (count > 0) {
                    document.getElementById('countdownArea').textContent = count;
                } else {
                    clearInterval(interval);
                    document.getElementById('countdownArea').style.display = 'none';
                    showSlide(data.slide, data.duration);
                }
            }, 1000);
        }

        function showSlide(slide, duration) {
            document.getElementById('questionArea').style.display = 'block';
            document.getElementById('timerArea').style.display = 'block';

            document.getElementById('questionText').textContent = slide.question;
            document.getElementById('questionText').style.fontSize = slide.fontSize + 'px';
            document.getElementById('questionText').style.color = slide.fontColor;

            if (slide.image) {
                document.getElementById('questionImage').innerHTML = `<img src="${slide.image}" alt="Question image">`;
            } else {
                document.getElementById('questionImage').innerHTML = '';
            }

            const shapes = {
                circle: '●',
                square: '■',
                diamond: '◆',
                star: '★'
            };

            document.getElementById('answersGrid').innerHTML = slide.answers.map((answer, index) => `
                <div class="answer-card">
                    <div class="answer-icon">${shapes[answer.shape]}</div>
                    <div class="answer-text">${escapeHtml(answer.text)}</div>
                </div>
            `).join('');

            let timeLeft = duration;
            document.getElementById('timerArea').textContent = `⏱️ ${timeLeft} сек`;

            const timerInterval = setInterval(() => {
                timeLeft--;
                document.getElementById('timerArea').textContent = `⏱️ ${timeLeft} сек`;
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                }
            }, 1000);

            window.currentTimerInterval = timerInterval;
        }

        function showResults(results) {
            if (window.currentTimerInterval) {
                clearInterval(window.currentTimerInterval);
            }

            document.getElementById('timerArea').style.display = 'none';
            document.getElementById('questionArea').style.display = 'none';
            document.getElementById('resultsArea').style.display = 'block';

            const tbody = document.getElementById('resultsBody');
            tbody.innerHTML = results.map(result => `
                <tr>
                    <td>${escapeHtml(result.name)}</td>
                    <td>${result.score}</td>
                    <td>${result.answered ? (result.correct ? '✓ Правильно' : '✗ Неправильно') : '⏳ Не ответил'}</td>
                </tr>
            `).join('');
        }

        function showFinalResults(results) {
            document.getElementById('gameArea').style.display = 'none';
            document.getElementById('resultsArea').style.display = 'block';

            let html = '<h2>🏆 ИТОГОВЫЕ РЕЗУЛЬТАТЫ 🏆</h2>';
            results.forEach((result, index) => {
                const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : `${index + 1}.`;
                html += `<p style="font-size: 20px; margin: 10px 0;">${medal} ${escapeHtml(result.name)} - ${result.score} баллов</p>`;
            });

            document.getElementById('resultsBody').innerHTML = html;
            document.querySelector('.next-btn').style.display = 'none';

            showToast('Игра завершена!', 'success');
        }

        function nextSlide() {
            socket.emit('next_slide');
        }

        function manualNext() {
            socket.emit('next_slide');
        }

        function kickPlayer(playerId) {
            if (confirm('Выгнать игрока?')) {
                socket.emit('kick_player', { playerId });
            }
        }

        function toggleMusic() {
            musicEnabled = !musicEnabled;
            socket.emit('toggle_music', { enabled: musicEnabled });
            document.querySelector('.music-toggle').textContent = musicEnabled ? '🎵 Музыка Вкл' : '🔇 Музыка Выкл';
        }

        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>

</html>