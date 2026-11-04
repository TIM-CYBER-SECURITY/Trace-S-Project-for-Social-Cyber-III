@echo off
setlocal enabledelayedexpansion
title PUSSIBERAD TRACE-S // Tactical Cyber Intelligence Platform

echo =========================================================================
echo       PUSSIBERAD TNI AD - TRACE-S (Social Cyber Intelligence)
echo       Kategori 04: Siber Sosial - Kartika Cyber Hackathon 2026
echo =========================================================================
echo.

:: 1. Tentukan binary PHP (System PHP atau Portable .tools\php)
set PHP_BIN=php
where php >nul 2>nul
if %ERRORLEVEL% neq 0 (
    if exist ".tools\php\php.exe" (
        set PHP_BIN=.tools\php\php.exe
        echo [OK] Menggunakan portable PHP: .tools\php\php.exe
    ) else (
        echo [ERROR] PHP tidak ditemukan di sistem dan folder .tools\php tidak tersedia.
        pause
        exit /b 1
    )
) else (
    echo [OK] Menggunakan system PHP terpasang.
)

:: 2. Cek file .env
if not exist ".env" (
    echo [INFO] Membuat file .env dari .env.example...
    copy .env.example .env >nul
    %PHP_BIN% artisan key:generate
)

:: 3. Cek database SQLite
if not exist "database\database.sqlite" (
    echo [INFO] Membuat database SQLite...
    type nul > database\database.sqlite
    echo [INFO] Menjalankan migrasi dan seeder skenario PUSSIBERAD...
    %PHP_BIN% artisan migrate:fresh --seed --force
)

echo.
echo =========================================================================
echo  Sistem siap dijalankan!
echo  URL Akses: http://127.0.0.1:8000
echo  
echo  Akun Default Evaluasi (4 Peran):
echo    - Operator : operator@siberad.mil.id (Pass: password)
echo    - Analis   : analis@siberad.mil.id   (Pass: password)
echo    - Komandan : komandan@siberad.mil.id (Pass: password)
echo    - Admin    : admin@siberad.mil.id    (Pass: password)
echo =========================================================================
echo.
echo Menjalankan Laravel Development Server... Tekan Ctrl+C untuk berhenti.
%PHP_BIN% artisan serve --host=127.0.0.1 --port=8000
