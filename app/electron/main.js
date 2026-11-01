const { app, BrowserWindow, protocol } = require('electron');
const path = require('path');
const fs = require('fs');

let mainWindow;

// Путь к контенту приложения
// В режиме разработки: app/content
// В собранном приложении: app.asar/content (внутри asar архива)
let contentPath;
if (app.isPackaged) {
  // В собранном приложении файлы находятся в app.asar
  // process.resourcesPath = /path/to/app/Contents/Resources
  // app.asar находится в Resources/app.asar
  // main.js находится в app.asar/electron/main.js
  // content находится в app.asar/content
  // __dirname указывает на app.asar/electron/, поэтому поднимаемся на уровень выше
  contentPath = path.join(__dirname, '..', 'content');
  
  // Если не найдено, пробуем альтернативные пути
  if (!fs.existsSync(contentPath)) {
    // Пробуем через process.resourcesPath
    const asarPath = path.join(process.resourcesPath, 'app.asar');
    if (fs.existsSync(asarPath)) {
      contentPath = path.join(asarPath, 'content');
    }
    if (!fs.existsSync(contentPath)) {
      contentPath = path.join(process.resourcesPath, 'app', 'content');
      if (!fs.existsSync(contentPath)) {
        contentPath = path.join(process.resourcesPath, 'content');
      }
    }
  }
} else {
  // В режиме разработки
  contentPath = path.join(__dirname, '../content');
}

// Регистрируем кастомный протокол для работы без сервера
function registerFileProtocol() {
  protocol.registerFileProtocol('app', (request, callback) => {
    const url = request.url.replace('app://', '');
    const filePath = path.join(contentPath, url);
    
    // Безопасность: проверяем, что файл находится в contentPath
    const normalizedPath = path.normalize(filePath);
    if (!normalizedPath.startsWith(path.normalize(contentPath))) {
      callback({ error: -6 }); // FILE_NOT_FOUND
      return;
    }
    
    callback({ path: normalizedPath });
  });
}

// Регистрируем протокол для API запросов
function registerAPIProtocol() {
  protocol.registerBufferProtocol('app-api', (request, callback) => {
    try {
      const url = request.url.replace('app-api://', '');
      const urlObj = new URL('http://' + url);
      
      // Извлекаем имя API
      let apiName = urlObj.pathname.split('/').pop().replace('.php', '');
      
      // Обрабатываем параметры
      if (urlObj.search) {
        const params = new URLSearchParams(urlObj.search);
        const period = params.get('period');
        const limit = params.get('limit');
        const limit_tracks = params.get('limit_tracks');
        const limit_albums = params.get('limit_albums');
        const limit_artists = params.get('limit_artists');
        const limit_mixes = params.get('limit_mixes');
        const limit_favorites = params.get('limit_favorites');
        
        if (period || limit) {
          apiName = `${apiName}_${period || 'all'}_${limit || '10'}`;
        } else if (limit_tracks || limit_albums || limit_artists || limit_mixes || limit_favorites) {
          const paramsStr = [
            limit_tracks ? `tracks${limit_tracks}` : '',
            limit_albums ? `albums${limit_albums}` : '',
            limit_artists ? `artists${limit_artists}` : '',
            limit_mixes ? `mixes${limit_mixes}` : '',
            limit_favorites ? `favorites${limit_favorites}` : ''
          ].filter(p => p).join('_');
          if (paramsStr) {
            apiName = `${apiName}_${paramsStr}`;
          }
        }
      }
      
      const jsonPath = path.join(contentPath, 'api', apiName + '.json');
      
      if (fs.existsSync(jsonPath)) {
        const data = fs.readFileSync(jsonPath, 'utf8');
        callback({
          mimeType: 'application/json; charset=utf-8',
          data: Buffer.from(data, 'utf8')
        });
      } else {
        // Пробуем базовое имя
        const baseApiName = urlObj.pathname.split('/').pop().replace('.php', '');
        const baseJsonPath = path.join(contentPath, 'api', baseApiName + '.json');
        if (fs.existsSync(baseJsonPath)) {
          const data = fs.readFileSync(baseJsonPath, 'utf8');
          callback({
            mimeType: 'application/json; charset=utf-8',
            data: Buffer.from(data, 'utf8')
          });
        } else {
          callback({ error: -6 }); // FILE_NOT_FOUND
        }
      }
    } catch (error) {
      console.error('API protocol error:', error);
      callback({ error: -6 });
    }
  });
}

// Перехватываем fetch запросы для API через webRequest
function setupAPIIinterceptor(mainWindow) {
  const { session } = mainWindow.webContents;
  
  // Перехватываем все запросы к API
  session.webRequest.onBeforeRequest((details, callback) => {
    try {
      let requestUrl = details.url;
      console.log('🔍 Intercepting request:', requestUrl);
      
      // Обрабатываем относительные пути (file:// протокол)
      if (requestUrl.startsWith('file://')) {
        let urlPath = decodeURIComponent(requestUrl.replace(/^file:\/\/[^/]+/, ''));
        
        // Нормализуем путь
        if (urlPath.startsWith('/')) {
          urlPath = urlPath.substring(1);
        }
        
        // Проверяем, является ли это API запросом
        if (urlPath.includes('/src/api/') || urlPath.includes('/public/src/api/') || urlPath.includes('/api/')) {
          // Извлекаем имя API файла
          let apiName = '';
          let queryString = '';
          
          // Извлекаем имя файла и query string
          if (urlPath.includes('?')) {
            const parts = urlPath.split('?');
            urlPath = parts[0];
            queryString = parts[1];
          }
          
          // Извлекаем имя API из пути
          if (urlPath.includes('/src/api/')) {
            apiName = urlPath.split('/src/api/')[1].replace('.php', '');
          } else if (urlPath.includes('/public/src/api/')) {
            apiName = urlPath.split('/public/src/api/')[1].replace('.php', '');
          } else if (urlPath.includes('/api/')) {
            apiName = urlPath.split('/api/')[1].replace('.php', '');
          }
          
          console.log('📝 API name:', apiName, 'Query:', queryString);
          
          // Обрабатываем параметры из query string
          if (queryString) {
            try {
              const params = new URLSearchParams(queryString);
              const period = params.get('period');
              const limit = params.get('limit');
              const limit_tracks = params.get('limit_tracks');
              const limit_albums = params.get('limit_albums');
              const limit_artists = params.get('limit_artists');
              const limit_mixes = params.get('limit_mixes');
              const limit_favorites = params.get('limit_favorites');
              
              if (period || limit) {
                apiName = `${apiName}_${period || 'all'}_${limit || '10'}`;
              } else if (limit_tracks || limit_albums || limit_artists || limit_mixes || limit_favorites) {
                const paramsStr = [
                  limit_tracks ? `tracks${limit_tracks}` : '',
                  limit_albums ? `albums${limit_albums}` : '',
                  limit_artists ? `artists${limit_artists}` : '',
                  limit_mixes ? `mixes${limit_mixes}` : '',
                  limit_favorites ? `favorites${limit_favorites}` : ''
                ].filter(p => p).join('_');
                if (paramsStr) {
                  apiName = `${apiName}_${paramsStr}`;
                }
              }
            } catch (e) {
              console.error('Error parsing query string:', e);
            }
          }
          
          // Ищем JSON файл
          const jsonPath = path.join(contentPath, 'api', apiName + '.json');
          console.log('🔎 Looking for JSON:', jsonPath);
          
          if (fs.existsSync(jsonPath)) {
            console.log('✅ Found JSON, redirecting to:', jsonPath);
            callback({
              redirectURL: `file://${jsonPath}`
            });
            return;
          } else {
            // Пробуем базовое имя без параметров
            const baseApiName = urlPath.split('/').pop().replace('.php', '');
            const baseJsonPath = path.join(contentPath, 'api', baseApiName + '.json');
            console.log('🔎 Trying base name:', baseJsonPath);
            if (fs.existsSync(baseJsonPath)) {
              console.log('✅ Found base JSON, redirecting to:', baseJsonPath);
              callback({
                redirectURL: `file://${baseJsonPath}`
              });
              return;
            } else {
              console.log('❌ JSON not found for:', apiName);
            }
          }
        }
      }
      
      callback({});
    } catch (error) {
      console.error('API interceptor error:', error);
    callback({});
    }
  });
}

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1400,
    height: 900,
    minWidth: 800,
    minHeight: 600,
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      webSecurity: false, // Отключаем для работы с file://
      allowRunningInsecureContent: true
    },
    icon: path.join(__dirname, '../assets/icon.png'),
    titleBarStyle: 'hiddenInset',
    backgroundColor: '#0f0f0f'
  });

  // Загружаем index.html напрямую через file://
  const indexPath = path.join(contentPath, 'index.html');
  console.log('=== Electron App Debug ===');
  console.log('isPackaged:', app.isPackaged);
  console.log('__dirname:', __dirname);
  console.log('process.resourcesPath:', process.resourcesPath);
  console.log('Content path:', contentPath);
  console.log('Content path exists:', fs.existsSync(contentPath));
  console.log('Index path:', indexPath);
  console.log('Index path exists:', fs.existsSync(indexPath));
  if (fs.existsSync(contentPath)) {
    console.log('Content directory contents:', fs.readdirSync(contentPath).slice(0, 10));
  }
  console.log('Tracks path exists:', fs.existsSync(path.join(contentPath, 'tracks')));
  console.log('=======================');
  
  if (!fs.existsSync(indexPath)) {
    console.error('ERROR: index.html not found at:', indexPath);
    // Пробуем найти альтернативные пути
    const altPaths = [
      path.join(process.resourcesPath, 'app.asar', 'content', 'index.html'),
      path.join(process.resourcesPath, 'content', 'index.html'),
      path.join(__dirname, '..', 'content', 'index.html'),
      path.join(__dirname, 'content', 'index.html')
    ];
    console.error('Trying alternative paths:');
    for (const altPath of altPaths) {
      console.error('  -', altPath, ':', fs.existsSync(altPath) ? 'EXISTS' : 'NOT FOUND');
      if (fs.existsSync(altPath)) {
        console.log('Found index.html at:', altPath);
        mainWindow.loadFile(altPath, { query: { app: 'true' } });
        return;
      }
    }
    mainWindow.loadURL('data:text/html,<h1>Ошибка: index.html не найден</h1><p>Путь: ' + indexPath + '</p><pre>' + JSON.stringify({ isPackaged: app.isPackaged, __dirname, resourcesPath: process.resourcesPath, contentPath }, null, 2) + '</pre>');
    return;
  }
  
  mainWindow.loadFile(indexPath, {
    query: { app: 'true' }
  });
  
  // Открываем DevTools только в режиме разработки
  if (!app.isPackaged) {
    mainWindow.webContents.openDevTools();
  }
  
  // Логируем ошибки загрузки
  mainWindow.webContents.on('did-fail-load', (event, errorCode, errorDescription, validatedURL) => {
    console.error('Failed to load:', validatedURL, errorCode, errorDescription);
  });
  
  // Логируем консольные сообщения из рендерера
  mainWindow.webContents.on('console-message', (event, level, message, line, sourceId) => {
    console.log(`[Renderer ${level}]:`, message);
  });
}

app.whenReady().then(() => {
  // Регистрируем протоколы перед созданием окна
  registerFileProtocol();
  registerAPIProtocol();
  
  createWindow();
  
  // Настраиваем API interceptor после создания окна
  setupAPIIinterceptor(mainWindow);

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});