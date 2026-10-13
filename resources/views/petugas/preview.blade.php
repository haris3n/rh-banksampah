<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pages[$page][0] }} · Rumah Hijau</title>
    <link rel="stylesheet" href="{{ asset('css/petugas-preview.css') }}">
    <script src="{{ asset('js/petugas-preview.js') }}" defer></script>
</head>
<body data-page="{{ $page }}">
<aside class="sidebar">
    <a class="brand" href="{{ route('petugas.preview') }}"><img src="{{ route('petugas.logo') }}" alt="Logo Bank Sampah Rumah Hijau" width="48" height="48" style="border-radius:10px"><span><strong>Rumah Hijau</strong><small>Portal Petugas · Haris</small></span></a>
    <nav aria-label="Navigasi petugas">
        @foreach($pages as $key => $labels)
            <a href="{{ route('petugas.preview', $key) }}" @class(['nav-link', 'active' => $page === $key]) @if($page === $key) aria-current="page" @endif>
                @svg(['dashboard'=>'heroicon-o-squares-2x2','setoran-baru'=>'heroicon-o-document-plus','transaksi'=>'heroicon-o-clipboard-document-list','nasabah'=>'heroicon-o-user-group','pengepul'=>'heroicon-o-scale'][$key])
                {{ $labels[0] }}
            </a>
        @endforeach
    </nav>
    <div class="sidebar-note"><strong>Pratinjau lokal</strong><p>Data contoh untuk mencoba tampilan dan alur pelayanan.</p><button id="reset-demo" class="sidebar-button">Reset data contoh</button></div>
</aside>
<main>
    <header><div><h1>{{ $pages[$page][1] }}</h1><p id="page-subtitle">Pencatatan sampah terpilah untuk Rumah Hijau.</p></div><a class="button primary" href="{{ route('petugas.preview', 'setoran-baru') }}">+ Setoran Baru</a></header>
    <div class="notice"><strong>Mode demo</strong> · Data hanya disimpan di browser ini. Belum terhubung ke akun, saldo, atau database bersama.</div>
    <p class="muted">Jadwal setor belum diumumkan. Harga beli resmi Rumah Hijau; tanggal mulai berlaku belum dikonfirmasi.</p>
    <div id="feedback" role="status" hidden></div>

    @if(in_array($page, ['dashboard', 'transaksi', 'pengepul']))
        <div class="toolbar filters"><label>Dari tanggal<input id="from-date" type="date"></label><label>Sampai tanggal<input id="to-date" type="date"></label><span class="muted">Rekap selesai berdasarkan waktu verifikasi (WIB).</span></div>
        <section class="stats" aria-label="Ringkasan periode"><article class="stat featured"><small id="stat-label">Setoran selesai</small><strong id="stat-count">0</strong><span>Dalam periode terpilih</span></article><article class="stat"><small>Berat & volume diterima</small><strong id="stat-weight">0 kg + 0 liter</strong><span>Setoran selesai · satuan terpisah</span></article><article class="stat"><small id="value-label">Nilai pembelian sampah</small><strong id="stat-value">Rp0</strong><span id="value-note">Nilai saldo dari setoran</span></article></section>
    @endif

    @if(in_array($page, ['dashboard', 'setoran-baru']))
        <div class="columns"><section class="card"><div class="section-heading"><div><h2>Detail Sampah & Perhitungan</h2><p>Isi berat (kg) atau volume (liter) sesuai jenis sampah.</p></div><span class="badge waiting" id="form-status">Belum disimpan</span></div>
            <form id="deposit-form"><div id="items"></div><button type="button" class="text-button" id="add-item">+ Tambah Jenis Sampah</button><div class="total-strip"><div><small>Total berat & volume</small><strong id="form-weight">0 kg + 0 liter</strong></div><div class="right"><small>Total nilai setoran</small><strong id="form-total">Rp0</strong></div></div><p class="muted">Simpan terlebih dahulu. Saldo demo baru bertambah setelah verifikasi.</p><div class="actions"><button class="button primary" type="submit">Simpan Setoran</button><button class="button" type="button" id="clear-form">Kosongkan Form</button></div></form>
        </section><section class="card"><h2>Pilih Nasabah Aktif</h2>@include('petugas.customer-search')<div class="scan-box">@svg('heroicon-o-qr-code')<strong>Pencarian kode QR</strong><p>Masukkan kode contoh pada kolom pencarian, misalnya RH-DEMO-A7K2.</p><small>Scanner kamera menyusul pada integrasi operasional.</small></div></section></div>
    @endif

    @if($page === 'nasabah')
        <div class="columns equal"><section class="card"><h2>Cari nama, nomor HP, atau kode QR</h2>@include('petugas.customer-search')</section><section class="card" id="customer-detail"><h2>Detail Nasabah</h2><p>Pilih hasil pencarian untuk melihat identitas dan riwayat setoran.</p></section></div>
    @endif

    @if(in_array($page, ['dashboard', 'transaksi']))
        <section class="card"><div class="section-heading"><div><h2>Riwayat Transaksi</h2><p>Periksa rincian sebelum melakukan verifikasi.</p></div><span class="badge">Data contoh</span></div><div class="toolbar"><label class="grow">Cari transaksi atau nasabah<input id="transaction-search" placeholder="Nama atau kode transaksi"></label><label>Status<select id="status-filter"><option value="">Semua</option><option>Menunggu Verifikasi</option><option>Selesai</option><option>Dibatalkan</option></select></label></div><div class="table-scroll"><table><thead><tr><th>ID Transaksi</th><th>Nasabah</th><th>Berat</th><th>Nilai Tukar</th><th>Status</th><th>Aksi</th></tr></thead><tbody id="transactions"></tbody></table></div><p id="transaction-count" class="muted"></p></section>
    @endif

    @if($page === 'pengepul')
        <section class="card"><h2>Jumlah Diterima & Estimasi Nilai Jual</h2><p>Harga berasal dari snapshot setoran selesai. Bukan stok gudang atau penjualan aktual.</p><div class="table-scroll"><table><thead><tr><th>Jenis Sampah</th><th>Jumlah & satuan</th><th>Nilai Beli</th><th>Estimasi Nilai Jual</th><th>Estimasi Selisih</th></tr></thead><tbody id="collector-rows"></tbody></table></div><div class="total-strip"><span>Total nilai pembelian</span><strong id="collector-buy">Rp0</strong></div><div class="total-strip"><span>Total estimasi selisih</span><strong id="collector-margin">Belum tersedia</strong></div><p class="muted">Export XLSX akan dihubungkan setelah skema transaksi bersama tersedia.</p></section>
    @endif
    <footer><strong>Bank Sampah Rumah Hijau</strong><br>GLAYEM VILLAGE No.25-26, Sindang, Kec. Sindang, Kabupaten Indramayu, Jawa Barat 45211<br><a href="https://wa.me/6287874948049">WhatsApp: 0878-7494-8049</a> · <a href="https://www.instagram.com/rumahhijau_indramayu/">Instagram @rumahhijau_indramayu</a></footer>
</main>
<dialog id="deposit-dialog"><div class="section-heading"><h2 id="dialog-title">Detail Setoran</h2><button class="button" id="close-dialog" aria-label="Tutup detail">Tutup</button></div><div id="dialog-content"></div><div id="dialog-actions" class="actions"></div></dialog>
<template id="receipt-brand"><img src="{{ route('petugas.logo') }}" alt="Logo Rumah Hijau" width="64" height="64"><h3>Bank Sampah Rumah Hijau</h3><p>GLAYEM VILLAGE No.25-26, Sindang, Kec. Sindang, Kabupaten Indramayu, Jawa Barat 45211<br>WhatsApp: 0878-7494-8049</p></template>
</body>
</html>
