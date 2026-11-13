@echo off
setlocal enabledelayedexpansion

cd /d "%~dp0"
title TRACE-S v2.0 // Institutional Social Cyber Intelligence Platform

echo =========================================================================
echo   TRACE-S v2.0 // Institutional Information & Social Cyber Platform
echo   Military-Oriented Operations - RBAC - External Trace-ID Provisioning
echo =========================================================================
echo.

set PHP_BIN=php
where php >nul 2>nul
if %ERRORLEVEL% equ 0 (
    echo [OK] Menggunakan system PHP terpasang.
    goto check_env
)

if exist ".tools\php\php.exe" (
    set PHP_BIN=.tools\php\php.exe
    echo [OK] Menggunakan portable PHP: .tools\php\php.exe
    goto check_env
)

if exist "%~dp0.tools\php\php.exe" (
    set PHP_BIN="%~dp0.tools\php\php.exe"
    echo [OK] Menggunakan portable PHP: .tools\php\php.exe
    goto check_env
)

echo [INFO] PHP belum terdeteksi. Menyiapkan portable PHP 8.3...
if exist ".tools\setup-php.ps1" (
    powershell -ExecutionPolicy Bypass -File ".tools\setup-php.ps1"
    if exist ".tools\php\php.exe" (
        set PHP_BIN=.tools\php\php.exe
        echo [OK] Portable PHP siap digunakan.
        goto check_env
    )
)

echo [ERROR] PHP tidak ditemukan di sistem dan folder .tools\php tidak tersedia.
pause
exit /b 1

:check_env
if not exist ".env" (
    echo [INFO] Menyiapkan .env dari .env.example...
    copy .env.example .env >nul
    %PHP_BIN% artisan key:generate
)

if not exist "database\database.sqlite" (
    echo [INFO] Inisialisasi database SQLite, RBAC, dan skenario simulasi lokal...
    type nul > database\database.sqlite
    %PHP_BIN% artisan migrate:fresh --seed --force
)

if not exist "public\build\manifest.json" (
    echo [WARN] Asset frontend terkompilasi tidak ditemukan di public\build\manifest.json.
    where npm >nul 2>nul
    if %ERRORLEVEL% equ 0 (
        echo [INFO] Menjalankan npm run build...
        call npm run build
    ) else (
        echo [WARN] Jalankan "npm install && npm run build" secara manual sebelum membuka UI.
    )
)

set TRACE_PORT=8000
for %%P in (8000 8080 8081 8082 8888 9000) do (
    powershell -NoProfile -Command "if (Get-NetTCPConnection -LocalPort %%P -ErrorAction SilentlyContinue) { exit 1 } else { exit 0 }" >nul 2>nul
    if !ERRORLEVEL! equ 0 (
        set TRACE_PORT=%%P
        goto port_found
    ) else (
        echo [WARN] Port %%P sedang terpakai/gantung di Windows. Memeriksa port alternatif...
    )
)
:port_found

echo.
echo =========================================================================
echo  Sistem siap dijalankan (Mode Operasional Lokal)
echo  URL Utama         : http://127.0.0.1:!TRACE_PORT!/login
echo  Cek Kesehatan     : http://127.0.0.1:!TRACE_PORT!/health
echo.
echo  Catatan Keamanan ^& Provisioning Akun (Khusus Developer):
echo    Tidak ada pendaftaran publik maupun akun/kata sandi demo.
echo    Terbitkan akun baru setelah verifikasi institusi eksternal:
echo      provisioning\tools\issue-account.bat "Nama" "Organisasi" --role=ADMINISTRATOR --approved-by="DevTeam"
echo    Atau validasi ^& impor dari registry eksternal:
echo      %PHP_BIN% artisan trace:validate-accounts
echo      %PHP_BIN% artisan trace:provision-accounts
echo =========================================================================
echo.
echo Menjalankan server pengembangan Laravel pada port !TRACE_PORT!... Tekan Ctrl+C untuk berhenti.
set PHP_CLI_SERVER_WORKERS=4
%PHP_BIN% artisan serve --host=127.0.0.1 --port=!TRACE_PORT!
