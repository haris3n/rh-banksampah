/* Isolated UI demonstration. Never use this browser store as a financial ledger. */
(() => {
    'use strict';
    const $ = id => document.getElementById(id);
    const page = document.body.dataset.page;
    const money = n => 'Rp' + new Intl.NumberFormat('id-ID').format(n);
    const kg = n => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(n / 100) + ' kg';
    const escape = s => String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    const day = date => new Intl.DateTimeFormat('en-CA', { timeZone:'Asia/Jakarta', year:'numeric', month:'2-digit', day:'2-digit' }).format(new Date(date));
    const today = day(new Date());
    const catalog = [
        { id:1, name:'Botol Plastik PET Bersih', buy:5000, sell:5200 },
        { id:2, name:'Kertas & Kardus', buy:2500, sell:2700 },
        { id:3, name:'Kaleng Aluminium', buy:15000, sell:15200 },
        { id:4, name:'Kaca', buy:1500, sell:1700 },
    ];
    const customers = [
        { id:1, name:'Siti Amelia', phone:'081234567890', address:'Jl. Danau Toba No. 12, Malang', qr:'RH-DEMO-A7K2', balance:184500 },
        { id:2, name:'Budi Santoso', phone:'085712345678', address:'Jl. Melati No. 8, Malang', qr:'RH-DEMO-P9M4', balance:52000 },
        { id:3, name:'Rina Wulandari', phone:'081398765432', address:'Jl. Mawar No. 5, Malang', qr:'RH-DEMO-X2B8', balance:34000 },
    ];
    const key = 'rh-petugas-preview-v1';
    const snapshot = (type, weight) => ({ ...catalog.find(c => c.id === Number(type)), weight });
    const subtotal = item => Math.floor((item.weight * item.buy + 50) / 100);
    const total = d => d.items.reduce((n, item) => n + subtotal(item), 0);
    const weight = d => d.items.reduce((n, item) => n + item.weight, 0);
    const seed = () => [
        { id:'DEMO-001', customer:1, owner:1, status:'Selesai', created:new Date().toISOString(), verified:new Date().toISOString(), items:[snapshot(1,450), snapshot(2,720)] },
        { id:'DEMO-002', customer:2, owner:2, status:'Menunggu Verifikasi', created:new Date().toISOString(), items:[snapshot(3,210)] },
    ];
    let deposits;
    try { deposits = JSON.parse(localStorage.getItem(key)) || seed(); if (!Array.isArray(deposits)) deposits = seed(); }
    catch { deposits = seed(); }
    let selected = Number(new URLSearchParams(location.search).get('nasabah')) || null;
    let editing = null;
    let formItems = [snapshot(1, 0)];
    const notify = text => { $('feedback').textContent = text; $('feedback').hidden = false; };
    const save = () => {
        try { localStorage.setItem(key, JSON.stringify(deposits)); }
        catch { notify('Penyimpanan browser tidak tersedia. Data demo hanya bertahan di halaman ini.'); }
    };
    const customer = id => customers.find(c => c.id === id);
    const inPeriod = d => {
        const date = day(d.verified || d.created);
        return (!$('from-date')?.value || date >= $('from-date').value) && (!$('to-date')?.value || date <= $('to-date').value);
    };
    const balance = c => c.balance + deposits.filter(d => d.customer === c.id && d.status === 'Selesai').reduce((sum, d) => sum + total(d), 0);
    function renderCustomers() {
        if (!$('customer-results')) return;
        const query = $('customer-search').value.toLowerCase().trim();
        const matches = customers.filter(c => [c.name,c.phone,c.qr].some(v => v.toLowerCase().includes(query)));
        $('customer-results').innerHTML = matches.map(c => `<button type="button" class="customer-option ${selected === c.id ? 'selected' : ''}" data-customer="${c.id}"><span class="avatar">${escape(c.name.split(' ').map(n=>n[0]).join(''))}</span><span><strong>${escape(c.name)}</strong><small>${c.qr} · ${c.phone}</small></span></button>`).join('') || '<p class="empty">Nasabah tidak ditemukan. Periksa nama, nomor HP, atau kode QR.</p>';
        const c = customer(selected);
        $('selected-customer').hidden = !c;
        if (!c) return;
        $('selected-customer').innerHTML = `<strong>${escape(c.name)}</strong><p>${c.phone}<br>${escape(c.address)}</p>`;
        if ($('customer-detail')) {
            const history = deposits.filter(d => d.customer === c.id);
            $('customer-detail').innerHTML = `<h2>${escape(c.name)}</h2><span class="badge">Aktif · contoh</span><dl class="details-list"><dt>No. HP</dt><dd>${c.phone}</dd><dt>Alamat</dt><dd>${escape(c.address)}</dd><dt>Saldo demo</dt><dd><strong>${money(balance(c))}</strong></dd><dt>Total berat selesai</dt><dd>${kg(history.filter(d=>d.status==='Selesai').reduce((n,d)=>n+weight(d),0))}</dd></dl><a class="button primary" href="setoran-baru?nasabah=${c.id}">Pilih untuk Setoran Baru</a><h2 style="margin-top:24px">Riwayat Setoran</h2>${history.map(d=>`<p><button class="text-button" data-detail="${d.id}">${d.id}</button> · ${escape(d.status)} · ${money(total(d))}</p>`).join('') || '<p>Belum ada setoran.</p>'}`;
        }
    }
    function renderItems() {
        if (!$('items')) return;
        $('items').innerHTML = formItems.map((item, i) => `<div class="item-row"><label>Jenis sampah ${i+1}<select data-type="${i}">${catalog.map(c=>`<option value="${c.id}" ${c.id===item.id?'selected':''}>${escape(c.name)}</option>`).join('')}</select></label><label>Berat (kg)<input data-weight="${i}" type="number" inputmode="decimal" min="0.01" max="10000" step="0.01" required value="${item.weight ? item.weight / 100 : ''}"></label><label>${money(item.buy)}/kg<output id="subtotal-${i}">${money(subtotal(item))}</output></label><button type="button" class="button" data-remove="${i}" aria-label="Hapus jenis ${i+1}" ${formItems.length===1?'disabled':''}>×</button></div>`).join('');
        renderTotals();
    }
    function renderTotals() {
        if (!$('form-total')) return;
        formItems.forEach((item,i)=> { if ($('subtotal-'+i)) $('subtotal-'+i).textContent = money(subtotal(item)); });
        $('form-weight').textContent = kg(weight({ items:formItems }));
        $('form-total').textContent = money(total({ items:formItems }));
    }
    function renderReports() {
        const completed = deposits.filter(d=>d.status==='Selesai' && inPeriod(d));
        const bought = completed.reduce((n,d)=>n+total(d),0);
        const sold = completed.reduce((n,d)=>n+d.items.reduce((v,i)=>v+Math.floor((i.weight*i.sell+50)/100),0),0);
        if ($('stat-count')) {
            $('stat-count').textContent = completed.length + ' transaksi';
            $('stat-weight').textContent = kg(completed.reduce((n,d)=>n+weight(d),0));
            $('stat-value').textContent = money(page==='pengepul'?sold:bought);
            if (page==='pengepul') { $('value-label').textContent='Estimasi nilai jual'; $('value-note').textContent='Dari harga jual snapshot'; }
        }
        if ($('transactions')) {
            const query = $('transaction-search').value.toLowerCase();
            const status = $('status-filter').value;
            const rows = deposits.filter(d=>inPeriod(d) && (!status || d.status===status) && (d.id+' '+customer(d.customer).name).toLowerCase().includes(query));
            $('transactions').innerHTML = [...rows].reverse().map(d=>`<tr><td>${d.id}</td><td>${escape(customer(d.customer).name)}<br><small>${customer(d.customer).phone} · Petugas ${d.owner}</small></td><td>${kg(weight(d))}</td><td><strong>${money(total(d))}</strong></td><td><span class="badge ${d.status==='Menunggu Verifikasi'?'waiting':d.status==='Dibatalkan'?'cancelled':''}">${d.status}</span></td><td><button class="button" data-detail="${d.id}">Cek Transaksi</button></td></tr>`).join('') || '<tr><td class="empty" colspan="6">Tidak ada transaksi sesuai filter.</td></tr>';
            $('transaction-count').textContent = rows.length + ' transaksi dalam periode ini.';
        }
        if ($('collector-rows')) {
            const groups = new Map();
            completed.flatMap(d=>d.items).forEach(i=> { const g=groups.get(i.id)||{name:i.name,weight:0,buy:0,sell:0,details:[]}; g.weight+=i.weight; g.buy+=subtotal(i); g.sell+=Math.floor((i.weight*i.sell+50)/100); g.details.push(`${kg(i.weight)} × ${money(i.sell)}/kg`); groups.set(i.id,g); });
            $('collector-rows').innerHTML = [...groups.values()].map(g=>`<tr><td><strong>${escape(g.name)}</strong><details><summary>Rincian snapshot</summary>${g.details.map(v=>`<p>${escape(v)}</p>`).join('')}</details></td><td>${kg(g.weight)}</td><td>${money(g.buy)}</td><td><strong>${money(g.sell)}</strong></td><td>${money(g.sell-g.buy)}</td></tr>`).join('') || '<tr><td colspan="5" class="empty">Belum ada setoran selesai pada periode ini.</td></tr>';
            $('collector-margin').textContent=money(sold-bought);
        }
    }
    function detail(id, confirm = false) {
        const d=deposits.find(d=>d.id===id); if(!d) return;
        const c=customer(d.customer);
        $('dialog-title').textContent=confirm?'Konfirmasi Verifikasi':d.status==='Selesai'?'Struk Setoran · Demo':'Detail Setoran';
        $('dialog-content').innerHTML=`<span class="badge">${d.status}</span><h3>${d.id} · ${escape(c.name)}</h3><p>${c.phone}<br>${escape(c.address)}</p><p>${new Date(d.verified||d.created).toLocaleString('id-ID',{timeZone:'Asia/Jakarta'})} WIB · Petugas ${d.owner}</p><div class="table-scroll"><table><thead><tr><th>Jenis</th><th>Berat</th><th>Harga beli/kg</th><th>Subtotal</th></tr></thead><tbody>${d.items.map(i=>`<tr><td>${escape(i.name)}</td><td>${kg(i.weight)}</td><td>${money(i.buy)}</td><td>${money(subtotal(i))}</td></tr>`).join('')}</tbody></table></div><div class="total-strip"><strong>${kg(weight(d))}</strong><strong>${money(total(d))}</strong></div>${d.reason?`<p>Alasan pembatalan: ${escape(d.reason)}</p>`:''}${confirm?'<p>Pastikan jenis dan berat sudah benar. Setelah verifikasi, setoran terkunci.</p>':''}<p class="muted">Bukti simulasi antarmuka, bukan transaksi atau saldo sebenarnya.</p>`;
        const allowed=d.owner===1 && d.status==='Menunggu Verifikasi';
        $('dialog-actions').innerHTML=allowed?(confirm?`<button class="button primary" data-verify="${id}">Ya, Verifikasi Setoran Demo</button>`:`<button class="button primary" data-confirm="${id}">Verifikasi</button><a class="button" href="setoran-baru?edit=${id}">Edit</a><button class="button danger" data-cancel="${id}">Batalkan</button>`):'<p class="muted">Setoran selesai/dibatalkan terkunci. Hanya pencatat yang dapat mengubah setoran menunggu.</p>';
        if(!$('deposit-dialog').open) $('deposit-dialog').showModal();
    }
    document.addEventListener('click',event=> {
        const button=event.target.closest('[data-customer],[data-remove],[data-detail],[data-confirm],[data-verify],[data-cancel]'); if(!button)return;
        const a=button.dataset;
        if(a.customer){ selected=Number(a.customer); renderCustomers(); }
        if(a.remove){formItems.splice(Number(a.remove),1);renderItems();}
        if(a.detail) detail(a.detail);
        if(a.confirm) detail(a.confirm,true);
        if(a.verify || a.cancel){
            const d=deposits.find(d=>d.id===(a.verify||a.cancel));
            if(!d || d.owner!==1 || d.status!=='Menunggu Verifikasi')return;
            if(a.cancel){const reason=prompt('Alasan pembatalan (wajib):');if(!reason?.trim())return;d.reason=reason.trim();d.status='Dibatalkan';}
            else {d.status='Selesai';d.verified=new Date().toISOString();}
            save();renderReports();renderCustomers();detail(d.id);
        }
    });
    $('items')?.addEventListener('input',e=>{if(e.target.dataset.weight!==undefined){const index=Number(e.target.dataset.weight);formItems[index].weight=Math.round(Number(e.target.value)*100);renderTotals();}});
    $('items')?.addEventListener('change',e=>{if(e.target.dataset.type!==undefined){const index=Number(e.target.dataset.type);formItems[index]=snapshot(e.target.value,formItems[index].weight);renderItems();}});
    $('add-item')?.addEventListener('click',()=>{formItems.push(snapshot(1,0));renderItems();});
    $('clear-form')?.addEventListener('click',()=>{editing=null;formItems=[snapshot(1,0)];$('form-status').textContent='Belum disimpan';renderItems();});
    $('deposit-form')?.addEventListener('submit',e=>{
        e.preventDefault();
        if(!customer(selected)){notify('Pilih nasabah sebelum menyimpan setoran.');return;}
        if(formItems.some(i=>!Number.isInteger(i.weight)||i.weight<1||i.weight>1000000)){notify('Berat harus antara 0,01 dan 10.000 kg.');return;}
        if(new Set(formItems.map(i=>i.id)).size!==formItems.length){notify('Gabungkan berat untuk jenis sampah yang sama.');return;}
        let d=editing?deposits.find(d=>d.id===editing):null;
        if(editing && (!d||d.owner!==1||d.status!=='Menunggu Verifikasi')){notify('Setoran tidak dapat diedit.');return;}
        if(d){d.items=structuredClone(formItems);d.customer=selected;}
        else{d={id:'DEMO-'+crypto.randomUUID().slice(0,8).toUpperCase(),customer:selected,owner:1,status:'Menunggu Verifikasi',created:new Date().toISOString(),items:structuredClone(formItems)};deposits.push(d);}
        save();editing=null;formItems=[snapshot(1,0)];renderItems();renderReports();$('form-status').textContent='Belum disimpan';notify('Setoran demo disimpan. Periksa detail, lalu verifikasi secara terpisah.');detail(d.id);
    });
    $('customer-search')?.addEventListener('input',renderCustomers);
    ['transaction-search','status-filter','from-date','to-date'].forEach(id=>$(id)?.addEventListener('input',renderReports));
    ['from-date','to-date'].forEach(id=>{if($(id))$(id).value=today;});
    $('close-dialog').addEventListener('click',()=>$('deposit-dialog').close());
    $('reset-demo').addEventListener('click',()=>{if(confirm('Reset seluruh data contoh di browser ini?')){localStorage.removeItem(key);location.href=location.pathname;}});
    const editId=new URLSearchParams(location.search).get('edit');
    if(editId && $('deposit-form')){const d=deposits.find(d=>d.id===editId);if(d?.owner===1&&d.status==='Menunggu Verifikasi'){editing=d.id;selected=d.customer;formItems=structuredClone(d.items);$('form-status').textContent='Edit · Menunggu Verifikasi';}else notify('Setoran tidak ditemukan atau tidak dapat diedit.');}
    $('page-subtitle').textContent=new Date().toLocaleDateString('id-ID',{timeZone:'Asia/Jakarta',weekday:'long',day:'numeric',month:'long',year:'numeric'})+' · Siap melayani setoran hari ini';
    renderCustomers();renderItems();renderReports();
})();
