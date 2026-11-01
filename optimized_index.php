<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Muzic2 - Optimized</title>
    <link rel="stylesheet" href="/muzic2/public/assets/css/style.css">
</head>
<body>
    <header id="main-header">
        <div class="header-content">
            <div class="logo">
                <h1>Muzic2</h1>
            </div>
            <nav class="main-nav">
                <button id="nav-home" class="nav-btn active">Главная</button>
                <button id="nav-search" class="nav-btn">Поиск</button>
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
        // Optimized version using fast APIs
        let currentUser = null;
        let currentPage = 'home';
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            initAuth();
            renderHome();
        });
        
        // Simple auth check
        async function initAuth() {
            try {
                const res = await fetch('/muzic2/src/api/user.php', { credentials: 'include' });
                const data = await res.json();
                currentUser = data.user_id ? data : null;
                updateHeader();
            } catch (e) {
                currentUser = null;
            }
        }
        
        // Update header
        function updateHeader() {
            const userPanel = document.getElementById('user-panel');
            if (currentUser) {
                userPanel.innerHTML = `
                    <span>Привет, ${currentUser.username}</span>
                    <button onclick="logout()">Выйти</button>
                `;
            } else {
                userPanel.innerHTML = `
                    <button onclick="showLogin()">Войти</button>
                `;
            }
        }
        
        // Simple logout
        async function logout() {
            await fetch('/muzic2/src/api/logout.php', { credentials: 'include' });
            currentUser = null;
            updateHeader();
            renderHome();
        }
        
        // Show login
        function showLogin() {
            const username = prompt('Логин:');
            const password = prompt('Пароль:');
            if (username && password) {
                login(username, password);
            }
        }
        
        // Simple login
        async function login(username, password) {
            try {
                const res = await fetch('/muzic2/src/api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password }),
                    credentials: 'include'
                });
                const data = await res.json();
                if (res.ok) {
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
        
        // Render home page using optimized API
        async function renderHome() {
            const mainContent = document.getElementById('main-content');
            mainContent.innerHTML = '<div class="loading">Загрузка главной...</div>';
            
            try {
                // Use optimized home API with limits
                const res = await fetch('/muzic2/public/src/api/home.php?limit_tracks=10&limit_albums=10', { credentials: 'include' });
                const data = await res.json();
                
                mainContent.innerHTML = `
                    <div class="home">
                        <h2>Главная</h2>
                        <section class="tracks-section">
                            <h3>Треки</h3>
                            <div class="tracks-grid">
                                ${(data.tracks || []).map(track => `
                                    <div class="track-card">
                                        <img src="/muzic2/${track.cover || 'tracks/covers/placeholder.png'}" alt="cover" loading="lazy">
                                        <h4>${track.title}</h4>
                                        <p>${track.artist}</p>
                                    </div>
                                `).join('')}
                            </div>
                        </section>
                        <section class="albums-section">
                            <h3>Альбомы</h3>
                            <div class="albums-grid">
                                ${(data.albums || []).map(album => `
                                    <div class="album-card">
                                        <img src="/muzic2/${album.cover || 'tracks/covers/placeholder.png'}" alt="cover" loading="lazy">
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
        
        // Render search page
        async function renderSearch() {
            const mainContent = document.getElementById('main-content');
            mainContent.innerHTML = `
                <div class="search">
                    <h2>Поиск</h2>
                    <input type="text" id="search-input" placeholder="Поиск треков, альбомов, артистов...">
                    <button onclick="doSearch()">Поиск</button>
                    <div id="search-results"></div>
                </div>
            `;
        }
        
        // Do search
        async function doSearch() {
            const query = document.getElementById('search-input').value;
            if (!query) return;
            
            const results = document.getElementById('search-results');
            results.innerHTML = '<div class="loading">Поиск...</div>';
            
            try {
                const res = await fetch(`/muzic2/src/api/search.php?q=${encodeURIComponent(query)}`, { credentials: 'include' });
                const data = await res.json();
                
                results.innerHTML = `
                    <div class="search-results">
                        <h3>Треки (${data.tracks?.length || 0})</h3>
                        <div class="tracks-grid">
                            ${(data.tracks || []).slice(0, 10).map(track => `
                                <div class="track-card">
                                    <img src="/muzic2/${track.cover || 'tracks/covers/placeholder.png'}" alt="cover" loading="lazy">
                                    <h4>${track.title}</h4>
                                    <p>${track.artist}</p>
                                </div>
                            `).join('')}
                        </div>
                        <h3>Альбомы (${data.albums?.length || 0})</h3>
                        <div class="albums-grid">
                            ${(data.albums || []).slice(0, 10).map(album => `
                                <div class="album-card">
                                    <img src="/muzic2/${album.cover || 'tracks/covers/placeholder.png'}" alt="cover" loading="lazy">
                                    <h4>${album.title}</h4>
                                    <p>${album.artist}</p>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            } catch (e) {
                results.innerHTML = '<div class="error">Ошибка поиска</div>';
            }
        }
        
        // Render my music page using optimized API
        async function renderMyMusic() {
            const mainContent = document.getElementById('main-content');
            mainContent.innerHTML = '<div class="loading">Загрузка моей музыки...</div>';
            
            if (!currentUser) {
                mainContent.innerHTML = '<div class="error">Войдите в систему</div>';
                return;
            }
            
            try {
                // Use optimized playlists API
                const res = await fetch('/muzic2/src/api/playlists_fast.php', { credentials: 'include' });
                const playlists = await res.json();
                
                mainContent.innerHTML = `
                    <div class="my-music">
                        <h2>Моя музыка</h2>
                        <section class="playlists-section">
                            <h3>Плейлисты</h3>
                            <div class="playlists-grid">
                                ${playlists.map(pl => `
                                    <div class="playlist-card" onclick="openPlaylist(${pl.id}, '${pl.name}')">
                                        <img src="/muzic2/${pl.cover || 'tracks/covers/placeholder.png'}" alt="cover" loading="lazy">
                                        <h4>${pl.name}</h4>
                                        <p>${pl.track_count || 0} треков</p>
                                    </div>
                                `).join('')}
                            </div>
                        </section>
                    </div>
                    <div id="playlist-view"></div>
                `;
            } catch (e) {
                mainContent.innerHTML = '<div class="error">Ошибка загрузки</div>';
            }
        }
        
        // Open playlist using optimized API
        async function openPlaylist(playlistId, playlistName) {
            const view = document.getElementById('playlist-view');
            if (!view) return;
            
            view.innerHTML = '<div class="loading">Загрузка плейлиста...</div>';
            
            try {
                const res = await fetch(`/muzic2/src/api/playlists_fast.php?playlist_id=${playlistId}`, { credentials: 'include' });
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
        
        document.getElementById('nav-search').onclick = () => {
            currentPage = 'search';
            renderSearch();
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
