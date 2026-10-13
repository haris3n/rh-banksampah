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
@include('petugas.sidebar')
<main>
    <header>
        <div><h1>{{ $dashboard ? 'Dashboard Petugas' : 'Data untuk Pengepul' }}</h1>
            <p>{{ $dashboard ? 'Ringkasan pelayanan dan hasil setoran Rumah Hijau.' : 'Rekap sampah yang diterima dari setoran selesai.' }}</p>
        </div>
        <a class="button primary" href="/petugas/setoran-baru">+ Setoran Baru</a>
    </header>
    @if($errors->any())
        <div class="notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif
    <section class="period-panel" aria-label="Periode ringkasan">
        <div class="section-heading">
            <div><strong>Periode ringkasan: {{ $isToday ? 'Hari ini' : 'Rentang tanggal' }}</strong>
                <p>Menampilkan setoran selesai: {{ \Carbon\CarbonImmutable::parse($dates['from'])->format('d M Y') }}–{{ \Carbon\CarbonImmutable::parse($dates['to'])->format('d M Y') }} (WIB).</p>
            </div>
            @unless($isToday)<a class="button" href="{{ url()->current() }}">Hari ini</a>@endunless
        </div>
        <details @if(!$isToday || $errors->any()) open @endif>
            <summary>Pilih rentang tanggal</summary>
            <p class="period-help">Mengubah periode angka, tabel, dan export di halaman ini berdasarkan waktu verifikasi. Bukan jadwal buka Rumah Hijau.</p>
            <form class="toolbar filters">
                <label>Dari tanggal<input type="date" name="from" value="{{ $dates['from'] }}" required></label>
                <label>Sampai tanggal<input type="date" name="to" value="{{ $dates['to'] }}" required></label>
                <button class="button primary">Terapkan periode</button>
            </form>
        </details>
    </section>
    <section class="stats" aria-label="Ringkasan setoran selesai">
        <article class="stat featured"><small>Setoran selesai</small><strong>{{ $summary->transaksi }}</strong><span>Dalam periode terpilih</span></article>
        <article class="stat"><small>Berat dan volume diterima</small><strong>{{ number_format($summary->kg,2,',','.') }} kg</strong><span>{{ number_format($summary->liter,2,',','.') }} liter</span></article>
        <article class="stat"><small>Nilai pembelian dari nasabah</small><strong>Rp{{ number_format($summary->beli,0,',','.') }}</strong><span>Setoran yang sudah diverifikasi</span></article>
    </section>
    @if($missing)<div class="notice">Harga jual untuk {{ $missing }} kelompok jenis/satuan belum tersedia lengkap. Estimasi penjualan dan selisih belum dapat dihitung.</div>@endif

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
            <div class="section-heading"><div><h2>Rekap per jenis sampah</h2><p>Jumlah diterima pada periode terpilih, bukan stok gudang atau penjualan aktual.</p></div>
                <a class="button" href="{{ url('/petugas/export').'?'.http_build_query($dates+['kind'=>'pengepul']) }}">Export XLSX Pengepul</a>
            </div>
            <div class="table-scroll"><table><thead><tr><th>Jenis</th><th>Jumlah</th><th>Nilai beli</th><th>Estimasi jual</th><th>Estimasi selisih</th></tr></thead><tbody>
            @forelse($groups as $g)<tr><td>{{ $g->nama_jenis }}</td><td>{{ number_format($g->jumlah,2,',','.') }} {{ $g->satuan }}</td><td>Rp{{ number_format($g->beli,0,',','.') }}</td><td>{{ $g->missing ? 'Belum tersedia' : 'Rp'.number_format($g->jual,0,',','.') }}</td><td>{{ $g->missing ? 'Belum tersedia' : 'Rp'.number_format($g->jual-$g->beli,0,',','.') }}</td></tr>
            @empty<tr><td colspan="5">Belum ada setoran selesai pada periode ini. Pilih periode lain untuk melihat rekap.</td></tr>@endforelse
            </tbody></table></div>
            <div class="total-strip"><span>Total estimasi jual</span><strong>{{ $estimate }}</strong></div>
            <div class="total-strip"><span>Total estimasi selisih</span><strong>{{ $empty ? 'Belum ada setoran' : ($sale === null ? 'Belum tersedia' : 'Rp'.number_format($sale-$summary->beli,0,',','.')) }}</strong></div>
        </section>
    @endif
    <footer>Bank Sampah Rumah Hijau · Data PostgreSQL</footer>
</main>
</body>
</html>
