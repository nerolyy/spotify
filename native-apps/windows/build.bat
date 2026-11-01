@echo off
setlocal enabledelayedexpansion

echo Building Muzic2 Windows application...

set PROJECT_DIR=%~dp0
set PROJECT_ROOT=%PROJECT_DIR%..\..

:: Check if .NET SDK is installed
dotnet --version >nul 2>&1
if errorlevel 1 (
    echo Error: .NET SDK not found. Please install .NET 6.0 SDK or later.
    echo Download from: https://dotnet.microsoft.com/download
    exit /b 1
)

:: Build the application
echo Building application...
dotnet publish -c Release -r win-x64 --self-contained false -p:PublishSingleFile=false -p:IncludeNativeLibrariesForSelfExtract=true

if errorlevel 1 (
    echo Build failed!
    exit /b 1
)

:: Copy public directory to output
set OUTPUT_DIR=%PROJECT_DIR%bin\Release\net6.0-windows\win-x64\publish
if exist "%PROJECT_ROOT%\public" (
    echo Copying public directory...
    xcopy /E /I /Y "%PROJECT_ROOT%\public" "%OUTPUT_DIR%\public"
)

:: Copy database if exists
if exist "%PROJECT_ROOT%\db" (
    echo Copying database...
    xcopy /E /I /Y "%PROJECT_ROOT%\db" "%OUTPUT_DIR%\db"
)

echo.
echo Build complete! Output directory: %OUTPUT_DIR%
echo.
echo To run the application:
echo   cd %OUTPUT_DIR%
echo   Muzic2.exe

pause

