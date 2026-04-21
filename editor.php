<?php
// editor.php - Редактор викторин с сохранением изображений в файлы
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}
require_once 'db.php';


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

// Создание директории для загрузок
$upload_dir = __DIR__ . '/uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Функция для генерации уникального имени файла
function generate_unique_filename($original_name) {
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $unique_name = uniqid() . '_' . time() . '.' . $extension;
    return $unique_name;
}

// Обработка AJAX запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'save_quiz') {
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $slides_data = json_decode($_POST['slides_data'] ?? '[]', true);
            
            if ($quiz_id > 0) {
                // Проверяем, принадлежит ли викторина пользователю
                $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE id = ? AND user_id = ?");
                $stmt->execute([$quiz_id, $user_id]);
                if ($stmt->rowCount() === 0) {
                    $quiz_id = 0;
                }
            }
            
            if ($quiz_id > 0) {
                // Обновление существующей викторины
                $stmt = $pdo->prepare("UPDATE quizzes SET title = ?, description = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
                $stmt->execute([$title, $description, $quiz_id, $user_id]);
                
                // Получаем старые изображения для удаления
                $stmt = $pdo->prepare("SELECT id, image_url FROM slides WHERE quiz_id = ? AND image_url != ''");
                $stmt->execute([$quiz_id]);
                $old_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Удаляем старые слайды и ответы
                $stmt = $pdo->prepare("DELETE FROM slides WHERE quiz_id = ?");
                $stmt->execute([$quiz_id]);
            } else {
                // Создание новой викторины
                $stmt = $pdo->prepare("INSERT INTO quizzes (user_id, title, description, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
                $stmt->execute([$user_id, $title, $description]);
                $quiz_id = $pdo->lastInsertId();
            }
            
            // Создаем папку для викторины
            $quiz_upload_dir = $upload_dir . $quiz_id . '/';
            if (!file_exists($quiz_upload_dir)) {
                mkdir($quiz_upload_dir, 0777, true);
            }
            
            // Сохраняем слайды
            foreach ($slides_data as $index => $slide) {
                $image_filename = '';
                $image_data = $slide['image'] ?? '';
                
                // Обработка изображения
                if (!empty($image_data)) {
                    // Если изображение пришло как base64
                    if (strpos($image_data, 'data:image') === 0) {
                        $image_filename = save_base64_image($image_data, $quiz_upload_dir);
                    } 
                    // Если изображение уже сохранено в папке uploads
                    elseif (strpos($image_data, 'uploads/') === 0) {
                        // Извлекаем только имя файла из пути
                        $parts = explode('/', $image_data);
                        $image_filename = end($parts);
                        
                        // Копируем файл в папку викторины, если его там нет
                        $old_path = __DIR__ . '/' . $image_data;
                        $new_path = $quiz_upload_dir . $image_filename;
                        if (file_exists($old_path) && $old_path != $new_path) {
                            copy($old_path, $new_path);
                        }
                    }
                    // Если это просто имя файла
                    else {
                        $image_filename = $image_data;
                    }
                }
                
                $stmt = $pdo->prepare("INSERT INTO slides (quiz_id, slide_order, question_text, image_url, duration, points, font_size, font_color) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $quiz_id,
                    $index,
                    $slide['question'],
                    $image_filename,
                    (int)$slide['duration'],
                    (int)$slide['points'],
                    (int)$slide['fontSize'],
                    $slide['fontColor']
                ]);
                
                $slide_id = $pdo->lastInsertId();
                
                // Сохраняем ответы
                foreach ($slide['answers'] as $optIndex => $answer) {
                    $stmt = $pdo->prepare("INSERT INTO answer_options (slide_id, option_order, option_text, is_correct, shape_type) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $slide_id,
                        $optIndex,
                        $answer['text'],
                        $answer['isCorrect'] ? 1 : 0,
                        $answer['shape']
                    ]);
                }
            }
            
            echo json_encode(['success' => true, 'quiz_id' => $quiz_id, 'message' => 'Викторина сохранена']);
            exit();
        }
        
        if ($action === 'upload_image') {
            // Загрузка изображения через AJAX (FormData)
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Ошибка загрузки файла']);
                exit();
            }
            
            $file = $_FILES['image'];
            
            // Проверка размера файла (0.5 MB = 524288 байт)
            if ($file['size'] > 524288) {
                echo json_encode(['success' => false, 'message' => 'Файл слишком большой. Максимальный размер 0.5 MB']);
                exit();
            }
            
            // Проверка типа файла
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'message' => 'Разрешены только изображения (JPEG, PNG, GIF, WEBP)']);
                exit();
            }
            
            // Если викторина еще не сохранена, создаем временную
            $temp_quiz_id = $quiz_id;
            if ($temp_quiz_id == 0) {
                // Создаем временную викторину
                $stmt = $pdo->prepare("INSERT INTO quizzes (user_id, title, description, created_at, updated_at) VALUES (?, 'temp', 'temp', NOW(), NOW())");
                $stmt->execute([$user_id]);
                $temp_quiz_id = $pdo->lastInsertId();
                $quiz_id = $temp_quiz_id;
            }
            
            // Создаем папку для викторины
            $quiz_upload_dir = $upload_dir . $temp_quiz_id . '/';
            if (!file_exists($quiz_upload_dir)) {
                mkdir($quiz_upload_dir, 0777, true);
            }
            
            // Генерируем уникальное имя файла
            $filename = generate_unique_filename($file['name']);
            $filepath = $quiz_upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Сохраняем только имя файла в БД
                $stmt = $pdo->prepare("UPDATE slides SET image_url = ? WHERE quiz_id = ? AND slide_order = ?");
                $stmt->execute([$filename, $temp_quiz_id, (int)$_POST['slide_index']]);
                
                echo json_encode([
                    'success' => true, 
                    'filename' => $filename,
                    'url' => 'uploads/' . $temp_quiz_id . '/' . $filename,
                    'quiz_id' => $temp_quiz_id,
                    'message' => 'Изображение загружено'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Ошибка сохранения файла']);
            }
            exit();
        }
        
        if ($action === 'load_quiz') {
            if ($quiz_id > 0) {
                // Загрузка викторины
                $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ? AND user_id = ?");
                $stmt->execute([$quiz_id, $user_id]);
                $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($quiz) {
                    // Загрузка слайдов
                    $stmt = $pdo->prepare("SELECT * FROM slides WHERE quiz_id = ? ORDER BY slide_order");
                    $stmt->execute([$quiz_id]);
                    $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($slides as &$slide) {
                        $stmt = $pdo->prepare("SELECT * FROM answer_options WHERE slide_id = ? ORDER BY option_order");
                        $stmt->execute([$slide['id']]);
                        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $slide['answers'] = [];
                        foreach ($answers as $answer) {
                            $slide['answers'][] = [
                                'text' => $answer['option_text'],
                                'isCorrect' => (bool)$answer['is_correct'],
                                'shape' => $answer['shape_type']
                            ];
                        }
                        
                        // Формируем полный URL для изображения
                        if (!empty($slide['image_url'])) {
                            $image_path = 'uploads/' . $quiz_id . '/' . $slide['image_url'];
                            if (file_exists(__DIR__ . '/' . $image_path)) {
                                $slide['image_url'] = $image_path;
                            } else {
                                $slide['image_url'] = '';
                            }
                        }
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'quiz' => [
                            'id' => $quiz['id'],
                            'title' => $quiz['title'],
                            'description' => $quiz['description'],
                            'slides' => $slides
                        ]
                    ]);
                    exit();
                }
            }
            
            echo json_encode(['success' => false, 'message' => 'Викторина не найдена']);
            exit();
        }
        
        if ($action === 'delete_quiz') {
            if ($quiz_id > 0) {
                // Удаляем папку с изображениями
                $quiz_upload_dir = $upload_dir . $quiz_id;
                if (file_exists($quiz_upload_dir)) {
                    delete_directory($quiz_upload_dir);
                }
                
                $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ? AND user_id = ?");
                $stmt->execute([$quiz_id, $user_id]);
                echo json_encode(['success' => true, 'message' => 'Викторина удалена']);
                exit();
            }
            echo json_encode(['success' => false, 'message' => 'Ошибка удаления']);
            exit();
        }
        
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Функция сохранения base64 изображения
function save_base64_image($base64_string, $upload_dir) {
    // Разделяем данные
    if (preg_match('/^data:image\/(\w+);base64,/', $base64_string, $matches)) {
        $image_type = $matches[1];
        $image_data = substr($base64_string, strpos($base64_string, ',') + 1);
        $image_data = base64_decode($image_data);
        
        $filename = uniqid() . '_' . time() . '.' . $image_type;
        $filepath = $upload_dir . $filename;
        
        file_put_contents($filepath, $image_data);
        return $filename;
    }
    
    return '';
}

// Функция рекурсивного удаления директории
function delete_directory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        if (!delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    
    return rmdir($dir);
}

// Получение списка викторин для отображения
$quizzes = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, description, created_at, updated_at FROM quizzes WHERE user_id = ? AND title != 'temp' ORDER BY updated_at DESC");
    $stmt->execute([$user_id]);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($quizzes as &$quiz) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM slides WHERE quiz_id = ?");
        $stmt->execute([$quiz['id']]);
        $quiz['slides_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
} catch(PDOException $e) {
    $quizzes = [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактор викторин - Quiz27</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .back-btn {
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .quiz-select {
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            min-width: 200px;
        }

        .quiz-title-input {
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            width: 300px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .preview-btn, .save-btn, .new-quiz-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .preview-btn {
            background: #48bb78;
            color: white;
        }

        .save-btn {
            background: #4299e1;
            color: white;
        }

        .new-quiz-btn {
            background: #ed8936;
            color: white;
        }

        .preview-btn:hover, .save-btn:hover, .new-quiz-btn:hover {
            transform: translateY(-2px);
        }

        .main-layout {
            display: flex;
            height: calc(100vh - 70px);
        }

        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .add-slide-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .add-slide-btn:hover {
            transform: translateY(-2px);
        }

        .slides-list {
            flex: 1;
            padding: 15px;
        }

        .slide-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .slide-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .slide-item.active {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            border-color: #667eea;
        }

        .slide-number {
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .slide-preview {
            font-size: 12px;
            color: #666;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .slide-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .slide-action-btn {
            padding: 4px 8px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 11px;
            background: #e0e0e0;
        }

        .slide-action-btn.delete {
            background: #f56565;
            color: white;
        }

        .slide-action-btn.duplicate {
            background: #48bb78;
            color: white;
        }

        .editor-area {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
        }

        .slide-editor {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 900px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .image-upload {
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .image-upload:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }

        .image-upload-loading {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 14px;
            color: #667eea;
        }

        .image-preview {
            margin-top: 10px;
            max-width: 100%;
            max-height: 200px;
            display: none;
            border-radius: 10px;
        }

        .answers-table {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 10px;
        }

        .answer-cell {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
        }

        .answer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .answer-shape {
            font-size: 24px;
        }

        .answer-shape.circle { color: #4299e1; }
        .answer-shape.square { color: #48bb78; }
        .answer-shape.diamond { color: #ed8936; }
        .answer-shape.star { color: #f56565; }

        .correct-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .answer-text {
            width: 100%;
            padding: 8px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            margin-top: 5px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-weight: 600;
            color: #666;
        }

        .tab.active {
            color: #667eea;
            border-bottom: 2px solid #667eea;
            margin-bottom: -2px;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .preview-container {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .preview-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
        }

        .preview-content {
            padding: 30px;
            text-align: center;
        }

        .preview-answers {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }

        .preview-answer {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 30px;
            cursor: pointer;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            z-index: 3000;
            animation: slideIn 0.3s ease;
        }

        .toast.success { background: #48bb78; }
        .toast.error { background: #f56565; }

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
            .sidebar {
                width: 200px;
            }
            .answers-table {
                grid-template-columns: 1fr;
            }
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <a href="dashboard.php" class="back-btn">← Назад</a>
            <select id="quizSelect" class="quiz-select" onchange="loadQuizFromSelect()">
                <option value="0">-- Новая викторина --</option>
                <?php foreach ($quizzes as $quiz): ?>
                    <option value="<?php echo $quiz['id']; ?>" <?php echo $quiz_id == $quiz['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($quiz['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="quizTitle" class="quiz-title-input" placeholder="Название викторины">
        </div>
        <div class="header-actions">
            <button class="new-quiz-btn" onclick="createNewQuiz()">✨ Новая</button>
            <button class="preview-btn" onclick="previewQuiz()">👁️ Просмотр</button>
            <button class="save-btn" onclick="saveQuiz()">💾 Сохранить</button>
        </div>
    </div>

    <div class="main-layout">
        <div class="sidebar">
            <div class="sidebar-header">
                <button class="add-slide-btn" onclick="addSlide()">+ Добавить слайд</button>
            </div>
            <div class="slides-list" id="slidesList"></div>
        </div>

        <div class="editor-area">
            <div class="slide-editor" id="slideEditor">
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('content')">📝 Контент</button>
                    <button class="tab" onclick="switchTab('settings')">⚙️ Настройки</button>
                </div>

                <div id="contentTab" class="tab-content active">
                    <div class="form-group">
                        <label>Вопрос</label>
                        <textarea id="questionText" placeholder="Введите текст вопроса..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Изображение</label>
                        <div class="image-upload" id="imageUploadArea" onclick="document.getElementById('imageInput').click()">
                            📸 Нажмите для загрузки изображения (макс. 0.5 MB)
                        </div>
                        <input type="file" id="imageInput" style="display: none;" accept="image/jpeg,image/png,image/gif,image/webp" onchange="uploadImage(event)">
                        <img id="imagePreview" class="image-preview" alt="Preview">
                    </div>

                    <div class="form-group">
                        <label>Варианты ответов</label>
                        <div class="answers-table" id="answersTable"></div>
                    </div>
                </div>

                <div id="settingsTab" class="tab-content">
                    <div class="settings-grid">
                        <div class="form-group">
                            <label>Длительность показа (секунд)</label>
                            <input type="number" id="slideDuration" min="5" max="120" value="30">
                        </div>
                        <div class="form-group">
                            <label>Баллов за ответ</label>
                            <input type="number" id="slidePoints" min="1" max="1000" value="1">
                        </div>
                        <div class="form-group">
                            <label>Размер шрифта (px)</label>
                            <input type="number" id="fontSize" min="12" max="72" value="24">
                        </div>
                        <div class="form-group">
                            <label>Цвет шрифта</label>
                            <input type="color" id="fontColor" value="#000000">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="previewModal" class="modal">
        <div class="preview-container">
            <div class="preview-header">
                <h3>Просмотр викторины</h3>
                <button class="close-modal" onclick="closePreview()">×</button>
            </div>
            <div class="preview-content" id="previewContent"></div>
            <div style="padding: 20px; text-align: center;">
                <button onclick="startPreview()" style="padding: 10px 30px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer;">▶ Начать просмотр</button>
            </div>
        </div>
    </div>

    <script>
        let currentQuiz = {
            id: <?php echo $quiz_id; ?>,
            title: '',
            description: '',
            slides: []
        };

        let currentSlideIndex = 0;
        let previewInterval = null;
        let currentPreviewSlide = 0;
        let isSaving = false;

        const shapes = ['circle', 'square', 'diamond', 'star'];
        const shapeIcons = {
            circle: '●',
            square: '■',
            diamond: '◆',
            star: '★'
        };

        async function loadQuiz(quizId) {
            if (quizId == 0) {
                currentQuiz = {
                    id: 0,
                    title: '',
                    description: '',
                    slides: []
                };
                document.getElementById('quizTitle').value = '';
                while(currentQuiz.slides.length) currentQuiz.slides.pop();
                addSlide();
                renderSlidesList();
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'load_quiz');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    currentQuiz = {
                        id: data.quiz.id,
                        title: data.quiz.title,
                        description: data.quiz.description || '',
                        slides: data.quiz.slides.map(slide => ({
                            id: slide.id,
                            question: slide.question_text,
                            image: slide.image_url || '',
                            duration: slide.duration,
                            points: slide.points,
                            fontSize: slide.font_size,
                            fontColor: slide.font_color,
                            answers: slide.answers
                        }))
                    };
                    
                    document.getElementById('quizTitle').value = currentQuiz.title;
                    
                    if (currentQuiz.slides.length === 0) {
                        addSlide();
                    } else {
                        currentSlideIndex = 0;
                        loadSlide(currentSlideIndex);
                    }
                    renderSlidesList();
                } else {
                    showToast('Ошибка загрузки: ' + data.message, 'error');
                }
            } catch (error) {
                showToast('Ошибка загрузки викторины', 'error');
            }
        }

        async function saveQuiz() {
            if (isSaving) return;
            isSaving = true;
            
            saveCurrentSlide();
            currentQuiz.title = document.getElementById('quizTitle').value;
            
            if (!currentQuiz.title.trim()) {
                showToast('Введите название викторины', 'error');
                isSaving = false;
                return;
            }
            
            if (currentQuiz.slides.length === 0) {
                showToast('Добавьте хотя бы один слайд', 'error');
                isSaving = false;
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'save_quiz');
                formData.append('title', currentQuiz.title);
                formData.append('description', currentQuiz.description);
                formData.append('slides_data', JSON.stringify(currentQuiz.slides.map(slide => ({
                    question: slide.question,
                    image: slide.image,
                    duration: slide.duration,
                    points: slide.points,
                    fontSize: slide.fontSize,
                    fontColor: slide.fontColor,
                    answers: slide.answers
                }))));
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    currentQuiz.id = data.quiz_id;
                    showToast('Викторина сохранена!', 'success');
                    
                    const select = document.getElementById('quizSelect');
                    if (!select.querySelector(`option[value="${data.quiz_id}"]`)) {
                        const option = document.createElement('option');
                        option.value = data.quiz_id;
                        option.textContent = currentQuiz.title;
                        select.appendChild(option);
                    }
                    select.value = data.quiz_id;
                } else {
                    showToast('Ошибка: ' + data.message, 'error');
                }
            } catch (error) {
                showToast('Ошибка сохранения: ' + error.message, 'error');
            }
            
            isSaving = false;
        }

        async function uploadImage(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            // Проверка размера (0.5 MB = 524288 байт)
            if (file.size > 524288) {
                showToast('Файл слишком большой! Максимальный размер 0.5 MB', 'error');
                document.getElementById('imageInput').value = '';
                return;
            }
            
            // Проверка типа
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                showToast('Разрешены только изображения (JPEG, PNG, GIF, WEBP)', 'error');
                document.getElementById('imageInput').value = '';
                return;
            }
            
            const uploadArea = document.getElementById('imageUploadArea');
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'image-upload-loading';
            loadingDiv.innerHTML = '⏳ Загрузка...';
            uploadArea.style.position = 'relative';
            uploadArea.appendChild(loadingDiv);
            
            try {
                const formData = new FormData();
                formData.append('action', 'upload_image');
                formData.append('slide_index', currentSlideIndex);
                formData.append('image', file);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    if (currentQuiz.id === 0 && data.quiz_id) {
                        currentQuiz.id = data.quiz_id;
                    }
                    
                    currentQuiz.slides[currentSlideIndex].image = data.url;
                    document.getElementById('imagePreview').src = data.url;
                    document.getElementById('imagePreview').style.display = 'block';
                    showToast('Изображение загружено!', 'success');
                } else {
                    showToast('Ошибка: ' + data.message, 'error');
                }
            } catch (error) {
                showToast('Ошибка загрузки изображения', 'error');
            } finally {
                loadingDiv.remove();
                document.getElementById('imageInput').value = '';
            }
        }

        function createNewQuiz() {
            if (confirm('Создать новую викторину? Несохраненные изменения будут потеряны.')) {
                currentQuiz = {
                    id: 0,
                    title: '',
                    description: '',
                    slides: []
                };
                document.getElementById('quizTitle').value = '';
                document.getElementById('quizSelect').value = '0';
                addSlide();
                renderSlidesList();
            }
        }

        function loadQuizFromSelect() {
            const select = document.getElementById('quizSelect');
            const quizId = parseInt(select.value);
            if (quizId === 0) {
                createNewQuiz();
            } else {
                loadQuiz(quizId);
            }
        }

        function addSlide() {
            const newSlide = {
                id: Date.now(),
                question: 'Новый вопрос',
                image: '',
                duration: 30,
                points: 1,
                fontSize: 24,
                fontColor: '#000000',
                answers: [
                    { text: 'Вариант 1', isCorrect: false, shape: 'circle' },
                    { text: 'Вариант 2', isCorrect: false, shape: 'square' },
                    { text: 'Вариант 3', isCorrect: false, shape: 'diamond' },
                    { text: 'Вариант 4', isCorrect: false, shape: 'star' }
                ]
            };
            currentQuiz.slides.push(newSlide);
            currentSlideIndex = currentQuiz.slides.length - 1;
            renderSlidesList();
            loadSlide(currentSlideIndex);
        }

        function deleteSlide(index) {
            if (currentQuiz.slides.length === 1) {
                showToast('Нельзя удалить единственный слайд', 'error');
                return;
            }
            if (confirm('Удалить этот слайд?')) {
                currentQuiz.slides.splice(index, 1);
                if (currentSlideIndex >= currentQuiz.slides.length) {
                    currentSlideIndex = currentQuiz.slides.length - 1;
                }
                renderSlidesList();
                loadSlide(currentSlideIndex);
            }
        }

        function duplicateSlide(index) {
            const original = currentQuiz.slides[index];
            const duplicated = JSON.parse(JSON.stringify(original));
            duplicated.id = Date.now();
            duplicated.question = original.question + ' (копия)';
            duplicated.image = '';
            currentQuiz.slides.splice(index + 1, 0, duplicated);
            currentSlideIndex = index + 1;
            renderSlidesList();
            loadSlide(currentSlideIndex);
        }

        function loadSlide(index) {
            if (index < 0 || index >= currentQuiz.slides.length) return;
            
            currentSlideIndex = index;
            const slide = currentQuiz.slides[index];
            
            document.getElementById('questionText').value = slide.question;
            document.getElementById('slideDuration').value = slide.duration;
            document.getElementById('slidePoints').value = slide.points;
            document.getElementById('fontSize').value = slide.fontSize;
            document.getElementById('fontColor').value = slide.fontColor;
            
            if (slide.image) {
                document.getElementById('imagePreview').src = slide.image;
                document.getElementById('imagePreview').style.display = 'block';
            } else {
                document.getElementById('imagePreview').style.display = 'none';
            }
            
            renderAnswers(slide.answers);
            renderSlidesList();
        }

        function saveCurrentSlide() {
            if (currentSlideIndex < 0 || currentSlideIndex >= currentQuiz.slides.length) return;
            
            const slide = currentQuiz.slides[currentSlideIndex];
            slide.question = document.getElementById('questionText').value;
            slide.duration = parseInt(document.getElementById('slideDuration').value);
            slide.points = parseInt(document.getElementById('slidePoints').value);
            slide.fontSize = parseInt(document.getElementById('fontSize').value);
            slide.fontColor = document.getElementById('fontColor').value;
            
            const answers = [];
            for (let i = 0; i < 4; i++) {
                const textInput = document.getElementById(`answer_text_${i}`);
                const checkbox = document.getElementById(`answer_correct_${i}`);
                if (textInput) {
                    answers.push({
                        text: textInput.value,
                        isCorrect: checkbox.checked,
                        shape: shapes[i]
                    });
                }
            }
            slide.answers = answers;
        }

        function renderAnswers(answers) {
            const container = document.getElementById('answersTable');
            let html = '';
            
            for (let i = 0; i < 4; i++) {
                const answer = answers[i] || { text: '', isCorrect: false, shape: shapes[i] };
                html += `
                    <div class="answer-cell">
                        <div class="answer-header">
                            <span class="answer-shape ${answer.shape}">${shapeIcons[answer.shape]}</span>
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="checkbox" id="answer_correct_${i}" class="correct-checkbox" ${answer.isCorrect ? 'checked' : ''}>
                                Правильный
                            </label>
                        </div>
                        <input type="text" id="answer_text_${i}" class="answer-text" value="${escapeHtml(answer.text)}" placeholder="Вариант ответа">
                    </div>
                `;
            }
            
            container.innerHTML = html;
        }

        function renderSlidesList() {
            const container = document.getElementById('slidesList');
            let html = '';
            
            currentQuiz.slides.forEach((slide, index) => {
                const isActive = index === currentSlideIndex;
                html += `
                    <div class="slide-item ${isActive ? 'active' : ''}" onclick="selectSlide(${index})">
                        <div class="slide-number">Слайд ${index + 1}</div>
                        <div class="slide-preview">${escapeHtml(slide.question.substring(0, 50))}</div>
                        <div class="slide-actions">
                            <button class="slide-action-btn duplicate" onclick="event.stopPropagation(); duplicateSlide(${index})">📋</button>
                            <button class="slide-action-btn delete" onclick="event.stopPropagation(); deleteSlide(${index})">🗑️</button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function selectSlide(index) {
            saveCurrentSlide();
            loadSlide(index);
        }

        function switchTab(tab) {
            const contentTab = document.getElementById('contentTab');
            const settingsTab = document.getElementById('settingsTab');
            const tabs = document.querySelectorAll('.tab');
            
            if (tab === 'content') {
                contentTab.classList.add('active');
                settingsTab.classList.remove('active');
                tabs[0].classList.add('active');
                tabs[1].classList.remove('active');
            } else {
                contentTab.classList.remove('active');
                settingsTab.classList.add('active');
                tabs[0].classList.remove('active');
                tabs[1].classList.add('active');
            }
        }

        function previewQuiz() {
            saveCurrentSlide();
            document.getElementById('previewModal').style.display = 'flex';
            showPreviewSlide(0);
        }

        function showPreviewSlide(index) {
            const slide = currentQuiz.slides[index];
            if (!slide) return;
            
            const container = document.getElementById('previewContent');
            let answersHtml = '';
            
            slide.answers.forEach(answer => {
                answersHtml += `
                    <div class="preview-answer">
                        <span style="font-size: 24px; color: #667eea;">${shapeIcons[answer.shape]}</span>
                        <span>${escapeHtml(answer.text)}</span>
                    </div>
                `;
            });
            
            const imageHtml = slide.image ? `<img src="${slide.image}" class="preview-image" style="max-width: 100%; max-height: 200px; margin-bottom: 20px;">` : '';
            
            container.innerHTML = `
                <div style="font-size: ${slide.fontSize}px; color: ${slide.fontColor}; margin-bottom: 20px;">
                    ${escapeHtml(slide.question)}
                </div>
                ${imageHtml}
                <div class="preview-answers">
                    ${answersHtml}
                </div>
                <div style="margin-top: 20px; color: #666;">
                    ⏱️ ${slide.duration} сек | ⭐ ${slide.points} баллов
                </div>
                <div style="margin-top: 20px; font-size: 14px; color: #999;">
                    Слайд ${index + 1} из ${currentQuiz.slides.length}
                </div>
            `;
        }

        function startPreview() {
            if (previewInterval) clearInterval(previewInterval);
            currentPreviewSlide = 0;
            showPreviewSlide(0);
            
            previewInterval = setInterval(() => {
                currentPreviewSlide++;
                if (currentPreviewSlide >= currentQuiz.slides.length) {
                    clearInterval(previewInterval);
                    showToast('Просмотр завершен!', 'success');
                } else {
                    showPreviewSlide(currentPreviewSlide);
                }
            }, currentQuiz.slides[currentPreviewSlide]?.duration * 1000 || 30000);
        }

        function closePreview() {
            if (previewInterval) {
                clearInterval(previewInterval);
                previewInterval = null;
            }
            document.getElementById('previewModal').style.display = 'none';
        }

        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Инициализация
        if (currentQuiz.id > 0) {
            loadQuiz(currentQuiz.id);
        } else {
            addSlide();
        }
    </script>
</body>
</html>