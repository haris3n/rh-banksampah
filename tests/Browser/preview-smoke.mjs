// No added dependencies. Run against a dedicated Chrome debugging profile only.
// node tests/Browser/preview-smoke.mjs (preview :8001, Chrome CDP :9223)
import assert from 'node:assert/strict';

const targets = await fetch('http://127.0.0.1:9223/json').then(r => r.json());
const target = targets.find(t => t.type === 'page');
assert(target, 'Start a dedicated Chrome debugging instance on port 9223.');
const socket = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve, reject) => {
    socket.addEventListener('open', resolve, { once: true });
    socket.addEventListener('error', reject, { once: true });
});
let sequence = 0;
const pending = new Map();
const errors = [];
socket.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails);
    if (pending.has(message.id)) {
        const { resolve, reject, timer } = pending.get(message.id);
        clearTimeout(timer);
        pending.delete(message.id);
        message.error ? reject(new Error(JSON.stringify(message.error))) : resolve(message.result);
    }
});
function send(method, params = {}) {
    return new Promise((resolve, reject) => {
        const id = ++sequence;
        const timer = setTimeout(() => { pending.delete(id); reject(new Error('CDP timeout: '+method)); }, 15000);
        pending.set(id, { resolve, reject, timer });
        socket.send(JSON.stringify({ id, method, params }));
    });
}
async function evaluate(expression) {
    const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    assert(!result.exceptionDetails, JSON.stringify(result.exceptionDetails));
    return result.result.value;
}
async function navigate(page) {
    const url = 'http://127.0.0.1:8001/preview/petugas/'+page;
    await send('Page.navigate', { url });
    for (let i = 0; i < 100; i++) {
        if (await evaluate(`location.href === ${JSON.stringify(url)} && document.readyState === 'complete' && Boolean(document.getElementById('page-subtitle')?.textContent.includes('Siap melayani'))`)) return;
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    throw new Error('Preview did not finish loading: '+page);
}
async function click(selector) {
    await evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
}
async function fill(selector, value) {
    await evaluate(`(() => { const el=document.querySelector(${JSON.stringify(selector)});el.value=${JSON.stringify(value)};el.dispatchEvent(new Event('input',{bubbles:true})); })()`);
}
try {
    await send('Runtime.enable');
    await navigate('dashboard');
    await evaluate(`localStorage.removeItem('rh-petugas-preview-v2')`);
    await navigate('setoran-baru');
    await click('[data-customer="1"]');
    await fill('[data-weight="0"]', '1.25');
    assert.equal(await evaluate(`document.querySelectorAll('[data-type="0"] option').length`), 14);
    const expected = [
        ['Emberan',1600,'kg'],['Kaleng',1600,'kg'],['Jerigen',1600,'kg'],['Duplex',400,'kg'],
        ['Le Mineral',800,'kg'],['Kardus',1300,'kg'],['Kertas HVS',1200,'kg'],['Buku',800,'kg'],
        ['Botol Bersih',3200,'kg'],['Tutup Botol',2000,'kg'],['Ringe Gelas Plastik',1600,'kg'],
        ['Minyak Jelantah Bersih',4000,'liter'],['Minyak Jelantah Endapan',1500,'liter'],['Kaca',240,'kg'],
    ];
    for (const [index,[name,price,unit]] of expected.entries()) {
        await evaluate(`(() => { const s=document.querySelector('[data-type="0"]');s.value='${index+1}';s.dispatchEvent(new Event('change',{bubbles:true})); })()`);
        await fill('[data-weight="0"]','1');
        assert.equal(await evaluate(`document.querySelector('[data-type="0"] option:checked').textContent`),name);
        assert.equal(await evaluate(`document.getElementById('form-total').textContent`),'Rp'+new Intl.NumberFormat('id-ID').format(price));
        assert.equal(await evaluate(`document.querySelector('[data-weight="0"]').parentElement.textContent.trim()`),unit==='liter'?'Volume (liter)':'Berat (kg)');
    }
    await evaluate(`(() => {const s=document.querySelector('[data-type="0"]');s.value='1';s.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    await fill('[data-weight="0"]','1.25');
    assert.equal(await evaluate(`document.querySelector('.brand img').naturalWidth > 0`), true);
    assert.equal(await evaluate(`document.getElementById('form-total').textContent`), 'Rp2.000');
    await click('#add-item');
    await evaluate(`const select=document.querySelector('[data-type="1"]');select.value='12';select.dispatchEvent(new Event('change',{bubbles:true}));`);
    await fill('[data-weight="1"]', '2.50');
    assert.equal(await evaluate(`document.getElementById('form-total').textContent`), 'Rp12.000');
    assert.equal(await evaluate(`document.getElementById('form-weight').textContent`), '1,25 kg + 2,5 liter');
    await evaluate(`document.getElementById('deposit-form').requestSubmit()`);
    let deposit = await evaluate(`JSON.parse(localStorage.getItem('rh-petugas-preview-v2')).at(-1)`);
    assert.equal(deposit.status, 'Menunggu Verifikasi');
    assert.equal(deposit.items[0].buy, 1600);
    assert.equal(deposit.items[1].unit, 'liter');
    assert.equal(deposit.items[1].buy, 4000);
    assert.equal(deposit.items[1].sell, null);
    await click('[data-confirm]');
    await click('[data-verify]');
    assert.equal(await evaluate(`document.getElementById('dialog-title').textContent`), 'Struk Setoran · Demo');
    assert.equal(await evaluate(`document.querySelector('[data-verify]') === null`), true);
    assert.equal(await evaluate(`document.getElementById('dialog-content').textContent.includes('1,25 kg + 2,5 liter')`), true);
    deposit = await evaluate(`JSON.parse(localStorage.getItem('rh-petugas-preview-v2')).at(-1)`);
    assert.equal(deposit.status, 'Selesai');
    assert(deposit.verified);
    await navigate('transaksi');
    await click('[data-detail="DEMO-002"]');
    assert.equal(await evaluate(`document.querySelector('[data-confirm]') === null && document.querySelector('[data-cancel]') === null`), true);
    await navigate('pengepul');
    assert.equal(await evaluate(`document.getElementById('stat-value').textContent`), 'Belum tersedia');
    assert.equal(await evaluate(`document.getElementById('collector-margin').textContent`), 'Belum tersedia');
    assert.equal(await evaluate(`document.getElementById('collector-buy').textContent`), 'Rp30.720');
    assert.equal(await evaluate(`document.getElementById('stat-weight').textContent`), '12,95 kg + 2,5 liter');
    await navigate('nasabah');
    await fill('#customer-search', 'RH-DEMO-A7K2');
    assert.equal(await evaluate(`document.querySelectorAll('[data-customer]').length`), 1);
    await click('[data-customer="1"]');
    assert.equal(await evaluate(`document.getElementById('customer-detail').textContent.includes('Rp215.220')`), true);
    await fill('#customer-search', 'tidak-ada');
    assert.equal(await evaluate(`document.querySelectorAll('[data-customer]').length`), 0);
    await navigate('setoran-baru');
    await click('[data-customer="2"]');
    await evaluate(`const select=document.querySelector('[data-type="0"]');select.value='14';select.dispatchEvent(new Event('change',{bubbles:true}));`);
    await fill('[data-weight="0"]', '0.01');
    assert.equal(await evaluate(`document.getElementById('form-total').textContent`), 'Rp2');
    await evaluate(`document.getElementById('deposit-form').requestSubmit()`);
    let pendingDeposit = await evaluate(`JSON.parse(localStorage.getItem('rh-petugas-preview-v2')).at(-1)`);
    await navigate('setoran-baru?edit='+pendingDeposit.id);
    await fill('[data-weight="0"]', '2.5');
    await evaluate(`document.getElementById('deposit-form').requestSubmit()`);
    pendingDeposit = await evaluate(`JSON.parse(localStorage.getItem('rh-petugas-preview-v2')).at(-1)`);
    assert.equal(pendingDeposit.items[0].weight, 250);
    assert.equal(pendingDeposit.items[0].buy, 240, 'Weight edit preserves snapshot');
    await evaluate(`window.prompt = () => '   '`);
    await click('[data-cancel]');
    assert.equal(await evaluate(`JSON.parse(localStorage.getItem('rh-petugas-preview-v2')).at(-1).status`), 'Menunggu Verifikasi');
    await evaluate(`window.prompt = () => 'Nasabah membatalkan setoran'`);
    await click('[data-cancel]');
    assert.equal(await evaluate(`JSON.parse(localStorage.getItem('rh-petugas-preview-v2')).at(-1).status`), 'Dibatalkan');
    await navigate('pengepul');
    assert.equal(await evaluate(`document.getElementById('collector-buy').textContent`), 'Rp30.720', 'Cancelled deposit excluded');
    // A partially priced snapshot must not become a misleading complete total.
    await evaluate(`(() => {const ds=JSON.parse(localStorage.getItem('rh-petugas-preview-v2'));ds[0].items[0].sell=1800;localStorage.setItem('rh-petugas-preview-v2',JSON.stringify(ds));})()`);
    await navigate('pengepul');
    assert.equal(await evaluate(`document.getElementById('stat-value').textContent`),'Belum tersedia');
    assert.equal(await evaluate(`document.getElementById('collector-margin').textContent`),'Belum tersedia');
    await send('Emulation.setDeviceMetricsOverride', { width:390, height:844, deviceScaleFactor:1, mobile:true });
    for (const page of ['dashboard','setoran-baru','transaksi','nasabah','pengepul']) {
        await navigate(page);
        assert.equal(await evaluate(`document.documentElement.scrollWidth <= innerWidth`), true, page+' horizontal overflow');
    }
    assert.deepEqual(errors, [], 'Browser runtime errors');
    console.log('PASS: save/verify/receipt, edit snapshot, required cancellation reason, owner demo restriction, collector totals excluding cancelled, search, five mobile layouts; no browser exceptions.');
} finally {
    await send('Emulation.clearDeviceMetricsOverride').catch(() => {});
    socket.close();
}
