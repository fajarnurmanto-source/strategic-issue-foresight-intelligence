# SIFI Source Center — Paket Pemasangan Terkendali (2026-11-09)

**Target:** Laravel SIFI lokal milik Fajar, bukan sistem produksi eksternal. Paket berisi 19 berkas source yang diverifikasi terhadap Git blob SHA-1 cabang PR #18, satu perintah pemeriksaan kesiapan, Google News RSS dan Google Trends RSS satu kali, migrasi pilihan sumber serta tes terfokus.

## Prasyarat

- Clone checkout cabang `feat/sifi-modular-source-inventory-20261109` dari `fajarnurmanto-source/strategic-issue-foresight-intelligence` melalui kredensial GitHub normal. Jangan menyalin token ke perintah.
- Tentukan **folder Laravel yang sebenarnya berjalan**, berisi `artisan`, `composer.json`, `package.json`, `routes/web.php`; jangan gunakan `D:\Research\SIFI` sebagai target umum.
- PHP 8.3+, ekstensi `dom`, `mbstring`, `openssl`, Composer `vendor/` dan dependensi `node_modules/` untuk Laravel tersebut sudah tersedia. Pastikan PHP/Node yang dipakai sama dengan runtime Laravel Anda.
- Tidak diperlukan API key Google untuk RSS publik. Tidak ada login Facebook/X/Instagram yang ditambahkan oleh paket ini.

## Eksekusi (PowerShell Windows)

Siapkan checkout baru pada folder sumber terpisah jika belum tersedia (perintah ini tidak mengubah runtime):

```powershell
git clone --branch feat/sifi-modular-source-inventory-20261109 https://github.com/fajarnurmanto-source/strategic-issue-foresight-intelligence.git D:\\Research\\SIFI\\SIFI_SOURCE_CENTER_INSTALL
```

Pada checkout sumber yang sudah berada di branch fitur:

```powershell
$Source = 'D:\Research\SIFI\SIFI_SOURCE_CENTER_INSTALL'
$Laravel = 'D:\Research\SIFI\ISI_FOLDER_LARAVEL_YANG_SEBENARNYA'
$Installer = Join-Path $Source 'tools\sifi-source-center\install-source-center.ps1'

# Langkah A: verifikasi 19 file, checksum, versi target dan konflik tanpa perubahan
powershell -NoProfile -ExecutionPolicy Bypass -File $Installer -SourceRepo $Source -TargetLaravel $Laravel

# Langkah B: sekali saja setelah preflight PASS; pasang, backup, tes, build, migrasi, dua permintaan Google
powershell -NoProfile -ExecutionPolicy Bypass -File $Installer -SourceRepo $Source -TargetLaravel $Laravel -Apply -ProbeGoogle
```

Script menghentikan pemasangan jika target bukan Laravel, cabang tidak cocok, source berubah, atau file lokal memiliki perubahan yang tidak sama dengan baseline. **Tidak ada opsi paksa menimpa perubahan pengguna**. Berikan pesan `TARGET_DRIFT_BLOCKED` untuk rekonsiliasi terlebih dahulu.

Pemasang menolak Laravel dengan environment `production`; gunakan instance/staging nonproduksi yang benar. Jika semua lulus, hasil harus memuat:

- `SIFI_SOURCE_PREFLIGHT=PASS`
- Tes Laravel terfokus, TypeScript typecheck, dan build UI berhasil (exit 0)
- `SIFI_SOURCE_INSTALL=PASS`
- `SIFI_SOURCE_LIVE_CHECK=PASS; EXTERNAL_REQUESTS=2` jika `-ProbeGoogle` dipilih

Folder backup bertanda waktu dibuat di sebelah target Laravel pada `SIFI_SOURCE_CENTER_BACKUPS`. Berkas source dipulihkan otomatis jika langkah sebelum migrasi gagal. **Setelah migrasi mulai**, script tidak melakukan rollback DB otomatis untuk mencegah kehilangan data; backup dipertahankan untuk pemulihan terkontrol.

## Hasil yang harus diuji melalui UI

1. Buka `/sifi/source-catalog`, pastikan 8 modul / 28 penyedia tampil, pilihan sumber dapat tersimpan, dan peringatan API/biaya terlihat. Pada Google Trends tekan tombol pengambilan RSS untuk melihat hingga 10 tren Indonesia.
2. Buka `/sifi/monitoring-objects`, pastikan objek monitoring memiliki keyword. Di `/sifi/discovery-period`, pilih objek dan tanggal terbaru, periksa cakupan sumber, centang persetujuan, lalu klik **Cari lewat Google News RSS**. Tautan metadata yang sesuai tanggal akan tampil bila feed menyediakan hasil.
3. Hasil nol pada tanggal historis lama **bukan bukti kegagalan koneksi**: Google News RSS tidak menjamin backfill. Jalur Google News RSS adalah feed publik dan bukan API Google Web Search umum.
4. Status kolektor historis E4 tetap dibatasi sesuai izin masing-masing. Tombol preview RSS baru bekerja terpisah dan tidak menghasilkan admitted evidence.

## Perintah diagnostik setelah instalasi

```powershell
Set-Location $Laravel
php artisan sifi:sources:doctor
php artisan sifi:sources:doctor --live
```

`doctor` tanpa `--live` **tidak mengakses Google**. Dengan `--live` dilakukan tepat dua permintaan feed publik, tanpa API key dan tanpa menyimpan hasil mentah; ia melaporkan kode kegagalan atau jumlah metadata.

## Batas dan dukungan

- Belum memasang OAuth platform sosial, login pihak ketiga, Google Web Search API, scheduler, pemrosesan full-text atau pengambilan massal. Pilihan sumber milik akun adalah **preferensi koneksi**, bukan izin mengambil seluruh posting.
- Folder `.env`, rahasia, basis data eksisting, provider token dan sumber luar daftar 19 file tidak disalin atau ditimpa oleh installer. Laravel tetap membaca konfigurasi lingkungan normalnya saat pengujian dan migrasi. Laravel sendiri memakai konfigurasi DB yang sudah tersedia untuk migrasi **satu** berkas yang membuat dua tabel sumber.
- Source-only PR #18 tetap **draft** sampai ada bukti hasil lokal: instalasi PASS, UI berjalan, dan paling tidak satu respons Google nyata melalui `--live`. Tidak ada bukti ini dari lingkungan GitHub saja.
