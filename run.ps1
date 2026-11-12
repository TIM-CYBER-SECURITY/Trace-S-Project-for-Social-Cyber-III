# =========================================================================
# PUSSIBERAD TNI AD - TRACE-S (Social Cyber Intelligence)
# Kategori 04: Siber Sosial - Kartika Cyber Hackathon 2026
# =========================================================================

Write-Host "=========================================================================" -ForegroundColor Cyan
Write-Host "      PUSSIBERAD TNI AD - TRACE-S (Social Cyber Intelligence)            " -ForegroundColor Green
Write-Host "      Kategori 04: Siber Sosial - Kartika Cyber Hackathon 2026           " -ForegroundColor Yellow
Write-Host "=========================================================================" -ForegroundColor Cyan

$phpBin = "php"
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    if (Test-Path ".tools\php\php.exe") {
        $phpBin = ".\.tools\php\php.exe"
        Write-Host "[OK] Menggunakan portable PHP: .tools\php\php.exe" -ForegroundColor Green
    } else {
        Write-Host "[ERROR] PHP tidak ditemukan di sistem dan .tools\php\php.exe tidak tersedia." -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host "[OK] Menggunakan system PHP terpasang." -ForegroundColor Green
}

# Cek .env
if (-not (Test-Path ".env")) {
    Write-Host "[INFO] Membuat file .env dari .env.example..." -ForegroundColor Yellow
    Copy-Item ".env.example" ".env"
    & $phpBin artisan key:generate
}

# Cek SQLite
if (-not (Test-Path "database\database.sqlite") -or (Get-Item "database\database.sqlite").Length -eq 0) {
    Write-Host "[INFO] Inisialisasi database SQLite dan seeder skenario..." -ForegroundColor Yellow
    New-Item -ItemType File -Path "database\database.sqlite" -Force | Out-Null
    & $phpBin artisan migrate:fresh --seed --force
}

Write-Host ""
Write-Host "=========================================================================" -ForegroundColor Cyan
Write-Host " Sistem siap dijalankan!                                                 " -ForegroundColor White
Write-Host " URL Akses : http://127.0.0.1:8000                                       " -ForegroundColor Green
Write-Host "                                                                         "
Write-Host " Akun Default Evaluasi (4 Peran):                                        " -ForegroundColor White
Write-Host "   - Operator : operator@traces.mil.id (Password: operator123)          " -ForegroundColor Gray
Write-Host "   - Analis   : analis@traces.mil.id   (Password: analis123)            " -ForegroundColor Gray
Write-Host "   - Komandan : komandan@traces.mil.id (Password: komandan123)          " -ForegroundColor Gray
Write-Host "   - Admin    : admin@traces.mil.id    (Password: admin123)             " -ForegroundColor Gray
Write-Host "=========================================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Menjalankan Laravel Server (Tekan Ctrl+C untuk berhenti)..." -ForegroundColor White

& $phpBin artisan serve --host=127.0.0.1 --port=8000
