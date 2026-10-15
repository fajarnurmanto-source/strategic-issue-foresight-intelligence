# Panduan Operator SIFI Lokal

SIFI pada tahap ini adalah aplikasi analitis lokal dan non-produksi. Semua
komponen aplikasi, data, log, konfigurasi, serta cadangan berada di dalam
folder `D:\Research\SIFI`.

## Menjalankan aplikasi

1. Buka PowerShell di folder `D:\Research\SIFI`.
2. Jalankan `scripts\start-sifi.ps1`.
3. Browser akan membuka `http://127.0.0.1:8765`.
4. Saat selesai, jalankan `scripts\stop-sifi.ps1`.

Pada pemakaian pertama, launcher membuat lingkungan Python lokal, database
lokal, dan dua rekam demonstrasi sintetis. Proses ini tidak mengunduh data,
tidak mengirim data, dan aman dijalankan ulang.

Bagian **Latihan terpandu** pada halaman lokal memungkinkan operator menjalani
WF-01 sampai WF-11 satu per satu. Setiap konfirmasi menambah jejak audit ke
database lokal. Setelah WF-11, SIFI membuat brief sintetis yang dapat dibuka
dari katalog rekam. Menutup halaman di tengah latihan tidak menghapus jejak
yang sudah tersimpan, tetapi tahap ini belum menyediakan tombol melanjutkan
sesi latihan yang terputus.

Pilihan launcher:

- `scripts\start-sifi.ps1 -NoDemo` untuk tidak membuat data demo;
- `scripts\start-sifi.ps1 -NoBrowser` untuk tidak membuka browser otomatis;
- `scripts\start-sifi.ps1 -Port 9000` untuk memakai port loopback lain;
- `scripts\start-local.ps1` untuk menjalankan server di depan layar dan
  menghentikannya dengan `Ctrl+C`.

Launcher terkelola menyimpan identitas proses di `runtime\config` dan output
server di `runtime\logs`. Perintah stop memeriksa executable serta command line
sebelum menghentikan proses, sehingga PID yang telah dipakai ulang oleh proses
lain tidak dihentikan secara keliru.

## Pemeriksaan kesehatan

Jalankan `scripts\status-local.ps1`. Kondisi normal menampilkan:

- `database_exists: true`;
- `integrity: OK`;
- `local_only: true`.

Untuk memeriksa seluruh paket dan semua pengujian, jalankan
`scripts\verify-local.ps1`.

## Cadangan dan pemulihan

Jalankan `scripts\backup-local.ps1` untuk membuat cadangan SQLite yang
konsisten di `runtime\backups`. Untuk memulihkan, gunakan
`scripts\restore-local.ps1 -BackupPath <path-cadangan>`. Pemulihan selalu
membuat safety backup terlebih dahulu bila database aktif sudah ada.

## Lokasi data

- database: `runtime\data\sifi.sqlite`;
- cadangan: `runtime\backups`;
- konfigurasi lokal: `runtime\config`;
- log lokal: `runtime\logs`.

Folder `runtime` dan `.venv` tidak masuk Git. Jangan memindahkan database atau
cadangan ke luar folder SIFI melalui alat aplikasi; batas path akan menolaknya.

## Batas tahap ini

Katalog dan penampil rekam hanya membaca data; wizard latihan hanya menulis
sesi sintetis lokal melalui token sesi dan pemeriksaan same-origin. SIFI belum
mengklaim ingestion sumber langsung, deployment produksi, validasi pengguna
representatif, nilai keputusan komparatif, CB22 PASS, atau kesiapan komersial.
