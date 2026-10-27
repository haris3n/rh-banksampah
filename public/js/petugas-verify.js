(() => {
    const form = document.getElementById('verify-setoran-form');
    const dialog = document.getElementById('verify-setoran-dialog');
    const confirm = document.getElementById('verify-confirm');
    const back = document.getElementById('verify-back');
    let sending = false;
    form.addEventListener('submit', event => {
        event.preventDefault();
        if (!sending && !dialog.open) dialog.showModal();
    });
    back.addEventListener('click', () => dialog.close());
    dialog.addEventListener('cancel', event => { if (sending) event.preventDefault(); });
    confirm.addEventListener('click', () => {
        if (sending) return;
        sending = true;
        confirm.disabled = back.disabled = true;
        confirm.textContent = 'Memverifikasi…';
        document.getElementById('verify-progress').textContent = 'Menunggu konfirmasi server. Jangan kirim ulang transaksi.';
        form.submit();
    });
    window.addEventListener('pageshow', () => {
        sending = false;
        confirm.disabled = back.disabled = false;
        confirm.textContent = 'Ya, Verifikasi Setoran';
        document.getElementById('verify-progress').textContent = '';
        if (dialog.open) dialog.close();
    });
})();
