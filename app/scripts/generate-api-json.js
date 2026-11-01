const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

// Генерирует статические JSON файлы для API из существующих PHP файлов
// Это упрощенная версия - в реальности нужно будет экспортировать данные из БД

const apiDir = path.join(__dirname, '../content/api');
if (!fs.existsSync(apiDir)) {
  fs.mkdirSync(apiDir, { recursive: true });
}

// Базовые ответы API для работы приложения
const apiResponses = {
  'home': { tracks: [], albums: [], artists: [], mixes: [] },
  'search': { tracks: [], artists: [], albums: [] },
  'tracks': { tracks: [] },
  'likes': { likes: [] },
  'user': { user: null, authenticated: false },
  'login': { success: false, error: 'Login disabled in app mode' },
  'windows_auth': { user: null, authenticated: false },
  'windows_likes': { likes: [] }
};

const phpExporter = path.join(__dirname, 'export_offline_data.php');
const jsExporter = path.join(__dirname, 'build-offline-data.js');
let exported = false;
if (fs.existsSync(phpExporter)) {
  console.log('Экспорт офлайн-данных через PHP...');
  const result = spawnSync('php', [phpExporter], { stdio: 'inherit' });
  if (result.status === 0) {
    exported = true;
  } else {
    console.warn('Не удалось экспортировать данные через PHP.');
  }
}

if (!exported && fs.existsSync(jsExporter)) {
  console.log('Экспорт офлайн-данных через Node (сканируем tracks/)...');
  try {
    const buildOfflineData = require(jsExporter);
    exported = buildOfflineData() === true;
  } catch (err) {
    console.error('Ошибка Node-экспортера:', err.message);
  }
}

if (!exported) {
  // Создаем JSON файлы для каждого API (заглушки)
Object.keys(apiResponses).forEach(apiName => {
  const jsonPath = path.join(apiDir, apiName + '.json');
  fs.writeFileSync(jsonPath, JSON.stringify(apiResponses[apiName], null, 2));
  console.log(`Создан API файл: ${apiName}.json`);
});

  console.log('API JSON файлы созданы (заглушки).');
  console.log('Для реальных данных убедитесь, что MySQL доступен и повторите команду.');
}

