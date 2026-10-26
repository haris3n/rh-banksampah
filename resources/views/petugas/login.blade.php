<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Masuk Petugas · Rumah Hijau</title>
    <link rel="stylesheet" href="{{ asset('css/petugas-login.css') }}">
    <script src="{{ asset('js/petugas-login.js') }}" defer></script>
</head>
<body>
<main class="login-shell">
    <section class="identity" aria-label="Bank Sampah Rumah Hijau">
        <div class="brand"><img src="{{ route('petugas.logo') }}" alt="Logo Bank Sampah Rumah Hijau" width="64" height="64"><div><strong>Rumah Hijau</strong><span>Bank Sampah · Indramayu</span></div></div>
        <div class="identity-message">
            <div class="leaf-mark" aria-hidden="true">@svg('heroicon-o-arrow-path')</div>
            <h1>Setoran tercatat.<br>Manfaat berkelanjutan.</h1>
            <p>Tempat petugas mencatat sampah terpilah, memeriksa setoran, dan menjaga tabungan nasabah.</p>
            <div class="workflow" aria-label="Alur pelayanan"><span>Catat setoran</span><span aria-hidden="true">→</span><span>Verifikasi</span><span aria-hidden="true">→</span><span>Saldo masuk</span></div>
        </div>
        <address>GLAYEM VILLAGE No.25–26, Sindang<br>Kabupaten Indramayu, Jawa Barat</address>
    </section>
    <section class="entry" aria-labelledby="login-title">
        <div class="entry-inner">
            <span class="portal-label">@svg('heroicon-o-identification') Portal Petugas</span>
            <h2 id="login-title">Selamat datang kembali</h2>
            <p class="intro">Masuk untuk memulai pelayanan di Rumah Hijau.</p>
            @if($errors->any())
                <div class="login-error" role="alert"><strong>Belum berhasil masuk</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form id="login-form" method="post" action="{{ url('/petugas/login') }}">
                @csrf
                <label for="email">Email petugas</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="username" placeholder="nama@contoh.com" @if($errors->has('email')) aria-invalid="true" @endif>
                <label for="password">Password</label>
                <div class="password-field"><input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Masukkan password"><button type="button" id="toggle-password" aria-controls="password" aria-pressed="false" hidden>Tampilkan</button></div>
                <p class="account-help">Lupa password? Hubungi admin Rumah Hijau untuk mengatur ulang akun Anda.</p>
                <button class="submit-button" id="login-submit" type="submit"><span id="submit-label">Masuk ke Dashboard</span>@svg('heroicon-o-arrow-right')</button>
                <p id="login-status" class="login-status" role="status" aria-live="polite"></p>
            </form>
            <div class="access-note">@svg('heroicon-o-lock-closed')<p>Akses khusus petugas aktif. Akun dibuat oleh admin Rumah Hijau.</p></div>
        </div>
        <footer>Bank Sampah Rumah Hijau</footer>
    </section>
</main>
</body>
</html>
