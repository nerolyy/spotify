<?php
// Minimal, idempotent DB setup helper for MAMP/CLI.
// Usage (CLI): php scripts/setup_db.php
// Usage (HTTP): /muzic2/scripts/setup_db.php (outputs JSON)

declare(strict_types=1);

$isHttp = (PHP_SAPI !== 'cli');
if ($isHttp) { header('Content-Type: application/json; charset=utf-8'); }

require_once __DIR__ . '/../src/config/db.php';

function respond($ok, $data = []){
    global $isHttp;
    $payload = $data + ['success' => (bool)$ok];
    if ($isHttp) { echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); }
    else { fwrite(STDOUT, ($ok?"OK":"ERR")."\n".json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n"); }
    exit($ok?0:1);
}

function file_contents_or_null(string $path): ?string {
    if (!is_file($path)) return null;
    $s = @file_get_contents($path);
    return ($s===false)?null:$s;
}

function table_exists(PDO $db, string $table): bool {
    try {
        $st = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function index_exists(PDO $db, string $table, string $indexName): bool {
    try {
        $st = $db->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1');
        $st->execute([$table, $indexName]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function ensure_index(PDO $db, array &$executed, string $table, string $indexName, string $ddl): void {
    if (!table_exists($db, $table)) return;
    if (index_exists($db, $table, $indexName)) return;
    $db->exec($ddl);
    $executed[] = "index:$table.$indexName";
}

try {
    // Сначала пытаемся подключиться к БД
    $db = null;
    $connectionErrors = [];
    
    try {
        $db = get_db_connection();
    } catch (Throwable $e) {
        // Если не удалось подключиться к БД, возможно БД не существует
        // Пробуем подключиться без указания БД и создать её
        $host = 'localhost';
        $username = 'root';
        $password = 'root';
        $ports = [3306, 8889, 3307];
        
        foreach ($ports as $port) {
            try {
                $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
                $pdo = new PDO($dsn, $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Создаём БД если её нет
                $pdo->exec("CREATE DATABASE IF NOT EXISTS muzic2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                // Теперь подключаемся к созданной БД
                $dsn = "mysql:host=$host;port=$port;dbname=muzic2;charset=utf8mb4";
                $db = new PDO($dsn, $username, $password);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                break;
            } catch (PDOException $e2) {
                $connectionErrors[] = "Порт $port: " . $e2->getMessage();
                continue;
            }
        }
        
        if (!$db) {
            $errorMsg = "Не удалось подключиться к MySQL.\n\n";
            $errorMsg .= "Проверьте:\n";
            $errorMsg .= "1. MAMP запущен и MySQL работает (зелёный индикатор)\n";
            $errorMsg .= "2. Порт MySQL в MAMP (обычно 3306 или 8889)\n";
            $errorMsg .= "3. Логин и пароль в src/config/db.php\n\n";
            $errorMsg .= "Ошибки подключения:\n" . implode("\n", $connectionErrors);
            throw new Exception($errorMsg);
        }
    }

    // Detect if main tables exist (tracks is core for the app)
    $hasTracks = false;
    try {
        $db->query("SELECT 1 FROM tracks LIMIT 1");
        $hasTracks = true;
    } catch (Throwable $e) { $hasTracks = false; }

    $executed = [];

    // Run schema if tracks table missing
    if (!$hasTracks) {
        $schemaCandidates = [
            __DIR__ . '/../db/schema.sql',
        ];
        foreach ($schemaCandidates as $schemaPath) {
            $sql = file_contents_or_null($schemaPath);
            if ($sql) {
                // Split by ; at line end to avoid common pitfalls
                $stmts = preg_split('/;\s*(\r?\n)+/u', $sql);
                foreach ($stmts as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt === '' || strpos($stmt, '--') === 0) continue;
                    $db->exec($stmt);
                }
                $executed[] = basename($schemaPath);
                break; // first found schema is enough
            }
        }
    }

    // Ensure optional tables that code relies on (defensive)
    try { $db->exec("CREATE TABLE IF NOT EXISTS artists (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL UNIQUE, cover VARCHAR(255), bio TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)"); } catch (Throwable $e) {}
    try { $db->exec("ALTER TABLE tracks ADD COLUMN video_url VARCHAR(500) NULL"); } catch (Throwable $e) {}
    try { $db->exec("ALTER TABLE tracks ADD COLUMN explicit TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
    try { $db->exec("ALTER TABLE playlists ADD COLUMN cover VARCHAR(500) NULL"); } catch (Throwable $e) {}
    try { $db->exec("ALTER TABLE playlist_tracks ADD COLUMN added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Throwable $e) {}
    // Featured artists mapping for tracks
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS track_artists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            track_id INT NOT NULL,
            artist VARCHAR(255) NOT NULL,
            role ENUM('primary','featured') NOT NULL DEFAULT 'featured',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_track_artist_role (track_id, artist, role)
        )");
    } catch (Throwable $e) {}
    // Featured artists mapping for entire albums (to propagate to new tracks)
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS album_artists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            album VARCHAR(255) NOT NULL,
            artist VARCHAR(255) NOT NULL,
            role ENUM('primary','featured') NOT NULL DEFAULT 'featured',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_album_artist_role (album, artist, role)
        )");
    } catch (Throwable $e) {}

    // Performance indexes (idempotent)
    try {
        // likes: accelerate user likes fetch + existence checks
        ensure_index($db, $executed, 'likes', 'idx_likes_user_created', 'CREATE INDEX idx_likes_user_created ON likes (user_id, created_at)');
        ensure_index($db, $executed, 'likes', 'uniq_likes_user_track', 'CREATE UNIQUE INDEX uniq_likes_user_track ON likes (user_id, track_id)');

        // album_likes: accelerate user album likes ORDER BY created_at
        ensure_index($db, $executed, 'album_likes', 'idx_album_likes_user_created', 'CREATE INDEX idx_album_likes_user_created ON album_likes (user_id, created_at)');

        // track_artists: accelerate feats aggregation lookups
        ensure_index($db, $executed, 'track_artists', 'idx_track_artists_track_role', 'CREATE INDEX idx_track_artists_track_role ON track_artists (track_id, role)');

        // tracks: admin filters by artist/album (and ORDER BY id)
        ensure_index($db, $executed, 'tracks', 'idx_tracks_artist_id', 'CREATE INDEX idx_tracks_artist_id ON tracks (artist, id)');
        ensure_index($db, $executed, 'tracks', 'idx_tracks_album_id', 'CREATE INDEX idx_tracks_album_id ON tracks (album, id)');
    } catch (Throwable $e) {
        // ignore: setup should be best-effort and remain idempotent
    }

    // Seeds: optional, run only if tracks is empty
    $shouldSeed = false;
    try { $cnt = (int)$db->query("SELECT COUNT(*) FROM tracks")->fetchColumn(); $shouldSeed = ($cnt === 0); } catch (Throwable $e) {}
    if ($shouldSeed) {
        $seedCandidates = [
            __DIR__ . '/../db/seed.sql',
            __DIR__ . '/../insert_tracks.sql',
        ];
        foreach ($seedCandidates as $seedPath) {
            $sql = file_contents_or_null($seedPath);
            if ($sql) {
                $stmts = preg_split('/;\s*(\r?\n)+/u', $sql);
                foreach ($stmts as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt === '' || strpos($stmt, '--') === 0) continue;
                    $db->exec($stmt);
                }
                $executed[] = basename($seedPath);
                // do not break: allow multiple seed files if both present
            }
        }
    }

    // Final health check
    $health = [
        'tracks' => false,
        'artists' => false,
    ];
    try { $db->query("SELECT 1 FROM tracks LIMIT 1"); $health['tracks']=true; } catch (Throwable $e) {}
    try { $db->query("SELECT 1 FROM artists LIMIT 1"); $health['artists']=true; } catch (Throwable $e) {}

    respond(true, [
        'executed' => $executed,
        'health' => $health,
    ]);
} catch (Throwable $e) {
    respond(false, ['error' => $e->getMessage()]);
}


