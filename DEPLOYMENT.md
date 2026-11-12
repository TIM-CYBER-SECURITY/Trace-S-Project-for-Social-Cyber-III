# Panduan Deployment Produksi — TRACE-S v2.0

Dokumen ini menjelaskan konfigurasi dan langkah deployment aman untuk platform **TRACE-S v2.0** pada lingkungan produksi atau intranet tertutup.

> **PERINGATAN OTORISASI & KEAMANAN:** Deployment aplikasi ke lingkungan institusional atau operasional nyata **wajib** melalui penilaian keamanan independen, uji penetrasi (*penetration testing*), pengerasan sistem operasi/jaringan (*infrastructure hardening*), serta otorisasi keamanan formal sesuai kebijakan organisasi sebelum digunakan pada data nyata.

---

## 1. Variabel `.env` Produksi Wajib

Pastikan berkas `.env` produksi tidak pernah disalin dari contoh pengembangan tanpa penyesuaian:

```env
APP_NAME="TRACE-S"
APP_ENV=production
APP_KEY=base64:<bangkitkan-dengan-php-artisan-key-generate>
APP_DEBUG=false
APP_URL=https://trace-s.internal.example

# Keamanan Hash & Sesi HTTPS
HASH_DRIVER=argon2id
BCRYPT_ROUNDS=12
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_SECURE_COOKIE=true

# Database Produksi (gunakan MySQL 8+ / MariaDB 10.6+ / PostgreSQL, bukan SQLite)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=trace_s_prod
DB_USERNAME=trace_app_user
DB_PASSWORD=<kata-sandi-basis-data-kuat>

# Queue & Cache
QUEUE_CONNECTION=database
CACHE_STORE=database

# Proxy Tepercaya (sesuaikan dengan IP reverse proxy / Nginx / load balancer)
TRUSTED_PROXIES=127.0.0.1

# Lokasi Registry Trace-ID Eksternal (WAJIB di luar repositori & web root)
TRACE_PROVISIONING_PATH=/etc/trace-s/provisioning
TRACE_REGISTRY_PATH=/etc/trace-s/provisioning/registry
TRACE_ISSUED_PATH=/var/lib/trace-s/issued

# Proteksi Brute-Force & Otorisasi Server AI
TRACE_LOGIN_MAX_ATTEMPTS=5
TRACE_LOGIN_DECAY=300
TRACE_FETCH_ALLOW_PRIVATE=false

# Driver AI Intranet (mock | ollama | openai_compatible | external)
AI_DRIVER=ollama
AI_BASE_URL=http://127.0.0.1:11434
AI_MODEL=<isi-nama-model-intranet-yang-terpasang>
AI_ALLOW_EXTERNAL=false
AI_REQUIRE_LOCAL=true
TRACE_CLASSIFICATION_LABEL="TERBATAS // INTERNAL OPERASIONAL"
```

---

## 2. Pemisahan Web Root & Izin Direktori Eksternal

1. Arahkan *Document Root* Nginx/Apache **hanya ke direktori `public/`**, bukan ke root proyek.
2. Pastikan `storage/app/private` (tempat bukti digital mentah disimpan) tidak memiliki symlink ke `public/` dan hanya dapat dibaca oleh user proses PHP/web server (`0750` direktori, `0640` berkas).
3. Tempatkan direktori `TRACE_PROVISIONING_PATH`, `TRACE_REGISTRY_PATH`, dan `TRACE_ISSUED_PATH` **di luar repositori dan di luar web root**:
   ```bash
   sudo mkdir -p /etc/trace-s/provisioning/registry /var/lib/trace-s/issued
   sudo chown -R www-data:www-data /etc/trace-s/provisioning /var/lib/trace-s/issued
   sudo chmod 750 /etc/trace-s/provisioning /etc/trace-s/provisioning/registry
   sudo chmod 700 /var/lib/trace-s/issued
   ```
4. Pastikan file kredensial awal (`*.credential.txt`) pada `TRACE_ISSUED_PATH` segera dihapus setelah diserahkan melalui kanal aman.

---

## 3. Kompilasi Aset & Migrasi Database

TRACE-S v2.0 tidak menggunakan CDN eksternal pada runtime (seluruh font, ikon FontAwesome, Tailwind CSS, dan JS dikompilasi lokal ke `public/build/`).

```bash
# Dependensi PHP & kompilasi aset frontend
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# Jalankan migrasi & seeder RBAC saja (tanpa data simulasi/akun demo)
php artisan migrate --force
php artisan db:seed --class=RbacSeeder --force

# Cache konfigurasi & rute untuk performa produksi
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> Catatan: `DatabaseSeeder` secara otomatis melewati pembuatan fixture uji apabila `APP_ENV=production`. Pada produksi, hanya `RbacSeeder` yang wajib dijalankan untuk menyediakan definisi peran `ADMINISTRATOR` dan `OPERATOR` beserta matriks izin.

---

## 4. Provisioning Akun Awal Oleh Developer

Jangan pernah menanamkan akun atau kata sandi melalui seeder di produksi. Setelah institusi diverifikasi secara eksternal, terbitkan akun melalui CLI resmi:

```bash
# 1. Terbitkan akun Trace-ID awal (Administrator & Operator) setelah verifikasi eksternal
php artisan trace:issue-account "Nama Administrator" "Institusi Resmi" --role=ADMINISTRATOR --approved-by="Developer Authority"

# Atau validasi & impor dari berkas registry eksternal ($TRACE_REGISTRY_PATH/approved-accounts.json)
php artisan trace:validate-accounts
php artisan trace:provision-accounts --dry-run
php artisan trace:provision-accounts

# 2. Verifikasi daftar akun & integritas rantai audit hash SHA-256
php artisan trace:list-accounts
php artisan trace:audit-verify
```

Kredensial sementara ditulis hanya ke `$TRACE_ISSUED_PATH/<TRACE-ID>.credential.txt`. Serahkan melalui kanal aman, hapus berkas kredensial tersebut, dan pastikan pengguna langsung mengganti kata sandi saat login perdana di `/login`.

---

## 5. Worker Antrean, Scheduler & Backup

- **Queue Worker**: Jalankan supervisord/systemd untuk memproses antrean asinkron bila `TRACE_ANALYSIS_MODE=queue`:
  ```bash
  php artisan queue:work --sleep=3 --tries=3 --max-time=3600
  ```
- **Audit Integrity Check Berkala**: Jadwalkan pemeriksaan berkala melalui cron/systemd timer:
  ```bash
  0 * * * * cd /srv/trace-s && php artisan trace:audit-verify
  ```
- **Backup Terenkripsi**: Cadangkan basis data operasional, direktori `storage/app/private` (evidence vault), serta `/etc/trace-s/provisioning/registry` secara berkala ke penyimpanan terisolasi. Jangan pernah menurunkan atau mematikan trigger database `system_logs_no_update` dan `system_logs_no_delete`.

---

## 6. Verifikasi Kesehatan & Header Keamanan

Setelah aplikasi berjalan di balik HTTPS reverse proxy:

1. Periksa endpoint kesehatan minimal:
   ```bash
   curl -i https://trace-s.internal.example/health
   # Ekspektasi: HTTP/2 200 dengan body {"status":"ok"}
   ```
2. Verifikasi keberadaan header keamanan HTTP dari middleware `SecurityHeaders`:
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: DENY`
   - `Referrer-Policy: same-origin`
   - `Permissions-Policy: camera=(), microphone=(), geolocation=()`
   - `Strict-Transport-Security: max-age=31536000; includeSubDomains` (aktif otomatis saat koneksi HTTPS)
3. Verifikasi pemblokiran akses langsung ke `/storage/app/private`, `/provisioning/`, `.env`, dan `/database/`.
