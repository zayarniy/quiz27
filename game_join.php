<?php
// game_join.php - Страница подключения игрока к игре
session_start();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подключение к игре - Quiz27</title>
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

        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        h1 {
            text-align: center;
            color: #667eea;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            text-align: center;
            letter-spacing: 2px;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
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

        .toast.error { background: #f56565; }
        .toast.success { background: #48bb78; }

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
        <h1>🎮 Подключение к игре</h1>
        <form id="joinForm" onsubmit="return false;">
            <div class="form-group">
                <label>Код игры</label>
                <input type="text" id="gameCode" maxlength="4" placeholder="XXXX" autocomplete="off" style="text-transform: uppercase;">
            </div>
            <div class="form-group">
                <label>Ваше имя</label>
                <input type="text" id="playerName" maxlength="20" placeholder="Введите ваше имя">
            </div>
            <button type="submit" onclick="joinGame()">Подключиться</button>
        </form>
    </div>

    <script>
        //const socket = io('http://localhost:3000');
        const socket = io('http://quiz3000.site:3001');
        let playerId = null;
        let gameCode = null;

        // Проверка переподключения
        const savedGameCode = localStorage.getItem('quiz_game_code');
        const savedPlayerId = localStorage.getItem('quiz_player_id');
        const savedPlayerName = localStorage.getItem('quiz_player_name');

        if (savedGameCode && savedPlayerId && savedPlayerName) {
            socket.emit('reconnect_game', {
                gameCode: savedGameCode,
                playerId: savedPlayerId,
                playerName: savedPlayerName
            });
        }

        function joinGame() {
            const code = document.getElementById('gameCode').value.trim().toUpperCase();
            const name = document.getElementById('playerName').value.trim();

            if (!code || code.length !== 4) {
                showToast('Введите корректный 4-значный код', 'error');
                return;
            }

            if (!name || name.length < 2) {
                showToast('Введите корректное имя (минимум 2 символа)', 'error');
                return;
            }

            socket.emit('join_game', {
                gameCode: code,
                playerName: name
            });
        }

        socket.on('join_success', (data) => {
            playerId = data.playerId;
            gameCode = data.gameCode;
            
            // Сохраняем данные для переподключения
            localStorage.setItem('quiz_game_code', gameCode);
            localStorage.setItem('quiz_player_id', playerId);
            localStorage.setItem('quiz_player_name', document.getElementById('playerName').value);
            
            window.location.href = 'game_player.php?code=' + gameCode;
        });

        socket.on('join_error', (data) => {
            showToast(data.message, 'error');
        });

        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
    </script>
</body>
</html>