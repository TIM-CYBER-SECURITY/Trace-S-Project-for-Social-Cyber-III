# Provisioning Trace-ID

Direktori ini memisahkan **manajemen akun** dari kode aplikasi. Menambah akun **tidak memerlukan perubahan kode frontend maupun backend**.

| Path | Isi | Di-commit? |
|---|---|---|
| `schema/trace-id-account.schema.json` | JSON Schema entri akun | Ya |
| `templates/account-template.json` | Contoh aman (data fiktif) | Ya |
| `registry/approved-accounts.json` | Registry **nyata** akun disetujui | **Tidak** (gitignore) |
| `registry/pending-accounts.json` | Catatan kandidat/menunggu | **Tidak** |
| `imports/`, `archive/` | Berkas impor sementara / arsip registry lama | **Tidak** |
| `issued/` | Berkas kredensial awal (sekali serah, lalu hapus) | **Tidak** |

> Untuk produksi, arahkan registry & issued ke lokasi di luar repositori dan di luar web root:
> `TRACE_REGISTRY_PATH=/secure/trace/registry`, `TRACE_ISSUED_PATH=/secure/trace/issued`
> dengan izin `0700` (direktori) / `0600` (berkas) milik pengguna layanan.

## Aturan keamanan

- Registry **tidak boleh** memuat kata sandi, hash, rahasia pemulihan, atau token sesi.
- Registry **bukan** basis data autentikasi. Database adalah sumber kebenaran akun aktif.
- Pangkat/jabatan **tidak pernah** otomatis memberi peran ADMINISTRATOR; setiap entri butuh `approved_by` dan `approved_at`.
- Hanya entri `approval_status: APPROVED` yang diimpor. Entri tidak valid membatalkan seluruh impor.

## Menambahkan akun baru (tanpa mengubah kode)

1. Salin entri dari `templates/account-template.json` ke `registry/approved-accounts.json` (di dalam array `accounts`).
2. Ubah isi: `trace_id` (format `TRS-XXXX-XXXX`, huruf besar/angka, unik), `full_name`, `organization`, `approved_role`, `approved_by`, `approved_at` (RFC 3339).
3. Validasi:
   ```
   php artisan trace:validate-registry
   ```
4. Simulasi (tanpa menulis ke DB):
   ```
   php artisan trace:provision-accounts --dry-run
   ```
5. Provisioning:
   ```
   php artisan trace:provision-accounts
   ```
   Kata sandi sementara **tidak** ditampilkan di terminal; ditulis ke `issued/<TRACE-ID>.credential.txt`.
6. Serahkan kredensial lewat kanal aman, lalu hapus berkas tersebut. Pengguna wajib mengganti kata sandi saat login pertama.

Menjalankan langkah 5 dua kali **tidak** membuat identitas ganda (idempoten per `trace_id`).

## Perintah

| Perintah | Fungsi |
|---|---|
| `trace:validate-registry` | Validasi skema + duplikat + persetujuan |
| `trace:provision-accounts [--dry-run] [--only=TRS-...]` | Impor akun disetujui |
| `trace:list-accounts [--status=ACTIVE]` | Daftar akun terprovisioning |
| `trace:suspend-account TRS-... --reason=` | Tangguhkan + cabut sesi |
| `trace:revoke-account TRS-... --reason=` | Cabut permanen |
| `trace:owner-create <id> "<nama>" [--generate]` | Buat identitas Developer/Owner |
| `trace:audit-verify` | Verifikasi rantai hash audit log |

Jalur UI (Developer/Owner Portal, `/platform`) memakai layanan yang sama dengan CLI.
