@php
    $dashboard = request()->is('petugas/dashboard');
    $today = \Carbon\CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d');
    $isToday = $dates['from'] === $today && $dates['to'] === $today;
    $empty = (int) $summary->transaksi === 0;
    $estimate = $empty ? 'Belum ada setoran' : ($sale === null ? 'Belum tersedia' : 'Rp'.number_format($sale,0,',','.'));
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $dashboard ? 'Dashboard' : 'Data untuk Pengepul' }} · Rumah Hijau</title>
    <link rel="stylesheet" href="{{ asset('css/petugas-preview.css') }}">
    <link rel="stylesheet" href="{{ asset('css/petugas-operational.css') }}">
</head>
<body>
<script src="{{ asset('js/petugas-reports.js') }}" defer></script>
@include('petugas.sidebar')
<main @class(['collector-page' => !$dashboard])>
    <header>
        <div><h1>{{ $dashboard ? 'Dashboard Petugas' : 'Data untuk Pengepul' }}</h1>
            <p>{{ $dashboard ? 'Ringkasan pelayanan dan hasil setoran Rumah Hijau.' : 'Rekap sampah yang diterima dari setoran selesai.' }}</p>
        </div>
        @if($dashboard)
        <div class="actions">
            <button type="button" class="button" onclick="document.getElementById('jadwal-dialog').showModal()">@svg('heroicon-o-calendar', 'action-icon') Atur Jadwal Buka</button>
            <a class="button primary" href="/petugas/setoran-baru">+ Setoran Baru</a>
        </div>
        @else
        <a class="button primary" href="{{ url('/petugas/export').'?'.http_build_query($dates+['kind'=>'pengepul']) }}">Export XLSX Pengepul</a>
        @endif
    </header>
    @if(session('success'))
        <div class="notice success-notice" role="status"><strong>Berhasil:</strong> {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    @if($dashboard && isset($jadwalInfo))
    <section class="card schedule-card" aria-label="Jadwal Pelayanan Rumah Hijau">
        <div class="schedule-header">
            <div class="schedule-title-area">
                <span class="schedule-badge-wrapper">
                    @svg('heroicon-o-clock', 'schedule-clock-icon')
                    <span class="badge {{ $jadwalInfo['badge_class'] }}">{{ $jadwalInfo['status_label'] }}</span>
                </span>
                <h2>Jadwal Pelayanan Rumah Hijau</h2>
                <p class="schedule-desc">{{ $jadwalInfo['status_desc'] }}</p>
            </div>
            <div class="schedule-actions">
                <button type="button" class="button primary" onclick="document.getElementById('jadwal-dialog').showModal()">
                    @svg('heroicon-o-calendar') {{ $jadwalInfo['jadwal'] ? 'Ubah Jadwal' : 'Buat Jadwal' }}
                </button>
                @if($jadwalInfo['jadwal'])
                    <form method="post" action="/petugas/dashboard/jadwal/{{ $jadwalInfo['jadwal']->id }}/darurat" onsubmit="return confirm('{{ $jadwalInfo['is_emergency'] ? 'Buka kembali pelayanan normal?' : 'Tutup darurat pelayanan hari ini?' }}')">
                        @csrf
                        <button type="submit" class="button {{ $jadwalInfo['is_emergency'] ? 'success-btn' : 'danger' }}">
                            @if($jadwalInfo['is_emergency'])
                                @svg('heroicon-o-check-circle') Buka Kembali
                            @else
                                @svg('heroicon-o-exclamation-triangle') Tutup Darurat
                            @endif
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if($jadwalInfo['jadwal'])
            <div class="schedule-details-grid">
                <div class="schedule-col">
                    <small>Tanggal Pelayanan</small>
                    <strong>{{ \Carbon\CarbonImmutable::parse($jadwalInfo['jadwal']->tanggal)->locale('id')->isoFormat('dddd, D MMMM Y') }}</strong>
                </div>
                <div class="schedule-col">
                    <small>Jam Operasional</small>
                    <strong>{{ substr($jadwalInfo['jadwal']->jam_buka, 0, 5) }} — {{ substr($jadwalInfo['jadwal']->jam_tutup, 0, 5) }} WIB</strong>
                </div>
                <div class="schedule-col note-col">
                    <small>Catatan Petugas</small>
                    <p>{{ $jadwalInfo['jadwal']->catatan ?: 'Bawa sampah yang sudah bersih dan terpilah sesuai jenisnya.' }}</p>
                </div>
            </div>
        @endif
    </section>
    @endif
    <section class="period-panel" aria-label="Periode ringkasan">
        <div class="section-heading">
            <div><strong>Periode ringkasan: {{ $isToday ? 'Hari ini' : 'Rentang tanggal' }}</strong>
                <p>Menampilkan setoran selesai: {{ \Carbon\CarbonImmutable::parse($dates['from'])->format('d M Y') }}–{{ \Carbon\CarbonImmutable::parse($dates['to'])->format('d M Y') }} (WIB).</p>
            </div>
            <div class="actions period-shortcuts"><a data-report-link class="button {{ $isToday ? 'primary' : '' }}" href="{{ url()->current() }}">Hari ini</a><a data-report-link class="button" href="{{ url()->current().'?'.http_build_query(['from'=>now('Asia/Jakarta')->startOfMonth()->format('Y-m-d'),'to'=>$today]) }}">Bulan ini</a></div>
        </div>
        <details @if(!$isToday || $errors->any()) open @endif>
            <summary>Pilih rentang tanggal</summary>
            <p class="period-help">Mengubah periode angka, tabel, dan export di halaman ini berdasarkan waktu verifikasi. Bukan jadwal buka Rumah Hijau.</p>
            <form class="toolbar filters" data-report-form>
                <label>Dari tanggal<input type="date" name="from" value="{{ $dates['from'] }}" required></label>
                <label>Sampai tanggal<input type="date" name="to" value="{{ $dates['to'] }}" required></label>
                <button class="button primary">Terapkan periode</button>
            </form>
        </details>
    </section>
    @if($dashboard)
    <section class="stats" aria-label="Ringkasan setoran selesai">
        <article class="stat featured"><small>Setoran selesai</small><strong>{{ $summary->transaksi }}</strong><span>Dalam periode terpilih</span></article>
        <article class="stat"><small>Berat dan volume diterima</small><strong>{{ number_format($summary->kg,2,',','.') }} kg</strong><span>{{ number_format($summary->liter,2,',','.') }} liter</span></article>
        <article class="stat"><small>Nilai pembelian dari nasabah</small><strong>Rp{{ number_format($summary->beli,0,',','.') }}</strong><span>Setoran yang sudah diverifikasi</span></article>
    </section>
    @if($missing)<div class="notice">Harga jual untuk {{ $missing }} kelompok jenis/satuan belum tersedia lengkap. Estimasi penjualan dan selisih belum dapat dihitung.</div>@endif
    @else
    <dl class="collector-summary" aria-label="Ringkasan sampah diterima">
        <div><dt>Jenis diterima</dt><dd>{{ $groups->pluck('jenis_sampah_id')->unique()->count() }} <small>jenis</small></dd></div>
        <div><dt>Total berat</dt><dd>{{ number_format($summary->kg,2,',','.') }} <small>kg</small></dd></div>
        <div><dt>Total volume</dt><dd>{{ number_format($summary->liter,2,',','.') }} <small>liter</small></dd></div>
    </dl>
    @endif

    @if($dashboard)
        <section class="card">
            <h2>Estimasi penjualan ke pengepul</h2>
            <div class="total-strip"><span>Total estimasi jual</span><strong>{{ $estimate }}</strong></div>
            <div class="total-strip"><span>Estimasi selisih</span><strong>{{ $empty ? 'Belum ada setoran' : ($sale === null ? 'Belum tersedia' : 'Rp'.number_format($sale-$summary->beli,0,',','.')) }}</strong></div>
            <a class="button" href="{{ url('/petugas/pengepul').'?'.http_build_query($dates) }}">Lihat rekap pengepul</a>
        </section>
        <section class="card">
            <h2>Top 5 nasabah berdasarkan berat (kg)</h2>
            <p>Nasabah dengan berat setoran selesai terbanyak pada periode ini. Volume liter ditampilkan terpisah.</p>
            <div class="table-scroll"><table><thead><tr><th>Nasabah</th><th>Berat</th><th>Volume</th></tr></thead><tbody>
            @forelse($top as $c)<tr><td>{{ $c->nama }}</td><td>{{ number_format($c->kg,2,',','.') }} kg</td><td>{{ number_format($c->liter,2,',','.') }} liter</td></tr>
            @empty<tr><td colspan="3">Belum ada setoran selesai pada periode ini.</td></tr>@endforelse
            </tbody></table></div>
        </section>
    @else
        <section class="card">
            <div class="section-heading"><div><h2>Rekap sampah diterima</h2><p>Hanya setoran selesai pada periode terpilih. Jumlah ini bukan stok gudang.</p></div>
                <span class="badge">{{ $summary->transaksi }} setoran selesai</span>
            </div>
            <div class="table-scroll"><table><thead><tr><th>Jenis</th><th>Jumlah</th><th>Nilai beli</th><th>Estimasi jual</th><th>Estimasi selisih</th></tr></thead><tbody>
            @forelse($groups as $g)<tr><td>{{ $g->nama_jenis }}</td><td>{{ number_format($g->jumlah,2,',','.') }} {{ $g->satuan }}</td><td>Rp{{ number_format($g->beli,0,',','.') }}</td><td>{{ $g->missing ? 'Belum tersedia' : 'Rp'.number_format($g->jual,0,',','.') }}</td><td>{{ $g->missing ? 'Belum tersedia' : 'Rp'.number_format($g->jual-$g->beli,0,',','.') }}</td></tr>
            @empty<tr><td colspan="5"><div class="collector-empty">@svg('heroicon-o-inbox')<strong>Belum ada sampah untuk direkap</strong><p>Setoran yang selesai diverifikasi akan muncul di sini. Coba pilih periode bulan ini.</p><a data-report-link class="button" href="{{ url()->current().'?'.http_build_query(['from'=>now('Asia/Jakarta')->startOfMonth()->format('Y-m-d'),'to'=>$today]) }}">Lihat bulan ini</a><small>Belum ada setoran selesai pada periode ini.</small></div></td></tr>@endforelse
            </tbody></table></div>
            <div class="collector-purchase"><span>Total nilai pembelian dari nasabah</span><strong>Rp{{ number_format($summary->beli,0,',','.') }}</strong></div>
            <details class="collector-pricing"><summary>Rincian harga saat setoran dicatat</summary><p>Harga berikut berasal dari transaksi, bukan harga master terbaru. Histori liter tidak dikonversi ke kg.</p><div class="table-scroll"><table><thead><tr><th>Jenis</th><th>Jumlah</th><th>Harga beli/satuan</th><th>Harga jual/satuan</th></tr></thead><tbody>@forelse($breakdown as $d)<tr><td>{{ $d->nama_jenis }}</td><td>{{ number_format($d->jumlah,2,',','.') }} {{ $d->satuan }}</td><td>Rp{{ number_format($d->harga_beli_snapshot,0,',','.') }}/{{ $d->satuan }}</td><td>{{ $d->harga_jual_snapshot === null ? 'Belum tersedia' : 'Rp'.number_format($d->harga_jual_snapshot,0,',','.').'/'.$d->satuan }}</td></tr>@empty<tr><td colspan="4">Belum ada rincian pada periode ini.</td></tr>@endforelse</tbody></table></div></details>
            <details class="collector-pricing">
                <summary>Harga jual dan estimasi selisih</summary>
                <p>{{ $empty ? 'Belum ada setoran selesai pada periode ini.' : ($missing ? 'Harga jual belum lengkap. Menunggu data resmi Rumah Hijau; estimasi tidak dihitung sebagai nol.' : 'Estimasi memakai harga jual saat setoran dicatat, bukan penjualan aktual.') }}</p>
                @if(!$empty && !$missing)<p>Total estimasi jual: <strong>{{ $estimate }}</strong><br>Estimasi selisih: <strong>Rp{{ number_format($sale-$summary->beli,0,',','.') }}</strong></p>@endif
            </details>
        </section>
    @endif
    <footer>Bank Sampah Rumah Hijau · Data PostgreSQL</footer>
</main>

@if($dashboard)
<dialog id="jadwal-dialog" class="dialog-modal">
    <div class="dialog-header">
        <h2>{{ (isset($jadwalInfo) && $jadwalInfo['jadwal']) ? 'Ubah Jadwal Pelayanan' : 'Buat Jadwal Pelayanan' }}</h2>
        <button type="button" class="close-dialog-btn" onclick="document.getElementById('jadwal-dialog').close()" aria-label="Tutup modal">×</button>
    </div>
    <form method="post" action="/petugas/dashboard/jadwal" class="dialog-form">
        @csrf
        @if(isset($jadwalInfo) && $jadwalInfo['jadwal'])
            <input type="hidden" name="id" value="{{ $jadwalInfo['jadwal']->id }}">
        @endif
        <label for="jadwal-tanggal">Tanggal Pelayanan
            <input id="jadwal-tanggal" type="date" name="tanggal" value="{{ old('tanggal', $jadwalInfo['jadwal']->tanggal ?? '') }}" required>
        </label>
        <div class="time-inputs-grid">
            <label for="jadwal-jam-buka">Jam Buka
                <input id="jadwal-jam-buka" type="time" name="jam_buka" value="{{ old('jam_buka', substr($jadwalInfo['jadwal']->jam_buka ?? '08:00', 0, 5)) }}" required>
            </label>
            <label for="jadwal-jam-tutup">Jam Tutup
                <input id="jadwal-jam-tutup" type="time" name="jam_tutup" value="{{ old('jam_tutup', substr($jadwalInfo['jadwal']->jam_tutup ?? '14:00', 0, 5)) }}" required>
            </label>
        </div>
        <label for="jadwal-catatan">Catatan untuk Nasabah (Opsional)
            <textarea id="jadwal-catatan" name="catatan" rows="3" placeholder="Contoh: Harap pilah botol plastik dan kardus sebelum dibawa.">{{ old('catatan', $jadwalInfo['jadwal']->catatan ?? '') }}</textarea>
        </label>
        <div class="actions dialog-actions">
            <button type="submit" class="button primary">Simpan Jadwal</button>
            <button type="button" class="button" onclick="document.getElementById('jadwal-dialog').close()">Batal</button>
        </div>
    </form>
</dialog>
@endif
</body>
</html>
