<aside class="sidebar">
<a class="brand" href="/petugas/dashboard"><img src="{{ route('petugas.logo') }}" width="40" height="40" alt="Logo Rumah Hijau"><span><strong>Rumah Hijau</strong><small>Portal Petugas</small></span></a>
<nav aria-label="Navigasi petugas">
@foreach([
['/petugas/dashboard','Dashboard','heroicon-o-squares-2x2',request()->is('petugas/dashboard')],
['/petugas/setoran-baru','Setoran Baru','heroicon-o-document-plus',request()->is('petugas/setoran-baru','petugas/setoran/*/edit')],
['/petugas','Transaksi Hari Ini','heroicon-o-clipboard-document-list',request()->is('petugas')],
['/petugas/nasabah','Cari Nasabah','heroicon-o-user-group',request()->is('petugas/nasabah','petugas/nasabah/*')],
['/petugas/pengepul','Data untuk Pengepul','heroicon-o-scale',request()->is('petugas/pengepul')]
] as [$url,$label,$icon,$active])
<a href="{{ $url }}" @class(['nav-link','active'=>$active]) @if($active) aria-current="page" @endif>@svg($icon) {{ $label }}</a>
@endforeach
</nav>
@auth<div class="sidebar-note"><small>Petugas aktif</small><strong>{{ auth()->user()->nama }}</strong><p>Jadwal setor belum diumumkan</p><form action="/petugas/logout" method="post">@csrf<button class="sidebar-button">Keluar</button></form></div>@endauth
</aside>
