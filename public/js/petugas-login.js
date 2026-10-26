(() => {
    const form = document.getElementById('login-form');
    const password = document.getElementById('password');
    const toggle = document.getElementById('toggle-password');
    const submit = document.getElementById('login-submit');
    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        const show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        toggle.textContent = show ? 'Sembunyikan' : 'Tampilkan';
        toggle.setAttribute('aria-pressed', String(show));
    });
    form.addEventListener('submit', event => {
        if (submit.disabled) { event.preventDefault(); return; }
        submit.disabled = true;
        document.getElementById('submit-label').textContent = 'Sedang masuk…';
        document.getElementById('login-status').textContent = 'Memeriksa akun petugas Anda.';
    });
    window.addEventListener('pageshow', () => {
        submit.disabled = false;
        password.type = 'password';
        toggle.textContent = 'Tampilkan';
        toggle.setAttribute('aria-pressed', 'false');
        document.getElementById('submit-label').textContent = 'Masuk ke Dashboard';
        document.getElementById('login-status').textContent = '';
    });
})();
