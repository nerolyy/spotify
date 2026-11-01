const fs = require('fs');
const path = require('path');

// Адаптирует JS файлы для работы в приложении (без сервера, через file://)
function adaptJSFiles(contentDir) {
  const jsDir = path.join(contentDir, 'assets/js');
  
  if (!fs.existsSync(jsDir)) {
    console.log('JS директория не найдена');
    return;
  }
  
  const jsFiles = fs.readdirSync(jsDir).filter(f => f.endsWith('.js'));
  
  jsFiles.forEach(file => {
    const filePath = path.join(jsDir, file);
    let content = fs.readFileSync(filePath, 'utf8');
    
    // Заменяем абсолютные пути на относительные
    content = content.replace(/\/muzic2\//g, './');
    content = content.replace(/\/muzic2\/public\//g, './');
    
    // Заменяем API пути на локальные JSON файлы
    content = content.replace(/fetch\(['"]\/muzic2\/src\/api\/([^'"]+)\.php['"]/g, "fetch('./api/$1.json'");
    content = content.replace(/fetch\(['"]\/muzic2\/public\/src\/api\/([^'"]+)\.php['"]/g, "fetch('./api/$1.json'");
    content = content.replace(/fetch\(['"]\/src\/api\/([^'"]+)\.php['"]/g, "fetch('./api/$1.json'");
    
    // Заменяем пути к ресурсам
    content = content.replace(/['"]\/muzic2\/tracks\//g, "'./tracks/");
    content = content.replace(/['"]\/muzic2\/public\/tracks\//g, "'./tracks/");
    
    // Исправляем API_ORIGIN для работы без сервера
    content = content.replace(/const API_ORIGIN = .*?;/g, 
      "const API_ORIGIN = window.location.origin || 'file://';");
    content = content.replace(/window\.API_ORIGIN = API_ORIGIN;/g, 
      "window.API_ORIGIN = API_ORIGIN; window.IS_APP_MODE = true;");
    
    // Добавляем обработку file:// протокола
    if (!content.includes('IS_APP_MODE')) {
      content = content.replace(/const api = \(path\) =>/g, 
        `const api = (path) => {
  // В режиме приложения используем относительные пути
  if (window.IS_APP_MODE || window.location.protocol === 'file:') {
    if (String(path).includes('/api/')) {
      return path.replace(/\\.php$/, '.json').replace(/^\\/muzic2\\//, './');
    }
    return path.replace(/^\\/muzic2\\//, './');
  }
  return`);
    }
    
    fs.writeFileSync(filePath, content);
    console.log(`Адаптирован: ${file}`);
  });
}

// Адаптирует HTML файлы
function adaptHTMLFiles(contentDir) {
  const htmlFiles = ['index.html', 'index.php', 'artist.html', 'album.html'];
  
  htmlFiles.forEach(file => {
    const filePath = path.join(contentDir, file);
    if (!fs.existsSync(filePath)) return;
    
    let content = fs.readFileSync(filePath, 'utf8');
    
    // Заменяем пути
    content = content.replace(/\/muzic2\//g, './');
    content = content.replace(/href="\/muzic2\//g, 'href="./');
    content = content.replace(/src="\/muzic2\//g, 'src="./');
    content = content.replace(/url\(['"]\/muzic2\//g, "url('./");
    
    // Добавляем скрипт для определения режима приложения
    if (!content.includes('IS_APP_MODE')) {
      const scriptTag = '<script>window.IS_APP_MODE = window.location.protocol === "file:";</script>';
      content = content.replace('</head>', scriptTag + '</head>');
    }
    
    fs.writeFileSync(filePath, content);
    console.log(`Адаптирован HTML: ${file}`);
  });
}

// Адаптирует CSS файлы
function adaptCSSFiles(contentDir) {
  const cssDir = path.join(contentDir, 'assets/css');
  
  if (!fs.existsSync(cssDir)) {
    return;
  }
  
  const cssFiles = fs.readdirSync(cssDir).filter(f => f.endsWith('.css'));
  
  cssFiles.forEach(file => {
    const filePath = path.join(cssDir, file);
    let content = fs.readFileSync(filePath, 'utf8');
    
    // Заменяем пути к ресурсам
    content = content.replace(/url\(['"]\/muzic2\//g, "url('./");
    content = content.replace(/url\(['"]\/muzic2\/public\//g, "url('./");
    
    fs.writeFileSync(filePath, content);
    console.log(`Адаптирован CSS: ${file}`);
  });
}

const contentDir = path.join(__dirname, '../content');

if (fs.existsSync(contentDir)) {
  console.log('Адаптация файлов для работы без сервера (file://)...');
  adaptJSFiles(contentDir);
  adaptHTMLFiles(contentDir);
  adaptCSSFiles(contentDir);
  console.log('Адаптация завершена!');
} else {
  console.log('Папка content не найдена. Сначала запустите copy-content.js');
}
