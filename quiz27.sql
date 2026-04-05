-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Апр 02 2026 г., 23:07
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `quiz27`
--

-- --------------------------------------------------------

--
-- Структура таблицы `answer_options`
--

CREATE TABLE `answer_options` (
  `id` int(11) NOT NULL,
  `slide_id` int(11) NOT NULL,
  `option_order` int(11) NOT NULL DEFAULT 0,
  `option_text` varchar(500) NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `shape_type` enum('circle','square','diamond','star') DEFAULT 'circle',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `answer_options`
--

INSERT INTO `answer_options` (`id`, `slide_id`, `option_order`, `option_text`, `is_correct`, `shape_type`, `created_at`) VALUES
(261, 70, 0, '8', 1, 'circle', '2026-04-02 21:04:32'),
(262, 70, 1, '9', 0, 'square', '2026-04-02 21:04:32'),
(263, 70, 2, '10', 0, 'diamond', '2026-04-02 21:04:32'),
(264, 70, 3, '7', 0, 'star', '2026-04-02 21:04:32'),
(265, 71, 0, 'Юпитер', 1, 'circle', '2026-04-02 21:04:32'),
(266, 71, 1, 'Сатурн', 0, 'square', '2026-04-02 21:04:32'),
(267, 71, 2, 'Уран', 0, 'diamond', '2026-04-02 21:04:32'),
(268, 71, 3, 'Титан', 0, 'star', '2026-04-02 21:04:33');

-- --------------------------------------------------------

--
-- Структура таблицы `app_settings`
--

CREATE TABLE `app_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `app_settings`
--

INSERT INTO `app_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES
(1, 'default_background_music', 'default_music.mp3', 'string', 'Путь к файлу фоновой музыки по умолчанию', '2026-04-02 08:51:40'),
(2, 'music_volume', '0.7', 'string', 'Громкость фоновой музыки (0-1)', '2026-04-02 08:51:40'),
(3, 'result_display_seconds', '5', 'integer', 'Сколько секунд показывать результаты после вопроса', '2026-04-02 08:51:40'),
(4, 'max_players_per_session', '50', 'integer', 'Максимальное количество игроков на одну сессию', '2026-04-02 08:51:40'),
(5, 'session_code_length', '4', 'integer', 'Длина кода сессии', '2026-04-02 08:51:40'),
(6, 'default_font_size', '24', 'integer', 'Размер шрифта по умолчанию', '2026-04-02 08:51:40'),
(7, 'default_font_color', '#000000', 'string', 'Цвет шрифта по умолчанию', '2026-04-02 08:51:40'),
(8, 'allow_music_toggle', 'true', 'boolean', 'Разрешить выключение музыки', '2026-04-02 08:51:40'),
(9, 'session_timeout_minutes', '30', 'integer', 'Таймаут сессии в минутах', '2026-04-02 08:51:40'),
(10, 'cleanup_old_sessions_days', '7', 'integer', 'Через сколько дней удалять старые сессии', '2026-04-02 08:51:40');

-- --------------------------------------------------------

--
-- Структура таблицы `game_players`
--

CREATE TABLE `game_players` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `player_name` varchar(50) NOT NULL,
  `player_score` int(11) DEFAULT 0,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `game_sessions`
--

CREATE TABLE `game_sessions` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `session_code` varchar(4) NOT NULL,
  `host_user_id` int(11) NOT NULL,
  `status` enum('waiting','active','finished','cancelled') DEFAULT 'waiting',
  `current_slide_id` int(11) DEFAULT NULL,
  `current_slide_start_time` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ended_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `game_state`
--

CREATE TABLE `game_state` (
  `session_id` int(11) NOT NULL,
  `current_slide_index` int(11) DEFAULT 0,
  `slide_start_time` timestamp NULL DEFAULT NULL,
  `slide_end_time` timestamp NULL DEFAULT NULL,
  `is_accepting_answers` tinyint(1) DEFAULT 0,
  `is_music_enabled` tinyint(1) DEFAULT 1,
  `game_state_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`game_state_data`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `player_answers`
--

CREATE TABLE `player_answers` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `slide_id` int(11) NOT NULL,
  `answer_option_id` int(11) NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `response_time_ms` int(11) NOT NULL,
  `points_awarded` int(11) DEFAULT 0,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `quizzes`
--

CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `background_music` varchar(500) DEFAULT 'default_music.mp3',
  `default_slide_duration` int(11) DEFAULT 30,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `quizzes`
--

INSERT INTO `quizzes` (`id`, `user_id`, `title`, `description`, `background_music`, `default_slide_duration`, `created_at`, `updated_at`) VALUES
(13, 1, 'Test', '', 'default_music.mp3', 30, '2026-04-02 21:00:35', '2026-04-02 21:04:32');

-- --------------------------------------------------------

--
-- Структура таблицы `quiz_statistics`
--

CREATE TABLE `quiz_statistics` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `total_players` int(11) DEFAULT 0,
  `total_questions` int(11) DEFAULT 0,
  `average_score` decimal(10,2) DEFAULT 0.00,
  `average_response_time_ms` decimal(10,2) DEFAULT 0.00,
  `completion_rate` decimal(5,2) DEFAULT 0.00,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `slides`
--

CREATE TABLE `slides` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `slide_order` int(11) NOT NULL DEFAULT 0,
  `question_text` text NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `duration` int(11) DEFAULT 30,
  `points` int(11) DEFAULT 1,
  `font_size` int(11) DEFAULT 24,
  `font_color` varchar(7) DEFAULT '#000000',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `slides`
--

INSERT INTO `slides` (`id`, `quiz_id`, `slide_order`, `question_text`, `image_url`, `duration`, `points`, `font_size`, `font_color`, `created_at`, `updated_at`) VALUES
(70, 13, 0, 'Сколько планет в Солнечной системе?', '69ced9daccdc0_1775163866.jpg', 30, 1, 24, '#000000', '2026-04-02 21:04:32', '2026-04-02 21:04:32'),
(71, 13, 1, 'Назовите самую большую планету Cолнечной системы?', '69ced9b138415_1775163825.jpg', 30, 1, 24, '#000000', '2026-04-02 21:04:32', '2026-04-02 21:04:32');

-- --------------------------------------------------------

--
-- Структура таблицы `slide_statistics`
--

CREATE TABLE `slide_statistics` (
  `id` int(11) NOT NULL,
  `quiz_statistic_id` int(11) NOT NULL,
  `slide_id` int(11) NOT NULL,
  `correct_answers_count` int(11) DEFAULT 0,
  `wrong_answers_count` int(11) DEFAULT 0,
  `total_answers_count` int(11) DEFAULT 0,
  `average_response_time_ms` decimal(10,2) DEFAULT 0.00,
  `fastest_response_time_ms` int(11) DEFAULT 0,
  `slowest_response_time_ms` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `created_at`, `updated_at`) VALUES
(1, 'user', 'user@mail.ru', '$2y$10$ubB9h8.3QEz.1cn5QTRWueaN5y/NqjkIf3Grv7Tlbgyfs7h/stPa6', '2026-04-02 17:12:48', '2026-04-02 17:12:48');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `answer_options`
--
ALTER TABLE `answer_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_slide_id` (`slide_id`),
  ADD KEY `idx_is_correct` (`is_correct`),
  ADD KEY `idx_options_slide_order` (`slide_id`,`option_order`);

--
-- Индексы таблицы `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- Индексы таблицы `game_players`
--
ALTER TABLE `game_players`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_session_player` (`session_id`,`player_name`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_player_name` (`player_name`),
  ADD KEY `idx_players_active` (`session_id`,`is_active`);

--
-- Индексы таблицы `game_sessions`
--
ALTER TABLE `game_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_code` (`session_code`),
  ADD KEY `quiz_id` (`quiz_id`),
  ADD KEY `host_user_id` (`host_user_id`),
  ADD KEY `current_slide_id` (`current_slide_id`),
  ADD KEY `idx_session_code` (`session_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_game_state_status` (`status`,`created_at`);

--
-- Индексы таблицы `game_state`
--
ALTER TABLE `game_state`
  ADD PRIMARY KEY (`session_id`);

--
-- Индексы таблицы `player_answers`
--
ALTER TABLE `player_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `slide_id` (`slide_id`),
  ADD KEY `answer_option_id` (`answer_option_id`),
  ADD KEY `idx_session_slide` (`session_id`,`slide_id`),
  ADD KEY `idx_player_session` (`player_id`,`session_id`),
  ADD KEY `idx_answered_at` (`answered_at`),
  ADD KEY `idx_answers_correct` (`is_correct`),
  ADD KEY `idx_answers_response_time` (`response_time_ms`);

--
-- Индексы таблицы `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_title` (`title`);
ALTER TABLE `quizzes` ADD FULLTEXT KEY `ft_description` (`description`);

--
-- Индексы таблицы `quiz_statistics`
--
ALTER TABLE `quiz_statistics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `idx_quiz_id` (`quiz_id`),
  ADD KEY `idx_completed_at` (`completed_at`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Индексы таблицы `slides`
--
ALTER TABLE `slides`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quiz_id` (`quiz_id`),
  ADD KEY `idx_slide_order` (`quiz_id`,`slide_order`),
  ADD KEY `idx_slides_quiz_order` (`quiz_id`,`slide_order`);

--
-- Индексы таблицы `slide_statistics`
--
ALTER TABLE `slide_statistics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quiz_statistic` (`quiz_statistic_id`),
  ADD KEY `idx_slide_id` (`slide_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `answer_options`
--
ALTER TABLE `answer_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=269;

--
-- AUTO_INCREMENT для таблицы `app_settings`
--
ALTER TABLE `app_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `game_players`
--
ALTER TABLE `game_players`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `game_sessions`
--
ALTER TABLE `game_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `player_answers`
--
ALTER TABLE `player_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT для таблицы `quiz_statistics`
--
ALTER TABLE `quiz_statistics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `slides`
--
ALTER TABLE `slides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT для таблицы `slide_statistics`
--
ALTER TABLE `slide_statistics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `answer_options`
--
ALTER TABLE `answer_options`
  ADD CONSTRAINT `answer_options_ibfk_1` FOREIGN KEY (`slide_id`) REFERENCES `slides` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `game_players`
--
ALTER TABLE `game_players`
  ADD CONSTRAINT `game_players_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `game_sessions` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `game_sessions`
--
ALTER TABLE `game_sessions`
  ADD CONSTRAINT `game_sessions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `game_sessions_ibfk_2` FOREIGN KEY (`host_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `game_sessions_ibfk_3` FOREIGN KEY (`current_slide_id`) REFERENCES `slides` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `game_state`
--
ALTER TABLE `game_state`
  ADD CONSTRAINT `game_state_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `game_sessions` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `player_answers`
--
ALTER TABLE `player_answers`
  ADD CONSTRAINT `player_answers_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `game_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `player_answers_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `game_players` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `player_answers_ibfk_3` FOREIGN KEY (`slide_id`) REFERENCES `slides` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `player_answers_ibfk_4` FOREIGN KEY (`answer_option_id`) REFERENCES `answer_options` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `quiz_statistics`
--
ALTER TABLE `quiz_statistics`
  ADD CONSTRAINT `quiz_statistics_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_statistics_ibfk_2` FOREIGN KEY (`session_id`) REFERENCES `game_sessions` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `slides`
--
ALTER TABLE `slides`
  ADD CONSTRAINT `slides_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `slide_statistics`
--
ALTER TABLE `slide_statistics`
  ADD CONSTRAINT `slide_statistics_ibfk_1` FOREIGN KEY (`quiz_statistic_id`) REFERENCES `quiz_statistics` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `slide_statistics_ibfk_2` FOREIGN KEY (`slide_id`) REFERENCES `slides` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
