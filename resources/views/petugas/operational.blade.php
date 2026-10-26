<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Petugas · Rumah Hijau</title><link rel="stylesheet" href="{{ asset('css/petugas-preview.css') }}"><link rel="stylesheet" href="{{ asset('css/petugas-operational.css') }}"></head><body>
@include('petugas.sidebar')
<script src="{{ asset('js/petugas-browse.js') }}" defer></script>
<main><header><div><h1>{{ ['login'=>'Masuk sebagai Petugas','index'=>'Transaksi Hari Ini','form'=>'Input Setoran Baru','show'=>'Detail Setoran','customers'=>'Cari Nasabah'][$screen] }}</h1><p>{{ $screen === 'index' ? 'Pantau setoran nasabah dan periksa transaksi yang menunggu verifikasi.' : 'Bank Sampah Rumah Hijau · Jadwal setor belum diumumkan' }}</p></div>@if($screen === 'index')<div class="actions"><a class="button" href="{{ url('/petugas/export').'?'.http_build_query(['kind'=>'transaksi','from'=>$filters['from'],'to'=>$filters['to']]) }}">@svg('heroicon-o-arrow-down-tray','action-icon') Export selesai</a><a class="button primary" href="/petugas/setoran-baru">+ Setoran Baru</a></div>@endif</header>
@if($errors->any())<div class="notice" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($screen === 'login')
<section class="card" style="max-width:500px"><h2>Selamat datang kembali</h2><p>Gunakan akun petugas aktif yang dibuat admin.</p><form method="post" action="/petugas/login">@csrf<label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button class="button primary" style="margin-top:20px">Masuk</button></form></section>
@elseif($screen === 'index')
@include('petugas.transactions')
@elseif($screen === 'form')
<form id="operational-form" method="post" action="{{ $record ? '/petugas/setoran/'.$record->id : '/petugas/setoran' }}">@csrf @if($record)@method('PUT')@endif
<div class="columns"><section class="card"><div class="section-heading"><div><h2>Detail Sampah & Perhitungan</h2><p>Harga otomatis; kg dan liter dihitung terpisah.</p></div><span class="badge waiting">{{ $record ? 'Menunggu Verifikasi' : 'Belum disimpan' }}</span></div>
<div id="server-items">@foreach(old('items', $details->map(fn($d)=>['jenis_sampah_id'=>$d->jenis_sampah_id,'jumlah'=>$d->jumlah])->all() ?: [['jenis_sampah_id'=>'','jumlah'=>'']]) as $i=>$item)<div class="item-row"><label>Jenis sampah<select name="items[{{ $i }}][jenis_sampah_id]" required><option value="">Pilih jenis</option>@foreach($types as $t)<option value="{{ $t->id }}" data-price="{{ $t->harga_beli }}" data-unit="{{ $t->satuan }}" @selected(($item['jenis_sampah_id']??'')==$t->id)>{{ $t->nama }} · Rp{{ number_format($t->harga_beli,0,',','.') }}/{{ $t->satuan }}{{ !$t->aktif ? ' (snapshot nonaktif)' : '' }}</option>@endforeach</select></label><label><span data-quantity-label>Jumlah</span><input type="number" name="items[{{ $i }}][jumlah]" min="0.01" max="10000" step="0.01" value="{{ $item['jumlah']??'' }}" required></label><label>Subtotal<output data-subtotal>Rp0</output></label><button type="button" class="button" data-remove aria-label="Hapus jenis sampah">×</button></div>@endforeach</div>
<button type="button" class="text-button" id="server-add">+ Tambah Jenis Sampah Lain</button><div class="total-strip"><div><small>Total berat & volume</small><strong id="live-quantity">0 kg + 0 liter</strong></div><div class="right"><small>Total nilai nasabah</small><strong id="live-total" aria-live="polite">Rp0</strong></div></div><div class="actions"><button class="button primary">Simpan Setoran</button><a class="button" href="/petugas">Kembali</a></div><small>Verifikasi tersedia setelah setoran disimpan.</small></section>
<section class="card customer-panel"><h2>Pilih Nasabah Aktif</h2><label>Cari manual<div class="search-control"><input id="customer-query" type="search" placeholder="Nama / no. HP / kode QR" maxlength="100" autocomplete="off"><button type="button" id="search-customers" aria-label="Cari nasabah">@svg('heroicon-o-magnifying-glass')</button></div></label><p id="search-status" role="status"></p><label class="customer-picker">Hasil pencarian<select id="live-customer" name="nasabah_id" required><option value="">Pilih nasabah</option>@foreach($customers as $c)<option value="{{ $c->id }}" data-phone="{{ $c->no_hp }}" data-address="{{ $c->alamat }}" @selected($selected==$c->id)>{{ $c->nama }}</option>@endforeach</select></label><div class="identity-card" id="identity-card" hidden><span class="avatar" id="customer-initials"></span><div><strong id="customer-name"></strong><p id="live-customer-detail"></p></div></div>@include('petugas.scanner')</section></div></form>
<script src="{{ asset('js/petugas-operational.js') }}" defer></script>
@elseif($screen === 'customers')
<div class="columns customer-layout">
    <div class="customer-left-col">
        <section class="card customer-search-card">
            <h2 class="customer-search-heading">Masukkan Nama atau No. Anggota</h2>
            <form method="get" action="/petugas/nasabah" class="customer-search-form">
                <div class="search-input-wrapper">
                    @svg('heroicon-o-magnifying-glass', 'search-icon')
                    <input name="q" value="{{ request('q') }}" placeholder="Cari nama, no. HP, atau kode QR..." maxlength="100" autocomplete="off">
                </div>
                <button type="submit" class="button search-submit-btn">Cari</button>
            </form>
        </section>

        <section class="card customer-results-card">
            <div class="section-heading">
                <h2>Hasil Pencarian ({{ $customers->total() }} Anggota Ditemukan)</h2>
            </div>
            <div class="customer-results-list">
                @forelse($customers as $c)
                    <a class="customer-result-item {{ ($customer && $customer->id === $c->id) ? 'selected' : '' }}" href="/petugas/nasabah/{{ $c->id }}{{ request('q') ? '?q='.urlencode(request('q')) : '' }}">
                        <span class="avatar avatar-circle">{{ mb_substr($c->nama, 0, 1) }}</span>
                        <div class="customer-meta">
                            <strong class="customer-meta-name">{{ $c->nama }}</strong>
                            <small class="customer-meta-sub">
                                ID: {{ $c->id }} &bull; {{ $c->alamat ?: 'Alamat belum tersedia' }} &bull; {{ $c->no_hp ?: 'No. HP belum tersedia' }}
                            </small>
                        </div>
                    </a>
                @empty
                    <div class="empty-state">
                        <p class="muted">Nasabah tidak ditemukan dengan kata kunci tersebut.</p>
                    </div>
                @endforelse
            </div>
            <div class="actions pagination-actions">
                @if($customers->previousPageUrl())
                    <a class="button" href="{{ $customers->previousPageUrl() }}">Sebelumnya</a>
                @endif
                <span class="muted pagination-info">Halaman {{ $customers->currentPage() }} dari {{ $customers->lastPage() }}</span>
                @if($customers->nextPageUrl())
                    <a class="button" href="{{ $customers->nextPageUrl() }}">Selanjutnya</a>
                @endif
            </div>
        </section>
    </div>

    <div class="customer-right-col">
        <section class="card customer-profile-card">
            @if($customer)
                <div class="profile-header">
                    <div class="profile-avatar-large">
                        {{ mb_substr($customer->nama, 0, 1) }}
                    </div>
                    <h2 class="profile-name">{{ $customer->nama }}</h2>
                    <span class="profile-meta-sub">
                        ID: {{ $customer->id }} &bull; Anggota Terdaftar
                    </span>
                </div>

                <div class="profile-details-list">
                    <div class="profile-detail-row">
                        <span class="detail-label">No. HP</span>
                        <strong class="detail-value">{{ $customer->no_hp ?: 'Belum tersedia' }}</strong>
                    </div>
                    <div class="profile-detail-row">
                        <span class="detail-label">Alamat</span>
                        <strong class="detail-value">{{ $customer->alamat ?: 'Belum tersedia' }}</strong>
                    </div>
                    <div class="profile-detail-row">
                        <span class="detail-label">Status Keaktifan</span>
                        <span class="badge {{ $customer->aktif ? 'success' : 'cancelled' }}">
                            {{ $customer->aktif ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                </div>

                <div class="profile-finance-box">
                    <div class="finance-row">
                        <span class="finance-label">Saldo Utama</span>
                        <strong class="finance-amount">Rp{{ number_format($saldo?->tersedia ?? 0, 0, ',', '.') }}</strong>
                    </div>
                    <div class="finance-row-sub">
                        <span class="finance-sub-label">Total Setoran</span>
                        <span class="finance-sub-value">
                            {{ number_format($totalKg, 2, ',', '.') }} kg
                            @if($totalLiter > 0)
                                + {{ number_format($totalLiter, 2, ',', '.') }} liter
                            @endif
                        </span>
                    </div>
                </div>

                @if($customer->aktif)
                    <a class="button primary select-for-deposit-btn" href="/petugas/setoran-baru?nasabah_id={{ $customer->id }}">
                        Pilih untuk Setoran Baru
                    </a>
                @endif

                <div class="profile-history-section">
                    <h3 class="history-heading">Riwayat Setoran</h3>
                    <div class="history-list">
                        @forelse($history as $h)
                            <div class="history-item">
                                <a class="history-link" href="/petugas/setoran/{{ $h->id }}">
                                    <span class="history-code">{{ $h->kode }}</span>
                                    <span class="history-status badge {{ $h->status === 'SELESAI' ? 'success' : ($h->status === 'MENUNGGU_VERIFIKASI' ? 'waiting' : 'cancelled') }}">
                                        {{ str_replace('_', ' ', $h->status) }}
                                    </span>
                                    <strong class="history-amount">Rp{{ number_format($h->total_nilai, 0, ',', '.') }}</strong>
                                </a>
                            </div>
                        @empty
                            <p class="muted empty-history">Belum ada riwayat transaksi setoran.</p>
                        @endforelse
                    </div>
                    @if($history && ($history->previousPageUrl() || $history->nextPageUrl()))
                        <div class="actions history-pagination">
                            @if($history->previousPageUrl())
                                <a class="button" href="{{ $history->previousPageUrl() }}">Sebelumnya</a>
                            @endif
                            @if($history->nextPageUrl())
                                <a class="button" href="{{ $history->nextPageUrl() }}">Selanjutnya</a>
                            @endif
                        </div>
                    @endif
                </div>
            @else
                <div class="profile-empty-state">
                    <div class="empty-icon-circle">@svg('heroicon-o-user', 'icon-empty')</div>
                    <h2>Detail Nasabah</h2>
                    <p class="muted">Silakan pilih salah satu nasabah dari hasil pencarian di sebelah kiri untuk melihat rincian profil dan saldo.</p>
                </div>
            @endif
        </section>
    </div>
</div>
@elseif($screen === 'show')
<div class="transaction-detail-shell">
    <section class="card detail-header-card">
        <div class="detail-top-nav">
            <a class="button" href="/petugas">@svg('heroicon-o-arrow-left', 'action-icon') Kembali ke Transaksi</a>
            <div class="detail-actions-right">
                @if($record->status === 'SELESAI')
                    <button type="button" class="button" onclick="document.getElementById('receipt-dialog').showModal()">
                        @svg('heroicon-o-ticket', 'action-icon') Lihat Struk Lengkap
                    </button>
                    <button type="button" class="button primary" onclick="printReceiptDirectly()">
                        @svg('heroicon-o-printer', 'action-icon') Cetak Struk
                    </button>
                @endif
            </div>
        </div>

        <div class="detail-summary-banner">
            <div class="summary-left">
                <span class="badge {{ $record->status === 'SELESAI' ? 'success' : ($record->status === 'MENUNGGU_VERIFIKASI' ? 'waiting' : 'cancelled') }}">
                    {{ str_replace('_', ' ', $record->status) }}
                </span>
                <h2>Setoran {{ $record->kode }}</h2>
                <p class="summary-meta">
                    Dicatat oleh <strong>{{ $recorder->nama }}</strong> pada {{ \Carbon\Carbon::parse($record->created_at)->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                    @if($record->diverifikasi_at)
                        &bull; Diverifikasi pada {{ \Carbon\Carbon::parse($record->diverifikasi_at)->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                    @endif
                </p>
            </div>
            <div class="summary-right">
                <small>Total Nilai Setoran</small>
                <strong class="total-big">Rp{{ number_format($record->total_nilai, 0, ',', '.') }}</strong>
            </div>
        </div>
    </section>

    <div class="columns equal" style="margin-top:20px">
        <section class="card">
            <div class="section-heading">
                <h2>Rincian Sampah Disetor</h2>
                <span class="badge">{{ $record->total_kg }} kg @if($record->total_liter > 0) + {{ $record->total_liter }} liter @endif</span>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Jenis Sampah</th>
                            <th>Jumlah</th>
                            <th>Harga Beli</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($details as $d)
                            <tr>
                                <td><strong>{{ $d->nama_jenis }}</strong></td>
                                <td>{{ $d->jumlah }} {{ $d->satuan }}</td>
                                <td>Rp{{ number_format($d->harga_beli_snapshot, 0, ',', '.') }}/{{ $d->satuan }}</td>
                                <td><strong style="color:#198653">Rp{{ number_format($d->subtotal, 0, ',', '.') }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="total-strip">
                <div>
                    <small>Total Berat & Volume</small>
                    <strong>{{ $record->total_kg }} kg @if($record->total_liter > 0) + {{ $record->total_liter }} liter @endif</strong>
                </div>
                <div class="right">
                    <small>Total Yang Diterima Nasabah</small>
                    <strong style="font-size:24px;color:#198653">Rp{{ number_format($record->total_nilai, 0, ',', '.') }}</strong>
                </div>
            </div>

            @if($record->alasan_batal)
                <div class="notice" style="background:#fff1ed;border-color:#f0c8bd;color:#87392c;margin-top:16px">
                    <strong>Alasan Pembatalan:</strong> {{ $record->alasan_batal }}
                </div>
            @endif

            @if($record->status === 'MENUNGGU_VERIFIKASI' && $record->petugas_id === auth()->id())
                <div class="actions" style="margin-top:24px;justify-content:space-between">
                    <a class="button" href="/petugas/setoran/{{ $record->id }}/edit">Edit Setoran</a>
                    <form method="post" action="/petugas/setoran/{{ $record->id }}/finish" onsubmit="return confirm('Verifikasi setoran ini? Saldo nasabah akan bertambah dan transaksi akan terkunci permanen.')">
                        @csrf
                        <input type="hidden" name="action" value="verify">
                        <button class="button primary" style="font-size:15px;padding:12px 24px">
                            @svg('heroicon-o-check-circle', 'action-icon') Verifikasi & Cetak Struk
                        </button>
                    </form>
                </div>
                <details style="margin-top:24px;border-top:1px solid #d9e5dc;padding-top:16px">
                    <summary style="color:#87392c;font-size:13px;cursor:pointer">Batalkan setoran ini</summary>
                    <form method="post" action="/petugas/setoran/{{ $record->id }}/finish" style="margin-top:12px">
                        @csrf
                        <input type="hidden" name="action" value="cancel">
                        <label>Alasan pembatalan (wajib)<input name="alasan" maxlength="2000" placeholder="Misal: Salah input timbangan atau nasabah membatalkan" required></label>
                        <button class="button danger" style="margin-top:10px">Konfirmasi Batalkan</button>
                    </form>
                </details>
            @endif
        </section>

        <section class="card">
            <div class="section-heading">
                <h2>Informasi Nasabah</h2>
            </div>
            <div class="profile-details-list">
                <div class="profile-detail-row">
                    <span class="detail-label">Nama Nasabah</span>
                    <strong class="detail-value">{{ $customer->nama }}</strong>
                </div>
                <div class="profile-detail-row">
                    <span class="detail-label">No. Telepon / WA</span>
                    <strong class="detail-value">{{ $customer->no_hp ?: 'Belum tersedia' }}</strong>
                </div>
                <div class="profile-detail-row">
                    <span class="detail-label">Alamat Domisili</span>
                    <strong class="detail-value">{{ $customer->alamat ?: 'Belum tersedia' }}</strong>
                </div>
                <div class="profile-detail-row">
                    <span class="detail-label">Status Akun</span>
                    <span class="badge {{ $customer->aktif ? 'success' : 'cancelled' }}">{{ $customer->aktif ? 'Aktif' : 'Nonaktif' }}</span>
                </div>
            </div>

            <div class="profile-finance-box" style="margin-top:20px">
                <div class="finance-row">
                    <span class="finance-label">Saldo Nasabah Saat Ini</span>
                    <strong class="finance-amount">Rp{{ number_format($saldoNasabah?->tersedia ?? 0, 0, ',', '.') }}</strong>
                </div>
                <div class="finance-row-sub">
                    <span class="finance-sub-label">Saldo Ditahan</span>
                    <span class="finance-sub-value">Rp{{ number_format($saldoNasabah?->ditahan ?? 0, 0, ',', '.') }}</span>
                </div>
            </div>
        </section>
    </div>
</div>

@if($record->status === 'SELESAI')
<dialog id="receipt-dialog" class="dialog-modal receipt-modal">
    <div class="receipt-paper" id="printable-receipt">
        <div class="receipt-header">
            <img src="{{ route('petugas.logo') }}" alt="Logo Bank Sampah Rumah Hijau" class="receipt-logo" width="60" height="60">
            <h2 class="receipt-title">BANK SAMPAH RUMAH HIJAU</h2>
            <p class="receipt-org-meta">
                Sekretariat: Perumahan Glayem Village C 25-26, Sindang, Indramayu<br>
                Nomor SK : 18/XI/2025/07-Ds &bull; WA: 0878-7494-8049
            </p>
        </div>

        <div class="receipt-divider-dashed"></div>

        <div class="receipt-meta-grid">
            <div>
                <span class="rmeta-label">No. Transaksi</span>
                <strong class="rmeta-val">{{ $record->kode }}</strong>
            </div>
            <div style="text-align:right">
                <span class="rmeta-label">Waktu</span>
                <strong class="rmeta-val">{{ \Carbon\Carbon::parse($record->diverifikasi_at ?? $record->created_at)->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</strong>
            </div>
            <div>
                <span class="rmeta-label">Nasabah</span>
                <strong class="rmeta-val">{{ $customer->nama }} (ID: {{ $customer->id }})</strong>
            </div>
            <div style="text-align:right">
                <span class="rmeta-label">Petugas</span>
                <strong class="rmeta-val">{{ $recorder->nama }}</strong>
            </div>
        </div>

        <div class="receipt-divider-dashed"></div>

        <table class="receipt-table">
            <thead>
                <tr>
                    <th style="text-align:left">Tipe Sampah</th>
                    <th style="text-align:right">Berat</th>
                    <th style="text-align:right">Harga/kg</th>
                    <th style="text-align:right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($details as $d)
                    <tr>
                        <td style="text-align:left">{{ $d->nama_jenis }}</td>
                        <td style="text-align:right">{{ $d->jumlah }} {{ $d->satuan }}</td>
                        <td style="text-align:right">{{ number_format($d->harga_beli_snapshot, 0, ',', '.') }}</td>
                        <td style="text-align:right">{{ number_format($d->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="receipt-divider-dashed"></div>

        <div class="receipt-totals-box">
            <div class="rtotal-row">
                <span>Total Berat Sampah:</span>
                <strong>{{ $record->total_kg }} kg @if($record->total_liter > 0) + {{ $record->total_liter }} liter @endif</strong>
            </div>
            <div class="rtotal-row rtotal-grand">
                <span>TOTAL NILAI TUKAR:</span>
                <strong>Rp{{ number_format($record->total_nilai, 0, ',', '.') }}</strong>
            </div>
            <div class="rtotal-row rtotal-sub">
                <span>Saldo Nasabah Saat Ini:</span>
                <span>Rp{{ number_format($saldoNasabah?->tersedia ?? 0, 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="receipt-footer">
            <p class="receipt-thanks">*** TERIMA KASIH TELAH MEMILAH SAMPAH ***</p>
            <small>Sampah Anda berharga, selamatkan bumi bersama Rumah Hijau.</small>
        </div>
    </div>

    <div class="receipt-modal-actions no-print">
        <button type="button" class="button" onclick="printReceiptDirectly()">
            @svg('heroicon-o-printer', 'action-icon') Cetak Struk
        </button>
        <a class="button primary" href="/petugas/setoran-baru">
            @svg('heroicon-o-plus-circle', 'action-icon') Catat Setoran Berikutnya
        </a>
        <button type="button" class="button" onclick="document.getElementById('receipt-dialog').close()">
            Tutup
        </button>
    </div>
</dialog>

<script>
    function printReceiptDirectly() {
        const dialog = document.getElementById('receipt-dialog');
        if (dialog && !dialog.open) {
            dialog.showModal();
        }
        window.print();
    }

    // Otomatis buka pop-up struk jika baru saja selesai diverifikasi
    @if(session('just_verified'))
        document.addEventListener('DOMContentLoaded', function() {
            const dialog = document.getElementById('receipt-dialog');
            if (dialog) {
                dialog.showModal();
            }
        });
    @endif
</script>
@endif
@endif
<footer>Bank Sampah Rumah Hijau · Data PostgreSQL</footer></main></body></html>
