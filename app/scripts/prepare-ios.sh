#!/bin/bash

# Скрипт для автоматической подготовки iOS проекта

echo "🚀 Подготовка iOS проекта для установки на iPhone..."

# Переходим в корень проекта
cd "$(dirname "$0")/.."

# Проверяем наличие контента
if [ ! -d "content" ]; then
    echo "❌ Папка content не найдена. Запускаю копирование..."
    npm run copy-content
fi

# Копируем контент в iOS проект
echo "📦 Копирование контента в iOS проект..."
if [ -d "ios/Muzic2App/Muzic2App" ]; then
    cp -r content ios/Muzic2App/Muzic2App/
    echo "✅ Контент скопирован"
else
    echo "❌ iOS проект не найден в ios/Muzic2App/Muzic2App/"
    exit 1
fi

# Проверяем размер
echo "📊 Размер контента:"
du -sh ios/Muzic2App/Muzic2App/content/

# Проверяем наличие необходимых файлов
echo "🔍 Проверка файлов..."
if [ -f "ios/Muzic2App/Muzic2App/content/index.html" ]; then
    echo "✅ index.html найден"
else
    echo "❌ index.html не найден"
fi

if [ -d "ios/Muzic2App/Muzic2App/content/tracks" ]; then
    TRACK_COUNT=$(find ios/Muzic2App/Muzic2App/content/tracks/music -name "*.mp3" 2>/dev/null | wc -l | tr -d ' ')
    echo "✅ Треков найдено: $TRACK_COUNT"
else
    echo "❌ Папка tracks не найдена"
fi

echo ""
echo "✅ Подготовка завершена!"
echo ""
echo "📱 Следующие шаги:"
echo "1. Откройте Xcode:"
echo "   open ios/Muzic2App/Muzic2App.xcodeproj"
echo ""
echo "2. В Xcode:"
echo "   - Выберите проект в навигаторе"
echo "   - Перейдите в Signing & Capabilities"
echo "   - Выберите Team (ваш Apple ID)"
echo "   - Добавьте папку content как 'folder reference' (синий цвет)"
echo ""
echo "3. Подключите iPhone и нажмите ▶️ Play"
echo ""
echo "📖 Подробная инструкция: ios/INSTALL_IPHONE.md"

