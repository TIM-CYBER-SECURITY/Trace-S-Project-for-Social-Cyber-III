# TRACE-S — Institutional Social Cyber Cognitive Defense & Intelligence Platform
### KARTIKA HACKATHON CYBER 2026 // KATEGORI 04: SIBER SOSIAL (COGNITIVE DOMAIN)

**Mitra Implementasi:** Pusat Siber TNI Angkatan Darat (PUSSIBERAD TNI AD) — Kode Instansi: `PUS001TNIAD`  
**Tim Pengusul:** Sindikat_PawPatrol  
**Status Evaluasi:** Production-Ready • Zero-Simulation • 24/7 Cloud Deployment  

> 🌐 **Live Cloud Purwarupa (24/7 Evaluasi Juri):** [https://trace-s-web-production.up.railway.app](https://trace-s-web-production.up.railway.app)  
> 📹 **Video Demo Purwarupa (YouTube):** [https://www.youtube.com/watch?v=Ta0ANIhyg-8](https://www.youtube.com/watch?v=Ta0ANIhyg-8)  
> 📄 **Proposal Solusi Resmi:** [PROPOSAL_SOLUSI_TRACE-S.pdf](PROPOSAL_SOLUSI_TRACE-S.pdf)  
> 📋 **Dokumentasi Teknis Lengkap:** [DOKUMENTASI_TEKNIS_TRACE-S.pdf](DOKUMENTASI_TEKNIS_TRACE-S.pdf)  
> 🔑 **Daftar Akun Resmi & SOP Akses:** [DAFTAR_AKUN_RESMI_PUS001TNIAD.pdf](DAFTAR_AKUN_RESMI_PUS001TNIAD.pdf)

---

**TRACE-S (Tactical Reconnaissance, Analysis, and Credibility Evaluation for Social cyber)** adalah platform manajemen informasi, tata kelola akses berbasis Trace-ID, dan analisis intelijen siber sosial berbasis bukti (*evidence-first*). Aplikasi dibangun di atas **Laravel + Vue 3 / Inertia + Blade + Tailwind CSS** terkompilasi lokal (tanpa CDN eksternal) dengan tampilan command center institusional (*Light default + Dark theme toggle*).

---

## 1. Prinsip Arsitektur, Kedaulatan AI & Batasan Keamanan

1. **Model-Agnostic Local AI & Zero Vendor Lock-In**:
   - Engine AI Trace-S mengimplementasikan gateway standar *OpenAI-Compatible* (`http://127.0.0.1:1234/v1` via LM Studio atau `http://localhost:11434/v1` via Ollama).
   - **Mendukung SELURUH model AI lokal** yang dimiliki atau diunduh di workstation analis: keluarga **Qwen2.5**, **DeepSeek-R1 / DeepSeek-Coder**, **Llama 3 / 3.1**, **Mistral**, **Gemma 2**, **Phi-3 / Phi-4**, hingga bobot model kustom hasil *fine-tuning* doktrin internal militer TNI AD (*TNI-LLM*).
   - **Reasoning Scratchpad Stripper**: Engine secara otomatis memfilter dan membersihkan blok `<think>...</think>` dari model reasoning modern (seperti DeepSeek-R1 atau Qwen-QwQ) untuk menghasilkan output intelijen JSON berstandar operasional.
2. **Hybrid AI Auto-Failover (Cloud ↔ Air-Gapped Local)**:
   - Terhubung secara adaptif ke Cloud AI (Google AI Studio & Exa.ai) untuk throughput komputasi masif saat online.
   - *Auto-failover* instan ke Local AI On-Premise saat terjadi pemutusan jaringan / kondisi *air-gapped* demi menjamin kerahasiaan data rahasia negara (*zero data leakage*).
3. **Human-in-the-Loop & Pemisahan Tugas**: Analisis AI berfungsi sebagai pendukung keputusan (*advisory*). Validasi klaim (`VALIDATE` / `REJECT` / `RECLASSIFY`) dan persetujuan rekaman informasi ditetapkan oleh otoritas berwenang, tanpa izin menyetujui pengajuan milik sendiri.
4. **Privasi & Anti-Identitas Individu**: Sistem tidak melakukan resolusi identitas individu atau profiling warga. Paparan wilayah jaringan disajikan melalui **Geospatial & Network Exposure Grid** secara agregat (`Pulau -> Kota/Kab -> Kecamatan -> Subnet /24 & ASN`).
5. **Zero-Simulation & Status Jujur**: Seluruh engine verifikasi bekerja secara deterministik matematis & semantik nyata (*Numeric Entity Differencing*, *Claim Matcher*, *Lexical Jaccard Similarity*, dan live search calibration). Telah lulus 49 test suites (578 assertions) dan 60/60 pengujian empiris tanpa dummy.
6. **Kredensial Nol-Hardcode & Otentikasi Trace-ID**: Tidak ada pendaftaran publik, email demo, kata sandi bawaan, ataupun tombol *Quick Role Switch*. Login wajib memakai **Trace-ID (`TRS-XXXX-XXXX`)** + **Argon2id**.
7. **Audit Trail Append-Only dengan Hash Chain**: `SystemLog` dikunci pada level model Eloquent dan trigger database (`BEFORE UPDATE` / `BEFORE DELETE`), membentuk rantai hash SHA-256 yang dapat diverifikasi via `php artisan trace:audit-verify`.

---

## 2. Alur Pembuatan Akun Trace-ID (Developer-Controlled)

TRACE-S adalah sistem tertutup. Tidak tersedia halaman registrasi publik di dalam aplikasi demi menjaga perimeter keamanan militer:

1. **Permohonan Eksternal**: Institusi menghubungi tim pengembang melalui kanal komunikasi resmi untuk mengajukan akses personel (`nama`, `organisasi`, `satuan`, `jabatan`, `peran`, `kode_instansi: PUS001TNIAD`, tujuan penggunaan, dan surat otorisasi bila diperlukan).
2. **Verifikasi & Persetujuan Developer**: Tim pengembang memverifikasi identitas institusi dan kelayakan peran sebelum menyetujui pembuatan akun.
3. **Penerbitan & Registry Eksternal (`provisioning/`)**: Tim pengembang mencatat entri tervalidasi JSON Schema (`provisioning/schema/account.schema.json`) ke `provisioning/registry/approved-accounts.json` atau menerbitkan langsung melalui CLI:
   - `php artisan trace:issue-account "<Nama>" "<Organisasi>" --role=ADMINISTRATOR|OPERATOR --approved-by="<Approver>"`
   - `php artisan trace:validate-accounts`
   - `php artisan trace:provision-accounts [--dry-run] [--only=TRS-XXXX-XXXX]`
4. **Penyerahan Kredensial & Login Awal (`/login`)**: Kata sandi sementara acak (CSPRNG) hanya ditulis ke berkas privat `provisioning/issued/<TRACE-ID>.credential.txt` (status akun `PROVISIONED`, `must_change_password = true`). Login pertama di `/login` mewajibkan penggantian kata sandi (min. 12 karakter: kombinasi huruf besar, kecil, angka, simbol) sebelum status berubah menjadi `ACTIVE`.
5. **Penegakan Sesi Seketika**: Middleware `AuthenticateTrace` memeriksa status akun (`PROVISIONED`/`ACTIVE`, `APPROVED`, belum kedaluwarsa) pada setiap permintaan sehingga akun `SUSPENDED` atau `REVOKED` langsung terputus.

---

## 3. Matriks RBAC Operasional (Dual-Role)

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

# 3. Jalankan migrasi & seeder RBAC (+ fixture kasus PUS001TNIAD)
php artisan migrate:fresh --seed

# 4. Terbitkan akun Trace-ID awal melalui jalur eksternal Developer CLI
php artisan trace:issue-account "Nama Administrator" "PUSSIBERAD TNI AD" --role=ADMINISTRATOR --approved-by="Tim Pengembang"

# 5. Jalankan server lokal
php artisan serve --host=127.0.0.1 --port=8000
```
Atau di Windows jalankan `./run.bat` atau `./run.ps1`, lalu buka `http://127.0.0.1:8000/login`.

---

## 5. Perintah CLI Operasional & Rangkaian Uji

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

# Rangkaian Pengujian Otomatis PHPUnit (49 Test Suite / 578 Assertions)
php artisan test
```

> Seluruh arsitektur mendalam, protokol keamanan, dan bukti empiris dapat dibaca pada [DOKUMENTASI_TEKNIS_TRACE-S.pdf](DOKUMENTASI_TEKNIS_TRACE-S.pdf) dan [PROPOSAL_SOLUSI_TRACE-S.pdf](PROPOSAL_SOLUSI_TRACE-S.pdf).
