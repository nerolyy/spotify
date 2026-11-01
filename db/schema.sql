CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS genres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS tracks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    artist VARCHAR(255) NOT NULL,
    album VARCHAR(255) NOT NULL,
    album_type ENUM('album', 'ep', 'single') NOT NULL DEFAULT 'album',
    duration INT,
    file_path VARCHAR(255) NOT NULL,
    cover VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE track_genres (
    track_id INT,
    genre_id INT,
    PRIMARY KEY (track_id, genre_id),
    FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
);

CREATE TABLE playlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(255) NOT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE playlist_tracks (
    playlist_id INT,
    track_id INT,
    position INT,
    PRIMARY KEY (playlist_id, track_id),
    FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE
);

CREATE TABLE likes (
    user_id INT,
    track_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, track_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE
);

CREATE TABLE history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    track_id INT,
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE
);

CREATE TABLE tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE track_tags (
    track_id INT,
    tag_id INT,
    PRIMARY KEY (track_id, tag_id),
    FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

-- Artist videos table for concert videos, live performances, etc.
CREATE TABLE artist_videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    artist VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    video_path VARCHAR(500) NOT NULL,
    thumbnail VARCHAR(500),
    duration INT,
    video_type ENUM('concert', 'live', 'behind_scenes', 'interview', 'other') DEFAULT 'other',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_artist (artist)
);

-- Track-specific video mapping (optional - if not set, video applies to all artist tracks)
CREATE TABLE track_video_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    track_id INT,
    artist_video_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE,
    FOREIGN KEY (artist_video_id) REFERENCES artist_videos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_track_video (track_id, artist_video_id)
);

-- User preferences for karaoke background
CREATE TABLE user_karaoke_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    background_type ENUM('cover', 'artist_video', 'auto') DEFAULT 'auto',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preference (user_id)
); 

INSERT INTO tracks (title, artist, album, album_type, duration, file_path, cover)
VALUES
('кассета 1', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/trinadcat_karat_kasseta_1.mp3', 'tracks/covers/m1000x1000.jpeg'),
('утонуть', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/утонуть.mp3', 'tracks/covers/m1000x1000.jpeg'),
('во снах', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/во снах.mp3', 'tracks/covers/m1000x1000.jpeg'),
('проваливай', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/проваливай.mp3', 'tracks/covers/m1000x1000.jpeg'),
('ты', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/ты.mp3', 'tracks/covers/m1000x1000.jpeg'),
('кассета 6', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/кассета 6.mp3', 'tracks/covers/m1000x1000.jpeg'),
('давай расскажем', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/давай расскажем.mp3', 'tracks/covers/m1000x1000.jpeg'),
('пока он тебя не бросит', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/пока он тебя не бросит.mp3', 'tracks/covers/m1000x1000.jpeg'),
('подружка', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/trinadcat_karat_kasseta_1.mp3', 'tracks/covers/m1000x1000.jpeg'),
('больше не буду', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/тринадцать_карат_Три_больше_не_буду.mp3', 'tracks/covers/m1000x1000.jpeg'),
('научился летать', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/научился_летать_тринадцать_карат.m4a', 'tracks/covers/m1000x1000.jpeg'),
('одна', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/trinadcat_karat_kasseta_1.mp3', 'tracks/covers/m1000x1000.jpeg'),
('жить после', 'тринадцать карат', '13 причин почему', 'album', 180, 'tracks/music/жить после - тринадцать карат.m4a', 'tracks/covers/m1000x1000.jpeg'),
('JUMP!', 'Kai Angel', 'Angel May Cry', 'album', 180, 'tracks/music/Kai Angel-JUMP!.mp3', 'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg'),
('KYLIE MINOGUE', 'Kai Angel', 'Angel May Cry', 'album', 180, 'tracks/music/Kai Angel-KYLIE MINOGUE.mp3', 'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg'),
('PARIS 2008', 'Kai Angel', 'Angel May Cry', 'album', 180, 'tracks/music/Kai Angel-PARIS 2008.mp3', 'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg'),
('BABY', 'Kai Angel', 'Angel May Cry', 'album', 180, 'tracks/music/Kai Angel-BABY.mp3', 'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg'),
('$$$', 'Kai Angel', 'Angel May Cry', 'album', 180, 'tracks/music/Kai Angel-$$$.mp3', 'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg'),
('DEAD MEN WALKING', 'Kai Angel', 'Angel May Cry', 'album', 180, 'tracks/music/Kai Angel-DEAD MEN WALKING.mp3', 'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg'),
('ANGLE MAY CRY', 'Kai Angel', 'Angel May Cry', 'album', 180, 'tracks/music/Kai Angel-ANGEL MAY CRY.mp3', 'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg'),
('john galliano', 'Kai Angel', 'Angel May Cry 2', 'album', 180, 'tracks/music/Kai Angel - john galliano.mp3', 'tracks/covers/Снимок экрана 2025-07-14 в 07.03.03.png'),
('millions', 'Kai Angel', 'Angel May Cry 2', 'album', 180, 'tracks/music/Kai Angel - millions.mp3', 'tracks/covers/Снимок экрана 2025-07-14 в 07.03.03.png'),
('i hate fashion shows', 'Kai Angel', 'Angel May Cry 2', 'album', 180, 'ttracks/music/Kai Angel - i hate fashion shows.mp3', 'tracks/covers/Снимок экрана 2025-07-14 в 07.03.03.png'),
('metallica', 'Kai Angel', 'Angel May Cry 2', 'album', 180, 'tracks/music/Kai Angel - metallica.mp3', 'tracks/covers/Снимок экрана 2025-07-14 в 07.03.03.png'),
('laperouse', 'Kai Angel', 'Angel May Cry 2', 'album', 180, 'tracks/music/Kai Angel - laperouse.mp3', 'tracks/covers/Снимок экрана 2025-07-14 в 07.03.03.png'),
('white ferrari', 'Kai Angel', 'Angel May Cry 2', 'album', 180, 'tracks/music/Kai Angel - white ferrari.mp3', 'tracks/covers/Снимок экрана 2025-07-14 в 07.03.03.png'),
('smirnoff ice', 'Kai Angel', 'Angel May Cry 2', 'album', 180, 'tracks/music/smirnoff ice.mp3', 'tracks/covers/Снимок экрана 2025-07-14 в 07.03.03.png');


