@echo off
setlocal
cd /d "%~dp0"
where php >nul 2>nul
if errorlevel 1 if exist "C:\xampp\php\php.exe" set "PATH=C:\xampp\php;%PATH%"
where php >nul 2>nul
if errorlevel 1 (echo Install PHP 8.2+ and add it to PATH first. & pause & exit /b 1)
where composer >nul 2>nul
if errorlevel 1 (echo Install Composer for this PHP version first. & pause & exit /b 1)
php tools\prepare.php
if errorlevel 1 goto fail
call composer install --no-dev --prefer-dist --optimize-autoloader
if errorlevel 1 goto fail
php tools\key.php
if errorlevel 1 goto fail
php artisan config:clear
if errorlevel 1 goto fail
php artisan migrate --force
if errorlevel 1 goto fail
php artisan storage:link
if errorlevel 1 echo Logo symlinks need Windows Developer Mode or administrator rights. Other screens can still work.
php artisan park:install
if errorlevel 1 goto fail
echo.
echo Setup complete. Run 02-start-windows.bat to start local testing.
php artisan park:database-check
if errorlevel 1 goto fail
pause
exit /b 0
:fail
echo.
echo Setup stopped. Read the error above; no database reset was performed.
pause
exit /b 1
