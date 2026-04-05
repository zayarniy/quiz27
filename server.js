// server.js - Обновленный сервер с автоматическим завершением вопроса
const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const mysql = require('mysql2/promise');
const cors = require('cors');

const app = express();
app.use(cors());
app.use(express.json());

const server = http.createServer(app);
const io = socketIo(server, {
  cors: {
    origin: "*",
    methods: ["GET", "POST"]
  }
});

// Подключение к базе данных
const dbConfig = {
  host: 'localhost',
  user: 'root',
  password: '',
  database: 'quiz27'
};

let pool;

async function initDB() {
  pool = await mysql.createPool(dbConfig);
  console.log('Connected to MySQL database');
}

// Хранилище активных игр
const games = new Map(); // gameCode -> gameData

class Game {
  constructor(gameCode, quizId, hostId, quizData) {
    this.gameCode = gameCode;
    this.quizId = quizId;
    this.hostId = hostId;
    this.quizData = quizData;
    this.players = new Map(); // playerId -> playerData
    this.status = 'waiting'; // waiting, active, finished
    this.currentSlide = 0;
    this.slideStartTime = null;
    this.timer = null;
    this.answers = new Map(); // playerId -> answerData
    this.scores = new Map(); // playerId -> score
    this.musicEnabled = true;
    this.autoEndSlide = false; // Флаг для автоматического завершения
  }

  addPlayer(playerId, playerName, socketId) {
    this.players.set(playerId, {
      id: playerId,
      name: playerName,
      socketId: socketId,
      connected: true,
      score: 0,
      currentAnswer: null,
      answerTime: null
    });
    this.scores.set(playerId, 0);
    return this.players.get(playerId);
  }

  removePlayer(playerId) {
    this.players.delete(playerId);
    this.scores.delete(playerId);
    this.answers.delete(playerId);
  }

  getPlayersList() {
    return Array.from(this.players.values()).map(p => ({
      id: p.id,
      name: p.name,
      connected: p.connected
    }));
  }

  startGame() {
    this.status = 'active';
    this.currentSlide = 0;
    this.startSlide();
  }

  startSlide() {
    const slide = this.quizData.slides[this.currentSlide];
    if (!slide) {
      this.endGame();
      return;
    }

    this.answers.clear();
    this.autoEndSlide = false;
    this.slideStartTime = Date.now();
    
    // Отправляем вопрос ведущему и игрокам
    io.to(this.gameCode).emit('slide_start', {
      slide: slide,
      duration: slide.duration,
      currentSlide: this.currentSlide + 1,
      totalSlides: this.quizData.slides.length,
      totalPlayers: this.players.size
    });

    // Устанавливаем таймер на окончание слайда
    if (this.timer) clearTimeout(this.timer);
    this.timer = setTimeout(() => {
      if (!this.autoEndSlide) {
        this.endSlide();
      }
    }, slide.duration * 1000);
    
    // Отправляем уведомление ведущему о количестве игроков
    io.to(this.gameCode).emit('players_count_update', { 
      totalPlayers: this.players.size,
      answeredCount: 0
    });
  }

  submitAnswer(playerId, answerIndex) {
    if (this.answers.has(playerId)) return false;
    if (this.status !== 'active') return false;
    
    const slide = this.quizData.slides[this.currentSlide];
    const isCorrect = slide.answers[answerIndex].isCorrect;
    const responseTime = Date.now() - this.slideStartTime;
    
    this.answers.set(playerId, {
      answerIndex,
      isCorrect,
      responseTime
    });
    
    // Проверяем, ответили ли все игроки
    const allAnswered = this.answers.size === this.players.size;
    
    // Отправляем обновление ведущему
    io.to(this.gameCode).emit('players_count_update', { 
      totalPlayers: this.players.size,
      answeredCount: this.answers.size
    });
    
    if (allAnswered && !this.autoEndSlide) {
      // Все игроки ответили - завершаем вопрос
      this.autoEndSlide = true;
      if (this.timer) clearTimeout(this.timer);
      
      // Отправляем уведомление о досрочном завершении
      io.to(this.gameCode).emit('all_answered', { 
        message: 'Все игроки ответили!',
        answeredCount: this.answers.size
      });
      
      // Даем небольшую задержку для показа уведомления
      setTimeout(() => {
        this.endSlide();
      }, 2000);
    }
    
    return { isCorrect, responseTime };
  }

  endSlide() {
    if (this.timer) clearTimeout(this.timer);
    
    const slide = this.quizData.slides[this.currentSlide];
    const points = 100;
    console.log('points:'+points);
    // Сортируем правильные ответы по времени
    const correctAnswers = Array.from(this.answers.entries())
      .filter(([_, answer]) => answer.isCorrect)
      .sort((a, b) => a[1].responseTime - b[1].responseTime);
    
    // Начисляем баллы (первый получает максимальные баллы)
    correctAnswers.forEach(([playerId, answer], index) => {
      const bonus = Math.max(0, 100 - Math.floor(answer.responseTime / 100));
      const earnedPoints = points + bonus;
      console.log("earnedPoints:"+earnedPoints)
      const currentScore = this.scores.get(playerId) || 0;
      this.scores.set(playerId, currentScore + earnedPoints);
      
      const player = this.players.get(playerId);
      if (player) player.score = currentScore + earnedPoints;
    });
    
    // Отправляем результаты
    const results = Array.from(this.players.values()).map(p => ({
      name: p.name,
      score: this.scores.get(p.id) || 0,
      answered: this.answers.has(p.id),
      correct: this.answers.get(p.id)?.isCorrect || false
    }));
    
    io.to(this.gameCode).emit('slide_results', {
      results: results.sort((a, b) => b.score - a.score),
      nextSlide: this.currentSlide + 1 < this.quizData.slides.length,
      currentSlide: this.currentSlide + 1,
      totalSlides: this.quizData.slides.length
    });
    
    // Переход к следующему слайду через 5 секунд
    setTimeout(() => {
      this.currentSlide++;
      if (this.currentSlide < this.quizData.slides.length) {
        this.startSlide();
      } else {
        this.endGame();
      }
    }, 5000);
  }

  endGame() {
    this.status = 'finished';
    
    const finalResults = Array.from(this.players.values())
      .map(p => ({
        name: p.name,
        score: this.scores.get(p.id) || 0
      }))
      .sort((a, b) => b.score - a.score);
    
    io.to(this.gameCode).emit('game_end', { results: finalResults });
    
    // Сохраняем результаты в базу данных
    this.saveResults(finalResults);
    
    // Удаляем игру через 10 минут
    setTimeout(() => {
      games.delete(this.gameCode);
    }, 600000);
  }
  
  async saveResults(results) {
    try {
      if (pool) {
        const stmt = await pool.execute(
          "INSERT INTO game_sessions (quiz_id, session_code, host_user_id, status, created_at) VALUES (?, ?, ?, 'finished', NOW())",
          [this.quizId, this.gameCode, this.hostId]
        );
        const sessionId = stmt[0].insertId;
        
        for (const result of results) {
          await pool.execute(
            "INSERT INTO game_players (session_id, player_name, player_score) VALUES (?, ?, ?)",
            [sessionId, result.name, result.score]
          );
        }
      }
    } catch (error) {
      console.error('Error saving results:', error);
    }
  }
}

// Socket.IO обработчики
io.on('connection', (socket) => {
  console.log('New client connected:', socket.id);
  
  // Создание игры (ведущий)
  socket.on('create_game', async (data) => {
    try {
      const { quizId, userId, quizData } = data;
      const gameCode = generateGameCode();
      
      const game = new Game(gameCode, quizId, userId, quizData);
      games.set(gameCode, game);
      
      socket.join(gameCode);
      socket.gameCode = gameCode;
      socket.isHost = true;
      
      socket.emit('game_created', { gameCode });
      console.log(`Game created: ${gameCode}`);
    } catch (error) {
      console.error('Error creating game:', error);
      socket.emit('error', { message: 'Failed to create game' });
    }
  });
  
  // Подключение игрока
  socket.on('join_game', async (data) => {
    try {
      const { gameCode, playerName } = data;
      const game = games.get(gameCode);
      
      if (!game) {
        socket.emit('join_error', { message: 'Игра не найдена' });
        return;
      }
      
      if (game.status !== 'waiting') {
        socket.emit('join_error', { message: 'Игра уже началась' });
        return;
      }
      
      // Проверка уникальности имени
      const existingPlayer = Array.from(game.players.values()).find(
        p => p.name.toLowerCase() === playerName.toLowerCase()
      );
      
      if (existingPlayer) {
        socket.emit('join_error', { message: 'Игрок с таким именем уже есть' });
        return;
      }
      
      const playerId = socket.id;
      const player = game.addPlayer(playerId, playerName, socket.id);
      
      socket.join(gameCode);
      socket.gameCode = gameCode;
      socket.playerId = playerId;
      
      socket.emit('join_success', { playerId, gameCode });
      
      // Обновляем список игроков для ведущего
      io.to(gameCode).emit('players_update', { players: game.getPlayersList() });
      
      console.log(`Player ${playerName} joined game ${gameCode}`);
    } catch (error) {
      console.error('Error joining game:', error);
      socket.emit('join_error', { message: 'Failed to join game' });
    }
  });
  
  // Переподключение игрока
  socket.on('reconnect_game', async (data) => {
    try {
      const { gameCode, playerId, playerName } = data;
      const game = games.get(gameCode);
      
      if (!game) {
        socket.emit('join_error', { message: 'Игра не найдена' });
        return;
      }
      
      const existingPlayer = game.players.get(playerId);
      if (existingPlayer) {
        existingPlayer.socketId = socket.id;
        existingPlayer.connected = true;
        socket.join(gameCode);
        socket.gameCode = gameCode;
        socket.playerId = playerId;
        
        socket.emit('join_success', { playerId, gameCode });
        io.to(gameCode).emit('players_update', { players: game.getPlayersList() });
        
        // Если игра активна, отправляем текущее состояние
        if (game.status === 'active') {
          socket.emit('game_state', {
            currentSlide: game.currentSlide,
            totalSlides: game.quizData.slides.length
          });
        }
      } else {
        socket.emit('join_error', { message: 'Сессия не найдена' });
      }
    } catch (error) {
      console.error('Error reconnecting:', error);
    }
  });
  
  // Kick player (ведущий)
  socket.on('kick_player', (data) => {
    const { playerId } = data;
    const game = games.get(socket.gameCode);
    
    if (game && socket.isHost) {
      const player = game.players.get(playerId);
      if (player) {
        io.to(player.socketId).emit('kicked');
        game.removePlayer(playerId);
        io.to(socket.gameCode).emit('players_update', { players: game.getPlayersList() });
      }
    }
  });
  
  // Начать игру (ведущий)
  socket.on('start_game', () => {
    const game = games.get(socket.gameCode);
    if (game && socket.isHost && game.status === 'waiting') {
      game.startGame();
    }
  });
  
  // Ответ игрока
  socket.on('submit_answer', (data) => {
    const { answerIndex } = data;
    const game = games.get(socket.gameCode);
    
    if (game && game.status === 'active') {
      const result = game.submitAnswer(socket.playerId, answerIndex);
      if (result) {
        socket.emit('answer_received', { 
          success: true, 
          correct: result.isCorrect,
          responseTime: result.responseTime
        });
      }
    }
  });
  
  // Следующий слайд (ведущий)
  socket.on('next_slide', () => {
    const game = games.get(socket.gameCode);
    if (game && socket.isHost && game.status === 'active') {
      if (game.timer) clearTimeout(game.timer);
      game.endSlide();
    }
  });
  
  // Переключение музыки (ведущий)
  socket.on('toggle_music', (data) => {
    const game = games.get(socket.gameCode);
    if (game && socket.isHost) {
      game.musicEnabled = data.enabled;
      io.to(socket.gameCode).emit('music_toggle', { enabled: game.musicEnabled });
    }
  });
  
  // Отключение
  socket.on('disconnect', () => {
    const game = games.get(socket.gameCode);
    if (game) {
      if (socket.isHost) {
        // Хост отключился - завершаем игру
        io.to(socket.gameCode).emit('host_disconnected');
        games.delete(socket.gameCode);
      } else {
        // Игрок отключился
        const player = game.players.get(socket.playerId);
        if (player) {
          player.connected = false;
          io.to(socket.gameCode).emit('players_update', { players: game.getPlayersList() });
        }
      }
    }
    console.log('Client disconnected:', socket.id);
  });
});

function generateGameCode() {
  const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  let code;
  do {
    code = '';
    for (let i = 0; i < 4; i++) {
      code += characters.charAt(Math.floor(Math.random() * characters.length));
    }
  } while (games.has(code));
  return code;
}

// HTTP маршруты
app.get('/api/game/:code', async (req, res) => {
  const game = games.get(req.params.code);
  if (game) {
    res.json({ exists: true, status: game.status });
  } else {
    res.json({ exists: false });
  }
});

app.post('/api/save_game_result', async (req, res) => {
  try {
    const { quizId, results, sessionCode } = req.body;
    res.json({ success: true });
  } catch (error) {
    res.json({ success: false });
  }
});

// Запуск сервера
const PORT = 3000;
initDB().then(() => {
  server.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`);
  });
});