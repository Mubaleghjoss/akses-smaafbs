@echo off
setlocal

set "PROJECT_ROOT=%~dp0..\.."
set "PHP_BIN=%PHP_BIN%"

if "%PHP_BIN%"=="" if exist "E:\xampp\php\php.exe" set "PHP_BIN=E:\xampp\php\php.exe"
if "%PHP_BIN%"=="" if exist "C:\xampp\php\php.exe" set "PHP_BIN=C:\xampp\php\php.exe"
if "%PHP_BIN%"=="" set "PHP_BIN=php"

cd /d "%PROJECT_ROOT%"

echo Menjalankan Laravel queue worker...
echo Project  : %CD%
echo PHP      : %PHP_BIN%

"%PHP_BIN%" -r "exit(version_compare(PHP_VERSION, '8.2.0', '>=') ? 0 : 1);"
if errorlevel 1 (
    echo.
    echo ERROR: Project ini butuh PHP 8.2 atau lebih baru.
    echo Binary saat ini: %PHP_BIN%
    echo.
    echo Jika PHP 8.2 Anda ada di lokasi lain, set environment variable PHP_BIN
    echo atau edit file scripts\windows\queue-worker.bat.
    exit /b 1
)

echo PHP Ver  : OK ^(>= 8.2^)
echo Queue    : aktif dan menunggu antrean...

"%PHP_BIN%" artisan queue:work --queue=default --tries=3 --sleep=3 --timeout=120

endlocal
