<?php
// register.php - Скрипт регистрации новых пользователей
session_start();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - Quiz27</title>
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
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .content {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-register {
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

        .btn-register:hover {
            transform: translateY(-2px);
        }

        .alert {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .password-requirements {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 Регистрация</h1>
            <p>Создайте аккаунт для работы с Quiz27</p>
        </div>
        
        <div class="content">
            <div id="message"></div>
            
            <form id="registerForm" onsubmit="return registerUser(event)">
                <div class="form-group">
                    <label>Логин *</label>
                    <input type="text" id="username" required autocomplete="username" minlength="3" maxlength="50">
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" id="email" required autocomplete="email">
                </div>
                
                <div class="form-group">
                    <label>Пароль *</label>
                    <input type="password" id="password" required autocomplete="new-password" minlength="6">
                    <div class="password-requirements">Минимум 6 символов</div>
                </div>
                
                <div class="form-group">
                    <label>Подтверждение пароля *</label>
                    <input type="password" id="password_confirm" required autocomplete="new-password">
                </div>
                
                <button type="submit" class="btn-register">Зарегистрироваться</button>
            </form>
            
            <div class="login-link">
                Уже есть аккаунт? <a href="login.php">Войти</a>
            </div>
        </div>
    </div>

    <script>
        async function registerUser(event) {
            event.preventDefault();
            
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            
            // Валидация
            if (username.length < 3) {
                showMessage('Логин должен содержать минимум 3 символа', 'error');
                return false;
            }
            
            if (!email.includes('@') || !email.includes('.')) {
                showMessage('Введите корректный email адрес', 'error');
                return false;
            }
            
            if (password.length < 6) {
                showMessage('Пароль должен содержать минимум 6 символов', 'error');
                return false;
            }
            
            if (password !== passwordConfirm) {
                showMessage('Пароли не совпадают', 'error');
                return false;
            }
            
            // Отправка данных на сервер
            try {
                const formData = new FormData();
                formData.append('username', username);
                formData.append('email', email);
                formData.append('password', password);
                
                const response = await fetch('register_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showMessage(result.message, 'success');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    showMessage(result.message, 'error');
                }
            } catch (error) {
                showMessage('Ошибка подключения к серверу', 'error');
            }
            
            return false;
        }
        
        function showMessage(msg, type) {
            const messageDiv = document.getElementById('message');
            messageDiv.innerHTML = `<div class="alert alert-${type}">${msg}</div>`;
            setTimeout(() => {
                if (messageDiv.firstChild) {
                    messageDiv.removeChild(messageDiv.firstChild);
                }
            }, 5000);
        }
    </script>
</body>
</html>