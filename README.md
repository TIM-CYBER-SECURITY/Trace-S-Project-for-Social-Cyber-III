# TRACE-S — Institutional Social Cyber Intelligence Platform

**TRACE-S (Tactical Reconnaissance, Analysis, and Credibility Evaluation for Social cyber)** adalah platform manajemen informasi, tata kelola akses berbasis Trace-ID, dan analisis intelijen siber sosial berbasis bukti (*evidence-first*). Aplikasi dibangun di atas **Laravel 12 + Blade + Tailwind CSS + Vanilla JavaScript** terkompilasi lokal (tanpa CDN eksternal) dengan tampilan command center institusional (*Light default + Dark theme toggle*).

---

## 1. Prinsip Arsitektur & Batasan Keamanan

1. **Human-in-the-Loop & Pemisahan Tugas**: Analisis AI berfungsi sebagai pendukung keputusan (*advisory*). Validasi klaim (`VALIDATE` / `REJECT` / `RECLASSIFY`) dan persetujuan rekaman informasi ditetapkan oleh otoritas berwenang, tanpa izin menyetujui pengajuan milik sendiri.
2. **Privasi & Anti-Identitas Individu**: Sistem tidak melakukan resolusi identitas individu atau profiling warga. Paparan wilayah jaringan disajikan melalui **Geospatial & Network Exposure Grid** secara agregat (`Pulau -> Kota/Kab -> Kecamatan -> Subnet /24 & ASN`).
3. **Pelabelan Simulasi & Status Jujur**: Output `MockDriver` ditandai secara eksplisit (`SIMULASI`). Konektor atau server AI yang belum dikonfigurasi ditampilkan apa adanya sebagai `NOT CONFIGURED` atau `OFFLINE`.
4. **Kredensial Nol-Hardcode & Otentikasi Trace-ID**: Tidak ada pendaftaran publik, email demo, kata sandi bawaan, ataupun tombol *Quick Role Switch*. Login wajib memakai **Trace-ID (`TRS-XXXX-XXXX`)** + **Argon2id**.
5. **Audit Trail Append-Only dengan Hash Chain**: `SystemLog` dikunci pada level model Eloquent dan trigger database (`BEFORE UPDATE` / `BEFORE DELETE`), membentuk rantai hash SHA-256 yang dapat diverifikasi via `php artisan trace:audit-verify`.

---

## 2. Alur Pembuatan Akun Trace-ID (Developer-Controlled)

TRACE-S adalah sistem tertutup. Tidak tersedia halaman registrasi publik di dalam aplikasi:

1. **Permohonan Eksternal**: Institusi menghubungi tim pengembang melalui kanal komunikasi resmi untuk mengajukan akses personel (`nama`, `organisasi`, `satuan`, `jabatan`, `peran`, tujuan penggunaan, dan surat otorisasi bila diperlukan).
2. **Verifikasi & Persetujuan Developer**: Tim pengembang memverifikasi identitas institusi dan kelayakan peran sebelum menyetujui pembuatan akun.
3. **Penerbitan & Registry Eksternal (`provisioning/`)**: Tim pengembang mencatat entri tervalidasi JSON Schema (`provisioning/schema/account.schema.json`) ke `provisioning/registry/approved-accounts.json` atau menerbitkan langsung melalui CLI:
   - `php artisan trace:issue-account "<Nama>" "<Organisasi>" --role=ADMINISTRATOR|OPERATOR --approved-by="<Approver>"`
   - `php artisan trace:validate-accounts`
   - `php artisan trace:provision-accounts [--dry-run] [--only=TRS-XXXX-XXXX]`
4. **Penyerahan Kredensial & Login Awal (`/login`)**: Kata sandi sementara acak (CSPRNG) hanya ditulis ke berkas privat `provisioning/issued/<TRACE-ID>.credential.txt` (status akun `PROVISIONED`, `must_change_password = true`). Login pertama di `/login` mewajibkan penggantian kata sandi (min. 12 karakter: huruf besar, kecil, angka, simbol) sebelum status berubah menjadi `ACTIVE`.
5. **Penegakan Sesi Seketika**: Middleware `AuthenticateTrace` memeriksa status akun (`PROVISIONED`/`ACTIVE`, `APPROVED`, belum kedaluwarsa) pada setiap permintaan sehingga akun `SUSPENDED` atau `REVOKED` langsung terputus.

---

## 3. Matriks RBAC Operasional

Otorisasi dijalankan secara *deny-by-default* berbasis tabel `roles`, `permissions`, dan `role_permissions` (`Database\Seeders\RbacSeeder`, sumber kebenaran `config/trace.php`):

| Peran (`guard: web`) | Ruang Lingkup Hak Akses |
| :--- | :--- |
| **ADMINISTRATOR** | Manajemen akun Operator dalam organisasinya (`/admin/accounts` — penangguhan, aktivasi ulang, reset sandi sementara, masa berlaku), manajemen & penugasan Skills (`/admin/skills`), kesehatan & konfigurasi sistem (`/admin/system`), AI server/model (`/admin/ai`), audit log (`/admin/audit`), review/persetujuan rekaman informasi, validasi hasil scan (`analysis.review`), dan ekspor Brief PDF (`report.export`). |
| **OPERATOR** | Dashboard intelijen (`/early-warning`, `/sentiment`), investigasi kasus & impor bukti (`/cases`, `evidence.import`), pemeriksaan kredibilitas/komparator/lintas sumber (`/scanner`, `/network`), Konsol Analis AI terkunci kasus (`/console`), pengajuan & revisi rekaman informasi (`/records`), eksekusi Skills yang ditugaskan (`/skills`), serta riwayat pribadi (`/operator/history`). |

---

## 4. Menjalankan Aplikasi Secara Lokal

### Prasyarat
- **PHP >= 8.2** (tersedia runtime portable pada `.tools/php/php.exe` untuk Windows) dengan ekstensi `pdo_sqlite` / `pdo_mysql`, `mbstring`, `openssl`, `curl`, `gd`, `intl`.
- **Node.js & npm** untuk kompilasi aset frontend lokal ke `public/build/` (`esbuild`, `@tailwindcss/node`, `@fontsource/*`, `@fortawesome/fontawesome-free`).

### Langkah Menjalankan
```bash
# 1. Salin environment dan bangkitkan APP_KEY
cp .env.example .env
php artisan key:generate

# 2. Pasang dependensi & kompilasi aset lokal
composer install
npm install
npm run build

# 3. Jalankan migrasi & seeder RBAC (+ fixture simulasi khusus non-produksi)
php artisan migrate:fresh --seed

# 4. Terbitkan akun Trace-ID awal melalui jalur eksternal Developer CLI
php artisan trace:issue-account "Nama Administrator" "PUSSIBERAD" --role=ADMINISTRATOR --approved-by="Tim Pengembang"

# 5. Jalankan server lokal
php artisan serve --host=127.0.0.1 --port=8000
```
Atau di Windows jalankan `./run.bat` atau `./run.ps1`, lalu buka `http://127.0.0.1:8000/login`.

---

## 5. Perintah CLI Operasional & Pengujian

```bash
# Provisioning & Manajemen Akun Eksternal
php artisan trace:issue-account "<Nama>" "<Organisasi>" --role=OPERATOR --approved-by="<Approver>"
php artisan trace:validate-accounts
php artisan trace:validate-registry
php artisan trace:provision-accounts [--dry-run] [--only=TRS-XXXX-XXXX]
php artisan trace:list-accounts [--status=ACTIVE]
php artisan trace:suspend-account TRS-XXXX-XXXX --reason="Alasan"
php artisan trace:revoke-account TRS-XXXX-XXXX --reason="Alasan"

# Verifikasi Integritas Audit Log (Hash Chain SHA-256)
php artisan trace:audit-verify

# Rangkaian Pengujian Otomatis PHPUnit
php artisan test
```

> Untuk panduan hardening dan deployment produksi, lihat [DEPLOYMENT.md](DEPLOYMENT.md) dan [provisioning/README.md](provisioning/README.md).
