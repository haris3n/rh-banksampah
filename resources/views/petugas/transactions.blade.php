@php($statuses = [''=>'Semua','MENUNGGU_VERIFIKASI'=>'Menunggu','SELESAI'=>'Selesai','DIBATALKAN'=>'Dibatalkan'])
<section class="stats transaction-stats" aria-label="Ringkasan hasil filter">
    <article class="stat featured">@svg('heroicon-o-clipboard-document-list','metric-icon')<div><small>Total transaksi</small><strong>{{ $summary->transaksi }} <span>transaksi</span></strong><span>Sesuai pencarian dan filter</span></div></article>
    <article class="stat">@svg('heroicon-o-scale','metric-icon')<div><small>Sampah diterima</small><strong>{{ number_format($summary->kg,2,',','.') }} kg</strong><span>{{ number_format($summary->liter,2,',','.') }} liter · selesai</span></div></article>
    <article class="stat">@svg('heroicon-o-banknotes','metric-icon')<div><small>Nilai setoran selesai</small><strong>Rp{{ number_format($summary->nilai,0,',','.') }}</strong><span>Saldo masuk dari hasil filter</span></div></article>
</section>
<section class="card transaction-card">
    <form action="/petugas" method="get" class="transaction-filters">
        <div class="transaction-toolbar">
            <label class="transaction-search"><span class="sr-only">Cari nasabah, kode transaksi, atau nomor HP</span>@svg('heroicon-o-magnifying-glass')<input name="q" value="{{ request('q') }}" maxlength="100" placeholder="Cari nasabah, ID transaksi, no. HP…"></label>
            <div class="status-tabs" aria-label="Filter status">@foreach($statuses as $value=>$label)<a href="{{ url('/petugas').'?'.http_build_query(array_merge($filters,['status'=>$value])) }}" @class(['status-tab','selected'=>(request('status')??'')===$value]) @if((request('status')??'')===$value) aria-current="true" @endif>{{ $label }}</a>@endforeach</div>
        </div>
        <input type="hidden" name="status" value="{{ request('status') }}">
        <details class="transaction-period"><summary>Periode: {{ \Carbon\Carbon::parse($filters['from'])->format('d M Y') }} — {{ \Carbon\Carbon::parse($filters['to'])->format('d M Y') }} WIB · Ubah</summary><div class="toolbar filters"><label>Dari<input type="date" name="from" value="{{ $filters['from'] }}" required></label><label>Sampai<input type="date" name="to" value="{{ $filters['to'] }}" required></label><button class="button primary">Terapkan</button></div></details>
        <noscript><button class="button">Cari</button></noscript>
    </form>
    <div class="table-scroll"><table><thead><tr><th>ID transaksi</th><th>Nasabah</th><th>Sampah</th><th>Jumlah</th><th>Nilai tukar</th><th>Status</th><th></th></tr></thead><tbody>
    @forelse($records as $r)
        <tr><td><strong class="transaction-code">{{ $r->kode }}</strong><small class="cell-note">{{ \Carbon\Carbon::parse($r->diverifikasi_at??$r->created_at)->timezone('Asia/Jakarta')->format('d M Y H:i') }}</small></td><td>{{ $r->nama }}<small class="cell-note">{{ $r->no_hp ?: 'No. HP belum diisi' }}</small><details class="address-detail"><summary>Alamat</summary>{{ $r->alamat ?: 'Belum diisi' }}</details></td><td>{{ ($items[$r->id]??collect())->pluck('nama_jenis')->join(', ') }}</td><td>{{ number_format($r->total_kg,2,',','.') }} kg<small class="cell-note">{{ number_format($r->total_liter,2,',','.') }} liter</small></td><td><strong>Rp{{ number_format($r->total_nilai,0,',','.') }}</strong></td><td><span @class(['badge','waiting'=>$r->status==='MENUNGGU_VERIFIKASI','cancelled'=>$r->status==='DIBATALKAN'])>{{ $statuses[$r->status] }}</span></td><td><a class="button" href="/petugas/setoran/{{ $r->id }}">Cek Transaksi</a></td></tr>
    @empty
        <tr><td colspan="7"><div class="transaction-empty">@svg('heroicon-o-clipboard-document-list')<h2>Belum ada transaksi yang sesuai</h2><p>Ubah pencarian atau periode, atau mulai catat setoran nasabah.</p><a class="button primary" href="/petugas/setoran-baru">+ Setoran Baru</a></div></td></tr>
    @endforelse
    </tbody></table></div>
    <div class="transaction-pagination"><span>Menampilkan {{ $records->firstItem()??0 }}–{{ $records->lastItem()??0 }} dari {{ $records->total() }} transaksi</span><div class="actions">@if($records->previousPageUrl())<a class="button" href="{{ $records->previousPageUrl() }}">Sebelumnya</a>@endif @if($records->nextPageUrl())<a class="button" href="{{ $records->nextPageUrl() }}">Selanjutnya</a>@endif</div></div>
    <small class="transaction-help">Periode memakai waktu verifikasi untuk setoran selesai dan waktu pencatatan untuk status lain. Export memuat semua setoran selesai pada periode ini, tanpa filter pencarian/status.</small>
</section>
