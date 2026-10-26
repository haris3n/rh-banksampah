// Read-only navigation enhancement. Writes retain Laravel's normal form/CSRF flow.
(() => {
    if (!/^\/petugas(?:\/nasabah(?:\/\d+)?)?$/.test(location.pathname)) return;
    let active, timer;
    const message=document.createElement('div');message.className='report-feedback';message.hidden=true;message.setAttribute('role','status');document.body.append(message);
    async function load(url,push=true){
        active?.abort();const controller=new AbortController();active=controller;
        const main=document.querySelector('main');main.setAttribute('aria-busy','true');message.hidden=false;message.textContent='Memuat data…';
        try{
            const response=await fetch(url,{signal:controller.signal,headers:{Accept:'text/html'}});
            if(!response.ok||response.redirected)throw new Error();
            const doc=new DOMParser().parseFromString(await response.text(),'text/html');const next=doc.querySelector('main');
            if(!next||controller!==active)return;
            const focused=document.activeElement?.name;
            main.replaceWith(next);if(push)history.pushState(null,'',url);
            if(focused){const input=Array.from(next.querySelectorAll('input')).find(el=>el.name===focused);input?.focus({preventScroll:true});if(input&&input.type==='text')input.setSelectionRange(input.value.length,input.value.length);}
            message.textContent='Data diperbarui.';
        }catch(e){if(e.name!=='AbortError')message.textContent='Data gagal dimuat. Isian sebelumnya tetap tersedia; periksa koneksi atau masuk kembali.';}
        finally{if(controller===active)document.querySelector('main').removeAttribute('aria-busy');}
    }
    function eligible(form){return form && form.method.toLowerCase()==='get' && !new URL(form.action).pathname.endsWith('/export');}
    function submit(form){load(new URL(form.action).pathname+'?'+new URLSearchParams(new FormData(form)));}
    document.addEventListener('submit',e=>{if(eligible(e.target)){e.preventDefault();clearTimeout(timer);submit(e.target);}});
    document.addEventListener('input',e=>{if(e.target.name==='q'&&eligible(e.target.form)){clearTimeout(timer);active?.abort();timer=setTimeout(()=>submit(e.target.form),350);}});
    document.addEventListener('change',e=>{if(eligible(e.target.form)&&e.target.tagName==='SELECT')submit(e.target.form);});
    document.addEventListener('click',e=>{const a=e.target.closest('main a');if(!a||e.ctrlKey||e.metaKey||e.shiftKey||e.button!==0)return;const url=new URL(a.href);if(url.origin===location.origin&&/^\/petugas(?:\/nasabah(?:\/\d+)?)?$/.test(url.pathname)){e.preventDefault();clearTimeout(timer);load(url.href);}});
    window.addEventListener('popstate',()=>load(location.href,false));
})();
