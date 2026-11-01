<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ultra Fast Debug</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #1a1a1a; color: #fff; }
        .result { margin: 10px 0; padding: 10px; border-left: 4px solid #4CAF50; background: #2a2a2a; }
        .error { border-left-color: #f44336; }
        button { background: #4CAF50; color: white; border: none; padding: 10px 20px; margin: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Ultra Fast Debug</h1>
    
    <div id="results"></div>
    
    <button onclick="testOne()">Test One API</button>
    <button onclick="testLogin()">Test Login</button>
    <button onclick="testPlaylist()">Test Playlist</button>

    <script>
        const results = document.getElementById('results');
        
        function addResult(text, isError = false) {
            const div = document.createElement('div');
            div.className = isError ? 'result error' : 'result';
            div.innerHTML = text;
            results.appendChild(div);
        }
        
        async function testOne() {
            results.innerHTML = '';
            addResult('Testing ONE API only...');
            
            try {
                const start = Date.now();
                const res = await fetch('/muzic2/src/api/user.php', { credentials: 'include' });
                const data = await res.json();
                const time = Date.now() - start;
                addResult(`User API: ${res.status} - ${time}ms - ${data.user_id ? 'Logged in' : 'Not logged in'}`);
            } catch (e) {
                addResult(`User API: ERROR - ${e.message}`, true);
            }
        }
        
        async function testLogin() {
            try {
                const start = Date.now();
                const res = await fetch('/muzic2/src/api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username: 'admin', password: 'admin' }),
                    credentials: 'include'
                });
                const data = await res.json();
                const time = Date.now() - start;
                addResult(`Login: ${res.status} - ${time}ms - ${data.message || 'Success'}`);
            } catch (e) {
                addResult(`Login: ERROR - ${e.message}`, true);
            }
        }
        
        async function testPlaylist() {
            try {
                const start = Date.now();
                const res = await fetch('/muzic2/src/api/playlists.php', { credentials: 'include' });
                const data = await res.json();
                const time = Date.now() - start;
                addResult(`Playlists: ${res.status} - ${time}ms - ${data.length} playlists`);
            } catch (e) {
                addResult(`Playlists: ERROR - ${e.message}`, true);
            }
        }
        
        // Auto-test on load
        window.onload = testOne;
    </script>
</body>
</html>
