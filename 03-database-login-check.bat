@echo off
setlocal
cd /d "%~dp0"
where php >nul 2>nul
if errorlevel 1 if exist "C:\xampp\php\php.exe" set "PATH=C:\xampp\php;%PATH%"
where php >nul 2>nul
if errorlevel 1 (echo Install PHP 8.2+ and add it to PATH first. & pause & exit /b 1)
php artisan config:clear
if errorlevel 1 goto fail
php artisan park:database-check
if errorlevel 1 goto fail
pause
exit /b 0
:fail
echo Review the message above. No database reset was performed.
pause
exit /b 1
