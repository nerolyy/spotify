# Muzic2 Windows Application

Нативное Windows приложение для Muzic2.

## Требования

- Windows 10/11
- .NET 6.0 Runtime (или SDK для разработки)
- PHP (встроенный в приложение или установленный в системе)

## Установка .NET Runtime

Если у вас не установлен .NET Runtime, скачайте его с:
https://dotnet.microsoft.com/download/dotnet/6.0

## Сборка

### Требования для сборки

- Visual Studio 2022 или новее, ИЛИ
- .NET 6.0 SDK или новее

### Автоматическая сборка

```batch
build.bat
```

### Ручная сборка

```batch
dotnet publish -c Release -r win-x64 --self-contained false
```

Собранное приложение будет в папке:
```
bin\Release\net6.0-windows\win-x64\publish\
```

## Упаковка в установщик

### Вариант 1: WiX Toolset

1. Установите WiX Toolset: https://wixtoolset.org/
2. Создайте файл `Muzic2.wxs` для создания MSI установщика

### Вариант 2: Inno Setup

1. Установите Inno Setup: https://jrsoftware.org/isinfo.php
2. Создайте скрипт установки для создания EXE установщика

### Вариант 3: ClickOnce (Visual Studio)

1. Откройте проект в Visual Studio
2. Правый клик на проект → Publish
3. Настройте параметры публикации

## Включение PHP в приложение

Для полной автономности приложения рекомендуется включить PHP в пакет приложения:

1. Скачайте PHP для Windows: https://windows.php.net/download/
2. Распакуйте в папку `php` рядом с исполняемым файлом
3. Приложение автоматически найдет `php.exe` в этой папке

## Структура после сборки

```
publish/
├── Muzic2.exe
├── public/
│   ├── index.php
│   ├── assets/
│   └── ...
├── db/
│   └── database.sqlite
└── (другие файлы .NET)
```

## Запуск

Просто запустите `Muzic2.exe`. Приложение автоматически:
1. Найдет PHP (в системе или в папке приложения)
2. Запустит PHP встроенный сервер
3. Откроет интерфейс в WebView2

## Отладка

Если приложение не запускается, проверьте:

1. Установлен ли .NET Runtime
2. Установлен ли PHP (или находится ли он в папке приложения)
3. Существует ли папка `public` с содержимым сайта

Логи PHP сервера выводятся в консоль отладки Visual Studio или можно посмотреть в Task Manager процессы `php.exe`.

