# Provisioning Akun Trace-ID (Developer-Controlled)

Direktori ini memisahkan **pengelolaan dan persetujuan akun institusional** dari antarmuka web TRACE-S. Pembuatan akun **tidak tersedia melalui web publik** — seluruh permohonan diterima, diverifikasi, dan disetujui oleh tim pengembang secara eksternal sebelum diprovisioning ke database.

| Path | Isi | Di-commit? |
|---|---|---|
| `schema/account.schema.json` | JSON Schema kanonik entri akun (`trace_id`, `role`, metadata persetujuan) | Ya |
| `schema/trace-id-account.schema.json` | JSON Schema kompatibel untuk skema entri akun eksternal | Ya |
| `templates/account-template.json` | Contoh format entri JSON aman (tanpa rahasia/kredensial nyata) | Ya |
| `tools/` | Skrip pembantu Windows (`issue-account.bat`, `validate-accounts.bat`, `provision-accounts.bat`, dll.) | Ya |
| `registry/approved-accounts.json` | Registry akun institusi yang telah disetujui secara eksternal | **Tidak** (gitignore) |
| `imports/`, `archive/` | Berkas impor sementara / arsip registry lama | **Tidak** |
| `issued/` | Berkas kredensial sementara (`<TRACE-ID>.credential.txt`, hapus setelah diserahkan) | **Tidak** |

> Untuk produksi, arahkan registry & issued ke lokasi terisolasi di luar repositori dan di luar web root:
> `TRACE_REGISTRY_PATH=/secure/trace/registry`, `TRACE_ISSUED_PATH=/secure/trace/issued`
> dengan izin `0700` (direktori) / `0600` (berkas) milik pengguna layanan.

## Aturan Keamanan

- Registry **dilarang keras** memuat kata sandi, hash kata sandi, rahasia pemulihan, atau token sesi.
- Database adalah sumber kebenaran status akun aktif (`PROVISIONED`, `ACTIVE`, `SUSPENDED`, `REVOKED`).
- Jabatan atau pangkat **tidak pernah** otomatis memberikan peran `ADMINISTRATOR`; setiap entri wajib mencantumkan `approved_by` dan `approved_at`.
- Hanya entri dengan `approval_status: APPROVED` yang dapat diimpor. Satu entri tidak valid menggagalkan seluruh proses validasi/impor.

## Alur Menambahkan Akun Baru (Dua Jalur CLI Resmi)

### Jalur 1 — Penerbitan Terintegrasi (`trace:issue-account`)
Setelah verifikasi permohonan institusi selesai:
```bash
php artisan trace:issue-account "Mayor Inf Contoh" "Satuan Operasi Siber" \
  --unit="Subdirektorat Analisis" \
  --position="Kepala Seksi" \
  --role=ADMINISTRATOR \
  --approved-by="Developer Verification Lead"
```
Atau di Windows gunakan `provisioning\tools\issue-account.bat` / `.ps1`. Perintah ini memvalidasi kelengkapan, menghasilkan Trace-ID unik (`TRS-XXXX-XXXX`), memperbarui `registry/approved-accounts.json`, membuat akun `PROVISIONED` di database, dan menuliskan kata sandi sementara acak **hanya** ke `issued/<TRACE-ID>.credential.txt`.

### Jalur 2 — Sunting Registry JSON + Provisioning Batch
1. Salin struktur dari `templates/account-template.json` ke `registry/approved-accounts.json` (array `accounts`).
2. Isi field wajib: `trace_id` (`TRS-XXXX-XXXX`), `full_name`, `organization`, `role` (`ADMINISTRATOR` atau `OPERATOR`), `approval_status` (`APPROVED`), `approved_by`, dan `approved_at` (RFC 3339).
3. Validasi skema dan keunikan:
   ```bash
   php artisan trace:validate-accounts
   ```
4. Simulasi tanpa menulis ke DB:
   ```bash
   php artisan trace:provision-accounts --dry-run
   ```
5. Jalankan provisioning:
   ```bash
   php artisan trace:provision-accounts
   ```
   Kata sandi sementara ditulis hanya ke `issued/<TRACE-ID>.credential.txt` (tidak pernah dicetak ke terminal).
6. Serahkan Trace-ID & kata sandi sementara melalui kanal aman terpisah, hapus berkas `.credential.txt`, dan instruksikan pengguna login di `/login` untuk mengganti kata sandi pada sesi pertama (`PROVISIONED -> ACTIVE`).

Menjalankan `trace:provision-accounts` berulang kali bersifat idempoten per `trace_id` (akun yang sudah ada di DB dilewati dengan status `SKIPPED_ALREADY_PROVISIONED`).

## Daftar Perintah CLI Resmi

| Perintah | Fungsi |
|---|---|
| `trace:issue-account` | Catat persetujuan ke registry + provision DB + tulis berkas kredensial privat |
| `trace:validate-accounts` | Validasi registry terhadap skema JSON, duplikasi, dan field terlarang |
| `trace:validate-registry` | Alias validasi registry eksternal |
| `trace:provision-accounts [--dry-run] [--only=TRS-...]` | Impor akun `APPROVED` dari registry ke database |
| `trace:list-accounts [--status=ACTIVE]` | Daftar akun operasional yang terdaftar di database |
| `trace:suspend-account TRS-... --reason=` | Tangguhkan akun + cabut seluruh sesi aktifnya |
| `trace:revoke-account TRS-... --reason=` | Cabut permanen akun + cabut seluruh sesi aktifnya |
| `trace:audit-verify` | Verifikasi integritas rantai hash SHA-256 log audit |
