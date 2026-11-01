@echo off
echo ========================================
echo  Установка базы данных Muzic2
echo ========================================
echo.

cd /d "%~dp0"

REM Пробуем найти PHP в MAMP
set PHP_PATH=
if exist "C:\MAMP\bin\php\php8.2.0\php.exe" set PHP_PATH=C:\MAMP\bin\php\php8.2.0\php.exe
if exist "C:\MAMP\bin\php\php8.1.0\php.exe" set PHP_PATH=C:\MAMP\bin\php\php8.1.0\php.exe
if exist "C:\MAMP\bin\php\php8.0.0\php.exe" set PHP_PATH=C:\MAMP\bin\php\php8.0.0\php.exe
if exist "C:\MAMP\bin\php\php7.4.0\php.exe" set PHP_PATH=C:\MAMP\bin\php\php7.4.0\php.exe

REM Если не нашли в MAMP, пробуем системный PHP
if "%PHP_PATH%"=="" (
    where php >nul 2>&1
    if %errorlevel%==0 (
        set PHP_PATH=php
    )
)

if "%PHP_PATH%"=="" (
    echo ОШИБКА: PHP не найден!
    echo.
    echo Убедитесь, что:
    echo 1. MAMP установлен в C:\MAMP
    echo 2. Или PHP добавлен в PATH
    echo.
    pause
    exit /b 1
)

echo Найден PHP: %PHP_PATH%
echo.
echo Запускаю установку базы данных...
echo.

"%PHP_PATH%" scripts\setup_db.php

if %errorlevel%==0 (
    echo.
    echo ========================================
    echo  Установка завершена успешно!
    echo ========================================
    echo.
    echo Теперь можно открыть:
    echo http://localhost:8888/muzic2/public/index.php
    echo.
) else (
    echo.
    echo ========================================
    echo  ОШИБКА при установке!
    echo ========================================
    echo.
    echo Проверьте:
    echo 1. MAMP запущен и MySQL работает
    echo 2. В файле src/config/db.php правильные настройки
    echo.
)

pause

