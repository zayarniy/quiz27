<?php
// test_db.php - Скрипт проверки подключения к базе данных
// Разместите этот файл в корне вашего сайта и откройте в браузере

// Конфигурация базы данных
require_once 'db.php';
// Функция для форматирования вывода
function formatOutput($title, $status, $details = '') {
    $statusClass = $status === 'OK' ? 'success' : 'error';
    $statusText = $status === 'OK' ? '✅ УСПЕШНО' : '❌ ОШИБКА';
    
    echo "<div style='border:1px solid #ddd; margin:10px 0; padding:15px; border-radius:5px;'>";
    echo "<h3 style='margin-top:0; color:#333;'>{$title}</h3>";
    echo "<p style='color:{$statusClass}; font-weight:bold;'>{$statusText}</p>";
    if ($details) {
        echo "<pre style='background:#f5f5f5; padding:10px; border-radius:3px; overflow:auto;'>{$details}</pre>";
    }
    echo "</div>";
}

// Настройка отображения ошибок
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>";
echo "<html><head><title>Тест подключения к БД</title>";
echo "<style>
    body { font-family: monospace; margin: 20px; background: #f0f0f0; }
    .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
    .success { color: #4CAF50; }
    .error { color: #f44336; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #4CAF50; color: white; }
    tr:nth-child(even) { background-color: #f2f2f2; }
</style>";
echo "</head><body>";
echo "<div class='container'>";
echo "<h1>🔍 Тест подключения к базе данных</h1>";
echo "<p>Проверка подключения к базе данных <strong>{$dbname}</strong> с пользователем <strong>{$user}</strong>@<strong>{$host}</strong></p>";

// ============================================
// 1. ПРОВЕРКА НАЛИЧИЯ PDO
// ============================================
if (!extension_loaded('pdo')) {
    formatOutput('PDO Extension', 'ERROR', 'PDO extension не загружена. Установите php-pdo.');
} else {
    formatOutput('PDO Extension', 'OK', 'PDO extension загружена');
    
    if (!in_array('mysql', PDO::getAvailableDrivers())) {
        formatOutput('PDO MySQL Driver', 'ERROR', 'Драйвер pdo_mysql не найден. Установите php-mysql.');
    } else {
        formatOutput('PDO MySQL Driver', 'OK', 'Драйвер pdo_mysql доступен');
        
        // ============================================
        // 2. ПРОВЕРКА ПОДКЛЮЧЕНИЯ К БАЗЕ ДАННЫХ
        // ============================================
        try {
            $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            formatOutput('Подключение к БД', 'OK', "Успешно подключено к {$dbname} на {$host}");
            
            // ============================================
            // 3. ПОЛУЧЕНИЕ ИНФОРМАЦИИ О БАЗЕ ДАННЫХ
            // ============================================
            echo "<div style='border:1px solid #ddd; margin:10px 0; padding:15px; border-radius:5px;'>";
            echo "<h3 style='margin-top:0;'>📊 Информация о сервере БД</h3>";
            
            // Версия MySQL
            $stmt = $pdo->query("SELECT VERSION() as version");
            $version = $stmt->fetch();
            echo "<p><strong>Версия MySQL:</strong> " . $version['version'] . "</p>";
            
            // Текущая база данных
            $stmt = $pdo->query("SELECT DATABASE() as db");
            $currentDb = $stmt->fetch();
            echo "<p><strong>Текущая база данных:</strong> " . $currentDb['db'] . "</p>";
            
            // Статус соединения
            echo "<p><strong>Статус соединения:</strong> " . $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) . "</p>";
            
            // Информация о драйвере
            echo "<p><strong>Информация о драйвере:</strong> " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "</p>";
            echo "</div>";
            
            // ============================================
            // 4. ПРОВЕРКА НАЛИЧИЯ ТАБЛИЦ
            // ============================================
            echo "<div style='border:1px solid #ddd; margin:10px 0; padding:15px; border-radius:5px;'>";
            echo "<h3 style='margin-top:0;'>📋 Список таблиц в базе данных</h3>";
            
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll();
            
            if (count($tables) > 0) {
                echo "<table>";
                echo "<tr><th>#</th><th>Имя таблицы</th></tr>";
                foreach ($tables as $index => $row) {
                    $tableName = reset($row);
                    echo "<tr>";
                    echo "<td>" . ($index + 1) . "</td>";
                    echo "<td>" . htmlspecialchars($tableName) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>⚠️ В базе данных нет таблиц</p>";
            }
            echo "</div>";
            
            // ============================================
            // 5. ПРОВЕРКА ПРАВ ПОЛЬЗОВАТЕЛЯ
            // ============================================
            echo "<div style='border:1px solid #ddd; margin:10px 0; padding:15px; border-radius:5px;'>";
            echo "<h3 style='margin-top:0;'>🔑 Права пользователя</h3>";
            
            $stmt = $pdo->query("SHOW GRANTS FOR CURRENT_USER");
            $grants = $stmt->fetchAll();
            
            echo "<ul>";
            foreach ($grants as $grant) {
                $grantText = reset($grant);
                echo "<li style='font-family:monospace; font-size:12px;'>" . htmlspecialchars($grantText) . "</li>";
            }
            echo "</ul>";
            echo "</div>";
            
            // ============================================
            // 6. ТЕСТОВЫЙ ЗАПРОС
            // ============================================
            echo "<div style='border:1px solid #ddd; margin:10px 0; padding:15px; border-radius:5px;'>";
            echo "<h3 style='margin-top:0;'>🧪 Тестовый запрос</h3>";
            
            try {
                $stmt = $pdo->query("SELECT 'Подключение работает!' as message, NOW() as current_time, USER() as current_user");
                $result = $stmt->fetch();
                echo "<table>";
                echo "<tr><th>Поле</th><th>Значение</th></tr>";
                foreach ($result as $key => $value) {
                    echo "<tr><td>" . htmlspecialchars($key) . "</td><td>" . htmlspecialchars($value) . "</td></tr>";
                }
                echo "</table>";
                echo "<p style='color:green; font-weight:bold; margin-top:15px;'>✅ Все тесты пройдены успешно! Подключение к базе данных работает корректно.</p>";
            } catch (PDOException $e) {
                echo "<p class='error'>❌ Ошибка выполнения запроса: " . $e->getMessage() . "</p>";
            }
            echo "</div>";
            
        } catch (PDOException $e) {
            formatOutput('Подключение к БД', 'ERROR', $e->getMessage());
            echo "<div style='background:#ffebee; padding:15px; border-radius:5px; margin-top:20px;'>";
            echo "<h4 style='color:#c62828; margin-top:0;'>🔧 Возможные причины ошибки:</h4>";
            echo "<ul>";
            echo "<li>Неверный пароль пользователя <strong>{$user}</strong></li>";
            echo "<li>Пользователь <strong>{$user}</strong> не имеет доступа к базе <strong>{$dbname}</strong></li>";
            echo "<li>MySQL сервер не запущен</li>";
            echo "<li>Неверный хост (проверьте: {$host})</li>";
            echo "</ul>";
            echo "</div>";
        }
    }
}

echo "<div style='margin-top:20px; padding:15px; background:#e3f2fd; border-radius:5px;'>";
echo "<h4 style='margin-top:0;'>💡 Рекомендации</h4>";
echo "<ul>";
echo "<li>После проверки удалите этот файл для безопасности: <code>sudo rm /var/www/html/test_db.php</code></li>";
echo "<li>Используйте файл конфигурации <code>config/db.php</code> для подключения в ваших скриптах</li>";
echo "<li>Для production используйте переменные окружения вместо хранения пароля в коде</li>";
echo "</ul>";
echo "</div>";

echo "</div>";
echo "</body></html>";
?>