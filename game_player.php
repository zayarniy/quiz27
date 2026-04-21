<?php
// game_player.php - Страница игрока во время игры
session_start();

$gameCode = isset($_GET['code']) ? $_GET['code'] : '';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Игра - Quiz27</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .game-container {
            max-width: 800px;
            width: 100%;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .score {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }

        .waiting-area {
            background: white;
            border-radius: 20px;
            padding: 60px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .waiting-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .countdown {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
        }

        .countdown-number {
            font-size: 120px;
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

        .question-area {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .timer {
            text-align: center;
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 20px;
        }

        .answers-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 30px;
        }

        .answer-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 40px;
        }

        .answer-btn:hover:not(:disabled) {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .answer-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .answer-text {
            font-size: 16px;
            margin-top: 10px;
            display: block;
        }

        .results-area {
            background: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
        }

        .final-results {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
        }

        .result-item {
            padding: 15px;
            margin: 10px 0;
            border-radius: 10px;
            animation: slideIn 0.5s ease;
            font-size: 24px;
        }

        .result-item.gold {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
        }

        .result-item.silver {
            background: linear-gradient(135deg, #c0c0c0, #e8e8e8);
        }

        .result-item.bronze {
            background: linear-gradient(135deg, #cd7f32, #e8a870);
        }

        @keyframes slideIn {
            from {
                transform: translateX(-100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
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
    </style>
</head>

<body>
    <div class="game-container">
        <div class="header">
            <div>🎮 Игра: <span id="gameCode"><?php echo htmlspecialchars($gameCode); ?></span></div>
            <div class="score">⭐ <span id="playerScore">0</span></div>
        </div>

        <div id="waitingArea" class="waiting-area">
            <h2>⏳ Ожидание начала игры...</h2>
            <div class="waiting-spinner"></div>
            <p style="margin-top: 20px; color: #666;">Ведущий скоро начнет игру</p>
        </div>

        <div id="countdownArea" class="countdown" style="display: none;">
            <div class="countdown-number" id="countdownNumber">5</div>
            <p>Приготовьтесь!</p>
        </div>

        <div id="gameArea" class="question-area" style="display: none;">
            <div id="timerArea" class="timer"></div>
            <div class="answers-grid" id="answersGrid"></div>
        </div>

        <div id="resultsArea" class="results-area" style="display: none;">
            <h2 id="resultsTitle">📊 Результаты раунда</h2>
            <div id="resultsList"></div>
        </div>

        <div id="finalResultsArea" class="final-results" style="display: none;">
            <h1>🏆 ИТОГИ 🏆</h1>
            <div id="finalResultsList"></div>
        </div>
    </div>
    <div id="answerStatus" style="text-align: center; margin-top: 20px; display: none;">
        <div id="answerStatusText" style="font-size: 18px; font-weight: bold;"></div>
    </div>
    
    <script>
        //const socket = io('http://localhost:3000');
        const socket = io('http://quiz3000.site:3001');
        const gameCode = '<?php echo $gameCode; ?>';
        let playerId = localStorage.getItem('quiz_player_id');
        let playerName = localStorage.getItem('quiz_player_name');
        let hasAnswered = false;
        let currentTimer = null;
        let countdownInterval = null;

        // Проверка наличия данных игрока
        if (!playerId || !playerName) {
            window.location.href = 'game_join.php';
        }

        // Обработка ошибок подключения
        socket.on('connect_error', (error) => {
            showToast('Ошибка подключения к серверу', 'error');
        });

        socket.on('disconnect', () => {
            showToast('Соединение потеряно. Переподключение...', 'info');
        });

        // Подключение к игре
        socket.on('connect', () => {
            socket.emit('reconnect_game', {
                gameCode: gameCode,
                playerId: playerId,
                playerName: playerName
            });
        });

        // Ошибка подключения к игре
        socket.on('join_error', (data) => {
            showToast(data.message, 'error');
            setTimeout(() => {
                window.location.href = 'game_join.php';
            }, 2000);
        });

        // Начало слайда (вопроса)
        socket.on('slide_start', (data) => {
            hasAnswered = false;
            document.getElementById('answerStatus').style.display = 'none';
            document.getElementById('answerStatusText').innerHTML = '';
            document.getElementById('waitingArea').style.display = 'none';
            document.getElementById('resultsArea').style.display = 'none';
            document.getElementById('gameArea').style.display = 'none';
            document.getElementById('countdownArea').style.display = 'block';

            let count = 5;
            document.getElementById('countdownNumber').textContent = count;

            if (countdownInterval) clearInterval(countdownInterval);
            countdownInterval = setInterval(() => {
                count--;
                if (count > 0) {
                    document.getElementById('countdownNumber').textContent = count;
                } else {
                    clearInterval(countdownInterval);
                    document.getElementById('countdownArea').style.display = 'none';
                    showQuestion(data.slide, data.duration);
                }
            }, 1000);
        });

        // Отображение вопроса
        function showQuestion(slide, duration) {
            document.getElementById('gameArea').style.display = 'block';

            const shapes = {
                circle: '●',
                square: '■',
                diamond: '◆',
                star: '★'
            };

            document.getElementById('answersGrid').innerHTML = slide.answers.map((answer, index) => `
                <button class="answer-btn" onclick="submitAnswer(${index})" id="answerBtn${index}">
                    ${shapes[answer.shape]}
                    <span class="answer-text">${escapeHtml(answer.text)}</span>
                </button>
            `).join('');

            let timeLeft = duration;
            document.getElementById('timerArea').textContent = `⏱️ ${timeLeft} сек`;

            if (currentTimer) clearInterval(currentTimer);
            currentTimer = setInterval(() => {
                timeLeft--;
                document.getElementById('timerArea').textContent = `⏱️ ${timeLeft} сек`;
                if (timeLeft <= 0) {
                    clearInterval(currentTimer);
                    disableAnswers();
                }
            }, 1000);
        }

        // Отправка ответа
        function submitAnswer(answerIndex) {
            if (hasAnswered) return;

            hasAnswered = true;
            disableAnswers();

            document.getElementById('answerStatus').style.display = 'block';
            document.getElementById('answerStatusText').innerHTML = '⏳ Ответ отправлен... Ожидание остальных игроков';
            document.getElementById('answerStatusText').style.color = '#000';

            socket.emit('submit_answer', { answerIndex });
        }

        // Блокировка кнопок ответов
        function disableAnswers() {
            for (let i = 0; i < 4; i++) {
                const btn = document.getElementById(`answerBtn${i}`);
                if (btn) btn.disabled = true;
            }
        }

        // Все игроки ответили
        socket.on('all_answered', (data) => {
            showToast(data.message, 'info');
            document.getElementById('answerStatusText').innerHTML = '✅ ' + data.message;
        });

        // Подтверждение получения ответа
        socket.on('answer_received', (data) => {
            if (data.correct) {
                //document.getElementById('answerStatusText').innerHTML = '✓ Правильно!';
                //document.getElementById('answerStatusText').style.color = '#48bb78';
                //showToast('✓ Правильно!', 'success');
            } else {
                //document.getElementById('answerStatusText').innerHTML = '✗ Неправильно';
                //document.getElementById('answerStatusText').style.color = '#f56565';
                //showToast('✗ Неправильно', 'error');
            }
        });

        // Результаты раунда
        socket.on('slide_results', (data) => {
            if (currentTimer) clearInterval(currentTimer);
            
            document.getElementById('answerStatus').style.display = 'none';
            document.getElementById('gameArea').style.display = 'none';
            document.getElementById('resultsArea').style.display = 'block';

            const playerResult = data.results.find(r => r.name === playerName);
            if (playerResult) {
                document.getElementById('playerScore').textContent = playerResult.score;
            }

            const resultsList = document.getElementById('resultsList');
            resultsList.innerHTML = data.results.map(result => `
                <div style="padding: 10px; margin: 5px 0; background: ${result.name === playerName ? '#e3f2fd' : '#f8f9fa'}; border-radius: 10px;">
                    <strong>${escapeHtml(result.name)}</strong> - ${result.score} баллов
                    ${result.name === playerName ? ' 👈 Вы' : ''}
                </div>
            `).join('');

            if (!data.nextSlide) {
                document.getElementById('resultsTitle').textContent = '📊 Результаты раунда (игра продолжается)';
            }
        });

        // Завершение игры
        socket.on('game_end', (data) => {
            if (currentTimer) clearInterval(currentTimer);
            if (countdownInterval) clearInterval(countdownInterval);
            
            document.getElementById('waitingArea').style.display = 'none';
            document.getElementById('gameArea').style.display = 'none';
            document.getElementById('resultsArea').style.display = 'none';
            document.getElementById('answerStatus').style.display = 'none';
            document.getElementById('finalResultsArea').style.display = 'block';

            const finalList = document.getElementById('finalResultsList');
            finalList.innerHTML = ''; // Очищаем предыдущие результаты
            let delay = 0;

            data.results.forEach((result, index) => {
                setTimeout(() => {
                    const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : `${index + 1}.`;
                    const resultDiv = document.createElement('div');
                    resultDiv.className = `result-item ${index === 0 ? 'gold' : index === 1 ? 'silver' : index === 2 ? 'bronze' : ''}`;
                    resultDiv.innerHTML = `${medal} ${escapeHtml(result.name)} - ${result.score} баллов`;
                    finalList.appendChild(resultDiv);

                    if (result.name === playerName) {
                        showToast(`Ваше место: ${index + 1}!`, 'success');
                    }
                }, delay);
                delay += 500;
            });

            // Очищаем localStorage через 10 секунд после окончания игры
            setTimeout(() => {
                localStorage.removeItem('quiz_game_code');
                localStorage.removeItem('quiz_player_id');
                localStorage.removeItem('quiz_player_name');
            }, 10000);
        });

        // Игрок исключен
        socket.on('kicked', () => {
            showToast('Вы были исключены из игры', 'error');
            if (currentTimer) clearInterval(currentTimer);
            if (countdownInterval) clearInterval(countdownInterval);
            localStorage.removeItem('quiz_game_code');
            localStorage.removeItem('quiz_player_id');
            localStorage.removeItem('quiz_player_name');
            setTimeout(() => {
                window.location.href = 'game_join.php';
            }, 2000);
        });

        // Хост отключился
        socket.on('host_disconnected', () => {
            showToast('Ведущий отключился. Игра завершена.', 'error');
            if (currentTimer) clearInterval(currentTimer);
            if (countdownInterval) clearInterval(countdownInterval);
            localStorage.removeItem('quiz_game_code');
            localStorage.removeItem('quiz_player_id');
            localStorage.removeItem('quiz_player_name');
            setTimeout(() => {
                window.location.href = 'game_join.php';
            }, 2000);
        });

        // Вспомогательные функции
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