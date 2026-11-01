<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Muzic2 - Ultra Fast</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #1a1a1a; color: #fff; }
        header { background: #2a2a2a; padding: 1rem; border-bottom: 1px solid #333; }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; }
        .logo h1 { color: #fff; }
        nav { display: flex; gap: 1rem; }
        .nav-btn { background: transparent; border: none; color: #ccc; padding: 0.5rem 1rem; cursor: pointer; }
        .nav-btn.active { color: #fff; background: #333; }
        .user-section { display: flex; gap: 1rem; align-items: center; }
        main { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .loading { text-align: center; padding: 2rem; color: #666; }
        .error { text-align: center; padding: 2rem; color: #f44336; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin: 1rem 0; }
        .card { background: #2a2a2a; border-radius: 8px; padding: 1rem; text-align: center; }
        .card img { width: 100%; height: 150px; object-fit: cover; border-radius: 4px; margin-bottom: 0.5rem; }
        .card h4 { margin-bottom: 0.25rem; }
        .card p { color: #666; font-size: 0.9rem; }
        button { background: #4CAF50; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; }
        button:hover { background: #45a049; }
        .search { margin: 2rem 0; }
        .search input { width: 300px; padding: 0.5rem; background: #2a2a2a; border: 1px solid #333; color: #fff; border-radius: 4px; }
        .playlist-section { margin-top: 2rem; }
        .track-row { display: flex; justify-content: space-between; padding: 0.5rem; border-bottom: 1px solid #333; }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo">
                <h1>Muzic2</h1>
            </div>
            <nav>
                <button id="nav-home" class="nav-btn active">Главная</button>
                <button id="nav-my-music" class="nav-btn">Моя музыка</button>
            </nav>
            <div class="user-section">
                <div id="user-panel"></div>
            </div>
        </div>
    </header>

    <main id="main-content">
        <div class="loading">Загрузка...</div>
    </main>

    <script>
        // Ultra fast version - minimal code
        let currentUser = null;
        let currentPage = 'home';
        
        // Initialize
        document.addEventListener('DOMContentLoaded', init);
        
        async function init() {
            await checkAuth();
            renderHome();
        }
        
        async function checkAuth() {
            try {
                const res = await fetch('/muzic2/ultra_fast_api.php?action=user', { credentials: 'include' });
                const data = await res.json();
                currentUser = data.user_id ? data : null;
                updateHeader();
            } catch (e) {
                currentUser = null;
            }
        }
        
        function updateHeader() {
            const userPanel = document.getElementById('user-panel');
            if (currentUser) {
                userPanel.innerHTML = `
                    <span>Привет, ${currentUser.username}</span>
                    <button onclick="logout()">Выйти</button>
                `;
            } else {
                userPanel.innerHTML = `
                    <button onclick="login()">Войти</button>
                `;
            }
        }
        
        async function login() {
            const username = prompt('Логин:');
            const password = prompt('Пароль:');
            if (!username || !password) return;
            
            try {
                const res = await fetch('/muzic2/ultra_fast_api.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ login: username, password: password }),
                    credentials: 'include'
                });
                const data = await res.json();
                if (data.success) {
                    currentUser = data.user;
                    updateHeader();
                    renderHome();
                } else {
                    alert('Ошибка входа: ' + data.message);
                }
            } catch (e) {
                alert('Ошибка сети');
            }
        }
        
        async function logout() {
            await fetch('/muzic2/src/api/logout.php', { credentials: 'include' });
            currentUser = null;
            updateHeader();
            renderHome();
        }
        
        async function renderHome() {
            const mainContent = document.getElementById('main-content');
            mainContent.innerHTML = '<div class="loading">Загрузка главной...</div>';
            
            try {
                const res = await fetch('/muzic2/ultra_fast_api.php?action=home', { credentials: 'include' });
                const data = await res.json();
                
                mainContent.innerHTML = `
                    <div class="home">
                        <h2>Главная</h2>
                        <section>
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
                        </section>
                        <section>
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
                        </section>
                    </div>
                `;
            } catch (e) {
                mainContent.innerHTML = '<div class="error">Ошибка загрузки</div>';
            }
        }
        
        async function renderMyMusic() {
            const mainContent = document.getElementById('main-content');
            mainContent.innerHTML = '<div class="loading">Загрузка моей музыки...</div>';
            
            if (!currentUser) {
                mainContent.innerHTML = '<div class="error">Войдите в систему</div>';
                return;
            }
            
            try {
                const res = await fetch('/muzic2/ultra_fast_api.php?action=playlists', { credentials: 'include' });
                const playlists = await res.json();
                
                mainContent.innerHTML = `
                    <div class="my-music">
                        <h2>Моя музыка</h2>
                        <div class="grid">
                            ${playlists.map(pl => `
                                <div class="card" onclick="openPlaylist(${pl.id}, '${pl.name}')">
                                    <img src="/muzic2/tracks/covers/placeholder.png" alt="cover">
                                    <h4>${pl.name}</h4>
                                    <p>${pl.track_count || 0} треков</p>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    <div id="playlist-view"></div>
                `;
            } catch (e) {
                mainContent.innerHTML = '<div class="error">Ошибка загрузки</div>';
            }
        }
        
        async function openPlaylist(playlistId, playlistName) {
            const view = document.getElementById('playlist-view');
            if (!view) return;
            
            view.innerHTML = '<div class="loading">Загрузка плейлиста...</div>';
            
            try {
                const res = await fetch(`/muzic2/ultra_fast_api.php?action=playlist_tracks&playlist_id=${playlistId}`, { credentials: 'include' });
                const data = await res.json();
                const tracks = data.tracks || [];
                
                view.innerHTML = `
                    <div class="playlist-section">
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
                    </div>
                `;
            } catch (e) {
                view.innerHTML = '<div class="error">Ошибка загрузки плейлиста</div>';
            }
        }
        
        // Navigation
        document.getElementById('nav-home').onclick = () => {
            currentPage = 'home';
            renderHome();
            updateNav();
        };
        
        document.getElementById('nav-my-music').onclick = () => {
            currentPage = 'my-music';
            renderMyMusic();
            updateNav();
        };
        
        function updateNav() {
            document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(`nav-${currentPage}`).classList.add('active');
        }
    </script>
</body>
</html>
