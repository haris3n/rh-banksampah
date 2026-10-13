# Portal petugas: tahap pratinjau

## Menjalankan di PowerShell

Dari folder `rh-banksampah`:

```powershell
$env:PETUGAS_PREVIEW = 'true'
php artisan serve --host=127.0.0.1 --port=8001
```

Buka http://127.0.0.1:8001/preview/petugas/dashboard . APP_ENV harus local.
Flag hanya berlaku pada proses terminal ini; dapat juga diatur lewat .env lokal.
Jika konfigurasi pernah dicache, jalankan `php artisan config:clear` di repo ini.

Tidak diperlukan migrate, database, npm, atau koneksi backend untuk preview.
Composer menyediakan Blade Heroicons melalui dependensi Filament 3.3.
Halaman preview adalah Blade terisolasi; panel/auth Filament operasional belum dihubungkan.

## Data resmi dan satuan (pembaruan)

Katalog berisi 14 harga beli resmi yang dikonfirmasi pemilik proyek. Kategori:
Plastik, Logam, Kertas, Minyak Jelantah, Kaca. Dua jenis minyak memakai liter
(bersih Rp4.000/liter, endapan Rp1.500/liter); lainnya kg, seluruhnya 2 desimal.
Tanggal efektif harga belum diketahui. Harga jual snapshot nullable, bukan nol.
Total estimasi jual/selisih disembunyikan jika ada snapshot harga jual kosong,
termasuk kondisi sebagian harga sudah terisi. Nilai beli tetap ditampilkan.
Total fisik dipisahkan kg dan liter pada semua ringkasan/struk, tidak dikonversi.
Mengganti jenis dengan satuan berbeda mengosongkan jumlah agar tidak dianggap konversi.

localStorage sekarang memakai `rh-petugas-preview-v2`; data v1 tidak digunakan
atau dikonversi karena ID katalog berbeda. Data transaksi tetap dummy.
Nama pencatat demo Haris; tidak ada email pribadi/password pada aset JavaScript.
Logo sumber resources/photos/petugas/image.png disajikan lewat route gambar tetap.
Kontak/alamat/Instagram mitra tampil di footer; struk memiliki identitas mitra.
Jadwal belum diumumkan. Perubahan ini hanya di repo petugas.

## Yang dapat dicoba

- Lima halaman sesuai referensi resources/photos/petugas, dengan navigasi responsif.
- Cari contoh Siti/Budi/Rina atau kode RH-DEMO-A7K2.
- Tambah jenis/berat, simpan, cek detail, konfirmasi verifikasi, lihat struk demo.
- Edit/batal setoran menunggu milik Petugas 1; alasan batal wajib.
- Setoran seed Petugas 2 hanya dapat dibaca.
- Filter tanggal/status/nama, rekap per jenis dengan rincian snapshot.
- Semua perubahan contoh disimpan di localStorage browser. Reset melalui sidebar.

## Batas tegas

Ini pratinjau interaktif, BUKAN implementasi otorisasi/keuangan produksi.
Pemeriksaan JavaScript dapat dimodifikasi pengguna; bukan bukti keamanan server.
Saldo hanya simulasi dan tidak tersambung ke nasabah asli. Login, scanner kamera,
XLSX, pagination server, audit, notifikasi dan PostgreSQL belum diimplementasikan.
Preview mengembalikan 404 jika flag tidak aktif atau environment bukan local/testing.

## Integrasi berikutnya

Migrasi hanya dari SetorIn-Backend, yang sengaja tidak diubah pada tahap ini.
Jangan menjalankan migrasi bawaan skeleton petugas pada database bersama.
Skrip Composer setup/post-create tidak lagi menjalankan migrate otomatis.

Setelah backend boleh dikerjakan: tetapkan skema bersama, role Spatie guard web,
panel Filament berautentikasi, layanan transaksi server dengan row lock dan mutasi,
serta ganti sumber fixture dengan data server. Jangan memindahkan keuangan ke
localStorage atau menggunakan halaman preview sebagai panel produksi.

Aturan tersimpan: hanya pencatat mengubah pending, selesai terkunci, snapshot
lama untuk edit berat, harga saat penambahan untuk item baru; rupiah integer dan
berat perseratus kg. Rekap berdasarkan verified_at WIB, bukan penjualan aktual.

## Bukti dan pengujian

`php artisan test --filter=PetugasPreviewTest`: akses opt-in, semua halaman tanpa
query database, unknown page 404, production 404 walau flag aktif.
`node --check public/js/petugas-preview.js`: pemeriksaan sintaks JavaScript.
`composer audit`: pemeriksaan dependensi.

### Pengujian browser tanpa paket tambahan

Tersedia `tests/Browser/preview-smoke.mjs`, menggunakan Node 22+ WebSocket dan
Chrome DevTools Protocol. Server preview harus berjalan pada port 8001.
Jalankan Chrome terpisah dengan profil pengujian (jangan profil pribadi):

```powershell
& 'C:\Program Files\Google\Chrome\Application\chrome.exe' --headless=new --disable-gpu --remote-debugging-address=127.0.0.1 --remote-debugging-port=9223 --user-data-dir="$env:TEMP\rh-preview-test" about:blank
```

Di terminal lain: `node tests/Browser/preview-smoke.mjs`.
Tes mereset localStorage preview pada profil browser pengujian. Mencakup simpan,
verifikasi, struk, edit berat, alasan pembatalan, transaksi petugas lain,
rekap snapshot, pencarian, dan overflow lima halaman pada viewport 390px.
Tutup Chrome pengujian setelah selesai. Jangan membuka port debug ke jaringan.

### Hasil verifikasi sesi awal

- PHP 8.2.12; Laravel 12; Filament 3.3.55; Composer platform check lulus.
- `php artisan test`: 5 tes lulus, 20 assertions (termasuk 2 contoh bawaan).
- Browser smoke: lulus seluruh skenario di atas, tanpa exception JavaScript.
- Pint pada PHP yang diubah, Composer validate dan git diff --check: lulus.
- Composer update dependency menghasilkan audit tanpa advisory.
- Screenshot desktop Setoran Baru 1440×1120 diperiksa terhadap referensi.
- Docker Engine aktif; PHP CLI lokal belum memiliki pdo_pgsql. Tidak ada
  container/database baru yang dibuat. Aktifkan driver nanti pada integrasi.

Halaman ini belum membuktikan konkurensi, otorisasi server, atau kesesuaian visual
pixel-perfect seluruh halaman. Tes browser menjalankan demo dengan identitas
Petugas 1 tetap, bukan autentikasi beberapa pengguna sebenarnya.

Uji manual: tambah setoran Siti dengan Emberan 1,25 kg (Rp2.000) dan Minyak
Jelantah Bersih 2,50 liter (Rp10.000), simpan, pastikan menunggu, verifikasi,
struk Rp12.000 dengan kg/liter terpisah, kembali ke transaksi lalu pastikan tidak ada
aksi verifikasi kedua. Buka seed Petugas 2, pastikan baca saja. Batalkan setoran
baru dengan alasan, pastikan tidak masuk rekap. Tes ini hanya membuktikan demo;
konkurensi PostgreSQL dan integritas saldo nyata harus diuji setelah integrasi.

Referensi visual: forest #143e2e, action #198653, lime #b7e76b, paper #f3f7f3,
ink #203e32. Segoe UI/system sans sementara (font Figma belum ditentukan).
Sidebar desktop 248px; form dan nasabah 2:1; kartu/tabel tetap mengikuti PNG.
