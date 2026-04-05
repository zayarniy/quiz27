<?php
// game_lobby.php - лобби для ведущего
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
if ($quiz_id == 0) {
    header('Location: dashboard.php');
    exit();
}
$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Лобби викторины - Quiz27</title>
    <script src="https://cdn.socket.io/4.5.4/socket.io.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .code-box {
            background: #f0f0f0;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }
        .code {
            font-size: 48px;
            font-weight: bold;
            letter-spacing: 10px;
            color: #667eea;
            font-family: monospace;
        }
        .players-list {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .player-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        .kick-btn {
            background: #f56565;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
        }
        .music-control {
            margin: 20px 0;
        }
        .start-btn {
            background: #48bb78;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 18px;
            border-radius: 10px;
            cursor: pointer;
            width: 100%;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🎮 Лобби викторины</h1>
        <div class="code-box">
            <p>Код для подключения игроков:</p>
            <div class="code" id="sessionCode">---</div>
        </div>
        <div class="music-control">
            <label>
                <input type="checkbox" id="musicToggle" checked> Включить фоновую музыку
            </label>
        </div>
    </div>
    <div class="players-list">
        <h2>Подключенные игроки (<span id="playersCount">0</span>)</h2>
        <div id="playersContainer"></div>
    </div>
    <button class="start-btn" id="startGameBtn">▶ Начать игру</button>
    <br>
    <a href="dashboard.php" class="back-link">← Вернуться в панель</a>
</div>

<script>
    const socket = io('http://localhost:3000');
    let sessionCode = null;
    let sessionId = null;

    socket.on('connect', () => {
        socket.emit('host_join', { quizId: <?php echo $quiz_id; ?>, userId: <?php echo $user_id; ?> });
    });

    socket.on('host_joined', (data) => {
        sessionCode = data.sessionCode;
        sessionId = data.sessionId;
        document.getElementById('sessionCode').innerText = sessionCode;
        updatePlayers(data.players);
        document.getElementById('musicToggle').checked = data.musicEnabled;
    });

    socket.on('players_update', (players) => {
        updatePlayers(players);
    });

    function updatePlayers(players) {
        const container = document.getElementById('playersContainer');
        const countSpan = document.getElementById('playersCount');
        countSpan.innerText = players.length;
        if (players.length === 0) {
            container.innerHTML = '<p>Нет подключенных игроков</p>';
            return;
        }
        let html = '';
        players.forEach(p => {
            html += `
                <div class="player-item">
                    <span>👤 ${escapeHtml(p.player_name)}</span>
                    <button class="kick-btn" onclick="kickPlayer(${p.id})">Выгнать</button>
                </div>
            `;
        });
        container.innerHTML = html;
    }

    function kickPlayer(playerId) {
        socket.emit('kick_player', { playerId: playerId });
    }

    document.getElementById('musicToggle').addEventListener('change', (e) => {
        socket.emit('toggle_music', { enabled: e.target.checked });
    });

    document.getElementById('startGameBtn').addEventListener('click', () => {
        if (confirm('Начать игру?')) {
            socket.emit('start_game');
        }
    });

    socket.on('game_started', (data) => {
        // Перенаправляем на страницу игры
        window.location.href = `game_play.php?session_code=${sessionCode}&role=host`;
    });

    socket.on('error', (msg) => {
        alert(msg);
    });

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
</script>
</body>
</html>