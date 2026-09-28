=====================================================================
PRODUCT REQUIREMENTS DOCUMENT (PRD)
SETOR.IN - PROYEK 3
Aplikasi Mobile Nasabah, Web Petugas, dan Web Admin
Mitra: Rumah Hijau
=====================================================================

Versi       : 1.0 (draft final untuk disetujui tim)
Tanggal     : 24 September 2026
Tim         : Haris, Chizbu, Dijah, Eca
Target      : Web petugas siap uji lapangan Oktober 2026;
              seluruh proyek selesai Desember 2026
Repositori  :
  - Mobile nasabah       : github.com/chizbu/setor_in (Flutter, repo lama)
  - Backend + web admin  : github.com/haris3n/setorin_backend (Laravel/Filament, repo lama)
  - Web petugas          : repo baru (Laravel/Filament)

Keterangan penanda fase pada dokumen ini: [F0] [F1] [F2] [F3]
(lihat Bagian 15).


=====================================================================
1. RINGKASAN & LATAR BELAKANG
=====================================================================

Setor.in adalah sistem bank sampah digital. Pada Proyek 2 aplikasi
bersifat umum (banyak bank sampah, ada koin dan misi). Pada Proyek 3
aplikasi dipersempit untuk satu mitra, yaitu Rumah Hijau.

Alur bisnis Rumah Hijau:
1. Nasabah menyetor sampah yang sudah bersih dan terpilah.
2. Petugas menimbang dan menilai sampah dengan harga beli per jenis
   per kg. Nilainya menjadi saldo rupiah nasabah.
3. Rumah Hijau menjual sampah ke pengepul dengan harga sedikit lebih
   tinggi. Contoh: beli botol Rp1.800/kg dari nasabah, jual ke
   pengepul Rp2.000/kg. Selisihnya menjadi pendapatan Rumah Hijau.

Kebutuhan mitra dan kebutuhan akademik:
- Kebutuhan riil Rumah Hijau hanya WEB PETUGAS (pencatatan setoran).
- Aplikasi mobile nasabah dan web admin dibuat untuk memenuhi tugas
  Proyek 3. Syarat kampus: mobile wajib memiliki chatbot AI, dan
  harus ada landing page (landing page sudah dibuat dan berada di
  luar lingkup dokumen ini).
- Rumah Hijau buka setor sampah sebulan sekali, sehingga web petugas
  dites di lapangan sekitar satu kali per bulan.


=====================================================================
2. TUJUAN & RUANG LINGKUP
=====================================================================

2.1 Tujuan
- Petugas Rumah Hijau dapat mencatat setoran nasabah dengan cepat dan
  akurat (scan QR, input jenis dan berat, verifikasi, struk).
- Nasabah dapat melihat saldo, riwayat, harga sampah, jadwal setor,
  serta menukar saldo menjadi uang.
- Admin dapat mengatur harga, akun petugas, persetujuan tukar saldo,
  dan konten edukasi.
- Mobile memiliki chatbot AI sebagai asisten smart waste management
  sekaligus customer support.

2.2 Dalam lingkup
- Mobile nasabah (Android, Bahasa Indonesia).
- Web petugas (repo baru).
- Web admin dan backend/API (repo lama, khusus admin).
- Integrasi: Gemini API (chatbot), Firebase (FCM dan Google Sign-In),
  email SMTP (OTP), Google Maps (tombol petunjuk arah).
- Tukar saldo berupa simulasi internal; Midtrans Payouts (Iris)
  sandbox bersifat opsional.

2.3 Di luar lingkup
- Landing page (sudah jadi; hanya perlu tautan unduh APK).
- iOS dan bahasa selain Indonesia.
- Pencairan uang sungguhan.
- Banyak bank sampah, koin, misi, reward.
- Pendaftaran nasabah oleh petugas (nasabah wajib daftar sendiri).
- Distribusi lewat Play Store (APK dibagikan lewat tautan).


=====================================================================
3. PERAN PENGGUNA & HAK AKSES
=====================================================================

NASABAH (mobile)
- Daftar dan login sendiri; melihat data milik sendiri saja.
- Melihat saldo, riwayat, harga, edukasi, FAQ, jadwal setor.
- Mengajukan tukar saldo; memakai chatbot.

PETUGAS (web petugas)
- Akun dibuat admin; boleh lebih dari satu petugas.
- Mencatat dan memverifikasi setoran, mencari nasabah, melihat
  dashboard dan data pengepul, mengekspor data, mengatur jadwal setor.
- Tidak dapat mengubah harga maupun saldo secara langsung.

ADMIN (web admin)
- Mengelola nasabah, petugas, jenis sampah dan harga, persetujuan
  tukar saldo, dan edukasi.
- Melihat semua setoran dan data pengepul (baca saja).
- Tidak membuat atau mengubah setoran.


=====================================================================
4. ARSITEKTUR & STACK
=====================================================================

+ Mobile        : Flutter, Android saja (min. Android 8.0 / API 26).
+ Backend/API   : Laravel di repo setorin_backend; menyediakan REST API
                  untuk mobile dan panel web admin (Filament).
+ Web petugas   : Laravel + Filament di repo BARU.
+ Database      : SATU database bersama untuk backend admin dan web
                  petugas.
+ Chatbot       : Flutter -> endpoint Laravel -> Gemini API
                  (model gemini-3.1-flash-lite, tier gratis).
                  Jangan memakai model seri Gemini 2.5 (dijadwalkan
                  dihentikan 16 Oktober 2026 menurut dokumentasi
                  Google; verifikasi ulang sebelum implementasi).
+ Notifikasi    : in-app (tabel notifikasi) + FCM.
+ Login Google  : google_sign_in (Flutter); backend memverifikasi ID
                  token. Firebase project yang sama dengan FCM.
+ Tukar saldo   : lapisan PayoutService dengan dua implementasi:
                  (1) simulasi internal, (2) Midtrans Payouts (Iris)
                  sandbox, opsional.
+ OTP           : dikirim lewat email (layanan SMTP ditentukan tim).
+ Deploy        : VPS yang sudah ada (Docker); web petugas sebagai
                  container/subdomain terpisah.

Aturan dua repo Laravel dengan satu database:
1. Migrasi HANYA ada di repo backend admin. Repo petugas tidak
   menjalankan migrate; hanya membaca skema yang sama.
2. Nama guard Spatie Permission harus identik di kedua repo
   (mis. "web"), karena role tersimpan per guard.
3. Logika verifikasi setoran (hitung total, snapshot harga, tambah
   saldo) ditulis di satu tempat dan disalin persis ke repo lain
   bila diperlukan.
4. Setiap perubahan saldo memakai DB::transaction dengan
   lockForUpdate() pada baris saldo nasabah untuk mencegah race
   condition.

Struktur navigasi mobile (usulan; final ditentukan saat desain UI):
Beranda | Harga | QR (tengah) | Edukasi | Profil. Chatbot lewat tombol
melayang; notifikasi lewat ikon di bagian atas; FAQ dan edit akun
di Profil.


=====================================================================
5. PERUBAHAN DARI PROYEK 2
=====================================================================

DIHAPUS
- Koin, misi, reward, harga koin (fitur dan tabel).
- Konsep banyak bank sampah (tabel dan relasi bank sampah).
- Panel petugas dari repo backend (dipindah ke repo baru).
- Fitur admin yang berkaitan dengan petugas.

DIUBAH
- Bank sampah menjadi satu lokasi tetap: Rumah Hijau.
- Saldo berupa rupiah dari berat x harga beli per jenis sampah.
- Desain dan tata letak mobile dirombak total; memakai logo
  Rumah Hijau.
- Web admin menyesuaikan satu bank sampah dan fitur petugas baru.

DITAMBAH
- Chatbot AI, halaman harga sampah, FAQ, lokasi dan jadwal setor,
  FCM, login Google, riwayat dan notifikasi lengkap.
- Web petugas versi baru (setoran, struk, pengepul, export).


=====================================================================
6. ATURAN BISNIS
=====================================================================

BR-01  Harga ditetapkan admin saja. Setiap jenis sampah punya harga
       beli (dibayar ke nasabah) dan harga jual (ke pengepul) per kg.
BR-02  Saat setoran disimpan, harga beli dan harga jual disalin
       (snapshot) ke detail setoran. Perubahan harga kemudian tidak
       memengaruhi transaksi lama.
BR-03  Berat dalam kg dengan 2 desimal (0,01 kg), bukan integer.
       Subtotal = berat x harga beli, dibulatkan ke rupiah terdekat.
       Total setoran = jumlah subtotal.
BR-04  Saldo nasabah bertambah hanya saat setoran diverifikasi.
BR-05  Status setoran: MENUNGGU_VERIFIKASI -> SELESAI atau DIBATALKAN.
       Edit dan pembatalan hanya saat MENUNGGU_VERIFIKASI (pembatalan
       wajib alasan). Setoran SELESAI terkunci.
BR-06  Konfirmasi dua tahap: (1) petugas menyimpan setoran,
       (2) petugas menekan verifikasi. Nasabah tidak perlu
       konfirmasi di aplikasi.
BR-07  Saldo terdiri dari saldo tersedia dan saldo ditahan.
BR-08  Tukar saldo: minimal Rp10.000 dan tidak melebihi saldo
       tersedia. Saat diajukan, nominal dipindah ke saldo ditahan.
       Ditolak atau gagal -> nominal kembali ke saldo tersedia.
       Berhasil -> saldo ditahan berkurang.
BR-09  Satu nasabah hanya boleh punya satu permintaan tukar saldo
       aktif (MENUNGGU atau DIPROSES). Tidak ada biaya/potongan.
BR-10  Tukar saldo memerlukan PIN 6 digit. PIN dibuat saat pertama
       kali nasabah menukar saldo.
BR-11  Tujuan pencairan: bank atau e-wallet. Diisi setiap pengajuan
       (form terisi otomatis dari tujuan terakhir yang disimpan),
       dengan opsi "simpan untuk berikutnya".
BR-12  Persetujuan admin wajib. Penolakan wajib disertai alasan.
       Satu permintaan tidak boleh diproses dua kali.
BR-13  Rekap pengepul dihitung otomatis dari setoran berstatus SELESAI
       pada periode terpilih. Nilai jual = jumlah (berat x harga jual
       snapshot). Estimasi selisih = nilai jual - nilai beli.
BR-14  Status buka/tutup Rumah Hijau dihitung server (WIB) dari jadwal
       setor yang diisi petugas, dengan override buka/tutup darurat.
BR-15  Akun nonaktif tidak dapat login.
BR-16  QR nasabah berisi kode unik acak (bukan ID berurutan), dibuat
       otomatis saat akun terbentuk, dan tidak berubah.
BR-17  Akun Google: OTP dilewati (email sudah terverifikasi Google);
       no. HP dan alamat wajib dilengkapi setelah login pertama.
BR-18  Chatbot hanya berpengetahuan umum (sampah, lingkungan, cara
       memakai Setor.in). Chatbot tidak mengakses saldo/riwayat dan
       tidak menerima data pribadi.
BR-19  Format tampilan: Rupiah "Rp1.800", berat "1,25 kg",
       tanggal "24 Sep 2026 14:05 WIB" (zona Asia/Jakarta).


=====================================================================
7. KEBUTUHAN FUNGSIONAL - MOBILE NASABAH
=====================================================================

M-01 Registrasi [F1]
- Field: nama, no. HP, alamat, email, password (min. 8 karakter) dan
  konfirmasi password.
- Sistem mengirim OTP ke email; akun aktif setelah OTP benar.
- QR nasabah dibuat otomatis saat akun aktif.

M-02 Login dan sesi [F1]
- Login email + password; sesi memakai token (Sanctum); logout.

M-03 Lupa password [F1]
- Minta OTP ke email, lalu set password baru.

M-04 Daftar/login dengan akun Google [F2]
- Alur mengikuti BR-17. Jika email sudah terdaftar, akun ditautkan
  ke Google setelah token valid.

M-05 Beranda dan tabungan [F1]
- Menampilkan saldo tersedia (dan saldo ditahan jika > 0), pintasan
  QR, kartu status Rumah Hijau, setoran terakhir, akses notifikasi
  dan chatbot.

M-06 QR dan panduan setor [F1]
- Layar QR besar dengan penjelasan: (1) bawa sampah bersih dan
  terpilah, (2) tunjukkan QR ke petugas, (3) petugas menimbang dan
  memverifikasi, (4) saldo masuk.

M-07 Harga sampah [F1]
- Daftar jenis sampah aktif: nama, kategori, harga beli per kg,
  waktu pembaruan terakhir. Hanya baca.

M-08 Riwayat transaksi [F1 setoran; F2 lengkap]
- F1: daftar setoran dan detail (ID, tanggal-jam, jenis, berat,
  harga/kg, subtotal, total, status).
- F2: digabung dengan riwayat tukar saldo (nominal, tujuan, status,
  alasan penolakan), filter rentang tanggal.

M-09 Tukar saldo [F2]
- Form: nominal (min. Rp10.000), jenis tujuan (bank/e-wallet),
  nama bank/e-wallet, nomor rekening/no. e-wallet, nama pemilik,
  opsi "simpan untuk berikutnya".
- Ringkasan konfirmasi -> input PIN -> pengajuan terkirim, saldo
  ditahan sesuai BR-08.
- Status: MENUNGGU, DIPROSES, BERHASIL, DITOLAK, GAGAL.

M-10 PIN [F2]
- Buat PIN 6 digit saat pertama kali tukar saldo; ganti PIN; lupa
  PIN lewat OTP email. Setelah 5 kali salah, PIN terkunci 15 menit.

M-11 Notifikasi [F2]
- Inbox di aplikasi dengan penanda sudah/belum dibaca dan badge.
- Push notification FCM untuk peristiwa yang sama.
- Pemicu: setoran berhasil, tukar saldo disetujui/berhasil,
  tukar saldo ditolak (memuat alasan), edukasi baru.
- Ketuk notifikasi membuka halaman terkait.
- Android 13+ meminta izin notifikasi saat runtime.

M-12 Edukasi [F2]
- Daftar artikel terbaru dengan filter kategori; detail berisi gambar
  sampul, isi, dan tautan video opsional.
- Topik: cara memilah/mengolah sampah, lingkungan sehat, bahaya
  membuang sampah sembarangan.

M-13 FAQ [F2]
- Daftar tanya-jawab statis di dalam aplikasi (akordeon).

M-14 Lokasi dan jadwal setor [F2]
- Kartu Rumah Hijau: nama, alamat, status BUKA/TUTUP, jadwal
  terdekat (tanggal, jam buka-tutup, catatan).
- Status diperbarui saat halaman dibuka dan berkala (mis. tiap
  60 detik saat halaman aktif).
- Tombol "Petunjuk arah" membuka Google Maps.
- Tombol WhatsApp Rumah Hijau tampil hanya jika nomor tersedia.

M-15 Chatbot AI [F2]
- Layar percakapan; input maks. 500 karakter.
- Backend memakai system prompt yang membatasi topik (sampah,
  lingkungan, cara pakai Setor.in) dan menyisipkan isi FAQ sebagai
  konteks. Pertanyaan di luar topik ditolak dengan sopan.
- Jika tidak bisa menjawab, arahkan ke FAQ atau kontak Rumah Hijau.
- Rate limit: 10 pesan per menit per pengguna. Saat kena limit atau
  error, tampilkan pesan yang jelas.
- Riwayat percakapan disimpan lokal di perangkat; backend mengirim
  maks. 10 pesan terakhir sebagai konteks.
- Tampilkan keterangan bahwa jawaban bersifat umum.

M-16 Edit akun [F2]
- Ubah nama, no. HP, alamat; ganti password (tidak untuk akun
  Google); ganti PIN. Email tidak dapat diubah.

M-17 Identitas visual [F1-F2]
- Logo Rumah Hijau dan desain baru (splash, login, header).
  Desain detail disusun terpisah.


=====================================================================
8. KEBUTUHAN FUNGSIONAL - WEB PETUGAS
=====================================================================

P-01 Login petugas [F1]
- Hanya role petugas yang aktif. Akun dibuat admin.

P-02 Setoran Baru [F1]
- Cari nasabah lewat scan QR, atau nama/no. HP/kode manual.
- Tampilkan identitas nasabah (nama, no. HP, alamat) untuk
  memastikan orangnya.
- Tambah item: jenis sampah (hanya yang aktif), berat kg (desimal).
  Harga beli terisi otomatis; subtotal dan total terhitung otomatis.
  Satu setoran boleh berisi beberapa jenis sampah.
- Simpan -> status MENUNGGU_VERIFIKASI (tahap 1).
- Selama menunggu: item dapat diedit, atau setoran dibatalkan dengan
  alasan wajib.

P-03 Scan QR nasabah [F1]
- Berada di halaman Setoran Baru (sidebar). Memakai webcam lewat
  html5-qrcode; tersedia input kode manual sebagai cadangan.

P-04 Verifikasi dan struk [F1]
- Verifikasi (tahap 2) dengan dialog konfirmasi. Dalam satu transaksi
  DB: status -> SELESAI, saldo nasabah bertambah, mutasi saldo
  tercatat, notifikasi ke nasabah dibuat.
- Struk langsung tampil di layar: ID transaksi, nama nasabah,
  no. HP, alamat, rincian tipe sampah (berat, harga/kg, subtotal),
  total setor sampah, total nilai, tanggal dan jam transaksi.

P-05 Transaksi hari ini dan cek transaksi [F1]
- Tabel: ID, nama nasabah, no. HP, alamat, tipe sampah, berat (kg),
  nilai tukar (Rp), status. Filter tanggal dan pencarian.
- Tombol "Cek Transaksi" menampilkan semua hasil setoran nasabah
  tersebut.

P-06 Cari nasabah [F1]
- Cari berdasarkan nama, no. HP, atau kode QR; hasil membuka detail
  nasabah beserta riwayat setorannya.

P-07 Dashboard [F1]
- Filter rentang tanggal (default hari ini).
- Menampilkan: jumlah transaksi, total berat (kg), hasil pembelian
  dari nasabah (Rp), estimasi penjualan ke pengepul (Rp),
  estimasi selisih, dan daftar top nasabah setor terbanyak (berat).

P-08 Data untuk pengepul [F1]
- Rekap otomatis dari setoran SELESAI (BR-13), filter rentang tanggal
  (default hari ini). Per jenis sampah:
  total berat diterima, harga beli nasabah, harga jual pengepul,
  total nilai jual, estimasi selisih.
- Ringkasan stok (opsional): total berat per jenis pada periode.
- Tidak ada pencatatan penjualan atau pengurangan stok.

P-09 Export XLSX [F1]
- Tersedia di Setoran Baru/Transaksi dan Data Pengepul.
- Format XLSX, filter rentang tanggal (default hari ini). Berat dan
  rupiah disimpan sebagai angka, bukan teks.
- Nama file contoh: setorin-transaksi-20261011-20261011.xlsx.

P-10 Jadwal setor [F2]
- Petugas mengisi tanggal, jam buka, jam tutup, catatan; tombol
  buka/tutup darurat. Jadwal terdekat tampil di mobile (M-14).


=====================================================================
9. KEBUTUHAN FUNGSIONAL - WEB ADMIN
=====================================================================

A-01 Login admin [F1]
- Hanya role admin.

A-02 Pembersihan fitur lama [F0-F1]
- Hapus fitur petugas, bank sampah, misi, reward, harga koin.
  Rapikan skema (lihat Bagian 11).

A-03 Kelola nasabah [F1]
- Daftar, cari, detail (profil, saldo, riwayat), aktifkan/nonaktifkan.
  Saldo tidak dapat diubah manual.

A-04 Kelola petugas [F1]
- Buat, ubah, nonaktifkan, reset password. Boleh banyak petugas.

A-05 Jenis sampah dan harga [F1]
- CRUD: nama, kategori, harga beli/kg, harga jual/kg, status aktif.
- Data awal (seeder) dari daftar jenis sampah Rumah Hijau.
- Log riwayat perubahan harga (siapa, kapan, nilai lama dan baru).
- Peringatan jika harga jual lebih rendah dari harga beli.

A-06 Setoran dan data pengepul (baca saja) [F1]
- Lihat semua setoran dan rekap pengepul dengan filter.

A-07 Persetujuan tukar saldo [F2]
- Daftar permintaan dengan filter status. Detail: nasabah, nominal,
  tujuan, saldo.
- Aksi Setujui atau Tolak (alasan wajib) sesuai BR-12.
- Mode simulasi: setelah disetujui, status langsung BERHASIL dan
  saldo ditahan dilepas.
- Mode Payouts (opsional): membuat payout di Midtrans sandbox,
  status DIPROSES, lalu diperbarui lewat callback menjadi
  BERHASIL atau GAGAL.
- Notifikasi ke nasabah pada setiap hasil.

A-08 Edukasi [F2]
- CRUD: judul, kategori, gambar sampul, isi (rich text), tautan
  video opsional, status draft/terbit.
- Saat pertama kali terbit, kirim notifikasi "edukasi baru" ke
  nasabah (in-app + FCM).

A-09 Dashboard admin [F2]
- Total nasabah, total kg setoran bulan ini, total nilai setoran
  bulan ini, total saldo nasabah (tersedia + ditahan), jumlah
  permintaan tukar saldo yang menunggu.

A-10 Audit log [F2]
- Mencatat: perubahan harga, persetujuan/penolakan tukar saldo,
  pembatalan setoran, perubahan akun petugas dan nasabah.
  Admin dapat melihat (baca saja).

A-11 Desain [F2]
- Tampilan disesuaikan dengan identitas Setor.in dan logo
  Rumah Hijau.


=====================================================================
10. ALUR UTAMA
=====================================================================

10.1 Registrasi nasabah
Isi form -> OTP email -> akun aktif -> QR dibuat -> masuk Beranda.
Jalur Google: pilih akun Google -> lengkapi no. HP dan alamat ->
QR dibuat -> Beranda.

10.2 Setor sampah (di Rumah Hijau)
Nasabah menunjukkan QR -> petugas scan (atau cari manual) ->
identitas tampil -> petugas menimbang, input jenis dan berat ->
simpan (MENUNGGU_VERIFIKASI) -> verifikasi -> status SELESAI, saldo
bertambah, struk tampil, nasabah menerima notifikasi.

10.3 Tukar saldo
Nasabah mengisi form -> PIN -> saldo ditahan, status MENUNGGU ->
admin menyetujui -> (simulasi) BERHASIL, atau (Payouts)
DIPROSES -> BERHASIL/GAGAL -> notifikasi ke nasabah.
Jika ditolak: alasan dikirim ke nasabah, saldo kembali.

10.4 Jadwal setor
Petugas mengisi jadwal -> mobile menghitung dan menampilkan status
BUKA/TUTUP serta jadwal terdekat -> nasabah dapat membuka petunjuk
arah ke Rumah Hijau.

10.5 Rekap ke pengepul
Petugas membuka Data Pengepul -> memilih periode -> melihat total
berat, nilai jual, dan estimasi selisih -> export XLSX bila perlu.


=====================================================================
11. MODEL DATA (RINGKAS)
=====================================================================

Nama tabel final mengikuti skema hasil pembersihan di Fase 0.
Tabel proyek 2 yang dihapus: koin, misi (dan progres), reward,
harga_coin, bank_sampah (dan relasinya).

- users: id, nama, email (unik), no_hp, alamat, password (nullable
  untuk akun Google), google_id (nullable), email_verified_at,
  status (aktif/nonaktif), kode_qr (unik, khusus nasabah), pin_hash,
  pin_gagal, pin_terkunci_sampai. Role lewat Spatie Permission.
- saldo_nasabah: user_id, tersedia, ditahan (rupiah, integer).
- mutasi_saldo: id, user_id, tipe (SETORAN, TUKAR_TAHAN,
  TUKAR_BERHASIL, TUKAR_KEMBALI), jumlah, saldo_setelah, referensi
  (tipe + id), waktu.
- otp_codes: id, user_id/email, tujuan (REGISTRASI, LUPA_PASSWORD,
  LUPA_PIN), kode_hash, kedaluwarsa, percobaan, digunakan_at.
- jenis_sampah: id, nama, kategori, harga_beli, harga_jual, aktif.
- riwayat_harga: id, jenis_sampah_id, harga lama/baru (beli dan
  jual), diubah_oleh, waktu.
- setoran: id, kode, nasabah_id, petugas_id, status, total_berat,
  total_nilai, alasan_batal, diverifikasi_at, dibatalkan_at,
  created_at.
- setoran_detail: id, setoran_id, jenis_sampah_id, nama_jenis
  (snapshot), berat (2 desimal), harga_beli_snapshot,
  harga_jual_snapshot, subtotal.
- tukar_saldo: id, kode, nasabah_id, nominal, jenis_tujuan
  (BANK/EWALLET), nama_tujuan, nomor_tujuan (terenkripsi),
  nama_pemilik, status, alasan_penolakan, diproses_oleh,
  diproses_at, payout_ref (nullable), created_at.
- tujuan_tersimpan: user_id, jenis, nama, nomor (terenkripsi),
  pemilik.
- notifikasi: id, user_id, tipe, judul, isi, data (json),
  dibaca_at, created_at.
- perangkat: id, user_id, fcm_token, platform, last_seen.
- edukasi: id, judul, kategori, gambar_sampul, isi, video_url,
  status, terbit_at, penulis_id.
- jadwal_setor: id, tanggal, jam_buka, jam_tutup, catatan, aktif,
  ditutup_darurat, dibuat_oleh.
- audit_log: id, user_id, aksi, entitas, entitas_id, data_lama,
  data_baru, ip, waktu.


=====================================================================
12. API MOBILE (USULAN KONTRAK, PREFIKS /api/v1)
=====================================================================

Auth
  POST /auth/register, /auth/verify-otp, /auth/resend-otp
  POST /auth/login, /auth/google, /auth/complete-profile
  POST /auth/forgot-password, /auth/reset-password, /auth/logout
Akun
  GET /me            PUT /me            PUT /me/password
  POST /me/pin       PUT /me/pin        POST /me/pin/reset
  POST /me/device-token
Saldo, harga, riwayat
  GET /saldo         GET /jenis-sampah
  GET /riwayat       GET /setoran/{id}  GET /tukar-saldo/{id}
Tukar saldo
  POST /tukar-saldo  GET /tukar-saldo   GET /tujuan-tersimpan
Notifikasi
  GET /notifikasi    POST /notifikasi/{id}/dibaca
  POST /notifikasi/dibaca-semua
Konten dan lokasi
  GET /edukasi       GET /edukasi/{id}
  GET /lokasi        GET /jadwal-setor/terdekat
Chatbot
  POST /chat

Web admin dan web petugas berbasis Filament (server-rendered) dan
tidak memakai API di atas.

Aturan API: respons JSON seragam; OTP tidak boleh muncul di respons
atau log; setiap endpoint memeriksa kepemilikan data (nasabah hanya
dapat mengakses data sendiri); dokumentasikan lewat koleksi Postman
sebelum Fase 1 dimulai agar mobile dan backend dapat paralel.


=====================================================================
13. INTEGRASI EKSTERNAL
=====================================================================

- Gemini API : chatbot; API key hanya di server (.env); ada rate
  limit dan pesan fallback saat limit gratis tercapai. Karena tier
  gratis dapat memakai prompt untuk perbaikan produk, jangan kirim
  data pribadi nasabah.
- Firebase   : FCM (push) dan Google Sign-In (Android memerlukan SHA-1
  fingerprint aplikasi). Laravel mengirim push lewat FCM HTTP v1.
- SMTP       : pengiriman OTP.
- Google Maps: hanya tombol petunjuk arah (intent/url_launcher), tanpa
  Maps SDK atau billing.
- Midtrans Payouts (Iris): opsional, sandbox; dipakai bila waktu
  cukup. Pencairan uang sungguhan di luar lingkup.


=====================================================================
14. KEBUTUHAN NON-FUNGSIONAL
=====================================================================

Keamanan
- HTTPS di semua layanan. Password dan PIN di-hash (bcrypt).
- OTP 6 digit, disimpan ter-hash, berlaku 5 menit, maks. 5 percobaan,
  jeda kirim ulang 60 detik.
- Rate limit pada login, OTP, PIN, dan chatbot.
- Nomor rekening/e-wallet disimpan terenkripsi.
- Otorisasi per role dan per kepemilikan data (cegah IDOR).
- Rahasia (API key, kredensial Firebase, Midtrans) hanya di .env.
- Operasi saldo atomik (transaksi DB + row lock) dan idempotent.
- Aksi penting tercatat di audit log.

Kinerja dan keandalan
- Respons API normal di bawah 2 detik (chatbot di bawah 10 detik).
- Tabel besar memakai paginasi.
- Backup database terjadwal harian di VPS.

Kompatibilitas dan kegunaan
- Mobile: Android 8.0 ke atas. Web petugas/admin: Chrome atau Edge
  terbaru di desktop; webcam diperlukan untuk scan QR.
- Antarmuka Bahasa Indonesia; format sesuai BR-19.
- Target: petugas menyelesaikan satu setoran (3 jenis sampah, sampai
  verifikasi dan struk) dalam kurang dari 2 menit.

Pemeliharaan
- Branch per fitur dan pull request untuk setiap perubahan.
- Migrasi hanya dari repo backend admin.


=====================================================================
15. FASE, TIMELINE, & USULAN PEMBAGIAN TUGAS
=====================================================================

[F0] Persiapan (24 Sep - awal Okt 2026)
- Bersihkan skema database (hapus koin, misi, reward, bank sampah);
  tambah tabel baru sesuai Bagian 11.
- Sepakati kontrak API (koleksi Postman).
- Buat repo web petugas; salin kode panel petugas lama (termasuk
  halaman scan QR) sebelum dihapus dari repo backend.
- Siapkan Firebase project.

[F1] MVP uji lapangan (Oktober 2026)
- Web petugas lengkap: P-01 sampai P-09.
- Admin minimum: A-01 sampai A-06.
- Mobile MVP: M-01, M-02, M-03, M-05, M-06, M-07, M-08 (setoran),
  M-17 (logo dan desain dasar).
- Deploy dan gladi bersih dengan data dummy minimal 1 minggu
  sebelum hari setor Rumah Hijau.
- Tanggal setor Rumah Hijau bulan Oktober: [ISI - tim menyesuaikan]
- Akun uji nasabah dibuat lewat seeder untuk pengujian.

[F2] Fitur lengkap (November 2026)
- Perbaikan dari hasil uji lapangan pertama.
- Mobile: M-04, M-09 sampai M-16, M-08 lengkap.
- Web petugas: P-10. Admin: A-07 sampai A-11.
- Siklus uji lapangan kedua (setor bulan berikutnya).
- Midtrans Payouts sandbox bila waktu cukup.

[F3] Finalisasi (Desember 2026)
- Polishing UI, pengujian menyeluruh, dokumentasi, dan demo.

Usulan pembagian tugas (tukar sesuai kemampuan anggota):
- Haris  : skema DB dan kontrak API; web petugas (setoran baru, scan
           QR, struk, cari nasabah); F2: endpoint chatbot, PayoutService,
           deploy, integrasi.
- Eca    : web petugas (dashboard, data pengepul, export XLSX);
           F2: perbaikan dari uji lapangan, landing page (tautan APK),
           dokumentasi dan pengujian.
- Dijah  : web admin (pembersihan fitur lama, nasabah, petugas, jenis
           sampah dan harga); F2: persetujuan tukar saldo, edukasi,
           audit log, dashboard admin; mobile: edukasi, FAQ, notifikasi,
           lokasi, edit akun.
- Chizbu : mobile inti (auth, QR, saldo, riwayat, harga, desain baru);
           F2: tukar saldo dan PIN, chatbot (UI), login Google, FCM
           (sisi mobile), penyempurnaan desain.


=====================================================================
16. RISIKO & MITIGASI
=====================================================================

R1  Uji lapangan hanya sebulan sekali.
    -> Gladi bersih data dummy sebelum hari setor; siapkan cadangan
       pencatatan manual; prioritaskan alur inti (P-02 sampai P-05).
R2  Web petugas bergantung pada nasabah yang sudah punya QR.
    -> Mobile MVP (registrasi, QR, saldo) wajib siap di Fase 1;
       cadangan: akun uji lewat seeder.
R3  Dua repo Laravel dengan satu database.
    -> Aturan Bagian 4 (migrasi satu repo, guard sama, logika saldo
       satu tempat, transaksi + lock).
R4  Limit gratis atau perubahan model Gemini.
    -> Rate limit, pesan fallback, nama model diverifikasi sebelum
       implementasi; jangan memakai seri 2.5.
R5  Konfigurasi Firebase (SHA-1, izin notifikasi Android 13+).
    -> Siapkan sejak Fase 0; gunakan keystore yang konsisten.
R6  Midtrans Payouts butuh akun dan proses pendaftaran.
    -> Opsional; simulasi internal sebagai jalur utama lewat
       PayoutService.
R7  Beban kerja tim (FCM, login Google, chatbot bertambah).
    -> Ikuti fase; fitur F2 boleh bergeser ke F3 bila F1 terlambat,
       kecuali chatbot yang menjadi syarat kampus.
R8  Warga tanpa aplikasi tidak dapat dilayani petugas.
    -> Di luar lingkup; bila jadi masalah, tambahkan fitur
       "daftarkan nasabah" di web petugas.
R9  Logo dan data mitra terlambat diterima.
    -> Pakai placeholder, ganti saat data tiba.


=====================================================================
17. KRITERIA PENERIMAAN
=====================================================================

F1 dianggap selesai bila:
- Nasabah dapat daftar, verifikasi OTP, login, dan melihat QR serta
  saldo di aplikasi.
- Petugas dapat login, scan QR (atau cari manual), input beberapa
  jenis sampah dengan berat desimal, menyimpan, memverifikasi, dan
  melihat struk berisi semua field pada P-04.
- Saldo nasabah bertambah tepat sesuai harga beli saat setoran, dan
  tidak berubah walau admin mengubah harga setelahnya.
- Setoran yang dibatalkan tidak mengubah saldo; setoran SELESAI tidak
  dapat diedit.
- Transaksi hari ini, cek transaksi, cari nasabah, dashboard, data
  pengepul, dan export XLSX berfungsi dengan angka yang benar.
- Admin dapat mengelola petugas, nasabah, jenis sampah, dan harga
  (dengan log perubahan).
- Fitur koin, misi, reward, bank sampah, dan harga koin sudah tidak
  ada di semua panel dan aplikasi.

F2 dianggap selesai bila:
- Tukar saldo berjalan penuh: validasi minimal Rp10.000, PIN, saldo
  ditahan, persetujuan/penolakan admin dengan alasan, saldo
  dikembalikan bila ditolak, notifikasi terkirim.
- Login Google berjalan sesuai BR-17.
- Notifikasi in-app dan push FCM diterima untuk semua pemicu.
- Edukasi (dikelola admin), FAQ, lokasi/jadwal, dan chatbot berfungsi;
  chatbot menolak pertanyaan di luar topik dan tidak mengakses data
  pribadi.
- Jadwal setor yang diisi petugas tampil benar di mobile.

F3 dianggap selesai bila:
- Tidak ada bug kritis terbuka, dokumentasi tersedia, dan demo
  berjalan mulus.


=====================================================================
18. DATA YANG DIBUTUHKAN DARI MITRA / TIM
=====================================================================

- Logo Rumah Hijau (dari Haris).
- Alamat lengkap dan koordinat Rumah Hijau.
- Daftar jenis sampah beserta harga beli dan harga jual awal.
- Tanggal setor Rumah Hijau pada Oktober dan November 2026.
- Nomor WhatsApp Rumah Hijau (opsional, untuk tombol kontak).
- Konten awal: artikel edukasi (sekitar 5-10) dan daftar FAQ
  (sekitar 10).


=====================================================================
19. ASUMSI (BELUM DISEBUT EKSPLISIT OLEH PEMILIK PRODUK)
=====================================================================

Bagian ini berisi keputusan default penyusun. Ubah bila tidak sesuai.

1. Edit/batal setoran hanya sebelum verifikasi; batal wajib alasan.
2. Dua tahap konfirmasi dilakukan petugas saja (BR-06).
3. Snapshot harga dilakukan saat setoran DISIMPAN (bukan saat
   verifikasi), agar total yang dilihat petugas dan nasabah tidak
   berubah di antara dua langkah.
4. Edukasi berformat artikel + gambar sampul + tautan video opsional.
5. Aplikasi dibagikan sebagai APK lewat tautan di landing page.
6. Tombol WhatsApp opsional, tampil bila nomor tersedia.
7. PIN 6 digit, terkunci 15 menit setelah 5 kali salah.
8. OTP 6 digit, 5 menit, maks. 5 percobaan, jeda kirim ulang 60 detik.
9. Berat 2 desimal; rupiah integer dengan pembulatan ke rupiah
   terdekat.
10. Satu permintaan tukar saldo aktif per nasabah; tanpa biaya.
11. Min. Android 8.0 (API 26).
12. Rate limit chatbot 10 pesan/menit; riwayat chat disimpan lokal.
13. Metrik dashboard admin dan cakupan audit log seperti A-09 dan
    A-10.
14. Estimasi pengepul memakai harga jual snapshot per setoran.
15. Field edit akun seperti M-16; email tidak dapat diubah.


=====================================================================
20. PENGEMBANGAN LANJUTAN (DI LUAR PROYEK 3)
=====================================================================

- Fitur "daftarkan nasabah" oleh petugas untuk warga tanpa aplikasi.
- Pencairan sungguhan lewat Midtrans Payouts (butuh akun bisnis dan
  dana sumber).
- Distribusi Play Store dan dukungan iOS.
- Pencatatan penjualan ke pengepul dan pengurangan stok.
- Laporan bulanan otomatis dan grafik tren.

=====================================================================
AKHIR DOKUMEN
=====================================================================
