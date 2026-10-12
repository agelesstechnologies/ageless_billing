@echo off
setlocal
cd /d "%~dp0"
where php >nul 2>nul
if errorlevel 1 if exist "C:\xampp\php\php.exe" set "PATH=C:\xampp\php;%PATH%"
if not exist vendor\autoload.php (echo Run 01-setup-windows.bat first. & pause & exit /b 1)
echo Local browser: http://localhost:8000
echo ESP SERVER_URL: http://YOUR-PC-LAN-IP:8000
echo This is the PHP development server. Use Apache or Nginx for live multi-reader deployment.
php artisan serve --host=0.0.0.0 --port=8000
pause
