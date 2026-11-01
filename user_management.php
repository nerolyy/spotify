<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>User Management</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #1a1a1a; color: #fff; }
        .result { margin: 10px 0; padding: 10px; border-left: 4px solid #4CAF50; background: #2a2a2a; }
        .error { border-left-color: #f44336; }
        .success { border-left-color: #4CAF50; }
        .warning { border-left-color: #ff9800; }
        button { background: #4CAF50; color: white; border: none; padding: 10px 20px; margin: 5px; cursor: pointer; }
        input { padding: 8px; margin: 5px; border: 1px solid #333; background: #2a2a2a; color: #fff; }
    </style>
</head>
<body>
    <h1>User Management</h1>
    
    <div id="results"></div>
    
    <h3>Create User</h3>
    <input type="text" id="username" placeholder="Username" value="admin">
    <input type="text" id="email" placeholder="Email" value="admin@test.com">
    <input type="password" id="password" placeholder="Password" value="admin">
    <button onclick="createUser()">Create User</button>
    
    <h3>Test Login</h3>
    <input type="text" id="login-username" placeholder="Login" value="admin">
    <input type="password" id="login-password" placeholder="Password" value="admin">
    <button onclick="testLogin()">Test Login</button>
    
    <h3>Check Users</h3>
    <button onclick="checkUsers()">Check Users</button>

    <script>
        const results = document.getElementById('results');
        
        function addResult(text, type = 'success') {
            const div = document.createElement('div');
            div.className = `result ${type}`;
            div.innerHTML = text;
            results.appendChild(div);
        }
        
        async function createUser() {
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!username || !email || !password) {
                addResult('Fill all fields', 'error');
                return;
            }
            
            try {
                const start = Date.now();
                const res = await fetch('/muzic2/src/api/register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, email, password }),
                    credentials: 'include'
                });
                const data = await res.json();
                const time = Date.now() - start;
                
                addResult(`
                    Register: ${res.status} - ${time}ms<br>
                    Success: ${data.success || false}<br>
                    Message: ${data.message || data.error || 'none'}
                `, res.status === 200 ? 'success' : 'error');
            } catch (e) {
                addResult(`Register: ERROR - ${e.message}`, 'error');
            }
        }
        
        async function testLogin() {
            const username = document.getElementById('login-username').value;
            const password = document.getElementById('login-password').value;
            
            try {
                const start = Date.now();
                const res = await fetch('/muzic2/src/api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ login: username, password: password }),
                    credentials: 'include'
                });
                const data = await res.json();
                const time = Date.now() - start;
                
                addResult(`
                    Login: ${res.status} - ${time}ms<br>
                    Success: ${data.success || false}<br>
                    Message: ${data.message || data.error || 'none'}<br>
                    User: ${data.user ? JSON.stringify(data.user) : 'none'}
                `, res.status === 200 ? 'success' : 'error');
            } catch (e) {
                addResult(`Login: ERROR - ${e.message}`, 'error');
            }
        }
        
        async function checkUsers() {
            try {
                const start = Date.now();
                const res = await fetch('/muzic2/src/api/user.php', { credentials: 'include' });
                const data = await res.json();
                const time = Date.now() - start;
                
                addResult(`
                    User Check: ${res.status} - ${time}ms<br>
                    User ID: ${data.user_id || 'null'}<br>
                    Username: ${data.username || 'null'}<br>
                    Authenticated: ${data.authenticated || false}
                `, res.status === 200 ? 'success' : 'error');
            } catch (e) {
                addResult(`User Check: ERROR - ${e.message}`, 'error');
            }
        }
        
        // Auto-check on load
        window.onload = () => {
            addResult('User management loaded...', 'warning');
            checkUsers();
        };
    </script>
</body>
</html>
