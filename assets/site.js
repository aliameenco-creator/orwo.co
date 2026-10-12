/* Progressive enhancement only. Content, forms' fallback messages and links remain available without JS. */
(() => {
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  if (!reduced.matches && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
      if (entry.isIntersecting) { entry.target.classList.add('reveal-seen'); observer.unobserve(entry.target); }
    }), {threshold: .06});
    document.querySelectorAll('.section-top,.image-tile,.history-column,.film-card,.gallery-photo,.orwoco-post-grid>li').forEach(el => observer.observe(el));
  }
  const dialog = document.createElement('dialog');
  dialog.className = 'orwoco-lightbox';
  dialog.setAttribute('aria-label', 'Community photograph');
  dialog.innerHTML = '<button type="button" aria-label="Close photograph">×</button><img alt=""><p></p>';
  document.body.append(dialog);
  dialog.querySelector('button').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
  document.querySelectorAll('.orwoco-gallery a').forEach(a => a.addEventListener('click', e => {
    if (!dialog.showModal || e.ctrlKey || e.metaKey) return;
    e.preventDefault(); const img = a.querySelector('img');
    dialog.querySelector('img').src = a.href; dialog.querySelector('img').alt = img.alt;
    dialog.querySelector('p').textContent = img.alt; dialog.showModal();
  }));
  // The native editable image link is the video source. No third-party request occurs before click.
  document.querySelectorAll('.orwoco-video a').forEach(a => a.addEventListener('click', e => {
    if (e.ctrlKey || e.metaKey) return;
    let url; try { url = new URL(a.href); } catch { return; }
    const id = url.searchParams.get('v');
    let src = '';
    if (['www.youtube.com','youtube.com'].includes(url.hostname) && /^[A-Za-z0-9_-]{11}$/.test(id || '')) src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1';
    if (url.hostname === 'vimeo.com' && /^\/\d+$/.test(url.pathname)) src = 'https://player.vimeo.com/video' + url.pathname + '?autoplay=1&dnt=1';
    if (!src) return;
    e.preventDefault(); const frame = document.createElement('iframe');
    frame.src = src;
    frame.title = a.querySelector('img')?.alt || 'ORWO film';
    frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
    frame.referrerPolicy = 'strict-origin-when-cross-origin'; frame.allowFullscreen = true;
    a.parentElement.replaceChildren(frame); frame.focus();
  }));
  document.querySelectorAll('[data-orwoco-form]').forEach(form => {
    const opened = performance.now();
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const status = form.querySelector('.form-status'), button = form.querySelector('[type=submit]');
      form.querySelector('[data-elapsed]').value = Math.round(performance.now() - opened);
      form.querySelectorAll('[aria-invalid]').forEach(x => x.removeAttribute('aria-invalid'));
      form.querySelectorAll('.field-error').forEach(x => x.textContent = '');
      button.disabled = true; const label = button.textContent; button.textContent = 'Sending…';
      try {
        const response = await fetch(form.getAttribute('action'), {method:'POST', body:new FormData(form), headers:{Accept:'application/json'}, credentials:'same-origin'});
        const data = await response.json();
        status.replaceChildren(); const msg = document.createElement('p');
        msg.className = data.ok ? 'form-success' : 'form-error'; msg.textContent = data.message; status.append(msg);
        for (const [key,message] of Object.entries(data.errors || {})) {
          const input = form.elements.namedItem('orwoco_' + key);
          if (input) { input.setAttribute('aria-invalid','true'); input.closest('.field').querySelector('.field-error').textContent = message; }
        }
        if (data.ok) form.reset();
        else form.querySelector('[aria-invalid]')?.focus();
      } catch {
        status.textContent = 'Your message could not be confirmed. Please try again or email sales@filmotec.de.';
      } finally { button.disabled = false; button.textContent = label; }
    });
  });
})();
