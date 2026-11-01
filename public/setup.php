<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Быстрая настройка Muzic2</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .step { background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .step h3 { margin-top: 0; color: #333; }
        .btn { background: #007cba; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin: 10px 5px; }
        .btn:hover { background: #005a87; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #17a2b8; }
        .log { background: #000; color: #0f0; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; margin: 10px 0; }
        .status { padding: 10px; border-radius: 4px; margin: 10px 0; }
        .status.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .status.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .status.info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
    </style>
</head>
<body>
    <h1>🚀 Быстрая настройка Muzic2</h1>
    
    <div class="step">
        <h3>1️⃣ Проверка окружения</h3>
        <button class="btn" onclick="checkEnvironment()">Проверить PHP и БД</button>
        <div id="env-status"></div>
    </div>

    <div class="step">
        <h3>2️⃣ Инициализация базы данных</h3>
        <button class="btn" onclick="initDatabase()">Создать таблицы</button>
        <div id="db-status"></div>
    </div>

    <div class="step">
        <h3>3️⃣ Импорт данных</h3>
        <button class="btn" onclick="importData()">Импортировать треки и артистов</button>
        <div id="import-status"></div>
    </div>

    <div class="step">
        <h3>4️⃣ Проверка работоспособности</h3>
        <button class="btn" onclick="checkHealth()">Проверить всё</button>
        <div id="health-status"></div>
    </div>

    <div class="step">
        <h3>📊 Лог операций</h3>
        <div id="log" class="log"></div>
        <button class="btn" onclick="clearLog()">Очистить лог</button>
    </div>

    <script>
        function log(message, type = 'info') {
            const logDiv = document.getElementById('log');
            const timestamp = new Date().toLocaleTimeString();
            const color = type === 'error' ? '#f00' : type === 'success' ? '#0f0' : '#0ff';
            logDiv.innerHTML += `<div style="color: ${color}">[${timestamp}] ${message}</div>`;
            logDiv.scrollTop = logDiv.scrollHeight;
        }

        function clearLog() {
            document.getElementById('log').innerHTML = '';
        }

        function setStatus(elementId, message, type = 'info') {
            const element = document.getElementById(elementId);
            element.innerHTML = `<div class="status ${type}">${message}</div>`;
        }

        async function checkEnvironment() {
            log('Проверка окружения...');
            try {
                const response = await fetch('setup_api.php?action=check_env');
                const result = await response.json();
                
                if (result.success) {
                    setStatus('env-status', '✅ PHP и БД работают корректно', 'success');
                    log('Окружение проверено успешно', 'success');
                } else {
                    setStatus('env-status', `❌ Ошибка: ${result.error}`, 'error');
                    log(`Ошибка окружения: ${result.error}`, 'error');
                }
            } catch (error) {
                setStatus('env-status', `❌ Ошибка подключения: ${error.message}`, 'error');
                log(`Ошибка подключения: ${error.message}`, 'error');
            }
        }

        async function initDatabase() {
            log('Инициализация базы данных...');
            try {
                const response = await fetch('setup_api.php?action=init_db');
                const result = await response.json();
                
                if (result.success) {
                    setStatus('db-status', '✅ База данных инициализирована', 'success');
                    log('База данных создана успешно', 'success');
                    if (result.executed && result.executed.length > 0) {
                        log(`Выполнено: ${result.executed.join(', ')}`, 'info');
                    }
                } else {
                    setStatus('db-status', `❌ Ошибка: ${result.error}`, 'error');
                    log(`Ошибка БД: ${result.error}`, 'error');
                }
            } catch (error) {
                setStatus('db-status', `❌ Ошибка подключения: ${error.message}`, 'error');
                log(`Ошибка подключения: ${error.message}`, 'error');
            }
        }

        async function importData() {
            log('Импорт данных...');
            try {
                const response = await fetch('setup_api.php?action=import_data');
                const result = await response.json();
                
                if (result.success) {
                    setStatus('import-status', `✅ Импортировано: ${result.imported.tracks || 0} треков, ${result.imported.artists || 0} артистов`, 'success');
                    log(`Импорт завершен: ${result.imported.tracks || 0} треков, ${result.imported.artists || 0} артистов`, 'success');
                } else {
                    setStatus('import-status', `❌ Ошибка: ${result.error}`, 'error');
                    log(`Ошибка импорта: ${result.error}`, 'error');
                }
            } catch (error) {
                setStatus('import-status', `❌ Ошибка подключения: ${error.message}`, 'error');
                log(`Ошибка подключения: ${error.message}`, 'error');
            }
        }

        async function checkHealth() {
            log('Проверка работоспособности...');
            try {
                const response = await fetch('setup_api.php?action=check_health');
                const result = await response.json();
                
                if (result.success) {
                    setStatus('health-status', `✅ Всё работает! Треков: ${result.health.tracks_count}, Артистов: ${result.health.artists_count}`, 'success');
                    log(`Проверка завершена: ${result.health.tracks_count} треков, ${result.health.artists_count} артистов`, 'success');
                    
                    // Показываем ссылки
                    const links = `
                        <div style="margin-top: 15px;">
                            <a href="index.php" class="btn" style="text-decoration: none; display: inline-block;">🏠 Главная страница</a>
                            <a href="admin/" class="btn" style="text-decoration: none; display: inline-block;">⚙️ Админ-панель</a>
                        </div>
                    `;
                    document.getElementById('health-status').innerHTML += links;
                } else {
                    setStatus('health-status', `❌ Ошибка: ${result.error}`, 'error');
                    log(`Ошибка проверки: ${result.error}`, 'error');
                }
            } catch (error) {
                setStatus('health-status', `❌ Ошибка подключения: ${error.message}`, 'error');
                log(`Ошибка подключения: ${error.message}`, 'error');
            }
        }

        // Автоматическая проверка при загрузке
        window.onload = function() {
            log('Страница загружена. Готов к настройке!', 'success');
        };
    </script>
</body>
</html>
