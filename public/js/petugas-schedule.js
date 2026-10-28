document.addEventListener('click', event => {
    if (!event.target.closest('[data-new-schedule]')) return;
    const dialog=document.getElementById('jadwal-dialog');
    const clone=dialog.cloneNode(true);
    clone.id='new-schedule-dialog';
    clone.querySelector('h2').textContent='Tambah Jadwal Berikutnya';
    clone.querySelector('[name=id]')?.remove();
    clone.querySelector('[name=tanggal]').value='';
    clone.querySelector('[name=catatan]').value='';
    clone.querySelectorAll('[id]').forEach(el=>el.id='new-'+el.id);
    clone.querySelectorAll('label[for]').forEach(el=>el.htmlFor='new-'+el.htmlFor);
    clone.querySelectorAll('[onclick]').forEach(button=>{button.removeAttribute('onclick');button.onclick=()=>clone.close();});
    clone.addEventListener('close',()=>clone.remove(),{once:true});
    document.body.append(clone);
    clone.showModal();
});
