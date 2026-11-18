'use strict';
document.addEventListener('DOMContentLoaded', function(){
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('mobileBackdrop');
  const menu = document.getElementById('mobileMenu');
  const toggle = (open) => {
    if(!sidebar)return;
    sidebar.classList.toggle('opened',open);
    backdrop?.classList.toggle('visible',open);
    document.body.classList.toggle('menu-open',open);
  };
  menu?.addEventListener('click',()=>toggle(!sidebar.classList.contains('opened')));
  backdrop?.addEventListener('click',()=>toggle(false));
  document.querySelectorAll('[data-copy]').forEach(btn=>btn.addEventListener('click',async()=>{
    const target=document.getElementById(btn.dataset.copy);
    if(!target)return;
    const text=target.textContent.trim();
    try{await navigator.clipboard.writeText(text);btn.textContent='Copied ✓';setTimeout(()=>btn.textContent='Copy caption',2000);}catch(e){prompt('Caption:',text);}
  }));
  // Telegram auth is dispatched to a dedicated background queue. Poll only the local cached state.
  const telegramOperation = document.getElementById('telegramOperation');
  if (telegramOperation) {
    const hint = document.getElementById('telegramWorkerHint');
    const statusUrl = telegramOperation.dataset.statusUrl;
    let checks = 0;
    const poll = async () => {
      checks++;
      try {
        const response = await fetch(statusUrl, {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}});
        if (response.ok) {
          const result = await response.json();
          if (!result.busy) { window.location.reload(); return; }
        }
      } catch (_) { /* Temporary local connectivity issue: keep polling. */ }
      if (checks >= 5 && hint) hint.textContent = 'Still waiting. Make sure the Telegram queue worker is running: php artisan queue:work database --queue=telegram --tries=1 --timeout=110';
      window.setTimeout(poll, 2500);
    };
    window.setTimeout(poll, 2500);
  }
  document.querySelectorAll('.file-drop input[type=file]').forEach(input=>input.addEventListener('change',()=>{
    const hint=input.closest('.file-drop')?.querySelector('span');
    if(hint && input.files.length)hint.textContent=input.files[0].name;
  }));
});
