@echo off
echo ========================================================
echo TRACE-S // START WEB APP & PUBLIC HTTPS TUNNEL
echo ========================================================
echo.
start "Trace-S Web Server" /min .tools\php\php.exe artisan serve --host=127.0.0.1 --port=8080
timeout /t 2 >nul
echo Web server berjalan di http://127.0.0.1:8080
echo.
echo Membuka tunnel publik Cloudflare...
.tools\cloudflared.exe tunnel --url http://127.0.0.1:8080
pause
