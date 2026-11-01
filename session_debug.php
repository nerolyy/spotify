<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Session Debug</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #1a1a1a; color: #fff; }
        .result { margin: 10px 0; padding: 10px; border-left: 4px solid #4CAF50; background: #2a2a2a; }
        .error { border-left-color: #f44336; }
        .warning { border-left-color: #ff9800; }
        button { background: #4CAF50; color: white; border: none; padding: 10px 20px; margin: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Session Debug</h1>
    
    <div id="results"></div>
    
    <button onclick="testSession()">Test Session</button>
    <button onclick="testLogin()">Test Login</button>
    <button onclick="testUser()">Test User</button>
    <button onclick="clearSession()">Clear Session</button>

    <script>
        const results = document.getElementById('results');
        
        function addResult(text, type = 'success') {
            const div = document.createElement('div');
            div.className = `result ${type}`;
            div.innerHTML = text;
            results.appendChild(div);
        }
        
        async function testSession() {
            try {
                const start = Date.now();
                const res = await fetch('/muzic2/src/api/user.php', { credentials: 'include' });
                const data = await res.json();
                const time = Date.now() - start;
                
                addResult(`
                    Session Test: ${res.status} - ${time}ms<br>
                    User ID: ${data.user_id || 'null'}<br>
                    Username: ${data.username || 'null'}<br>
                    Cookies: ${document.cookie || 'none'}
                `, res.status === 200 ? 'success' : 'error');
            } catch (e) {
                addResult(`Session Test: ERROR - ${e.message}`, 'error');
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
                
                addResult(`
                    Login Test: ${res.status} - ${time}ms<br>
                    Success: ${data.success || false}<br>
                    Message: ${data.message || 'none'}<br>
                    User: ${data.user ? JSON.stringify(data.user) : 'none'}
                `, res.status === 200 ? 'success' : 'error');
            } catch (e) {
                addResult(`Login Test: ERROR - ${e.message}`, 'error');
            }
        }
        
        async function testUser() {
            try {
                const start = Date.now();
                const res = await fetch('/muzic2/src/api/user.php', { credentials: 'include' });
                const data = await res.json();
                const time = Date.now() - start;
                
                addResult(`
                    User Test: ${res.status} - ${time}ms<br>
                    User ID: ${data.user_id || 'null'}<br>
                    Username: ${data.username || 'null'}<br>
                    Authenticated: ${data.authenticated || false}
                `, res.status === 200 ? 'success' : 'error');
            } catch (e) {
                addResult(`User Test: ERROR - ${e.message}`, 'error');
            }
        }
        
        async function clearSession() {
            try {
                const res = await fetch('/muzic2/src/api/logout.php', { credentials: 'include' });
                addResult(`Logout: ${res.status}`, 'warning');
            } catch (e) {
                addResult(`Logout: ERROR - ${e.message}`, 'error');
            }
        }
        
        // Auto-test on load
        window.onload = () => {
            addResult('Starting session debug...', 'warning');
            testSession();
        };
    </script>
</body>
</html>
