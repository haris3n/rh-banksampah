(() => {
    let pending;
    const status = document.createElement('div');
    status.className = 'report-feedback';
    status.setAttribute('role', 'status');
    status.hidden = true;
    document.body.append(status);
    async function update(url, push = true) {
        pending?.abort();
        const controller = new AbortController();
        pending = controller;
        const main = document.querySelector('main');
        main.setAttribute('aria-busy', 'true');
        status.hidden = false;
        status.textContent = 'Memperbarui rekap…';
        try {
            const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'text/html' } });
            if (response.redirected || !response.ok) throw new Error();
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const replacement = doc.querySelector('main');
            if (!replacement?.querySelector('[data-report-form]')) throw new Error();
            if (controller !== pending) return;
            main.replaceWith(replacement);
            if (push) history.pushState(null, '', url);
            document.title = doc.title;
            const heading = replacement.querySelector('h1');
            heading.tabIndex = -1;
            heading.focus({ preventScroll: true });
            status.textContent = 'Rekap diperbarui. Export mengikuti periode ini.';
        } catch (error) {
            if (error.name !== 'AbortError') status.textContent = 'Rekap gagal diperbarui. Data sebelumnya tetap ditampilkan. Periksa rentang tanggal, koneksi, atau masuk kembali.';
        } finally {
            if (controller === pending) document.querySelector('main').removeAttribute('aria-busy');
        }
    }
    document.addEventListener('click', event => {
        const link = event.target.closest('[data-report-link]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;
        event.preventDefault(); update(link.href);
    });
    document.addEventListener('submit', event => {
        if (!event.target.matches('[data-report-form]')) return;
        event.preventDefault();
        const data = new FormData(event.target);
        if (data.get('from') > data.get('to')) {
            status.hidden = false; status.textContent = 'Tanggal akhir harus sama atau setelah tanggal awal.'; return;
        }
        update(location.pathname + '?' + new URLSearchParams(data));
    });
    window.addEventListener('popstate', () => update(location.href, false));
})();
