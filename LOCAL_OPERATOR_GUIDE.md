# Panduan Operator SIFI Lokal

SIFI pada tahap ini adalah aplikasi analitis lokal dan non-produksi. Semua
komponen aplikasi, data, log, konfigurasi, serta cadangan berada di dalam
folder `D:\Research\SIFI_GITHUB_CANDIDATE`.

## Menjalankan aplikasi

1. Buka PowerShell di folder `D:\Research\SIFI_GITHUB_CANDIDATE`.
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
yang sudah tersimpan. Sesi belum selesai muncul sebagai **Lanjutkan sesi** di
katalog dan kembali ke tahap berikutnya saat dipilih.

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
- ekspor brief: `runtime\exports`.

Saat sebuah brief dibuka, tombol **Ekspor Markdown ke folder SIFI** membuat
salinan yang dapat dibaca manusia di `runtime\exports`. Nama file diturunkan
dari hash ID brief sehingga ID yang mengandung karakter path tidak dapat
keluar dari folder ekspor.

Folder `runtime` dan `.venv` tidak masuk Git. Jangan memindahkan database atau
cadangan ke luar folder SIFI melalui alat aplikasi; batas path akan menolaknya.

## Batas tahap ini

Katalog dan penampil rekam hanya membaca data. Wizard latihan menulis sesi
sintetis lokal; mode CB22 menulis sesi studi berpseudonim melalui token sesi
dan pemeriksaan same-origin. SIFI belum mengklaim ingestion sumber langsung,
deployment produksi, validasi pengguna representatif, nilai keputusan
komparatif, CB22 PASS, atau kesiapan komersial.

## Studi pengguna representatif CB22

CB22 terpisah dari latihan sintetis biasa. Siapkan konfigurasi lokal yang
tertutup secara default dengan:

```powershell
.\scripts\prepare-cb22.ps1
.\scripts\status-cb22.ps1
```

Perintah pertama hanya membuat `runtime\config\cb22-execution-gate.json` di
dalam folder SIFI. Sesi counted tetap terkunci sampai dua kode staf berwenang
yang berbeda ditetapkan, pemeriksaan konflik selesai, dan kebutuhan review
eksternal/institusional dicatat sebagai terpenuhi atau tidak diwajibkan. Jangan
pernah memasukkan nama atau kontak peserta ke file ini atau dashboard.

Setelah dua peran nyata ditetapkan dan keputusan kebutuhan review sudah
didokumentasikan, gunakan `scripts\configure-cb22.ps1` dengan kode staf
pseudonim yang berbeda. Perintah menolak satu orang pada kedua peran dan tidak
akan membuka gerbang bila review konflik belum selesai.

Gunakan bagian `CB22` di dashboard untuk rehearsal sebelum melibatkan peserta
counted. Rehearsal tidak pernah memengaruhi hasil CB22. Sebelum sesi counted,
kustodian wajib menyelesaikan screening dan consent di luar database SIFI,
lalu hanya memasukkan kode pseudonim serta waktu konfirmasi consent.

Untuk smoke rehearsal otomatis yang selalu dikecualikan dari hasil, jalankan
`scripts\rehearse-cb22.ps1`. Perintah ini memakai kode `REHEARSAL-AUTOMATED`,
menyelesaikan task lokal, lalu memastikan asesmen CB22 tetap tanpa peserta.

Setiap tahap CB22 menampilkan paket bukti sintetis yang sama dan meminta
pilihan terstruktur. Jawaban yang melanggar boundary tidak memajukan workflow
dan percobaannya dicatat sebagai blocker berkode. Tidak ada catatan bebas yang
diperlukan dari peserta.

Materi beku berada di `05_VALIDATION\CB22_PREP`. Perubahan checksum task
package akan otomatis menutup gerbang sesi counted.

Setelah seluruh sesi selesai, ekspor bundle dari dashboard atau jalankan
`scripts\export-cb22.ps1`. Dua staf yang kode perannya sudah ditetapkan harus
meninjau bundle yang sama dan masing-masing menjalankan
`scripts\adjudicate-cb22.ps1`. Hanya dua vote bebas konflik yang final dapat
menghasilkan receipt; satu vote saja tidak pernah mengizinkan klaim PASS.
