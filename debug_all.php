<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug All APIs</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #1a1a1a; color: #fff; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #333; border-radius: 5px; }
        .success { color: #4CAF50; }
        .error { color: #f44336; }
        .warning { color: #ff9800; }
        .info { color: #2196F3; }
        pre { background: #2a2a2a; padding: 10px; border-radius: 3px; overflow-x: auto; }
        button { background: #4CAF50; color: white; border: none; padding: 10px 20px; margin: 5px; border-radius: 3px; cursor: pointer; }
        button:hover { background: #45a049; }
        .test-result { margin: 10px 0; padding: 10px; border-left: 4px solid #333; }
        .test-result.success { border-left-color: #4CAF50; }
        .test-result.error { border-left-color: #f44336; }
    </style>
</head>
<body>
    <h1>🔧 Debug All APIs & Database</h1>
    
    <div class="section">
        <h2>📊 Database Connection</h2>
        <div id="db-status">Testing...</div>
    </div>

    <div class="section">
        <h2>🔐 Authentication</h2>
        <div id="auth-status">Testing...</div>
        <button onclick="testLogin()">Test Login</button>
        <button onclick="testRegister()">Test Register</button>
        <button onclick="testUser()">Test User API</button>
    </div>

    <div class="section">
        <h2>🎵 Music APIs</h2>
        <div id="music-status">Testing...</div>
        <button onclick="testHome()">Test Home API</button>
        <button onclick="testSearch()">Test Search API</button>
        <button onclick="testTracks()">Test Tracks API</button>
        <button onclick="testAlbums()">Test Albums API</button>
        <button onclick="testArtists()">Test Artists API</button>
    </div>

    <div class="section">
        <h2>❤️ Likes & Playlists</h2>
        <div id="likes-status">Testing...</div>
        <button onclick="testLikes()">Test Likes API</button>
        <button onclick="testPlaylists()">Test Playlists API</button>
    </div>

    <div class="section">
        <h2>📁 File System</h2>
        <div id="files-status">Testing...</div>
    </div>

    <div class="section">
        <h2>🌐 Session Info</h2>
        <div id="session-info">Loading...</div>
    </div>

    <script>
        // Test database connection
        async function testDatabase() {
            try {
                const response = await fetch('/muzic2/src/api/user.php', { 
                    method: 'GET',
                    credentials: 'include'
                });
                const data = await response.json();
                
                document.getElementById('db-status').innerHTML = `
                    <div class="test-result success">
                        ✅ Database connection: OK<br>
                        Status: ${response.status}<br>
                        Response: <pre>${JSON.stringify(data, null, 2)}</pre>
                    </div>
                `;
            } catch (error) {
                document.getElementById('db-status').innerHTML = `
                    <div class="test-result error">
                        ❌ Database connection: FAILED<br>
                        Error: ${error.message}
                    </div>
                `;
            }
        }

        // Test authentication
        async function testAuth() {
            try {
                const response = await fetch('/muzic2/src/api/user.php', { 
                    method: 'GET',
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.user_id) {
                    document.getElementById('auth-status').innerHTML = `
                        <div class="test-result success">
                            ✅ User authenticated: ${data.username || 'Unknown'}<br>
                            User ID: ${data.user_id}<br>
                            Session: Active
                        </div>
                    `;
                } else {
                    document.getElementById('auth-status').innerHTML = `
                        <div class="test-result warning">
                            ⚠️ User not authenticated<br>
                            Response: <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                }
            } catch (error) {
                document.getElementById('auth-status').innerHTML = `
                    <div class="test-result error">
                        ❌ Auth test failed: ${error.message}
                    </div>
                `;
            }
        }

        // Test login
        async function testLogin() {
            try {
                const response = await fetch('/muzic2/src/api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: 'admin',
                        password: 'admin'
                    }),
                    credentials: 'include'
                });
                const data = await response.json();
                
                document.getElementById('auth-status').innerHTML += `
                    <div class="test-result ${response.ok ? 'success' : 'error'}">
                        ${response.ok ? '✅' : '❌'} Login test: ${response.status}<br>
                        Response: <pre>${JSON.stringify(data, null, 2)}</pre>
                    </div>
                `;
            } catch (error) {
                document.getElementById('auth-status').innerHTML += `
                    <div class="test-result error">
                        ❌ Login test failed: ${error.message}
                    </div>
                `;
            }
        }

        // Test register
        async function testRegister() {
            try {
                const testUser = 'test_' + Date.now();
                const response = await fetch('/muzic2/src/api/register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: testUser,
                        password: 'test123',
                        email: testUser + '@test.com'
                    }),
                    credentials: 'include'
                });
                const data = await response.json();
                
                document.getElementById('auth-status').innerHTML += `
                    <div class="test-result ${response.ok ? 'success' : 'error'}">
                        ${response.ok ? '✅' : '❌'} Register test: ${response.status}<br>
                        Test user: ${testUser}<br>
                        Response: <pre>${JSON.stringify(data, null, 2)}</pre>
                    </div>
                `;
            } catch (error) {
                document.getElementById('auth-status').innerHTML += `
                    <div class="test-result error">
                        ❌ Register test failed: ${error.message}
                    </div>
                `;
            }
        }

        // Test user API
        async function testUser() {
            try {
                const response = await fetch('/muzic2/src/api/user.php', { 
                    method: 'GET',
                    credentials: 'include'
                });
                const data = await response.json();
                
                document.getElementById('auth-status').innerHTML += `
                    <div class="test-result ${response.ok ? 'success' : 'error'}">
                        ${response.ok ? '✅' : '❌'} User API test: ${response.status}<br>
                        Response: <pre>${JSON.stringify(data, null, 2)}</pre>
                    </div>
                `;
            } catch (error) {
                document.getElementById('auth-status').innerHTML += `
                    <div class="test-result error">
                        ❌ User API test failed: ${error.message}
                    </div>
                `;
            }
        }

        // Test music APIs
        async function testMusicAPIs() {
            const apis = [
                { name: 'Home', url: '/muzic2/public/src/api/home.php' },
                { name: 'Search', url: '/muzic2/src/api/search.php?q=test' },
                { name: 'Tracks', url: '/muzic2/src/api/tracks.php' },
                { name: 'Albums', url: '/muzic2/src/api/album.php' },
                { name: 'Artists', url: '/muzic2/src/api/artist.php' }
            ];

            let results = '';
            for (const api of apis) {
                try {
                    const response = await fetch(api.url, { credentials: 'include' });
                    const data = await response.json();
                    
                    results += `
                        <div class="test-result ${response.ok ? 'success' : 'error'}">
                            ${response.ok ? '✅' : '❌'} ${api.name}: ${response.status}<br>
                            ${response.ok ? `Data keys: ${Object.keys(data).join(', ')}` : `Error: ${data.message || 'Unknown error'}`}
                        </div>
                    `;
                } catch (error) {
                    results += `
                        <div class="test-result error">
                            ❌ ${api.name}: ${error.message}
                        </div>
                    `;
                }
            }
            
            document.getElementById('music-status').innerHTML = results;
        }

        // Test home API
        async function testHome() {
            try {
                const response = await fetch('/muzic2/public/src/api/home.php', { credentials: 'include' });
                const data = await response.json();
                
                document.getElementById('music-status').innerHTML += `
                    <div class="test-result ${response.ok ? 'success' : 'error'}">
                        ${response.ok ? '✅' : '❌'} Home API: ${response.status}<br>
                        Tracks: ${data.tracks?.length || 0}<br>
                        Albums: ${data.albums?.length || 0}<br>
                        Artists: ${data.artists?.length || 0}<br>
                        Favorites: ${data.favorites?.length || 0}<br>
                        Mixes: ${data.mixes?.length || 0}
                    </div>
                `;
            } catch (error) {
                document.getElementById('music-status').innerHTML += `
                    <div class="test-result error">
                        ❌ Home API failed: ${error.message}
                    </div>
                `;
            }
        }

        // Test search API
        async function testSearch() {
            try {
                const response = await fetch('/muzic2/src/api/search.php?q=kai', { credentials: 'include' });
                const data = await response.json();
                
                document.getElementById('music-status').innerHTML += `
                    <div class="test-result ${response.ok ? 'success' : 'error'}">
                        ${response.ok ? '✅' : '❌'} Search API: ${response.status}<br>
                        Results: ${data.tracks?.length || 0} tracks, ${data.albums?.length || 0} albums, ${data.artists?.length || 0} artists
                    </div>
                `;
            } catch (error) {
                document.getElementById('music-status').innerHTML += `
                    <div class="test-result error">
                        ❌ Search API failed: ${error.message}
                    </div>
                `;
            }
        }

        // Test tracks API
        async function testTracks() {
            try {
                const response = await fetch('/muzic2/src/api/tracks.php', { credentials: 'include' });
                const data = await response.json();
                
                document.getElementById('music-status').innerHTML += `
                    <div class="test-result ${response.ok ? 'success' : 'error'}">
                        ${response.ok ? '✅' : '❌'} Tracks API: ${response.status}<br>
                        Tracks count: ${data.length || 0}
                    </div>
                `;
            } catch (error) {
                document.getElementById('music-status').innerHTML += `
                    <div class="test-result error">
                        ❌ Tracks API failed: ${error.message}
                    </div>
                `;
            }
        }

        // Test albums API
        async function testAlbums() {
            try {
                const response = await fetch('/muzic2/src/api/album.php', { credentials: 'include' });
                const data = await response.json();
                
                document.getElementById('music-status').innerHTML += `
                    <div class="test-result ${response.ok ? 'success' : 'error'}">
                        ${response.ok ? '✅' : '❌'} Albums API: ${response.status}<br>
                        Albums count: ${data.length || 0}
                    </div>
                `;
            } catch (error) {
                document.getElementById('music-status').innerHTML += `
                    <div class="test-result error">
                        ❌ Albums API failed: ${error.message}
                    </div>
                `;
            }
        }

        // Test artists API
        async function testArtists() {
            try {
                const response = await fetch('/muzic2/src/api/artist.php', { credentials: 'include' });
                const data = await response.json();
                
                document.getElementById('music-status').innerHTML += `
                    <div class="test-result ${response.ok ? 'success' : 'error'}">
                        ${response.ok ? '✅' : '❌'} Artists API: ${response.status}<br>
                        Artists count: ${data.length || 0}
                    </div>
                `;
            } catch (error) {
                document.getElementById('music-status').innerHTML += `
                    <div class="test-result error">
                        ❌ Artists API failed: ${error.message}
                    </div>
                `;
            }
        }

        // Test likes API
        async function testLikes() {
            try {
                const response = await fetch('/muzic2/src/api/likes.php', { credentials: 'include' });
                const data = await response.json();
                
                document.getElementById('likes-status').innerHTML += `
                    <div class="test-result ${response.ok ? 'success' : 'error'}">
                        ${response.ok ? '✅' : '❌'} Likes API: ${response.status}<br>
                        Liked tracks: ${data.tracks?.length || 0}<br>
                        Liked albums: ${data.albums?.length || 0}
                    </div>
                `;
            } catch (error) {
                document.getElementById('likes-status').innerHTML += `
                    <div class="test-result error">
                        ❌ Likes API failed: ${error.message}
                    </div>
                `;
            }
        }

        // Test playlists API
        async function testPlaylists() {
            try {
                const response = await fetch('/muzic2/src/api/playlists.php', { credentials: 'include' });
                const data = await response.json();
                
                document.getElementById('likes-status').innerHTML += `
                    <div class="test-result ${response.ok ? 'success' : 'error'}">
                        ${response.ok ? '✅' : '❌'} Playlists API: ${response.status}<br>
                        Playlists count: ${data.length || 0}<br>
                        ${data.length > 0 ? `First playlist: ${data[0].name}` : 'No playlists found'}
                    </div>
                `;
            } catch (error) {
                document.getElementById('likes-status').innerHTML += `
                    <div class="test-result error">
                        ❌ Playlists API failed: ${error.message}
                    </div>
                `;
            }
        }

        // Test file system
        async function testFileSystem() {
            const paths = [
                '/muzic2/tracks/music/',
                '/muzic2/tracks/covers/',
                '/muzic2/tracks/video/',
                '/muzic2/public/assets/css/',
                '/muzic2/public/assets/js/'
            ];

            let results = '';
            for (const path of paths) {
                try {
                    const response = await fetch(path);
                    if (response.ok) {
                        results += `
                            <div class="test-result success">
                                ✅ ${path}: Accessible
                            </div>
                        `;
                    } else {
                        results += `
                            <div class="test-result error">
                                ❌ ${path}: ${response.status} ${response.statusText}
                            </div>
                        `;
                    }
                } catch (error) {
                    results += `
                        <div class="test-result error">
                            ❌ ${path}: ${error.message}
                        </div>
                    `;
                }
            }
            
            document.getElementById('files-status').innerHTML = results;
        }

        // Load session info
        function loadSessionInfo() {
            document.getElementById('session-info').innerHTML = `
                <div class="test-result info">
                    📊 Session Information:<br>
                    Current URL: ${window.location.href}<br>
                    User Agent: ${navigator.userAgent}<br>
                    Cookies: ${document.cookie || 'None'}<br>
                    Local Storage: ${Object.keys(localStorage).length} items<br>
                    Session Storage: ${Object.keys(sessionStorage).length} items
                </div>
            `;
        }

        // Run all tests on page load
        window.onload = function() {
            testDatabase();
            testAuth();
            testMusicAPIs();
            testLikes();
            testPlaylists();
            testFileSystem();
            loadSessionInfo();
        };
    </script>
</body>
</html>
