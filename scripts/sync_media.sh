#!/bin/bash

# Скрипт для синхронизации медиа файлов между устройствами
# Использование: ./sync_media.sh [source] [destination]

SOURCE_DIR="/Applications/MAMP/htdocs/muzic2/tracks"
DEST_DIR=""

# Параметры командной строки
if [ $# -ge 1 ]; then
    SOURCE_DIR="$1"
fi
if [ $# -ge 2 ]; then
    DEST_DIR="$2"
fi

# Если не указан destination, показываем помощь
if [ -z "$DEST_DIR" ]; then
    echo "Использование: $0 [source_dir] [destination]"
    echo ""
    echo "Примеры:"
    echo "  $0 /path/to/source /path/to/destination"
    echo "  $0 /path/to/source user@server:/path/to/destination"
    echo "  $0 /path/to/source ftp://user:pass@server/path"
    echo ""
    echo "Текущая папка источника: $SOURCE_DIR"
    exit 1
fi

# Проверяем существование исходной папки
if [ ! -d "$SOURCE_DIR" ]; then
    echo "Ошибка: Папка источника '$SOURCE_DIR' не существует"
    exit 1
fi

echo "Синхронизация медиа файлов..."
echo "Источник: $SOURCE_DIR"
echo "Назначение: $DEST_DIR"
echo ""

# Создаем временный файл для логов
LOG_FILE="/tmp/muzic2_sync_$(date +%Y%m%d_%H%M%S).log"

# Функция для rsync
sync_with_rsync() {
    echo "Используем rsync для синхронизации..."
    rsync -av --delete --progress \
        --exclude=".*" \
        --exclude="*.tmp" \
        --exclude="*.log" \
        "$SOURCE_DIR/" "$DEST_DIR/" 2>&1 | tee "$LOG_FILE"
    
    if [ ${PIPESTATUS[0]} -eq 0 ]; then
        echo "Синхронизация завершена успешно"
        echo "Лог сохранен в: $LOG_FILE"
    else
        echo "Ошибка при синхронизации. Проверьте лог: $LOG_FILE"
        exit 1
    fi
}

# Функция для FTP
sync_with_ftp() {
    echo "Используем FTP для синхронизации..."
    # Создаем временный скрипт для lftp
    FTP_SCRIPT="/tmp/muzic2_ftp_script.txt"
    cat > "$FTP_SCRIPT" << EOF
set ftp:list-options -a
set cmd:fail-exit true
open $DEST_DIR
mirror -R --delete --verbose "$SOURCE_DIR" /tracks
quit
EOF
    
    lftp -f "$FTP_SCRIPT" 2>&1 | tee "$LOG_FILE"
    
    if [ ${PIPESTATUS[0]} -eq 0 ]; then
        echo "FTP синхронизация завершена успешно"
        echo "Лог сохранен в: $LOG_FILE"
    else
        echo "Ошибка при FTP синхронизации. Проверьте лог: $LOG_FILE"
        exit 1
    fi
    
    rm -f "$FTP_SCRIPT"
}

# Определяем тип назначения и выбираем метод синхронизации
if [[ "$DEST_DIR" =~ ^ftp:// ]]; then
    sync_with_ftp
elif [[ "$DEST_DIR" =~ ^[a-zA-Z0-9._-]+@ ]]; then
    sync_with_rsync
else
    sync_with_rsync
fi

echo ""
echo "Синхронизация завершена!"
echo "Проверьте лог для деталей: $LOG_FILE"
