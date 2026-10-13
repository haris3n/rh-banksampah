# Transaksi PostgreSQL nyata

## Koreksi navigasi dan pemisahan laporan

Dashboard dan Pengepul memakai partial sidebar yang sama dengan transaksi/form,
termasuk ikon, menu aktif, dan stylesheet operasional. Default laporan tetap hari
ini WIB (PRD P-07/P-08); rentang tanggal tersedia melalui details "Pilih rentang
tanggal". Periode aktif terlihat jelas dan berlaku pada angka/tabel/export terkait.
Top 5 Nasabah hanya di Dashboard. Pengepul hanya rekap dan export pengepul.
Export transaksi selesai dipindahkan ke halaman Transaksi dengan tanggal eksplisit.
Tanpa transaksi: "Belum ada setoran" untuk estimasi. Ada transaksi dengan snapshot
harga jual kosong: "Belum tersedia". Tidak ada harga jual/margin rekaan.
Verifikasi setelah koreksi: 7 tes /100 assertions lulus, termasuk navbar bersama,
Top 5 tidak ada di pengepul, periode hari ini, kondisi kosong dan regresi PostgreSQL.
git diff --check lulus. Screenshot browser perubahan ini belum diverifikasi.

## Pembaruan operasional

- Form dua kolom memakai katalog PostgreSQL, kalkulasi integer perseratus kg/liter,
  subtotal live, detail nasabah, pencarian AJAX tanpa menghapus isian. Harga edit
  berasal snapshot, termasuk jenis yang dinonaktifkan setelah pencatatan.
- /petugas/nasabah: pencarian, profil, saldo tersedia/ditahan, riwayat paginasi,
  dan pintasan ke Setoran Baru. /petugas menyediakan filter nama/kode/HP/status/
  periode WIB (selesai: waktu verifikasi; lainnya: waktu pencatatan).
- Scanner html5-qrcode lokal: `npm ci` menyalin aset scanner lewat postinstall.
  Mulai/Hentikan kamera pada Setoran Baru; localhost atau HTTPS wajib. Izin
  ditolak/tidak ada kamera tetap menyediakan input QR manual. Kamera fisik belum
  diuji oleh agen; coba QR UUID nasabah lokal dari perangkat lain.
- Audit pembatalan satu transaksi dengan perubahan status: jalankan migrasi
  tambahan backend sebelum memakai versi ini (sudah dijalankan pada mesin ini).
- `php artisan petugas:demo-nasabah`: local-only, tiga nasabah example.test tanpa
  password, QR UUID permanen dan saldo awal nol. Aman dijalankan ulang. Data ini
  sudah dibuat pada lingkungan lokal. Bukan pendaftaran nasabah oleh petugas.
- `php tests/concurrent-setoran.php`: dua worker PHP memakai PostgreSQL nyata,
  memeriksa satu setoran tidak dikreditkan ganda dan dua setoran ke saldo sama
  tidak kehilangan penambahan. Fixture sementara dibersihkan setelah tes.
- Verifikasi: PHPUnit 6 tes/78 assertions, tes concurrency, build Vite, syntax JS
  dan git diff --check lulus. npm audit 0 vulnerabilities setelah override
  shell-quote ^1.12.0 untuk dependensi development concurrently.

Masih perlu: pemeriksaan visual browser operasional menyeluruh dan webcam nyata,
penyatuan seluruh navigasi/halaman ke Filament, cakupan export semua status,
pengujian rollback kegagalan di tengah verifikasi. Jangan klaim F1 lengkap.

Jalankan Docker/migrasi/katalog sesuai docs/proyek3-database.md di backend.
Dari PowerShell di rh-banksampah:

```powershell
$env:SESSION_DRIVER='file'
$env:CACHE_STORE='file'
php -d extension=pdo_pgsql artisan petugas:create haris3n@gmail.com Haris
php -d extension=pdo_pgsql artisan serve --host=127.0.0.1 --port=8002
```

Password diminta tersembunyi, tidak ada password bawaan yang dicommit. Pengaturan
session/cache dapat disimpan di .env repo petugas. Jika config pernah dicache,
jalankan artisan config:clear. Buka http://127.0.0.1:8002/petugas/login.

UI /petugas adalah alur server nyata, berbeda dari /preview/petugas yang masih
demo localStorage. Login memeriksa aktif dan relasi role Spatie-compatible web.
Middleware mengulangi pemeriksaan pada setiap request. Form POST/PUT memakai
CSRF, validasi jumlah 2 desimal, distinct jenis; server menentukan harga/pencatat.
Simpan tidak menambah saldo. Verifikasi mengunci header lalu baris saldo dalam
satu transaksi, membuat mutasi dan notifikasi; verifikasi ulang ditolak 409.
FK dan referensi mutasi unik tetap melindungi histori. Read semua petugas, edit/
batal/verifikasi hanya pencatat. Struk memakai snapshot dan WIB.

Tes nyata (seluruh fixture dibungkus transaksi lalu rollback):
`php -d extension=pdo_pgsql vendor/bin/phpunit --filter=SetoranDatabaseTest`

Pengujian mencakup login, render form/struk, kg+liter Rp12.000, petugas lain 403,
snapshot setelah perubahan master, verifikasi ulang 409, saldo dan mutasi tepat
sekali. Ini belum bukti dua proses verifikasi bersamaan; perlu uji konkurensi
sebelum uji lapangan. Model User kedua aplikasi harus memakai morph App\Models\User
dan koneksi proyek3 ketika integrasi admin dilakukan (admin lama belum dipindah).

Dashboard/rekap PostgreSQL tersedia di /petugas/dashboard dan /petugas/pengepul.
Filter tanggal dihitung dari batas tengah malam WIB, interval akhir eksklusif.
Semua agregat hanya dari setoran SELESAI. Kg/liter dipisah. Harga jual kosong pada
salah satu detail membuat estimasi kelompok/total belum tersedia; tidak menjadi nol.
XLSX tersedia pada /petugas/export?kind=transaksi atau kind=pengepul dengan from/to.
Export transaksi ini khusus transaksi selesai sesuai periode verifikasi, bukan semua
status. Angka ditulis sebagai cell numeric memakai OpenSpout yang sudah terpasang.
Tes memeriksa ZIP/worksheet XLSX, nilai, dan pergantian tanggal WIB.

Belum selesai: scanner webcam, audit pembatalan,
panel/resource Filament, serta admin pengelolaan akun. UI server memakai Blade
dengan stylesheet referensi sebagai tahap penghubung; jangan menyebutnya F1 lengkap.
Tidak ada nasabah produksi yang dibuat: nasabah akan berasal dari registrasi API;
tes memakai nasabah fixture dengan role dan rollback otomatis.
