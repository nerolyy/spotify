const fs = require('fs');
const path = require('path');

const sourceDir = path.join(__dirname, '../../public');
const targetDir = path.join(__dirname, '../content');

// Создаем папку content если её нет
if (!fs.existsSync(targetDir)) {
  fs.mkdirSync(targetDir, { recursive: true });
}

// Функция для копирования файлов и папок
function copyRecursive(src, dest) {
  const exists = fs.existsSync(src);
  const stats = exists && fs.statSync(src);
  const isDirectory = exists && stats.isDirectory();
  
  if (isDirectory) {
    if (!fs.existsSync(dest)) {
      fs.mkdirSync(dest, { recursive: true });
    }
    fs.readdirSync(src).forEach(childItemName => {
      copyRecursive(
        path.join(src, childItemName),
        path.join(dest, childItemName)
      );
    });
  } else {
    // Создаем директорию для файла если её нет
    const destDir = path.dirname(dest);
    if (!fs.existsSync(destDir)) {
      fs.mkdirSync(destDir, { recursive: true });
    }
    fs.copyFileSync(src, dest);
  }
}

// Копируем весь контент из public
console.log('Копирование контента из public в app/content...');
copyRecursive(sourceDir, targetDir);

// Копируем треки и обложки
const tracksSource = path.join(__dirname, '../../tracks');
const tracksDest = path.join(targetDir, 'tracks');

if (fs.existsSync(tracksSource)) {
  console.log('Копирование треков и обложек...');
  copyRecursive(tracksSource, tracksDest);
}

// Создаем адаптированный index.html для приложения
const indexPath = path.join(targetDir, 'index.html');
if (fs.existsSync(indexPath)) {
  let indexContent = fs.readFileSync(indexPath, 'utf8');
  
  // Заменяем пути на относительные (без /muzic2/)
  indexContent = indexContent.replace(/\/muzic2\//g, './');
  indexContent = indexContent.replace(/href="\/muzic2\//g, 'href="./');
  indexContent = indexContent.replace(/src="\/muzic2\//g, 'src="./');
  
  fs.writeFileSync(indexPath, indexContent);
  console.log('Адаптирован index.html для приложения');
}

console.log('Копирование завершено!');

// Запускаем адаптацию файлов
const adaptScript = path.join(__dirname, 'adapt-js-for-app.js');
if (fs.existsSync(adaptScript)) {
  console.log('Запуск адаптации файлов...');
  require(adaptScript);
}

// Генерируем API JSON файлы
const generateAPIScript = path.join(__dirname, 'generate-api-json.js');
if (fs.existsSync(generateAPIScript)) {
  console.log('Генерация API JSON файлов...');
  require(generateAPIScript);
}

