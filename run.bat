@echo off
setlocal enabledelayedexpansion

:: 1. Pastikan selalu berada di folder tempat file run.bat berada
cd /d "%~dp0"

title PUSSIBERAD TRACE-S // Tactical Cyber Intelligence Platform

echo =========================================================================
echo       PUSSIBERAD TNI AD - TRACE-S (Social Cyber Intelligence)
echo       Kategori 04: Siber Sosial - Kartika Cyber Hackathon 2026
echo =========================================================================
echo.

:: 2. Tentukan binary PHP (System PHP atau Portable .tools\php)
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

echo [INFO] PHP belum terdeteksi. Menyiapkan portable PHP 8.3 secara otomatis...
if exist ".tools\setup-php.ps1" (
    powershell -ExecutionPolicy Bypass -File ".tools\setup-php.ps1"
    if exist ".tools\php\php.exe" (
        set PHP_BIN=.tools\php\php.exe
        echo [OK] Portable PHP berhasil disiapkan!
        goto check_env
    )
)

echo [ERROR] PHP tidak ditemukan di sistem dan folder .tools\php tidak tersedia.
echo Silakan pastikan folder .tools\php ada atau koneksi internet aktif.
pause
exit /b 1

:check_env
:: 3. Cek file .env
if not exist ".env" (
    echo [INFO] Menyiapkan file .env dari .env.example...
    copy .env.example .env >nul
    %PHP_BIN% artisan key:generate
)

:: 4. Cek database SQLite
if not exist "database\database.sqlite" (
    echo [INFO] Inisialisasi database SQLite dan seeder skenario PUSSIBERAD...
    type nul > database\database.sqlite
    %PHP_BIN% artisan migrate:fresh --seed --force
)

echo.
echo =========================================================================
echo  Sistem siap dijalankan!
echo  URL Akses : http://127.0.0.1:8000
echo  
echo  Akun Default Evaluasi (Tersedia Tombol Quick Switch di Login):
echo    - Operator : operator@siberad.mil.id (Password: password)
echo    - Analis   : analis@siberad.mil.id   (Password: password)
echo    - Komandan : komandan@siberad.mil.id (Password: password)
echo    - Admin    : admin@siberad.mil.id    (Password: password)
echo =========================================================================
echo.
echo Menjalankan Laravel Development Server... Tekan Ctrl+C untuk berhenti.
%PHP_BIN% artisan serve --host=127.0.0.1 --port=8000
