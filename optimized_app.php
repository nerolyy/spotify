<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Muzic2 - Optimized</title>
    <style>
        body { font-family: Arial; background: #1a1a1a; color: #fff; margin: 0; padding: 20px; }
        .header { background: #2a2a2a; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .nav { display: flex; gap: 10px; }
        .nav button { background: #333; color: #fff; border: none; padding: 8px 16px; cursor: pointer; border-radius: 3px; }
        .nav button.active { background: #4CAF50; }
        .user { float: right; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }
        .card { background: #2a2a2a; padding: 10px; border-radius: 5px; text-align: center; }
        .card img { width: 100%; height: 120px; object-fit: cover; border-radius: 3px; margin-bottom: 8px; }
        .card h4 { margin: 5px 0; font-size: 14px; }
        .card p { color: #666; font-size: 12px; margin: 0; }
        .loading { text-align: center; padding: 40px; color: #666; }
        .error { text-align: center; padding: 40px; color: #f44336; }
        .track-row { display: flex; justify-content: space-between; padding: 8px; border-bottom: 1px solid #333; }
        button { background: #4CAF50; color: white; border: none; padding: 8px 16px; cursor: pointer; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="nav">
            <button id="nav-home" class="active">Главная</button>
            <button id="nav-music">Моя музыка</button>
        </div>
        <div class="user" id="user-panel">
            <button onclick="login()">Войти</button>
        </div>
    </div>

    <div id="content">
        <div class="loading">Загрузка...</div>
    </div>

    <script>
        let user = null;
        let page = 'home';
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            checkUser();
            loadHome();
        });
        
        async function checkUser() {
            try {
                const res = await fetch('/muzic2/src/api/user.php', { credentials: 'include' });
                const data = await res.json();
                user = data.user_id ? data : null;
                updateUserPanel();
            } catch (e) {
                user = null;
            }
        }
        
        function updateUserPanel() {
            const panel = document.getElementById('user-panel');
            if (user) {
                panel.innerHTML = `<span>${user.username}</span> <button onclick="logout()">Выйти</button>`;
            } else {
                panel.innerHTML = '<button onclick="login()">Войти</button>';
            }
        }
        
        async function login() {
            const username = prompt('Логин:');
            const password = prompt('Пароль:');
            if (!username || !password) return;
            
            try {
                const res = await fetch('/muzic2/src/api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ login: username, password: password }),
                    credentials: 'include'
                });
                const data = await res.json();
                if (data.success) {
                    user = data.user;
                    updateUserPanel();
                    loadHome();
                } else {
                    alert('Ошибка входа');
                }
            } catch (e) {
                alert('Ошибка сети');
            }
        }
        
        async function logout() {
            await fetch('/muzic2/src/api/logout.php', { credentials: 'include' });
            user = null;
            updateUserPanel();
            loadHome();
        }
        
        async function loadHome() {
            const content = document.getElementById('content');
            content.innerHTML = '<div class="loading">Загрузка главной...</div>';
            
            try {
                // Use optimized home API
                const res = await fetch('/muzic2/public/src/api/home_fast.php?limit_tracks=10&limit_albums=10', { credentials: 'include' });
                const data = await res.json();
                
                content.innerHTML = `
                    <h2>Главная</h2>
                    <h3>Треки</h3>
                    <div class="grid">
                        ${data.tracks.map(track => `
                            <div class="card">
                                <img src="/muzic2/${track.cover || 'tracks/covers/placeholder.png'}" alt="cover">
                                <h4>${track.title}</h4>
                                <p>${track.artist}</p>
                            </div>
                        `).join('')}
                    </div>
                    <h3>Альбомы</h3>
                    <div class="grid">
                        ${data.albums.map(album => `
                            <div class="card">
                                <img src="/muzic2/${album.cover || 'tracks/covers/placeholder.png'}" alt="cover">
                                <h4>${album.title}</h4>
                                <p>${album.artist}</p>
                            </div>
                        `).join('')}
                    </div>
                    <h3>Артисты</h3>
                    <div class="grid">
                        ${data.artists.map(artist => `
                            <div class="card">
                                <img src="/muzic2/tracks/covers/placeholder.png" alt="cover">
                                <h4>${artist.name}</h4>
                                <p>Артист</p>
                            </div>
                        `).join('')}
                    </div>
                `;
            } catch (e) {
                content.innerHTML = '<div class="error">Ошибка загрузки</div>';
            }
        }
        
        async function loadMusic() {
            const content = document.getElementById('content');
            content.innerHTML = '<div class="loading">Загрузка моей музыки...</div>';
            
            if (!user) {
                content.innerHTML = '<div class="error">Войдите в систему</div>';
                return;
            }
            
            try {
                const res = await fetch('/muzic2/src/api/playlists.php', { credentials: 'include' });
                const data = await res.json();
                const playlists = data.playlists || data;
                
                content.innerHTML = `
                    <h2>Моя музыка</h2>
                    <div class="grid">
                        ${playlists.map(pl => `
                            <div class="card" onclick="openPlaylist(${pl.id}, '${pl.name}')">
                                <img src="/muzic2/${pl.cover || 'tracks/covers/placeholder.png'}" alt="cover">
                                <h4>${pl.name}</h4>
                                <p>${pl.track_count || 0} треков</p>
                            </div>
                        `).join('')}
                    </div>
                    <div id="playlist-view"></div>
                `;
            } catch (e) {
                content.innerHTML = '<div class="error">Ошибка загрузки</div>';
            }
        }
        
        async function openPlaylist(playlistId, playlistName) {
            const view = document.getElementById('playlist-view');
            if (!view) return;
            
            view.innerHTML = '<div class="loading">Загрузка плейлиста...</div>';
            
            try {
                const res = await fetch(`/muzic2/src/api/playlists.php?playlist_id=${playlistId}`, { credentials: 'include' });
                const data = await res.json();
                const tracks = data.tracks || [];
                
                view.innerHTML = `
                    <h3>${playlistName}</h3>
                    <div class="tracks-list">
                        ${tracks.map(track => `
                            <div class="track-row">
                                <span>${track.title}</span>
                                <span>${track.artist}</span>
                                <span>${Math.floor(track.duration / 60)}:${(track.duration % 60).toString().padStart(2, '0')}</span>
                            </div>
                        `).join('')}
                    </div>
                `;
            } catch (e) {
                view.innerHTML = '<div class="error">Ошибка загрузки плейлиста</div>';
            }
        }
        
        // Navigation
        document.getElementById('nav-home').onclick = () => {
            page = 'home';
            loadHome();
            updateNav();
        };
        
        document.getElementById('nav-music').onclick = () => {
            page = 'music';
            loadMusic();
            updateNav();
        };
        
        function updateNav() {
            document.querySelectorAll('.nav button').forEach(btn => btn.classList.remove('active'));
            document.getElementById(`nav-${page}`).classList.add('active');
        }
    </script>
</body>
</html>
