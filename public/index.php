<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="description" content="Современный музыкальный сервис с караоке режимом">
    <meta name="theme-color" content="#1db954">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Muzic2">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Muzic2 — Музыкальный сервис</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <link rel="manifest" href="/muzic2/public/manifest.json">
    <link rel="apple-touch-icon" href="/muzic2/public/assets/img/icon-192x192.png">
</head>
<body>
    <div id="app">
        <header id="main-header">
            <div class="logo">Muzic2</div>
            <nav id="main-nav">
                <button id="nav-home">Главная</button>
                <button id="nav-search">Поиск</button>
                <button id="nav-library">Моя музыка</button>
            </nav>
        </header>
        <main id="main-content">
            <section class="main-filters">
                <button class="filter-btn active">Все</button>
                <button class="filter-btn">Музыка</button>
                <button class="filter-btn">Артисты</button>
            </section>
            <section class="main-section" id="favorites-section">
                <div class="section-header">
                <h3>Любимые треки</h3>
                    <button class="see-all-btn">Смотреть всё</button>
                </div>
                <div class="card-row" id="favorites-row"></div>
            </section>
            <section class="main-section" id="mixes-section">
                <div class="section-header">
                <h3>Миксы дня</h3>
                    <button class="see-all-btn">Смотреть всё</button>
                </div>
                <div class="card-row" id="mixes-row"></div>
            </section>
            <section class="main-section" id="albums-section">
                <div class="section-header">
                <h3>Случайные альбомы</h3>
                    <button class="see-all-btn">Смотреть всё</button>
                </div>
                <div class="card-row" id="albums-row"></div>
            </section>
            <section class="main-section" id="tracks-section">
                <div class="section-header">
                <h3>Случайные треки</h3>
                    <button class="see-all-btn">Смотреть всё</button>
                </div>
                <div class="card-row" id="tracks-row"></div>
            </section>
            <section class="main-section" id="artists-section">
                <div class="section-header">
                <h3>Артисты</h3>
                    <button class="see-all-btn">Смотреть всё</button>
                </div>
                <div class="card-row" id="artists-row"></div>
            </section>
        </main>
        <div id="player-root"></div>
    </div>
    <script>
        // Принудительно используем HTTP вместо HTTPS
        if (location.protocol === 'https:') {
            location.replace('http:' + location.href.substring(5));
        }
        
        // Проверяем загрузку ресурсов
        function checkResource(url, callback) {
            fetch(url)
                .then(response => {
                    if (response.ok) {
                        callback(true);
                    } else {
                        callback(false);
                    }
                })
                .catch(() => callback(false));
        }
        
        // Загружаем JS файлы с fallback
        function loadScript(src, fallbackSrc = null) {
            return new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = src;
                script.onload = resolve;
                script.onerror = () => {
                    if (fallbackSrc) {
                        const fallbackScript = document.createElement('script');
                        fallbackScript.src = fallbackSrc;
                        fallbackScript.onload = resolve;
                        fallbackScript.onerror = reject;
                        document.head.appendChild(fallbackScript);
                    } else {
                        reject();
                    }
                };
                document.head.appendChild(script);
            });
        }
        
        // Загружаем все скрипты
        Promise.all([
            loadScript('assets/js/crossfade.js'),
            loadScript('assets/js/app.js'),
            loadScript('assets/js/player.js')
        ]).catch(error => {
            console.error('Ошибка загрузки скриптов:', error);
            document.body.innerHTML = '<div style="padding: 20px; text-align: center;"><h2>Ошибка загрузки</h2><p>Проверьте подключение к интернету и обновите страницу</p></div>';
        });
    </script>
</body>
</html> 