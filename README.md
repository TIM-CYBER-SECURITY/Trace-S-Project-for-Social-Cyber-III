# TRACE-S // PUSSIBERAD TNI AD
### Social Cyber Intelligence Platform (Platform Intelijen Siber Sosial)
**Kategori 04: Siber Sosial — Kartika Cyber Hackathon 2026**

---

## 1. Ringkasan Eksekutif & Filosofi Sistem

**TRACE-S (Tactical Reconnaissance, Analysis, and Credibility Evaluation for Social cyber)** adalah platform intelijen siber sosial kelas militer yang dirancang khusus untuk Pusat Siber TNI AD (**PUSSIBERAD**). Platform ini mengintegrasikan pemantauan dini (*early warning*), analisis kredibilitas berbasis multi-dimensi, perbandingan keaslian teks (*authenticity comparator*), analisis jaringan aktor dan penyebaran disinformasi (*social network analysis & spread timeline*), verifikasi multi-sumber lintas kanal (*cross-source verification*), serta konsol interaktif berbantuan AI lokal dengan pengamanan ketat (*air-gapped local AI inference*).

Sistem ini diimplementasikan dengan kepatuhan penuh terhadap **MASTER PROMPT v2** dan **SUPPLEMENTAL MASTER PROMPT v1.0**, dengan prinsip etika, integritas, dan hukum operasional militer:
1. **Human-in-the-Loop & Akuntabilitas**: Rekomendasi AI dan skor probabilitas tidak pernah menjadi vonis mutlak. Keputusan operasional, penetapan status, dan penerbitan laporan berada di tangan perso
\

?"
nel manusia (Operator, Analis, dan Komandan).
2. **Kemandirian & Keamanan Data (Air-Gapped)**: AI inferensi beroperasi secara lokal di intranet militer (Ollama / vLLM / OpenAI-compatible / MockDriver) tanpa ketergantungan API pihak ketiga publik. `LocalGuard` memblokir akses ke domain internet eksternal saat mode intranet aktif.
3. **Anti Prompt-Injection**: Seluruh materi eksternal (web scraping, unggahan dokumen, konten media sosial) diperlakukan sebagai **DATA TIDAK TERPERCAYA** melalui `PromptGuard` sanitization dan isolasi pembatas tag khusus.
4. **Audit Trail Mutlak (Append-Only)**: Setiap aksi operator, perubahan model, otentikasi, dan eksekusi AI dicatat secara kriptografis pada model `SystemLog` yang dilarang diubah atau dihapus (*append-only enforcement* di tingkat Eloquent ORM).
5. **Kepatuhan Etika & Batasan Operasional**: Menolak segala bentuk otomasi *harassment*, serangan balik digital tak berizin, atau *botting*. Dataset skenario evaluasi menggunakan data tiruan (*fictitious simulation scenario*) untuk perlindungan privasi.

---

## 2. Struktur Modul & Fitur Utama

| Modul | Nama Modul | Deskripsi Kapabilitas Operasional |
| :--- | :--- | :--- |
| **Modul A** | **Early Warning Dashboard** | Visualisasi SVG peta kepulauan Indonesia dengan node ancaman berkedip, 4 metrik status taktis, Live Threat Feed terminal dengan polling otomatis, serta status konektor RSS/Media Sosial. |
| **Modul B** | **Credibility Scanner Suite** | **3-in-1 Suite**: (1) *Credibility Scan* dengan gauge radial skor risiko, deteksi manipulasi emosi, dan rubrik tertimbang; (2) *Authenticity Comparator* word-level LCS diff berwarna; (3) *Human Validation Review* untuk persetujuan analis. |
| **Modul C** | **Spread & SNA Analysis** | Analisis jaringan aktor ancaman (SNA), identifikasi **Pasien Nol (Node-A1)** sebagai titik pertama observasi dalam dataset, skor indikator otomatisasi bot (Automation Indicator), dan sebaran platform. |
| **Modul D** | **Command Reporting & Dossier** | Format resmi **16-Bagian PUSSIBERAD Intelligence Brief** dengan 5 penanda visual: `[FAKTA TERDOKUMENTASI]`, `[KLAIM SUMBER]`, `[INTERPRETASI AI]`, `[TEMUAN ANALIS]`, `[REKOMENDASI]`. Ekspor PDF A4 siap cetak via DomPDF. |
| **Modul E** | **AI Analyst Console** | Konsol interaktif militer dengan **Strict Tool Allowlist** (10 alat backend berizin; dilarang shell/SQL mentah), log inspeksi parameter eksekusi, serta pelindung injeksi prompt. |
| **Modul F** | **Local AI Server Management** | Registry model lokal, pemantauan latensi dan status runtime intranet, pengujian koneksi (*ping test*), pergantian model aktif (*hot-swap*), dan log audit pergantian model. |
| **Modul G** | **Investigasi Kasus & Digital Locker** | Manajemen alur investigasi 12-tahap (Pipeline Stepper), brankas bukti digital dengan hashing SHA-256 otomatis, metode perolehan (Manual Import & URL Fetcher dengan deteksi SSRF). |
| **Modul H** | **Cross-Source Verification** | Analisis independensi sumber (5 tipe relasi: *Syndicated, Secondary Citation, Common Primary, Independent, Unknown*), penarikan kutipan otomatis, dan perhitungan unit independen efektif. |

---

## 3. Matriks Otorisasi Peran (RBAC) & Kredensial Default

TRACE-S menerapkan 4 tingkat *clearance* militer. Tersedia tombol **Beralih Peran (Quick Switch)** di halaman login dan navigation bar atas untuk kemudahan tim penilai/juri hackathon:

| Peran (Role) | Pangkat / Jabatan Contoh | Email Akses | Password | Clearance Level | Cakupan Hak Akses |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Operator** | Serda / Operator Monitoring | `operator@siberad.mil.id` | `password` | `LEVEL-1 TACTICAL` | Dashboard, Kasus, Import Bukti, Scanner Read |
| **Analis** | Lettu / Analis Disinformasi | `analis@siberad.mil.id` | `password` | `LEVEL-2 OPERATIONAL` | Operator + Credibility Scan, Authenticity Compare, Human Validation Review, Console AI |
| **Komandan** | Kolonel / Asintel Siber | `komandan@siberad.mil.id` | `password` | `LEVEL-3 STRATEGIC` | Analis + Otorisasi Status Akhir Kasus, Penerbitan & Tanda Tangan Intel Brief PDF |
| **Administrator** | Mayor / Administrator Sistem | `admin@siberad.mil.id` | `password` | `LEVEL-4 SYSTEM` | Konfigurasi Host AI Lokal, Model Registry Switch, Pengawasan Immutable Audit Trail |

---

## 4. Persyaratan Sistem & Dependensi

- **Sistem Operasi**: Windows 10/11, Linux (Ubuntu/Debian/CentOS), atau macOS.
- **PHP**: PHP >= 8.2 (dengan ekstensi: `pdo_sqlite` atau `pdo_mysql`, `mbstring`, `openssl`, `curl`, `gd`, `intl`, `fileinfo`).
  *(Catatan: Proyek ini menyertakan runtime portable di folder `.tools/php` untuk kemudahan evaluasi instan di lingkungan Windows tanpa instalasi sistem).*
- **Composer**: Composer v2.x (dapat menggunakan `.tools/composer.phar` atau composer sistem).
- **Database**: SQLite (default zero-config out-of-the-box) atau MySQL/MariaDB.
- **Browser Modern**: Chrome, Edge, Firefox, atau Safari.

---

## 5. Panduan Menjalankan Aplikasi (Quick Start)

### Metode A: Menjalankan Langsung di Windows (Satu Klik)
Buka terminal PowerShell / Command Prompt di dalam direktori `trace-s` atau cukup klik dua kali file script:
```powershell
# Opsi 1: Menjalankan file batch
.\run.bat

# Opsi 2: Menjalankan file PowerShell
.\run.ps1
```
Script akan otomatis:
1. Memeriksa runtime PHP (menggunakan PHP sistem atau portable binary `.tools\php\php.exe`).
2. Menyiapkan berkas `.env` dari `.env.example` dan membangkitkan `APP_KEY`.
3. Menginisialisasi `database/database.sqlite` dan menjalankan seeder skenario kasus `TS-2026-001`.
4. Menjalankan server web pada `http://127.0.0.1:8000`.

---

### Metode B: Menjalankan Menggunakan PHP / Composer Sistem

1. **Buka Proyek di VS Code**:
   ```bash
   cd trace-s
   code .
   ```

2. **Salin Environment & Pasang Dependensi**:
   ```bash
   cp .env.example .env
   composer install
   php artisan key:generate
   ```

3. **Migrasi Database & Seeder Skenario**:
   ```bash
   php artisan migrate:fresh --seed
   ```

4. **Jalankan Development Server**:
   ```bash
   php artisan serve --port=8000
   ```

5. **Akses Aplikasi**:
   Buka peramban ke **`http://127.0.0.1:8000`** dan login menggunakan tombol **Quick Switch Peran** atau kredensial tabel di atas.

---

## 6. Menjalankan Automated Unit & Feature Tests

Sistem dilengkapi dengan paket pengujian otomatis PHPUnit yang memverifikasi integritas kontrol akses (RBAC), kalkulasi kemiripan teks TF-IDF, pembanding numerik, pelindung injeksi prompt, ketahanan log audit append-only, eksekusi AI scan, dan ekspor berkas PDF 16-bagian:

```bash
# Menjalankan seluruh rangkaian tes (100% Pass)
php artisan test

# Atau menggunakan portable PHP wrapper:
.\.tools\php\php.exe artisan test
```

Hasil verifikasi:
```text
   PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Feature\ExampleTest
  ✓ unauthenticated visitor redirected to login
  ✓ login page renders successfully

   PASS  Tests\Feature\TracePlatformTest
  ✓ rbac boundaries for operator vs admin
  ✓ komandan role can access intelligence reports
  ✓ text normalization and lexical similarity
  ✓ numeric entity comparer extracts and compares
  ✓ text diff computes word level changes
  ✓ claim matcher dimensions
  ✓ source relationship analyzer citations
  ✓ prompt guard blocks jailbreak attempts
  ✓ immutable audit log prevents updates and deletions
  ✓ credibility scanner simulation mode
  ✓ tool registry enforces allowlist
  ✓ report generation and pdf export

  Tests:    15 passed (71 assertions)
  Duration: 0.59s
```

---

## 7. Konfigurasi Mesin Inferensi AI (Modul F)

TRACE-S mendukung 4 jenis driver inferensi AI yang dapat dikonfigurasi melalui `.env` atau menu antarmuka Administrator (**Local AI Management**):

1. **`mock` (Bawaan)**:
   Driver simulasi internal PUSSIBERAD yang sepenuhnya deterministik, cepat, dan aman tanpa memerlukan GPU/server eksternal. Seluruh keluaran secara transparan dilabeli bendera `is_simulation: true` sesuai Supplemental §8.
2. **`ollama`**:
   Inferensi intranet lokal langsung ke Ollama API (`http://127.0.0.1:11434`), mendukung model lokal seperti `llama3:8b`, `mistral`, atau `qwen2.5:7b`.
3. **`openai_compatible`**:
   Inferensi ke vLLM, LM Studio, Text-Generation-WebUI, atau host OpenAI-compatible yang beroperasi pada intranet tertutup.
4. **`external`**:
   Secara baku dinonaktifkan (`AI_ALLOW_EXTERNAL=false`) untuk menjaga kerahasiaan data militer dan mencegah kebocoran informasi ke cloud publik.

---

## 8. Panduan Git & Push ke Repository GitHub

Folder `trace-s` telah disiapkan secara bersih untuk diinisialisasi dan diunggah ke repository GitHub Anda:

```bash
# Masuk ke direktori trace-s
cd trace-s

# Inisialisasi git (jika belum ada)
git init

# Tambahkan seluruh berkas proyek yang telah terfilter rapi oleh .gitignore
git add .

# Buat commit perdana
git commit -m "feat: complete TRACE-S Social Cyber Intelligence Platform v1.0"

# Hubungkan ke repository GitHub Anda
git remote add origin https://github.com/<username-anda>/<nama-repo>.git

# Push ke cabang utama (main)
git branch -M main
git push -u origin main
```

---

## 9. Struktur Folder Utama

```text
trace-s/
├── app/
│   ├── Http/
│   │   ├── Controllers/          # Controller Modul A s.d. H
│   │   └── Middleware/           # AuthenticateTrace, RoleMiddleware (RBAC)
│   ├── Models/                   # 18 Model Eloquent (SystemLog, Case, Evidence, dll.)
│   └── Services/
│       ├── Ai/                   # AiManager, Drivers (Mock, Ollama, OpenAI), ToolRegistry
│       │   └── Support/          # PromptGuard, LocalGuard, JsonExtractor
│       ├── Analysis/             # TextNormalizer, LexicalSimilarity, NumericEntityComparer,
│       │                         # ClaimAnalyzer, ClaimMatcher, TextDiff, CrossSource, SNA
│       ├── Ingestion/            # EvidenceCollectorService (SHA-256 Hashing, Safe URL Fetch)
│       ├── Reporting/            # ReportGeneratorService (16-Section Brief & DomPDF)
│       └── Support/              # AuditLogger (Append-Only Enforcement)
├── config/
│   └── trace.php                 # Matriks konfigurasi, RBAC, rubrik bobot, batas ambang
├── database/
│   ├── migrations/               # Skema tabel relasional lengkap
│   └── seeders/                  # Seeder skenario uji PUSSIBERAD TS-2026-001 & 4 user
├── resources/
│   └── views/                    # Blade templates bergaya militer taktis gelap (Extreme Dark)
│       ├── admin/                # Local AI & Immutable Audit views
│       ├── auth/                 # Gate login taktis & Role Quick Switch
│       ├── cases/                # Case Management & Digital Evidence Locker
│       ├── console/              # AI Analyst Console
│       ├── dashboard/            # Early Warning Dashboard & Archipelago SVG Map
│       ├── layouts/              # Tactical Master Layout (UTC Clock, Clearance Badge)
│       ├── network/              # Threat Actor Network & Spread Timeline
│       ├── reports/              # 16-Section Dossier & A4 DomPDF template
│       └── scanner/              # 3-in-1 Credibility & Authenticity Suite
├── routes/
│   └── web.php                   # Routing terlindungi dengan RBAC
├── tests/
│   └── Feature/                  # TracePlatformTest (15 test, 71 assertions)
├── run.bat                       # Script peluncur Windows instan
├── run.ps1                       # Script peluncur PowerShell instan
└── README.md                     # Dokumentasi komprehensif ini
```

---
**PUSSIBERAD TNI AD // TANGGUH, CERMAT, AKUNTABEL**
*Kategori 04: Siber Sosial — Kartika Cyber Hackathon 2026*
