@echo off
echo ========================================================
echo TRACE-S // START PUBLIC CLOUDFLARE TUNNEL (PORT 8080)
echo ========================================================
echo.
echo Pastikan server Laravel sedang berjalan (port 8080).
echo Membuka tunnel publik HTTPS...
echo.
.tools\cloudflared.exe tunnel --url http://127.0.0.1:8080
pause
