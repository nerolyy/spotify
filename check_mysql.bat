@echo off
chcp 65001 >nul
echo ========================================
echo  Проверка подключения к MySQL
echo ========================================
echo.

cd /d "%~dp0"

REM Пробуем найти PHP в MAMP
set PHP_PATH=
if exist "C:\MAMP\bin\php\php8.2.0\php.exe" set PHP_PATH=C:\MAMP\bin\php\php8.2.0\php.exe
if exist "C:\MAMP\bin\php\php8.1.0\php.exe" set PHP_PATH=C:\MAMP\bin\php\php8.1.0\php.exe
if exist "C:\MAMP\bin\php\php8.0.0\php.exe" set PHP_PATH=C:\MAMP\bin\php\php8.0.0\php.exe
if exist "C:\MAMP\bin\php\php7.4.0\php.exe" set PHP_PATH=C:\MAMP\bin\php\php7.4.0\php.exe

if "%PHP_PATH%"=="" (
    where php >nul 2>&1
    if %errorlevel%==0 set PHP_PATH=php
)

if "%PHP_PATH%"=="" (
    echo ОШИБКА: PHP не найден!
    pause
    exit /b 1
)

echo Проверяю подключение к MySQL...
echo.

"%PHP_PATH%" -r "try { $pdo = new PDO('mysql:host=localhost;port=3306', 'root', 'root'); echo 'OK: Подключение к порту 3306 успешно\n'; } catch (Exception $e) { echo 'ОШИБКА порт 3306: ' . $e->getMessage() . '\n'; }"

"%PHP_PATH%" -r "try { $pdo = new PDO('mysql:host=localhost;port=8889', 'root', 'root'); echo 'OK: Подключение к порту 8889 успешно\n'; } catch (Exception $e) { echo 'ОШИБКА порт 8889: ' . $e->getMessage() . '\n'; }"

echo.
echo ========================================
echo  Проверка завершена
echo ========================================
echo.
echo Если оба порта не работают:
echo 1. Убедитесь, что MAMP запущен
echo 2. Проверьте, что MySQL работает (зелёный индикатор)
echo 3. Проверьте настройки в MAMP - какой порт использует MySQL
echo.
pause

