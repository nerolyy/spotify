<?php
session_start();
require_once __DIR__ . '/src/config/db.php';

echo "<h2>Проверка авторизации</h2>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>User ID в сессии: " . ($_SESSION['user_id'] ?? 'НЕТ') . "</p>";

if (isset($_SESSION['user_id'])) {
    echo "<p style='color: green;'>✅ Пользователь авторизован!</p>";
    
    try {
        $db = get_db_connection();
        
        // Get user info
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "<p>Логин: " . htmlspecialchars($user['username']) . "</p>";
            echo "<p>Email: " . htmlspecialchars($user['email']) . "</p>";
        }
        
        // Check playlists
        $stmt = $db->prepare('SELECT * FROM playlists WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$_SESSION['user_id']]);
        $playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Плейлисты пользователя:</h3>";
        if (empty($playlists)) {
            echo "<p style='color: orange;'>У пользователя нет плейлистов!</p>";
            
            // Create "Любимые треки" playlist
            $createStmt = $db->prepare('INSERT INTO playlists (user_id, name, is_public) VALUES (?, ?, 0)');
            $result = $createStmt->execute([$_SESSION['user_id'], 'Любимые треки']);
            if ($result) {
                echo "<p style='color: green;'>✅ Плейлист 'Любимые треки' создан!</p>";
            }
        } else {
            foreach ($playlists as $playlist) {
                echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
                echo "<strong>ID:</strong> " . $playlist['id'] . " | ";
                echo "<strong>Название:</strong> " . htmlspecialchars($playlist['name']) . " | ";
                echo "<strong>Создан:</strong> " . $playlist['created_at'];
                echo "</div>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Ошибка БД: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ Пользователь НЕ авторизован!</p>";
    echo "<p><a href='/muzic2/public/index.php'>Перейти на главную</a></p>";
}
?>
