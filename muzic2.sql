-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- Хост: localhost:8889
-- Время создания: Май 10 2026 г., 11:53
-- Версия сервера: 5.7.24
-- Версия PHP: 8.3.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `muzic2`
--

-- --------------------------------------------------------

--
-- Структура таблицы `album_artists`
--

CREATE TABLE `album_artists` (
  `id` int(11) NOT NULL,
  `album` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `artist` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('primary','featured') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'featured',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `album_likes`
--

CREATE TABLE `album_likes` (
  `user_id` int(11) NOT NULL,
  `album_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `album_likes`
--

INSERT INTO `album_likes` (`user_id`, `album_title`, `created_at`) VALUES
(1, 'Angel May Cry 2', '2026-04-05 11:34:32'),
(1, 'Angel May Cry', '2026-04-05 11:34:40'),
(2, 'Angel May Cry', '2026-04-08 15:07:14'),
(2, 'До рая', '2026-05-05 14:32:53');

-- --------------------------------------------------------

--
-- Структура таблицы `artists`
--

CREATE TABLE `artists` (
  `id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cover` text COLLATE utf8mb4_unicode_ci,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `promo_video` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `artists`
--

INSERT INTO `artists` (`id`, `name`, `cover`, `bio`, `created_at`, `promo_video`) VALUES
(1, 'kai', 'tracks/covers/ab6761610000e5eb890d02408890475ee94cb31a.jpg', 'ss', '2026-04-08 14:21:39', ''),
(2, 'zhanulka', 'tracks/covers/zhanulka.jpg', 'нет такого', '2026-04-09 11:03:05', ''),
(3, 'eizer01', 'tracks/covers/jiadpmb4wv2emnj4-oxnueyysil8k2fca_mu4r63ztqpv_giq0lptuipthqr_-rim4rl58ninc7vfyidgotmahin.jpg', 'мальчик репер с тропарево', '2026-04-21 20:40:56', '');

-- --------------------------------------------------------

--
-- Структура таблицы `genres`
--

CREATE TABLE `genres` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `history`
--

CREATE TABLE `history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `track_id` int(11) DEFAULT NULL,
  `played_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `likes`
--

CREATE TABLE `likes` (
  `user_id` int(11) NOT NULL,
  `track_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `likes`
--

INSERT INTO `likes` (`user_id`, `track_id`, `created_at`) VALUES
(1, 17, '2026-04-05 11:26:34'),
(1, 21, '2026-04-05 11:33:47'),
(1, 8, '2026-04-05 11:36:20'),
(2, 21, '2026-04-08 14:00:20'),
(2, 9, '2026-04-08 14:36:24'),
(2, 26, '2026-04-08 14:41:08'),
(2, 25, '2026-04-08 15:07:11'),
(2, 28, '2026-04-09 11:09:41'),
(2, 18, '2026-05-05 14:31:51'),
(2, 24, '2026-05-05 14:31:52'),
(2, 29, '2026-05-06 21:21:57');

-- --------------------------------------------------------

--
-- Структура таблицы `listening_stats`
--

CREATE TABLE `listening_stats` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `stat_type` enum('artist','track','album','genre') COLLATE utf8mb4_unicode_ci NOT NULL,
  `stat_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `play_count` int(11) DEFAULT '0',
  `total_duration` int(11) DEFAULT '0',
  `last_played` timestamp NULL DEFAULT NULL,
  `period_type` enum('all','day','week','month','year') COLLATE utf8mb4_unicode_ci DEFAULT 'all',
  `period_start` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `listening_stats`
--

INSERT INTO `listening_stats` (`id`, `user_id`, `stat_type`, `stat_name`, `play_count`, `total_duration`, `last_played`, `period_type`, `period_start`) VALUES
(1, 1, 'artist', 'Kai Angel', 1, 180, '2026-04-03 20:50:40', 'all', NULL),
(2, 1, 'track', 'JUMP!', 1, 180, '2026-04-03 20:50:40', 'all', NULL),
(3, 1, 'album', 'Angel May Cry', 1, 180, '2026-04-03 20:50:40', 'all', NULL),
(4, 1, 'artist', 'Kai Angel', 1, 180, '2026-04-03 20:50:40', 'day', '2026-04-03'),
(5, 1, 'track', 'JUMP!', 1, 180, '2026-04-03 20:50:40', 'day', '2026-04-03'),
(6, 1, 'album', 'Angel May Cry', 1, 180, '2026-04-03 20:50:40', 'day', '2026-04-03'),
(7, 1, 'artist', 'Kai Angel', 1, 180, '2026-04-03 20:50:40', 'week', '2026-03-30'),
(8, 1, 'track', 'JUMP!', 1, 180, '2026-04-03 20:50:40', 'week', '2026-03-30'),
(9, 1, 'album', 'Angel May Cry', 1, 180, '2026-04-03 20:50:40', 'week', '2026-03-30'),
(10, 1, 'artist', 'Kai Angel', 1, 180, '2026-04-03 20:50:40', 'month', '2026-04-01'),
(11, 1, 'track', 'JUMP!', 1, 180, '2026-04-03 20:50:40', 'month', '2026-04-01'),
(12, 1, 'album', 'Angel May Cry', 1, 180, '2026-04-03 20:50:40', 'month', '2026-04-01'),
(13, 2, 'artist', 'Kai Angel', 1, 0, '2026-04-08 14:02:32', 'all', NULL),
(14, 2, 'track', 'white ferrari', 1, 0, '2026-04-08 14:02:32', 'all', NULL),
(15, 2, 'artist', 'Kai Angel', 1, 0, '2026-04-08 14:02:32', 'day', '2026-04-08'),
(16, 2, 'track', 'white ferrari', 1, 0, '2026-04-08 14:02:32', 'day', '2026-04-08'),
(17, 2, 'artist', 'Kai Angel', 1, 0, '2026-04-08 14:02:32', 'week', '2026-04-06'),
(18, 2, 'track', 'white ferrari', 1, 0, '2026-04-08 14:02:32', 'week', '2026-04-06'),
(19, 2, 'artist', 'Kai Angel', 1, 0, '2026-04-08 14:02:32', 'month', '2026-04-01'),
(20, 2, 'track', 'white ferrari', 1, 0, '2026-04-08 14:02:32', 'month', '2026-04-01'),
(21, 2, 'artist', 'eizer01', 1, 126, '2026-04-28 14:55:56', 'all', NULL),
(22, 2, 'track', 'До Рая', 1, 126, '2026-04-28 14:55:56', 'all', NULL),
(23, 2, 'album', 'До рая', 1, 126, '2026-04-28 14:55:56', 'all', NULL),
(24, 2, 'artist', 'eizer01', 1, 126, '2026-04-28 14:55:56', 'day', '2026-04-28'),
(25, 2, 'track', 'До Рая', 1, 126, '2026-04-28 14:55:56', 'day', '2026-04-28'),
(26, 2, 'album', 'До рая', 1, 126, '2026-04-28 14:55:56', 'day', '2026-04-28'),
(27, 2, 'artist', 'eizer01', 1, 126, '2026-04-28 14:55:56', 'week', '2026-04-27'),
(28, 2, 'track', 'До Рая', 1, 126, '2026-04-28 14:55:56', 'week', '2026-04-27'),
(29, 2, 'album', 'До рая', 1, 126, '2026-04-28 14:55:56', 'week', '2026-04-27'),
(30, 2, 'artist', 'eizer01', 1, 126, '2026-04-28 14:55:56', 'month', '2026-04-01'),
(31, 2, 'track', 'До Рая', 1, 126, '2026-04-28 14:55:56', 'month', '2026-04-01'),
(32, 2, 'album', 'До рая', 1, 126, '2026-04-28 14:55:56', 'month', '2026-04-01'),
(33, 2, 'artist', 'Kai Angel', 1, 0, '2026-05-05 14:12:22', 'all', NULL),
(34, 2, 'track', 'john galliano', 1, 0, '2026-05-05 14:12:22', 'all', NULL),
(35, 2, 'artist', 'Kai Angel', 1, 0, '2026-05-05 14:12:22', 'day', '2026-05-05'),
(36, 2, 'track', 'john galliano', 1, 0, '2026-05-05 14:12:22', 'day', '2026-05-05'),
(37, 2, 'artist', 'Kai Angel', 3, 0, '2026-05-06 13:48:23', 'week', '2026-05-04'),
(38, 2, 'track', 'john galliano', 1, 0, '2026-05-05 14:12:22', 'week', '2026-05-04'),
(39, 2, 'artist', 'Kai Angel', 3, 0, '2026-05-06 13:48:23', 'month', '2026-05-01'),
(40, 2, 'track', 'john galliano', 1, 0, '2026-05-05 14:12:22', 'month', '2026-05-01'),
(41, 2, 'artist', 'тринадцать карат', 1, 0, '2026-05-05 14:12:32', 'all', NULL),
(42, 2, 'track', 'подружка', 1, 0, '2026-05-05 14:12:32', 'all', NULL),
(43, 2, 'artist', 'тринадцать карат', 2, 0, '2026-05-05 14:45:29', 'day', '2026-05-05'),
(44, 2, 'track', 'подружка', 2, 0, '2026-05-05 14:45:29', 'day', '2026-05-05'),
(45, 2, 'artist', 'тринадцать карат', 2, 0, '2026-05-05 14:45:29', 'week', '2026-05-04'),
(46, 2, 'track', 'подружка', 2, 0, '2026-05-05 14:45:29', 'week', '2026-05-04'),
(47, 2, 'artist', 'тринадцать карат', 2, 0, '2026-05-05 14:45:29', 'month', '2026-05-01'),
(48, 2, 'track', 'подружка', 2, 0, '2026-05-05 14:45:29', 'month', '2026-05-01'),
(49, 2, 'artist', 'тринадцать карат', 1, 0, '2026-05-05 14:45:29', 'all', NULL),
(50, 2, 'track', 'подружка', 1, 0, '2026-05-05 14:45:29', 'all', NULL),
(51, 2, 'artist', 'Kai Angel', 1, 0, '2026-05-06 13:46:13', 'all', NULL),
(52, 2, 'track', 'white ferrari', 1, 0, '2026-05-06 13:46:13', 'all', NULL),
(53, 2, 'artist', 'Kai Angel', 2, 0, '2026-05-06 13:48:23', 'day', '2026-05-06'),
(54, 2, 'track', 'white ferrari', 1, 0, '2026-05-06 13:46:13', 'day', '2026-05-06'),
(56, 2, 'track', 'white ferrari', 1, 0, '2026-05-06 13:46:13', 'week', '2026-05-04'),
(58, 2, 'track', 'white ferrari', 1, 0, '2026-05-06 13:46:13', 'month', '2026-05-01'),
(59, 2, 'artist', 'Kai Angel', 1, 0, '2026-05-06 13:48:23', 'all', NULL),
(60, 2, 'track', 'laperouse', 1, 0, '2026-05-06 13:48:23', 'all', NULL),
(62, 2, 'track', 'laperouse', 1, 0, '2026-05-06 13:48:23', 'day', '2026-05-06'),
(64, 2, 'track', 'laperouse', 1, 0, '2026-05-06 13:48:23', 'week', '2026-05-04'),
(66, 2, 'track', 'laperouse', 1, 0, '2026-05-06 13:48:23', 'month', '2026-05-01');

-- --------------------------------------------------------

--
-- Структура таблицы `lyrics`
--

CREATE TABLE `lyrics` (
  `track_id` int(11) NOT NULL,
  `lrc` mediumtext COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `playlists`
--

CREATE TABLE `playlists` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_public` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cover` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `playlists`
--

INSERT INTO `playlists` (`id`, `user_id`, `name`, `is_public`, `created_at`, `cover`) VALUES
(1, 1, 'Любимые треки', 0, '2026-02-09 22:22:43', NULL),
(2, 2, 'Любимые треки', 0, '2026-04-08 14:00:03', NULL),
(3, 2, '33', 0, '2026-05-05 14:55:29', 'tracks/covers/c9e9d61fd9077a3f33b118a0199634f0.jpg');

-- --------------------------------------------------------

--
-- Структура таблицы `playlist_tracks`
--

CREATE TABLE `playlist_tracks` (
  `playlist_id` int(11) NOT NULL,
  `track_id` int(11) NOT NULL,
  `position` int(11) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `playlist_tracks`
--

INSERT INTO `playlist_tracks` (`playlist_id`, `track_id`, `position`, `added_at`) VALUES
(1, 8, 3, '2026-05-05 14:13:43'),
(1, 17, 1, '2026-05-05 14:13:43'),
(1, 21, 2, '2026-05-05 14:13:43'),
(2, 9, 2, '2026-05-05 14:13:43'),
(2, 18, 6, '2026-05-05 14:31:51'),
(2, 21, 1, '2026-05-05 14:13:43'),
(2, 24, 7, '2026-05-05 14:31:52'),
(2, 25, 4, '2026-05-05 14:13:43'),
(2, 26, 3, '2026-05-05 14:13:43'),
(2, 28, 5, '2026-05-05 14:13:43'),
(2, 29, 8, '2026-05-06 21:21:57'),
(3, 9, 6, '2026-05-05 14:55:29'),
(3, 18, 2, '2026-05-05 14:55:29'),
(3, 24, 1, '2026-05-05 14:55:29'),
(3, 25, 4, '2026-05-05 14:55:29'),
(3, 26, 5, '2026-05-05 14:55:29'),
(3, 28, 3, '2026-05-05 14:55:29');

-- --------------------------------------------------------

--
-- Структура таблицы `play_history`
--

CREATE TABLE `play_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `track_id` int(11) NOT NULL,
  `track_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `track_artist` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `album` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `played_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `play_duration` int(11) DEFAULT '0' COMMENT 'Сколько секунд было прослушано',
  `completed` tinyint(1) DEFAULT '0' COMMENT 'Был ли трек прослушан до конца'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `play_history`
--

INSERT INTO `play_history` (`id`, `user_id`, `track_id`, `track_title`, `track_artist`, `album`, `duration`, `played_at`, `play_duration`, `completed`) VALUES
(1, 1, 1720292342, 'DEAD MEN WALKING', 'Kai Angel', '', 0, '2026-02-09 22:23:36', 0, 0),
(2, 1, 118895460, 'кассета 1', 'тринадцать карат', '', 0, '2026-02-09 22:23:44', 0, 0),
(3, 1, 104355074, 'проваливай', 'тринадцать карат', '', 0, '2026-02-09 22:23:48', 0, 0),
(4, 1, 843219282, 'KYLIE MINOGUE', 'Kai Angel', '', 0, '2026-02-09 22:23:52', 0, 0),
(5, 1, 1401865148, 'laperouse', 'Kai Angel', '', 0, '2026-02-09 22:24:01', 0, 0),
(6, 1, 118895460, 'кассета 1', 'тринадцать карат', '', 0, '2026-02-09 22:24:05', 0, 0),
(7, 1, 61121507, 'больше не буду', 'тринадцать карат', '', 0, '2026-02-09 22:24:13', 0, 0),
(8, 1, 118895460, 'кассета 1', 'тринадцать карат', '', 0, '2026-02-09 22:24:17', 0, 0),
(9, 1, 394498387, 'жить после', 'тринадцать карат', '', 0, '2026-02-09 22:24:25', 0, 0),
(10, 1, 394498387, 'жить после', 'тринадцать карат', '', 0, '2026-02-15 02:20:26', 3, 0),
(11, 1, 7684050, 'ты', 'тринадцать карат', '', 0, '2026-04-03 20:49:27', 13, 0),
(12, 1, 230123929, 'подружка', 'тринадцать карат', '', 0, '2026-04-03 20:49:46', 1, 0),
(13, 1, 1125704659, 'пока он тебя не бросит', 'тринадцать карат', '', 0, '2026-04-03 20:49:58', 0, 0),
(14, 1, 1776434113, 'одна', 'тринадцать карат', '', 0, '2026-04-03 20:50:02', 4, 0),
(15, 1, 14, 'JUMP!', 'Kai Angel', 'Angel May Cry', 180, '2026-04-03 20:50:15', 6, 0),
(16, 1, 14, 'JUMP!', 'Kai Angel', 'Angel May Cry', 180, '2026-04-03 20:50:35', 126, 1),
(17, 1, 14, 'JUMP!', 'Kai Angel', 'Angel May Cry', 180, '2026-04-03 20:50:48', 7, 0),
(18, 1, 28440621, 'утонуть', 'тринадцать карат', '', 0, '2026-04-03 21:07:57', 32, 0),
(19, 1, 2105098084, 'PARIS 2008', 'Kai Angel', '', 0, '2026-04-05 10:49:10', 5, 0),
(20, 1, 1776434113, 'одна', 'тринадцать карат', '', 0, '2026-04-05 10:49:38', 6, 0),
(21, 1, 28440621, 'утонуть', 'тринадцать карат', '', 0, '2026-04-05 10:56:14', 43, 0),
(22, 1, 515166351, 'JUMP!', 'Kai Angel', '', 0, '2026-04-05 11:01:35', 0, 0),
(23, 1, 1190316052, 'BABY', 'Kai Angel', '', 0, '2026-04-05 11:01:39', 0, 0),
(24, 1, 1190316052, 'BABY', 'Kai Angel', '', 0, '2026-04-05 11:01:43', 0, 0),
(25, 1, 1190316052, 'BABY', 'Kai Angel', '', 0, '2026-04-05 11:01:48', 0, 0),
(26, 1, 1190316052, 'BABY', 'Kai Angel', '', 0, '2026-04-05 11:02:04', 35, 0),
(27, 1, 28440621, 'утонуть', 'тринадцать карат', '', 0, '2026-04-05 11:18:18', 0, 0),
(28, 1, 104355074, 'проваливай', 'тринадцать карат', '', 0, '2026-04-05 11:19:01', 0, 0),
(29, 1, 843219282, 'KYLIE MINOGUE', 'Kai Angel', '', 0, '2026-04-05 11:19:38', 0, 0),
(30, 1, 843219282, 'KYLIE MINOGUE', 'Kai Angel', '', 0, '2026-04-05 11:19:46', 0, 0),
(31, 1, 843219282, 'KYLIE MINOGUE', 'Kai Angel', '', 0, '2026-04-05 11:26:30', 1, 0),
(32, 1, 1190316052, 'BABY', 'Kai Angel', '', 0, '2026-04-05 11:27:22', 48, 0),
(33, 1, 432269760, 'john galliano', 'Kai Angel', '', 0, '2026-04-05 11:33:51', 97, 0),
(34, 2, 432269760, 'john galliano', 'Kai Angel', '', 0, '2026-04-08 13:59:59', 128, 0),
(35, 2, 21, 'john galliano', 'Kai Angel', 'Angel May Cry 2', 180, '2026-04-08 14:01:05', 0, 0),
(36, 2, 5312907, 'white ferrari', 'Kai Angel', '', 0, '2026-04-08 14:01:09', 110, 1),
(37, 2, 628042091, '$$$', 'Kai Angel', '', 0, '2026-04-08 14:02:27', 0, 0),
(38, 2, 628042091, '$$$', 'Kai Angel', '', 0, '2026-04-08 14:11:36', 0, 0),
(39, 2, 432269760, 'john galliano', 'Kai Angel', '', 0, '2026-04-08 14:23:31', 2, 0),
(40, 2, 230123929, 'подружка', 'тринадцать карат', '', 0, '2026-04-08 14:37:17', 14, 0),
(41, 2, 1, 'кассета 1', 'тринадцать карат', '13 причин почему', 180, '2026-04-08 14:53:27', 4, 0),
(42, 2, 5312907, 'white ferrari', 'Kai Angel', '', 0, '2026-04-08 14:55:51', 0, 0),
(43, 2, 230123929, 'подружка', 'тринадцать карат', '', 0, '2026-04-08 15:01:26', 0, 0),
(44, 2, 230123929, 'подружка', 'тринадцать карат', '', 0, '2026-04-08 15:01:34', 0, 0),
(45, 2, 1720292342, 'DEAD MEN WALKING', 'Kai Angel', '', 0, '2026-04-08 15:01:39', 0, 0),
(46, 2, 230123929, 'подружка', 'тринадцать карат', '', 0, '2026-04-08 15:01:55', 0, 0),
(47, 2, 1720292342, 'DEAD MEN WALKING', 'Kai Angel', '', 0, '2026-04-08 15:01:59', 0, 0),
(48, 2, 230123929, 'подружка', 'тринадцать карат', '', 0, '2026-04-08 15:02:12', 0, 0),
(49, 2, 5312907, 'white ferrari', 'Kai Angel', '', 0, '2026-04-08 15:02:43', 0, 0),
(50, 2, 1263288517, 'millions', 'Kai Angel', '', 0, '2026-04-08 15:06:19', 0, 0),
(51, 2, 230123929, 'подружка', 'тринадцать карат', '', 0, '2026-04-08 15:06:20', 40, 0),
(52, 2, 230123929, 'подружка', 'тринадцать карат', '', 0, '2026-04-09 11:01:47', 0, 0),
(53, 2, 5312907, 'white ferrari', 'Kai Angel', '', 0, '2026-04-09 11:01:48', 0, 0),
(54, 2, 230123929, 'подружка', 'тринадцать карат', '', 0, '2026-04-09 11:01:49', 0, 0),
(55, 2, 28, 'ты похож на кота', 'zhanulka', 'испанский стыд', 191, '2026-04-09 11:07:20', 23, 0),
(56, 2, 5312907, 'white ferrari', 'Kai Angel', '', 0, '2026-04-09 11:13:32', 0, 0),
(57, 2, 28440621, 'утонуть', 'тринадцать карат', '', 0, '2026-04-09 11:13:35', 0, 0),
(58, 2, 628042091, '$$$', 'Kai Angel', '', 0, '2026-04-09 11:13:35', 0, 0),
(59, 2, 1268231266, 'во снах', 'тринадцать карат', '', 0, '2026-04-09 11:13:36', 0, 0),
(60, 2, 515166351, 'JUMP!', 'Kai Angel', '', 0, '2026-04-09 11:13:36', 0, 0),
(61, 2, 843219282, 'KYLIE MINOGUE', 'Kai Angel', '', 0, '2026-04-09 11:13:37', 0, 0),
(62, 2, 631488819, 'smirnoff ice', 'Kai Angel', '', 0, '2026-04-09 11:13:37', 0, 0),
(63, 2, 1190316052, 'BABY', 'Kai Angel', '', 0, '2026-04-09 11:13:37', 0, 0),
(64, 2, 1929381050, 'ANGLE MAY CRY', 'Kai Angel', '', 0, '2026-04-09 11:13:38', 0, 0),
(65, 2, 1263288517, 'millions', 'Kai Angel', '', 0, '2026-04-09 11:13:38', 0, 0),
(66, 2, 799123797, 'кассета 6', 'тринадцать карат', '', 0, '2026-04-09 11:13:39', 0, 0),
(67, 2, 24, 'metallica', 'Kai Angel', 'Angel May Cry 2', 180, '2026-04-09 11:13:39', 0, 0),
(68, 2, 14, 'JUMP!', 'Kai Angel', 'Angel May Cry', 180, '2026-04-09 11:13:40', 0, 0),
(69, 2, 13, 'жить после', 'тринадцать карат', '13 причин почему', 180, '2026-04-09 11:13:40', 0, 0),
(70, 2, 1, 'кассета 1', 'тринадцать карат', '13 причин почему', 180, '2026-04-09 11:13:41', 0, 0),
(71, 2, 12, 'одна', 'тринадцать карат', '13 причин почему', 180, '2026-04-09 11:13:41', 0, 0),
(72, 2, 15, 'KYLIE MINOGUE', 'Kai Angel', 'Angel May Cry', 180, '2026-04-09 11:13:41', 0, 0),
(73, 2, 9, 'подружка', 'тринадцать карат', '13 причин почему', 180, '2026-04-09 11:13:42', 0, 0),
(74, 2, 5, 'ты', 'тринадцать карат', '13 причин почему', 180, '2026-04-09 11:13:43', 0, 0),
(75, 2, 25, 'laperouse', 'Kai Angel', 'Angel May Cry 2', 180, '2026-04-09 11:13:43', 0, 0),
(76, 2, 26, 'white ferrari', 'Kai Angel', 'Angel May Cry 2', 180, '2026-04-09 11:13:43', 0, 0),
(77, 2, 17, 'BABY', 'Kai Angel', 'Angel May Cry', 180, '2026-04-09 11:13:44', 0, 0),
(78, 2, 2, 'утонуть', 'тринадцать карат', '13 причин почему', 180, '2026-04-09 11:13:44', 0, 0),
(79, 2, 28, 'ты похож на кота', 'zhanulka', 'испанский стыд', 191, '2026-04-09 11:13:44', 0, 0),
(80, 2, 18, '$$$', 'Kai Angel', 'Angel May Cry', 180, '2026-04-09 11:13:44', 0, 0),
(81, 2, 21, 'john galliano', 'Kai Angel', 'Angel May Cry 2', 180, '2026-04-09 11:13:45', 0, 0),
(82, 2, 21, 'john galliano', 'Kai Angel', 'Angel May Cry 2', 180, '2026-04-09 11:18:19', 1, 0),
(83, 2, 1916343973, 'BAD BITCHES ONLY', 'Kai Angel', '', 0, '2026-04-09 12:30:41', 0, 0),
(84, 2, 1361750933, '10-13', 'PHARAOH', '', 0, '2026-04-09 12:30:50', 0, 0),
(85, 2, 798763765, 'Гринч', 'Платина feat. LOVV66', '', 0, '2026-04-09 12:30:51', 0, 0),
(86, 2, 432269760, 'john galliano', 'Kai Angel', '', 0, '2026-04-21 20:37:38', 15, 0),
(87, 2, 515166351, 'JUMP!', 'Kai Angel', '', 0, '2026-04-28 11:16:49', 0, 0),
(88, 2, 515166351, 'JUMP!', 'Kai Angel', '', 0, '2026-04-28 14:55:46', 0, 0),
(89, 2, 29, 'До Рая', 'eizer01', 'До рая', 126, '2026-04-28 14:55:49', 126, 1),
(90, 2, 13, 'жить после', 'тринадцать карат', '13 причин почему', 180, '2026-04-28 14:55:56', 0, 0),
(91, 2, 13, 'жить после', 'тринадцать карат', '13 причин почему', 180, '2026-04-28 15:06:42', 3, 0),
(92, 2, 13, 'жить после', 'тринадцать карат', '13 причин почему', 180, '2026-04-28 16:06:40', 0, 0),
(93, 2, 25, 'laperouse', 'Kai Angel', 'Angel May Cry 2', 180, '2026-04-28 16:06:41', 0, 0),
(94, 2, 29, 'До Рая', 'eizer01', 'До рая', 126, '2026-04-28 16:06:42', 8, 0),
(95, 2, 432269760, 'john galliano', 'Kai Angel', '', 0, '2026-05-05 14:12:01', 138, 1),
(96, 2, 230123929, 'подружка', 'тринадцать карат', '', 0, '2026-05-05 14:12:22', 55, 1),
(97, 2, 1853247571, 'ты похож на кота', 'zhanulka', '', 0, '2026-05-05 14:12:32', 9, 0),
(98, 2, 1853247571, 'ты похож на кота', 'zhanulka', '', 0, '2026-05-05 14:12:48', 48, 0),
(99, 2, 230123929, 'подружка', 'тринадцать карат', '', 0, '2026-05-05 14:13:00', 29, 0),
(100, 2, 1125704659, 'пока он тебя не бросит', 'тринадцать карат', '', 0, '2026-05-05 14:28:56', 0, 0),
(101, 2, 1853247571, 'ты похож на кота', 'zhanulka', '', 0, '2026-05-05 14:28:59', 9, 0),
(102, 2, 1853247571, 'ты похож на кота', 'zhanulka', '', 0, '2026-05-05 14:29:11', 111, 0),
(103, 2, 628042091, '$$$', 'Kai Angel', '', 0, '2026-05-05 14:31:50', 0, 0),
(104, 2, 1947750514, 'metallica', 'Kai Angel', '', 0, '2026-05-05 14:31:53', 1, 0),
(105, 2, 29, 'До Рая', 'eizer01', 'До рая', 126, '2026-05-05 14:32:55', 31, 0),
(106, 2, 29, 'До Рая', 'eizer01', 'До рая', 126, '2026-05-05 14:33:30', 0, 0),
(107, 2, 29, 'До Рая', 'eizer01', 'До рая', 126, '2026-05-05 14:33:31', 35, 0),
(108, 2, 29, 'До Рая', 'eizer01', 'До рая', 126, '2026-05-05 14:33:31', 0, 0),
(109, 2, 230123929, 'подружка', 'тринадцать карат', '', 0, '2026-05-05 14:44:34', 55, 1),
(110, 2, 5312907, 'white ferrari', 'Kai Angel', '', 0, '2026-05-05 14:45:29', 10, 0),
(111, 2, 5312907, 'white ferrari', 'Kai Angel', '', 0, '2026-05-05 14:52:26', 21, 0),
(112, 2, 5312907, 'white ferrari', 'Kai Angel', '', 0, '2026-05-06 13:44:23', 110, 1),
(113, 2, 1401865148, 'laperouse', 'Kai Angel', '', 0, '2026-05-06 13:46:13', 129, 1),
(114, 2, 1853247571, 'ты похож на кота', 'zhanulka', '', 0, '2026-05-06 13:48:23', 184, 0),
(115, 2, 29, 'До Рая', 'eizer01', 'До рая', 126, '2026-05-06 21:21:50', 19, 0),
(116, 2, 29, 'До Рая', 'eizer01', 'До рая', 126, '2026-05-06 21:24:22', 21, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tracks`
--

CREATE TABLE `tracks` (
  `id` int(11) NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `artist` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `album` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `album_type` enum('album','ep','single') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'album',
  `duration` int(11) DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cover` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `video_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `explicit` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `tracks`
--

INSERT INTO `tracks` (`id`, `title`, `artist`, `album`, `album_type`, `duration`, `file_path`, `cover`, `created_at`, `video_url`, `explicit`) VALUES
(1, 'кассета 1', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/trinadcat_karat_kasseta_1.mp3', 'tracks/covers/m1000x1000.jpeg', '2026-02-09 22:16:53', NULL, 0),
(2, 'утонуть', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/утонуть.mp3', 'tracks/covers/m1000x1000.jpeg', '2026-02-09 22:16:53', NULL, 0),
(3, 'во снах', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/во снах.mp3', 'tracks/covers/m1000x1000.jpeg', '2026-02-09 22:16:53', NULL, 0),
(4, 'проваливай', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/проваливай.mp3', 'tracks/covers/m1000x1000.jpeg', '2026-02-09 22:16:53', NULL, 0),
(5, 'ты', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/ты.mp3', 'tracks/covers/m1000x1000.jpeg', '2026-02-09 22:16:53', NULL, 0),
(6, 'кассета 6', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/кассета 6.mp3', 'tracks/covers/m1000x1000.jpeg', '2026-02-09 22:16:53', NULL, 0),
(7, 'давай расскажем', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/давай расскажем.mp3', 'tracks/covers/m1000x1000.jpeg', '2026-02-09 22:16:53', NULL, 0),
(8, 'пока он тебя не бросит', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/пока он тебя не бросит.mp3', 'tracks/covers/m1000x1000.jpeg', '2026-02-09 22:16:53', NULL, 0),
(9, 'подружка', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/trinadcat_karat_kasseta_1.mp3', 'tracks/covers/m1000x1000.jpeg', '2026-02-09 22:16:53', NULL, 0),
(10, 'больше не буду', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/тринадцать_карат_Три_больше_не_буду.mp3', 'tracks/covers/m1000x1000.jpeg', '2026-02-09 22:16:53', NULL, 0),
(11, 'научился летать', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/научился_летать_тринадцать_карат.m4a', 'tracks/covers/m1000x1000.jpeg', '2026-02-09 22:16:53', NULL, 0),
(12, 'одна', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/trinadcat_karat_kasseta_1.mp3', 'tracks/covers/m1000x1000.jpeg', '2026-02-09 22:16:53', NULL, 0),
(13, 'жить после', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/жить после - тринадцать карат.m4a', 'tracks/covers/m1000x1000.jpeg', '2026-02-09 22:16:53', NULL, 0),
(14, 'JUMP!', 'Kai Angel', 'Angel May Cry', 'album', 180, 'tracks/music/Kai Angel-JUMP!.mp3', 'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg', '2026-02-09 22:16:53', NULL, 0),
(15, 'KYLIE MINOGUE', 'Kai Angel', 'Angel May Cry', 'album', 180, 'tracks/music/Kai Angel-KYLIE MINOGUE.mp3', 'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg', '2026-02-09 22:16:53', NULL, 0),
(16, 'PARIS 2008', 'Kai Angel', 'Angel May Cry', 'album', 180, 'tracks/music/Kai Angel-PARIS 2008.mp3', 'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg', '2026-02-09 22:16:53', NULL, 0),
(17, 'BABY', 'Kai Angel', 'Angel May Cry', 'album', 180, 'tracks/music/Kai Angel-BABY.mp3', 'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg', '2026-02-09 22:16:53', NULL, 0),
(18, '$$$', 'Kai Angel', 'Angel May Cry', 'album', 180, 'tracks/music/Kai Angel-$$$.mp3', 'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg', '2026-02-09 22:16:53', NULL, 0),
(19, 'DEAD MEN WALKING', 'Kai Angel', 'Angel May Cry', 'album', 180, 'tracks/music/Kai Angel-DEAD MEN WALKING.mp3', 'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg', '2026-02-09 22:16:53', NULL, 0),
(20, 'ANGLE MAY CRY', 'Kai Angel', 'Angel May Cry', 'album', 180, 'tracks/music/Kai Angel-ANGEL MAY CRY.mp3', 'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg', '2026-02-09 22:16:53', NULL, 0),
(21, 'john galliano', 'Kai Angel', 'Angel May Cry 2', 'album', 180, 'tracks/music/Kai Angel - john galliano.mp3', 'tracks/covers/Снимок экрана 2025-07-14 в 07.03.03.png', '2026-02-09 22:16:53', NULL, 0),
(22, 'millions', 'Kai Angel', 'Angel May Cry 2', 'album', 180, 'tracks/music/Kai Angel - millions.mp3', 'tracks/covers/Снимок экрана 2025-07-14 в 07.03.03.png', '2026-02-09 22:16:53', NULL, 0),
(23, 'i hate fashion shows', 'Kai Angel', 'Angel May Cry 2', 'album', 180, 'ttracks/music/Kai Angel - i hate fashion shows.mp3', 'tracks/covers/Снимок экрана 2025-07-14 в 07.03.03.png', '2026-02-09 22:16:53', NULL, 0),
(24, 'metallica', 'Kai Angel', 'Angel May Cry 2', 'album', 180, 'tracks/music/Kai Angel - metallica.mp3', 'tracks/covers/Снимок экрана 2025-07-14 в 07.03.03.png', '2026-02-09 22:16:53', NULL, 0),
(25, 'laperouse', 'Kai Angel', 'Angel May Cry 2', 'album', 180, 'tracks/music/Kai Angel - laperouse.mp3', 'tracks/covers/Снимок экрана 2025-07-14 в 07.03.03.png', '2026-02-09 22:16:53', NULL, 0),
(26, 'white ferrari', 'Kai Angel', 'Angel May Cry 2', 'album', 180, 'tracks/music/Kai Angel - white ferrari.mp3', 'tracks/covers/Снимок экрана 2025-07-14 в 07.03.03.png', '2026-02-09 22:16:53', NULL, 0),
(27, 'smirnoff ice', 'Kai Angel', 'Angel May Cry 2', 'album', 180, 'tracks/music/smirnoff ice.mp3', 'tracks/covers/Снимок экрана 2025-07-14 в 07.03.03.png', '2026-02-09 22:16:53', NULL, 0),
(28, 'ты похож на кота', 'zhanulka', 'испанский стыд', 'single', 191, 'tracks/music/zhanulka_-_ty_pohozh_na_kota_(SkySound.cc).mp3', 'tracks/covers/https___images-genius-com_d0fd846c081d1a522540ba716c5ab604-1000x1000x1.png', '2026-04-09 11:06:59', '', 0),
(29, 'До Рая', 'eizer01', 'До рая', 'single', 126, 'tracks/music/До Рая.mp3', 'tracks/covers/jiadpmb4wv2emnj4-oxnueyysil8k2fca_mu4r63ztqpv_giq0lptuipthqr_-rim4rl58ninc7vfyidgotmahin-20260421-204758-881011.jpg', '2026-04-21 20:47:58', '', 0);

-- --------------------------------------------------------

--
-- Структура таблицы `track_artists`
--

CREATE TABLE `track_artists` (
  `id` int(11) NOT NULL,
  `track_id` int(11) NOT NULL,
  `artist` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('primary','featured') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'featured',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `track_genres`
--

CREATE TABLE `track_genres` (
  `track_id` int(11) NOT NULL,
  `genre_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `track_tags`
--

CREATE TABLE `track_tags` (
  `track_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `created_at`) VALUES
(1, '123', '$2y$10$HvyWhvRyfU25SpTA1Aucte6OW/dKOnII82l0VKRBpni9rRsjGt9Nu', 'artem@gmail.com', '2026-02-09 22:22:43'),
(2, 'admin', '$2y$10$LEvlp.vNoGCH4JsVPYyjb.6Foik2L9p0vo2b2u8eaV3bNH8z6x6Zu', 'admin@test.com', '2026-04-08 13:53:54');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `album_artists`
--
ALTER TABLE `album_artists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_album_artist_role` (`album`,`artist`,`role`);

--
-- Индексы таблицы `album_likes`
--
ALTER TABLE `album_likes`
  ADD PRIMARY KEY (`user_id`,`album_title`),
  ADD KEY `idx_album_likes_user_created` (`user_id`,`created_at`);

--
-- Индексы таблицы `artists`
--
ALTER TABLE `artists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Индексы таблицы `genres`
--
ALTER TABLE `genres`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Индексы таблицы `history`
--
ALTER TABLE `history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `track_id` (`track_id`);

--
-- Индексы таблицы `likes`
--
ALTER TABLE `likes`
  ADD PRIMARY KEY (`user_id`,`track_id`),
  ADD UNIQUE KEY `uniq_likes_user_track` (`user_id`,`track_id`),
  ADD KEY `track_id` (`track_id`),
  ADD KEY `idx_likes_user_created` (`user_id`,`created_at`);

--
-- Индексы таблицы `listening_stats`
--
ALTER TABLE `listening_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_stat` (`user_id`,`stat_type`,`stat_name`,`period_type`,`period_start`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_stat_type` (`stat_type`),
  ADD KEY `idx_period` (`period_type`,`period_start`);

--
-- Индексы таблицы `lyrics`
--
ALTER TABLE `lyrics`
  ADD PRIMARY KEY (`track_id`);

--
-- Индексы таблицы `playlists`
--
ALTER TABLE `playlists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `playlist_tracks`
--
ALTER TABLE `playlist_tracks`
  ADD PRIMARY KEY (`playlist_id`,`track_id`),
  ADD KEY `track_id` (`track_id`);

--
-- Индексы таблицы `play_history`
--
ALTER TABLE `play_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_track_id` (`track_id`),
  ADD KEY `idx_played_at` (`played_at`);

--
-- Индексы таблицы `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Индексы таблицы `tracks`
--
ALTER TABLE `tracks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tracks_artist_id` (`artist`,`id`),
  ADD KEY `idx_tracks_album_id` (`album`,`id`);

--
-- Индексы таблицы `track_artists`
--
ALTER TABLE `track_artists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_track_artist_role` (`track_id`,`artist`,`role`),
  ADD KEY `idx_track_artists_track_role` (`track_id`,`role`);

--
-- Индексы таблицы `track_genres`
--
ALTER TABLE `track_genres`
  ADD PRIMARY KEY (`track_id`,`genre_id`),
  ADD KEY `genre_id` (`genre_id`);

--
-- Индексы таблицы `track_tags`
--
ALTER TABLE `track_tags`
  ADD PRIMARY KEY (`track_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `album_artists`
--
ALTER TABLE `album_artists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `artists`
--
ALTER TABLE `artists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `genres`
--
ALTER TABLE `genres`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `history`
--
ALTER TABLE `history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `listening_stats`
--
ALTER TABLE `listening_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT для таблицы `playlists`
--
ALTER TABLE `playlists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `play_history`
--
ALTER TABLE `play_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT для таблицы `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tracks`
--
ALTER TABLE `tracks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT для таблицы `track_artists`
--
ALTER TABLE `track_artists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `album_likes`
--
ALTER TABLE `album_likes`
  ADD CONSTRAINT `album_likes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `history`
--
ALTER TABLE `history`
  ADD CONSTRAINT `history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `history_ibfk_2` FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `likes`
--
ALTER TABLE `likes`
  ADD CONSTRAINT `likes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `likes_ibfk_2` FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `lyrics`
--
ALTER TABLE `lyrics`
  ADD CONSTRAINT `lyrics_ibfk_1` FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `playlists`
--
ALTER TABLE `playlists`
  ADD CONSTRAINT `playlists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `playlist_tracks`
--
ALTER TABLE `playlist_tracks`
  ADD CONSTRAINT `playlist_tracks_ibfk_1` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `playlist_tracks_ibfk_2` FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `track_genres`
--
ALTER TABLE `track_genres`
  ADD CONSTRAINT `track_genres_ibfk_1` FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `track_genres_ibfk_2` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `track_tags`
--
ALTER TABLE `track_tags`
  ADD CONSTRAINT `track_tags_ibfk_1` FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `track_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
