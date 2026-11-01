<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/config/db.php';

// Используем функцию из config/db.php

echo "=== Muzic2 Quick Migration Tool ===\n\n";

$action = $argv[1] ?? 'help';

switch ($action) {
    case 'export':
        echo "Экспорт данных...\n";
        $since = $argv[2] ?? '2024-01-01 00:00:00';
        $outputFile = "muzic2_export_" . date('Y-m-d_H-i-s') . ".json";
        
        try {
            $db = get_db_connection();
            
            // Экспорт всех данных
            $tracks = $db->query("SELECT * FROM tracks ORDER BY id")->fetchAll();
            $artists = $db->query("SELECT * FROM artists ORDER BY id")->fetchAll();
            
            $exportData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'tracks' => $tracks,
                'artists' => $artists
            ];
            
            file_put_contents($outputFile, json_encode($exportData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            echo "Экспорт завершен: $outputFile\n";
            echo "Треков: " . count($tracks) . "\n";
            echo "Артистов: " . count($artists) . "\n";
            
        } catch (Exception $e) {
            echo "Ошибка экспорта: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;
        
    case 'import':
        $inputFile = $argv[2] ?? '';
        if (empty($inputFile) || !file_exists($inputFile)) {
            echo "Использование: php quick_migrate.php import <файл.json>\n";
            exit(1);
        }
        
        echo "Импорт данных из $inputFile...\n";
        
        try {
            $db = get_db_connection();
            $data = json_decode(file_get_contents($inputFile), true);
            
            if (!$data) {
                throw new Exception("Неверный формат файла");
            }
            
            $db->beginTransaction();
            
            // Импорт треков
            if (isset($data['tracks'])) {
                $insertTrack = $db->prepare("
                    INSERT INTO tracks (id, title, artist, album, album_type, duration, file_path, cover, video_url, explicit)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        title = VALUES(title),
                        artist = VALUES(artist),
                        album = VALUES(album),
                        album_type = VALUES(album_type),
                        duration = VALUES(duration),
                        file_path = VALUES(file_path),
                        cover = VALUES(cover),
                        video_url = VALUES(video_url),
                        explicit = VALUES(explicit)
                ");
                
                $imported = 0;
                foreach ($data['tracks'] as $track) {
                    $insertTrack->execute([
                        $track['id'],
                        $track['title'],
                        $track['artist'],
                        $track['album'],
                        $track['album_type'] ?? '',
                        $track['duration'] ?? 0,
                        $track['file_path'],
                        $track['cover'],
                        $track['video_url'] ?? '',
                        $track['explicit'] ?? 0
                    ]);
                    $imported++;
                }
                echo "Импортировано треков: $imported\n";
            }
            
            // Импорт артистов
            if (isset($data['artists'])) {
                $insertArtist = $db->prepare("
                    INSERT INTO artists (id, name, cover, bio)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        cover = VALUES(cover),
                        bio = VALUES(bio)
                ");
                
                $imported = 0;
                foreach ($data['artists'] as $artist) {
                    $insertArtist->execute([
                        $artist['id'],
                        $artist['name'],
                        $artist['cover'] ?? '',
                        $artist['bio'] ?? ''
                    ]);
                    $imported++;
                }
                echo "Импортировано артистов: $imported\n";
            }
            
            $db->commit();
            echo "Импорт завершен успешно!\n";
            
        } catch (Exception $e) {
            $db->rollBack();
            echo "Ошибка импорта: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;
        
    case 'check':
        echo "Проверка состояния базы данных...\n";
        
        try {
            $db = get_db_connection();
            
            $tracksCount = $db->query("SELECT COUNT(*) FROM tracks")->fetchColumn();
            $artistsCount = $db->query("SELECT COUNT(*) FROM artists")->fetchColumn();
            
            echo "Треков в БД: $tracksCount\n";
            echo "Артистов в БД: $artistsCount\n";
            
            // Проверяем наличие медиа файлов
            $tracks = $db->query("SELECT file_path, cover FROM tracks LIMIT 5")->fetchAll();
            $missingFiles = 0;
            
            foreach ($tracks as $track) {
                $audioPath = __DIR__ . '/../' . $track['file_path'];
                $coverPath = __DIR__ . '/../' . $track['cover'];
                
                if (!file_exists($audioPath)) {
                    echo "Отсутствует аудио: " . $track['file_path'] . "\n";
                    $missingFiles++;
                }
                if (!file_exists($coverPath)) {
                    echo "Отсутствует обложка: " . $track['cover'] . "\n";
                    $missingFiles++;
                }
            }
            
            if ($missingFiles === 0) {
                echo "Все проверенные файлы найдены ✓\n";
            } else {
                echo "Найдено отсутствующих файлов: $missingFiles\n";
            }
            
        } catch (Exception $e) {
            echo "Ошибка проверки: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;
        
    case 'help':
    default:
        echo "Использование: php quick_migrate.php <команда> [параметры]\n\n";
        echo "Команды:\n";
        echo "  export [дата]     - Экспорт всех данных в JSON\n";
        echo "  import <файл>     - Импорт данных из JSON\n";
        echo "  check            - Проверка состояния БД и файлов\n";
        echo "  help             - Показать эту справку\n\n";
        echo "Примеры:\n";
        echo "  php quick_migrate.php export\n";
        echo "  php quick_migrate.php export '2024-01-01 00:00:00'\n";
        echo "  php quick_migrate.php import muzic2_export_2024-01-15_14-30-00.json\n";
        echo "  php quick_migrate.php check\n";
        break;
}
?>
