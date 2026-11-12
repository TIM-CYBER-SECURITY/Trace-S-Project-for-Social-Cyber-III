# =========================================================================
# TRACE-S v2.0 // Institutional Social Cyber Intelligence Platform
# =========================================================================

Write-Host "=========================================================================" -ForegroundColor DarkGreen
Write-Host "  TRACE-S v2.0 // Institutional Information & Social Cyber Platform     " -ForegroundColor White
Write-Host "  RBAC Governance - External Trace-ID Provisioning - Append-Only Audit  " -ForegroundColor Gray
Write-Host "=========================================================================" -ForegroundColor DarkGreen

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

if (-not (Test-Path ".env")) {
    Write-Host "[INFO] Membuat .env dari .env.example..." -ForegroundColor Yellow
    Copy-Item ".env.example" ".env"
    & $phpBin artisan key:generate
}

if (-not (Test-Path "database\database.sqlite") -or (Get-Item "database\database.sqlite").Length -eq 0) {
    Write-Host "[INFO] Inisialisasi database SQLite, RBAC, dan skenario simulasi lokal..." -ForegroundColor Yellow
    New-Item -ItemType File -Path "database\database.sqlite" -Force | Out-Null
    & $phpBin artisan migrate:fresh --seed --force
}

if (-not (Test-Path "public\build\manifest.json")) {
    Write-Host "[WARN] Asset frontend terkompilasi tidak ditemukan (public/build/manifest.json)." -ForegroundColor Yellow
    if (Get-Command npm -ErrorAction SilentlyContinue) {
        Write-Host "[INFO] Menjalankan npm run build..." -ForegroundColor Yellow
        npm run build
    } else {
        Write-Host "[WARN] npm tidak ditemukan. Jalankan 'npm install && npm run build' secara manual." -ForegroundColor Yellow
    }
}

$tracePort = 8000
foreach ($candidatePort in @(8000, 8080, 8081, 8082)) {
    $busy = $false
    try {
        $conns = Get-NetTCPConnection -LocalPort $candidatePort -ErrorAction SilentlyContinue
        if ($conns) { $busy = $true }
    } catch { }
    if (-not $busy) {
        $tracePort = $candidatePort
        break
    } else {
        Write-Host "[WARN] Port $candidatePort sedang terpakai/gantung di Windows. Memilih port alternatif..." -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "=========================================================================" -ForegroundColor DarkGreen
Write-Host " Sistem siap dijalankan (Mode Operasional Lokal)" -ForegroundColor White
Write-Host " URL Utama         : http://127.0.0.1:$tracePort/login" -ForegroundColor Green
Write-Host " Cek Kesehatan     : http://127.0.0.1:$tracePort/health" -ForegroundColor Gray
Write-Host ""
Write-Host " Catatan Keamanan & Provisioning Akun (Khusus Developer):" -ForegroundColor Yellow
Write-Host "   Tidak ada pendaftaran publik maupun akun/kata sandi demo." -ForegroundColor Gray
Write-Host "   Terbitkan akun setelah verifikasi institusi eksternal:" -ForegroundColor Gray
Write-Host "     $phpBin artisan trace:issue-account ""Nama"" ""Organisasi"" --role=ADMINISTRATOR --approved-by=""DevTeam""" -ForegroundColor Gray
Write-Host "   Validasi & impor dari provisioning/registry/approved-accounts.json:" -ForegroundColor Gray
Write-Host "     $phpBin artisan trace:validate-accounts" -ForegroundColor Gray
Write-Host "     $phpBin artisan trace:provision-accounts" -ForegroundColor Gray
Write-Host "=========================================================================" -ForegroundColor DarkGreen
Write-Host ""
Write-Host "Menjalankan Laravel Development Server pada port $tracePort (Ctrl+C untuk berhenti)..." -ForegroundColor White

& $phpBin artisan serve --host=127.0.0.1 --port=$tracePort
