(() => {
    'use strict';
    const box=document.getElementById('server-items');
    if(!box)return;
    const money=n=>'Rp'+new Intl.NumberFormat('id-ID').format(n);
    const number=n=>new Intl.NumberFormat('id-ID',{maximumFractionDigits:2}).format(n/100);
    function prepareRow(row){
        const select=row.querySelector('select');
        for(const option of select.options){if(option.value)option.textContent=option.textContent.split(' · Rp')[0];}
        if(!row.querySelector('[data-price-display]')){
            const label=document.createElement('label');label.textContent='Harga/satuan (otomatis)';
            const output=document.createElement('output');output.className='price-field';output.dataset.priceDisplay='';label.append(output);
            row.insertBefore(label,row.querySelector('[data-subtotal]').parentElement);
        }
    }
    let index=Math.max(...Array.from(box.querySelectorAll('select'),s=>Number(s.name.match(/\[(\d+)\]/)[1])))+1;
    function calculate(){
        let total=0;const units={kg:0,liter:0};const selected=new Set();
        box.querySelectorAll('.item-row').forEach(row=>{
            prepareRow(row);
            const s=row.querySelector('select'), input=row.querySelector('input');
            const option=s.selectedOptions[0], unit=option.dataset.unit;
            const text=(input.value||'').trim().replace(',','.');
            const parts=text.split('.');
            const valid=/^\d+(\.\d+)?$/.test(text) && Number(text) > 0;
            const amount=valid ? Number(parts[0])*100 + Number((parts[1]||'').slice(0,2).padEnd(2,'0')) : 0;
            const value=Math.floor((amount*Number(option.dataset.price||0)+50)/100);
            row.querySelector('[data-subtotal]').textContent=money(value);
            row.querySelector('[data-price-display]').textContent=unit?money(Number(option.dataset.price))+' / '+unit:'—';
            row.querySelector('[data-quantity-label]').textContent=unit==='liter'?'Volume (liter)':'Berat (kg)';
            s.setCustomValidity(s.value&&selected.has(s.value)?'Gabungkan jumlah untuk jenis yang sama.':'');selected.add(s.value);
            row.querySelector('[data-remove]').disabled=box.children.length===1;
            if(unit)units[unit]+=amount;total+=value;
        });
        document.getElementById('live-total').textContent=money(total);
        document.getElementById('live-quantity').textContent=number(units.kg)+' kg + '+number(units.liter)+' liter';
    }
    box.addEventListener('input',event=>{
        const input=event.target;
        if(input.matches('input[name$="[jumlah]"]')){
            let val=input.value;
            const dotIndex=val.indexOf('.');
            if(dotIndex!==-1 && val.length > dotIndex + 3){
                input.value=val.slice(0, dotIndex + 3);
            }
        }
        calculate();
    });
    box.addEventListener('change',event=>{if(event.target.tagName==='SELECT'){const s=event.target,unit=s.selectedOptions[0].dataset.unit;if(s.dataset.lastUnit&&s.dataset.lastUnit!==unit)s.closest('.item-row').querySelector('input').value='';s.dataset.lastUnit=unit||'';}calculate();});
    box.querySelectorAll('select').forEach(s=>s.dataset.lastUnit=s.selectedOptions[0].dataset.unit||'');
    box.addEventListener('click',event=>{if(event.target.closest('[data-remove]')&&box.children.length>1){event.target.closest('.item-row').remove();calculate();}});
    document.getElementById('server-add').onclick=()=>{if(box.children.length>=50)return;const row=box.firstElementChild.cloneNode(true);row.querySelectorAll('[name]').forEach(el=>{el.name=el.name.replace(/items\[\d+\]/,'items['+index+']');el.value='';});row.querySelector('select').dataset.lastUnit='';index++;box.append(row);calculate();};
    const customer=document.getElementById('live-customer');
    function identity(){const option=customer.selectedOptions[0];const chosen=Boolean(option?.value);document.getElementById('identity-card').hidden=!chosen;document.getElementById('customer-name').textContent=chosen?option.textContent:'';document.getElementById('customer-initials').textContent=chosen?option.textContent.trim().split(/\s+/).slice(0,2).map(s=>s[0]).join(''):'';document.getElementById('live-customer-detail').textContent=chosen?[option.dataset.phone,option.dataset.address].filter(Boolean).join(' · '):'';}
    customer.addEventListener('change',identity);
    let sequence=0;
    async function search(){
        const seq=++sequence,status=document.getElementById('search-status');status.textContent='Mencari…';
        try{const response=await fetch('/petugas/nasabah-search?q='+encodeURIComponent(document.getElementById('customer-query').value),{headers:{Accept:'application/json'}});if(!response.ok)throw new Error();const rows=await response.json();if(seq!==sequence)return;
            const current=customer.value,previous=customer.selectedOptions[0]?.cloneNode(true);customer.replaceChildren(new Option('Pilih nasabah',''));
            rows.forEach(c=>{const o=new Option(c.nama,String(c.id));o.dataset.phone=c.no_hp||'';o.dataset.address=c.alamat||'';customer.add(o);});
            if(current&&!rows.some(c=>String(c.id)===current)&&previous)customer.add(previous);
            customer.value=rows.length===1?String(rows[0].id):current;
            status.textContent=rows.length?`${rows.length} hasil (maksimal 50).`:'Nasabah aktif tidak ditemukan.';identity();
        }catch{if(seq===sequence)status.textContent='Pencarian gagal. Periksa koneksi atau masuk kembali. Isian setoran tetap tersimpan di halaman ini.';}
    }
    document.getElementById('search-customers').onclick=search;
    let searchTimer;
    document.getElementById('customer-query').addEventListener('input',()=>{
        clearTimeout(searchTimer);
        sequence++;
        if(!document.getElementById('customer-query').value.trim()){
            document.getElementById('search-status').textContent='Ketik nama, nomor HP, atau kode QR nasabah.';
            return;
        }
        searchTimer=setTimeout(search,350);
    });
    document.getElementById('customer-query').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();search();}});
    let scanner=null,reading=false;
    const start=document.getElementById('start-camera'),stop=document.getElementById('stop-camera'),status=document.getElementById('camera-status');
    async function stopCamera(){const active=scanner;scanner=null;if(active){try{await active.stop();}catch{}try{await active.clear();}catch{}}start.disabled=false;stop.hidden=true;}
    start.onclick=async()=>{
        if(!window.isSecureContext){status.textContent='Kamera membutuhkan HTTPS atau localhost. Gunakan kode manual.';return;}
        start.disabled=true;reading=false;status.textContent='Meminta akses kamera…';
        try{
            if(!window.Html5Qrcode){await new Promise((resolve,reject)=>{const script=document.createElement('script');script.src='/vendor/html5-qrcode.min.js';script.onload=resolve;script.onerror=reject;document.head.append(script);});}
            scanner=new Html5Qrcode('qr-reader');
            await scanner.start({facingMode:'environment'},{fps:8,qrbox:220},async code=>{if(reading)return;reading=true;document.getElementById('customer-query').value=code.trim();await stopCamera();status.textContent='QR terbaca. Memeriksa nasabah…';await search();},()=>{});
            stop.hidden=false;status.textContent='Arahkan QR ke kamera.';
        }catch{await stopCamera();status.textContent='Kamera tidak dapat dibuka. Periksa izin kamera atau gunakan kode manual.';}
    };
    stop.onclick=stopCamera;
    window.addEventListener('pagehide',()=>{document.querySelectorAll('#qr-reader video').forEach(v=>v.srcObject?.getTracks().forEach(t=>t.stop()));});
    identity();calculate();
})();
